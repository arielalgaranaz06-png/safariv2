<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['usuario_id'])) {
    switch($_SESSION['rol']) {
        case 'garzon':
            header('Location: garzon/garzon.php');
            break;
        case 'cajero':
            header('Location: cajero/cajero.php');
            break;
        case 'admin':
            header('Location: admin/admin.php');
            break;
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $error = "Por favor complete todos los campos";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['login_time'] = time();

                $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                switch($user['rol']) {
                    case 'garzon':
                        header('Location: garzon/garzon.php');
                        break;
                    case 'cajero':
                        header('Location: cajero/cajero.php');
                        break;
                    case 'admin':
                        header('Location: admin/admin.php');
                        break;
                }
                exit;
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado o inactivo";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Acceso - Safari Churrasquería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Fondo con textura sutil */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                linear-gradient(135deg, rgba(139, 69, 19, 0.05) 0%, rgba(0, 0, 0, 0.3) 100%),
                url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="%23ffffff" fill-opacity="0.01" width="2" height="2"/></svg>');
            background-size: cover, 5px 5px;
            z-index: 0;
        }

        /* Elementos decorativos minimalistas */
        .deco-circle {
            position: absolute;
            border: 1px solid rgba(139, 69, 19, 0.1);
            border-radius: 50%;
            z-index: 1;
        }

        .deco-circle:nth-child(1) {
            width: 400px;
            height: 400px;
            top: -200px;
            right: -200px;
        }

        .deco-circle:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -150px;
            left: -150px;
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 60px 50px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(139, 69, 19, 0.1);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 50px;
        }

        .logo-container img {
            max-width: 180px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.1));
        }

        .login-title {
            text-align: center;
            color: #2c2c2c;
            font-weight: 300;
            font-size: 1.5rem;
            margin-bottom: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .login-subtitle {
            text-align: center;
            color: #8B4513;
            font-size: 0.9rem;
            margin-bottom: 40px;
            font-weight: 400;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 500;
            color: #2c2c2c;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8B4513;
            font-size: 1rem;
            z-index: 2;
        }

        .form-control {
            height: 50px;
            font-size: 0.95rem;
            border: 1px solid #d4d4d4;
            border-radius: 4px;
            padding-left: 45px;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #2c2c2c;
        }

        .form-control:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
            background: #ffffff;
        }

        .form-control::placeholder {
            color: #999;
            font-size: 0.9rem;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
            z-index: 2;
            padding: 10px;
            touch-action: manipulation;
        }

        .password-toggle:active,
        .password-toggle:hover {
            color: #8B4513;
        }

        .btn-login {
            height: 50px;
            font-size: 0.95rem;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            background: #8B4513;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            touch-action: manipulation;
        }

        .btn-login:hover {
            background: #6d3410;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .alert {
            border-radius: 4px;
            border: none;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            border-left: 4px solid #d32f2f;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }

        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading .spinner {
            display: inline-block;
        }

        /* Footer profesional */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 0.8rem;
        }

        /* RESPONSIVE MÓVIL */
        @media (max-width: 576px) {
            .login-container {
                padding: 15px;
            }

            .login-card {
                padding: 40px 30px;
            }

            .logo-container {
                margin-bottom: 40px;
            }

            .logo-container img {
                max-width: 150px;
            }

            .login-title {
                font-size: 1.3rem;
            }

            .login-subtitle {
                font-size: 0.85rem;
                margin-bottom: 30px;
            }

            .form-control {
                height: 48px;
                font-size: 0.9rem;
            }

            .btn-login {
                height: 48px;
                font-size: 0.9rem;
            }

            .deco-circle {
                display: none;
            }
        }

        /* RESPONSIVE HORIZONTAL */
        @media (max-width: 900px) and (orientation: landscape) {
            .login-card {
                padding: 30px 40px;
                max-height: 90vh;
                overflow-y: auto;
            }

            .logo-container {
                margin-bottom: 25px;
            }

            .logo-container img {
                max-width: 120px;
            }

            .login-title {
                font-size: 1.2rem;
                margin-bottom: 5px;
            }

            .login-subtitle {
                font-size: 0.8rem;
                margin-bottom: 25px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-control,
            .btn-login {
                height: 45px;
            }

            .deco-circle {
                display: none;
            }
        }

        /* TABLETS */
        @media (min-width: 577px) and (max-width: 768px) {
            .login-card {
                padding: 50px 40px;
            }
        }

        /* Fix para iOS */
        @supports (-webkit-touch-callout: none) {
            .form-control {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="deco-circle"></div>
    <div class="deco-circle"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <img src="media/logo.png" alt="Safari Churrasquería">
                <h1 class="login-title">Acceso al Sistema</h1>
                <p class="login-subtitle">Sistema de Gestión</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               name="usuario" 
                               class="form-control" 
                               required 
                               autofocus 
                               placeholder="Ingrese su usuario"
                               autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               name="password" 
                               id="password"
                               class="form-control" 
                               required 
                               placeholder="Ingrese su contraseña"
                               autocomplete="current-password">
                        <i class="fas fa-eye password-toggle" 
                           id="togglePassword"
                           onclick="togglePasswordVisibility()"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100" id="btnLogin">
                    <span id="btnText">Iniciar Sesión</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <div class="login-footer">
                © 2025 Safari Churrasquería
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        let formSubmitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
            const btn = document.getElementById('btnLogin');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Prevenir doble tap zoom en iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 