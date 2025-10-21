<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_POST['action'] == 'crear') {
    $numero = $_POST['numero'];
    $capacidad = $_POST['capacidad'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO mesas (numero, capacidad) VALUES (?, ?)");
        $stmt->execute([$numero, $capacidad]);
        
        echo json_encode(['success' => true, 'message' => 'Mesa creada exitosamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear mesa: ' . $e->getMessage()]);
    }
} elseif ($_POST['action'] == 'editar') {
    $id = $_POST['id'];
    $numero = $_POST['numero'];
    $capacidad = $_POST['capacidad'];
    $estado = $_POST['estado'];
    
    try {
        $stmt = $pdo->prepare("UPDATE mesas SET numero = ?, capacidad = ?, estado = ? WHERE id = ?");
        $stmt->execute([$numero, $capacidad, $estado, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Mesa actualizada exitosamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar mesa: ' . $e->getMessage()]);
    }
} elseif ($_POST['action'] == 'eliminar') {
    $id = $_POST['id'];
    
    try {
        // Verificar si la mesa tiene pedidos activos
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE mesa_id = ? AND estado != 'pagado'");
        $stmt->execute([$id]);
        $pedidos_activos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedidos_activos['total'] > 0) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar una mesa con pedidos activos']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM mesas WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Mesa eliminada exitosamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar mesa: ' . $e->getMessage()]);
    }
}
?>