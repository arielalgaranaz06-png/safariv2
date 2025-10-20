<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'garzon') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Función para determinar el turno basado en la hora de Bolivia
function obtenerTurno() {
    // Configurar zona horaria de Bolivia
    date_default_timezone_set('America/La_Paz');
    
    $hora_actual = (int) date('H');
    $minuto_actual = (int) date('i');
    
    // Convertir a minutos desde medianoche para comparación precisa
    $minutos_totales = ($hora_actual * 60) + $minuto_actual;
    
    // Definir rangos de turnos (horas Bolivia)
    // Turno mañana: 12:00 PM - 15:59 PM (720 - 959 minutos)
    // Turno noche: 19:00 PM - 23:59 PM (1140 - 1439 minutos)
    
    $inicio_mediodia = 12 * 60;      // 12:00 = 720 minutos
    $fin_mediodia = 16 * 60 - 1;     // 15:59 = 959 minutos
    $inicio_noche = 19 * 60;         // 19:00 = 1140 minutos
    $fin_noche = 24 * 60 - 1;        // 23:59 = 1439 minutos
    
    if ($minutos_totales >= $inicio_mediodia && $minutos_totales <= $fin_mediodia) {
        return 'mañana';
    } elseif ($minutos_totales >= $inicio_noche && $minutos_totales <= $fin_noche) {
        return 'noche';
    } else {
        // Fuera de horario laboral - asignar según la hora más cercana
        if ($minutos_totales < $inicio_mediodia) {
            return 'mañana'; // Antes de las 12:00, asignar a mañana
        } else {
            return 'noche'; // Después de las 16:00 y antes de las 19:00, asignar a noche
        }
    }
}

// Función para obtener caja activa por turno
function obtenerCajaActivaPorTurno($pdo, $usuario_id, $turno) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM caja_control 
        WHERE usuario_id = ? 
        AND turno = ? 
        AND estado = 'abierta'
        AND DATE(fecha_apertura) = CURDATE()
        ORDER BY fecha_apertura DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id, $turno]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $pdo->beginTransaction();

    $mesa_id = $_POST['mesa_id'];
    $usuario_id = $_SESSION['usuario_id'];
    $productos = json_decode($_POST['productos'], true);

    // Validar datos
    if (!$mesa_id || !$productos) {
        throw new Exception('Datos incompletos');
    }

    // Determinar el turno actual
    $turno = obtenerTurno();

    // Obtener caja activa para este turno
    $caja_activa = obtenerCajaActivaPorTurno($pdo, $usuario_id, $turno);
    $caja_id = $caja_activa ? $caja_activa['id'] : null;

    // Calcular total
    $total = 0;
    foreach ($productos as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }

    // Crear pedido con turno y caja_id
    $stmt = $pdo->prepare("INSERT INTO pedidos (mesa_id, usuario_id, estado, total, turno, caja_id, fecha_pedido) VALUES (?, ?, 'pendiente', ?, ?, ?, NOW())");
    $stmt->execute([$mesa_id, $usuario_id, $total, $turno, $caja_id]);
    $pedido_id = $pdo->lastInsertId();

    // Insertar items del pedido
    $stmt = $pdo->prepare("INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($productos as $producto_id => $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $stmt->execute([
            $pedido_id,
            $producto_id,
            $item['cantidad'],
            $item['precio'],
            $subtotal
        ]);
    }

    // Actualizar estado de la mesa
    $stmt = $pdo->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ?");
    $stmt->execute([$mesa_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'pedido_id' => $pedido_id,
        'turno' => $turno,
        'caja_asociada' => $caja_id ? true : false,
        'caja_id' => $caja_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>