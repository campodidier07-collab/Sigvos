<?php
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    header('Location: ../views/auth/login.php');
    exit;
}

require_once $rootPath . '/config/database.php';
require_once $rootPath . '/config/permisos.php';
require_once $rootPath . '/models/Lote.php';

$db   = (new Database())->conectar();
$lote = new Lote($db);

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'crear_lote':
        requirePermiso($db, $_SESSION['id_usuario'], 'lotes.crear');
        crearLote($lote);
        break;
    case 'editar_lote':
        requirePermiso($db, $_SESSION['id_usuario'], 'lotes.editar');
        editarLote($lote);
        break;
    case 'eliminar_lote':
        requirePermiso($db, $_SESSION['id_usuario'], 'lotes.eliminar');
        eliminarLote($lote);
        break;
    default:
        header('Location: ../views/dashboards/admin.php');
        exit;
}

function crearLote($lote) {
    $identificador  = strtoupper(trim($_POST['identificador'] ?? ''));
    $nombre         = trim($_POST['nombre'] ?? '');
    $ubicacion      = trim($_POST['ubicacion'] ?? '');
    $area_ha        = trim($_POST['area_ha'] ?? '');
    $id_tipo        = $_POST['id_tipo_preferido'] ?? null;
    $es_alternativo = isset($_POST['es_alternativo']) ? 1 : 0;

    if (empty($identificador) || empty($nombre) || empty($ubicacion) || empty($area_ha)) {
        $_SESSION['toast'] = ['text' => 'Todos los campos obligatorios deben completarse.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }
    if (strlen($identificador) !== 1 || !ctype_alpha($identificador)) {
        $_SESSION['toast'] = ['text' => 'El identificador debe ser una sola letra (A-Z).', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }
    if (!is_numeric($area_ha) || (float)$area_ha <= 0) {
        $_SESSION['toast'] = ['text' => 'El área debe ser un número mayor a 0.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }
    if ($lote->existeIdentificador($identificador)) {
        $_SESSION['toast'] = ['text' => "El identificador '{$identificador}' ya está en uso. Elige otro.", 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }

    $lote->crear([
        'identificador'     => $identificador,
        'nombre'            => $nombre,
        'ubicacion'         => $ubicacion,
        'area_ha'           => (float)$area_ha,
        'id_tipo_preferido' => $id_tipo ?: null,
        'es_alternativo'    => $es_alternativo,
    ]);

    $_SESSION['toast'] = ['text' => "Lote '{$nombre}' registrado exitosamente.", 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#lotes'); exit;
}

function editarLote($lote) {
    $id             = (int)($_POST['id_lote'] ?? 0);
    $identificador  = strtoupper(trim($_POST['identificador'] ?? ''));
    $nombre         = trim($_POST['nombre'] ?? '');
    $ubicacion      = trim($_POST['ubicacion'] ?? '');
    $area_ha        = trim($_POST['area_ha'] ?? '');
    $id_tipo        = $_POST['id_tipo_preferido'] ?? null;
    $es_alternativo = isset($_POST['es_alternativo']) ? 1 : 0;

    if (!$id || empty($identificador) || empty($nombre) || empty($ubicacion) || empty($area_ha)) {
        $_SESSION['toast'] = ['text' => 'Todos los campos obligatorios deben completarse.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }
    if (strlen($identificador) !== 1 || !ctype_alpha($identificador)) {
        $_SESSION['toast'] = ['text' => 'El identificador debe ser una sola letra (A-Z).', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }
    if (!is_numeric($area_ha) || (float)$area_ha <= 0) {
        $_SESSION['toast'] = ['text' => 'El área debe ser un número mayor a 0.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }
    if ($lote->existeIdentificador($identificador, $id)) {
        $_SESSION['toast'] = ['text' => "El identificador '{$identificador}' ya está en uso.", 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#lotes'); exit;
    }

    $lote->editar($id, [
        'identificador'     => $identificador,
        'nombre'            => $nombre,
        'ubicacion'         => $ubicacion,
        'area_ha'           => $area_ha,
        'id_tipo_preferido' => $id_tipo ?: null,
        'es_alternativo'    => $es_alternativo,
        'estado'            => in_array($_POST['estado'] ?? '', ['disponible','ocupado','en_descanso','inactivo']) ? $_POST['estado'] : 'disponible',
    ]);

    $_SESSION['toast'] = ['text' => "Lote '{$nombre}' actualizado correctamente.", 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#lotes'); exit;
}

function eliminarLote($lote) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); exit; }
    $lote->eliminar($id);
    $_SESSION['toast'] = ['text' => 'Lote eliminado correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#lotes'); exit;
}
