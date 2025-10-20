<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // OBTENER AUTOMÁTICAMENTE la caja abierta del usuario
    $stmt_caja = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        ORDER BY fecha_apertura DESC 
        LIMIT 1
    ");
    $stmt_caja->execute([$_SESSION['usuario_id']]);
    $caja_abierta = $stmt_caja->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja_abierta) {
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta para cerrar']);
        exit;
    }
    
    $caja_id = $caja_abierta['id'];
    $denominaciones_cierre = $_POST['denominaciones'] ?? '{}';
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Obtener datos completos de la caja
    $stmt_caja_detalle = $pdo->prepare("SELECT * FROM caja_control WHERE id = ?");
    $stmt_caja_detalle->execute([$caja_id]);
    $caja = $stmt_caja_detalle->fetch(PDO::FETCH_ASSOC);
    
    // Procesar denominaciones de cierre
    $denominaciones_data = json_decode($denominaciones_cierre, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Si no hay denominaciones, usar las de apertura como base
        $denominaciones_apertura = json_decode($caja['denominaciones_apertura'] ?? '[]', true);
        $denominaciones_data = [];
        foreach ($denominaciones_apertura as $denom) {
            $denominaciones_data[$denom['tipo']] = $denom['cantidad'];
        }
    }
    
    // Calcular monto final en efectivo
    $monto_final_efectivo = 0;
    $denominaciones_cierre_array = [];
    
    $denominaciones_estructura = [
        'm1' => ['valor' => 1, 'nombre' => 'Moneda Bs. 1'],
        'm2' => ['valor' => 2, 'nombre' => 'Moneda Bs. 2'],
        'm5' => ['valor' => 5, 'nombre' => 'Moneda Bs. 5'],
        'b10' => ['valor' => 10, 'nombre' => 'Billete Bs. 10'],
        'b20' => ['valor' => 20, 'nombre' => 'Billete Bs. 20'],
        'b50' => ['valor' => 50, 'nombre' => 'Billete Bs. 50'],
        'b100' => ['valor' => 100, 'nombre' => 'Billete Bs. 100'],
        'b200' => ['valor' => 200, 'nombre' => 'Billete Bs. 200']
    ];
    
    foreach ($denominaciones_estructura as $tipo => $info) {
        $cantidad = isset($denominaciones_data[$tipo]) ? intval($denominaciones_data[$tipo]) : 0;
        
        if ($cantidad >= 0) {
            $subtotal = $cantidad * $info['valor'];
            $monto_final_efectivo += $subtotal;
            
            $denominaciones_cierre_array[] = [
                'tipo' => $tipo,
                'valor' => $info['valor'],
                'cantidad' => $cantidad,
                'subtotal' => $subtotal,
                'nombre' => $info['nombre']
            ];
        }
    }
    
    // Calcular diferencia
    $efectivo_teorico = $caja['monto_inicial'] + $caja['total_efectivo'];
    
    // Restar los cambios dados (egresos por cambio)
    $stmt_cambios = $pdo->prepare("
        SELECT COALESCE(SUM(ABS(monto)), 0) as total_cambios
        FROM caja_movimientos 
        WHERE caja_id = ? 
        AND tipo = 'egreso'
        AND descripcion LIKE '%cambio%'
    ");
    $stmt_cambios->execute([$caja_id]);
    $cambios_dados = $stmt_cambios->fetch(PDO::FETCH_ASSOC);
    $efectivo_teorico -= $cambios_dados['total_cambios'];
    
    $diferencia = $monto_final_efectivo - $efectivo_teorico;
    
    $pdo->beginTransaction();
    
    // Actualizar caja con cierre
    $stmt_update = $pdo->prepare("
        UPDATE caja_control 
        SET estado = 'cerrada',
            fecha_cierre = NOW(),
            monto_final = ?,
            denominaciones_cierre = ?,
            observaciones = ?
        WHERE id = ?
    ");
    
    $observaciones_completas = $observaciones;
    if (abs($diferencia) > 0.01) {
        $observaciones_completas .= " | Diferencia: Bs. " . number_format($diferencia, 2);
    }
    
    $stmt_update->execute([
        $monto_final_efectivo,
        json_encode($denominaciones_cierre_array),
        $observaciones_completas,
        $caja_id
    ]);
    
    // Registrar movimiento de cierre
    $stmt_movimiento = $pdo->prepare("
        INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, fecha_movimiento)
        VALUES (?, 'cierre', ?, ?, NOW())
    ");
    
    $descripcion_cierre = "Cierre de caja - Efectivo contado: Bs. " . number_format($monto_final_efectivo, 2);
    $stmt_movimiento->execute([$caja_id, $monto_final_efectivo, $descripcion_cierre]);
    
    // Registrar diferencia si existe
    if (abs($diferencia) > 0.01) {
        $stmt_diferencia = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, fecha_movimiento)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $tipo_diferencia = $diferencia > 0 ? 'ingreso' : 'egreso';
        $desc_diferencia = $diferencia > 0 ? 'Sobrante en caja' : 'Faltante en caja';
        
        $stmt_diferencia->execute([$caja_id, $tipo_diferencia, abs($diferencia), $desc_diferencia]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Caja cerrada correctamente',
        'resumen' => [
            'monto_inicial' => $caja['monto_inicial'],
            'total_ventas' => $caja['total_ventas'],
            'total_efectivo' => $caja['total_efectivo'],
            'total_qr' => $caja['total_qr'],
            'monto_final' => $monto_final_efectivo,
            'efectivo_teorico' => $efectivo_teorico,
            'diferencia' => $diferencia,
            'total_pedidos' => $caja['total_pedidos']
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en cerrar_caja.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cerrar caja: ' . $e->getMessage()]);
}
?>