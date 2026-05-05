<?php
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    header('Location: ../views/auth/login.php');
    exit;
}

require_once $rootPath . '/config/database.php';
require_once $rootPath . '/config/permisos.php';
require_once $rootPath . '/models/Actividad.php';

$db    = (new Database())->conectar();
$model = new Actividad($db);
require_once $rootPath . '/models/Notificacion.php';
$notif = new Notificacion($db);

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'crear_actividad':
        requirePermiso($db, $_SESSION['id_usuario'], 'actividades.crear');
        crearActividad($model, $notif);
        break;
    case 'editar_actividad':
        requirePermiso($db, $_SESSION['id_usuario'], 'actividades.editar');
        editarActividad($model, $notif);
        break;
    case 'eliminar_actividad':
        requirePermiso($db, $_SESSION['id_usuario'], 'actividades.eliminar');
        eliminarActividad($model);
        break;
    default:
        header('Location: ../views/dashboards/admin.php');
        exit;
}

function crearActividad($model, $notif = null) {
    $id_cultivo      = (int)($_POST['id_cultivo']       ?? 0);
    $id_tipo         = (int)($_POST['id_tipo_actividad'] ?? 0);
    $id_asignado_a   = (int)($_POST['id_asignado_a']    ?? 0);
    $fecha           = trim($_POST['fecha_programada']   ?? '');
    $descripcion     = trim($_POST['descripcion']        ?? '');
    $observaciones   = trim($_POST['observaciones']      ?? '');

    if (!$id_cultivo || !$id_tipo || empty($fecha) || empty($descripcion)) {
        $_SESSION['toast'] = ['text' => 'Completa todos los campos obligatorios.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#actividades'); exit;
    }

    if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
        $_SESSION['toast'] = ['text' => 'Fecha programada inválida.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#actividades'); exit;
    }

    $id = $model->crear([
        'id_cultivo'       => $id_cultivo,
        'id_tipo_actividad'=> $id_tipo,
        'id_creado_por'    => $_SESSION['id_usuario'],
        'id_asignado_a'    => $id_asignado_a ?: null,
        'fecha_programada' => $fecha,
        'descripcion'      => $descripcion,
        'observaciones'    => $observaciones,
    ]);

    // Notificar al trabajador asignado
    if ($id_asignado_a && $notif) {
        $tipoAct = $model->obtenerTipos();
        $tipoNombre = '';
        foreach ($tipoAct as $t) { if ($t['id_tipo_actividad'] == $id_tipo) { $tipoNombre = $t['nombre']; break; } }
        $notif->crear($id_asignado_a, 'actividad', '📋 Nueva actividad asignada',
            "Se te asignó: {$tipoNombre} — {$descripcion}. Fecha: " . date('d/m/Y', strtotime($fecha)), 'alta', null, $id);
    }

    $_SESSION['toast'] = ['text' => 'Actividad registrada y asignada correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#actividades');
    exit;
}

function editarActividad($model, $notif = null) {
    $id            = (int)($_POST['id_actividad']      ?? 0);
    $id_tipo       = (int)($_POST['id_tipo_actividad'] ?? 0);
    $id_asignado_a = (int)($_POST['id_asignado_a']     ?? 0);
    $estado        = trim($_POST['estado']             ?? '');
    $fecha         = trim($_POST['fecha_programada']   ?? '');
    $descripcion   = trim($_POST['descripcion']        ?? '');
    $observaciones = trim($_POST['observaciones']      ?? '');

    $estados_validos = ['pendiente','en_proceso','completada','cancelada'];
    if (!$id || !$id_tipo || empty($fecha) || empty($descripcion) || !in_array($estado, $estados_validos)) {
        $_SESSION['toast'] = ['text' => 'Datos inválidos para editar la actividad.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#actividades'); exit;
    }

    $model->editar($id, [
        'id_tipo_actividad' => $id_tipo,
        'id_asignado_a'     => $id_asignado_a ?: null,
        'estado'            => $estado,
        'fecha_programada'  => $fecha,
        'descripcion'       => $descripcion,
        'observaciones'     => $observaciones,
    ]);

    // Notificar al trabajador si se le reasignó o cambió el estado
    if ($id_asignado_a && $notif) {
        $notif->crear($id_asignado_a, 'actividad', '✏️ Actividad actualizada',
            "La actividad '{$descripcion}' fue actualizada. Estado: " . ucfirst(str_replace('_',' ',$estado)), 'media', null, $id);
    }

    $_SESSION['toast'] = ['text' => 'Actividad actualizada correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#actividades');
    exit;
}

function eliminarActividad($model) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $_SESSION['toast'] = ['text' => 'ID inválido.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#actividades'); exit;
    }
    $model->eliminar($id);
    $_SESSION['toast'] = ['text' => 'Actividad eliminada.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#actividades');
    exit;
}
?>
