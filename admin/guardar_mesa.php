<?php
session_start();
require_once '../db.php';

// Verificar sesión y rol
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No ha iniciado sesión']);
    exit;
}

if ($_SESSION['rol'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permisos de administrador']);
    exit;
}

header('Content-Type: application/json');

// Verificar que la acción esté presente
if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
    exit;
}

try {
    if ($_POST['action'] == 'crear') {
        $numero = trim($_POST['numero']);
        $capacidad = intval($_POST['capacidad']);
        
        if (empty($numero)) {
            echo json_encode(['success' => false, 'message' => 'El número de mesa es requerido']);
            exit;
        }
        
        // Verificar si ya existe una mesa con ese número
        $stmt = $pdo->prepare("SELECT id FROM mesas WHERE numero = ?");
        $stmt->execute([$numero]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una mesa con ese número']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO mesas (numero, capacidad) VALUES (?, ?)");
        $stmt->execute([$numero, $capacidad]);
        
        echo json_encode(['success' => true, 'message' => 'Mesa creada exitosamente']);
        
    } elseif ($_POST['action'] == 'editar') {
        $id = intval($_POST['id']);
        $numero = trim($_POST['numero']);
        $capacidad = intval($_POST['capacidad']);
        $estado = $_POST['estado'];
        
        if (empty($numero)) {
            echo json_encode(['success' => false, 'message' => 'El número de mesa es requerido']);
            exit;
        }
        
        // Verificar si ya existe otra mesa con ese número
        $stmt = $pdo->prepare("SELECT id FROM mesas WHERE numero = ? AND id != ?");
        $stmt->execute([$numero, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otra mesa con ese número']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE mesas SET numero = ?, capacidad = ?, estado = ? WHERE id = ?");
        $stmt->execute([$numero, $capacidad, $estado, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Mesa actualizada exitosamente']);
        
    } elseif ($_POST['action'] == 'eliminar') {
        $id = intval($_POST['id']);
        
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
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    error_log("Error en guardar_mesa.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>