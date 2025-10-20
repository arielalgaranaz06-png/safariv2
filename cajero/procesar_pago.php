<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pedido_id = $_POST['pedido_id'] ?? 0;
    $efectivo = floatval($_POST['efectivo'] ?? 0);
    $qr = floatval($_POST['qr'] ?? 0);
    $cambio = floatval($_POST['cambio'] ?? 0);
    $nota = $_POST['nota'] ?? '';
    $caja_id = $_POST['caja_id'] ?? 0;
    
    if (!$pedido_id || !$caja_id) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    // Verificar que la caja esté abierta
    $stmt_caja = $pdo->prepare("SELECT * FROM caja_control WHERE id = ? AND estado = 'abierta'");
    $stmt_caja->execute([$caja_id]);
    $caja = $stmt_caja->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        echo json_encode(['success' => false, 'message' => 'La caja no está abierta']);
        exit;
    }
    
    // Obtener datos del pedido
    $stmt_pedido = $pdo->prepare("
        SELECT p.*, m.id as mesa_id, m.numero as mesa_numero 
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        WHERE p.id = ?
    ");
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }
    
    if ($pedido['estado'] === 'pagado') {
        echo json_encode(['success' => false, 'message' => 'El pedido ya fue pagado']);
        exit;
    }
    
    // Validar pago
    $total_pagado = $efectivo + $qr;
    if ($total_pagado < $pedido['total']) {
        echo json_encode(['success' => false, 'message' => 'Monto insuficiente']);
        exit;
    }
    
    // Verificar si hay suficiente cambio disponible (solo si el pago fue en efectivo)
    if ($cambio > 0 && $efectivo > 0) {
        $denominaciones_actuales = json_decode($caja['denominaciones_apertura'] ?? '[]', true);
        
        // Calcular efectivo disponible en caja
        $efectivo_disponible = $caja['monto_inicial'];
        
        // Sumar ventas en efectivo del día
        $stmt_efectivo = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as total_efectivo
            FROM pedidos 
            WHERE estado = 'pagado' 
            AND metodo_pago LIKE '%efectivo%'
            AND DATE(fecha_pedido) = CURDATE()
        ");
        $stmt_efectivo->execute();
        $efectivo_caja = $stmt_efectivo->fetch(PDO::FETCH_ASSOC);
        $efectivo_disponible += $efectivo_caja['total_efectivo'];
        
        // Restar cambios dados
        $stmt_cambios = $pdo->prepare("
            SELECT COALESCE(SUM(ABS(monto)), 0) as total_cambios
            FROM caja_movimientos 
            WHERE caja_id = ? 
            AND tipo = 'cambio'
        ");
        $stmt_cambios->execute([$caja_id]);
        $cambios_dados = $stmt_cambios->fetch(PDO::FETCH_ASSOC);
        $efectivo_disponible -= $cambios_dados['total_cambios'];
        
        if ($cambio > $efectivo_disponible) {
            echo json_encode([
                'success' => false, 
                'message' => 'No hay suficiente cambio disponible en caja. Disponible: Bs. ' . number_format($efectivo_disponible, 2)
            ]);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    // Construir método de pago
    $metodos = [];
    if ($efectivo > 0) $metodos[] = "Efectivo: Bs. " . number_format($efectivo, 2);
    if ($qr > 0) $metodos[] = "QR: Bs. " . number_format($qr, 2);
    $metodo_pago = implode(" + ", $metodos);
    
    // Actualizar pedido
    $stmt_update_pedido = $pdo->prepare("
        UPDATE pedidos 
        SET estado = 'pagado',
            metodo_pago = ?,
            nota_pago = ?,
            fecha_pago = NOW()
        WHERE id = ?
    ");
    $stmt_update_pedido->execute([$metodo_pago, $nota, $pedido_id]);
    
    // Liberar mesa
    $stmt_mesa = $pdo->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?");
    $stmt_mesa->execute([$pedido['mesa_id']]);
    
    // Registrar movimiento de venta en efectivo
    if ($efectivo > 0) {
        $stmt_mov_efectivo = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, pedido_id, fecha)
            VALUES (?, 'venta_efectivo', ?, ?, ?, NOW())
        ");
        $desc_efectivo = "Venta Mesa {$pedido['mesa_numero']} - Efectivo";
        $stmt_mov_efectivo->execute([$caja_id, $efectivo, $desc_efectivo, $pedido_id]);
    }
    
    // Registrar movimiento de venta QR
    if ($qr > 0) {
        $stmt_mov_qr = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, pedido_id, fecha)
            VALUES (?, 'venta_qr', ?, ?, ?, NOW())
        ");
        $desc_qr = "Venta Mesa {$pedido['mesa_numero']} - QR";
        $stmt_mov_qr->execute([$caja_id, $qr, $desc_qr, $pedido_id]);
    }
    
    // Registrar cambio dado (como egreso de caja)
    if ($cambio > 0) {
        $stmt_mov_cambio = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, pedido_id, fecha)
            VALUES (?, 'cambio', ?, ?, ?, NOW())
        ");
        $desc_cambio = "Cambio entregado - Mesa {$pedido['mesa_numero']}";
        $stmt_mov_cambio->execute([$caja_id, -$cambio, $desc_cambio, $pedido_id]);
    }
    
    // Registrar en tabla de métodos de pago
    if ($efectivo > 0) {
        $stmt_metodo = $pdo->prepare("
            INSERT INTO metodos_pago (pedido_id, metodo, monto, fecha)
            VALUES (?, 'efectivo', ?, NOW())
        ");
        $stmt_metodo->execute([$pedido_id, $efectivo]);
    }
    
    if ($qr > 0) {
        $stmt_metodo = $pdo->prepare("
            INSERT INTO metodos_pago (pedido_id, metodo, monto, fecha)
            VALUES (?, 'qr', ?, NOW())
        ");
        $stmt_metodo->execute([$pedido_id, $qr]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago procesado correctamente',
        'cambio' => $cambio
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>