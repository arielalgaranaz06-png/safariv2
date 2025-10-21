<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Verificar que sea admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'crear':
            // Validar datos
            $nombre = trim($_POST['nombre'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $rol = $_POST['rol'] ?? '';
            $activo = $_POST['activo'] ?? 1;
            
            if (empty($nombre) || empty($usuario) || empty($password) || empty($rol)) {
                throw new Exception('Todos los campos son obligatorios');
            }
            
            // Validar que el usuario no exista
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                throw new Exception('El nombre de usuario ya existe');
            }
            
            // Validar rol
            $roles_validos = ['admin', 'cajero', 'garzon'];
            if (!in_array($rol, $roles_validos)) {
                throw new Exception('Rol inválido');
            }
            
            // Hash de la contraseña (importante para seguridad)
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar usuario
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nombre, usuario, password, rol, activo, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            if (!$stmt->execute([$nombre, $usuario, $password_hash, $rol, $activo])) {
                throw new Exception('Error al insertar el usuario en la base de datos');
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Usuario creado exitosamente'
            ]);
            break;
            
        case 'editar':
            $id = $_POST['id'] ?? 0;
            $nombre = trim($_POST['nombre'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $rol = $_POST['rol'] ?? '';
            $activo = $_POST['activo'] ?? 1;
            
            if (empty($id) || empty($nombre) || empty($usuario) || empty($rol)) {
                throw new Exception('Datos incompletos');
            }
            
            // Verificar que el usuario existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Usuario no encontrado');
            }
            
            // Verificar que el nombre de usuario no esté en uso por otro usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $stmt->execute([$usuario, $id]);
            if ($stmt->fetch()) {
                throw new Exception('El nombre de usuario ya está en uso por otro usuario');
            }
            
            // Validar rol
            $roles_validos = ['admin', 'cajero', 'garzon'];
            if (!in_array($rol, $roles_validos)) {
                throw new Exception('Rol inválido');
            }
            
            // Actualizar usuario
            if (!empty($password)) {
                // Si se proporciona nueva contraseña, actualizarla con hash
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, usuario = ?, password = ?, rol = ?, activo = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $usuario, $password_hash, $rol, $activo, $id]);
            } else {
                // Si no, mantener la contraseña actual
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, usuario = ?, rol = ?, activo = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $usuario, $rol, $activo, $id]);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Usuario actualizado exitosamente'
            ]);
            break;
            
        case 'eliminar':
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                throw new Exception('ID de usuario no proporcionado');
            }
            
            // Verificar que no se elimine a sí mismo
            if ($id == $_SESSION['usuario_id']) {
                throw new Exception('No puede eliminarse a sí mismo');
            }
            
            // Verificar que el usuario existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Usuario no encontrado');
            }
            
            // Verificar que no tenga pedidos asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE usuario_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['total'] > 0) {
                throw new Exception('No se puede eliminar: el usuario tiene pedidos asociados');
            }
            
            // Eliminar usuario
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Usuario eliminado exitosamente'
            ]);
            break;
            
        case 'toggle_estado':
            // Acción adicional para activar/desactivar usuario sin eliminarlo
            $id = $_POST['id'] ?? 0;
            $nuevo_estado = $_POST['estado'] ?? 0;
            
            if (empty($id)) {
                throw new Exception('ID de usuario no proporcionado');
            }
            
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Estado actualizado exitosamente'
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>