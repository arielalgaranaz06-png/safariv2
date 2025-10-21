<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'garzon') {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM mesas ORDER BY numero");
$mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$productos_por_categoria = [
    'plato_principal' => [],
    'acompanamiento' => [],
    'bebida' => []
];

foreach ($productos as $producto) {
    $productos_por_categoria[$producto['categoria']][] = $producto;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Garzón - Safari</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { 
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body { 
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            overflow-x: hidden;
        }
        
        .header-bar {
            background: linear-gradient(135deg, #667eea 0%, #085a91ff 100%);
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-bar h1 {
            font-size: 1.2rem;
            margin: 0;
        }
        
        .header-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        
        .turno-info {
            background: #ffeaa7;
            color: #2d3436;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.8rem;
            border: 2px solid #fdcb6e;
            white-space: nowrap;
        }
        
        .turno-info.noche {
            background: #a29bfe;
            border-color: #6c5ce7;
        }
        
        .main-container {
            display: flex;
            height: calc(100vh - 60px);
            overflow: hidden;
        }
        
        /* PANEL IZQUIERDO - MESAS */
        .mesas-panel {
            width: 280px;
            background: white;
            border-right: 3px solid #ddd;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .mesas-header {
            padding: 15px;
            background: #2c3e50;
            color: white;
            font-size: 1.1rem;
            font-weight: bold;
            text-align: center;
        }
        
        .mesas-grid {
            padding: 15px;
            overflow-y: auto;
            flex: 1;
        }
        
        .mesa-item {
            width: 70px;
            height: 70px;
            margin: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            border: 3px solid transparent;
            touch-action: manipulation;
        }
        
        .mesa-libre {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .mesa-ocupada {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        
        .mesa-item:active {
            transform: scale(0.95);
        }
        
        .mesa-item.selected {
            border-color: #ff0000ff;
            box-shadow: 0 0 15px rgba(243, 130, 130, 0.5);
            transform: scale(1.1);
        }
        
        /* PANEL CENTRAL - PRODUCTOS */
        .productos-panel {
            flex: 1;
            background: #f8f9fa;
            overflow-y: auto;
            padding: 15px;
        }
        
        .categorias-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            position: sticky;
            top: 0;
            background: #f8f9fa;
            padding: 10px 0;
            z-index: 10;
            border-bottom: 2px solid #ddd;
        }
        
        .categoria-tab {
            flex: 1;
            padding: 12px 15px;
            border: none;
            background: white;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            touch-action: manipulation;
        }
        
        .categoria-tab:active {
            transform: translateY(1px);
        }
        
        .categoria-tab.active {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .categoria-tab.plato-principal { border: 3px solid #3498db; color: #3498db; }
        .categoria-tab.plato-principal.active { background: #3498db3e; }
        
        .categoria-tab.acompanamiento { border: 3px solid #3498db; color: #3498db; }
        .categoria-tab.acompanamiento.active { background: #3498db3e; }
        
        .categoria-tab.bebida { border: 3px solid #3498db; color: #3498db; }
        .categoria-tab.bebida.active { background: #3498db3e; }
        
        .categoria-content {
            display: none;
        }
        
        .categoria-content.active {
            display: block;
        }
        
        .producto-item {
            background: white;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            touch-action: manipulation;
        }
        
        .producto-item:active {
            transform: scale(0.98);
            background: #f0f0f0;
        }
        
        .producto-nombre {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
            padding-right: 10px;
        }
        
        .producto-precio {
            font-size: 1.1rem;
            font-weight: bold;
            color: #27ae60;
            background: #d4edda;
            padding: 6px 12px;
            border-radius: 15px;
            white-space: nowrap;
        }
        
        /* PANEL DERECHO - RESUMEN */
        .resumen-panel {
            width: 350px;
            background: white;
            border-left: 3px solid #ddd;
            display: flex;
            flex-direction: column;
            box-shadow: -2px 0 10px rgba(0,0,0,0.05);
        }
        
        .resumen-header {
            padding: 15px;
            background: #9dc8e6ff;
            color: white;
            font-size: 1.1rem;
            font-weight: bold;
            text-align: center;
        }
        
        .resumen-body {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
        }
        
        .mesa-info {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: bold;
            color: #1565c0;
            margin-bottom: 15px;
        }
        
        .info-turno-pedido {
            background: #e3f2fd;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.85rem;
            color: #1565c0;
            margin-bottom: 10px;
            border-left: 4px solid #2196f3;
        }
        
        .pedido-item {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        
        .pedido-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            gap: 10px;
        }
        
        .pedido-item-nombre {
            font-weight: bold;
            font-size: 0.95rem;
            color: #2c3e50;
            flex: 1;
        }
        
        .pedido-item-subtotal {
            font-weight: bold;
            color: #27ae60;
            white-space: nowrap;
        }
        
        .cantidad-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .cantidad-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 6px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            touch-action: manipulation;
        }
        
        .cantidad-btn.menos {
            background: #e74c3c;
            color: white;
        }
        
        .cantidad-btn.mas {
            background: #27ae60;
            color: white;
        }
        
        .cantidad-btn:active {
            transform: scale(0.9);
        }
        
        .cantidad-display {
            font-size: 1.2rem;
            font-weight: bold;
            min-width: 30px;
            text-align: center;
        }
        
        .resumen-total {
            padding: 15px;
            background: #f8f9fa;
            border-top: 3px solid #dee2e6;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .total-label {
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }
        
        .btn-crear-pedido {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            background: #27ae60;
            color: white;
            touch-action: manipulation;
        }
        
        .btn-crear-pedido:active:not(:disabled) {
            transform: scale(0.98);
        }
        
        .btn-crear-pedido:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Toggle para resumen en móvil */
        .resumen-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #27ae60;
            color: white;
            border: none;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 999;
            cursor: pointer;
            touch-action: manipulation;
        }

        .resumen-toggle:active {
            transform: scale(0.95);
        }

        .resumen-toggle .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            border-radius: 50%;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        /* RESPONSIVE MÓVIL - VERTICAL */
        @media (max-width: 768px) and (orientation: portrait) {
            .header-bar h1 {
                font-size: 1rem;
            }

            .header-info {
                gap: 8px;
            }

            .header-info span {
                display: none;
            }

            .btn-danger {
                padding: 4px 10px;
                font-size: 0.8rem;
            }
            
            .main-container {
                flex-direction: column;
                height: calc(100vh - 55px);
            }
            
            .mesas-panel {
                width: 100%;
                border-right: none;
                border-bottom: 2px solid #ddd;
                height: auto;
                max-height: 200px;
                min-height: 200px;
            }

            .mesas-header {
                padding: 10px;
                font-size: 0.95rem;
            }

            .mesas-grid {
                padding: 15px;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }

            .mesa-item {
                width: 60px;
                height: 60px;
                margin: 5px;
                font-size: 1rem;
            }
            
            .resumen-panel {
                position: fixed;
                bottom: -100%;
                left: 0;
                width: 100%;
                height: 70vh;
                border-left: none;
                border-top: 3px solid #ddd;
                z-index: 998;
                transition: bottom 0.3s ease-in-out;
            }

            .resumen-panel.show {
                bottom: 0;
            }

            .resumen-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .productos-panel {
                flex: 1;
                padding: 10px;
                overflow-y: auto;
                padding-bottom: 100px;
            }

            .categorias-tabs {
                gap: 6px;
                padding: 8px 0;
            }

            .categoria-tab {
                padding: 10px 8px;
                font-size: 0.8rem;
            }

            .producto-item {
                padding: 12px;
                margin-bottom: 10px;
            }

            .producto-nombre {
                font-size: 0.9rem;
            }

            .producto-precio {
                font-size: 0.95rem;
                padding: 5px 10px;
            }
        }
        
        /* RESPONSIVE MÓVIL - HORIZONTAL */
        @media (max-width: 900px) and (orientation: landscape) {
            .header-bar {
                padding: 8px 12px;
            }

            .header-bar h1 {
                font-size: 0.95rem;
            }

            .header-info {
                font-size: 0.75rem;
                gap: 8px;
            }

            .turno-info {
                padding: 4px 8px;
                font-size: 0.7rem;
            }

            .btn-danger {
                padding: 3px 8px;
                font-size: 0.75rem;
            }
            
            .main-container {
                height: calc(100vh - 45px);
            }

            .mesas-panel {
                width: 200px;
            }

            .mesas-header {
                padding: 8px;
                font-size: 0.85rem;
            }

            .mesas-grid {
                padding: 8px;
            }

            .mesa-item {
                width: 50px;
                height: 50px;
                margin: 5px;
                font-size: 0.9rem;
                border-width: 2px;
            }

            .resumen-panel {
                width: 280px;
            }

            .resumen-header {
                padding: 10px;
                font-size: 0.95rem;
            }

            .resumen-body {
                padding: 10px;
            }

            .mesa-info {
                padding: 8px;
                font-size: 0.95rem;
                margin-bottom: 10px;
            }

            .info-turno-pedido {
                padding: 6px;
                font-size: 0.75rem;
            }

            .pedido-item {
                padding: 10px;
                margin-bottom: 8px;
            }

            .pedido-item-nombre {
                font-size: 0.85rem;
            }

            .pedido-item-subtotal {
                font-size: 0.85rem;
            }

            .cantidad-btn {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .cantidad-display {
                font-size: 1rem;
            }

            .resumen-total {
                padding: 10px;
            }

            .total-label {
                font-size: 1.1rem;
            }

            .total-amount {
                font-size: 1.2rem;
            }

            .btn-crear-pedido {
                padding: 12px;
                font-size: 1rem;
            }

            .productos-panel {
                padding: 10px;
            }

            .categorias-tabs {
                gap: 6px;
                margin-bottom: 10px;
                padding: 6px 0;
            }

            .categoria-tab {
                padding: 8px 10px;
                font-size: 0.8rem;
                border-width: 2px;
            }

            .producto-item {
                padding: 10px;
                margin-bottom: 8px;
            }

            .producto-nombre {
                font-size: 0.85rem;
            }

            .producto-precio {
                font-size: 0.9rem;
                padding: 4px 10px;
            }
        }

        /* Overlay para cerrar resumen en móvil */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 997;
        }

        .overlay.show {
            display: block;
        }

        /* Scrollbar personalizado */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>🍖 Safari - Garzón</h1>
        <div class="header-info">
            <div id="turnoDisplay" class="turno-info">Cargando...</div>
            <span><?php echo $_SESSION['nombre']; ?></span>
            <a href="../logout.php" class="btn btn-danger btn-sm">Salir</a>
        </div>
    </div>

    <div class="overlay" id="overlay" onclick="toggleResumen()"></div>

    <button class="resumen-toggle" id="resumenToggle" onclick="toggleResumen()">
        🛒
        <span class="badge" id="badgeCount">0</span>
    </button>

    <div class="main-container">
        <!-- PANEL MESAS -->
        <div class="mesas-panel">
            <div class="mesas-header">MESAS</div>
            <div class="mesas-grid">
                <?php foreach ($mesas as $mesa): ?>
                    <div class="mesa-item <?php echo $mesa['estado'] == 'libre' ? 'mesa-libre' : 'mesa-ocupada'; ?>"
                         data-mesa-id="<?php echo $mesa['id']; ?>"
                         data-mesa-numero="<?php echo $mesa['numero']; ?>"
                         onclick="seleccionarMesa(this)">
                        <?php echo $mesa['numero']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PANEL PRODUCTOS -->
        <div class="productos-panel">
            <div class="categorias-tabs">
                <button class="categoria-tab plato-principal active" onclick="cambiarCategoria('plato_principal', this)">
                    PLATOS
                </button>
                <button class="categoria-tab acompanamiento" onclick="cambiarCategoria('acompanamiento', this)">
                    ACOMPAÑAMIENTOS
                </button>
                <button class="categoria-tab bebida" onclick="cambiarCategoria('bebida', this)">
                    BEBIDAS
                </button>
            </div>

            <!-- PLATOS PRINCIPALES -->
            <div class="categoria-content active" id="plato_principal">
                <?php foreach ($productos_por_categoria['plato_principal'] as $prod): ?>
                    <div class="producto-item"
                         data-producto-id="<?php echo $prod['id']; ?>"
                         data-producto-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                         data-producto-precio="<?php echo $prod['precio']; ?>"
                         onclick="agregarProducto(this)">
                        <span class="producto-nombre"><?php echo $prod['nombre']; ?></span>
                        <span class="producto-precio">Bs. <?php echo number_format($prod['precio'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ACOMPAÑAMIENTOS -->
            <div class="categoria-content" id="acompanamiento">
                <?php foreach ($productos_por_categoria['acompanamiento'] as $prod): ?>
                    <div class="producto-item"
                         data-producto-id="<?php echo $prod['id']; ?>"
                         data-producto-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                         data-producto-precio="<?php echo $prod['precio']; ?>"
                         onclick="agregarProducto(this)">
                        <span class="producto-nombre"><?php echo $prod['nombre']; ?></span>
                        <span class="producto-precio">Bs. <?php echo number_format($prod['precio'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- BEBIDAS -->
            <div class="categoria-content" id="bebida">
                <?php foreach ($productos_por_categoria['bebida'] as $prod): ?>
                    <div class="producto-item"
                         data-producto-id="<?php echo $prod['id']; ?>"
                         data-producto-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                         data-producto-precio="<?php echo $prod['precio']; ?>"
                         onclick="agregarProducto(this)">
                        <span class="producto-nombre"><?php echo $prod['nombre']; ?></span>
                        <span class="producto-precio">Bs. <?php echo number_format($prod['precio'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PANEL RESUMEN -->
        <div class="resumen-panel" id="resumenPanel">
            <div class="resumen-header">PEDIDO ACTUAL</div>
            <div class="resumen-body">
                <div class="mesa-info" id="mesaInfo">Seleccione una mesa</div>
                <div id="listaPedido"></div>
            </div>
            <div class="resumen-total">
                <div class="total-row">
                    <span class="total-label">TOTAL:</span>
                    <span class="total-amount" id="totalPedido">Bs. 0.00</span>
                </div>
                <button class="btn-crear-pedido" id="btnCrearPedido" onclick="crearPedido()" disabled>
                    CREAR PEDIDO
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let mesaSeleccionada = null;
        let productosEnPedido = {};
        let turnoActual = '';

        function toggleResumen() {
            const panel = document.getElementById('resumenPanel');
            const overlay = document.getElementById('overlay');
            panel.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        function actualizarBadge() {
            const count = Object.keys(productosEnPedido).length;
            const badge = document.getElementById('badgeCount');
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }

        function mostrarInfoTurno() {
            fetch('obtener_turno_actual.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        turnoActual = data.turno;
                        const turnoDisplay = document.getElementById('turnoDisplay');
                        turnoDisplay.textContent = `Turno: ${data.turno === 'mañana' ? 'MEDIODÍA' : 'NOCHE'}`;
                        turnoDisplay.className = `turno-info ${data.turno === 'noche' ? 'noche' : ''}`;
                    }
                })
                .catch(error => {
                    console.error('Error al obtener turno:', error);
                });
        }

        function cambiarCategoria(categoria, btn) {
            document.querySelectorAll('.categoria-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.categoria-content').forEach(content => content.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById(categoria).classList.add('active');
        }

        function seleccionarMesa(elemento) {
            document.querySelectorAll('.mesa-item').forEach(m => m.classList.remove('selected'));
            elemento.classList.add('selected');
            
            mesaSeleccionada = {
                id: elemento.dataset.mesaId,
                numero: elemento.dataset.mesaNumero
            };

            document.getElementById('mesaInfo').textContent = `Mesa ${mesaSeleccionada.numero}`;
            actualizarBotonCrear();
        }

        function agregarProducto(elemento) {
            const id = elemento.dataset.productoId;
            const nombre = elemento.dataset.productoNombre;
            const precio = parseFloat(elemento.dataset.productoPrecio);

            if (!productosEnPedido[id]) {
                productosEnPedido[id] = {
                    nombre: nombre,
                    precio: precio,
                    cantidad: 0
                };
            }

            productosEnPedido[id].cantidad++;
            actualizarResumen();
            actualizarBadge();

            // Feedback visual
            elemento.style.background = '#d4edda';
            setTimeout(() => {
                elemento.style.background = 'white';
            }, 200);
        }

        function cambiarCantidad(id, cambio) {
            if (productosEnPedido[id]) {
                productosEnPedido[id].cantidad += cambio;
                if (productosEnPedido[id].cantidad <= 0) {
                    delete productosEnPedido[id];
                }
                actualizarResumen();
                actualizarBadge();
            }
        }

        function actualizarResumen() {
            const lista = document.getElementById('listaPedido');
            lista.innerHTML = '';
            let total = 0;

            const turnoInfo = document.createElement('div');
            turnoInfo.className = 'info-turno-pedido';
            turnoInfo.textContent = `Turno: ${turnoActual === 'mañana' ? 'MEDIODÍA' : 'NOCHE'}`;
            lista.appendChild(turnoInfo);

            for (const [id, item] of Object.entries(productosEnPedido)) {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;

                const itemDiv = document.createElement('div');
                itemDiv.className = 'pedido-item';
                itemDiv.innerHTML = `
                    <div class="pedido-item-header">
                        <span class="pedido-item-nombre">${item.nombre}</span>
                        <span class="pedido-item-subtotal">Bs. ${subtotal.toFixed(2)}</span>
                    </div>
                    <div class="cantidad-controls">
                        <button class="cantidad-btn menos" onclick="cambiarCantidad('${id}', -1)">-</button>
                        <span class="cantidad-display">${item.cantidad}</span>
                        <button class="cantidad-btn mas" onclick="cambiarCantidad('${id}', 1)">+</button>
                    </div>
                `;
                lista.appendChild(itemDiv);
            }

            document.getElementById('totalPedido').textContent = `Bs. ${total.toFixed(2)}`;
            actualizarBotonCrear();
        }

        function actualizarBotonCrear() {
            const btn = document.getElementById('btnCrearPedido');
            const tieneProductos = Object.keys(productosEnPedido).length > 0;
            btn.disabled = !mesaSeleccionada || !tieneProductos;
        }

        function crearPedido() {
            if (!mesaSeleccionada || Object.keys(productosEnPedido).length === 0) {
                alert('Debe seleccionar una mesa y agregar productos');
                return;
            }

            const formData = new FormData();
            formData.append('mesa_id', mesaSeleccionada.id);
            formData.append('productos', JSON.stringify(productosEnPedido));

            fetch('crear_pedido.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const turnoNombre = data.turno === 'mañana' ? 'MEDIODÍA' : 'NOCHE';
                    const cajaInfo = data.caja_asociada ? ' (Asociado a caja activa)' : '';
                    alert(`✅ Pedido creado exitosamente\nTurno: ${turnoNombre}${cajaInfo}`);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error);
            });
        }

        // Prevenir zoom en doble tap en iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            mostrarInfoTurno();
            actualizarBadge();
        });
    </script>
</body>
</html>