<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_GET['action'] == 'obtener') {
    try {
        $stmt = $pdo->query("SELECT * FROM productos ORDER BY categoria, nombre");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'productos' => $productos
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener productos: ' . $e->getMessage()
        ]);
    }
}
?>