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
            background: linear-gradient(#333333 100%);
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
            background: linear-gradient(to bottom, #424242, #686868);
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

/* ESTILOS PARA PRODUCTOS */
.badge-plato_principal {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.badge-acompanamiento {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
}

.badge-bebida {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
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
/* ESTILOS PARA EL BUSCADOR */
.buscador-container {
    position: relative;
    margin-bottom: 20px;
}

.buscador-input {
    width: 100%;
    padding: 12px 45px 12px 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s;
    background: white;
}

.buscador-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.buscador-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1.2rem;
}

.contador-resultados {
    text-align: right;
    margin-top: 8px;
    color: #7f8c8d;
    font-size: 0.9rem;
    font-weight: 500;
}
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>Administración</h1>
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
                <a href="#" class="menu-item" onclick="cambiarSeccion('productos'); return false;">
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
                                <input type="date" class="cantidad-input" id="filtroFecha" value="2025-10-21">
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
                                <input type="date" class="cantidad-input" id="filtroFechaSemana" value="2025-10-21">
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
                                <input type="month" class="cantidad-input" id="filtroMesSeleccionado" value="2025-10">
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
                                <input type="date" class="cantidad-input" id="filtroFechaInicio" value="2025-10-20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">📅 Fecha Fin</label>
                                <input type="date" class="cantidad-input" id="filtroFechaFin" value="2025-10-21">
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

            <!-- SECCIÓN PRODUCTOS -->
             <!-- SECCIÓN PRODUCTOS -->
<div class="section-content" id="section-productos">
    <div class="content-card">
        <div class="card-header">
            <h2 class="card-title">📦 Gestión de Productos</h2>
            <button class="btn-primary-custom" onclick="abrirModalNuevoProducto()">
                ➕ NUEVO PRODUCTO
            </button>
        </div>
        
        <!-- AGREGAR ESTE CÓDIGO - BUSCADOR -->
        <div class="caja-info" style="margin-bottom: 20px;">
            <div class="form-group">
                <label style="font-weight: bold; margin-bottom: 8px; display: block; color: #2c3e50;">
                    🔍 Buscar Producto
                </label>
                <input type="text" 
                       class="form-control" 
                       id="buscadorProductos" 
                       placeholder="Escribe para buscar por nombre, categoría o precio..."
                       onkeyup="filtrarProductos()"
                       style="padding: 12px; font-size: 1rem;">
                <small style="color: #7f8c8d; font-size: 0.85rem;">
                    Busca por nombre, categoría o precio. Los resultados se filtran automáticamente.
                </small>
            </div>
        </div>
        
        <div id="tabla-productos-container">
            <!-- La tabla se cargará dinámicamente -->
            <div style="text-align: center; padding: 40px;">
                <div class="spinner-reporte"></div>
                <p>Cargando productos...</p>
            </div>
        </div>
    </div>
</div>

          <!-- SECCIÓN MESAS -->
<div class="section-content" id="section-mesas">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 10px;">
                🍽️ Gestión de Mesas
            </h2>
            <p style="color: #7f8c8d; margin: 5px 0 0 0; font-size: 0.9rem;">
                Administra las mesas del restaurante
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
    </div>
</div>
 <!-- Fin seccio mesas -->
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

    <!-- MODAL CREAR/EDITAR PRODUCTO -->
    <div class="modal-overlay" id="modalProducto">
        <div class="modal-content">
            <div class="modal-header" id="modalTituloProducto">Nuevo Producto</div>
            
            <form id="formProducto">
                <input type="hidden" id="productoId" name="id">
                
                <div class="form-group">
                    <label>📦 Nombre del Producto</label>
                    <input type="text" class="form-control" id="productoNombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label>💰 Precio (Bs.)</label>
                    <input type="number" class="form-control" id="productoPrecio" name="precio" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>📁 Categoría</label>
                    <select class="form-control" id="productoCategoria" name="categoria" required>
                        <option value="">Seleccionar categoría...</option>
                        <option value="plato_principal">🍽️ Plato Principal</option>
                        <option value="acompanamiento">🥗 Acompañamiento</option>
                        <option value="bebida">🥤 Bebida</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>✅ Estado</label>
                    <select class="form-control" id="productoActivo" name="activo" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-confirmar">💾 GUARDAR</button>
                    <button type="button" class="btn-modal btn-cancelar" onclick="cerrarModalProducto()">❌ CANCELAR</button>
                </div>
            </form>
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
        // NAVEGACIÓN
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }
        
      function cambiarSeccion(seccion) {
    document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
    
    document.getElementById('section-' + seccion).classList.add('active');
    event.currentTarget.classList.add('active');
    
    // Detener actualizaciones anteriores
    detenerActualizacionMesas();
    
    // Cargar productos cuando se active la sección
    if (seccion === 'productos') {
        cargarProductos();
    }
    
    // Iniciar actualización de mesas cuando se active la sección
    if (seccion === 'mesas') {
        iniciarActualizacionMesas();
    }
}
        // VARIABLES PARA MESAS
let intervaloMesas = null;
let ultimaActualizacionMesas = null;
        // MODALES USUARIOS
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
        
        // FORMULARIO USUARIOS
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
        
        // ELIMINAR USUARIO
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
            // funcion cargar productos
        function cargarProductos() {
    fetch('productos.php?action=obtener')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarProductos(data.productos);
            } else {
                mostrarErrorProductos('Error al cargar productos');
            }
        })
        .catch(error => {
            mostrarErrorProductos('Error de conexión: ' + error);
        });
}
function mostrarProductos(productos) {
    const container = document.getElementById('tabla-productos-container');
    
    // Guardar los productos originales para el filtrado
    window.productosOriginales = productos;
    
    if (productos.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3>No hay productos registrados</h3>
                <p>Comienza agregando un nuevo producto</p>
            </div>
        `;
        return;
    }
    
    // Mostrar todos los productos inicialmente
    renderizarTablaProductos(productos);
    actualizarContadorResultados(productos.length, productos.length);
}

// Nueva función para renderizar la tabla
function renderizarTablaProductos(productos) {
    const container = document.getElementById('tabla-productos-container');
    
    let html = `
        <div class="contador-resultados" id="contadorResultados">
            Mostrando ${productos.length} de ${window.productosOriginales.length} productos
        </div>
        <table class="usuarios-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Fecha Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    productos.forEach(producto => {
        const categoriaNombres = {
            'plato_principal': '🍽️ Plato Principal',
            'acompanamiento': '🥗 Acompañamiento',
            'bebida': '🥤 Bebida'
        };
        
        html += `
            <tr>
                <td><strong>#${producto.id}</strong></td>
                <td>${escapeHtml(producto.nombre)}</td>
                <td><strong>Bs. ${formatearNumero(producto.precio)}</strong></td>
                <td>
                    <span class="badge-rol badge-${producto.categoria}">
                        ${categoriaNombres[producto.categoria]}
                    </span>
                </td>
                <td>
                    <span class="badge-estado badge-${producto.activo ? 'activo' : 'inactivo'}">
                        ${producto.activo ? '✓ Activo' : '✗ Inactivo'}
                    </span>
                </td>
                <td>${formatearFecha(producto.fecha_creacion)}</td>
                <td>
                    <button class="btn-action btn-editar" 
                            onclick='editarProducto(${JSON.stringify(producto)})'>
                        ✏️ Editar
                    </button>
                    <button class="btn-action btn-eliminar" 
                            onclick="eliminarProducto(${producto.id}, '${escapeHtml(producto.nombre)}')">
                        🗑️ Eliminar
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += `</tbody></table>`;
    container.innerHTML = html;
}

// Función para actualizar el contador de resultados
function actualizarContadorResultados(mostrando, total) {
    const contador = document.getElementById('contadorResultados');
    if (contador) {
        contador.textContent = `Mostrando ${mostrando} de ${total} productos`;
    }
}
        function mostrarErrorProductos(mensaje) {
            document.getElementById('tabla-productos-container').innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <h3>Error</h3>
                    <p>${mensaje}</p>
                    <button class="btn-primary-custom" onclick="cargarProductos()">
                        🔄 Reintentar
                    </button>
                </div>
            `;
        }

        function abrirModalNuevoProducto() {
            document.getElementById('modalTituloProducto').textContent = '➕ Nuevo Producto';
            document.getElementById('formProducto').reset();
            document.getElementById('productoId').value = '';
            document.getElementById('modalProducto').classList.add('show');
        }

        function editarProducto(producto) {
            document.getElementById('modalTituloProducto').textContent = '✏️ Editar Producto';
            document.getElementById('productoId').value = producto.id;
            document.getElementById('productoNombre').value = producto.nombre;
            document.getElementById('productoPrecio').value = producto.precio;
            document.getElementById('productoCategoria').value = producto.categoria;
            document.getElementById('productoActivo').value = producto.activo;
            document.getElementById('modalProducto').classList.add('show');
        }

        function cerrarModalProducto() {
            document.getElementById('modalProducto').classList.remove('show');
        }

        function eliminarProducto(id, nombre) {
            if (!confirm(`¿Está seguro de eliminar el producto "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            
            fetch('gestionar_producto.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    cargarProductos();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        }

        // Función auxiliar para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Función para formatear fecha
        function formatearFecha(fechaStr) {
            const fecha = new Date(fechaStr);
            return fecha.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Agregar evento submit al formulario de productos
        document.getElementById('formProducto').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const productoId = document.getElementById('productoId').value;
            const action = productoId ? 'editar' : 'crear';
            
            formData.append('action', action);
            
            fetch('gestionar_producto.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    cerrarModalProducto();
                    cargarProductos();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        });

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

        /// FUNCIÓN GENERAR REPORTE CORREGIDA
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
            
            // Validaciones
            if (periodo === 'rango') {
                if (!fechaInicio || !fechaFin) {
                    alert('Por favor complete ambas fechas del rango');
                    return;
                }
                if (fechaInicio > fechaFin) {
                    alert('La fecha de inicio no puede ser mayor a la fecha fin');
                    return;
                }
            } else if (!fecha) {
                alert('Por favor seleccione una fecha');
                return;
            }
            
            // Mostrar loading
            document.getElementById('resultadoReporte').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner-reporte"></div>
                    <p style="margin-top: 15px; color: #667eea; font-size: 1.1rem;">
                        Generando reporte...
                    </p>
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
            
            console.log('Enviando datos:', {
                periodo: periodo,
                fecha: fecha,
                fechaInicio: fechaInicio,
                fechaFin: fechaFin,
                cajero: cajero,
                turno: turno
            });
            
            fetch('generar_reporte.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta del servidor:', data);
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

        // FUNCIÓN PARA MOSTRAR RESULTADO DEL REPORTE - CON FECHA EXACTA
        function mostrarResultadoReporte(data) {
            if (!data.hay_datos) {
                // MOSTRAR MENSAJE DE "NO HAY DATOS" CON FECHA EXACTA
                const fechaMostrar = data.fecha_seleccionada || data.fecha_consultada;
                document.getElementById('resultadoReporte').innerHTML = `
                    <div class="caja-status" style="border-color: #f39c12;">
                        <div class="status-icon">📭</div>
                        <div class="status-text" style="color: #f39c12;">SIN DATOS</div>
                        <p style="color: #7f8c8d; font-size: 1.1rem;">${data.mensaje}</p>
                        <p style="color: #95a5a6; font-size: 0.9rem;">
                            Fecha consultada: <strong>${fechaMostrar}</strong>
                        </p>
                    </div>
                `;
                return;
            }

            // SI HAY DATOS - Mostrar reporte normal CON FECHA EXACTA
            const fechaMostrar = data.fecha_seleccionada || data.fecha_consultada;
            const plantilla = document.getElementById('plantillaReporteDetallado').innerHTML;
            
            let html = plantilla;
            document.getElementById('resultadoReporte').innerHTML = html;
            
            // Actualizar fecha del reporte CON LA FECHA EXACTA
            document.querySelector('.fecha-reporte').textContent = formatearFechaCompleta(fechaMostrar);
            
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

        // AGREGAR ESTA FUNCIÓN NUEVA PARA FORMATEAR FECHA
        // FUNCIÓN MEJORADA PARA FORMATEAR FECHA
        function formatearFechaCompleta(fechaStr) {
            // Si ya viene formateada del servidor (como "Semana del...", "Del...", o contiene mes en español), usarla directamente
            if (fechaStr.includes('Semana') || fechaStr.includes('Del') || 
                fechaStr.includes('Enero') || fechaStr.includes('Febrero') || fechaStr.includes('Marzo') ||
                fechaStr.includes('Abril') || fechaStr.includes('Mayo') || fechaStr.includes('Junio') ||
                fechaStr.includes('Julio') || fechaStr.includes('Agosto') || fechaStr.includes('Septiembre') ||
                fechaStr.includes('Octubre') || fechaStr.includes('Noviembre') || fechaStr.includes('Diciembre')) {
                return fechaStr;
            }
            
            // Si es una fecha en formato dd/mm/yyyy, formatearla
            try {
                const partes = fechaStr.split('/');
                if (partes.length === 3) {
                    const fecha = new Date(partes[2], partes[1] - 1, partes[0]);
                    return fecha.toLocaleDateString('es-ES', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                }
            } catch (e) {
                console.error('Error formateando fecha:', e);
            }
            
            return fechaStr; // Devolver original si hay error
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

        // FUNCIÓN PARA MOSTRAR ERROR MEJORADO
        function mostrarErrorReporte(mensaje) {
            document.getElementById('resultadoReporte').innerHTML = `
                <div class="caja-status" style="border-color: #e74c3c;">
                    <div class="status-icon">❌</div>
                    <div class="status-text" style="color: #e74c3c;">ERROR</div>
                    <p style="color: #7f8c8d; font-size: 1.1rem;">${mensaje}</p>
                </div>
            `;
        }

        // FUNCIÓN EXPORTAR EXCEL CORREGIDA
        function exportarExcel() {
            const periodo = document.getElementById('filtroPeriodo').value;
            const cajero = document.getElementById('filtroCajero').value;
            const turno = obtenerTurnoSeleccionado(periodo);
            
            let url = `generar_reporte.php?exportar=excel&periodo=${periodo}&cajero_id=${cajero}&turno=${turno}`;
            
            if (periodo === 'rango') {
                const fechaInicio = document.getElementById('filtroFechaInicio').value;
                const fechaFin = document.getElementById('filtroFechaFin').value;
                url += `&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
            } else {
                const fecha = obtenerFechaSeleccionada(periodo);
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
// FUNCIONES DE MESAS MEJORADAS - CON DEBUG
function cargarMesas() {
    console.log('🔍 Iniciando carga de mesas...');
    
    // Mostrar indicador de carga
    document.getElementById('mesasGrid').innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
            <div class="spinner-reporte" style="margin: 0 auto 15px;"></div>
            <p style="color: #667eea;">Cargando mesas...</p>
        </div>
    `;
    
    fetch('actualizar_mesas.php')
        .then(response => {
            console.log('📡 Respuesta recibida:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📦 Datos recibidos:', data);
            if (data.success) {
                mostrarMesas(data.mesas);
                actualizarContadoresMesas(data.mesas);
                ultimaActualizacionMesas = new Date();
                document.getElementById('ultimaActualizacion').textContent = 
                    ultimaActualizacionMesas.toLocaleTimeString();
            } else {
                console.error('❌ Error en datos:', data.message);
                mostrarErrorMesas('Error al cargar mesas: ' + data.message);
            }
        })
        .catch(error => {
            console.error('🚨 Error de conexión:', error);
            mostrarErrorMesas('Error de conexión: ' + error);
        });
}

function guardarMesa() {
    const id = document.getElementById('mesaId').value;
    const numero = document.getElementById('mesaNumero').value.trim();
    const capacidad = document.getElementById('mesaCapacidad').value;
    const estado = document.getElementById('mesaEstado').value || 'libre';
    
    console.log('💾 Guardando mesa:', { id, numero, capacidad, estado });
    
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
    
    console.log('📤 Enviando datos:', Object.fromEntries(formData));
    
    // Mostrar loading
    const btnGuardar = document.querySelector('#modalMesa .btn-confirmar');
    const textoOriginal = btnGuardar.textContent;
    btnGuardar.textContent = 'Guardando...';
    btnGuardar.disabled = true;
    
    fetch('guardar_mesa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📡 Respuesta del servidor:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📦 Resultado:', data);
        if (data.success) {
            alert('✅ ' + data.message);
            cerrarModalMesa();
            cargarMesas();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('🚨 Error de conexión:', error);
        alert('❌ Error de conexión: ' + error);
    })
    .finally(() => {
        btnGuardar.textContent = textoOriginal;
        btnGuardar.disabled = false;
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

function actualizarContadoresMesas(mesas) {
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
            alert('✅ ' + data.message);
            cargarMesas();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error de conexión: ' + error);
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
        // Inicializar filtros cuando se carga la página
        // Inicializar filtros cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    cambiarPeriodo(); // Mostrar filtros iniciales
    
    // Iniciar actualización de mesas si la sección está activa
    if (document.getElementById('section-mesas').classList.contains('active')) {
        iniciarActualizacionMesas();
    }
});
// FUNCIÓN DE BÚSQUEDA EN TIEMPO REAL
function filtrarProductos() {
    const busqueda = document.getElementById('buscadorProductos').value.toLowerCase().trim();
    const productos = window.productosOriginales || [];
    
    if (!busqueda) {
        // Si no hay búsqueda, mostrar todos los productos
        renderizarTablaProductos(productos);
        actualizarContadorResultados(productos.length, productos.length);
        return;
    }
    
    // Filtrar productos
    const productosFiltrados = productos.filter(producto => {
        const nombre = producto.nombre.toLowerCase();
        const precio = producto.precio.toString();
        const categoria = producto.categoria.toLowerCase();
        const categoriaTexto = obtenerTextoCategoria(producto.categoria).toLowerCase();
        const estado = producto.activo ? 'activo' : 'inactivo';
        
        // Buscar en todos los campos
        return nombre.includes(busqueda) ||
               precio.includes(busqueda) ||
               categoria.includes(busqueda) ||
               categoriaTexto.includes(busqueda) ||
               estado.includes(busqueda);
    });
    
    // Mostrar resultados
    if (productosFiltrados.length === 0) {
        mostrarSinResultados(busqueda);
    } else {
        renderizarTablaProductos(productosFiltrados);
        actualizarContadorResultados(productosFiltrados.length, productos.length);
    }
}

// Función auxiliar para obtener texto de categoría
function obtenerTextoCategoria(categoria) {
    const categorias = {
        'plato_principal': 'Plato Principal',
        'acompanamiento': 'Acompañamiento', 
        'bebida': 'Bebida'
    };
    return categorias[categoria] || categoria;
}

// Función para mostrar mensaje cuando no hay resultados
function mostrarSinResultados(busqueda) {
    const container = document.getElementById('tabla-productos-container');
    container.innerHTML = `
        <div class="contador-resultados">
            Mostrando 0 de ${window.productosOriginales.length} productos
        </div>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>No se encontraron productos</h3>
            <p>No hay resultados para "<strong>${escapeHtml(busqueda)}</strong>"</p>
            <p style="color: #7f8c8d; font-size: 0.9rem;">
                Intenta con otros términos de búsqueda
            </p>
            <button class="btn-primary-custom" onclick="limpiarBusqueda()" style="margin-top: 15px;">
                🔄 Mostrar todos los productos
            </button>
        </div>
    `;
}

// Función para limpiar la búsqueda
function limpiarBusqueda() {
    document.getElementById('buscadorProductos').value = '';
    filtrarProductos();
}
    </script>
</body>
</html>