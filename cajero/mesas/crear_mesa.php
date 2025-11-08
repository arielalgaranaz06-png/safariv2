<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$numero = trim($_POST['numero']);
$capacidad = intval($_POST['capacidad']);
$estado = $_POST['estado'];

// Validaciones
if (empty($numero) || $capacidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    // Verificar si ya existe una mesa con ese número
    $stmt = $pdo->prepare("SELECT id FROM mesas WHERE numero = ?");
    $stmt->execute([$numero]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una mesa con ese número']);
        exit;
    }

    // Insertar nueva mesa
    $stmt = $pdo->prepare("INSERT INTO mesas (numero, capacidad, estado) VALUES (?, ?, ?, ?)");
    $stmt->execute([$numero, $capacidad, $estado]);

    echo json_encode(['success' => true, 'message' => 'Mesa creada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
}
?>