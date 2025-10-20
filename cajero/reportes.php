<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('Location: ../login.php');
    exit;
}

// Función para obtener datos del reporte
function obtenerReporteVentas($fecha, $turno = 'todos', $cajero_id = 'todos') {
    global $pdo;
    
    // Construir consulta base
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
    
    // Aplicar filtros
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

// Función para obtener resumen general
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

// Función para obtener lista de cajeros
function obtenerCajeros() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'cajero' ORDER BY nombre");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar filtros del formulario
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$turno = $_POST['turno'] ?? 'todos';
$cajero_id = $_POST['cajero_id'] ?? 'todos';

// Obtener datos
$cajeros = obtenerCajeros();
$reporteData = obtenerReporteVentas($fecha, $turno, $cajero_id);
$resumen = obtenerResumenGeneral($fecha, $turno, $cajero_id);

// Preparar datos para el frontend
$datosReporte = [
    'resumen' => $resumen,
    'detalle' => $reporteData,
    'filtros' => [
        'fecha' => $fecha,
        'turno' => $turno,
        'cajero_id' => $cajero_id
    ]
];

header('Content-Type: application/json');
echo json_encode($datosReporte);
?>