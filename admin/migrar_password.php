<?php
/**
 * Script para migrar contraseñas en texto plano a password_hash()
 * EJECUTAR SOLO UNA VEZ después de actualizar el sistema
 * 
 * IMPORTANTE: Este archivo debe eliminarse después de ejecutarlo
 */

require_once 'db.php';

// Contraseña de seguridad para ejecutar el script
$security_key = "MIGRAR_SAFARI_2024"; // Cambiar esto por seguridad

if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die("❌ Acceso denegado. Proporcione la clave de seguridad correcta.");
}

echo "<h2>🔐 Migración de Contraseñas - Safari</h2>";
echo "<hr>";

try {
    // Obtener todos los usuarios
    $stmt = $pdo->query("SELECT id, usuario, password FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($usuarios)) {
        echo "<p>⚠️ No hay usuarios en la base de datos.</p>";
        exit;
    }
    
    echo "<p>📊 Total de usuarios encontrados: <strong>" . count($usuarios) . "</strong></p>";
    echo "<hr>";
    
    $migrados = 0;
    $ya_hash = 0;
    $errores = 0;
    
    foreach ($usuarios as $usuario) {
        echo "<p>Procesando: <strong>" . htmlspecialchars($usuario['usuario']) . "</strong> (ID: {$usuario['id']})... ";
        
        // Verificar si ya está hasheada (password_hash genera strings de 60 caracteres)
        if (strlen($usuario['password']) === 60 && substr($usuario['password'], 0, 4) === '$2y$') {
            echo "<span style='color: blue;'>✓ Ya tiene hash</span></p>";
            $ya_hash++;
            continue;
        }
        
        // Convertir a hash
        try {
            $password_hash = password_hash($usuario['password'], PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $usuario['id']]);
            
            echo "<span style='color: green;'>✅ Migrado exitosamente</span></p>";
            $migrados++;
            
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ Error: " . $e->getMessage() . "</span></p>";
            $errores++;
        }
    }
    
    echo "<hr>";
    echo "<h3>📋 Resumen:</h3>";
    echo "<ul>";
    echo "<li>✅ Migrados: <strong style='color: green;'>$migrados</strong></li>";
    echo "<li>ℹ️ Ya tenían hash: <strong style='color: blue;'>$ya_hash</strong></li>";
    echo "<li>❌ Errores: <strong style='color: red;'>$errores</strong></li>";
    echo "</ul>";
    
    if ($migrados > 0) {
        echo "<hr>";
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; border-left: 5px solid #28a745;'>";
        echo "<h4 style='color: #155724;'>✅ Migración completada exitosamente</h4>";
        echo "<p style='color: #155724;'>Todos los usuarios ahora tienen contraseñas hasheadas de forma segura.</p>";
        echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
        echo "<strong>⚠️ IMPORTANTE:</strong> Por seguridad, elimine este archivo (migrar_passwords.php) del servidor inmediatamente.";
        echo "</p>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error de base de datos: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><small>Script ejecutado el: " . date('Y-m-d H:i:s') . "</small></p>";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración de Contraseñas</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2 { color: #2c3e50; }
        p { line-height: 1.6; }
        hr { margin: 20px 0; border: none; border-top: 2px solid #ddd; }
    </style>
</head>
<body>
</body>
</html>