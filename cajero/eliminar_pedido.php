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

    // Obtener mesa del pedido
    $stmt = $pdo->prepare("SELECT mesa_id FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    // Eliminar items del pedido
    $stmt = $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);

    // Eliminar pedido
    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);

    // Liberar mesa
    $stmt = $pdo->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?");
    $stmt->execute([$pedido['mesa_id']]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Pedido eliminado correctamente']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>