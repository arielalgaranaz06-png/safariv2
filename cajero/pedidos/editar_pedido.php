<?php
session_start();
require_once '../../db.php';

// HEADERS ANTES DE CUALQUIER OUTPUT
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// LIMPIAR BUFFER POR SI HAY OUTPUT PREVIO
if (ob_get_length()) ob_clean();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log("DEBUG EDITAR_PEDIDO: Datos recibidos: " . json_encode($input));
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
        exit;
    }
    
    $pedido_id = intval($input['pedido_id'] ?? 0);
    $items = $input['items'] ?? [];
    $total = floatval($input['total'] ?? 0);

    error_log("DEBUG EDITAR_PEDIDO: pedido_id=$pedido_id, items_count=" . count($items) . ", total=$total");

    if (!$pedido_id) {
        echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
        exit;
    }

    // Verificar que el pedido existe y no está pagado
    $stmt_pedido = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ?");
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch();

    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    if ($pedido['estado'] === 'pagado') {
        echo json_encode(['success' => false, 'message' => 'No se puede editar un pedido pagado']);
        exit;
    }

    $pdo->beginTransaction();

    // Eliminar items actuales del pedido
    $stmt_delete = $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?");
    $stmt_delete->execute([$pedido_id]);
    error_log("DEBUG EDITAR_PEDIDO: Items antiguos eliminados");

    // Insertar nuevos items solo si hay items
    if (!empty($items)) {
        $stmt_item = $pdo->prepare("
            INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $subtotal = floatval($item['precio']) * intval($item['cantidad']);
            $stmt_item->execute([
                $pedido_id,
                intval($item['producto_id']),
                intval($item['cantidad']),
                floatval($item['precio']),
                $subtotal
            ]);
            error_log("DEBUG EDITAR_PEDIDO: Item insertado - producto_id: " . $item['producto_id'] . ", cantidad: " . $item['cantidad']);
        }
    }

    // Actualizar pedido
    $stmt_update = $pdo->prepare("
        UPDATE pedidos 
        SET total = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
        WHERE id = ?
    ");
    $stmt_update->execute([$total, $_SESSION['usuario_id'], $pedido_id]);
    error_log("DEBUG EDITAR_PEDIDO: Pedido actualizado - total: $total");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pedido actualizado correctamente',
        'debug' => [
            'items_procesados' => count($items),
            'nuevo_total' => $total
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("ERROR EDITAR_PEDIDO: " . $e->getMessage());
    
    // Limpiar buffer en caso de error
    if (ob_get_length()) ob_clean();
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// NO DEJAR ESPACIOS O SALTOS DE LÍNEA DESPUÉS DE ESTE PUNTO
?>