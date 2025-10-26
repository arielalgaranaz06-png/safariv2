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

// Obtener productos activos ordenados por categoría y nombre
$stmt_prod = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
$todos_productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// Inicializar arrays para todas las categorías
$productos_por_categoria = [
    'plato_principal' => [],
    'acompanamiento' => [], 
    'bebida' => []
];

// Agrupar productos por categoría
foreach ($todos_productos as $producto) {
    if (isset($productos_por_categoria[$producto['categoria']])) {
        $productos_por_categoria[$producto['categoria']][] = $producto;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cajero - Safari</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles/cajero.css" rel="stylesheet">
</head>
<body>
    <div class="header-bar">
        <h1>Cajero - <?php echo $_SESSION['nombre']; ?></h1>
        <div>
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
                <div class="menu-item" onclick="cambiarSeccion('nuevo-pedido')">
                    <span class="menu-icon">📝</span>
                    <span class="menu-text">Nuevo Pedido</span>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content-area">
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
            <h2 class="text-center mb-4">🏦 Apertura de Caja</h2>
            
            <div class="turno-selector">
                <div class="turno-option" onclick="seleccionarTurno('mañana')">
                    <div class="turno-icon">🌅</div>
                    <div class="turno-nombre">Turno Mañana</div>
                    <div class="turno-horario">12:00 PM - 15:00 PM</div>
                </div>
                <div class="turno-option" onclick="seleccionarTurno('noche')">
                    <div class="turno-icon">🌃</div>
                    <div class="turno-nombre">Turno Noche</div>
                    <div class="turno-horario">19:00 PM - 23:00 PM</div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Registro Monetario Inicial</label>
                <div class="registro-monetario">
                    <div class="denominacion-item">
                        <label>200 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="200" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>100 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="100" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>50 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="50" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>20 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="20" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>10 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="10" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>5 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="5" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>2 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="2" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                    <div class="denominacion-item">
                        <label>1 Bs.</label>
                        <input type="number" class="denominacion-input" data-valor="1" min="0" oninput="calcularMontoInicial()" placeholder="0">
                    </div>
                </div>
                
                <div class="monto-total-display">
                    <strong>Monto Total Inicial: <span id="montoTotalDisplay">Bs. 0.00</span></strong>
                    <input type="hidden" id="montoInicial" name="monto_inicial" value="0">
                </div>
            </div>

            <button type="button" class="btn-abrir-caja" id="btnAbrirCaja" onclick="abrirCaja()" disabled>
                🏦 ABRIR CAJA
            </button>
        </div>
    <?php else: ?>
        <!-- CAJA ABIERTA -->
        <div class="caja-status caja-abierta">
            <div class="status-icon">💰</div>
            <div class="status-text">CAJA ABIERTA</div>
            <p style="color: #7f8c8d; font-size: 1.1rem;">
                Turno: <?php echo strtoupper($caja_abierta['turno']); ?> | 
                Apertura: <?php echo date('H:i', strtotime($caja_abierta['fecha_apertura'])); ?>
            </p>
        </div>

        <div class="caja-info">
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
                    <div class="info-label">Pedidos Activos</div>
                    <div class="info-value"><?php 
                        $stmt_pedidos = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE estado IN ('pendiente', 'preparacion', 'listo') AND caja_id = ?");
                        $stmt_pedidos->execute([$caja_abierta['id']]);
                        $total_pedidos = $stmt_pedidos->fetch(PDO::FETCH_ASSOC);
                        echo $total_pedidos['total'];
                    ?></div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button class="btn-cerrar-caja" onclick="cerrarCaja()">
                🔒 CERRAR CAJA
            </button>
        </div>
    <?php endif; ?>
</div>

            <!-- SECCIÓN PEDIDOS -->
            <div class="section-content" id="section-pedidos">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Pedidos Activos</h2>
                    <small style="color: #95a5a6;">
                        Última actualización: <span id="ultimaActualizacionPedidos">--:--:--</span>
                    </small>
                </div>

                <div id="pedidosLoading" style="text-align: center; padding: 40px;">
                    <div class="spinner"></div>
                    <p style="color: #667eea; margin-top: 15px;">Cargando pedidos...</p>
                </div>

                <div class="pedidos-grid" id="pedidosGrid"></div>

                <div id="pedidosVacio" style="display: none; text-align: center; padding: 40px; color: #7f8c8d;">
                    <div style="font-size: 4rem; margin-bottom: 15px;">📭</div>
                    <h4>No hay pedidos activos</h4>
                    <p>Los pedidos aparecerán aquí cuando sean creados</p>
                </div>
            </div>

            <!-- SECCIÓN MESAS -->
            <div class="section-content" id="section-mesas">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="mb-0">Gestión de Mesas</h2>
                    <button class="btn btn-success" onclick="mostrarModalMesa()">
                        ➕ Nueva Mesa
                    </button>
                </div>

                <div id="mesasLoading" style="text-align: center; padding: 40px;">
                    <div class="spinner"></div>
                    <p>Cargando mesas...</p>
                </div>

                <div id="mesasGrid" class="mesas-grid"></div>

                <div id="mesasVacio" style="display: none; text-align: center; padding: 40px; color: #7f8c8d;">
                    <div style="font-size: 4rem; margin-bottom: 15px;">🍽️</div>
                    <h4>No hay mesas registradas</h4>
                    <p>Haz clic en "Nueva Mesa" para agregar la primera</p>
                </div>
            </div>

            <!-- SECCIÓN NUEVO PEDIDO -->
            <div class="section-content" id="section-nuevo-pedido">
                <h2 class="mb-4">Nuevo Pedido</h2>
                
                <?php if (!$caja_abierta): ?>
                    <div class="caja-status caja-cerrada">
                        <div class="status-icon">🔒</div>
                        <div class="status-text">CAJA CERRADA</div>
                        <p style="color: #7f8c8d; font-size: 1.1rem;">Debe abrir la caja antes de crear pedidos</p>
                    </div>
                <?php else: ?>
                    <div class="nuevo-pedido-container">
                        <!-- Panel de Mesas -->
                        <div class="panel-mesas">
                            <h4 class="mb-3">Mesas Disponibles</h4>
                            <div id="mesasDisponibles" style="flex: 1; overflow-y: auto;">
                                <!-- Las mesas se cargarán dinámicamente aquí -->
                            </div>
                        </div>

                        <!-- Panel de Productos y Resumen -->
                        <div class="panel-productos-resumen">
                            <!-- Panel de Productos -->
                            <div class="panel-productos">
                                <h4 class="mb-3">Productos</h4>
                                <div class="mb-3">
                                    <button class="btn btn-outline-primary btn-sm active" onclick="mostrarCategoriaPedido('plato_principal')">Platos Principales</button>
                                    <button class="btn btn-outline-primary btn-sm" onclick="mostrarCategoriaPedido('acompanamiento')">Acompañamientos</button>
                                    <button class="btn btn-outline-primary btn-sm" onclick="mostrarCategoriaPedido('bebida')">Bebidas</button>
                                </div>
                                
                                <div id="productosContainer">
                                    <?php foreach($productos_por_categoria as $categoria => $productos): ?>
                                        <div class="categoria-content-pedido" id="cat-<?php echo $categoria; ?>" style="display: <?php echo $categoria == 'plato_principal' ? 'block' : 'none'; ?>;">
                                            <div class="row">
                                                <?php foreach($productos as $producto): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="producto-item-pedido" 
                                                             onclick="agregarProductoPedido(<?php echo $producto['id']; ?>, '<?php echo addslashes($producto['nombre']); ?>', <?php echo $producto['precio']; ?>)">
                                                            <h6 class="mb-2"><?php echo $producto['nombre']; ?></h6>
                                                            <p class="text-success mb-0 fw-bold">Bs. <?php echo number_format($producto['precio'], 2); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Panel de Resumen -->
                            <div class="panel-resumen">
                                <h4 class="mb-3">Resumen del Pedido</h4>
                                <div id="resumenPedido">
                                    <p class="text-muted">Seleccione productos para agregar al pedido</p>
                                </div>
                                <div id="totalPedido" class="mt-3 fw-bold fs-5 text-end"></div>
                            </div>
                            <button class="btn btn-success w-100 mt-3" id="btnCrearPedido" onclick="crearPedido()" disabled>
                                    Crear Pedido
                                </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL PAGO -->
    <div class="modal-overlay" id="modalPago">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">Procesar Pago</div>
            
            <div class="pago-section">
                <h5>Método de Pago</h5>
                <div class="pago-metodo">
                    <input type="radio" name="metodoPago" id="pagoEfectivo" value="efectivo" onchange="toggleMetodoPago('efectivo')">
                    <label for="pagoEfectivo">Efectivo</label>
                </div>
                <div class="pago-metodo">
                    <input type="radio" name="metodoPago" id="pagoQR" value="qr" onchange="toggleMetodoPago('qr')">
                    <label for="pagoQR">QR</label>
                </div>
                <div class="pago-metodo">
                    <input type="radio" name="metodoPago" id="pagoMixto" value="mixto" onchange="toggleMetodoPago('mixto')">
                    <label for="pagoMixto">Mixto (Efectivo + QR)</label>
                </div>
            </div>

            <!-- Sección Efectivo -->
            <div id="seccionEfectivo" style="display: none;">
                <div class="pago-section">
                    <label>Monto en Efectivo (Bs.):</label>
                    <input type="number" class="pago-input" id="montoEfectivo" min="0" step="0.01" oninput="calcularCambio()" placeholder="0.00">
                </div>
            </div>

            <!-- Sección Mixto -->
            <div id="seccionMixto" style="display: none;">
                <div class="pago-section">
                    <div class="info-row">
                        <span>Total a Pagar:</span>
                        <span id="totalPagarMixto">Bs. 0.00</span>
                    </div>
                    <div class="pago-section">
                        <label>Monto en Efectivo (Bs.):</label>
                        <input type="number" class="pago-input" id="montoEfectivoMixto" min="0" step="0.01" oninput="calcularPagoMixto()" placeholder="0.00">
                    </div>
                    <div class="info-row">
                        <span>Efectivo ingresado:</span>
                        <span id="efectivoMixto">Bs. 0.00</span>
                    </div>
                    <div class="info-row">
                        <span>Falta por QR:</span>
                        <span id="faltaQR" style="color: #e74c3c; font-weight: bold;">Bs. 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Sección Cambio -->
            <div id="seccionCambio" style="display: none;">
                <div class="cambio-display" id="cambioDisplay">
                    Cambio: Bs. 0.00
                </div>
            </div>

            <div class="total-display">
                <div class="amount" id="totalPagarModal">Bs. 0.00</div>
            </div>

            <div class="modal-actions">
                <button class="btn-modal btn-confirmar" onclick="confirmarPago()">Confirmar Pago</button>
                <button class="btn-modal btn-cancelar" onclick="cerrarModalPago()">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- MODAL MESA -->
    <div class="modal-overlay" id="modalMesa">
        <div class="modal-content">
            <div class="modal-header" id="modalMesaTitulo">Nueva Mesa</div>
            
            <form id="formMesa">
                <input type="hidden" id="mesaId">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Número de Mesa *</label>
                    <input type="text" class="form-control" id="mesaNumero" required 
                           placeholder="Ej: 1, 2, p1, p2...">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Capacidad *</label>
                    <input type="number" class="form-control" id="mesaCapacidad" 
                           min="1" max="20" value="4" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Estado *</label>
                    <select class="form-control" id="mesaEstado" required>
                        <option value="libre">Libre</option>
                        <option value="ocupada">Ocupada</option>
                        <option value="reservada">Reservada</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Descripción (Opcional)</label>
                    <textarea class="form-control" id="mesaDescripcion" 
                             placeholder="Ubicación o características especiales..." 
                             rows="3"></textarea>
                </div>
            </form>

            <div class="modal-actions">
                <button class="btn-modal btn-confirmar" onclick="guardarMesa()">Guardar</button>
                <button class="btn-modal btn-cancelar" onclick="cerrarModalMesa()">Cancelar</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let pedidoActual = {
            mesa_id: null,
            items: [],
            total: 0
        };
        // Función de debug para verificar el estado
/*function debugEstadoEdicion() {
    console.log('🐛 DEBUG ESTADO EDICIÓN:');
    console.log('  - pedidoEditando:', pedidoEditando);
    console.log('  - itemsEdicion:', itemsEdicion);
    console.log('  - totalEdicion:', totalEdicion);
    console.log('  - Modal existe:', document.getElementById('modalEditarPedido') ? 'SÍ' : 'NO');
}*/

// Agrega un botón de debug temporal en el modal (opcional)
// Puedes llamar a debugEstadoEdicion() desde la consola en cualquier momento

        let pedidoSeleccionado = null;
        let totalPagar = 0;
        let metodoPagoActual = '';
        let cajaAbierta = <?php echo $caja_abierta ? 'true' : 'false'; ?>;
        let turnoSeleccionado = null;
        let mesaEditando = null;
        let pedidoEditando = null;
        let itemsEdicion = [];
        let totalEdicion = 0;

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            mostrarCategoriaPedido('plato_principal');
            
            if (cajaAbierta) {
                cargarPedidos();
                cargarMesas();
                cargarMesasDisponibles();
                setInterval(cargarPedidos, 5000); 
            }
        });

        // Funciones de navegación
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        function cambiarSeccion(seccion) {
            document.querySelectorAll('.section-content').forEach(sec => {
                sec.classList.remove('active');
            });
            
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.getElementById('section-' + seccion).classList.add('active');
            
            // Corregir la selección del menú activo
            document.querySelectorAll('.menu-item').forEach(item => {
                const menuText = item.querySelector('.menu-text').textContent.toLowerCase();
                if (menuText.includes(seccion.replace('-', ' '))) {
                    item.classList.add('active');
                }
            });

            if (seccion === 'mesas') {
                cargarMesas();
            } else if (seccion === 'nuevo-pedido' && cajaAbierta) {
                cargarMesasDisponibles();
            } else if (seccion === 'pedidos' && cajaAbierta) {
                cargarPedidos();
            }
        }

        // Funciones para Caja
            function seleccionarTurno(turno) {
                document.querySelectorAll('.turno-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                const selectedOption = document.querySelector(`.turno-option:nth-child(${turno === 'mañana' ? 1 : 2})`);
                selectedOption.classList.add('selected');
                
                turnoSeleccionado = turno;
                verificarFormularioApertura();
            }
            function calcularMontoInicial() {
                    let total = 0;
                    
                    document.querySelectorAll('.denominacion-input').forEach(input => {
                        const cantidad = parseInt(input.value) || 0;
                        const valor = parseInt(input.getAttribute('data-valor'));
                        total += cantidad * valor;
                    });
                    
                    document.getElementById('montoTotalDisplay').textContent = `Bs. ${total.toFixed(2)}`;
                    document.getElementById('montoInicial').value = total;
                    
                    verificarFormularioApertura();
                }

        function verificarFormularioApertura() {
            const montoInicial = parseFloat(document.getElementById('montoInicial').value) || 0;
            const btnAbrir = document.getElementById('btnAbrirCaja');

            btnAbrir.disabled = !turnoSeleccionado || montoInicial <= 0;
        }


        function abrirCaja() {
    const montoInicial = parseFloat(document.getElementById('montoInicial').value) || 0;
    
    if (!turnoSeleccionado || montoInicial <= 0) {
        alert('Complete todos los campos correctamente');
        return;
    }

    // Obtener el detalle de denominaciones
    const denominaciones = {};
    document.querySelectorAll('.denominacion-input').forEach(input => {
        const valor = input.getAttribute('data-valor');
        const cantidad = parseInt(input.value) || 0;
        if (cantidad > 0) {
            denominaciones[valor] = cantidad;
        }
    });

    const formData = new FormData();
    formData.append('turno', turnoSeleccionado);
    formData.append('monto_inicial', montoInicial);
    formData.append('denominaciones', JSON.stringify(denominaciones));

    fetch('caja/abrir_caja.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Caja abierta correctamente');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al abrir caja');
    });
}
       function cerrarCaja() {
    if (!confirm('¿Está seguro de cerrar la caja? Se calcularán los totales del turno.')) return;

    fetch('caja/cerrar_caja.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Caja cerrada correctamente');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cerrar caja');
    });
}

        // Funciones para Mesas
        function cargarMesas() {
            document.getElementById('mesasLoading').style.display = 'block';
            document.getElementById('mesasVacio').style.display = 'none';
            document.getElementById('mesasGrid').innerHTML = '';

            fetch('mesas/obtener_mesas.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('mesasLoading').style.display = 'none';
                    
                    if (data.success && data.mesas.length > 0) {
                        mostrarMesas(data.mesas);
                    } else {
                        document.getElementById('mesasVacio').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('mesasLoading').style.display = 'none';
                    document.getElementById('mesasVacio').style.display = 'block';
                });
        }

        function cargarMesasDisponibles() {
            fetch('mesas/obtener_mesas.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMesasParaPedido(data.mesas);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function mostrarMesas(mesas) {
            const grid = document.getElementById('mesasGrid');
            grid.innerHTML = '';

            // Ordenar mesas
            mesas.sort((a, b) => {
                const numA = a.numero.toString();
                const numB = b.numero.toString();
                
                if (!isNaN(numA) && !isNaN(numB)) {
                    return parseInt(numA) - parseInt(numB);
                }
                
                if (numA.includes('p') && !numB.includes('p')) return 1;
                if (!numA.includes('p') && numB.includes('p')) return -1;
                
                if (numA.includes('p') && numB.includes('p')) {
                    const numAP = parseInt(numA.replace('p', ''));
                    const numBP = parseInt(numB.replace('p', ''));
                    return numAP - numBP;
                }
                
                return numA.localeCompare(numB);
            });

            mesas.forEach(mesa => {
                const estadoClass = mesa.estado === 'libre' ? 'mesa-libre' : 
                                mesa.estado === 'ocupada' ? 'mesa-ocupada' : 
                                mesa.estado === 'reservada' ? 'mesa-reservada' : 'mesa-mantenimiento';
                
                const estadoText = mesa.estado === 'libre' ? 'Libre' : 
                                mesa.estado === 'ocupada' ? 'Ocupada' : 
                                mesa.estado === 'reservada' ? 'Reservada' : 'Mantenimiento';

                const card = document.createElement('div');
                card.className = `mesa-card ${estadoClass}`;
                card.innerHTML = `
                    <div>
                        <div class="mesa-numero">Mesa ${mesa.numero}</div>
                        <span class="mesa-estado estado-${mesa.estado}">${estadoText}</span>
                    </div>
                    <div class="mesa-capacidad">
                        👥 Capacidad: ${mesa.capacidad} personas
                    </div>
                    ${mesa.descripcion ? `<div class="mesa-descripcion" style="font-size: 0.8rem; color: #7f8c8d; margin-top: 5px;">${mesa.descripcion}</div>` : ''}
                    <div class="mesa-actions">
                        <button class="btn-mesa-action btn-editar-mesa" onclick="editarMesa(${mesa.id})">
                            ✏️ Editar
                        </button>
                        <button class="btn-mesa-action btn-eliminar-mesa" onclick="eliminarMesa(${mesa.id}, '${mesa.numero}')">
                            🗑️ Eliminar
                        </button>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function mostrarMesasParaPedido(mesas) {
            const container = document.getElementById('mesasDisponibles');
            container.innerHTML = '';

            const mesasLibres = mesas.filter(mesa => mesa.estado === 'libre');
            
            if (mesasLibres.length === 0) {
                container.innerHTML = '<p class="text-muted">No hay mesas disponibles</p>';
                return;
            }

            mesasLibres.forEach(mesa => {
                const mesaItem = document.createElement('div');
                mesaItem.className = 'mesa-item';
                mesaItem.innerHTML = `
                    <h5>Mesa ${mesa.numero}</h5>
                    <small>Capacidad: ${mesa.capacidad} personas</small>
                `;
                mesaItem.onclick = () => seleccionarMesa(mesa.id, mesaItem);
                container.appendChild(mesaItem);
            });
        }

        function seleccionarMesa(mesaId, elemento) {
            document.querySelectorAll('.mesa-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            elemento.classList.add('selected');
            pedidoActual.mesa_id = mesaId;
            
            actualizarBotonCrearPedido();
        }

        // Funciones para el modal de mesas
        function mostrarModalMesa() {
            mesaEditando = null;
            document.getElementById('modalMesaTitulo').textContent = 'Nueva Mesa';
            document.getElementById('formMesa').reset();
            document.getElementById('mesaId').value = '';
            document.getElementById('modalMesa').classList.add('show');
        }

        function cerrarModalMesa() {
            document.getElementById('modalMesa').classList.remove('show');
            mesaEditando = null;
        }

        function editarMesa(mesaId) {
            //console.log('🔧 Editando mesa ID:', mesaId);
            
            // Mostrar loading en el modal
            document.getElementById('modalMesaTitulo').textContent = 'Cargando...';
            
            // Obtener datos de la mesa
            fetch(`mesas/obtener_mesa.php?id=${mesaId}`)
                .then(response => {
                    //console.log('📡 Respuesta del servidor:', response.status);
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    //console.log('📊 Datos recibidos:', data);
                    
                    if (data.success && data.mesa) {
                        const mesa = data.mesa;
                       // console.log('✅ Mesa encontrada:', mesa);
                        
                        mesaEditando = mesaId;
                        
                        // Llenar el formulario
                        document.getElementById('modalMesaTitulo').textContent = 'Editar Mesa';
                        document.getElementById('mesaId').value = mesa.id || '';
                        document.getElementById('mesaNumero').value = mesa.numero || '';
                        document.getElementById('mesaCapacidad').value = mesa.capacidad || 4;
                        document.getElementById('mesaEstado').value = mesa.estado || 'libre';
                        document.getElementById('mesaDescripcion').value = mesa.descripcion || '';
                        
                        // Mostrar el modal
                        document.getElementById('modalMesa').classList.add('show');
                        
                    } else {
                        console.error('❌ Error en datos:', data.message);
                        alert('Error al cargar datos de la mesa: ' + (data.message || 'Mesa no encontrada'));
                    }
                })
                .catch(error => {
                    console.error('💥 Error fatal:', error);
                    alert('Error de conexión: ' + error.message);
                });
        }

        function guardarMesa() {
            const formData = new FormData();
            formData.append('id', document.getElementById('mesaId').value);
            formData.append('numero', document.getElementById('mesaNumero').value);
            formData.append('capacidad', document.getElementById('mesaCapacidad').value);
            formData.append('estado', document.getElementById('mesaEstado').value);
            formData.append('descripcion', document.getElementById('mesaDescripcion').value);

            const url = mesaEditando ? 'mesas/editar_mesa.php' : 'mesas/crear_mesa.php';

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(mesaEditando ? 'Mesa actualizada correctamente' : 'Mesa creada correctamente');
                    cerrarModalMesa();
                    cargarMesas();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar la mesa');
            });
        }

        function eliminarMesa(mesaId, numeroMesa) {
            if (!confirm(`¿Estás seguro de eliminar la Mesa ${numeroMesa}? Esta acción no se puede deshacer.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('id', mesaId);

            fetch('mesas/eliminar_mesa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Mesa eliminada correctamente');
                    cargarMesas();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar la mesa');
            });
        }

        // Funciones para Productos y Pedidos
        function mostrarCategoriaPedido(categoria) {
            // Remover active de todas las categorías
            document.querySelectorAll('.categoria-content-pedido').forEach(cat => {
                cat.style.display = 'none';
            });
            
            // Remover active de todos los botones
            document.querySelectorAll('.panel-productos .btn').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            });
            
            // Activar la categoría seleccionada
            document.getElementById('cat-' + categoria).style.display = 'block';
            
            // Activar el botón correspondiente
            const botones = document.querySelectorAll('.panel-productos .btn');
            const categoriaMap = {
                'plato_principal': 0,
                'acompanamiento': 1,
                'bebida': 2
            };
            
            if (categoriaMap[categoria] !== undefined) {
                botones[categoriaMap[categoria]].classList.add('active');
                botones[categoriaMap[categoria]].classList.remove('btn-outline-primary');
                botones[categoriaMap[categoria]].classList.add('btn-primary');
            }
        }

        function agregarProductoPedido(productoId, nombre, precio) {
            if (!pedidoActual.mesa_id) {
                alert('Por favor seleccione una mesa primero');
                return;
            }

            const itemExistente = pedidoActual.items.find(item => item.producto_id === productoId);
            
            if (itemExistente) {
                itemExistente.cantidad++;
            } else {
                pedidoActual.items.push({
                    producto_id: productoId,
                    nombre: nombre,
                    precio: precio,
                    cantidad: 1
                });
            }

            actualizarResumenPedido();
            actualizarBotonCrearPedido();
        }

        function actualizarResumenPedido() {
            const resumen = document.getElementById('resumenPedido');
            const totalElement = document.getElementById('totalPedido');
            
            if (pedidoActual.items.length === 0) {
                resumen.innerHTML = '<p class="text-muted">Seleccione productos para agregar al pedido</p>';
                totalElement.innerHTML = '';
                pedidoActual.total = 0;
                return;
            }

            let html = '';
            let total = 0;

            pedidoActual.items.forEach((item, index) => {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;

                html += `
                    <div class="producto-editar">
                        <div>
                            <strong>${item.nombre}</strong><br>
                            <small>Bs. ${item.precio.toFixed(2)} x ${item.cantidad}</small>
                        </div>
                        <div>
                            <strong>Bs. ${subtotal.toFixed(2)}</strong>
                            <div style="margin-top: 5px;">
                                <button class="btn btn-sm btn-outline-secondary" onclick="modificarCantidad(${index}, -1)">-</button>
                                <span style="margin: 0 10px;">${item.cantidad}</span>
                                <button class="btn btn-sm btn-outline-secondary" onclick="modificarCantidad(${index}, 1)">+</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="eliminarProducto(${index})" style="margin-left: 5px;">×</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            resumen.innerHTML = html;
            totalElement.innerHTML = `<strong>Total: Bs. ${total.toFixed(2)}</strong>`;
            pedidoActual.total = total;
        }

        function modificarCantidad(index, cambio) {
            const nuevoTotal = pedidoActual.items[index].cantidad + cambio;
            
            if (nuevoTotal <= 0) {
                eliminarProducto(index);
            } else {
                pedidoActual.items[index].cantidad = nuevoTotal;
                actualizarResumenPedido();
            }
        }

        function eliminarProducto(index) {
            pedidoActual.items.splice(index, 1);
            actualizarResumenPedido();
            actualizarBotonCrearPedido();
        }

        function actualizarBotonCrearPedido() {
            const btn = document.getElementById('btnCrearPedido');
            btn.disabled = !pedidoActual.mesa_id || pedidoActual.items.length === 0;
        }

        function crearPedido() {
            if (!pedidoActual.mesa_id || pedidoActual.items.length === 0) {
                alert('Complete el pedido antes de crearlo');
                return;
            }

            const datos = {
                mesa_id: pedidoActual.mesa_id,
                items: pedidoActual.items,
                total: pedidoActual.total
            };

            fetch('pedidos/crear_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pedido creado correctamente');
                    // Resetear pedido actual
                    pedidoActual = {
                        mesa_id: null,
                        items: [],
                        total: 0
                    };
                    // Deseleccionar mesa
                    document.querySelectorAll('.mesa-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    actualizarResumenPedido();
                    actualizarBotonCrearPedido();
                    // Recargar datos
                    cargarMesasDisponibles();
                    cargarPedidos();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al crear pedido');
            });
        }

        // Funciones para la gestión de pedidos
        function cargarPedidos() {
            document.getElementById('pedidosLoading').style.display = 'block';
            document.getElementById('pedidosVacio').style.display = 'none';
            document.getElementById('pedidosGrid').innerHTML = '';

            fetch('pedidos/obtener_pedidos.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('pedidosLoading').style.display = 'none';
                    
                    if (data.success && data.pedidos.length > 0) {
                        mostrarPedidos(data.pedidos);
                        document.getElementById('ultimaActualizacionPedidos').textContent = new Date().toLocaleTimeString();
                    } else {
                        document.getElementById('pedidosVacio').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('pedidosLoading').style.display = 'none';
                    document.getElementById('pedidosVacio').style.display = 'block';
                });
        }

        function mostrarPedidos(pedidos) {
            const grid = document.getElementById('pedidosGrid');
            grid.innerHTML = '';

            pedidos.forEach(pedido => {
                const card = document.createElement('div');
                card.className = 'pedido-card';
                card.innerHTML = `
                    <div class="pedido-header">
                        <div class="pedido-mesa">Mesa ${pedido.mesa_numero}</div>
                        <div class="pedido-hora">${new Date(pedido.fecha_pedido).toLocaleTimeString()}</div>
                    </div>
                    <div class="pedido-info">
                        <div class="info-row">
                            <span>Garzon:</span>
                            <span>${pedido.garzon_nombre}</span>
                        </div>
                        <div class="info-row">
                            <span>Estado:</span>
                            <span class="badge bg-${pedido.estado === 'listo' ? 'success' : pedido.estado === 'preparacion' ? 'warning' : 'secondary'}">
                                ${pedido.estado.toUpperCase()}
                            </span>
                        </div>
                        <div class="info-row">
                            <span>Items:</span>
                            <span>${pedido.total_items}</span>
                        </div>
                    </div>
                    <div class="pedido-total">Bs. ${parseFloat(pedido.total).toFixed(2)}</div>
                    <div class="pedido-actions">
                        <button class="btn-action btn-editar" onclick="editarPedido(${pedido.id})">
                            ✏️ Editar
                        </button>
                        <button class="btn-action btn-eliminar" onclick="eliminarPedido(${pedido.id})">
                            🗑️ Eliminar
                        </button>
                        <button class="btn-action btn-pagar" onclick="procesarPago(${pedido.id}, ${pedido.total})">
                            💳 Pagar
                        </button>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        // CORREGIDO: Función para editar pedido - asegurar que guarde el ID correctamente
function editarPedido(pedidoId) {
    //console.log('🎯 Función editarPedido ejecutada con ID:', pedidoId);
    
    if (!pedidoId) {
        alert('Error: ID de pedido inválido');
        return;
    }
    
    // CORRECCIÓN: Asignar inmediatamente y verificar
    pedidoEditando = parseInt(pedidoId);
    //console.log('✅ pedidoEditando asignado:', pedidoEditando);
    
    // Mostrar loading
    const pedidoCard = document.querySelector(`[onclick*="editarPedido(${pedidoId})"]`)?.closest('.pedido-card');
    if (pedidoCard) {
        pedidoCard.style.opacity = '0.7';
    }
    
    //console.log('📡 Haciendo petición a:', `pedidos/obtener_pedido_editar.php?id=${pedidoId}`);
    
    // Cargar datos del pedido
    fetch(`pedidos/obtener_pedido_editar.php?id=${pedidoId}`)
        .then(response => {
            //console.log('📡 Respuesta del servidor - Status:', response.status);
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
           // console.log('📊 Datos recibidos:', data);
            
            if (data.success) {
                //console.log('✅ Pedido cargado exitosamente');
                // CORRECCIÓN: Verificar que el ID se mantenga
                //console.log('🔍 Verificando pedidoEditando antes de mostrar modal:', pedidoEditando);
                mostrarModalEditarPedido(data.pedido, data.items);
            } else {
                console.error('❌ Error en la respuesta:', data.message);
                alert('Error al cargar pedido: ' + data.message);
            }
        })
        .catch(error => {
            console.error('💥 Error en la petición:', error);
            alert('Error de conexión: ' + error.message);
        })
        .finally(() => {
            // Restaurar opacidad
            if (pedidoCard) {
                pedidoCard.style.opacity = '1';
            }
        });
}

        function eliminarPedido(pedidoId) {
            if (!confirm('¿Está seguro de eliminar este pedido? Esta acción no se puede deshacer.')) {
                return;
            }

            fetch('pedidos/eliminar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ pedido_id: pedidoId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pedido eliminado correctamente');
                    cargarPedidos();
                    cargarMesasDisponibles();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el pedido');
            });
        }

        // Funciones para el pago
        function procesarPago(pedidoId, total) {
            pedidoSeleccionado = pedidoId;
            totalPagar = parseFloat(total);
            
            document.getElementById('totalPagarModal').textContent = `Bs. ${totalPagar.toFixed(2)}`;
            document.getElementById('totalPagarMixto').textContent = `Bs. ${totalPagar.toFixed(2)}`;
            
            // Resetear formulario
            resetearModalPago();
            
            document.getElementById('modalPago').classList.add('show');
        }

        function resetearModalPago() {
            document.querySelectorAll('input[name="metodoPago"]').forEach(radio => {
                radio.checked = false;
            });
            document.getElementById('montoEfectivo').value = '';
            document.getElementById('montoEfectivoMixto').value = '';
            document.getElementById('seccionEfectivo').style.display = 'none';
            document.getElementById('seccionMixto').style.display = 'none';
            document.getElementById('seccionCambio').style.display = 'none';
            document.getElementById('cambioDisplay').textContent = 'Cambio: Bs. 0.00';
            document.getElementById('cambioDisplay').className = 'cambio-display';
            document.getElementById('efectivoMixto').textContent = 'Bs. 0.00';
            document.getElementById('faltaQR').textContent = `Bs. ${totalPagar.toFixed(2)}`;
        }

        function toggleMetodoPago(metodo) {
            metodoPagoActual = metodo;
            
            const seccionEfectivo = document.getElementById('seccionEfectivo');
            const seccionMixto = document.getElementById('seccionMixto');
            const seccionCambio = document.getElementById('seccionCambio');
            
            // Ocultar todas las secciones primero
            seccionEfectivo.style.display = 'none';
            seccionMixto.style.display = 'none';
            seccionCambio.style.display = 'none';
            
            if (metodo === 'efectivo') {
                seccionEfectivo.style.display = 'block';
                seccionCambio.style.display = 'block';
                calcularCambio();
            } else if (metodo === 'mixto') {
                seccionMixto.style.display = 'block';
                seccionCambio.style.display = 'block';
                calcularPagoMixto();
            }
        }

        function calcularPagoMixto() {
            if (metodoPagoActual !== 'mixto') return;
            
            const montoEfectivo = parseFloat(document.getElementById('montoEfectivoMixto').value) || 0;
            const faltaQR = totalPagar - montoEfectivo;
            
            document.getElementById('efectivoMixto').textContent = 'Bs. ' + montoEfectivo.toFixed(2);
            document.getElementById('faltaQR').textContent = 'Bs. ' + Math.max(0, faltaQR).toFixed(2);
            
            // Calcular cambio si el efectivo es mayor al total
            const cambioDisplay = document.getElementById('cambioDisplay');
            if (faltaQR < 0) {
                const cambio = Math.abs(faltaQR);
                cambioDisplay.textContent = 'Cambio: Bs. ' + cambio.toFixed(2);
                cambioDisplay.className = 'cambio-display positivo';
                document.getElementById('faltaQR').textContent = 'Bs. 0.00';
                document.getElementById('faltaQR').style.color = '#27ae60';
            } else {
                cambioDisplay.textContent = 'Cambio: Bs. 0.00';
                cambioDisplay.className = 'cambio-display';
                document.getElementById('faltaQR').style.color = '#e74c3c';
            }
        }

        function calcularCambio() {
            if (metodoPagoActual !== 'efectivo') return;
            
            const montoEfectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
            const cambio = montoEfectivo - totalPagar;
            
            const cambioDisplay = document.getElementById('cambioDisplay');
            cambioDisplay.textContent = 'Cambio: Bs. ' + cambio.toFixed(2);
            
            if (cambio >= 0) {
                cambioDisplay.className = 'cambio-display positivo';
            } else {
                cambioDisplay.className = 'cambio-display negativo';
                cambioDisplay.textContent = 'Falta: Bs. ' + Math.abs(cambio).toFixed(2);
            }
        }

        function confirmarPago() {
            if (!pedidoSeleccionado) return;

            const metodoEfectivo = document.getElementById('pagoEfectivo').checked;
            const metodoQR = document.getElementById('pagoQR').checked;
            const metodoMixto = document.getElementById('pagoMixto').checked;
            
            let montoEfectivo = 0;
            let montoQR = 0;
            let metodoPago = '';

            if (metodoEfectivo) {
                montoEfectivo = parseFloat(document.getElementById('montoEfectivo').value) || 0;
                if (montoEfectivo < totalPagar) {
                    alert('El monto en efectivo es menor al total a pagar');
                    return;
                }
                metodoPago = 'efectivo';
                montoQR = 0;
            } else if (metodoQR) {
                metodoPago = 'qr';
                montoEfectivo = 0;
                montoQR = totalPagar;
            } else if (metodoMixto) {
                montoEfectivo = parseFloat(document.getElementById('montoEfectivoMixto').value) || 0;
                montoQR = Math.max(0, totalPagar - montoEfectivo);
                
                if (montoEfectivo <= 0 && montoQR <= 0) {
                    alert('Ingrese un monto en efectivo para pago mixto');
                    return;
                }
                
                if (montoEfectivo + montoQR < totalPagar) {
                    alert('El monto total ingresado es insuficiente');
                    return;
                }
                
                metodoPago = 'mixto';
            } else {
                alert('Seleccione un método de pago');
                return;
            }

            const datos = {
                pedido_id: pedidoSeleccionado,
                metodo_pago: metodoPago,
                monto_efectivo: montoEfectivo,
                monto_qr: montoQR,
                total_pagar: totalPagar
            };

            fetch('pedidos/procesar_pago.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pago procesado exitosamente');
                    cerrarModalPago();
                    cargarPedidos();
                    cargarMesasDisponibles();
                } else {
                    alert('Error al procesar pago: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar pago');
            });
        }

        function cerrarModalPago() {
            document.getElementById('modalPago').classList.remove('show');
            pedidoSeleccionado = null;
            totalPagar = 0;
            metodoPagoActual = '';
        }

        // CORREGIDO: Función para mostrar modal de edición
function mostrarModalEditarPedido(pedido, items) {
    //console.log('🔄 Mostrando modal de edición para pedido:', pedido.id);
    //console.log('🔍 pedidoEditando al entrar a mostrarModal:', pedidoEditando);
    
    // Cerrar modal existente si hay uno
    cerrarModalEditarPedido();
    
    // CORRECCIÓN: Asegurar que el ID se mantenga
    if (pedidoEditando !== parseInt(pedido.id)) {
        console.warn('⚠️  IDs no coinciden, corrigiendo...');
        pedidoEditando = parseInt(pedido.id);
    }
    
    //console.log('✅ pedidoEditando confirmado:', pedidoEditando);
    
    // Crear modal de edición
    const modalHTML = `
        <div class="modal-overlay show" id="modalEditarPedido">
            <div class="modal-content" style="max-width: 95%; width: 1200px; height: 90vh;">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Editar Pedido - Mesa ${pedido.mesa_numero} (ID: ${pedido.id})</h4>
                    <button type="button" class="btn-close" onclick="cerrarModalEditarPedido()" style="border: none; background: none; font-size: 1.5rem;">×</button>
                </div>
                
                <div class="modal-body" style="height: calc(100% - 120px); overflow-y: auto;">
                    <!-- Información del pedido -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Información del Pedido</h6>
                                    <p class="mb-1"><strong>ID:</strong> ${pedido.id}</p>
                                    <p class="mb-1"><strong>Mesa:</strong> ${pedido.mesa_numero}</p>
                                    <p class="mb-1"><strong>Estado:</strong> <span class="badge bg-${pedido.estado === 'listo' ? 'success' : pedido.estado === 'preparacion' ? 'warning' : 'secondary'}">${pedido.estado.toUpperCase()}</span></p>
                                    <p class="mb-1"><strong>Garzon:</strong> ${pedido.garzon_nombre}</p>
                                    <p class="mb-1"><strong>Total actual:</strong> Bs. ${parseFloat(pedido.total).toFixed(2)}</p>
                                    ${pedido.modificado_por ? `<p class="mb-1"><strong>Última modificación:</strong> ${pedido.modificado_por} - ${new Date(pedido.fecha_modificacion).toLocaleString()}</p>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Buscador de Productos</h6>
                                    <input type="text" class="form-control" id="buscadorProductos" 
                                           placeholder="Buscar productos..." onkeyup="filtrarProductos()">
                                    <div class="mt-2">
                                        <button class="btn btn-outline-primary btn-sm active categoria-edicion-activa" data-categoria="todos" onclick="mostrarCategoriaEdicion('todos')">Todos</button>
                                        <button class="btn btn-outline-primary btn-sm" data-categoria="plato_principal" onclick="mostrarCategoriaEdicion('plato_principal')">Platos</button>
                                        <button class="btn btn-outline-primary btn-sm" data-categoria="acompanamiento" onclick="mostrarCategoriaEdicion('acompanamiento')">Acompañamientos</button>
                                        <button class="btn btn-outline-primary btn-sm" data-categoria="bebida" onclick="mostrarCategoriaEdicion('bebida')">Bebidas</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Panel de Productos -->
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Productos Disponibles</h5>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div id="productosEdicionContainer">
                                        <!-- Los productos se cargarán aquí -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de Resumen -->
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Items del Pedido</h5>
                                </div>
                                <div class="card-body">
                                    <div id="resumenEdicionPedido">
                                        ${generarHTMLItemsEdicion(items)}
                                    </div>
                                    <div class="mt-3">
                                        <h5 class="text-end">Total: <span id="totalEdicionPedido">Bs. ${parseFloat(pedido.total).toFixed(2)}</span></h5>
                                    </div>
                                    <div class="mt-2 text-center">
                                        <small class="text-muted">Pedido ID: ${pedido.id} | Items: ${items.length}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-success" onclick="guardarEdicionPedido()">
                        💾 Guardar Cambios (ID: ${pedido.id})
                    </button>
                    <button class="btn btn-secondary" onclick="cerrarModalEditarPedido()">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    `;

    // Agregar modal al body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Inicializar datos de edición
    inicializarDatosEdicion(items);
    cargarProductosEdicion();
    
    //console.log('✅ Modal de edición mostrado correctamente');
    //console.log('🔍 pedidoEditando final:', pedidoEditando);
}
        // Fin funciones mostrarModalEditarPedido
        function generarHTMLItemsEdicion(items) {
            if (!items || items.length === 0) {
                return '<p class="text-muted">No hay items en el pedido</p>';
            }

            let html = '';
            items.forEach((item, index) => {
                const subtotal = item.precio_unitario * item.cantidad;
                html += `
                    <div class="producto-editar-item mb-2 p-2 border rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.nombre}</strong><br>
                                <small>Bs. ${parseFloat(item.precio_unitario).toFixed(2)} x ${item.cantidad}</small>
                            </div>
                            <div class="text-end">
                                <strong>Bs. ${subtotal.toFixed(2)}</strong>
                                <div class="mt-1">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="modificarCantidadEdicion(${item.producto_id}, -1)">-</button>
                                    <span class="mx-2">${item.cantidad}</span>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="modificarCantidadEdicion(${item.producto_id}, 1)">+</button>
                                    <button class="btn btn-sm btn-outline-danger ms-1" onclick="eliminarProductoEdicion(${item.producto_id})">×</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            return html;
        }

        // Función corregida para inicializar datos de edición
function inicializarDatosEdicion(items) {
    //console.log('📝 Inicializando datos de edición con items:', items);
    
    itemsEdicion = [];
    
    if (items && items.length > 0) {
        items.forEach(item => {
            itemsEdicion.push({
                producto_id: parseInt(item.producto_id),
                nombre: item.nombre,
                precio: parseFloat(item.precio_unitario),
                cantidad: parseInt(item.cantidad)
            });
        });
    }
    
    //console.log('📝 ItemsEdicion después de inicializar:', itemsEdicion);
    calcularTotalEdicion();
}

       // Función corregida para cargar productos en edición
function cargarProductosEdicion() {
    const modal = document.getElementById('modalEditarPedido');
    if (!modal) return;
    
    const container = modal.querySelector('#productosEdicionContainer');
    
    // Usar los productos ya cargados desde PHP
    const productos = <?php echo json_encode($todos_productos); ?>;
    
   // console.log('📦 Cargando productos para edición:', productos.length);
    
    let html = '';
    
    productos.forEach(producto => {
        // Escapar comillas en el nombre
        const nombreSeguro = producto.nombre.replace(/'/g, "\\'").replace(/"/g, '\\"');
        
        html += `
            <div class="producto-item-edicion mb-2 p-2 border rounded" data-categoria="${producto.categoria}" data-nombre="${producto.nombre.toLowerCase()}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${producto.nombre}</h6>
                        <small class="text-muted">${producto.categoria.replace('_', ' ')}</small>
                    </div>
                    <div class="text-end">
                        <p class="text-success mb-1 fw-bold">Bs. ${parseFloat(producto.precio).toFixed(2)}</p>
                        <button class="btn btn-sm btn-primary" onclick="agregarProductoEdicion(${producto.id}, '${nombreSeguro}', ${producto.precio})">
                            Agregar
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    //console.log('✅ Productos cargados en el modal');
    
    // Aplicar filtro inicial
    filtrarProductos();
}

        // Función corregida para filtrar productos
function filtrarProductos() {
    const modal = document.getElementById('modalEditarPedido');
    if (!modal) return;
    
    const busqueda = modal.querySelector('#buscadorProductos').value.toLowerCase();
    const productos = modal.querySelectorAll('.producto-item-edicion');
    const botonActivo = modal.querySelector('.btn.active[data-categoria]');
    const categoriaActual = botonActivo ? botonActivo.getAttribute('data-categoria') : 'todos';
    
   // console.log('🔍 Filtrando - Categoría:', categoriaActual, 'Búsqueda:', busqueda);
    
    productos.forEach(producto => {
        const nombre = producto.getAttribute('data-nombre');
        const categoria = producto.getAttribute('data-categoria');
        
        const coincideBusqueda = nombre.includes(busqueda);
        const coincideCategoria = categoriaActual === 'todos' || categoria === categoriaActual;
        
        producto.style.display = (coincideBusqueda && coincideCategoria) ? 'block' : 'none';
    });
}
// Función corregida para mostrar categorías en edición
function mostrarCategoriaEdicion(categoria) {
    //console.log('🔧 Mostrando categoría:', categoria);
    
    // Actualizar botones - SOLUCIÓN: Seleccionar correctamente solo los botones del modal actual
    const modal = document.getElementById('modalEditarPedido');
    if (!modal) return;
    
    const botones = modal.querySelectorAll('.btn');
    botones.forEach(btn => {
        btn.classList.remove('active', 'btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    
    // Activar botón seleccionado
    const botonSeleccionado = modal.querySelector(`[data-categoria="${categoria}"]`);
    if (botonSeleccionado) {
        botonSeleccionado.classList.add('active', 'btn-primary');
        botonSeleccionado.classList.remove('btn-outline-primary');
    }
    
    // Filtrar productos
    filtrarProductos();
}

        // Función corregida para agregar productos en edición
function agregarProductoEdicion(productoId, nombre, precio) {
    //console.log('➕ Agregando producto a edición:', { productoId, nombre, precio });
    
    const itemExistente = itemsEdicion.find(item => item.producto_id === productoId);
    
    if (itemExistente) {
        itemExistente.cantidad++;
        //console.log('📈 Producto existente, cantidad aumentada a:', itemExistente.cantidad);
    } else {
        itemsEdicion.push({
            producto_id: productoId,
            nombre: nombre,
            precio: parseFloat(precio),
            cantidad: 1
        });
       // console.log('🆕 Nuevo producto agregado');
    }
    
    //console.log('📦 ItemsEdicion después de agregar:', itemsEdicion);
    actualizarResumenEdicion();
}


        function modificarCantidadEdicion(productoId, cambio) {
            const item = itemsEdicion.find(item => item.producto_id === productoId);
            
            if (item) {
                const nuevoTotal = item.cantidad + cambio;
                
                if (nuevoTotal <= 0) {
                    eliminarProductoEdicion(productoId);
                } else {
                    item.cantidad = nuevoTotal;
                    actualizarResumenEdicion();
                }
            }
        }

        function eliminarProductoEdicion(productoId) {
            itemsEdicion = itemsEdicion.filter(item => item.producto_id !== productoId);
            actualizarResumenEdicion();
        }

       // Función corregida para actualizar resumen de edición
function actualizarResumenEdicion() {
    const modal = document.getElementById('modalEditarPedido');
    if (!modal) return;
    
    const resumen = modal.querySelector('#resumenEdicionPedido');
    const totalElement = modal.querySelector('#totalEdicionPedido');
    
    //console.log('🔄 Actualizando resumen, items count:', itemsEdicion.length);
    
    if (!itemsEdicion || itemsEdicion.length === 0) {
        resumen.innerHTML = '<p class="text-muted">No hay items en el pedido</p>';
        totalElement.textContent = 'Bs. 0.00';
        totalEdicion = 0;
        return;
    }

    let html = '';
    let total = 0;

    itemsEdicion.forEach((item) => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;

        html += `
            <div class="producto-editar-item mb-2 p-2 border rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${item.nombre}</strong><br>
                        <small>Bs. ${item.precio.toFixed(2)} x ${item.cantidad}</small>
                    </div>
                    <div class="text-end">
                        <strong>Bs. ${subtotal.toFixed(2)}</strong>
                        <div class="mt-1">
                            <button class="btn btn-sm btn-outline-secondary" onclick="modificarCantidadEdicion(${item.producto_id}, -1)">-</button>
                            <span class="mx-2">${item.cantidad}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="modificarCantidadEdicion(${item.producto_id}, 1)">+</button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="eliminarProductoEdicion(${item.producto_id})">×</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    resumen.innerHTML = html;
    totalElement.textContent = `Bs. ${total.toFixed(2)}`;
    totalEdicion = total;
    
    //console.log('✅ Resumen actualizado, total:', totalEdicion);
}
// fin actualizarResumenEdicion
        function calcularTotalEdicion() {
            totalEdicion = itemsEdicion.reduce((total, item) => total + (item.precio * item.cantidad), 0);
            document.getElementById('totalEdicionPedido').textContent = `Bs. ${totalEdicion.toFixed(2)}`;
        }

        // CORREGIDO: Función para guardar edición
function guardarEdicionPedido() {
    //console.log('💾 Intentando guardar cambios...');
    //console.log('📋 pedidoEditando:', pedidoEditando);
   // console.log('📦 itemsEdicion:', itemsEdicion);
   // console.log('💰 totalEdicion:', totalEdicion);
    
    // CORRECCIÓN: Verificación más robusta
    if (!pedidoEditando || pedidoEditando === null || pedidoEditando === 0) {
        alert('❌ Error: No hay pedido seleccionado para editar (pedidoEditando es null)');
        console.error('💥 pedidoEditando es null o inválido');
        return;
    }
    
    if (!itemsEdicion || itemsEdicion.length === 0) {
        if (!confirm('El pedido quedará vacío. ¿Está seguro de continuar?')) {
            return;
        }
    }

    const datos = {
        pedido_id: pedidoEditando, // CORRECCIÓN: Usar pedidoEditando directamente
        items: itemsEdicion,
        total: totalEdicion
    };

    //console.log('📤 Enviando datos:', datos);

    fetch('pedidos/editar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(datos)
    })
    .then(response => {
        //console.log('📡 Respuesta del servidor - Status:', response.status);
        return response.json();
    })
    .then(data => {
       // console.log('📊 Respuesta recibida:', data);
        if (data.success) {
            alert('✅ Pedido actualizado correctamente');
            cerrarModalEditarPedido();
            cargarPedidos(); // Recargar lista de pedidos
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('💥 Error en la petición:', error);
        alert('❌ Error al actualizar pedido: ' + error.message);
    });
}

        // CORREGIDO: Función para cerrar modal
function cerrarModalEditarPedido() {
    //console.log('🚪 Cerrando modal de edición, pedidoEditando era:', pedidoEditando);
    const modal = document.getElementById('modalEditarPedido');
    if (modal) {
        modal.remove();
    }
    // NO resetear pedidoEditando aquí para debugging
    itemsEdicion = [];
    totalEdicion = 0;
    //console.log('✅ Modal cerrado, pedidoEditando ahora:', pedidoEditando);
}
    </script>
</body>
</html>