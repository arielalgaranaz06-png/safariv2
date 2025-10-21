<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'garzon') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pedido_id = $_POST['pedido_id'] ?? null;
$productos = json_decode($_POST['productos'] ?? '{}', true);

if (!$pedido_id || empty($productos)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verificar que el pedido existe y está en estado editable
    $stmt = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }
    
    if ($pedido['estado'] != 'pendiente') {
        throw new Exception('Solo se pueden editar pedidos pendientes');
    }
    
    // Eliminar items anteriores
    $stmt = $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);
    
    // Insertar nuevos items
    $total = 0;
    $stmt = $pdo->prepare("
        INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($productos as $producto_id => $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $total += $subtotal;
        
        $stmt->execute([
            $pedido_id,
            $producto_id,
            $item['cantidad'],
            $item['precio'],
            $subtotal
        ]);
    }
    
    // Actualizar total del pedido
    $stmt = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
    $stmt->execute([$total, $pedido_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido actualizado correctamente',
        'nuevo_total' => $total
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>