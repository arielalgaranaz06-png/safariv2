<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrador - Safari</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Panel del Administrador</h1>
            <div>
                <span class="me-3">Usuario: <?php echo $_SESSION['nombre']; ?></span>
                <a href="../logout.php" class="btn btn-danger">Cerrar Sesión</a>
            </div>
        </div>
        <div class="alert alert-info">
            Módulo de administrador en desarrollo...
        </div>
    </div>
</body>
</html>