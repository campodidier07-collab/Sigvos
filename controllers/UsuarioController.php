<?php
$rootPath = dirname(__DIR__);
if (!empty($_GET['PHPSESSID']) && session_status() === PHP_SESSION_NONE) {
    session_id(preg_replace('/[^a-zA-Z0-9,-]/', '', $_GET['PHPSESSID']));
}
require_once $rootPath . '/config/session.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/models/Usuario.php';

$database = new Database();
$db       = $database->conectar();
$usuario  = new Usuario($db);

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

 // Verificar cookie "remember me" si no hay sesión activa
if (!isset($_SESSION['id_usuario'])) {
    remember_me_check($db);
}

// Si la sesión se restauró y están en login/registro, redirigir al dashboard
if (isset($_SESSION['id_usuario']) && $accion === 'login') {
    if ((int)$_SESSION['id_rol'] === 1) {
        header('Location: ../views/dashboards/admin.php');
    } else {
        header('Location: ../views/dashboards/trabajador.php');
    }
    exit;
}

switch ($accion) {
    case 'login':
        csrf_verify();
        login($usuario, $db);
        break;

    case 'registro':
        csrf_verify();
        registro($usuario);
        break;

    case 'logout':
        logout($db);
        break;

    case 'toggleEstado':
        csrf_verify(true); // JSON response on failure
        toggleEstado($db);
        break;

    case 'crear_usuario':
        csrf_verify();
        crearUsuario($db);
        break;

    case 'editar_usuario':
        csrf_verify();
        editarUsuario($db);
        break;

    default:
        redirigirConError('../views/auth/login.php', 'Acción no válida.');
        break;
}

function login($usuario)
{
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (empty($correo) || empty($contrasena)) {
        redirigirConError('../views/auth/login.php', 'Completa todos los campos.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        redirigirConError('../views/auth/login.php', 'El correo no es válido.');
    }

    $maxIntentos   = 3;
    $tiempoBloqueo = 120;

    // Buscar usuario sin filtrar por activo para poder contar intentos
    $user = $usuario->obtenerPorCorreoSinFiltro($correo);

    if (!$user) {
        // Correo no existe, igual mostramos mensaje genérico
        redirigirConError('../views/auth/login.php', 'Credenciales incorrectas.');
    }

    // Verificar bloqueo activo en BD
    if (!empty($user['bloqueado_hasta']) && strtotime($user['bloqueado_hasta']) > time()) {
        $restante = strtotime($user['bloqueado_hasta']) - time();
        redirigirConError('../views/auth/login.php',
            "Bloqueo temporal por intentos fallidos. Intenta de nuevo en {$restante} segundos.");
    }

    // Si el bloqueo expiró, resetear
    if (!empty($user['bloqueado_hasta']) && strtotime($user['bloqueado_hasta']) <= time()) {
        $usuario->resetearIntentos($user['id_usuario']);
        $user['intentos_fallidos'] = 0;
    }

    // Verificar contraseña
    if (!password_verify($contrasena, $user['contrasena_hash'])) {
        $nuevosIntentos = (int)$user['intentos_fallidos'] + 1;

        if ($nuevosIntentos >= $maxIntentos) {
            $usuario->registrarBloqueo($user['id_usuario'], $tiempoBloqueo);
            redirigirConError('../views/auth/login.php',
                "Bloqueo temporal por intentos fallidos. Intenta de nuevo en {$tiempoBloqueo} segundos.");
        }

        $usuario->incrementarIntentos($user['id_usuario'], $nuevosIntentos);
        $restantes = $maxIntentos - $nuevosIntentos;
        redirigirConError('../views/auth/login.php',
            "Credenciales incorrectas. Te quedan {$restantes} intento(s) antes del bloqueo.");
    }

    // Verificar que el usuario esté activo
    if (!(int)$user['activo']) {
        redirigirConError('../views/auth/login.php', 'Tu cuenta está desactivada. Contacta al administrador.');
    }

    // Login exitoso
    $usuario->resetearIntentos($user['id_usuario']);

    $_SESSION['id_usuario'] = $user['id_usuario'];
    $_SESSION['nombre']     = $user['nombre'];
    $_SESSION['correo']     = $user['correo'];
    $_SESSION['id_rol']     = $user['id_rol'];
    $_SESSION['rol']        = $user['nombre_rol'];

    $usuario->actualizarUltimoAcceso($user['id_usuario']);

    if ((int)$user['id_rol'] === 1) {
        header('Location: ../views/dashboards/admin.php?t=' . time());
    } else {
        header('Location: ../views/dashboards/trabajador.php?t=' . time());
    }
    exit;
}

function registro($usuario)
{
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $confirmar = trim($_POST['confirmar'] ?? '');
    $terms = isset($_POST['terms']) ? true : false;

    if (
        empty($nombre) ||
        empty($apellidos) ||
        empty($telefono) ||
        empty($correo) ||
        empty($contrasena) ||
        empty($confirmar)
    ) {
        redirigirConError('../views/auth/registro.php', 'Completa todos los campos.');
    }

    if (!$terms) {
        redirigirConError('../views/auth/registro.php', 'Debes aceptar los términos y condiciones.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        redirigirConError('../views/auth/registro.php', 'El correo no es válido.');
    }

    if ($contrasena !== $confirmar) {
        redirigirConError('../views/auth/registro.php', 'Las contraseñas no coinciden.');
    }

    if (strlen($contrasena) < 8) {
        redirigirConError('../views/auth/registro.php', 'La contraseña debe tener al menos 8 caracteres.');
    }

    if ($usuario->existeCorreo($correo)) {
        redirigirConError('../views/auth/registro.php', 'Ya existe una cuenta con ese correo.');
    }

    $nombreCompleto = trim($nombre . ' ' . $apellidos);

    $datos = [
        'id_rol' => 2,
        'nombre' => $nombreCompleto,
        'apellidos' => $apellidos,
        'telefono' => $telefono,
        'correo' => $correo,
        'contrasena_hash' => password_hash($contrasena, PASSWORD_BCRYPT),
    ];

    $resultado = $usuario->registrar($datos);

    if ($resultado === true) {
        $_SESSION['exito'] = '¡Cuenta creada exitosamente! Ya puedes iniciar sesión.';
        header('Location: ../views/auth/login.php');
        exit;
    }

    redirigirConError('../views/auth/registro.php', 'Error al guardar: ' . $resultado);
}

function logout($db)
{
    $razon = $_GET['razon'] ?? '';
    remember_me_clear($db);
    session_unset();
    session_destroy();
    if ($razon === 'inactividad') {
        session_start();
        $_SESSION['error'] = 'Tu sesión se cerró por inactividad. Por favor, inicia sesión de nuevo.';
        header('Location: ../views/auth/login.php');
    } else {
        header('Location: ../public/index.php');
    }
    exit;
}

function toggleEstado($db)
{
    // Solo el administrador puede cambiar el estado de otros usuarios
    if ((int)($_SESSION['id_rol'] ?? 0) !== 1) {
        http_response_code(403);
        exit;
    }
    $id     = (int)($_GET['id'] ?? 0);
    $activo = (int)($_GET['estado'] ?? 0) === 1 ? 0 : 1; // toggle
    if (!$id) { http_response_code(400); exit; }
    // Evitar que el admin se desactive a sí mismo
    if ($id === (int)$_SESSION['id_usuario']) { http_response_code(400); exit; }
    $stmt = $db->prepare("UPDATE usuarios SET activo = :activo, actualizado_en = NOW() WHERE id_usuario = :id");
    $stmt->execute([':activo' => $activo, ':id' => $id]);
    http_response_code(200);
    exit;
}

function crearUsuario($db)
{
    if ((int)($_SESSION['id_rol'] ?? 0) !== 1) { http_response_code(403); exit; }

    $nombre    = trim($_POST['nombre'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $estado    = in_array($_POST['estado'] ?? '', ['Activo','Inactivo']) ? strtolower($_POST['estado']) : 'activo';

    if (empty($nombre) || empty($correo) || empty($contrasena)) {
        $_SESSION['toast'] = ['text' => 'Completa los campos requeridos.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast'] = ['text' => 'El correo ingresado no es válido.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    if (strlen($contrasena) < 8) {
        $_SESSION['toast'] = ['text' => 'La contraseña debe tener al menos 8 caracteres.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE correo = :correo LIMIT 1");
    $check->execute([':correo' => $correo]);
    if ($check->rowCount()) {
        $_SESSION['toast'] = ['text' => 'Ya existe un usuario con ese correo.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    $stmt = $db->prepare("INSERT INTO usuarios (id_rol, nombre, correo, contrasena_hash, telefono, activo, creado_en, actualizado_en)
                          VALUES (2, :nombre, :correo, :hash, :telefono, :activo, NOW(), NOW())");
    $stmt->execute([
        ':nombre'   => $nombre,
        ':correo'   => $correo,
        ':hash'     => password_hash($contrasena, PASSWORD_BCRYPT),
        ':telefono' => $telefono,
        ':activo'   => ($estado === 'activo') ? 1 : 0,
    ]);

    $_SESSION['toast'] = ['text' => 'Usuario creado correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#trabajadores');
    exit;
}

function editarUsuario($db)
{
    if ((int)($_SESSION['id_rol'] ?? 0) !== 1) { http_response_code(403); exit; }

    $id        = (int)($_POST['id_usuario'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $estado    = in_array($_POST['estado'] ?? '', ['Activo','Inactivo']) ? strtolower($_POST['estado']) : 'activo';

    if (!$id || empty($nombre) || empty($correo)) {
        $_SESSION['toast'] = ['text' => 'Datos inválidos.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast'] = ['text' => 'El correo ingresado no es válido.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    if ($contrasena && strlen($contrasena) < 8) {
        $_SESSION['toast'] = ['text' => 'La contraseña debe tener al menos 8 caracteres.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#trabajadores'); exit;
    }

    if ($contrasena) {
        $stmt = $db->prepare("UPDATE usuarios SET nombre=:nombre, correo=:correo, telefono=:telefono, activo=:activo, contrasena_hash=:hash, actualizado_en=NOW() WHERE id_usuario=:id");
        $stmt->execute([':nombre'=>$nombre,':correo'=>$correo,':telefono'=>$telefono,':activo'=>($estado==='activo'?1:0),':hash'=>password_hash($contrasena, PASSWORD_BCRYPT),':id'=>$id]);
    } else {
        $stmt = $db->prepare("UPDATE usuarios SET nombre=:nombre, correo=:correo, telefono=:telefono, activo=:activo, actualizado_en=NOW() WHERE id_usuario=:id");
        $stmt->execute([':nombre'=>$nombre,':correo'=>$correo,':telefono'=>$telefono,':activo'=>($estado==='activo'?1:0),':id'=>$id]);
    }

    $_SESSION['toast'] = ['text' => 'Usuario actualizado correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#trabajadores');
    exit;
}

function redirigirConError($url, $mensaje)
{
    $_SESSION['error'] = $mensaje;
    header('Location: ' . $url);
    exit;
}
?>
