<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND password = ? AND activo = 1");
    $stmt->execute([$usuario, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'];

        // Redirigir según el rol
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
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Safari</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            width: 100%;
            max-width: 450px;
        }
        .login-title {
            text-align: center;
            margin-bottom: 40px;
            color: #667eea;
            font-weight: bold;
            font-size: 2rem;
        }
        .form-control {
            height: 55px;
            font-size: 1.1rem;
        }
        .btn-login {
            height: 55px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .info-text {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .info-text small {
            display: block;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="login-title">🍖 SAFARI</h2>
        <p class="text-center text-muted mb-4">Sistema de Gestión</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Error:</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-4">
                <label class="form-label fw-bold">Usuario</label>
                <input type="text" name="usuario" class="form-control" required autofocus placeholder="Ingrese su usuario">
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Contraseña</label>
                <input type="password" name="password" class="form-control" required placeholder="Ingrese su contraseña">
            </div>
            <button type="submit" class="btn btn-primary btn-login w-100">Iniciar Sesión</button>
        </form>
        
        <div class="info-text text-center">
            <small class="text-muted fw-bold">Usuarios de prueba:</small>
            <small><strong>Garzón:</strong> garzon1 / prueba123</small>
            <small><strong>Cajero:</strong> cajero1 / prueba456</small>
            <small><strong>Admin:</strong> admin1 / admin789</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>