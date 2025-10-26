<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pedido_id = intval($_GET['id'] ?? 0);
    
    error_log("DEBUG: Intentando obtener pedido ID: " . $pedido_id);

    if (!$pedido_id) {
        echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
        exit;
    }

    // Obtener información del pedido
    $stmt_pedido = $pdo->prepare("
        SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre,
               um.nombre as modificado_por, p.fecha_modificacion
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN usuarios um ON p.usuario_modificacion_id = um.id
        WHERE p.id = ?
    ");
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    error_log("DEBUG: Pedido encontrado: " . ($pedido ? 'SÍ' : 'NO'));

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    // Obtener items del pedido
    $stmt_items = $pdo->prepare("
        SELECT pi.*, pr.nombre, pr.categoria
        FROM pedido_items pi
        INNER JOIN productos pr ON pi.producto_id = pr.id
        WHERE pi.pedido_id = ?
    ");
    $stmt_items->execute([$pedido_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    error_log("DEBUG: Items encontrados: " . count($items));

    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'items' => $items,
        'debug' => [
            'pedido_id' => $pedido_id,
            'items_count' => count($items)
        ]
    ]);

} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>