<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'crear':
            crearProducto();
            break;
            
        case 'editar':
            editarProducto();
            break;
            
        case 'eliminar':
            eliminarProducto();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function crearProducto() {
    global $pdo;
    
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $categoria = $_POST['categoria'];
    $activo = intval($_POST['activo']);
    
    // Validaciones
    if (empty($nombre) || $precio <= 0 || empty($categoria)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos y el precio debe ser mayor a 0']);
        return;
    }
    
    // Verificar si ya existe un producto con el mismo nombre
    $stmt = $pdo->prepare("SELECT id FROM productos WHERE nombre = ?");
    $stmt->execute([$nombre]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un producto con ese nombre']);
        return;
    }
    
    // Insertar nuevo producto
    $stmt = $pdo->prepare("INSERT INTO productos (nombre, precio, categoria, activo) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $precio, $categoria, $activo]);
    
    echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente']);
}

function editarProducto() {
    global $pdo;
    
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $categoria = $_POST['categoria'];
    $activo = intval($_POST['activo']);
    
    // Validaciones
    if (empty($nombre) || $precio <= 0 || empty($categoria)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos y el precio debe ser mayor a 0']);
        return;
    }
    
    // Verificar si ya existe otro producto con el mismo nombre
    $stmt = $pdo->prepare("SELECT id FROM productos WHERE nombre = ? AND id != ?");
    $stmt->execute([$nombre, $id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe otro producto con ese nombre']);
        return;
    }
    
    // Actualizar producto
    $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, precio = ?, categoria = ?, activo = ? WHERE id = ?");
    $stmt->execute([$nombre, $precio, $categoria, $activo, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
}

function eliminarProducto() {
    global $pdo;
    
    $id = intval($_POST['id']);
    
    // Verificar si el producto existe
    $stmt = $pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        echo json_encode(['success' => false, 'message' => 'El producto no existe']);
        return;
    }
    
    // Verificar si el producto está siendo usado en pedidos
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pedido_items WHERE producto_id = ?");
    $stmt->execute([$id]);
    $uso = $stmt->fetch();
    
    if ($uso['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar el producto porque está siendo usado en pedidos existentes']);
        return;
    }
    
    // Eliminar producto
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente']);
}
?>