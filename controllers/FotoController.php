<?php
$rootPath = dirname(__DIR__);
if (!empty($_GET['PHPSESSID']) && session_status() === PHP_SESSION_NONE) {
    session_id(preg_replace('/[^a-zA-Z0-9,-]/', '', $_GET['PHPSESSID']));
}
require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../views/auth/login.php');
    exit;
}

require_once $rootPath . '/config/database.php';

// Constantes para literales repetidos
const SQL_CHECK_ACTIVIDAD = "SELECT id_actividad FROM actividades WHERE id_actividad = :id AND id_asignado_a = :u LIMIT 1";
const MSG_SIN_PERMISO     = 'Sin permiso.';

$db         = (new Database())->conectar();
$id_usuario = (int)$_SESSION['id_usuario'];
$esAdmin    = (int)$_SESSION['id_rol'] === 1;
$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';
$redirect   = $esAdmin ? '../views/dashboards/admin.php#fotos' : '../views/dashboards/trabajador.php#fotos';

$uploadDir = $rootPath . '/public/storage/fotos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($accion === 'subir_cultivo') {
    $id = (int)($_POST['id_cultivo'] ?? 0);
    if (!$id || empty($_FILES['foto']['name'])) {
        $_SESSION['toast'] = ['text' => 'Selecciona un cultivo y una foto.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    $chkC = $db->prepare("SELECT id_cultivo FROM cultivos WHERE id_cultivo = :id LIMIT 1");
    $chkC->execute([':id' => $id]);
    if (!$chkC->fetch()) {
        $_SESSION['toast'] = ['text' => 'El cultivo seleccionado no existe.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    $ruta = subirArchivo($_FILES['foto'], $uploadDir);
    if (!$ruta) {
        $_SESSION['toast'] = ['text' => 'Solo JPG/PNG/WEBP hasta 5MB.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    $db->prepare("UPDATE cultivos SET fotografia = :r, actualizado_en = NOW() WHERE id_cultivo = :id")
       ->execute([':r' => $ruta, ':id' => $id]);

    if ($esAdmin) {
        $infoStmt = $db->prepare(
            "SELECT c.codigo, l.id_lote FROM cultivos c JOIN lotes l ON c.id_lote = l.id_lote WHERE c.id_cultivo = :id LIMIT 1"
        );
        $infoStmt->execute([':id' => $id]);
        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
        if ($info) {
            $trabStmt = $db->prepare("SELECT id_usuario FROM usuarios_lotes WHERE id_lote = :lote AND activo = 1");
            $trabStmt->execute([':lote' => $info['id_lote']]);
            $trabajadores = $trabStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($trabajadores as $uid) {
                $db->prepare(
                    "INSERT INTO notificaciones (id_usuario, tipo, prioridad, titulo, mensaje, leida, creada_en)
                     VALUES (:u, 'info', 'baja', 'Foto de cultivo actualizada', :msg, 0, NOW())"
                )->execute([
                    ':u'   => $uid,
                    ':msg' => 'El administrador actualizó la fotografía del cultivo ' . $info['codigo'] . '.',
                ]);
            }
        }
    }
    $_SESSION['toast'] = ['text' => 'Fotografía del cultivo actualizada.', 'type' => 'success'];

} elseif ($accion === 'subir_actividad') {
    $id = (int)($_POST['id_actividad'] ?? 0);
    if (!$esAdmin) {
        $chk = $db->prepare(SQL_CHECK_ACTIVIDAD);
        $chk->execute([':id' => $id, ':u' => $id_usuario]);
        if (!$chk->fetch()) {
            $_SESSION['toast'] = ['text' => MSG_SIN_PERMISO, 'type' => 'error'];
            header('Location: ' . $redirect); exit;
        }
    }
    if (!$id || empty($_FILES['foto']['name'])) {
        $_SESSION['toast'] = ['text' => 'Selecciona una actividad y una foto.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    $ruta = subirArchivo($_FILES['foto'], $uploadDir);
    if (!$ruta) {
        $_SESSION['toast'] = ['text' => 'Solo JPG/PNG/WEBP hasta 5MB.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    $db->prepare("UPDATE actividades SET fotografia = :r, actualizado_en = NOW() WHERE id_actividad = :id")
       ->execute([':r' => $ruta, ':id' => $id]);
    $_SESSION['toast'] = ['text' => 'Fotografía de la actividad actualizada.', 'type' => 'success'];

} elseif ($accion === 'eliminar_cultivo') {
    if (!$esAdmin) {
        $_SESSION['toast'] = ['text' => MSG_SIN_PERMISO, 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        header('Location: ' . $redirect); exit;
    }
    $row = $db->prepare("SELECT fotografia FROM cultivos WHERE id_cultivo = :id LIMIT 1");
    $row->execute([':id' => $id]);
    $ruta = $row->fetchColumn();
    if ($ruta && file_exists($rootPath . '/' . $ruta)) {
        unlink($rootPath . '/' . $ruta);
    }
    $db->prepare("UPDATE cultivos SET fotografia = NULL, actualizado_en = NOW() WHERE id_cultivo = :id")
       ->execute([':id' => $id]);
    $_SESSION['toast'] = ['text' => 'Fotografía del cultivo eliminada.', 'type' => 'success'];

} elseif ($accion === 'eliminar_actividad') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        header('Location: ' . $redirect); exit;
    }
    if (!$esAdmin) {
        $chk = $db->prepare(SQL_CHECK_ACTIVIDAD);
        $chk->execute([':id' => $id, ':u' => $id_usuario]);
        if (!$chk->fetch()) {
            $_SESSION['toast'] = ['text' => MSG_SIN_PERMISO, 'type' => 'error'];
            header('Location: ' . $redirect); exit;
        }
    }
    $row = $db->prepare("SELECT fotografia FROM actividades WHERE id_actividad = :id LIMIT 1");
    $row->execute([':id' => $id]);
    $ruta = $row->fetchColumn();
    if ($ruta && file_exists($rootPath . '/' . $ruta)) {
        unlink($rootPath . '/' . $ruta);
    }
    $db->prepare("UPDATE actividades SET fotografia = NULL, actualizado_en = NOW() WHERE id_actividad = :id")
       ->execute([':id' => $id]);
    $_SESSION['toast'] = ['text' => 'Fotografía de la actividad eliminada.', 'type' => 'success'];

} elseif ($accion === 'editar_descripcion_actividad') {
    $id          = (int)($_POST['id_actividad'] ?? 0);
    $descripcion = trim($_POST['descripcion']   ?? '');
    if (!$id || $descripcion === '') {
        $_SESSION['toast'] = ['text' => 'Descripción inválida.', 'type' => 'error'];
        header('Location: ' . $redirect); exit;
    }
    if (!$esAdmin) {
        $chk = $db->prepare(SQL_CHECK_ACTIVIDAD);
        $chk->execute([':id' => $id, ':u' => $id_usuario]);
        if (!$chk->fetch()) {
            $_SESSION['toast'] = ['text' => MSG_SIN_PERMISO, 'type' => 'error'];
            header('Location: ' . $redirect); exit;
        }
    }
    $db->prepare("UPDATE actividades SET descripcion = :d, actualizado_en = NOW() WHERE id_actividad = :id")
       ->execute([':d' => $descripcion, ':id' => $id]);
    $_SESSION['toast'] = ['text' => 'Descripción actualizada.', 'type' => 'success'];

} elseif ($accion === 'fotos_json') {
    header('Content-Type: application/json');
    require_once $rootPath . '/models/AsignacionLote.php';

    if ($esAdmin) {
        $rows = $db->query(
            "SELECT c.id_cultivo, c.codigo, c.fotografia, c.estado,
                    l.identificador AS lote_id, l.nombre AS lote_nombre
             FROM cultivos c JOIN lotes l ON c.id_lote = l.id_lote
             WHERE c.activo_en_lote IS NOT NULL ORDER BY c.codigo"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $idsLotes = array_column(
            array_filter((new AsignacionLote($db))->obtenerPorTrabajador($id_usuario), fn($l) => $l['activo'] == 1),
            'id_lote'
        );
        if (empty($idsLotes)) {
            echo json_encode(['cultivos' => []]); exit;
        }
        $ph   = implode(',', array_fill(0, count($idsLotes), '?'));
        $stmt = $db->prepare(
            "SELECT c.id_cultivo, c.codigo, c.fotografia, c.estado,
                    l.identificador AS lote_id, l.nombre AS lote_nombre
             FROM cultivos c JOIN lotes l ON c.id_lote = l.id_lote
             WHERE c.id_lote IN ($ph) AND c.activo_en_lote IS NOT NULL ORDER BY c.codigo"
        );
        $stmt->execute($idsLotes);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['cultivos' => $rows]);
    exit;

} else {
    header('Location: ' . $redirect); exit;
}

header('Location: ' . $redirect);
exit;

function subirArchivo(array $file, string $dir): string|false
{
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extValidas = ['jpg', 'jpeg', 'png', 'webp'];
    $tamanoMax  = 5 * 1024 * 1024;

    if (!in_array($ext, $extValidas) || $file['size'] > $tamanoMax) {
        return false;
    }

    $nombre = 'foto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
        return false;
    }

    return 'public/storage/fotos/' . $nombre;
}
