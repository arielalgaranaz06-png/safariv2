<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Función para obtener datos del reporte (misma función que en reportes.php)
function obtenerReporteVentas($fecha, $turno = 'todos', $cajero_id = 'todos') {
    global $pdo;
    
    $sql = "
        SELECT 
            cc.turno,
            u.id as cajero_id,
            u.nombre as cajero_nombre,
            COUNT(p.id) as total_pedidos,
            SUM(p.total) as total_ventas,
            DATE(cc.fecha_apertura) as fecha
        FROM caja_control cc
        INNER JOIN usuarios u ON cc.usuario_id = u.id
        INNER JOIN pedidos p ON p.caja_id = cc.id
        WHERE DATE(cc.fecha_apertura) = ?
          AND p.estado = 'pagado'
          AND u.rol = 'cajero'
    ";
    
    $params = [$fecha];
    
    if ($turno != 'todos') {
        $sql .= " AND cc.turno = ?";
        $params[] = $turno;
    }
    
    if ($cajero_id != 'todos') {
        $sql .= " AND u.id = ?";
        $params[] = $cajero_id;
    }
    
    $sql .= " GROUP BY cc.turno, u.id ORDER BY cc.turno, total_ventas DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerResumenGeneral($fecha, $turno = 'todos', $cajero_id = 'todos') {
    global $pdo;
    
    $sql = "
        SELECT 
            COUNT(p.id) as total_pedidos,
            SUM(p.total) as total_ventas,
            COUNT(DISTINCT u.id) as total_cajeros
        FROM caja_control cc
        INNER JOIN usuarios u ON cc.usuario_id = u.id
        INNER JOIN pedidos p ON p.caja_id = cc.id
        WHERE DATE(cc.fecha_apertura) = ?
          AND p.estado = 'pagado'
          AND u.rol = 'cajero'
    ";
    
    $params = [$fecha];
    
    if ($turno != 'todos') {
        $sql .= " AND cc.turno = ?";
        $params[] = $turno;
    }
    
    if ($cajero_id != 'todos') {
        $sql .= " AND u.id = ?";
        $params[] = $cajero_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener parámetros
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$turno = $_POST['turno'] ?? 'todos';
$cajero_id = $_POST['cajero_id'] ?? 'todos';

try {
    $reporteData = obtenerReporteVentas($fecha, $turno, $cajero_id);
    $resumen = obtenerResumenGeneral($fecha, $turno, $cajero_id);
    
    echo json_encode([
        'success' => true,
        'resumen' => $resumen,
        'detalle' => $reporteData,
        'filtros' => [
            'fecha' => $fecha,
            'turno' => $turno,
            'cajero_id' => $cajero_id
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar reporte: ' . $e->getMessage()
    ]);
}
?>