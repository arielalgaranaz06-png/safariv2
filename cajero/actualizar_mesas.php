<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT * FROM mesas 
        ORDER BY 
            CASE 
                WHEN numero REGEXP '^[0-9]+$' THEN 0 
                ELSE 1 
            END,
            CAST(numero AS UNSIGNED),
            numero
    ");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'mesas' => $mesas]);
} catch (Exception $e) {
    error_log("Error en actualizar_mesas.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar mesas: ' . $e->getMessage()]);
}
?>