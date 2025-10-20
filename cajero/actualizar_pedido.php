<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pdo->beginTransaction();

    $pedido_id = $_POST['pedido_id'];
    $productos = json_decode($_POST['productos'], true);

    // Calcular nuevo total
    $total = 0;
    foreach ($productos as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }

    // Eliminar items antiguos
    $stmt = $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);

    // Insertar nuevos items
    $stmt = $pdo->prepare("INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    
    foreach ($productos as $producto_id => $item) {
        $stmt->execute([
            $pedido_id,
            $producto_id,
            $item['cantidad'],
            $item['precio']
        ]);
    }

    // Actualizar total del pedido
    $stmt = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
    $stmt->execute([$total, $pedido_id]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>