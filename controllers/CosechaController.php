<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: ../views/auth/login.php'); exit; }

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/models/Cultivo.php';

$db      = (new Database())->conectar();
$model   = new Cultivo($db);
$esAdmin = (int)$_SESSION['id_rol'] === 1;
$redirect = $esAdmin ? '../views/dashboards/admin.php#cosecha' : '../views/dashboards/trabajador.php';

$id_cultivo    = (int)($_POST['id_cultivo']       ?? 0);
$fecha_real    = trim($_POST['fecha_cosecha_real'] ?? '');
$cantidad_kg   = trim($_POST['cantidad_kg']        ?? '');
$observaciones = trim($_POST['observaciones']      ?? '');

// Validaciones
if (!$id_cultivo || empty($fecha_real) || empty($cantidad_kg)) {
    $_SESSION['toast'] = ['text' => 'Completa todos los campos obligatorios.', 'type' => 'error'];
    header('Location: ' . $redirect); exit;
}
if (!DateTime::createFromFormat('Y-m-d', $fecha_real)) {
    $_SESSION['toast'] = ['text' => 'Fecha de cosecha inválida.', 'type' => 'error'];
    header('Location: ' . $redirect); exit;
}
// Validación cantidad mínima
if (!is_numeric($cantidad_kg) || (float)$cantidad_kg < 0.1) {
    $_SESSION['toast'] = ['text' => 'La cantidad cosechada debe ser mayor a 0.', 'type' => 'error'];
    header('Location: ' . $redirect); exit;
}

// Si es trabajador, verificar que el cultivo pertenece a sus lotes
if (!$esAdmin) {
    $stmt = $db->prepare(
        "SELECT c.id_cultivo FROM cultivos c
         JOIN usuarios_lotes ul ON c.id_lote = ul.id_lote
         WHERE c.id_cultivo = :id AND ul.id_usuario = :u AND ul.activo = 1 LIMIT 1"
    );
    $stmt->execute([':id' => $id_cultivo, ':u' => $_SESSION['id_usuario']]);
    if (!$stmt->fetch()) {
        $_SESSION['toast'] = ['text' => 'No tienes permiso para registrar esta cosecha.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
} else {
    // Admin: verificar que el cultivo existe y está activo
    $chk = $db->prepare("SELECT id_cultivo FROM cultivos WHERE id_cultivo = :id AND activo_en_lote IS NOT NULL LIMIT 1");
    $chk->execute([':id' => $id_cultivo]);
    if (!$chk->fetch()) {
        $_SESSION['toast'] = ['text' => 'El cultivo no existe o ya fue cosechado.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
}

try {
    $fechaEstimada = $model->registrarCosecha($id_cultivo, [
        'fecha_cosecha_real' => $fecha_real,
        'cantidad_kg'        => (float)$cantidad_kg,
        'observaciones'      => $observaciones,
    ]);
} catch (Exception $e) {
    $_SESSION['toast'] = ['text' => 'Error al registrar la cosecha. Intenta de nuevo.', 'type' => 'error'];
    header('Location: ' . $redirect); exit;
}

// Comparación con estimación
$diasDiff = (new DateTime($fecha_real))->diff(new DateTime($fechaEstimada))->days;
$signo    = $fecha_real <= $fechaEstimada ? 'antes' : 'después';
$msg = "Cosecha registrada. {$cantidad_kg} kg. Realizada {$diasDiff} día(s) {$signo} de lo estimado. Lote liberado.";

$_SESSION['toast'] = ['text' => $msg, 'type' => 'success'];
header('Location: ' . $redirect);
exit;
?>
