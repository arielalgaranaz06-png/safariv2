<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// Verificar si hay una caja abierta para este cajero
$stmt_caja = $pdo->prepare("
    SELECT * FROM caja_control 
    WHERE usuario_id = ? AND estado = 'abierta'
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt_caja->execute([$_SESSION['usuario_id']]);
$caja_abierta = $stmt_caja->fetch(PDO::FETCH_ASSOC);

$pedidos = [];
if ($caja_abierta) {
    // Obtener pedidos pendientes, en preparación y listos
    $stmt = $pdo->query("
        SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre,
               COUNT(pi.id) as total_items
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN pedido_items pi ON p.id = pi.pedido_id
        WHERE p.estado IN ('pendiente', 'preparacion', 'listo')
        GROUP BY p.id
        ORDER BY p.fecha_pedido DESC
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener items para cada pedido
    foreach ($pedidos as &$pedido) {
        $stmt_items = $pdo->prepare("
            SELECT pi.*, pr.nombre 
            FROM pedido_items pi
            INNER JOIN productos pr ON pi.producto_id = pr.id
            WHERE pi.pedido_id = ?
        ");
        $stmt_items->execute([$pedido['id']]);
        $pedido['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
}

header('Content-Type: application/json');
// Configurar zona horaria de Bolivia
date_default_timezone_set('America/La_Paz');

echo json_encode([
    'success' => true,
    'pedidos' => $pedidos,
    'caja_abierta' => $caja_abierta ? true : false,
    'timestamp' => date('H:i:s')
]);
?>