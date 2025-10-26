<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM mesas WHERE id = ?");
    $stmt->execute([$id]);
    $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mesa) {
        echo json_encode(['success' => true, 'mesa' => $mesa]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mesa no encontrada']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>