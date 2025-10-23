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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
/* ESTILOS MEJORADOS PARA MESAS */
.mesas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.mesa-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    transition: all 0.3s ease;
    border-top: 5px solid;
    position: relative;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.mesa-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.mesa-libre {
    border-top-color: #27ae60;
    background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
}

.mesa-ocupada {
    border-top-color: #e74c3c;
    background: linear-gradient(135deg, #fff8f8 0%, #fff0f0 100%);
}

.mesa-reservada {
    border-top-color: #f39c12;
    background: linear-gradient(135deg, #fffbf0 0%, #fff8e0 100%);
}

.mesa-header {
    margin-bottom: 15px;
}

.mesa-numero {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

.mesa-estado {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
}

.estado-libre {
    background: #27ae60;
    color: white;
}

.estado-ocupada {
    background: #e74c3c;
    color: white;
}

.estado-reservada {
    background: #f39c12;
    color: white;
}

.mesa-info {
    margin-bottom: 15px;
}

.mesa-capacidad {
    font-size: 0.9rem;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.mesa-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-mesa {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
    max-width: 80px;
}

.btn-mesa-editar {
    background: #3498db;
    color: white;
}

.btn-mesa-editar:hover {
    background: #2980b9;
}

.btn-mesa-eliminar {
    background: #e74c3c;
    color: white;
}

.btn-mesa-eliminar:hover {
    background: #c0392b;
}

/* Indicador de actualización */
.actualizando-mesas {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #3498db;
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.8rem;
    animation: pulse 2s infinite;
    z-index: 1000;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

/* Responsive */
@media (max-width: 768px) {
    .mesas-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .mesa-card {
        padding: 15px;
        min-height: 120px;
    }
    
    .mesa-numero {
        font-size: 1.5rem;
    }
}
/* Spinner para loading */
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
        <h1>Cajero</h1>
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
                <div class="menu-item" onclick="cambiarSeccion('mesas')">
                    <span class="menu-icon">🍽️</span>
                    <span class="menu-text">Mesas</span>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content-area">
            <!-- SECCIÓN PEDIDOS -->
            <div class="section-content" id="section-pedidos">
                <?php if ($caja_abierta): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Pedidos</h2>
                        <small style="color: #95a5a6;">
                            Última actualización: <span id="ultimaActualizacionPedidos">--:--:--</span>
                        </small>
                    </div>
                    <div class="pedidos-grid" id="pedidosGrid">
                            <!-- Los pedidos se cargarán dinámicamente aquí -->
                        </div>

                        <!-- Estado de carga -->
                        <div id="pedidosLoading" style="text-align: center; padding: 40px;">
                            <div class="spinner-reporte" style="margin: 0 auto 15px;"></div>
                            <p style="color: #667eea;">Cargando pedidos...</p>
                        </div>

                        <!-- Estado vacío -->
                        <div id="pedidosVacio" style="display: none; text-align: center; padding: 40px; color: #7f8c8d;">
                            <div style="font-size: 4rem; margin-bottom: 15px;">📭</div>
                            <h4>No hay pedidos activos</h4>
                            <p>Los pedidos aparecerán aquí cuando sean creados</p>
                        </div>

                        <!-- Estado caja cerrada -->
                        <div id="pedidosCajaCerrada" style="display: none;">
                            <div class="caja-status caja-cerrada">
                                <div class="status-icon">🔒</div>
                                <div class="status-text">CAJA CERRADA</div>
                                <p style="color: #7f8c8d; font-size: 1.1rem;">Debe abrir la caja antes de procesar pedidos</p>
                            </div>
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

          <!-- SECCIÓN MESAS -->
<div class="section-content" id="section-mesas">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 10px;">
                🍽️ Gestión de Mesas
            </h2>
            <p style="color: #7f8c8d; margin: 5px 0 0 0; font-size: 0.9rem;">
            </p>
        </div>
        <button class="btn-abrir-caja" onclick="abrirModalMesa()" 
                style="width: auto; padding: 12px 25px; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
            <span>➕</span>
            Agregar Mesa
        </button>
    </div>

    <!-- Indicadores de estado -->
    <div class="caja-info" style="margin-bottom: 25px;">
        <h4 style="color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
            📊 Resumen de Estados
        </h4>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 8px;">
                <div style="width: 12px; height: 12px; background: #27ae60; border-radius: 50%;"></div>
                <span style="font-weight: 500;">Libre: <span id="contadorLibre">0</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 8px;">
                <div style="width: 12px; height: 12px; background: #e74c3c; border-radius: 50%;"></div>
                <span style="font-weight: 500;">Ocupada: <span id="contadorOcupada">0</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 8px;">
                <div style="width: 12px; height: 12px; background: #f39c12; border-radius: 50%;"></div>
                <span style="font-weight: 500;">Reservada: <span id="contadorReservada">0</span></span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 8px;">
                <div style="width: 12px; height: 12px; background: #3498db; border-radius: 50%;"></div>
                <span style="font-weight: 500;">Total: <span id="contadorTotal">0</span></span>
            </div>
        </div>
    </div>

    <!-- Grid de mesas -->
    <div class="caja-info">
        <div id="mesasGrid" class="mesas-grid">
            <!-- Las mesas se cargarán aquí -->
        </div>
        
        <!-- Estado vacío -->
        <div id="mesasVacio" style="display: none; text-align: center; padding: 40px; color: #7f8c8d;">
            <div style="font-size: 4rem; margin-bottom: 15px;">🍽️</div>
            <h4>No hay mesas registradas</h4>
            <p>Agregue la primera mesa haciendo clic en el botón "Agregar Mesa"</p>
        </div>
    </div>
    
    <!-- Indicador de última actualización -->
    <div style="text-align: center; margin-top: 15px;">
        <small style="color: #95a5a6;">
            Última actualización: <span id="ultimaActualizacion">--:--:--</span>
        </small>
    </div>|
</div>
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
                        placeholder="Monto en efectivo recibido" 
                        oninput="calcularCambio()"
                        step="0.01" min="0">
                </div>
                
                <div class="pago-metodo" style="margin-top: 20px;">
                    <input type="checkbox" id="checkQR" onchange="toggleMetodoPago('qr')">
                    <label for="checkQR">📱 QR</label>
                </div>
                <div id="inputQR" style="display: none;">
                    <input type="number" class="pago-input" id="montoQR" 
                        placeholder="Monto por QR" 
                        oninput="calcularCambio()"
                        step="0.01" min="0">
                    <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                        <span id="qrHelpText">Se calculará automáticamente</span>
                    </small>
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

    <!-- MODAL MESA -->
    <div class="modal-overlay" id="modalMesa">
        <div class="modal-content">
            <div class="modal-header" id="modalMesaTitulo">Agregar Mesa</div>
            
            <form id="formMesa" onsubmit="event.preventDefault(); guardarMesa();">
                <input type="hidden" id="mesaId" value="">
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">
                        🔢 Número de Mesa *
                    </label>
                    <input type="text" class="cantidad-input" id="mesaNumero" required 
                           placeholder="Ej: 13, A1, Terraza-1, etc."
                           style="font-size: 1.1rem; padding: 12px;">
                    <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                        Identificador único para la mesa
                    </small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">
                        👥 Capacidad *
                    </label>
                    <input type="number" class="cantidad-input" id="mesaCapacidad" required 
                           min="1" max="20" value="4"
                           style="font-size: 1.1rem; padding: 12px;">
                    <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                        Número máximo de personas (1-20)
                    </small>
                </div>
                
                <div style="margin-bottom: 20px; display: none;" id="mesaEstadoContainer">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">
                        📊 Estado Actual
                    </label>
                    <select class="cantidad-input" id="mesaEstado" style="font-size: 1rem; padding: 12px;">
                        <option value="libre">✅ Libre - Disponible para clientes</option>
                        <option value="ocupada">❌ Ocupada - En uso actualmente</option>
                        <option value="reservada">⏰ Reservada - Reservada para más tarde</option>
                    </select>
                </div>
            </form>
            
            <div class="modal-actions">
                <button class="btn-modal btn-confirmar" onclick="guardarMesa()">
                    💾 GUARDAR
                </button>
                <button class="btn-modal btn-cancelar" onclick="cerrarModalMesa()">
                    ❌ CANCELAR
                </button>
            </div>
        </div>
    </div>

    <script>
        // VARIABLES PARA PEDIDOS
            let intervaloPedidos = null;

            // FUNCIONES PARA ACTUALIZACIÓN DE PEDIDOS EN TIEMPO REAL
            function cargarPedidos() {
                // Mostrar loading
                document.getElementById('pedidosLoading').style.display = 'block';
                document.getElementById('pedidosGrid').style.display = 'none';
                document.getElementById('pedidosVacio').style.display = 'none';
                document.getElementById('pedidosCajaCerrada').style.display = 'none';
                
                fetch('actualizar_pedidos.php')
                    .then(response => response.json())
                    .then(data => {
                        // Ocultar loading
                        document.getElementById('pedidosLoading').style.display = 'none';
                        
                        if (data.success) {
                            // Actualizar timestamp
                            if (data.timestamp) {
                                document.getElementById('ultimaActualizacionPedidos').textContent = data.timestamp;
                            }
                            
                            if (!data.caja_abierta) {
                                // Mostrar estado de caja cerrada
                                document.getElementById('pedidosCajaCerrada').style.display = 'block';
                                return;
                            }
                            
                            if (data.pedidos.length === 0) {
                                // Mostrar estado vacío
                                document.getElementById('pedidosVacio').style.display = 'block';
                                return;
                            }
                            
                            // Mostrar pedidos
                            document.getElementById('pedidosGrid').style.display = 'grid';
                            mostrarPedidos(data.pedidos);
                        } else {
                            mostrarErrorPedidos('Error al cargar pedidos: ' + data.message);
                        }
                    })
                    .catch(error => {
                        document.getElementById('pedidosLoading').style.display = 'none';
                        mostrarErrorPedidos('Error de conexión: ' + error);
                    });
            }

            function mostrarPedidos(pedidos) {
                const grid = document.getElementById('pedidosGrid');
                let html = '';
                
                pedidos.forEach(pedido => {
                    const hora = new Date(pedido.fecha_pedido).toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    let itemsHTML = '';
                    if (pedido.items && pedido.items.length > 0) {
                        pedido.items.forEach(item => {
                            itemsHTML += `
                                <div class="item-row">
                                    <span>${item.cantidad}x ${item.nombre}</span>
                                    <span>Bs. ${(item.precio_unitario * item.cantidad).toFixed(2)}</span>
                                </div>
                            `;
                        });
                    }
                    
                    // Determinar color del borde según estado
                    let borderColor = '#3498db'; // Por defecto azul
                    if (pedido.estado === 'preparacion') borderColor = '#f39c12'; // Naranja
                    if (pedido.estado === 'listo') borderColor = '#27ae60'; // Verde
                    
                    html += `
                        <div class="pedido-card" style="border-left-color: ${borderColor};">
                            <div class="pedido-header">
                                <div class="pedido-mesa">Mesa ${pedido.mesa_numero}</div>
                                <div class="pedido-hora">${hora}</div>
                            </div>
                            
                            <div class="pedido-info">
                                <div class="info-row">
                                    <span class="info-label">Garzón:</span>
                                    <span class="info-value">${pedido.garzon_nombre}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Items:</span>
                                    <span class="info-value">${pedido.total_items}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Estado:</span>
                                    <span class="info-value" style="color: ${borderColor}; font-weight: bold;">
                                        ${pedido.estado.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="pedido-items">
                                ${itemsHTML}
                            </div>
                            
                            <div class="pedido-total">
                                TOTAL: Bs. ${parseFloat(pedido.total).toFixed(2)}
                            </div>
                            
                            <div class="pedido-actions">
                                <button class="btn-action btn-pagar" 
                                        onclick='abrirModalPago(${JSON.stringify(pedido)}, ${JSON.stringify(pedido.items || [])})'>
                                    PAGAR
                                </button>
                                <button class="btn-action btn-editar" onclick="editarPedido(${pedido.id})">
                                    EDITAR
                                </button>
                                <button class="btn-action btn-eliminar" onclick="eliminarPedido(${pedido.id})">
                                    ELIMINAR
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                grid.innerHTML = html;
            }

            function mostrarErrorPedidos(mensaje) {
                const grid = document.getElementById('pedidosGrid');
                grid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #e74c3c;">
                        <div style="font-size: 4rem; margin-bottom: 15px;">❌</div>
                        <h4>Error al cargar pedidos</h4>
                        <p>${mensaje}</p>
                        <button class="btn-abrir-caja" onclick="cargarPedidos()" 
                                style="width: auto; padding: 10px 20px; font-size: 0.9rem; margin-top: 15px;">
                            🔄 Reintentar
                        </button>
                    </div>
                `;
            }

            // ACTUALIZACIÓN AUTOMÁTICA DE PEDIDOS
            function iniciarActualizacionPedidos() {
                // Cargar pedidos inmediatamente
                cargarPedidos();
                
                // Actualizar cada 25 segundos
                intervaloPedidos = setInterval(cargarPedidos, 25000);
            }

            function detenerActualizacionPedidos() {
                if (intervaloPedidos) {
                    clearInterval(intervaloPedidos);
                    intervaloPedidos = null;
                }
            }
        let pedidoActual = null;
        let pedidoEditando = null;
        let productosEditando = {};
        let cajaActual = <?php echo $caja_abierta ? json_encode($caja_abierta) : 'null'; ?>;
        
        // VARIABLES PARA MESAS
        let intervaloMesas = null;
        let ultimaActualizacion = null;
        
        // NAVEGACIÓN
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }
        
       function cambiarSeccion(seccion) {
            // Detener actualizaciones anteriores
            detenerActualizacionMesas();
            detenerActualizacionPedidos();
            
            // Ocultar todas las secciones
            document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            
            // Mostrar sección seleccionada
            document.getElementById('section-' + seccion).classList.add('active');
            event.currentTarget.classList.add('active');
            
            // Iniciar actualizaciones específicas de cada sección
            if (seccion === 'mesas') {
                iniciarActualizacionMesas();
            } else if (seccion === 'pedidos') {
                iniciarActualizacionPedidos();
            }
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
            setTimeout(configurarEventListenersPago, 100);
        }
        
        function cerrarModalPago() {
            document.getElementById('modalPago').classList.remove('show');
            pedidoActual = null;
        }
        
       function toggleMetodoPago(metodo) {
            const check = document.getElementById(`check${metodo.charAt(0).toUpperCase() + metodo.slice(1)}`);
            const input = document.getElementById(`input${metodo.charAt(0).toUpperCase() + metodo.slice(1)}`);
            const inputField = document.getElementById(`monto${metodo.charAt(0).toUpperCase() + metodo.slice(1)}`);
            
            if (check.checked) {
                input.style.display = 'block';
                
                // Si es QR y es el único método seleccionado, poner el total automáticamente y bloquear
                if (metodo === 'qr' && !document.getElementById('checkEfectivo').checked) {
                    inputField.value = parseFloat(pedidoActual.total).toFixed(2);
                    inputField.readOnly = true;
                    inputField.style.backgroundColor = '#f8f9fa';
                } 
                // Si es QR pero también está seleccionado efectivo (mixto), permitir editar
                else if (metodo === 'qr' && document.getElementById('checkEfectivo').checked) {
                    inputField.readOnly = false;
                    inputField.style.backgroundColor = 'white';
                    inputField.value = ''; // Limpiar para que el usuario ingrese el monto QR
                }
                // Si es efectivo, siempre permitir editar
                else if (metodo === 'efectivo') {
                    inputField.readOnly = false;
                    inputField.style.backgroundColor = 'white';
                    inputField.value = '';
                }
            } else {
                input.style.display = 'none';
                inputField.value = '';
            }
            calcularCambio();
        }
        
        function calcularCambio() {
            if (!pedidoActual) return;
            
            const total = parseFloat(pedidoActual.total);
            let efectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
            let qr = parseFloat(document.getElementById('montoQR').value) || 0;
            const pagado = efectivo + qr;
            const diferencia = pagado - total;
            
            const cambioDiv = document.getElementById('cambioDisplay');
            
            // Si solo QR está seleccionado, calcular automáticamente el monto QR faltante
            if (document.getElementById('checkQR').checked && !document.getElementById('checkEfectivo').checked) {
                qr = total; // Siempre usar el total completo para QR único
                document.getElementById('montoQR').value = total.toFixed(2);
            }
            
            // Si es mixto, calcular automáticamente el QR como el faltante
            if (document.getElementById('checkQR').checked && document.getElementById('checkEfectivo').checked) {
                const qrInput = document.getElementById('montoQR');
                if (!qrInput.readOnly) { // Solo si está editable (mixto)
                    const faltante = total - efectivo;
                    if (faltante > 0) {
                        qrInput.value = faltante.toFixed(2);
                        qr = faltante;
                    } else {
                        qrInput.value = '0.00';
                        qr = 0;
                    }
                }
            }
            
            const pagadoFinal = efectivo + qr;
            const diferenciaFinal = pagadoFinal - total;
            
            if (pagadoFinal > 0) {
                cambioDiv.style.display = 'block';
                
                if (diferenciaFinal >= 0) {
                    cambioDiv.className = 'cambio-display positivo';
                    cambioDiv.innerHTML = `
                        <div style="font-size: 1rem;">CAMBIO A ENTREGAR</div>
                        <div style="font-size: 1.8rem; font-weight: bold;">Bs. ${diferenciaFinal.toFixed(2)}</div>
                    `;
                } else {
                    cambioDiv.className = 'cambio-display';
                    cambioDiv.innerHTML = `
                        <div style="font-size: 1rem;">FALTA POR PAGAR</div>
                        <div style="font-size: 1.8rem; font-weight: bold;">Bs. ${Math.abs(diferenciaFinal).toFixed(2)}</div>
                    `;
                }
            } else {
                cambioDiv.style.display = 'none';
            }
        }
        function configurarEventListenersPago() {
                const montoEfectivo = document.getElementById('montoEfectivo');
                if (montoEfectivo) {
                    // Agregar event listener para cambios en efectivo
                    montoEfectivo.addEventListener('input', function() {
                        // Si es pago mixto, recalcular automáticamente el QR
                        if (document.getElementById('checkEfectivo').checked && 
                            document.getElementById('checkQR').checked) {
                            calcularCambio();
                        }
                    });
                }
            }
        
        function confirmarPago() {
            if (!pedidoActual) return;
            
            if (!cajaActual) {
                alert('Debe abrir la caja antes de procesar pagos');
                return;
            }
            
            const total = parseFloat(pedidoActual.total);
            let efectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
            let qr = parseFloat(document.getElementById('montoQR').value) || 0;
            const nota = document.getElementById('notaPago').value;
            
            // Validaciones
            if (!document.getElementById('checkEfectivo').checked && !document.getElementById('checkQR').checked) {
                alert('Debe seleccionar al menos un método de pago');
                return;
            }
            
            // Si solo QR está seleccionado, usar el total completo
            if (document.getElementById('checkQR').checked && !document.getElementById('checkEfectivo').checked) {
                qr = total;
                document.getElementById('montoQR').value = total.toFixed(2);
            }
            
            // Si solo efectivo está seleccionado, validar que se ingrese monto
            if (document.getElementById('checkEfectivo').checked && !document.getElementById('checkQR').checked) {
                if (efectivo <= 0) {
                    alert('Por favor ingrese el monto en efectivo');
                    document.getElementById('montoEfectivo').focus();
                    return;
                }
            }
            
            const pagado = efectivo + qr;
            const cambio = pagado - total;
            
            if (pagado < total) {
                alert(`El monto pagado (Bs. ${pagado.toFixed(2)}) es menor al total del pedido (Bs. ${total.toFixed(2)})`);
                return;
            }
            
            if (cambio < 0) {
                alert(`Falta por pagar: Bs. ${Math.abs(cambio).toFixed(2)}`);
                return;
            }
            
            // Confirmar pago
            const formData = new FormData();
            formData.append('pedido_id', pedidoActual.id);
            formData.append('efectivo', efectivo);
            formData.append('qr', qr);
            formData.append('cambio', cambio);
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
                    cerrarModalPago();
                    // Recargar pedidos si estamos en esa sección
                    if (document.getElementById('section-pedidos').classList.contains('active')) {
                        cargarPedidos();
                    }
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

        // FUNCIONES DE MESAS MEJORADAS
        function cargarMesas() {
            // Mostrar indicador de carga
            document.getElementById('mesasGrid').innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <div class="spinner-reporte" style="margin: 0 auto 15px;"></div>
                    <p style="color: #667eea;">Cargando mesas...</p>
                </div>
            `;
            
            fetch('actualizar_mesas.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMesas(data.mesas);
                        actualizarContadores(data.mesas);
                        ultimaActualizacion = new Date();
                        document.getElementById('ultimaActualizacion').textContent = 
                            ultimaActualizacion.toLocaleTimeString();
                    } else {
                        mostrarErrorMesas('Error al cargar mesas: ' + data.message);
                    }
                })
                .catch(error => {
                    mostrarErrorMesas('Error de conexión: ' + error);
                });
        }
        
        function mostrarMesas(mesas) {
            const grid = document.getElementById('mesasGrid');
            const vacio = document.getElementById('mesasVacio');
            
            if (mesas.length === 0) {
                grid.style.display = 'none';
                vacio.style.display = 'block';
                return;
            }
            
            grid.style.display = 'grid';
            vacio.style.display = 'none';
            
            let html = '';
            mesas.forEach(mesa => {
                const claseEstado = `mesa-${mesa.estado}`;
                const textoEstado = mesa.estado.charAt(0).toUpperCase() + mesa.estado.slice(1);
                
                html += `
                    <div class="mesa-card ${claseEstado}">
                        <div class="mesa-header">
                            <div class="mesa-numero">${mesa.numero}</div>
                            <div class="mesa-estado estado-${mesa.estado}">
                                ${textoEstado}
                            </div>
                        </div>
                        
                        <div class="mesa-info">
                            <div class="mesa-capacidad">
                                <span>👥</span>
                                <span>${mesa.capacidad} personas</span>
                            </div>
                        </div>
                        
                        <div class="mesa-actions">
                            <button class="btn-mesa btn-mesa-editar" 
                                    onclick="editarMesa(${mesa.id}, '${mesa.numero}', ${mesa.capacidad}, '${mesa.estado}')">
                                Editar
                            </button>
                            <button class="btn-mesa btn-mesa-eliminar" 
                                    onclick="eliminarMesa(${mesa.id})">
                                Eliminar
                            </button>
                        </div>
                    </div>
                `;
            });
            
            grid.innerHTML = html;
        }
        
        function actualizarContadores(mesas) {
            const contadores = {
                libre: 0,
                ocupada: 0,
                reservada: 0
            };
            
            mesas.forEach(mesa => {
                contadores[mesa.estado]++;
            });
            
            document.getElementById('contadorLibre').textContent = contadores.libre;
            document.getElementById('contadorOcupada').textContent = contadores.ocupada;
            document.getElementById('contadorReservada').textContent = contadores.reservada;
            document.getElementById('contadorTotal').textContent = mesas.length;
        }
        
        function mostrarErrorMesas(mensaje) {
            const grid = document.getElementById('mesasGrid');
            grid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #e74c3c;">
                    <div style="font-size: 4rem; margin-bottom: 15px;">❌</div>
                    <h4>Error al cargar mesas</h4>
                    <p>${mensaje}</p>
                    <button class="btn-abrir-caja" onclick="cargarMesas()" 
                            style="width: auto; padding: 10px 20px; font-size: 0.9rem; margin-top: 15px;">
                        🔄 Reintentar
                    </button>
                </div>
            `;
        }
        
        function abrirModalMesa() {
            document.getElementById('modalMesaTitulo').textContent = '➕ Agregar Nueva Mesa';
            document.getElementById('mesaId').value = '';
            document.getElementById('mesaNumero').value = '';
            document.getElementById('mesaCapacidad').value = '4';
            document.getElementById('mesaEstadoContainer').style.display = 'none';
            document.getElementById('modalMesa').classList.add('show');
            
            // Enfocar el primer campo
            setTimeout(() => {
                document.getElementById('mesaNumero').focus();
            }, 300);
        }
        
        function editarMesa(id, numero, capacidad, estado) {
        console.log('Editando mesa:', id, numero, capacidad, estado); // Para debug
        
        document.getElementById('modalMesaTitulo').textContent = '✏️ Editar Mesa';
        document.getElementById('mesaId').value = id;
        document.getElementById('mesaNumero').value = numero;
        document.getElementById('mesaCapacidad').value = capacidad;
        document.getElementById('mesaEstado').value = estado;
        document.getElementById('mesaEstadoContainer').style.display = 'block';
        document.getElementById('modalMesa').classList.add('show');
        }
        
        function cerrarModalMesa() {
            document.getElementById('modalMesa').classList.remove('show');
        }
        
        function guardarMesa() {
            const id = document.getElementById('mesaId').value;
            const numero = document.getElementById('mesaNumero').value.trim();
            const capacidad = document.getElementById('mesaCapacidad').value;
            const estado = document.getElementById('mesaEstado').value || 'libre';
            
            if (!numero) {
                alert('Por favor ingrese el número de mesa');
                document.getElementById('mesaNumero').focus();
                return;
            }
            
            if (!capacidad || capacidad < 1) {
                alert('La capacidad debe ser al menos 1 persona');
                document.getElementById('mesaCapacidad').focus();
                return;
            }
            
            const action = id ? 'editar' : 'crear';
            const formData = new FormData();
            formData.append('action', action);
            formData.append('numero', numero);
            formData.append('capacidad', capacidad);
            
            if (action === 'editar') {
                formData.append('id', id);
                formData.append('estado', estado);
            }
            
            // Mostrar loading
            const btnGuardar = document.querySelector('.btn-confirmar');
            const textoOriginal = btnGuardar.textContent;
            btnGuardar.textContent = 'Guardando...';
            btnGuardar.disabled = true;
            
            fetch('guardar_mesa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion('✅ ' + data.message, 'success');
                    cerrarModalMesa();
                    cargarMesas();
                } else {
                    mostrarNotificacion('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                mostrarNotificacion('❌ Error de conexión: ' + error, 'error');
            })
            .finally(() => {
                btnGuardar.textContent = textoOriginal;
                btnGuardar.disabled = false;
            });
        }
        
        function eliminarMesa(id) {
            if (!confirm('¿Está seguro de eliminar esta mesa?\n\nEsta acción no se puede deshacer.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            
            fetch('guardar_mesa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion('✅ ' + data.message, 'success');
                    cargarMesas();
                } else {
                    mostrarNotificacion('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                mostrarNotificacion('❌ Error de conexión: ' + error, 'error');
            });
        }
        
        // ACTUALIZACIÓN AUTOMÁTICA MEJORADA
        function iniciarActualizacionMesas() {
            // Cargar mesas inmediatamente
            cargarMesas();
            
            // Actualizar cada 20 segundos
            intervaloMesas = setInterval(cargarMesas, 20000);
        }
        
        function detenerActualizacionMesas() {
            if (intervaloMesas) {
                clearInterval(intervaloMesas);
                intervaloMesas = null;
            }
        }
        
        // Función auxiliar para notificaciones
        function mostrarNotificacion(mensaje, tipo) {
            // Puedes implementar un sistema de notificaciones más elegante aquí
            alert(mensaje);
        }
                // Debug para mesas
        console.log('Funciones de mesas cargadas correctamente');
        console.log('editarMesa function:', typeof editarMesa);
    </script>
</body>
</html>