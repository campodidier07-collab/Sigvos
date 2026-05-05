<?php
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    header('Location: ../views/auth/login.php');
    exit;
}

require_once $rootPath . '/config/database.php';
require_once $rootPath . '/models/AsignacionLote.php';

$db    = (new Database())->conectar();
$model = new AsignacionLote($db);
require_once $rootPath . '/models/Notificacion.php';
$notif = new Notificacion($db);

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
switch ($accion) {
    case 'asignar':
        asignarLote($model, $db, $notif);
        break;
    case 'asignar_cultivo':
        asignarCultivo($model, $db, $notif);
        break;
    case 'desactivar':
        desactivarAsignacion($model, $db, $notif);
        break;
    case 'reasignar':
        reasignarLote($model, $db, $notif);
        break;
    default:
        header('Location: ../views/dashboards/admin.php');
        exit;
}

function asignarLote($model, $db = null, $notif = null) {
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $id_lote    = (int)($_POST['id_lote']    ?? 0);

    if (!$id_usuario || !$id_lote) {
        $_SESSION['toast'] = ['text' => 'Selecciona un trabajador y un lote.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }
    if (!$model->esRolPermitido($id_usuario)) {
        $_SESSION['toast'] = ['text' => 'El usuario seleccionado no tiene rol de trabajador.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }
    if ($model->tieneAsignacionActiva($id_usuario, $id_lote)) {
        $_SESSION['toast'] = ['text' => 'Este trabajador ya tiene una asignación activa en ese lote.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }

    $model->asignar($id_usuario, $id_lote, $_SESSION['id_usuario']);

    // Obtener nombre del lote
    $loteInfo = $db->prepare("SELECT identificador, nombre FROM lotes WHERE id_lote = :id LIMIT 1");
    $loteInfo->execute([':id' => $id_lote]);
    $lote = $loteInfo->fetch(PDO::FETCH_ASSOC);
    $loteNombre = $lote ? $lote['identificador'] . ' — ' . $lote['nombre'] : 'Lote #' . $id_lote;

    // Notificar al trabajador
    $notif->crear($id_usuario, 'asignacion', '📍 Nuevo lote asignado',
        "Se te ha asignado el lote {$loteNombre}. Ya puedes ver tus cultivos y actividades.", 'alta');

    $_SESSION['toast'] = ['text' => 'Lote asignado exitosamente al trabajador.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#asignaciones');
    exit;
}

function desactivarAsignacion($model, $db = null, $notif = null) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $_SESSION['toast'] = ['text' => 'ID de asignación inválido.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }
    // Obtener trabajador y lote antes de desactivar
    $info = $db->prepare("SELECT ul.id_usuario, l.identificador, l.nombre FROM usuarios_lotes ul JOIN lotes l ON ul.id_lote = l.id_lote WHERE ul.id_asignacion = :id LIMIT 1");
    $info->execute([':id' => $id]);
    $row = $info->fetch(PDO::FETCH_ASSOC);

    $model->desactivar($id);

    if ($row && $notif) {
        $notif->crear($row['id_usuario'], 'asignacion',
            '📍 Asignación de lote desactivada',
            "Tu asignación al lote {$row['identificador']} — {$row['nombre']} ha sido desactivada.", 'media');
    }

    $_SESSION['toast'] = ['text' => 'Asignación desactivada. El historial se conserva.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#asignaciones');
    exit;
}

function reasignarLote($model, $db = null, $notif = null) {
    $id_usuario    = (int)($_POST['id_usuario'] ?? 0);
    $id_lote_nuevo = (int)($_POST['id_lote']   ?? 0);

    if (!$id_usuario || !$id_lote_nuevo) {
        $_SESSION['toast'] = ['text' => 'Selecciona trabajador y nuevo lote.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }
    if (!$model->esRolPermitido($id_usuario)) {
        $_SESSION['toast'] = ['text' => 'El usuario no tiene rol de trabajador.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }

    $model->reasignar($id_usuario, $id_lote_nuevo, $_SESSION['id_usuario']);

    $loteInfo = $db->prepare("SELECT identificador, nombre FROM lotes WHERE id_lote = :id LIMIT 1");
    $loteInfo->execute([':id' => $id_lote_nuevo]);
    $lote = $loteInfo->fetch(PDO::FETCH_ASSOC);
    $loteNombre = $lote ? $lote['identificador'] . ' — ' . $lote['nombre'] : 'Lote #' . $id_lote_nuevo;

    $notif->crear($id_usuario, 'asignacion', '🔄 Reasignación de lote',
        "Has sido reasignado al lote {$loteNombre}. Tus asignaciones anteriores fueron desactivadas.", 'alta');

    $_SESSION['toast'] = ['text' => 'Reasignación completada.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#asignaciones');
    exit;
}

function asignarCultivo($model, $db, $notif = null) {
    $id_usuario  = (int)($_POST['id_usuario']  ?? 0);
    $id_cultivo  = (int)($_POST['id_cultivo']  ?? 0);

    if (!$id_usuario || !$id_cultivo) {
        $_SESSION['toast'] = ['text' => 'Selecciona un trabajador y un cultivo.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }

    if (!$model->esRolPermitido($id_usuario)) {
        $_SESSION['toast'] = ['text' => 'El usuario seleccionado no tiene rol de trabajador.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }

    // Obtener el lote del cultivo
    $stmt = $db->prepare("SELECT id_lote FROM cultivos WHERE id_cultivo = :id AND activo_en_lote IS NOT NULL LIMIT 1");
    $stmt->execute([':id' => $id_cultivo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['toast'] = ['text' => 'El cultivo seleccionado no está activo.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }

    $id_lote = (int)$row['id_lote'];

    // Verificar duplicidad
    if ($model->tieneAsignacionActiva($id_usuario, $id_lote)) {
        $_SESSION['toast'] = ['text' => 'Este trabajador ya tiene asignado el lote de ese cultivo.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#asignaciones'); exit;
    }

    $model->asignar($id_usuario, $id_lote, $_SESSION['id_usuario']);

    // Info del cultivo y lote para la notificación
    $infoC = $db->prepare("SELECT c.codigo, l.identificador, l.nombre FROM cultivos c JOIN lotes l ON c.id_lote = l.id_lote WHERE c.id_cultivo = :id LIMIT 1");
    $infoC->execute([':id' => $id_cultivo]);
    $infoRow = $infoC->fetch(PDO::FETCH_ASSOC);
    $loteNombre = $infoRow ? $infoRow['identificador'] . ' — ' . $infoRow['nombre'] : 'Lote #' . $id_lote;
    $cultivoCodigo = $infoRow['codigo'] ?? 'Cultivo #' . $id_cultivo;

    $notif->crear($id_usuario, 'asignacion', '🌱 Cultivo asignado',
        "Se te ha asignado el cultivo {$cultivoCodigo} en el lote {$loteNombre}.", 'alta', $id_cultivo);

    $_SESSION['toast'] = ['text' => 'Cultivo asignado correctamente. El trabajador tiene acceso al lote completo.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#asignaciones');
    exit;
}
?>
