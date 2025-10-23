<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Obtener parámetros del reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodo = $_POST['periodo'] ?? 'dia';
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
    $fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
    $cajero_id = $_POST['cajero_id'] ?? 'todos';
    $turno = $_POST['turno'] ?? 'todos';
} else {
    $periodo = $_GET['periodo'] ?? 'dia';
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
    $cajero_id = $_GET['cajero_id'] ?? 'todos';
    $turno = $_GET['turno'] ?? 'todos';
}

// DEBUG: Ver qué está llegando
error_log("PERIODO: $periodo, FECHA: $fecha, INICIO: $fecha_inicio, FIN: $fecha_fin");

// Calcular fechas según el período CORREGIDO
if ($periodo === 'rango') {
    $fecha_inicio_real = $fecha_inicio;
    $fecha_fin_real = $fecha_fin;
} else {
    list($fecha_inicio_real, $fecha_fin_real) = calcularFechasPeriodo($periodo, $fecha, $fecha_inicio, $fecha_fin);
}

error_log("FECHAS REALES: $fecha_inicio_real a $fecha_fin_real");

// CONSULTA BASE MEJORADA
$sql = "SELECT 
            p.id,
            p.total,
            p.metodo_pago,
            p.fecha_pago,
            p.turno,
            p.caja_id,
            c.usuario_id as cajero_id,
            u.nombre as cajero_nombre,
            DATE(p.fecha_pago) as fecha_pago_date
        FROM pedidos p
        LEFT JOIN caja_control c ON p.caja_id = c.id
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE p.estado = 'pagado'
        AND DATE(p.fecha_pago) BETWEEN ? AND ?
        AND p.metodo_pago IS NOT NULL";

$params = [$fecha_inicio_real, $fecha_fin_real];

// Aplicar filtro de cajero
if ($cajero_id !== 'todos') {
    $sql .= " AND c.usuario_id = ?";
    $params[] = $cajero_id;
}

// Aplicar filtro de turno
if ($turno !== 'todos') {
    $sql .= " AND p.turno = ?";
    $params[] = $turno;
}

$sql .= " ORDER BY p.fecha_pago ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log("VENTAS ENCONTRADAS: " . count($ventas));

// Verificar si hay datos
$hay_datos = count($ventas) > 0;

// Formatear fecha para mostrar
$fecha_mostrar = obtenerTextoPeriodo($periodo, $fecha_inicio_real, $fecha_fin_real);

if (!$hay_datos) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'hay_datos' => false,
        'mensaje' => "No hay ventas registradas para el período seleccionado",
        'fecha_consultada' => $fecha_mostrar,
        'fecha_seleccionada' => $fecha_mostrar,
        'fecha_inicio' => $fecha_inicio_real,
        'fecha_fin' => $fecha_fin_real,
        'periodo' => $periodo,
        'total_ventas' => 0
    ]);
    exit;
}

// SI HAY DATOS - Calcular totales
$totales = [
    'efectivo' => 0,
    'qr' => 0,
    'mixto' => 0,
    'general' => 0
];

$cantidades = [
    'efectivo' => 0,
    'qr' => 0,
    'mixto' => 0,
    'general' => 0
];

foreach ($ventas as $venta) {
    $metodo = $venta['metodo_pago'];
    $monto = floatval($venta['total']);
    
    if (isset($totales[$metodo])) {
        $totales[$metodo] += $monto;
        $cantidades[$metodo]++;
        $totales['general'] += $monto;
        $cantidades['general']++;
    }
}

// Obtener detalle por turnos MEJORADO
$detalle_turnos = obtenerDetalleTurnos($pdo, $fecha_inicio_real, $fecha_fin_real, $cajero_id, $turno);

// Si se solicita exportar a Excel
if (isset($_GET['exportar']) && $_GET['exportar'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte_ventas_' . $fecha_inicio_real . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $titulo = "Reporte de Ventas - " . obtenerTextoPeriodo($periodo, $fecha_inicio_real, $fecha_fin_real);
    
    echo "<table border='1'>";
    echo "<tr><th colspan='4' style='background:#667eea;color:white;'><h3>$titulo</h3></th></tr>";
    
    echo "<tr style='background:#f8f9fa;font-weight:bold;'>";
    echo "<th>Método de Pago</th>";
    echo "<th>Cantidad de Ventas</th>";
    echo "<th>Monto Total</th>";
    echo "<th>Porcentaje</th>";
    echo "</tr>";
    
    $metodos = [
        'efectivo' => '💰 Efectivo',
        'qr' => '📱 QR/Pago Digital', 
        'mixto' => '💳 Pago Mixto'
    ];
    
    foreach ($metodos as $key => $nombre) {
        $porcentaje = $totales['general'] > 0 ? ($totales[$key] / $totales['general'] * 100) : 0;
        echo "<tr>";
        echo "<td>$nombre</td>";
        echo "<td>{$cantidades[$key]}</td>";
        echo "<td>Bs. " . number_format($totales[$key], 2) . "</td>";
        echo "<td>" . number_format($porcentaje, 1) . "%</td>";
        echo "</tr>";
    }
    
    echo "<tr style='background:#2c3e50;color:white;font-weight:bold;'>";
    echo "<td>TOTAL GENERAL</td>";
    echo "<td>{$cantidades['general']}</td>";
    echo "<td>Bs. " . number_format($totales['general'], 2) . "</td>";
    echo "<td>100%</td>";
    echo "</tr>";
    echo "</table>";
    
    // Agregar detalle por turnos si existe
    if (!empty($detalle_turnos)) {
        echo "<br><br>";
        echo "<table border='1'>";
        echo "<tr><th colspan='5' style='background:#27ae60;color:white;'><h3>Detalle por Turnos</h3></th></tr>";
        echo "<tr style='background:#f8f9fa;font-weight:bold;'>";
        echo "<th>Fecha</th>";
        echo "<th>Turno</th>";
        echo "<th>Cajero</th>";
        echo "<th>Ventas</th>";
        echo "<th>Total</th>";
        echo "</tr>";
        
        foreach ($detalle_turnos as $turno_det) {
            echo "<tr>";
            echo "<td>{$turno_det['fecha']}</td>";
            echo "<td>" . ($turno_det['turno'] == 'mañana' ? '🌅 Mañana' : '🌙 Noche') . "</td>";
            echo "<td colspan='2' style='background:#f1f8e9;'><strong>Total Turno:</strong></td>";
            echo "<td style='background:#f1f8e9;'><strong>Bs. " . number_format($turno_det['total'], 2) . "</strong></td>";
            echo "</tr>";
            
            if (!empty($turno_det['cajeros'])) {
                foreach ($turno_det['cajeros'] as $cajero) {
                    echo "<tr>";
                    echo "<td></td>";
                    echo "<td></td>";
                    echo "<td>{$cajero['nombre']}</td>";
                    echo "<td>{$cajero['total_ventas']} ventas</td>";
                    echo "<td>Bs. " . number_format($cajero['total'], 2) . "</td>";
                    echo "</tr>";
                }
            }
        }
        echo "</table>";
    }
    
    exit;
}

// Devolver datos para mostrar en la página
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hay_datos' => true,
    'totales' => $totales,
    'cantidades' => $cantidades,
    'detalle_turnos' => $detalle_turnos,
    'fecha_inicio' => $fecha_inicio_real,
    'fecha_fin' => $fecha_fin_real,
    'fecha_seleccionada' => $fecha_mostrar,
    'fecha_consultada' => $fecha_mostrar,
    'periodo' => $periodo,
    'total_ventas' => count($ventas)
]);

// FUNCIONES AUXILIARES CORREGIDAS
function calcularFechasPeriodo($periodo, $fecha, $fecha_inicio, $fecha_fin) {
    switch ($periodo) {
        case 'dia':
            return [$fecha, $fecha];
            
        case 'semana':
            // Semana completa (lunes a domingo) de la fecha seleccionada
            $fecha_obj = new DateTime($fecha);
            $lunes = clone $fecha_obj->modify('Monday this week');
            $domingo = clone $fecha_obj->modify('Sunday this week');
            return [$lunes->format('Y-m-d'), $domingo->format('Y-m-d')];
            
        case 'mes':
            // Mes completo de la fecha seleccionada
            $fecha_obj = new DateTime($fecha);
            $primer_dia = $fecha_obj->format('Y-m-01'); // Primer día del mes
            $ultimo_dia = $fecha_obj->format('Y-m-t');  // Último día del mes
            return [$primer_dia, $ultimo_dia];
            
        case 'rango':
            return [$fecha_inicio, $fecha_fin];
            
        default:
            return [date('Y-m-d'), date('Y-m-d')];
    }
}

function obtenerDetalleTurnos($pdo, $fecha_inicio, $fecha_fin, $cajero_id, $filtro_turno) {
    $sql = "SELECT 
                DATE(p.fecha_pago) as fecha,
                p.turno,
                c.usuario_id as cajero_id,
                u.nombre as cajero_nombre,
                COUNT(p.id) as total_ventas,
                SUM(p.total) as total,
                SUM(CASE WHEN p.metodo_pago = 'efectivo' THEN p.total ELSE 0 END) as efectivo,
                SUM(CASE WHEN p.metodo_pago = 'qr' THEN p.total ELSE 0 END) as qr,
                SUM(CASE WHEN p.metodo_pago = 'mixto' THEN p.total ELSE 0 END) as mixto
            FROM pedidos p
            LEFT JOIN caja_control c ON p.caja_id = c.id
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            WHERE p.estado = 'pagado'
            AND DATE(p.fecha_pago) BETWEEN ? AND ?
            AND p.metodo_pago IS NOT NULL";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if ($cajero_id !== 'todos') {
        $sql .= " AND c.usuario_id = ?";
        $params[] = $cajero_id;
    }
    
    if ($filtro_turno !== 'todos') {
        $sql .= " AND p.turno = ?";
        $params[] = $filtro_turno;
    }
    
    $sql .= " GROUP BY DATE(p.fecha_pago), p.turno, c.usuario_id
              ORDER BY fecha DESC, p.turno ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por fecha y turno
    $agrupados = [];
    foreach ($resultados as $fila) {
        $clave = $fila['fecha'] . '_' . $fila['turno'];
        
        if (!isset($agrupados[$clave])) {
            $agrupados[$clave] = [
                'fecha' => $fila['fecha'],
                'turno' => $fila['turno'],
                'total' => 0,
                'cajeros' => []
            ];
        }
        
        $agrupados[$clave]['total'] += $fila['total'];
        $agrupados[$clave]['cajeros'][] = [
            'nombre' => $fila['cajero_nombre'] ?: 'Sin cajero',
            'total_ventas' => $fila['total_ventas'],
            'total' => $fila['total'],
            'efectivo' => $fila['efectivo'],
            'qr' => $fila['qr'],
            'mixto' => $fila['mixto']
        ];
    }
    
    return array_values($agrupados);
}

function obtenerTextoPeriodo($periodo, $fecha_inicio, $fecha_fin) {
    $fecha_inicio_obj = new DateTime($fecha_inicio);
    $fecha_fin_obj = new DateTime($fecha_fin);
    
    switch ($periodo) {
        case 'dia':
            return $fecha_inicio_obj->format('d/m/Y');
            
        case 'semana':
            return 'Semana del ' . $fecha_inicio_obj->format('d/m/Y') . ' al ' . $fecha_fin_obj->format('d/m/Y');
            
        case 'mes':
            // Usar nombres de meses en español
            $meses = [
                'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
            ];
            $mes_ingles = $fecha_inicio_obj->format('F');
            $mes_espanol = $meses[$mes_ingles] ?? $mes_ingles;
            return $mes_espanol . ' ' . $fecha_inicio_obj->format('Y');
            
        case 'rango':
            return 'Del ' . $fecha_inicio_obj->format('d/m/Y') . ' al ' . $fecha_fin_obj->format('d/m/Y');
            
        default:
            return $fecha_inicio_obj->format('d/m/Y');
    }
}
?>