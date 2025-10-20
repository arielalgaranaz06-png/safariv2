<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener caja abierta del usuario
    $stmt = $pdo->prepare("
        SELECT * FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
        ORDER BY fecha_apertura DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
        exit;
    }
    
    // Calcular efectivo teórico en caja
    $efectivo_teorico = $caja['monto_inicial'] + $caja['total_efectivo'];
    
    // Restar cambios dados
    $stmt_cambios = $pdo->prepare("
        SELECT COALESCE(SUM(ABS(monto)), 0) as total_cambios
        FROM caja_movimientos 
        WHERE caja_id = ? 
        AND tipo = 'egreso'
        AND descripcion LIKE '%cambio%'
    ");
    $stmt_cambios->execute([$caja['id']]);
    $cambios_dados = $stmt_cambios->fetch(PDO::FETCH_ASSOC);
    $efectivo_teorico -= $cambios_dados['total_cambios'];
    
    echo json_encode([
        'success' => true,
        'caja' => $caja,
        'efectivo_teorico' => $efectivo_teorico
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>