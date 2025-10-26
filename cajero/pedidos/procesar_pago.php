<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = intval($input['pedido_id']);
$metodo_pago = $input['metodo_pago'];
$monto_efectivo = floatval($input['monto_efectivo']);
$monto_qr = floatval($input['monto_qr']);
$total_pagar = floatval($input['total_pagar']);

try {
    // Obtener información de la caja abierta
    $stmt_caja = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        ORDER BY fecha_apertura DESC LIMIT 1
    ");
    $stmt_caja->execute([$_SESSION['usuario_id']]);
    $caja = $stmt_caja->fetch();
    
    if (!$caja) {
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
        exit;
    }
    
    // Calcular cambio solo para efectivo
    $cambio = 0;
    if ($metodo_pago === 'efectivo') {
        $cambio = $monto_efectivo - $total_pagar;
    } elseif ($metodo_pago === 'mixto') {
        $cambio = 0; // En pago mixto no hay cambio
    }
    
    // Actualizar pedido
    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET estado = 'pagado', 
            metodo_pago = ?, 
            monto_recibido = ?,
            cambio = ?,
            fecha_pago = NOW(),
            caja_id = ?
        WHERE id = ?
    ");
    
    $monto_total_recibido = $monto_efectivo + $monto_qr;
    $stmt->execute([$metodo_pago, $monto_total_recibido, $cambio, $caja['id'], $pedido_id]);
    
    // Registrar movimientos de caja
    if ($monto_efectivo > 0) {
        $stmt_mov = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, pedido_id, tipo, monto, descripcion, metodo_pago)
            VALUES (?, ?, 'ingreso', ?, 'Venta - Efectivo', 'efectivo')
        ");
        $stmt_mov->execute([$caja['id'], $pedido_id, $monto_efectivo]);
    }
    
    if ($monto_qr > 0) {
        $stmt_mov = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, pedido_id, tipo, monto, descripcion, metodo_pago)
            VALUES (?, ?, 'ingreso', ?, 'Venta - QR', 'qr')
        ");
        $stmt_mov->execute([$caja['id'], $pedido_id, $monto_qr]);
    }
    
    // Registrar cambio si aplica
    if ($cambio > 0) {
        $stmt_mov = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, pedido_id, tipo, monto, descripcion, metodo_pago)
            VALUES (?, ?, 'egreso', ?, 'Cambio entregado', 'efectivo')
        ");
        $stmt_mov->execute([$caja['id'], $pedido_id, $cambio]);
    }
    
    // Liberar la mesa
    $stmt_mesa = $pdo->prepare("
        UPDATE mesas m
        JOIN pedidos p ON m.id = p.mesa_id
        SET m.estado = 'libre'
        WHERE p.id = ?
    ");
    $stmt_mesa->execute([$pedido_id]);
    
    echo json_encode(['success' => true, 'message' => 'Pago procesado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>