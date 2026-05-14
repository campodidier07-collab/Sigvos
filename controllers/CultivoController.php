<?php
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    header('Location: ../views/auth/login.php');
    exit;
}

require_once $rootPath . '/config/database.php';
require_once $rootPath . '/config/permisos.php';
require_once $rootPath . '/models/Cultivo.php';

$db      = (new Database())->conectar();
$cultivo = new Cultivo($db);
require_once $rootPath . '/models/Notificacion.php';
$notif = new Notificacion($db);

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'crear_cultivo':
        requirePermiso($db, $_SESSION['id_usuario'], 'cultivos.crear');
        crearCultivo($cultivo, $db, $notif);
        break;
    case 'editar_cultivo':
        requirePermiso($db, $_SESSION['id_usuario'], 'cultivos.editar');
        editarCultivo($cultivo, $db, $notif);
        break;
    case 'eliminar_cultivo':
        requirePermiso($db, $_SESSION['id_usuario'], 'cultivos.eliminar');
        eliminarCultivo($cultivo, $db, $notif);
        break;
    default:
        header('Location: ../views/dashboards/admin.php');
        exit;
}

function crearCultivo($cultivo, $db, $notif = null) {
    $id_lote     = (int)($_POST['id_lote']     ?? 0);
    $id_variedad = (int)($_POST['id_variedad'] ?? 0);
    $fecha_siembra = trim($_POST['fecha_siembra'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    $fotografia = null;
    if (!empty($_FILES['fotografia']['name'])) {
        $uploadDir = dirname(__DIR__) . '/public/storage/fotos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fotografia = subirArchivoAux($_FILES['fotografia'], $uploadDir);
        if (!$fotografia) {
            $_SESSION['toast'] = ['text' => 'Error en imagen: Solo JPG/PNG/WEBP hasta 5MB.', 'type' => 'error'];
            header('Location: ../views/dashboards/admin.php#cultivos'); exit;
        }
    }

    // Validación básica
    if (!$id_lote || !$id_variedad || empty($fecha_siembra)) {
        $_SESSION['toast'] = ['text' => 'Completa todos los campos obligatorios.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    // Validar fecha
    $dt_siembra = DateTime::createFromFormat('Y-m-d', $fecha_siembra);
    if (!$dt_siembra) {
        $_SESSION['toast'] = ['text' => 'Fecha de siembra inválida.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    // Validar lote disponible
    if (!$cultivo->loteDisponible($id_lote)) {
        $_SESSION['toast'] = ['text' => 'El lote seleccionado no está disponible.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    // Obtener días de cosecha y código de tipo
    $stmt = $db->prepare(
        "SELECT v.dias_cosecha_promedio, tc.codigo AS tipo_codigo
         FROM variedades v
         JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
         WHERE v.id_variedad = :id AND v.activo = 1 LIMIT 1"
    );
    $stmt->execute([':id' => $id_variedad]);
    $variedad = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variedad) {
        $_SESSION['toast'] = ['text' => 'Variedad no encontrada.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    // Cálculo automático de fecha de cosecha
    $fecha_cosecha = (clone $dt_siembra)->modify('+' . $variedad['dias_cosecha_promedio'] . ' days')->format('Y-m-d');

    // Generar código único
    $codigo = $cultivo->generarCodigo($variedad['tipo_codigo'], $id_lote);

    $idCultivo = $cultivo->crear([
        'id_lote'              => $id_lote,
        'id_variedad'          => $id_variedad,
        'id_registrado_por'    => $_SESSION['id_usuario'],
        'codigo'               => $codigo,
        'fecha_siembra'        => $fecha_siembra,
        'fecha_cosecha_estimada' => $fecha_cosecha,
        'observaciones'        => $observaciones,
        'fotografia'           => $fotografia,
    ]);

    // Notificar a trabajadores asignados al lote
    if ($notif) {
        $trabStmt = $db->prepare("SELECT id_usuario FROM usuarios_lotes WHERE id_lote = :l AND activo = 1");
        $trabStmt->execute([':l' => $id_lote]);
        foreach ($trabStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $notif->crear($uid, 'cultivo', '🌱 Nuevo cultivo en tu lote',
                "Se registró el cultivo {$codigo}. Cosecha estimada: " . date('d/m/Y', strtotime($fecha_cosecha)),
                'media', $idCultivo);
        }
    }

    $_SESSION['toast'] = [
        'text' => "Cultivo {$codigo} registrado. Cosecha estimada: " . date('d/m/Y', strtotime($fecha_cosecha)),
        'type' => 'success'
    ];
    header('Location: ../views/dashboards/admin.php#cultivos');
    exit;
}

function editarCultivo($cultivo, $db, $notif = null) {
    $id          = (int)($_POST['id_cultivo']   ?? 0);
    $id_variedad = (int)($_POST['id_variedad']  ?? 0);
    $estado      = trim($_POST['estado']        ?? '');
    $fecha_siembra = trim($_POST['fecha_siembra'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    $fotografia = null;
    if (!empty($_FILES['fotografia']['name'])) {
        $uploadDir = dirname(__DIR__) . '/public/storage/fotos/';
        $fotografia = subirArchivoAux($_FILES['fotografia'], $uploadDir);
        if (!$fotografia) {
            $_SESSION['toast'] = ['text' => 'Error en imagen: Solo JPG/PNG/WEBP hasta 5MB.', 'type' => 'error'];
            header('Location: ../views/dashboards/admin.php#cultivos'); exit;
        }
    }

    $estados_validos = ['sembrado','desarrollo','maduro','cosechado'];
    if (!$id || !$id_variedad || empty($fecha_siembra) || !in_array($estado, $estados_validos)) {
        $_SESSION['toast'] = ['text' => 'Datos inválidos para editar el cultivo.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $fecha_siembra);
    if (!$dt) {
        $_SESSION['toast'] = ['text' => 'Fecha de siembra inválida.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    // Recalcular fecha cosecha según variedad
    $stmt = $db->prepare("SELECT dias_cosecha_promedio FROM variedades WHERE id_variedad = :id AND activo = 1 LIMIT 1");
    $stmt->execute([':id' => $id_variedad]);
    $var = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$var) {
        $_SESSION['toast'] = ['text' => 'Variedad no encontrada.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    $fecha_cosecha = (clone $dt)->modify('+' . $var['dias_cosecha_promedio'] . ' days')->format('Y-m-d');

    $cultivo->editar($id, [
        'id_variedad'           => $id_variedad,
        'estado'                => $estado,
        'fecha_siembra'         => $fecha_siembra,
        'fecha_cosecha_estimada'=> $fecha_cosecha,
        'observaciones'         => $observaciones,
        'fotografia'            => $fotografia
    ]);

    // Notificar a trabajadores del lote si el estado cambió a maduro
    if ($notif && $estado === 'maduro') {
        $loteStmt = $db->prepare("SELECT id_lote, codigo FROM cultivos WHERE id_cultivo = :id LIMIT 1");
        $loteStmt->execute([':id' => $id]);
        $cRow = $loteStmt->fetch(PDO::FETCH_ASSOC);
        if ($cRow) {
            $trabStmt = $db->prepare("SELECT id_usuario FROM usuarios_lotes WHERE id_lote = :l AND activo = 1");
            $trabStmt->execute([':l' => $cRow['id_lote']]);
            foreach ($trabStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $notif->crear($uid, 'cultivo', '🌾 Cultivo listo para cosechar',
                    "El cultivo {$cRow['codigo']} está en estado Maduro y listo para cosechar.", 'alta', $id);
            }
        }
    }

    $_SESSION['toast'] = ['text' => 'Cultivo actualizado correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#cultivos');
    exit;
}

function eliminarCultivo($cultivo, $db = null, $notif = null) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $_SESSION['toast'] = ['text' => 'ID de cultivo inválido.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#cultivos'); exit;
    }

    // Notificar trabajadores antes de eliminar
    if ($notif && $db) {
        $cInfo = $db->prepare("SELECT c.codigo, c.id_lote FROM cultivos c WHERE c.id_cultivo = :id LIMIT 1");
        $cInfo->execute([':id' => $id]);
        $cRow = $cInfo->fetch(PDO::FETCH_ASSOC);
        if ($cRow) {
            $trabStmt = $db->prepare("SELECT id_usuario FROM usuarios_lotes WHERE id_lote = :l AND activo = 1");
            $trabStmt->execute([':l' => $cRow['id_lote']]);
            foreach ($trabStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $notif->crear($uid, 'cultivo', '🗑️ Cultivo eliminado',
                    "El cultivo {$cRow['codigo']} fue eliminado del sistema.", 'baja');
            }
        }
    }

    $cultivo->eliminar($id);
    $_SESSION['toast'] = ['text' => 'Cultivo eliminado y lote liberado correctamente.', 'type' => 'success'];
    header('Location: ../views/dashboards/admin.php#cultivos');
    exit;
}

function subirArchivoAux(array $file, string $dir): string|false
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

    return $nombre;
}
?>
