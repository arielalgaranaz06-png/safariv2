<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pedido_id = $_GET['id'];

// Obtener pedido
$stmt = $pdo->prepare("
    SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre
    FROM pedidos p
    INNER JOIN mesas m ON p.mesa_id = m.id
    INNER JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener items
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre
    FROM pedido_items pi
    INNER JOIN productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'pedido' => $pedido,
    'items' => $items
]);
?>