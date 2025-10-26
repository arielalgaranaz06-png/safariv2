<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$id = intval($_POST['id']);

try {
    // Verificar si la mesa tiene pedidos activos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE mesa_id = ? AND estado != 'pagado'");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    if ($result['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar la mesa porque tiene pedidos activos']);
        exit;
    }

    // Eliminar mesa
    $stmt = $pdo->prepare("DELETE FROM mesas WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Mesa eliminada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
}
?>