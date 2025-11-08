<?php
// DEJA TUS DATOS ORIGINALES AQUÍ - NO LOS CAMBIES
$host = 'localhost';
$db   = 'safari'; // tu base de datos real
$user = 'root';     // tu usuario real  
$pass = '';         // tu password real
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // SOLO AGREGA ESTA LÍNEA - NO CAMBIES LO DEMÁS
    $pdo->exec("SET time_zone = '-04:00'");
    
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}
?>