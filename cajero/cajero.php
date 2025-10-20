<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'cajero') {
    header('Location: ../login.php');
    exit;
}

// Verificar si hay una caja abierta para este cajero
$stmt_caja = $pdo->prepare("
    SELECT * FROM caja_control 
    WHERE usuario_id = ? AND estado = 'abierta'
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt_caja->execute([$_SESSION['usuario_id']]);
$caja_abierta = $stmt_caja->fetch(PDO::FETCH_ASSOC);

// Obtener pedidos pendientes y listos (solo si hay caja abierta)
$pedidos = [];
if ($caja_abierta) {
    $stmt = $pdo->query("
        SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre,
               COUNT(pi.id) as total_items
        FROM pedidos p
        INNER JOIN mesas m ON p.mesa_id = m.id
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN pedido_items pi ON p.id = pi.pedido_id
        WHERE p.estado IN ('pendiente', 'preparacion', 'listo')
        GROUP BY p.id
        ORDER BY p.fecha_pedido DESC
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener productos para el modal de edición
$stmt_prod = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
$todos_productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

$categorias_nombres = [
    'plato_principal' => 'PLATOS PRINCIPALES',
    'acompanamiento' => 'ACOMPAÑAMIENTOS',
    'bebida' => 'BEBIDAS'
];

$prod_por_cat = ['plato_principal' => [], 'acompanamiento' => [], 'bebida' => []];
foreach ($todos_productos as $p) {
    $prod_por_cat[$p['categoria']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cajero - Safari</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .header-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-bar h1 { font-size: 1.5rem; margin: 0; }
        
        .main-layout {
            display: flex;
            height: calc(100vh - 70px);
        }
        
        /* SIDEBAR */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            transition: all 0.3s;
            position: relative;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .sidebar-toggle {
            position: absolute;
            top: 15px;
            right: -15px;
            width: 30px;
            height: 30px;
            background: #667eea;
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            font-weight: bold;
            z-index: 10;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid transparent;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #667eea;
        }
        
        .menu-item.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #667eea;
        }
        
        .menu-icon {
            font-size: 1.5rem;
            min-width: 30px;
        }
        
        .menu-text {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .sidebar.collapsed .menu-text {
            display: none;
        }
        
        /* CONTENT */
        .content-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .section-content {
            display: none;
        }
        
        .section-content.active {
            display: block;
        }
        
        /* CAJA SECTION */
        .caja-status {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 30px;
        }
        
        .caja-cerrada {
            border: 3px solid #e74c3c;
        }
        
        .caja-abierta {
            border: 3px solid #27ae60;
        }
        
        .status-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .status-text {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .apertura-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        
        .form-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .turno-selector {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .turno-option {
            flex: 1;
            max-width: 300px;
            padding: 20px;
            border: 3px solid #ddd;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .turno-option:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .turno-option.selected {
            border-color: #667eea;
            background: #f0f3ff;
        }
        
        .turno-option input[type="radio"] {
            display: none;
        }
        
        .turno-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .turno-nombre {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .turno-horario {
            color: #7f8c8d;
            font-size: 1rem;
        }
        
        .denominaciones-section {
            margin-top: 30px;
        }
        
        .denominaciones-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .denominaciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
        }
        
        .denominacion-item {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .denominacion-item.disabled {
            opacity: 0.5;
            background: #ecf0f1;
        }
        
        .denominacion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .denominacion-valor {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #27ae60;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .cantidad-input {
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }
        
        .cantidad-input:disabled {
            background: #ecf0f1;
            cursor: not-allowed;
        }
        
        .cantidad-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .subtotal-denominacion {
            margin-top: 8px;
            text-align: center;
            font-size: 1rem;
            color: #27ae60;
            font-weight: bold;
        }
        
        .total-apertura {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-top: 30px;
        }
        
        .total-apertura .label {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .total-apertura .amount {
            font-size: 3rem;
            font-weight: bold;
        }
        
        .btn-abrir-caja {
            width: 100%;
            padding: 18px;
            font-size: 1.4rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .btn-abrir-caja:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-abrir-caja:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Estilos de caja abierta */
        .caja-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .btn-cerrar-caja {
            background: #e74c3c;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-cerrar-caja:hover {
            background: #c0392b;
        }
        
        /* PEDIDOS SECTION */
        .pedidos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .pedido-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #3498db;
            transition: all 0.3s;
        }
        
        .pedido-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .pedido-mesa {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .pedido-hora {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .pedido-info {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 0.95rem;
        }
        
        .pedido-items {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.9rem;
        }
        
        .pedido-total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
            text-align: right;
            margin-bottom: 15px;
        }
        
        .pedido-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-action {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-pagar {
            background: #27ae60;
            color: white;
        }
        
        .btn-pagar:hover {
            background: #229954;
        }
        
        .btn-editar {
            background: #f39c12;
            color: white;
        }
        
        .btn-editar:hover {
            background: #e67e22;
        }
        
        .btn-eliminar {
            background: #e74c3c;
            color: white;
        }
        
        .btn-eliminar:hover {
            background: #c0392b;
        }
        
        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        .pago-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .pago-metodo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .pago-metodo input[type="checkbox"] {
            width: 25px;
            height: 25px;
            cursor: pointer;
        }
        
        .pago-metodo label {
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .pago-input {
            width: 100%;
            padding: 15px;
            font-size: 1.3rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .pago-input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .nota-area {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            min-height: 80px;
        }
        
        .total-display {
            background: #27ae60;
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .total-display .label {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .total-display .amount {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .cambio-display {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 10px 0;
        }
        
        .cambio-display.positivo {
            background: #3498db;
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-modal {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-confirmar {
            background: #27ae60;
            color: white;
        }
        
        .btn-cancelar {
            background: #95a5a6;
            color: white;
        }
        
        .producto-editar {
            background: white;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .producto-editar:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .pedidos-grid {
                grid-template-columns: 1fr;
            }
            
            .denominaciones-grid {
                grid-template-columns: 1fr;
            }
            
            .turno-selector {
                flex-direction: column;
            }
            
            .sidebar {
                position: absolute;
                z-index: 100;
                height: 100%;
            }
            
            .sidebar.collapsed {
                width: 0;
                overflow: hidden;
            }
        }
        /* ESTILOS PARA REPORTES */
.reporte-resumen {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.reporte-turno {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 15px;
    border-left: 5px solid #3498db;
}

.reporte-cajero {
    background: white;
    padding: 15px;
    margin: 10px 0;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.reporte-detalle {
    background: #fff3cd;
    padding: 20px;
    border-radius: 10px;
    border: 2px solid #ffeaa7;
}

.resumen-item {
    text-align: center;
    padding: 15px;
}

.resumen-valor {
    font-size: 2.2rem;
    font-weight: bold;
    margin: 5px 0;
}

.resumen-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Loading spinner */
.spinner-reporte {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>💰 Safari - Cajero</h1>
        <div>
            <span style="margin-right: 15px;"><?php echo $_SESSION['nombre']; ?></span>
            <a href="../logout.php" class="btn btn-danger btn-sm">Salir</a>
        </div>
    </div>

    <div class="main-layout">
        <!-- SIDEBAR -->
        <div class="sidebar" id="sidebar">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div class="sidebar-menu">
                <div class="menu-item active" onclick="cambiarSeccion('caja')">
                    <span class="menu-icon">💵</span>
                    <span class="menu-text">Caja</span>
                </div>
                <div class="menu-item" onclick="cambiarSeccion('pedidos')">
                    <span class="menu-icon">📋</span>
                    <span class="menu-text">Pedidos</span>
                </div>
                <div class="menu-item" onclick="cambiarSeccion('reportes')">
                    <span class="menu-icon">📊</span>
                    <span class="menu-text">Reportes</span>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content-area">
            <!-- SECCIÓN PEDIDOS -->
            <div class="section-content" id="section-pedidos">
                <?php if ($caja_abierta): ?>
                    <h2 style="margin-bottom: 20px;">Pedidos Activos</h2>
                    <div class="pedidos-grid" id="pedidosGrid">
                        <?php foreach ($pedidos as $pedido): 
                            $stmt_items = $pdo->prepare("
                                SELECT pi.*, pr.nombre 
                                FROM pedido_items pi
                                INNER JOIN productos pr ON pi.producto_id = pr.id
                                WHERE pi.pedido_id = ?
                            ");
                            $stmt_items->execute([$pedido['id']]);
                            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                            
                            $hora = date('H:i', strtotime($pedido['fecha_pedido']));
                        ?>
                        <div class="pedido-card">
                            <div class="pedido-header">
                                <div class="pedido-mesa">Mesa <?php echo $pedido['mesa_numero']; ?></div>
                                <div class="pedido-hora"><?php echo $hora; ?></div>
                            </div>
                            
                            <div class="pedido-info">
                                <div class="info-row">
                                    <span class="info-label">Garzón:</span>
                                    <span class="info-value"><?php echo $pedido['garzon_nombre']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Items:</span>
                                    <span class="info-value"><?php echo $pedido['total_items']; ?></span>
                                </div>
                            </div>
                            
                            <div class="pedido-items">
                                <?php foreach ($items as $item): ?>
                                    <div class="item-row">
                                        <span><?php echo $item['cantidad']; ?>x <?php echo $item['nombre']; ?></span>
                                        <span>Bs. <?php echo number_format($item['precio_unitario'] * $item['cantidad'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="pedido-total">
                                TOTAL: Bs. <?php echo number_format($pedido['total'], 2); ?>
                            </div>
                            
                            <div class="pedido-actions">
                                <button class="btn-action btn-pagar" 
                                        onclick='abrirModalPago(<?php echo json_encode($pedido); ?>, <?php echo json_encode($items); ?>)'>
                                    PAGAR
                                </button>
                                <button class="btn-action btn-editar" onclick="editarPedido(<?php echo $pedido['id']; ?>)">
                                    EDITAR
                                </button>
                                <button class="btn-action btn-eliminar" onclick="eliminarPedido(<?php echo $pedido['id']; ?>)">
                                    ELIMINAR
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="caja-status caja-cerrada">
                        <div class="status-icon">🔒</div>
                        <div class="status-text">CAJA CERRADA</div>
                        <p style="color: #7f8c8d; font-size: 1.1rem;">Debe abrir la caja antes de procesar pedidos</p>
                    </div>
                <?php endif; ?>
            </div>

        <!-- SECCIÓN CAJA -->
<div class="section-content active" id="section-caja">
    <?php if (!$caja_abierta): ?>
        <!-- CAJA CERRADA - FORMULARIO DE APERTURA -->
        <div class="caja-status caja-cerrada">
            <div class="status-icon">🔒</div>
            <div class="status-text">CAJA CERRADA</div>
            <p style="color: #7f8c8d; font-size: 1.1rem;">No hay una caja abierta actualmente</p>
        </div>

        <div class="apertura-form">
            <div class="form-header">
                <h2>🏦 Apertura de Caja</h2>
                <p style="color: #7f8c8d;">Complete los datos para abrir la caja del turno</p>
            </div>

            <form id="formAperturaCaja">
                <!-- SELECCIÓN DE TURNO -->
                <h4 style="margin-bottom: 15px; color: #2c3e50;">Seleccionar Turno</h4>
                <div class="turno-selector">
                    <label class="turno-option" for="turnoMedioDia">
                        <input type="radio" name="turno" id="turnoMedioDia" value="mañana" required>
                        <div class="turno-icon">☀️</div>
                        <div class="turno-nombre">MAÑANA</div>
                        <div class="turno-horario">12:00 - 16:00</div>
                    </label>
                    
                    <label class="turno-option" for="turnoNoche">
                        <input type="radio" name="turno" id="turnoNoche" value="noche" required>
                        <div class="turno-icon">🌙</div>
                        <div class="turno-nombre">NOCHE</div>
                        <div class="turno-horario">19:00 - 00:00</div>
                    </label>
                </div>

                <!-- MONEDAS -->
                <div class="denominaciones-section">
                    <div class="denominaciones-header">
                        🪙 MONEDAS
                    </div>
                    <div class="denominaciones-grid">
                        <div class="denominacion-item" id="denom-m1">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">1 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('m1')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-m1" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('m1', 1)">
                            <div class="subtotal-denominacion" id="subtotal-m1">Bs. 0.00</div>
                        </div>

                        <div class="denominacion-item" id="denom-m2">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">2 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('m2')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-m2" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('m2', 2)">
                            <div class="subtotal-denominacion" id="subtotal-m2">Bs. 0.00</div>
                        </div>

                        <div class="denominacion-item" id="denom-m5">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">5 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('m5')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-m5" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('m5', 5)">
                            <div class="subtotal-denominacion" id="subtotal-m5">Bs. 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- BILLETES -->
                <div class="denominaciones-section">
                    <div class="denominaciones-header">
                        💵 BILLETES
                    </div>
                    <div class="denominaciones-grid">
                        <div class="denominacion-item" id="denom-b10">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">10 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('b10')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-b10" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('b10', 10)">
                            <div class="subtotal-denominacion" id="subtotal-b10">Bs. 0.00</div>
                        </div>

                        <div class="denominacion-item" id="denom-b20">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">20 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('b20')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-b20" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('b20', 20)">
                            <div class="subtotal-denominacion" id="subtotal-b20">Bs. 0.00</div>
                        </div>

                        <div class="denominacion-item" id="denom-b50">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">50 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('b50')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-b50" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('b50', 50)">
                            <div class="subtotal-denominacion" id="subtotal-b50">Bs. 0.00</div>
                        </div>

                        <div class="denominacion-item" id="denom-b100">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">100 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('b100')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-b100" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('b100', 100)">
                            <div class="subtotal-denominacion" id="subtotal-b100">Bs. 0.00</div>
                        </div>

                        <div class="denominacion-item" id="denom-b200">
                            <div class="denominacion-header">
                                <span class="denominacion-valor">200 Bs</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="toggleDenominacion('b200')" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <input type="number" class="cantidad-input" id="cant-b200" 
                                   placeholder="Cantidad" min="0" value="0" 
                                   oninput="calcularSubtotal('b200', 200)">
                            <div class="subtotal-denominacion" id="subtotal-b200">Bs. 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- TOTAL -->
                <div class="total-apertura">
                    <div class="label">MONTO INICIAL DE CAJA</div>
                    <div class="amount" id="totalApertura">Bs. 0.00</div>
                </div>

                <button type="submit" class="btn-abrir-caja" id="btnAbrirCaja">
                    🔓 ABRIR CAJA
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- CAJA ABIERTA -->
        <div class="caja-status caja-abierta">
            <div class="status-icon">🔓</div>
            <div class="status-text">CAJA ABIERTA</div>
            <p style="color: #7f8c8d; font-size: 1.1rem;">
                Turno: <?php echo strtoupper($caja_abierta['turno']); ?> | 
                Apertura: <?php echo date('H:i', strtotime($caja_abierta['fecha_apertura'])); ?>
            </p>
        </div>

        <div class="caja-info">
            <h3 style="margin-bottom: 20px; color: #2c3e50;">Información de Caja - Turno <?php echo strtoupper($caja_abierta['turno']); ?></h3>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Monto Inicial</div>
                    <div class="info-value">Bs. <?php echo number_format($caja_abierta['monto_inicial'], 2); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Ventas del Turno</div>
                    <div class="info-value">Bs. <?php 
                        $stmt_ventas = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total_ventas FROM pedidos WHERE estado = 'pagado' AND caja_id = ?");
                        $stmt_ventas->execute([$caja_abierta['id']]);
                        $ventas = $stmt_ventas->fetch(PDO::FETCH_ASSOC);
                        echo number_format($ventas['total_ventas'], 2);
                    ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Efectivo en Caja</div>
                    <div class="info-value">Bs. <?php 
                        $efectivo_caja = $caja_abierta['monto_inicial'] + $ventas['total_ventas'];
                        echo number_format($efectivo_caja, 2);
                    ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Pedidos del Turno</div>
                    <div class="info-value"><?php 
                        $stmt_pedidos = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE caja_id = ?");
                        $stmt_pedidos->execute([$caja_abierta['id']]);
                        $total_pedidos = $stmt_pedidos->fetch(PDO::FETCH_ASSOC);
                        echo $total_pedidos['total'];
                    ?></div>
                </div>
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <button class="btn-cerrar-caja" onclick="cerrarCaja()">
                    🔒 CERRAR CAJA
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>
                <!--FIN CAJA -->
          <!-- SECCIÓN REPORTES -->
<!-- SECCIÓN REPORTES -->
<div class="section-content" id="section-reportes">
    <h2 style="margin-bottom: 20px; color: #2c3e50;">📊 Reportes de Ventas</h2>
    
    <!-- FILTROS -->
    <div class="caja-info" style="margin-bottom: 20px;">
        <div class="form-header" style="margin-bottom: 20px;">
            <h4>🔍 Filtros del Reporte</h4>
            <p style="color: #7f8c8d; margin: 0;">Seleccione los criterios para generar el reporte</p>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">📅 Fecha</label>
                <input type="date" class="cantidad-input" id="filtroFecha" 
                       value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">🕐 Turno</label>
                <select class="cantidad-input" id="filtroTurno">
                    <option value="todos">Todos los turnos</option>
                    <option value="mañana">🌅 Turno Mañana</option>
                    <option value="noche">🌙 Turno Noche</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">👤 Cajero</label>
                <select class="cantidad-input" id="filtroCajero">
                    <option value="todos">Todos los cajeros</option>
                    <?php
                    $stmt_cajeros = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'cajero' ORDER BY nombre");
                    $stmt_cajeros->execute();
                    $cajeros = $stmt_cajeros->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cajeros as $cajero) {
                        echo "<option value='{$cajero['id']}'>{$cajero['nombre']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <button type="button" class="btn-abrir-caja" onclick="generarReporte()" 
                        style="margin-top: 10px;">
                    📈 GENERAR REPORTE
                </button>
            </div>
        </div>
    </div>

    <!-- RESULTADOS DEL REPORTE -->
    <div id="resultadoReporte">
        <div class="caja-status" style="border-color: #3498db;">
            <div class="status-icon">📊</div>
            <div class="status-text" style="color: #3498db;">REPORTES DE VENTAS</div>
            <p style="color: #7f8c8d; font-size: 1.1rem;">Seleccione los filtros y genere un reporte</p>
            <p style="color: #95a5a6; font-size: 0.9rem;">Los resultados aparecerán aquí</p>
        </div>
    </div>
</div>
 <!-- FIN SECCIÓN REPORTES -->

           <!-- SECCIÓN CONFIGURACIÓN -->
    <!-- MODAL PAGO -->
    <div class="modal-overlay" id="modalPago">
        <div class="modal-content">
            <div class="modal-header">Procesar Pago</div>
            
            <div id="modalPedidoInfo"></div>
            
            <div class="total-display">
                <div class="label">TOTAL A PAGAR</div>
                <div class="amount" id="totalAPagar">Bs. 0.00</div>
            </div>
            
            <div class="pago-section">
                <h4>Métodos de Pago</h4>
                
                <div class="pago-metodo">
                    <input type="checkbox" id="checkEfectivo" onchange="toggleMetodoPago('efectivo')">
                    <label for="checkEfectivo">💵 Efectivo</label>
                </div>
                <div id="inputEfectivo" style="display: none;">
                    <input type="number" class="pago-input" id="montoEfectivo" 
                           placeholder="Monto en efectivo" 
                           oninput="calcularCambio()">
                </div>
                
                <div class="pago-metodo" style="margin-top: 20px;">
                    <input type="checkbox" id="checkQR" onchange="toggleMetodoPago('qr')">
                    <label for="checkQR">📱 QR</label>
                </div>
                <div id="inputQR" style="display: none;">
                    <input type="number" class="pago-input" id="montoQR" 
                           placeholder="Monto por QR" 
                           oninput="calcularCambio()">
                </div>
            </div>
            
            <div id="cambioDisplay" style="display: none;"></div>
            
            <div class="pago-section">
                <h4>Nota (Opcional)</h4>
                <textarea class="nota-area" id="notaPago" placeholder="Agregar nota al pago..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button class="btn-modal btn-confirmar" onclick="confirmarPago()">CONFIRMAR PAGO</button>
                <button class="btn-modal btn-cancelar" onclick="cerrarModalPago()">CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR PEDIDO -->
    <div class="modal-overlay" id="modalEditar">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">Editar Pedido</div>
            
            <div id="modalEditarInfo"></div>
            
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-7">
                    <h4>Productos Disponibles</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; max-height: 400px; overflow-y: auto;">
                        <?php foreach ($prod_por_cat as $cat => $productos): 
                            if (empty($productos)) continue;
                        ?>
                            <div style="margin-bottom: 20px;">
                                <h5 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px;">
                                    <?php echo $categorias_nombres[$cat]; ?>
                                </h5>
                                <?php foreach ($productos as $prod): ?>
                                    <div class="producto-editar" 
                                         onclick="agregarProductoEditar(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['precio']; ?>)">
                                        <span style="font-weight: 500;"><?php echo $prod['nombre']; ?></span>
                                        <span style="color: #27ae60; font-weight: bold;">Bs. <?php echo number_format($prod['precio'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <h4>Pedido Actual</h4>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 10px; max-height: 400px; overflow-y: auto;" id="listaEditarPedido">
                    </div>
                    
                    <div style="background: #27ae60; color: white; padding: 15px; border-radius: 10px; margin-top: 15px; text-align: center;">
                        <div style="font-size: 1rem;">TOTAL</div>
                        <div style="font-size: 2rem; font-weight: bold;" id="totalEditar">Bs. 0.00</div>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-modal btn-confirmar" onclick="guardarEdicion()">GUARDAR CAMBIOS</button>
                <button class="btn-modal btn-cancelar" onclick="cerrarModalEditar()">CANCELAR</button>
            </div>
        </div>
    </div>

    <script>
        let pedidoActual = null;
        let pedidoEditando = null;
        let productosEditando = {};
        let cajaActual = <?php echo $caja_abierta ? json_encode($caja_abierta) : 'null'; ?>;
        
        // NAVEGACIÓN
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }
        
        function cambiarSeccion(seccion) {
            // Ocultar todas las secciones
            document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            
            // Mostrar sección seleccionada
            document.getElementById('section-' + seccion).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Selección de turno visual
        document.querySelectorAll('.turno-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.turno-option').forEach(opt => opt.classList.remove('selected'));
                this.closest('.turno-option').classList.add('selected');
            });
        });
        
        // GESTIÓN DE DENOMINACIONES - CÓDIGO CORREGIDO
        function toggleDenominacion(tipo) {
            const checkbox = event.target;
            const item = document.getElementById('denom-' + tipo);
            const input = document.getElementById('cant-' + tipo);
            
            if (checkbox.checked) {
                item.classList.remove('disabled');
                input.disabled = false;
                input.value = 0; // Reset a 0 cuando se activa
            } else {
                item.classList.add('disabled');
                input.disabled = true;
                input.value = 0; // Asegurar que sea 0 cuando se desactiva
                calcularSubtotal(tipo, parseFloat(tipo.replace(/[a-z]/g, '')));
            }
            calcularTotalApertura(); // Actualizar total
        }
        
        function calcularSubtotal(tipo, valor) {
            const cantidad = parseFloat(document.getElementById('cant-' + tipo).value) || 0;
            const subtotal = cantidad * valor;
            document.getElementById('subtotal-' + tipo).textContent = 'Bs. ' + subtotal.toFixed(2);
            calcularTotalApertura();
        }
        
        function calcularTotalApertura() {
            let total = 0;
            
            // Solo sumar las denominaciones que no están desactivadas
            if (!document.getElementById('denom-m1').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-m1').value) || 0) * 1;
            }
            if (!document.getElementById('denom-m2').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-m2').value) || 0) * 2;
            }
            if (!document.getElementById('denom-m5').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-m5').value) || 0) * 5;
            }
            if (!document.getElementById('denom-b10').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-b10').value) || 0) * 10;
            }
            if (!document.getElementById('denom-b20').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-b20').value) || 0) * 20;
            }
            if (!document.getElementById('denom-b50').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-b50').value) || 0) * 50;
            }
            if (!document.getElementById('denom-b100').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-b100').value) || 0) * 100;
            }
            if (!document.getElementById('denom-b200').classList.contains('disabled')) {
                total += (parseFloat(document.getElementById('cant-b200').value) || 0) * 200;
            }
            
            document.getElementById('totalApertura').textContent = 'Bs. ' + total.toFixed(2);
        }
        
      // APERTURA DE CAJA - CÓDIGO CORREGIDO
document.getElementById('formAperturaCaja')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const turno = document.querySelector('input[name="turno"]:checked')?.value;
    
    if (!turno) {
        alert('Debe seleccionar un turno');
        return;
    }
    
    // CORRECCIÓN: Enviar estructura simple con solo las cantidades
    const denominaciones = {
        m1: parseInt(document.getElementById('cant-m1').value) || 0,
        m2: parseInt(document.getElementById('cant-m2').value) || 0,
        m5: parseInt(document.getElementById('cant-m5').value) || 0,
        b10: parseInt(document.getElementById('cant-b10').value) || 0,
        b20: parseInt(document.getElementById('cant-b20').value) || 0,
        b50: parseInt(document.getElementById('cant-b50').value) || 0,
        b100: parseInt(document.getElementById('cant-b100').value) || 0,
        b200: parseInt(document.getElementById('cant-b200').value) || 0
    };
    
    const formData = new FormData();
    formData.append('turno', turno);
    formData.append('denominaciones', JSON.stringify(denominaciones));
    
    fetch('abrir_caja.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Caja abierta exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error de conexión: ' + error);
    });
});

function confirmarPago() {
    if (!pedidoActual) return;
    
    if (!cajaActual) {
        alert('Debe abrir la caja antes de procesar pagos');
        return;
    }
    
    const total = parseFloat(pedidoActual.total);
    const efectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
    const qr = parseFloat(document.getElementById('montoQR').value) || 0;
    const pagado = efectivo + qr;
    const nota = document.getElementById('notaPago').value;
    
    if (pagado < total) {
        alert('El monto pagado es menor al total');
        return;
    }
    
    if (!document.getElementById('checkEfectivo').checked && !document.getElementById('checkQR').checked) {
        alert('Debe seleccionar al menos un método de pago');
        return;
    }
    
    const formData = new FormData();
    formData.append('pedido_id', pedidoActual.id);
    formData.append('efectivo', efectivo);
    formData.append('qr', qr);
    formData.append('cambio', pagado - total);
    formData.append('nota', nota);
    formData.append('caja_id', cajaActual.id); // ← ESTA LÍNEA ES IMPORTANTE
    
    fetch('procesar_pago.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Pago procesado exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error de conexión: ' + error);
    });
}
        
        // CERRAR CAJA - CÓDIGO CORREGIDO
        function cerrarCaja() {
            if (!confirm('¿Está seguro de cerrar la caja? Esta acción no se puede deshacer.')) return;
            
            fetch('cerrar_caja.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Caja cerrada exitosamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        }
        
        // PAGOS
        function abrirModalPago(pedido, items) {
            pedidoActual = pedido;
            
            let itemsHTML = '';
            items.forEach(item => {
                itemsHTML += `
                    <div class="item-row">
                        <span>${item.cantidad}x ${item.nombre}</span>
                        <span>Bs. ${(item.precio_unitario * item.cantidad).toFixed(2)}</span>
                    </div>
                `;
            });
            
            document.getElementById('modalPedidoInfo').innerHTML = `
                <div class="pedido-info">
                    <div class="info-row">
                        <span class="info-label">Mesa:</span>
                        <span class="info-value">${pedido.mesa_numero}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Garzón:</span>
                        <span class="info-value">${pedido.garzon_nombre}</span>
                    </div>
                </div>
                <div class="pedido-items">${itemsHTML}</div>
            `;
            
            document.getElementById('totalAPagar').textContent = `Bs. ${parseFloat(pedido.total).toFixed(2)}`;
            
            // Reset
            document.getElementById('checkEfectivo').checked = false;
            document.getElementById('checkQR').checked = false;
            document.getElementById('inputEfectivo').style.display = 'none';
            document.getElementById('inputQR').style.display = 'none';
            document.getElementById('montoEfectivo').value = '';
            document.getElementById('montoQR').value = '';
            document.getElementById('notaPago').value = '';
            document.getElementById('cambioDisplay').style.display = 'none';
            
            document.getElementById('modalPago').classList.add('show');
        }
        
        function cerrarModalPago() {
            document.getElementById('modalPago').classList.remove('show');
            pedidoActual = null;
        }
        
        function toggleMetodoPago(metodo) {
            const check = document.getElementById(`check${metodo.charAt(0).toUpperCase() + metodo.slice(1)}`);
            const input = document.getElementById(`input${metodo.charAt(0).toUpperCase() + metodo.slice(1)}`);
            
            if (check.checked) {
                input.style.display = 'block';
            } else {
                input.style.display = 'none';
                document.getElementById(`monto${metodo.charAt(0).toUpperCase() + metodo.slice(1)}`).value = '';
            }
            calcularCambio();
        }
        
        function calcularCambio() {
            if (!pedidoActual) return;
            
            const total = parseFloat(pedidoActual.total);
            const efectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
            const qr = parseFloat(document.getElementById('montoQR').value) || 0;
            const pagado = efectivo + qr;
            const diferencia = pagado - total;
            
            const cambioDiv = document.getElementById('cambioDisplay');
            
            if (pagado > 0) {
                cambioDiv.style.display = 'block';
                
                if (diferencia >= 0) {
                    cambioDiv.className = 'cambio-display positivo';
                    cambioDiv.innerHTML = `
                        <div style="font-size: 1rem;">CAMBIO</div>
                        <div style="font-size: 1.8rem; font-weight: bold;">Bs. ${diferencia.toFixed(2)}</div>
                    `;
                } else {
                    cambioDiv.className = 'cambio-display';
                    cambioDiv.innerHTML = `
                        <div style="font-size: 1rem;">FALTA</div>
                        <div style="font-size: 1.8rem; font-weight: bold;">Bs. ${Math.abs(diferencia).toFixed(2)}</div>
                    `;
                }
            } else {
                cambioDiv.style.display = 'none';
            }
        }
        
        function confirmarPago() {
            if (!pedidoActual) return;
            
            if (!cajaActual) {
                alert('Debe abrir la caja antes de procesar pagos');
                return;
            }
            
            const total = parseFloat(pedidoActual.total);
            const efectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
            const qr = parseFloat(document.getElementById('montoQR').value) || 0;
            const pagado = efectivo + qr;
            const nota = document.getElementById('notaPago').value;
            
            if (pagado < total) {
                alert('El monto pagado es menor al total');
                return;
            }
            
            if (!document.getElementById('checkEfectivo').checked && !document.getElementById('checkQR').checked) {
                alert('Debe seleccionar al menos un método de pago');
                return;
            }
            
            const formData = new FormData();
            formData.append('pedido_id', pedidoActual.id);
            formData.append('efectivo', efectivo);
            formData.append('qr', qr);
            formData.append('cambio', pagado - total);
            formData.append('nota', nota);
            formData.append('caja_id', cajaActual.id);
            
            fetch('procesar_pago.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Pago procesado exitosamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        }
        
        // EDICIÓN DE PEDIDOS
        function editarPedido(pedidoId) {
            fetch('obtener_pedido.php?id=' + pedidoId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        pedidoEditando = data.pedido;
                        productosEditando = {};
                        
                        data.items.forEach(item => {
                            productosEditando[item.producto_id] = {
                                nombre: item.nombre,
                                precio: parseFloat(item.precio_unitario),
                                cantidad: parseInt(item.cantidad)
                            };
                        });
                        
                        document.getElementById('modalEditarInfo').innerHTML = `
                            <div class="pedido-info">
                                <div class="info-row">
                                    <span class="info-label">Mesa:</span>
                                    <span class="info-value">${data.pedido.mesa_numero}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Garzón:</span>
                                    <span class="info-value">${data.pedido.garzon_nombre}</span>
                                </div>
                            </div>
                        `;
                        
                        actualizarListaEditar();
                        document.getElementById('modalEditar').classList.add('show');
                    } else {
                        alert('Error al cargar pedido');
                    }
                });
        }
        
        function cerrarModalEditar() {
            document.getElementById('modalEditar').classList.remove('show');
            pedidoEditando = null;
            productosEditando = {};
        }
        
        function agregarProductoEditar(id, nombre, precio) {
            if (!productosEditando[id]) {
                productosEditando[id] = {
                    nombre: nombre,
                    precio: precio,
                    cantidad: 0
                };
            }
            productosEditando[id].cantidad++;
            actualizarListaEditar();
        }
        
        function cambiarCantidadEditar(id, cambio) {
            if (productosEditando[id]) {
                productosEditando[id].cantidad += cambio;
                if (productosEditando[id].cantidad <= 0) {
                    delete productosEditando[id];
                }
                actualizarListaEditar();
            }
        }
        
        function actualizarListaEditar() {
            const lista = document.getElementById('listaEditarPedido');
            lista.innerHTML = '';
            let total = 0;
            
            if (Object.keys(productosEditando).length === 0) {
                lista.innerHTML = '<p style="text-align: center; color: #7f8c8d; padding: 20px;">No hay productos en el pedido</p>';
            } else {
                for (const [id, item] of Object.entries(productosEditando)) {
                    const subtotal = item.precio * item.cantidad;
                    total += subtotal;
                    
                    const itemDiv = document.createElement('div');
                    itemDiv.style.cssText = 'background: white; padding: 12px; margin-bottom: 10px; border-radius: 8px; border: 2px solid #dee2e6;';
                    itemDiv.innerHTML = `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="font-size: 0.95rem;">${item.nombre}</strong>
                            <span style="color: #27ae60; font-weight: bold;">Bs. ${subtotal.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <button onclick="cambiarCantidadEditar('${id}', -1)" 
                                    style="width: 35px; height: 35px; border: none; background: #e74c3c; color: white; border-radius: 6px; font-size: 1.2rem; font-weight: bold; cursor: pointer;">-</button>
                            <span style="font-size: 1.3rem; font-weight: bold; min-width: 30px; text-align: center;">${item.cantidad}</span>
                            <button onclick="cambiarCantidadEditar('${id}', 1)" 
                                    style="width: 35px; height: 35px; border: none; background: #27ae60; color: white; border-radius: 6px; font-size: 1.2rem; font-weight: bold; cursor: pointer;">+</button>
                        </div>
                    `;
                    lista.appendChild(itemDiv);
                }
            }
            
            document.getElementById('totalEditar').textContent = `Bs. ${total.toFixed(2)}`;
        }
        
        function guardarEdicion() {
            if (!pedidoEditando) return;
            
            if (Object.keys(productosEditando).length === 0) {
                alert('Debe tener al menos un producto en el pedido');
                return;
            }
            
            const formData = new FormData();
            formData.append('pedido_id', pedidoEditando.id);
            formData.append('productos', JSON.stringify(productosEditando));
            
            fetch('actualizar_pedido.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Pedido actualizado exitosamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        }
        
        function eliminarPedido(id) {
            if (!confirm('¿Está seguro de eliminar este pedido?')) return;
            
            fetch('eliminar_pedido.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'pedido_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Pedido eliminado');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            });
        }
        // FUNCIONES DE REPORTES
function generarReporte() {
    const fecha = document.getElementById('filtroFecha').value;
    const turno = document.getElementById('filtroTurno').value;
    const cajero = document.getElementById('filtroCajero').value;
    
    if (!fecha) {
        alert('Por favor seleccione una fecha');
        return;
    }
    
    // Mostrar loading
document.getElementById('resultadoReporte').innerHTML = `
    <div style="text-align: center; padding: 40px;">
        <div class="spinner-reporte"></div>
        <p style="margin-top: 15px; color: #667eea; font-size: 1.1rem;">Generando reporte...</p>
    </div>
`;
    
    const formData = new FormData();
    formData.append('fecha', fecha);
    formData.append('turno', turno);
    formData.append('cajero_id', cajero);
    
    fetch('generar_reporte.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarResultadoReporte(data);
        } else {
            alert('Error: ' + data.message);
            document.getElementById('resultadoReporte').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <div style="font-size: 4rem; margin-bottom: 15px;">❌</div>
                    <h4>Error al generar reporte</h4>
                    <p>${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        alert('Error de conexión: ' + error);
        document.getElementById('resultadoReporte').innerHTML = `
            <div style="text-align: center; padding: 40px; color: #e74c3c;">
                <div style="font-size: 4rem; margin-bottom: 15px;">❌</div>
                <h4>Error de conexión</h4>
                <p>No se pudo conectar con el servidor</p>
            </div>
        `;
    });
}
// Mostrar resultados del reporte
function mostrarResultadoReporte(data) {
    const { resumen, detalle, filtros } = data;
    const fechaFormateada = new Date(filtros.fecha + 'T00:00:00').toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    let html = `
        <div class="caja-info">
            <h3 style="color: #2c3e50; margin-bottom: 20px; text-align: center; text-transform: uppercase;">
                📈 Reporte de Ventas - ${fechaFormateada}
            </h3>
            
            <!-- RESUMEN GENERAL -->
            <div class="reporte-resumen">
                <h4 style="margin-bottom: 20px; text-align: center;">📊 Resumen General del Día</h4>
                <div class="row text-center">
                    <div class="col-md-4 resumen-item">
                        <div class="resumen-label">TOTAL VENTAS</div>
                        <div class="resumen-valor">Bs. ${parseFloat(resumen.total_ventas || 0).toFixed(2)}</div>
                    </div>
                    <div class="col-md-4 resumen-item">
                        <div class="resumen-label">TOTAL PEDIDOS</div>
                        <div class="resumen-valor">${resumen.total_pedidos || 0}</div>
                    </div>
                    <div class="col-md-4 resumen-item">
                        <div class="resumen-label">CAJEROS ACTIVOS</div>
                        <div class="resumen-valor">${resumen.total_cajeros || 0}</div>
                    </div>
                </div>
            </div>
    `;
    
    // Si no hay datos
    if (detalle.length === 0) {
        html += `
            <div class="caja-status" style="border-color: #95a5a6; margin-top: 20px;">
                <div class="status-icon">📭</div>
                <div class="status-text" style="color: #95a5a6;">NO HAY DATOS</div>
                <p style="color: #7f8c8d; font-size: 1.1rem;">No se encontraron ventas para los filtros seleccionados</p>
            </div>
        `;
    } else {
        // Agrupar por turno
        const porTurno = {};
        detalle.forEach(item => {
            if (!porTurno[item.turno]) {
                porTurno[item.turno] = [];
            }
            porTurno[item.turno].push(item);
        });
        
        // Mostrar por turno
        Object.keys(porTurno).forEach(turno => {
            const itemsTurno = porTurno[turno];
            const totalTurno = itemsTurno.reduce((sum, item) => sum + parseFloat(item.total_ventas), 0);
            const totalPedidosTurno = itemsTurno.reduce((sum, item) => sum + parseInt(item.total_pedidos), 0);
            
            const icono = turno === 'mañana' ? '🌅' : '🌙';
            const nombreTurno = turno === 'mañana' ? 'TURNO MAÑANA (12:00 - 16:00)' : 'TURNO NOCHE (19:00 - 00:00)';
            const colorTurno = turno === 'mañana' ? '#e67e22' : '#9b59b6';
            
            html += `
                <div class="reporte-turno" style="border-left-color: ${colorTurno};">
                    <h5 style="color: ${colorTurno}; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        ${icono} ${nombreTurno}
                    </h5>
                    
                    <div style="display: flex; justify-content: space-between; background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <div>
                            <strong style="color: #2c3e50;">Ventas Totales:</strong>
                            <span style="color: #27ae60; font-weight: bold; font-size: 1.2rem; margin-left: 10px;">
                                Bs. ${totalTurno.toFixed(2)}
                            </span>
                        </div>
                        <div>
                            <strong style="color: #2c3e50;">Total Pedidos:</strong>
                            <span style="color: #3498db; font-weight: bold; font-size: 1.2rem; margin-left: 10px;">
                                ${totalPedidosTurno}
                            </span>
                        </div>
                    </div>
            `;
            
            // Detalle por cajero en el turno
            html += `<div style="margin-top: 10px;"><strong>Desglose por Cajero:</strong></div>`;
            itemsTurno.forEach(item => {
                html += `
                    <div class="reporte-cajero">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>${item.cajero_nombre}</strong>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: #27ae60; font-weight: bold; font-size: 1.1rem;">
                                    Bs. ${parseFloat(item.total_ventas).toFixed(2)}
                                </div>
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    ${item.total_pedidos} pedido(s)
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
        });
        
        // Resumen por cajero (todos los turnos)
        if (detalle.length > 0) {
            html += `
                <div class="reporte-detalle">
                    <h5 style="color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        👤 Resumen por Cajero
                    </h5>
            `;
            
            // Agrupar por cajero (sumar todos los turnos)
            const porCajero = {};
            detalle.forEach(item => {
                if (!porCajero[item.cajero_id]) {
                    porCajero[item.cajero_id] = {
                        nombre: item.cajero_nombre,
                        total_ventas: 0,
                        total_pedidos: 0
                    };
                }
                porCajero[item.cajero_id].total_ventas += parseFloat(item.total_ventas);
                porCajero[item.cajero_id].total_pedidos += parseInt(item.total_pedidos);
            });
            
            // Ordenar por total de ventas (mayor a menor)
            const cajerosOrdenados = Object.values(porCajero).sort((a, b) => b.total_ventas - a.total_ventas);
            
            cajerosOrdenados.forEach((cajero, index) => {
                const top3 = index < 3 ? '🏆 ' : '';
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: white; margin: 8px 0; border-radius: 8px; border-left: 4px solid #3498db;">
                        <div style="font-weight: bold; color: #2c3e50;">
                            ${top3}${cajero.nombre}
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #27ae60; font-weight: bold;">
                                Bs. ${cajero.total_ventas.toFixed(2)}
                            </div>
                            <div style="color: #7f8c8d; font-size: 0.9rem;">
                                ${cajero.total_pedidos} pedidos
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
        }
    }
    
    html += `</div>`;
    
    document.getElementById('resultadoReporte').innerHTML = html;
}
// fin resultados reporte
    </script>
</body>
</html>