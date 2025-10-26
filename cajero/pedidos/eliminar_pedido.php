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
    $pedido_id = intval($input['pedido_id'] ?? 0);

    if (!$pedido_id) {
        echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
        exit;
    }

    // Verificar si el pedido existe y obtener información
    $stmt = $pdo->prepare("SELECT estado, mesa_id FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }
    
    if ($pedido['estado'] === 'pagado') {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar un pedido ya pagado']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Eliminar items del pedido
    $stmt = $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);
    
    // Eliminar pedido
    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    
    // Liberar la mesa si estaba ocupada
    if ($pedido['mesa_id']) {
        $stmt_mesa = $pdo->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?");
        $stmt_mesa->execute([$pedido['mesa_id']]);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido eliminado correctamente']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>