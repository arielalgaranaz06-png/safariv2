<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'garzon') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener pedidos activos del día actual
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.mesa_id,
            p.estado,
            p.total,
            p.turno,
            p.fecha_pedido as fecha_hora,
            m.numero as mesa_numero,
            u.nombre as garzon_nombre
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        INNER JOIN usuarios u ON p.usuario_id = u.id
        WHERE DATE(p.fecha_pedido) = CURDATE()
        AND p.estado IN ('pendiente', 'preparacion')
        ORDER BY p.fecha_pedido DESC
    ");
    
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener detalles de cada pedido
    foreach ($pedidos as &$pedido) {
        $stmt = $pdo->prepare("
            SELECT 
                pi.id,
                pi.producto_id,
                pi.cantidad,
                pi.precio_unitario,
                pi.subtotal,
                pr.nombre as producto_nombre
            FROM pedido_items pi
            INNER JOIN productos pr ON pi.producto_id = pr.id
            WHERE pi.pedido_id = ?
            ORDER BY pi.id
        ");
        $stmt->execute([$pedido['id']]);
        $pedido['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'total_pedidos' => count($pedidos)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener pedidos: ' . $e->getMessage()
    ]);
}
?>