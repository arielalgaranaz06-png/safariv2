<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$id = intval($_POST['id']);
$numero = trim($_POST['numero']);
$capacidad = intval($_POST['capacidad']);
$estado = $_POST['estado'];

// Validaciones
if (empty($numero) || $capacidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    // Verificar si ya existe otra mesa con ese número
    $stmt = $pdo->prepare("SELECT id FROM mesas WHERE numero = ? AND id != ?");
    $stmt->execute([$numero, $id]);
    
    // CORRECCIÓN: Verificar si realmente existe otra mesa
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe otra mesa con ese número']);
        exit;
    }

    // Actualizar mesa
    $stmt = $pdo->prepare("UPDATE mesas SET numero = ?, capacidad = ?, estado = ? WHERE id = ?");
    $stmt->execute([$numero, $capacidad, $estado, $id]);

    echo json_encode(['success' => true, 'message' => 'Mesa actualizada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>