<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Verificar que no haya una caja abierta para este usuario
    $stmt_check = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
    ");
    $stmt_check->execute([$_SESSION['usuario_id']]);
    
    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya tiene una caja abierta']);
        exit;
    }
    
    $turno = $_POST['turno'] ?? '';
    $denominaciones = json_decode($_POST['denominaciones'] ?? '{}', true);
    
    if (empty($turno)) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar un turno']);
        exit;
    }
    
    // Calcular monto inicial
    $monto_inicial = 0;
    $denominaciones_activas = [];
    
    foreach ($denominaciones as $tipo => $datos) {
        if ($datos['activo'] && $datos['cantidad'] > 0) {
            $subtotal = $datos['cantidad'] * $datos['valor'];
            $monto_inicial += $subtotal;
            
            $denominaciones_activas[] = [
                'tipo' => $tipo,
                'valor' => $datos['valor'],
                'cantidad' => $datos['cantidad'],
                'subtotal' => $subtotal
            ];
        }
    }
    
    $pdo->beginTransaction();
    
    // Insertar registro de apertura de caja
    $stmt_caja = $pdo->prepare("
        INSERT INTO caja_control (usuario_id, turno, monto_inicial, fecha_apertura, estado, denominaciones_apertura)
        VALUES (?, ?, ?, NOW(), 'abierta', ?)
    ");
    
    $stmt_caja->execute([
        $_SESSION['usuario_id'],
        $turno,
        $monto_inicial,
        json_encode($denominaciones_activas)
    ]);
    
    $caja_id = $pdo->lastInsertId();
    
    // Registrar movimiento de apertura
    $stmt_movimiento = $pdo->prepare("
        INSERT INTO caja_movimientos (caja_id, tipo, monto, descripcion, fecha)
        VALUES (?, 'apertura', ?, ?, NOW())
    ");
    
    $descripcion = "Apertura de caja - Turno: " . strtoupper(str_replace('_', ' ', $turno));
    $stmt_movimiento->execute([$caja_id, $monto_inicial, $descripcion]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Caja abierta correctamente',
        'caja_id' => $caja_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>