<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Si no viene como JSON, intentar con POST normal
        $mesa_id = $_POST['mesa_id'] ?? null;
        $items_json = $_POST['items'] ?? '';
        $total = $_POST['total'] ?? 0;
    } else {
        $mesa_id = $input['mesa_id'] ?? null;
        $items_json = $input['items'] ?? '';
        $total = $input['total'] ?? 0;
    }
    
    // Decodificar items
    // Si el frontend envía un array ya (JSON.parse en JS y enviado como application/json),
    // $items_json llegará como array; si envía una cadena JSON, la decodificamos.
    if (is_array($items_json)) {
        $items = $items_json;
    } else {
        $items = json_decode($items_json, true);
    }
    
    // Normalizar tipos
    $mesa_id = intval($mesa_id);
    $total = floatval($total);
    
    if (!$mesa_id || empty($items)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Datos incompletos: mesa_id o items vacíos'
        ]);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Obtener caja abierta
    $stmt_caja = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        LIMIT 1
    ");
    $stmt_caja->execute([$_SESSION['usuario_id']]);
    $caja = $stmt_caja->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
        exit;
    }
    
    // Crear pedido
    $stmt_pedido = $pdo->prepare("
        INSERT INTO pedidos (mesa_id, usuario_id, estado, total, caja_id, fecha_pedido)
        VALUES (?, ?, 'pendiente', ?, ?, NOW())
    ");
    $stmt_pedido->execute([$mesa_id, $_SESSION['usuario_id'], $total, $caja['id']]);
    $pedido_id = $pdo->lastInsertId();
    
    // Insertar items
    $stmt_item = $pdo->prepare("
        INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $stmt_item->execute([
            $pedido_id,
            $item['producto_id'],
            $item['cantidad'],
            $item['precio'],
            $subtotal
        ]);
    }
    
    // Actualizar mesa a ocupada
    $stmt_mesa = $pdo->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ?");
    $stmt_mesa->execute([$mesa_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido creado correctamente',
        'pedido_id' => $pedido_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>