<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Función para construir WHERE clause según filtros
function construirWhere($filtros) {
    $where = " WHERE p.estado = 'pagado' AND u.rol = 'cajero'";
    $params = [];
    
    // Filtro por período
    if ($filtros['periodo'] === 'rango') {
        $where .= " AND DATE(cc.fecha_apertura) BETWEEN ? AND ?";
        $params[] = $filtros['fecha_inicio'];
        $params[] = $filtros['fecha_fin'];
    } elseif ($filtros['periodo'] === 'mes') {
        $where .= " AND DATE_FORMAT(cc.fecha_apertura, '%Y-%m') = ?";
        $params[] = $filtros['fecha'];
    } elseif ($filtros['periodo'] === 'semana') {
        $fechaObj = new DateTime($filtros['fecha']);
        $inicioSemana = $fechaObj->modify('monday this week')->format('Y-m-d');
        $finSemana = $fechaObj->modify('sunday this week')->format('Y-m-d');
        $where .= " AND DATE(cc.fecha_apertura) BETWEEN ? AND ?";
        $params[] = $inicioSemana;
        $params[] = $finSemana;
    } else { // día
        $where .= " AND DATE(cc.fecha_apertura) = ?";
        $params[] = $filtros['fecha'];
    }
    
    // Filtro por turno
    if ($filtros['turno'] != 'todos') {
        $where .= " AND cc.turno = ?";
        $params[] = $filtros['turno'];
    }
    
    // Filtro por cajero
    if ($filtros['cajero_id'] != 'todos') {
        $where .= " AND u.id = ?";
        $params[] = $filtros['cajero_id'];
    }
    
    return ['where' => $where, 'params' => $params];
}

// Función para obtener ventas por método de pago
function obtenerVentasPorMetodoPago($filtros) {
    global $pdo;
    
    $condiciones = construirWhere($filtros);
    $sql = "
        SELECT 
            p.metodo_pago,
            COUNT(p.id) as cantidad_ventas,
            SUM(p.total) as total_ventas
        FROM pedidos p
        INNER JOIN caja_control cc ON p.caja_id = cc.id
        INNER JOIN usuarios u ON cc.usuario_id = u.id
        " . $condiciones['where'] . "
        GROUP BY p.metodo_pago
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($condiciones['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener ventas por turno (solo para día específico)
function obtenerVentasPorTurno($filtros) {
    global $pdo;
    
    $condiciones = construirWhere($filtros);
    $sql = "
        SELECT 
            cc.turno,
            COUNT(p.id) as cantidad_pedidos,
            SUM(p.total) as total_ventas
        FROM caja_control cc
        INNER JOIN pedidos p ON p.caja_id = cc.id
        INNER JOIN usuarios u ON cc.usuario_id = u.id
        " . $condiciones['where'] . "
        GROUP BY cc.turno
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($condiciones['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener ventas por cajero
function obtenerVentasPorCajero($filtros) {
    global $pdo;
    
    $condiciones = construirWhere($filtros);
    $sql = "
        SELECT 
            u.id as cajero_id,
            u.nombre as cajero_nombre,
            COUNT(p.id) as cantidad_pedidos,
            SUM(p.total) as total_ventas
        FROM usuarios u
        INNER JOIN caja_control cc ON cc.usuario_id = u.id
        INNER JOIN pedidos p ON p.caja_id = cc.id
        " . $condiciones['where'] . "
        GROUP BY u.id, u.nombre 
        ORDER BY total_ventas DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($condiciones['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener ventas diarias (para semana y mes)
function obtenerVentasDiarias($filtros) {
    global $pdo;
    
    $condiciones = construirWhere($filtros);
    $sql = "
        SELECT 
            DATE(cc.fecha_apertura) as fecha,
            cc.turno,
            COUNT(p.id) as cantidad_pedidos,
            SUM(p.total) as total_ventas
        FROM caja_control cc
        INNER JOIN pedidos p ON p.caja_id = cc.id
        INNER JOIN usuarios u ON cc.usuario_id = u.id
        " . $condiciones['where'] . "
        GROUP BY DATE(cc.fecha_apertura), cc.turno 
        ORDER BY fecha, cc.turno
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($condiciones['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener resumen general
function obtenerResumenGeneral($filtros) {
    global $pdo;
    
    $condiciones = construirWhere($filtros);
    $sql = "
        SELECT 
            COUNT(p.id) as total_pedidos,
            SUM(p.total) as total_ventas,
            COUNT(DISTINCT u.id) as total_cajeros,
            MIN(DATE(cc.fecha_apertura)) as fecha_inicio,
            MAX(DATE(cc.fecha_apertura)) as fecha_fin
        FROM caja_control cc
        INNER JOIN usuarios u ON cc.usuario_id = u.id
        INNER JOIN pedidos p ON p.caja_id = cc.id
        " . $condiciones['where'] . "
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($condiciones['params']);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener parámetros
$periodo = $_POST['periodo'] ?? 'dia';
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$fecha_fin = $_POST['fecha_fin'] ?? null;
$turno = $_POST['turno'] ?? 'todos';
$cajero_id = $_POST['cajero_id'] ?? 'todos';

// Preparar filtros
$filtros = [
    'periodo' => $periodo,
    'fecha' => $fecha,
    'fecha_inicio' => $fecha_inicio,
    'fecha_fin' => $fecha_fin,
    'turno' => $turno,
    'cajero_id' => $cajero_id
];

try {
    // Obtener todos los datos según los filtros
    $metodos_pago = obtenerVentasPorMetodoPago($filtros);
    $ventas_turno = ($periodo === 'dia') ? obtenerVentasPorTurno($filtros) : [];
    $ventas_cajero = obtenerVentasPorCajero($filtros);
    $ventas_diarias = ($periodo === 'semana' || $periodo === 'mes' || $periodo === 'rango') ? obtenerVentasDiarias($filtros) : [];
    $resumen = obtenerResumenGeneral($filtros);
    
    echo json_encode([
        'success' => true,
        'metodos_pago' => $metodos_pago,
        'ventas_turno' => $ventas_turno,
        'ventas_cajero' => $ventas_cajero,
        'ventas_diarias' => $ventas_diarias,
        'resumen' => $resumen,
        'filtros' => $filtros
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar reporte: ' . $e->getMessage()
    ]);
}
?>