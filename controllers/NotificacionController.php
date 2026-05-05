<?php
$rootPath = dirname(__DIR__);
if (!empty($_GET['PHPSESSID']) && session_status() === PHP_SESSION_NONE) {
    session_id(preg_replace('/[^a-zA-Z0-9,-]/', '', $_GET['PHPSESSID']));
}
require_once $rootPath . '/config/session.php';
if (!isset($_SESSION['id_usuario'])) { header('Location: ../views/auth/login.php'); exit; }
require_once $rootPath . '/config/database.php';

$db         = (new Database())->conectar();
$id_usuario = (int)$_SESSION['id_usuario'];
$esAdmin    = (int)$_SESSION['id_rol'] === 1;
$redirect   = $esAdmin ? '../views/dashboards/admin.php' : '../views/dashboards/trabajador.php';
$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    case 'marcar_leida':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE notificaciones SET leida = 1, leida_en = NOW() WHERE id_notificacion = :id AND id_usuario = :u")
               ->execute([':id' => $id, ':u' => $id_usuario]);
        }
        header('Location: ' . $redirect); exit;

    case 'marcar_todas':
        $db->prepare("UPDATE notificaciones SET leida = 1, leida_en = NOW() WHERE id_usuario = :u AND leida = 0")
           ->execute([':u' => $id_usuario]);
        $_SESSION['toast'] = ['text' => 'Todas las notificaciones marcadas como leídas.', 'type' => 'success'];
        header('Location: ' . $redirect); exit;

    case 'sincronizar':
        // Registra sincronización manual
        $stmt = $db->prepare(
            "INSERT INTO sincronizaciones (id_usuario, dispositivo, tipo, estado, registros_subidos, registros_descargados, iniciada_en, finalizada_en)
             VALUES (:u, 'Web', 'manual', 'completada', 0, 0, NOW(), NOW())"
        );
        $stmt->execute([':u' => $id_usuario]);
        $_SESSION['toast'] = ['text' => 'Sincronización completada correctamente.', 'type' => 'success'];
        $_SESSION['ultima_sync'] = date('Y-m-d H:i:s');
        header('Location: ' . $redirect); exit;

    default:
        header('Location: ' . $redirect); exit;
}
?>
