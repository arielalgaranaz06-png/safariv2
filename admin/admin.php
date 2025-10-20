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
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">📊 Reportes</h2>
                    </div>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>Sección de Reportes</h3>
                        <p>Esta sección estará disponible próximamente</p>
                    </div>
                </div>
            </div>

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
    </script>
</body>
</html>