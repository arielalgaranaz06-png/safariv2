<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pedido_id = $_POST['pedido_id'] ?? null;
    $efectivo = floatval($_POST['efectivo'] ?? 0);
    $qr = floatval($_POST['qr'] ?? 0);
    $cambio = floatval($_POST['cambio'] ?? 0);
    $nota = $_POST['nota'] ?? '';
    $caja_id = $_POST['caja_id'] ?? null;

    if (!$pedido_id || !$caja_id) {
        throw new Exception('Datos incompletos');
    }

    // Obtener información del pedido
    $stmt_pedido = $pdo->prepare("
        SELECT p.*, m.id as mesa_id, m.numero as mesa_numero
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        WHERE p.id = ?
    ");
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }

    if ($pedido['estado'] == 'pagado') {
        throw new Exception('El pedido ya fue pagado');
    }

    $total_pedido = floatval($pedido['total']);
    $total_pagado = $efectivo + $qr;

    if ($total_pagado < $total_pedido) {
        throw new Exception('El monto pagado es menor al total del pedido');
    }

    $pdo->beginTransaction();

    // Actualizar estado del pedido
    $stmt_update = $pdo->prepare("
        UPDATE pedidos 
        SET estado = 'pagado', 
            metodo_pago = ?,
            monto_recibido = ?,
            cambio = ?,
            fecha_pago = NOW(),
            notas = ?,
            caja_id = ?
        WHERE id = ?
    ");

    $metodo_pago = '';
    if ($efectivo > 0 && $qr > 0) {
        $metodo_pago = 'mixto';
    } elseif ($efectivo > 0) {
        $metodo_pago = 'efectivo';
    } elseif ($qr > 0) {
        $metodo_pago = 'qr';
    }

    $stmt_update->execute([
        $metodo_pago,
        $total_pagado,
        $cambio,
        $nota,
        $caja_id,
        $pedido_id
    ]);

    // Actualizar estadísticas de la caja
    $stmt_caja = $pdo->prepare("
        UPDATE caja_control 
        SET total_ventas = total_ventas + ?,
            total_efectivo = total_efectivo + ?,
            total_qr = total_qr + ?,
            total_pedidos = total_pedidos + 1
        WHERE id = ?
    ");
    $stmt_caja->execute([$total_pedido, $efectivo, $qr, $caja_id]);

    // Liberar mesa
    $stmt_mesa = $pdo->prepare("
        UPDATE mesas 
        SET estado = 'libre' 
        WHERE id = ?
    ");
    $stmt_mesa->execute([$pedido['mesa_id']]);

    // Registrar movimientos de caja
    if ($efectivo > 0) {
        $stmt_mov_efectivo = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, pedido_id, tipo, monto, descripcion, metodo_pago, fecha_movimiento)
            VALUES (?, ?, 'ingreso', ?, ?, 'efectivo', NOW())
        ");
        $stmt_mov_efectivo->execute([
            $caja_id,
            $pedido_id,
            $efectivo,
            "Venta Mesa " . $pedido['mesa_numero'] . " - Efectivo"
        ]);
    }

    if ($qr > 0) {
        $stmt_mov_qr = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, pedido_id, tipo, monto, descripcion, metodo_pago, fecha_movimiento)
            VALUES (?, ?, 'ingreso', ?, ?, 'qr', NOW())
        ");
        $stmt_mov_qr->execute([
            $caja_id,
            $pedido_id,
            $qr,
            "Venta Mesa " . $pedido['mesa_numero'] . " - QR"
        ]);
    }

    // Registrar cambio si existe
    if ($cambio > 0) {
        $stmt_mov_cambio = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, pedido_id, tipo, monto, descripcion, metodo_pago, fecha_movimiento)
            VALUES (?, ?, 'egreso', ?, ?, 'efectivo', NOW())
        ");
        $stmt_mov_cambio->execute([
            $caja_id,
            $pedido_id,
            $cambio,
            "Cambio entregado - Mesa " . $pedido['mesa_numero']
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pago procesado correctamente',
        'pedido_id' => $pedido_id,
        'total' => $total_pedido,
        'efectivo' => $efectivo,
        'qr' => $qr,
        'cambio' => $cambio
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>