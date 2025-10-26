<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $turno = $_POST['turno'] ?? '';
    $monto_inicial = floatval($_POST['monto_inicial'] ?? 0);
    $denominaciones_json = $_POST['denominaciones'] ?? '{}';
    $denominaciones = json_decode($denominaciones_json, true);
    
    if (empty($turno) || $monto_inicial <= 0) {
        echo json_encode(['success' => false, 'message' => 'Complete todos los campos correctamente']);
        exit;
    }
    
    // Validar turnos permitidos según tu base de datos
    $turnos_permitidos = ['mañana', 'noche'];
    if (!in_array($turno, $turnos_permitidos)) {
        echo json_encode(['success' => false, 'message' => 'Turno no válido']);
        exit;
    }
    
    // Verificar si ya hay caja abierta para este usuario
    $stmt_check = $pdo->prepare("
        SELECT id FROM caja_control 
        WHERE usuario_id = ? AND estado = 'abierta'
    ");
    $stmt_check->execute([$_SESSION['usuario_id']]);
    
    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya tiene una caja abierta. Debe cerrarla antes de abrir una nueva.']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Preparar el array de denominaciones para guardar en JSON
    $denominaciones_apertura = [];
    
    // Definir todas las denominaciones posibles
    $todas_denominaciones = [
        ['tipo' => 'b200', 'valor' => 200, 'nombre' => 'Billete Bs. 200'],
        ['tipo' => 'b100', 'valor' => 100, 'nombre' => 'Billete Bs. 100'],
        ['tipo' => 'b50', 'valor' => 50, 'nombre' => 'Billete Bs. 50'],
        ['tipo' => 'b20', 'valor' => 20, 'nombre' => 'Billete Bs. 20'],
        ['tipo' => 'b10', 'valor' => 10, 'nombre' => 'Billete Bs. 10'],
        ['tipo' => 'm5', 'valor' => 5, 'nombre' => 'Moneda Bs. 5'],
        ['tipo' => 'm2', 'valor' => 2, 'nombre' => 'Moneda Bs. 2'],
        ['tipo' => 'm1', 'valor' => 1, 'nombre' => 'Moneda Bs. 1']
    ];
    
    // Procesar denominaciones ingresadas
    foreach ($todas_denominaciones as $denom) {
        $cantidad = isset($denominaciones[$denom['valor']]) ? intval($denominaciones[$denom['valor']]) : 0;
        $subtotal = $cantidad * $denom['valor'];
        
        $denominaciones_apertura[] = [
            'tipo' => $denom['tipo'],
            'valor' => $denom['valor'],
            'cantidad' => $cantidad,
            'subtotal' => $subtotal,
            'nombre' => $denom['nombre']
        ];
    }
    
    // Insertar caja en la base de datos
    $stmt_caja = $pdo->prepare("
        INSERT INTO caja_control 
        (usuario_id, turno, monto_inicial, fecha_apertura, estado, denominaciones_apertura) 
        VALUES (?, ?, ?, NOW(), 'abierta', ?)
    ");
    
    $stmt_caja->execute([
        $_SESSION['usuario_id'], 
        $turno, 
        $monto_inicial,
        json_encode($denominaciones_apertura)
    ]);
    $caja_id = $pdo->lastInsertId();
    
    // Registrar movimiento de apertura
    $stmt_movimiento = $pdo->prepare("
        INSERT INTO caja_movimientos 
        (caja_id, tipo, monto, descripcion, fecha_movimiento) 
        VALUES (?, 'apertura', ?, ?, NOW())
    ");
    
    $descripcion = "Apertura de caja - Turno: " . strtoupper($turno);
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