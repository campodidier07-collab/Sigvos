<?php
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../views/auth/login.php');
    exit;
}

require_once $rootPath . '/config/database.php';

$db         = (new Database())->conectar();
$id_usuario = (int)$_SESSION['id_usuario'];
$esAdmin    = (int)$_SESSION['id_rol'] === 1;
$redirect   = $esAdmin ? '../views/dashboards/admin.php#perfil' : '../views/dashboards/trabajador.php#perfil';
$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'actualizar_datos':
        actualizarDatos($db, $id_usuario, $redirect);
        break;
    case 'cambiar_password':
        cambiarPassword($db, $id_usuario, $redirect);
        break;
    case 'subir_foto':
        subirFoto($db, $id_usuario, $rootPath, $redirect);
        break;
    default:
        header('Location: ' . $redirect); exit;
}

// ── Actualizar nombre, correo, teléfono ───────────────────────────────────
function actualizarDatos($db, $id, $redirect): void
{
    $nombre   = trim($_POST['nombre']   ?? '');
    $correo   = trim($_POST['correo']   ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if (empty($nombre) || empty($correo)) {
        $_SESSION['toast'] = ['text' => 'Nombre y correo son obligatorios.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast'] = ['text' => 'El correo no es válido.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $chk = $db->prepare("SELECT id_usuario FROM usuarios WHERE correo = :c AND id_usuario != :id LIMIT 1");
    $chk->execute([':c' => $correo, ':id' => $id]);
    if ($chk->fetch()) {
        $_SESSION['toast'] = ['text' => 'Ese correo ya está en uso por otro usuario.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $db->prepare("UPDATE usuarios SET nombre=:n, correo=:c, telefono=:t, actualizado_en=NOW() WHERE id_usuario=:id")
       ->execute([':n' => $nombre, ':c' => $correo, ':t' => $telefono ?: null, ':id' => $id]);

    $_SESSION['nombre'] = $nombre;
    $_SESSION['correo'] = $correo;

    $_SESSION['toast'] = ['text' => 'Perfil actualizado correctamente.', 'type' => 'success'];
    header('Location: ' . $redirect);
    exit;
}

// ── Cambiar contraseña ────────────────────────────────────────────────────
function cambiarPassword($db, $id, $redirect): void
{
    $actual    = $_POST['pass_actual']    ?? '';
    $nueva     = $_POST['pass_nueva']     ?? '';
    $confirmar = $_POST['pass_confirmar'] ?? '';

    if (empty($actual) || empty($nueva) || empty($confirmar)) {
        $_SESSION['toast'] = ['text' => 'Completa todos los campos de contraseña.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    if ($nueva !== $confirmar) {
        $_SESSION['toast'] = ['text' => 'Las contraseñas nuevas no coinciden.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    if (strlen($nueva) < 8) {
        $_SESSION['toast'] = ['text' => 'La contraseña debe tener al menos 8 caracteres.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $stmt = $db->prepare("SELECT contrasena_hash FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($actual, $row['contrasena_hash'])) {
        $_SESSION['toast'] = ['text' => 'La contraseña actual es incorrecta.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $db->prepare("UPDATE usuarios SET contrasena_hash=:h, actualizado_en=NOW() WHERE id_usuario=:id")
       ->execute([':h' => password_hash($nueva, PASSWORD_BCRYPT), ':id' => $id]);

    $_SESSION['toast'] = ['text' => 'Contraseña actualizada correctamente.', 'type' => 'success'];
    header('Location: ' . $redirect);
    exit;
}

// ── Subir foto de perfil ──────────────────────────────────────────────────
function subirFoto($db, $id, $rootPath, $redirect): void
{
    if (empty($_FILES['foto']['name'])) {
        $_SESSION['toast'] = ['text' => 'No se recibió ninguna imagen.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $file = $_FILES['foto'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $_SESSION['toast'] = ['text' => 'Solo se permiten imágenes JPG, PNG o WEBP.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        $_SESSION['toast'] = ['text' => 'La imagen no puede superar 3MB.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $uploadDir = $rootPath . '/public/storage/perfiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $old = $db->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $old->execute([':id' => $id]);
    $oldFoto = $old->fetchColumn();
    if ($oldFoto && file_exists($rootPath . '/' . $oldFoto)) {
        @unlink($rootPath . '/' . $oldFoto);
    }

    $nombre = 'perfil_' . $id . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nombre)) {
        $_SESSION['toast'] = ['text' => 'Error al guardar la imagen.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }

    $ruta = 'public/storage/perfiles/' . $nombre;
    $db->prepare("UPDATE usuarios SET foto_perfil=:r, actualizado_en=NOW() WHERE id_usuario=:id")
       ->execute([':r' => $ruta, ':id' => $id]);

    $_SESSION['toast'] = ['text' => 'Foto de perfil actualizada.', 'type' => 'success'];
    header('Location: ' . $redirect);
    exit;
}
