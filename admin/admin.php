<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Obtener todos los usuarios
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY fecha_creacion DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles_nombres = [
    'admin' => 'Administrador',
    'cajero' => 'Cajero',
    'garzon' => 'Garzón'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Safari</title>
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
            text-decoration: none;
            color: white;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #667eea;
            color: white;
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
        
        /* CARDS Y CONTENEDORES */
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        
        .card-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* TABLA DE USUARIOS */
        .usuarios-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        
        .usuarios-table thead {
            background: #f8f9fa;
        }
        
        .usuarios-table thead th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #2c3e50;
            border: none;
        }
        
        .usuarios-table tbody tr {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .usuarios-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .usuarios-table tbody td {
            padding: 15px;
            border: none;
        }
        
        .usuarios-table tbody tr td:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .usuarios-table tbody tr td:last-child {
            border-radius: 0 10px 10px 0;
        }
        
        .badge-rol {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .badge-cajero {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .badge-garzon {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }
        
        .badge-estado {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .badge-activo {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactivo {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            margin: 0 3px;
            font-size: 0.85rem;
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
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-modal {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-confirmar {
            background: #27ae60;
            color: white;
        }
        
        .btn-confirmar:hover {
            background: #229954;
        }
        
        .btn-cancelar {
            background: #95a5a6;
            color: white;
        }
        
        .btn-cancelar:hover {
            background: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                z-index: 100;
                height: 100%;
            }
            
            .sidebar.collapsed {
                width: 0;
                overflow: hidden;
            }
            
            .usuarios-table {
                font-size: 0.85rem;
            }
            
            .btn-action {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
        }
        /* ESTILOS PARA REPORTES (COPIADOS DE CAJERO) */
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

.caja-info {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
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

.resumen-detalle {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-top: 5px;
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

/* Mejoras para la tabla */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
}

thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

tfoot {
    background: #2c3e50;
    color: white;
    font-weight: bold;
}
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>🦁 Safari - Administración</h1>
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
                <a href="#" class="menu-item active" onclick="cambiarSeccion('usuarios'); return false;">
                    <span class="menu-icon">👥</span>
                    <span class="menu-text">Usuarios</span>
                </a>
                <a href="#" class="menu-item" onclick="cambiarSeccion('reportes'); return false;">
                    <span class="menu-icon">📊</span>
                    <span class="menu-text">Reportes</span>
                </a>
                <a href="#" class="menu-item" onclick="cambiarSeccion('menu'); return false;">
                    <span class="menu-icon">🍽️</span>
                    <span class="menu-text">Menú</span>
                </a>
                <a href="#" class="menu-item" onclick="cambiarSeccion('mesas'); return false;">
                    <span class="menu-icon">🪑</span>
                    <span class="menu-text">Mesas</span>
                </a>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content-area">
            <!-- SECCIÓN USUARIOS -->
            <div class="section-content active" id="section-usuarios">
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">👥 Gestión de Usuarios</h2>
                        <button class="btn-primary-custom" onclick="abrirModalNuevoUsuario()">
                            ➕ NUEVO USUARIO
                        </button>
                    </div>

                    <?php if (count($usuarios) > 0): ?>
                        <table class="usuarios-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                        <td>
                                            <span class="badge-rol badge-<?php echo $usuario['rol']; ?>">
                                                <?php echo $roles_nombres[$usuario['rol']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-estado badge-<?php echo $usuario['activo'] ? 'activo' : 'inactivo'; ?>">
                                                <?php echo $usuario['activo'] ? '✓ Activo' : '✗ Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></td>
                                        <td>
                                            <button class="btn-action btn-editar" 
                                                    onclick='editarUsuario(<?php echo json_encode($usuario); ?>)'>
                                                ✏️ Editar
                                            </button>
                                            <button class="btn-action btn-eliminar" 
                                                    onclick="eliminarUsuario(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')">
                                                🗑️ Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <h3>No hay usuarios registrados</h3>
                            <p>Comienza agregando un nuevo usuario</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
             <!-- SECCIÓN REPORTES -->
<div class="section-content" id="section-reportes">
    <h2 style="margin-bottom: 20px; color: #2c3e50;">📊 Reportes de Ventas</h2>
    
    <!-- FILTROS MEJORADOS -->
    <div class="caja-info" style="margin-bottom: 20px;">
        <div class="form-header" style="margin-bottom: 20px;">
            <h4>🔍 Filtros Avanzados del Reporte</h4>
            <p style="color: #7f8c8d; margin: 0;">Seleccione el período y criterios para el reporte</p>
        </div>
        
        <!-- SELECTOR DE PERÍODO -->
        <div style="margin-bottom: 20px;">
            <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">
                📅 Período del Reporte
            </label>
            <select class="cantidad-input" id="filtroPeriodo" onchange="cambiarPeriodo()">
                <option value="dia">📊 Por Día (Específico)</option>
                <option value="semana">📅 Por Semana (Completa)</option>
                <option value="mes">🗓️ Por Mes (Completo)</option>
                <option value="rango">📆 Por Rango Personalizado</option>
            </select>
        </div>

        <!-- FILTROS DINÁMICOS SEGÚN PERIODO -->
        <div id="filtrosDinamicos">
            <!-- POR DÍA -->
            <div class="row" id="filtroDia">
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">📅 Fecha Específica</label>
                    <input type="date" class="cantidad-input" id="filtroFecha" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">🕐 Turno</label>
                    <select class="cantidad-input" id="filtroTurno">
                        <option value="todos">Todos los turnos</option>
                        <option value="mañana">🌅 Turno Mañana</option>
                        <option value="noche">🌙 Turno Noche</option>
                    </select>
                </div>
            </div>

            <!-- POR SEMANA -->
            <div class="row" id="filtroSemana" style="display: none;">
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">📅 Semana del</label>
                    <input type="date" class="cantidad-input" id="filtroFechaSemana" value="<?php echo date('Y-m-d'); ?>">
                    <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                        Se mostrará la semana completa (lunes a domingo)
                    </small>
                </div>
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">🕐 Filtro por Turno</label>
                    <select class="cantidad-input" id="filtroTurnoSemana">
                        <option value="todos">Todos los turnos</option>
                        <option value="mañana">Solo turno mañana</option>
                        <option value="noche">Solo turno noche</option>
                    </select>
                </div>
            </div>

            <!-- POR MES -->
            <div class="row" id="filtroMes" style="display: none;">
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">🗓️ Mes</label>
                    <input type="month" class="cantidad-input" id="filtroMesSeleccionado" value="<?php echo date('Y-m'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">🕐 Filtro por Turno</label>
                    <select class="cantidad-input" id="filtroTurnoMes">
                        <option value="todos">Todos los turnos</option>
                        <option value="mañana">Solo turno mañana</option>
                        <option value="noche">Solo turno noche</option>
                    </select>
                </div>
            </div>

            <!-- POR RANGO -->
            <div class="row" id="filtroRango" style="display: none;">
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">📅 Fecha Inicio</label>
                    <input type="date" class="cantidad-input" id="filtroFechaInicio" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">📅 Fecha Fin</label>
                    <input type="date" class="cantidad-input" id="filtroFechaFin" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">🕐 Filtro por Turno</label>
                    <select class="cantidad-input" id="filtroTurnoRango">
                        <option value="todos">Todos los turnos</option>
                        <option value="mañana">Solo turno mañana</option>
                        <option value="noche">Solo turno noche</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- FILTRO CAJERO (SIEMPRE VISIBLE) -->
        <div class="row">
            <div class="col-md-6 mb-3">
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
            <div class="col-md-6 mb-3" style="display: flex; align-items: end;">
                <button type="button" class="btn-abrir-caja" onclick="generarReporte()" style="width: 100%;">
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

    <!-- PLANTILLA PARA REPORTE DETALLADO (oculta inicialmente) -->
    <div id="plantillaReporteDetallado" style="display: none;">
        <div class="caja-info">
            <h3 style="color: #2c3e50; margin-bottom: 20px; text-align: center; text-transform: uppercase;">
                📈 Reporte Detallado de Ventas - <span class="fecha-reporte"></span>
            </h3>
            
            <!-- RESUMEN GENERAL -->
            <div class="reporte-resumen">
                <h4 style="margin-bottom: 20px; text-align: center;">📊 Resumen General</h4>
                <div class="row text-center">
                    <div class="col-md-3 resumen-item">
                        <div class="resumen-label">TOTAL EFECTIVO</div>
                        <div class="resumen-valor" id="resumenEfectivo">Bs. 0.00</div>
                        <div class="resumen-detalle" id="detalleEfectivo">0 ventas</div>
                    </div>
                    <div class="col-md-3 resumen-item">
                        <div class="resumen-label">TOTAL QR</div>
                        <div class="resumen-valor" id="resumenQR">Bs. 0.00</div>
                        <div class="resumen-detalle" id="detalleQR">0 ventas</div>
                    </div>
                    <div class="col-md-3 resumen-item">
                        <div class="resumen-label">TOTAL MIXTO</div>
                        <div class="resumen-valor" id="resumenMixto">Bs. 0.00</div>
                        <div class="resumen-detalle" id="detalleMixto">0 ventas</div>
                    </div>
                    <div class="col-md-3 resumen-item">
                        <div class="resumen-label">TOTAL RECAUDADO</div>
                        <div class="resumen-valor" id="resumenTotal">Bs. 0.00</div>
                        <div class="resumen-detalle" id="detalleTotal">0 ventas totales</div>
                    </div>
                </div>
            </div>

            <!-- TABLA DETALLADA -->
            <div style="margin-top: 30px;">
                <h4 style="color: #2c3e50; margin-bottom: 15px;">📋 Detalle por Método de Pago</h4>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <th style="padding: 15px; text-align: left; font-weight: bold;">Método de Pago</th>
                            <th style="padding: 15px; text-align: center; font-weight: bold;">Cantidad de Ventas</th>
                            <th style="padding: 15px; text-align: right; font-weight: bold;">Monto Total</th>
                            <th style="padding: 15px; text-align: right; font-weight: bold;">Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody id="tablaMetodosPago">
                        <!-- Los datos se llenarán dinámicamente -->
                    </tbody>
                    <tfoot>
                        <tr style="background: #2c3e50; color: white; font-weight: bold;">
                            <td style="padding: 15px; text-align: left;">TOTAL GENERAL</td>
                            <td style="padding: 15px; text-align: center;" id="totalVentas">0</td>
                            <td style="padding: 15px; text-align: right;" id="totalMonto">Bs. 0.00</td>
                            <td style="padding: 15px; text-align: right;">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- DETALLE POR TURNO Y CAJERO -->
            <div id="detalleTurnos" style="margin-top: 30px;">
                <!-- Se llenará dinámicamente -->
            </div>

            <!-- BOTONES DE ACCIÓN -->
            <div style="text-align: center; margin-top: 30px; padding: 20px;">
                <button onclick="exportarExcel()" class="btn-abrir-caja" style="width: auto; padding: 12px 25px; font-size: 1rem; background: #27ae60;">
                    📊 Exportar a Excel
                </button>
            </div>
        </div>
    </div>
</div>
            <!--Fin de SECCIÓN REPORTES -->
            <!-- SECCIÓN MENÚ -->
            <div class="section-content" id="section-menu">
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">🍽️ Gestión de Menú</h2>
                    </div>
                    <div class="empty-state">
                        <div class="empty-icon">🍽️</div>
                        <h3>Sección de Menú</h3>
                        <p>Esta sección estará disponible próximamente</p>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN MESAS -->
            <div class="section-content" id="section-mesas">
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">🪑 Gestión de Mesas</h2>
                    </div>
                    <div class="empty-state">
                        <div class="empty-icon">🪑</div>
                        <h3>Sección de Mesas</h3>
                        <p>Esta sección estará disponible próximamente</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CREAR/EDITAR USUARIO -->
    <div class="modal-overlay" id="modalUsuario">
        <div class="modal-content">
            <div class="modal-header" id="modalTitulo">Nuevo Usuario</div>
            
            <form id="formUsuario">
                <input type="hidden" id="usuarioId" name="id">
                
                <div class="form-group">
                    <label>👤 Nombre Completo</label>
                    <input type="text" class="form-control" id="usuarioNombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label>🔐 Usuario (Login)</label>
                    <input type="text" class="form-control" id="usuarioLogin" name="usuario" required>
                </div>
                
                <div class="form-group">
                    <label>🔑 Contraseña</label>
                    <input type="password" class="form-control" id="usuarioPassword" name="password">
                    <small style="color: #7f8c8d; font-size: 0.85rem;" id="passwordHelp">
                        Dejar en blanco para mantener la contraseña actual
                    </small>
                </div>
                
                <div class="form-group">
                    <label>👔 Rol</label>
                    <select class="form-control" id="usuarioRol" name="rol" required>
                        <option value="">Seleccionar rol...</option>
                        <option value="admin">Administrador</option>
                        <option value="cajero">Cajero</option>
                        <option value="garzon">Garzón</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>✅ Estado</label>
                    <select class="form-control" id="usuarioActivo" name="activo" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-confirmar">💾 GUARDAR</button>
                    <button type="button" class="btn-modal btn-cancelar" onclick="cerrarModalUsuario()">❌ CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // NAVEGACIÓN
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }
        
        function cambiarSeccion(seccion) {
            document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            
            document.getElementById('section-' + seccion).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // MODALES
        function abrirModalNuevoUsuario() {
            document.getElementById('modalTitulo').textContent = '➕ Nuevo Usuario';
            document.getElementById('formUsuario').reset();
            document.getElementById('usuarioId').value = '';
            document.getElementById('passwordHelp').style.display = 'none';
            document.getElementById('usuarioPassword').required = true;
            document.getElementById('modalUsuario').classList.add('show');
        }
        
        function editarUsuario(usuario) {
            document.getElementById('modalTitulo').textContent = '✏️ Editar Usuario';
            document.getElementById('usuarioId').value = usuario.id;
            document.getElementById('usuarioNombre').value = usuario.nombre;
            document.getElementById('usuarioLogin').value = usuario.usuario;
            document.getElementById('usuarioPassword').value = '';
            document.getElementById('usuarioPassword').required = false;
            document.getElementById('usuarioRol').value = usuario.rol;
            document.getElementById('usuarioActivo').value = usuario.activo;
            document.getElementById('passwordHelp').style.display = 'block';
            document.getElementById('modalUsuario').classList.add('show');
        }
        
        function cerrarModalUsuario() {
            document.getElementById('modalUsuario').classList.remove('show');
        }
        
        // FORMULARIO
        document.getElementById('formUsuario').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const usuarioId = document.getElementById('usuarioId').value;
            const action = usuarioId ? 'editar' : 'crear';
            
            formData.append('action', action);
            
            fetch('gestionar_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        });
        
        // ELIMINAR
        function eliminarUsuario(id, nombre) {
            if (!confirm(`¿Está seguro de eliminar al usuario "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            
            fetch('gestionar_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        }
        // FUNCIONES PARA REPORTES
function cambiarPeriodo() {
    const periodo = document.getElementById('filtroPeriodo').value;
    
    // Ocultar todos los filtros
    document.getElementById('filtroDia').style.display = 'none';
    document.getElementById('filtroSemana').style.display = 'none';
    document.getElementById('filtroMes').style.display = 'none';
    document.getElementById('filtroRango').style.display = 'none';
    
    // Mostrar solo el filtro seleccionado
    document.getElementById('filtro' + periodo.charAt(0).toUpperCase() + periodo.slice(1)).style.display = 'block';
}

// FUNCIÓN GENERAR REPORTE MEJORADA
function generarReporte() {
    const periodo = document.getElementById('filtroPeriodo').value;
    const cajero = document.getElementById('filtroCajero').value;
    
    let fecha, turno, fechaInicio, fechaFin;
    
    // Obtener parámetros según el período seleccionado
    switch(periodo) {
        case 'dia':
            fecha = document.getElementById('filtroFecha').value;
            turno = document.getElementById('filtroTurno').value;
            break;
        case 'semana':
            fecha = document.getElementById('filtroFechaSemana').value;
            turno = document.getElementById('filtroTurnoSemana').value;
            break;
        case 'mes':
            fecha = document.getElementById('filtroMesSeleccionado').value;
            turno = document.getElementById('filtroTurnoMes').value;
            break;
        case 'rango':
            fechaInicio = document.getElementById('filtroFechaInicio').value;
            fechaFin = document.getElementById('filtroFechaFin').value;
            turno = document.getElementById('filtroTurnoRango').value;
            break;
    }
    
    if ((periodo === 'rango' && (!fechaInicio || !fechaFin)) || 
        (periodo !== 'rango' && !fecha)) {
        alert('Por favor complete todos los campos requeridos');
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
    formData.append('periodo', periodo);
    formData.append('cajero_id', cajero);
    formData.append('turno', turno);
    
    if (periodo === 'rango') {
        formData.append('fecha_inicio', fechaInicio);
        formData.append('fecha_fin', fechaFin);
    } else {
        formData.append('fecha', fecha);
    }
    
    fetch('generar_reporte.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarResultadoReporte(data);
        } else {
            mostrarErrorReporte('Error: ' + data.message);
        }
    })
    .catch(error => {
        mostrarErrorReporte('Error de conexión: ' + error);
    });
}

// FUNCIÓN PARA MOSTRAR RESULTADO DEL REPORTE (COMPLETA)
function mostrarResultadoReporte(data) {
    const plantilla = document.getElementById('plantillaReporteDetallado').innerHTML;
    const fechaReporte = obtenerTextoFecha(data.fecha_inicio, data.fecha_fin, data.periodo);
    
    let html = plantilla;
    document.getElementById('resultadoReporte').innerHTML = html;
    
    // Actualizar fecha del reporte
    document.querySelector('.fecha-reporte').textContent = fechaReporte;
    
    // Actualizar resumen general
    document.getElementById('resumenEfectivo').textContent = `Bs. ${formatearNumero(data.totales.efectivo)}`;
    document.getElementById('resumenQR').textContent = `Bs. ${formatearNumero(data.totales.qr)}`;
    document.getElementById('resumenMixto').textContent = `Bs. ${formatearNumero(data.totales.mixto)}`;
    document.getElementById('resumenTotal').textContent = `Bs. ${formatearNumero(data.totales.general)}`;
    
    document.getElementById('detalleEfectivo').textContent = `${data.cantidades.efectivo} ventas`;
    document.getElementById('detalleQR').textContent = `${data.cantidades.qr} ventas`;
    document.getElementById('detalleMixto').textContent = `${data.cantidades.mixto} ventas`;
    document.getElementById('detalleTotal').textContent = `${data.cantidades.general} ventas totales`;
    
    // Actualizar tabla de métodos de pago
    actualizarTablaMetodosPago(data.totales, data.cantidades);
    
    // Actualizar detalle por turnos y cajeros
    actualizarDetalleTurnos(data.detalle_turnos);
}

function obtenerTextoFecha(fechaInicio, fechaFin, periodo) {
    const fechaInicioObj = new Date(fechaInicio);
    const fechaFinObj = new Date(fechaFin);
    
    switch(periodo) {
        case 'dia':
            return fechaInicioObj.toLocaleDateString('es-ES', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        case 'semana':
            return `Semana del ${fechaInicioObj.toLocaleDateString('es-ES')} al ${fechaFinObj.toLocaleDateString('es-ES')}`;
        case 'mes':
            return fechaInicioObj.toLocaleDateString('es-ES', { 
                year: 'numeric', 
                month: 'long' 
            });
        case 'rango':
            return `Del ${fechaInicioObj.toLocaleDateString('es-ES')} al ${fechaFinObj.toLocaleDateString('es-ES')}`;
        default:
            return fechaInicioObj.toLocaleDateString('es-ES');
    }
}

function formatearNumero(numero) {
    return parseFloat(numero).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function actualizarTablaMetodosPago(totales, cantidades) {
    const totalGeneral = totales.general;
    const tablaBody = document.getElementById('tablaMetodosPago');
    
    const metodos = [
        { nombre: '💰 Efectivo', monto: totales.efectivo, cantidad: cantidades.efectivo },
        { nombre: '📱 QR/Pago Digital', monto: totales.qr, cantidad: cantidades.qr },
        { nombre: '💳 Pago Mixto', monto: totales.mixto, cantidad: cantidades.mixto }
    ];
    
    let html = '';
    metodos.forEach(metodo => {
        const porcentaje = totalGeneral > 0 ? ((metodo.monto / totalGeneral) * 100).toFixed(1) : 0;
        html += `
            <tr>
                <td style="padding: 12px 15px;">${metodo.nombre}</td>
                <td style="padding: 12px 15px; text-align: center;">${metodo.cantidad}</td>
                <td style="padding: 12px 15px; text-align: right;">Bs. ${formatearNumero(metodo.monto)}</td>
                <td style="padding: 12px 15px; text-align: right;">${porcentaje}%</td>
            </tr>
        `;
    });
    
    tablaBody.innerHTML = html;
    document.getElementById('totalVentas').textContent = cantidades.general;
    document.getElementById('totalMonto').textContent = `Bs. ${formatearNumero(totalGeneral)}`;
}

function actualizarDetalleTurnos(detalleTurnos) {
    const contenedor = document.getElementById('detalleTurnos');
    
    if (!detalleTurnos || detalleTurnos.length === 0) {
        contenedor.innerHTML = `
            <div style="text-align: center; padding: 30px; background: #f8f9fa; border-radius: 10px;">
                <p style="color: #7f8c8d; font-size: 1.1rem;">No hay datos detallados por turno para este período</p>
            </div>
        `;
        return;
    }
    
    let html = '<h4 style="color: #2c3e50; margin-bottom: 20px;">🕐 Detalle por Turnos y Cajeros</h4>';
    
    detalleTurnos.forEach(turno => {
        html += `
            <div class="reporte-turno">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h5 style="margin: 0; color: #2c3e50;">
                        ${turno.turno === 'mañana' ? '🌅 Turno Mañana' : '🌙 Turno Noche'} 
                        - ${turno.fecha}
                    </h5>
                    <span style="font-weight: bold; color: #27ae60;">Bs. ${formatearNumero(turno.total)}</span>
                </div>
        `;
        
        if (turno.cajeros && turno.cajeros.length > 0) {
            turno.cajeros.forEach(cajero => {
                html += `
                    <div class="reporte-cajero">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>👤 ${cajero.nombre}</strong>
                                <small style="color: #7f8c8d; margin-left: 10px;">
                                    ${cajero.total_ventas} ventas
                                </small>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: bold; color: #2c3e50;">
                                    Bs. ${formatearNumero(cajero.total)}
                                </div>
                                <small style="color: #95a5a6;">
                                    Efectivo: Bs. ${formatearNumero(cajero.efectivo)} | 
                                    QR: Bs. ${formatearNumero(cajero.qr)} |
                                    Mixto: Bs. ${formatearNumero(cajero.mixto)}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            html += `
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <p style="color: #7f8c8d; margin: 0;">No hay datos de cajeros para este turno</p>
                </div>
            `;
        }
        
        html += `</div>`;
    });
    
    contenedor.innerHTML = html;
}

function mostrarErrorReporte(mensaje) {
    document.getElementById('resultadoReporte').innerHTML = `
        <div class="caja-status" style="border-color: #e74c3c;">
            <div class="status-icon">❌</div>
            <div class="status-text" style="color: #e74c3c;">ERROR</div>
            <p style="color: #7f8c8d; font-size: 1.1rem;">${mensaje}</p>
        </div>
    `;
}

// FUNCIÓN EXPORTAR EXCEL COMPLETA
function exportarExcel() {
    const periodo = document.getElementById('filtroPeriodo').value;
    const cajero = document.getElementById('filtroCajero').value;
    const turno = obtenerTurnoSeleccionado(periodo);
    const fecha = obtenerFechaSeleccionada(periodo);
    const fechaInicio = periodo === 'rango' ? document.getElementById('filtroFechaInicio').value : '';
    const fechaFin = periodo === 'rango' ? document.getElementById('filtroFechaFin').value : '';
    
    let url = `generar_reporte.php?exportar=excel&periodo=${periodo}&cajero_id=${cajero}&turno=${turno}`;
    
    if (periodo === 'rango') {
        url += `&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
    } else {
        url += `&fecha=${fecha}`;
    }
    
    window.open(url, '_blank');
}

function obtenerTurnoSeleccionado(periodo) {
    switch(periodo) {
        case 'dia': return document.getElementById('filtroTurno').value;
        case 'semana': return document.getElementById('filtroTurnoSemana').value;
        case 'mes': return document.getElementById('filtroTurnoMes').value;
        case 'rango': return document.getElementById('filtroTurnoRango').value;
        default: return 'todos';
    }
}

function obtenerFechaSeleccionada(periodo) {
    switch(periodo) {
        case 'dia': return document.getElementById('filtroFecha').value;
        case 'semana': return document.getElementById('filtroFechaSemana').value;
        case 'mes': return document.getElementById('filtroMesSeleccionado').value;
        default: return '';
    }
}

// Inicializar filtros cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    cambiarPeriodo(); // Mostrar filtros iniciales
});
        // Fin funciones para reportes
    </script>
</body>
</html>