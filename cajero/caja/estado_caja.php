<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        ORDER BY fecha_apertura DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'caja_abierta' => !!$caja,
        'caja' => $caja
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>