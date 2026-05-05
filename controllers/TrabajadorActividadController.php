<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../views/auth/login.php'); exit;
}
// Solo trabajadores (rol 2)
if ((int)$_SESSION['id_rol'] === 1) {
    header('Location: ../views/dashboards/admin.php'); exit;
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/models/Notificacion.php';

$db         = (new Database())->conectar();
$notif      = new Notificacion($db);
$id_usuario = (int)$_SESSION['id_usuario'];
$id         = (int)($_POST['id_actividad'] ?? 0);
$estado     = trim($_POST['estado'] ?? '');

$estados_validos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];

if (!$id || !in_array($estado, $estados_validos)) {
    $_SESSION['toast'] = ['text' => 'Datos inválidos.', 'type' => 'error'];
    header('Location: ../views/dashboards/trabajador.php#actividades'); exit;
}

// Verificar que la actividad le pertenece al trabajador
$stmt = $db->prepare("SELECT id_actividad FROM actividades WHERE id_actividad = :id AND id_asignado_a = :u LIMIT 1");
$stmt->execute([':id' => $id, ':u' => $id_usuario]);

if (!$stmt->fetch()) {
    $_SESSION['toast'] = ['text' => 'No tienes permiso para modificar esta actividad.', 'type' => 'error'];
    header('Location: ../views/dashboards/trabajador.php#actividades'); exit;
}

$db->prepare("UPDATE actividades SET estado = :estado, actualizado_en = NOW() WHERE id_actividad = :id")
   ->execute([':estado' => $estado, ':id' => $id]);

// Notificar al admin
$adminId = $notif->getAdminId();
if ($adminId) {
    $actInfo = $db->prepare(
        "SELECT a.descripcion, ta.nombre AS tipo, u.nombre AS trabajador
         FROM actividades a
         JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
         JOIN usuarios u ON a.id_asignado_a = u.id_usuario
         WHERE a.id_actividad = :id LIMIT 1"
    );
    $actInfo->execute([':id' => $id]);
    $act = $actInfo->fetch(PDO::FETCH_ASSOC);
    if ($act) {
        $estadoLabel = ['pendiente'=>'Pendiente','en_proceso'=>'En proceso','completada'=>'Completada','cancelada'=>'Cancelada'];
        $icono       = $estado === 'completada' ? '✅' : ($estado === 'cancelada' ? '❌' : '🔄');
        $label       = $estadoLabel[$estado] ?? $estado;
        $notif->crear($adminId, 'actividad',
            "{$icono} Actividad {$label}",
            "{$act['trabajador']} marcó '{$act['tipo']} — {$act['descripcion']}' como " . strtolower($label),
            $estado === 'completada' ? 'alta' : 'media', null, $id);
    }
}

$_SESSION['toast'] = ['text' => 'Estado actualizado correctamente.', 'type' => 'success'];
header('Location: ../views/dashboards/trabajador.php#actividades');
exit;
?>
