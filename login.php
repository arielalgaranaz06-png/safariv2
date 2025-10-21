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
    <title>Login - Safari Restaurant</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            z-index: 0;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .particle {
            position: absolute;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            animation: float 15s infinite;
            z-index: 1;
        }

        .particle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 70%;
            left: 80%;
            animation-delay: 2s;
        }

        .particle:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 40%;
            left: 5%;
            animation-delay: 4s;
        }

        .particle:nth-child(4) {
            width: 70px;
            height: 70px;
            top: 20%;
            left: 85%;
            animation-delay: 1s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.3;
            }
            50% {
                transform: translateY(-30px) rotate(180deg);
                opacity: 0.6;
            }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 50px 40px;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3),
                        0 0 100px rgba(102, 126, 234, 0.2);
            animation: slideDown 0.6s ease-out;
            border: 1px solid rgba(255,255,255,0.3);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            font-size: 4rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .login-title {
            text-align: center;
            color: #2c3e50;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .login-subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 40px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: #667eea;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.2rem;
            z-index: 2;
        }

        .form-control {
            height: 60px;
            font-size: 1.05rem;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding-left: 55px;
            transition: all 0.3s;
            background: #f8f9fa;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
            transform: translateY(-2px);
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #95a5a6;
            transition: color 0.3s;
            z-index: 2;
            padding: 10px;
            touch-action: manipulation;
        }

        .password-toggle:active {
            color: #667eea;
            transform: translateY(-50%) scale(0.9);
        }

        .btn-login {
            height: 60px;
            font-size: 1.2rem;
            font-weight: 700;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            touch-action: manipulation;
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 18px;
            margin-bottom: 25px;
            animation: shake 0.5s;
            font-weight: 500;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
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

        /* RESPONSIVE MÓVIL */
        @media (max-width: 576px) {
            body {
                padding: 15px;
            }

            .login-card {
                padding: 30px 20px;
                border-radius: 20px;
            }

            .logo-icon {
                font-size: 3rem;
            }

            .login-title {
                font-size: 1.8rem;
                letter-spacing: 1px;
            }

            .login-subtitle {
                font-size: 0.95rem;
                margin-bottom: 30px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-label {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }

            .form-control {
                height: 50px;
                font-size: 1rem;
                padding-left: 50px;
                border-radius: 12px;
            }

            .input-icon {
                left: 15px;
                font-size: 1.1rem;
            }

            .password-toggle {
                right: 15px;
                padding: 15px;
            }

            .btn-login {
                height: 50px;
                font-size: 1rem;
                border-radius: 12px;
            }

            .alert {
                padding: 15px;
                font-size: 0.9rem;
            }

            .particle {
                display: none;
            }
        }

        /* RESPONSIVE HORIZONTAL */
        @media (max-width: 900px) and (orientation: landscape) {
            body {
                padding: 10px;
            }

            .login-card {
                padding: 25px 30px;
                border-radius: 20px;
                max-height: 90vh;
                overflow-y: auto;
            }

            .logo-icon {
                font-size: 2.5rem;
            }

            .login-title {
                font-size: 1.8rem;
                margin-bottom: 5px;
            }

            .login-subtitle {
                font-size: 0.9rem;
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-label {
                font-size: 0.85rem;
                margin-bottom: 6px;
            }

            .form-control {
                height: 45px;
                font-size: 0.95rem;
                border-radius: 10px;
            }

            .input-icon {
                font-size: 1rem;
            }

            .btn-login {
                height: 45px;
                font-size: 0.95rem;
            }

            .particle {
                display: none;
            }
        }

        /* TABLETS */
        @media (min-width: 577px) and (max-width: 768px) {
            .login-container {
                max-width: 450px;
            }

            .login-card {
                padding: 40px 30px;
            }

            .logo-icon {
                font-size: 3.5rem;
            }

            .login-title {
                font-size: 2.2rem;
            }

            .form-control {
                height: 55px;
            }

            .btn-login {
                height: 55px;
                font-size: 1.1rem;
            }
        }

        /* DESKTOP GRANDE */
        @media (min-width: 1200px) {
            .login-container {
                max-width: 520px;
            }

            .login-card {
                padding: 60px 50px;
            }

            .btn-login:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
            }

            .password-toggle:hover {
                color: #667eea;
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
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-icon">🦁</div>
                <h1 class="login-title">SAFARI</h1>
                <p class="login-subtitle">Sistema de Gestión</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i>
                        Usuario
                    </label>
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
                    <label class="form-label">
                        <i class="fas fa-lock"></i>
                        Contraseña
                    </label>
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