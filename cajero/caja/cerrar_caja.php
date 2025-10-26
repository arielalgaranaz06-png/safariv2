<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener caja abierta
    $stmt_caja = $pdo->prepare("
        SELECT id, monto_inicial FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        ORDER BY fecha_apertura DESC 
        LIMIT 1
    ");
    $stmt_caja->execute([$_SESSION['usuario_id']]);
    $caja = $stmt_caja->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
        exit;
    }
    
    // Calcular totales de la caja
    $stmt_ventas = $pdo->prepare("
        SELECT 
            COUNT(*) as total_pedidos,
            COALESCE(SUM(total), 0) as total_ventas,
            COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END), 0) as total_efectivo,
            COALESCE(SUM(CASE WHEN metodo_pago = 'qr' THEN total ELSE 0 END), 0) as total_qr,
            COALESCE(SUM(CASE WHEN metodo_pago = 'mixto' THEN total ELSE 0 END), 0) as total_mixto
        FROM pedidos 
        WHERE caja_id = ? AND estado = 'pagado'
    ");
    $stmt_ventas->execute([$caja['id']]);
    $ventas = $stmt_ventas->fetch(PDO::FETCH_ASSOC);
    
    $pdo->beginTransaction();
    
    // Actualizar caja con los totales
    $stmt_update = $pdo->prepare("
        UPDATE caja_control 
        SET estado = 'cerrada', 
            fecha_cierre = NOW(),
            total_ventas = ?,
            total_efectivo = ?,
            total_qr = ?,
            total_pedidos = ?
        WHERE id = ?
    ");
    $stmt_update->execute([
        $ventas['total_ventas'],
        $ventas['total_efectivo'],
        $ventas['total_qr'],
        $ventas['total_pedidos'],
        $caja['id']
    ]);
    
    // Registrar movimiento de cierre
    $stmt_movimiento = $pdo->prepare("
        INSERT INTO caja_movimientos 
        (caja_id, tipo, monto, descripcion, fecha_movimiento) 
        VALUES (?, 'cierre', ?, ?, NOW())
    ");
    
    $descripcion = "Cierre de caja - Ventas: Bs. " . number_format($ventas['total_ventas'], 2);
    $stmt_movimiento->execute([$caja['id'], $ventas['total_ventas'], $descripcion]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Caja cerrada correctamente',
        'data' => [
            'total_ventas' => $ventas['total_ventas'],
            'total_efectivo' => $ventas['total_efectivo'],
            'total_qr' => $ventas['total_qr'],
            'total_pedidos' => $ventas['total_pedidos']
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>