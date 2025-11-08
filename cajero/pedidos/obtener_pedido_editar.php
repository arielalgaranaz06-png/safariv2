<?php
// DEBUG: Respuesta simple para testing
header('Content-Type: application/json');

// Iniciar sesión y verificar permisos
session_start();

// DEBUG: Forzar datos de sesión si no existen
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 2;
    $_SESSION['rol'] = 'cajero';
    $_SESSION['nombre'] = 'Ariel Algarañaz';
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Incluir DB
    require_once '../../db.php';
    
    $pedido_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$pedido_id) {
        echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
        exit;
    }

    // Consulta del pedido
    $stmt_pedido = $pdo->prepare("
        SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre
        FROM pedidos p
        LEFT JOIN mesas m ON p.mesa_id = m.id
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.id = ?
    ");
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    // Consulta de items
    $stmt_items = $pdo->prepare("
        SELECT pi.*, pr.nombre, pr.categoria
        FROM pedido_items pi
        LEFT JOIN productos pr ON pi.producto_id = pr.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id
    ");
    $stmt_items->execute([$pedido_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'items' => $items,
        'debug' => 'Consulta exitosa - Items: ' . count($items)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>