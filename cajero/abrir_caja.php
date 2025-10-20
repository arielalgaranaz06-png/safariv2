<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Verificar que no haya una caja abierta para este usuario en el mismo turno
    $turno = $_POST['turno'] ?? '';
    
    $stmt_check = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta' AND turno = ?
    ");
    $stmt_check->execute([$_SESSION['usuario_id'], $turno]);
    
    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya tiene una caja abierta para el turno ' . $turno]);
        exit;
    }
    
    // Obtener denominaciones
    $denominaciones = [];
    
    if (isset($_POST['denominaciones']) && !empty($_POST['denominaciones'])) {
        $denominaciones = json_decode($_POST['denominaciones'], true);
    }
    
    if (empty($denominaciones)) {
        $denominaciones = $_POST;
    }
    
    if (empty($turno)) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar un turno']);
        exit;
    }
    
    // Calcular monto inicial
    $monto_inicial = 0;
    $denominaciones_activas = [];
    
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
        $cantidad = 0;
        
        if (isset($denominaciones[$tipo])) {
            $cantidad = intval($denominaciones[$tipo]);
        } elseif (isset($denominaciones['denominaciones'][$tipo])) {
            $cantidad = intval($denominaciones['denominaciones'][$tipo]);
        } elseif (isset($_POST[$tipo])) {
            $cantidad = intval($_POST[$tipo]);
        }
        
        if ($cantidad > 0) {
            $subtotal = $cantidad * $info['valor'];
            $monto_inicial += $subtotal;
            
            $denominaciones_activas[] = [
                'tipo' => $tipo,
                'valor' => $info['valor'],
                'cantidad' => $cantidad,
                'subtotal' => $subtotal,
                'nombre' => $info['nombre']
            ];
        }
    }
    
    if ($monto_inicial <= 0) {
        echo json_encode(['success' => false, 'message' => 'Debe ingresar al menos una denominación con cantidad mayor a 0']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Insertar caja con turno específico
    $stmt_caja = $pdo->prepare("
        INSERT INTO caja_control 
        (usuario_id, turno, monto_inicial, fecha_apertura, estado, denominaciones_apertura, total_ventas, total_efectivo, total_qr, total_pedidos)
        VALUES (?, ?, ?, NOW(), 'abierta', ?, 0, 0, 0, 0)
    ");
    
    $stmt_caja->execute([
        $_SESSION['usuario_id'],
        $turno,
        $monto_inicial,
        json_encode($denominaciones_activas)
    ]);
    
    $caja_id = $pdo->lastInsertId();
    
    // Registrar movimiento
    $stmt_movimiento = $pdo->prepare("
        INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, fecha_movimiento)
        VALUES (?, 'apertura', ?, ?, NOW())
    ");
    
    $descripcion = "Apertura de caja - Turno: " . strtoupper($turno);
    $stmt_movimiento->execute([$caja_id, $monto_inicial, $descripcion]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Caja abierta correctamente para el turno ' . $turno,
        'caja_id' => $caja_id,
        'monto_inicial' => $monto_inicial,
        'turno' => $turno
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en abrir_caja.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al abrir la caja: ' . $e->getMessage()]);
}
?>