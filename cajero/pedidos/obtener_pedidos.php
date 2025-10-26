<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener caja abierta actual
    $stmt_caja = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        LIMIT 1
    ");
    $stmt_caja->execute([$_SESSION['usuario_id']]);
    $caja = $stmt_caja->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
        exit;
    }

    // Obtener pedidos activos de la caja actual
    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            m.numero as mesa_numero, 
            u.nombre as garzon_nombre,
            (SELECT COUNT(*) FROM pedido_items WHERE pedido_id = p.id) as total_items
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        INNER JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.estado IN ('pendiente', 'preparacion', 'listo', 'entregado')
        AND p.caja_id = ?
        ORDER BY 
            CASE p.estado 
                WHEN 'pendiente' THEN 1
                WHEN 'preparacion' THEN 2
                WHEN 'listo' THEN 3
                WHEN 'entregado' THEN 4
                ELSE 5
            END,
            p.fecha_pedido ASC
    ");
    $stmt->execute([$caja['id']]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>