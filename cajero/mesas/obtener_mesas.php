<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT * FROM mesas 
        ORDER BY 
            CASE 
                WHEN numero REGEXP '^[0-9]+$' THEN 0 
                ELSE 1 
            END,
            CAST(REPLACE(numero, 'p', '') AS UNSIGNED),
            numero
    ");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'mesas' => $mesas]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>