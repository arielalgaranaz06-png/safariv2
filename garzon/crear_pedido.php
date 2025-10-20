<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'garzon') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pdo->beginTransaction();

    $mesa_id = $_POST['mesa_id'];
    $usuario_id = $_SESSION['usuario_id'];
    $productos = json_decode($_POST['productos'], true);

    // Calcular total
    $total = 0;
    foreach ($productos as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }

    // Crear pedido
    $stmt = $pdo->prepare("INSERT INTO pedidos (mesa_id, usuario_id, estado, total) VALUES (?, ?, 'pendiente', ?)");
    $stmt->execute([$mesa_id, $usuario_id, $total]);
    $pedido_id = $pdo->lastInsertId();

    // Insertar items del pedido
    $stmt = $pdo->prepare("INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    
    foreach ($productos as $producto_id => $item) {
        $stmt->execute([
            $pedido_id,
            $producto_id,
            $item['cantidad'],
            $item['precio']
        ]);
    }

    // Actualizar estado de la mesa
    $stmt = $pdo->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ?");
    $stmt->execute([$mesa_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>