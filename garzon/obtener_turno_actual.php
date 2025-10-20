<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Función para determinar el turno (la misma que en crear_pedido.php)
function obtenerTurno() {
    date_default_timezone_set('America/La_Paz');
    
    $hora_actual = (int) date('H');
    $minuto_actual = (int) date('i');
    $minutos_totales = ($hora_actual * 60) + $minuto_actual;
    
    $inicio_mediodia = 12 * 60;      // 12:00 = 720 minutos
    $fin_mediodia = 16 * 60 - 1;     // 15:59 = 959 minutos
    $inicio_noche = 19 * 60;         // 19:00 = 1140 minutos
    $fin_noche = 24 * 60 - 1;        // 23:59 = 1439 minutos
    
    if ($minutos_totales >= $inicio_mediodia && $minutos_totales <= $fin_mediodia) {
        return 'mañana';
    } elseif ($minutos_totales >= $inicio_noche && $minutos_totales <= $fin_noche) {
        return 'noche';
    } else {
        if ($minutos_totales < $inicio_mediodia) {
            return 'mañana';
        } else {
            return 'noche';
        }
    }
}

$turno = obtenerTurno();

echo json_encode([
    'success' => true,
    'turno' => $turno,
    'hora_actual' => date('H:i:s'),
    'zona_horaria' => 'America/La_Paz'
]);
?>