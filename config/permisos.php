<?php

function tienePermiso($db, $id_usuario, $codigo_permiso) {
    $stmt = $db->prepare(
        "SELECT 1
         FROM usuarios u
         JOIN roles_permisos rp ON rp.id_rol = u.id_rol
         JOIN permisos p ON p.id_permiso = rp.id_permiso
         WHERE u.id_usuario = :uid
           AND p.codigo = :codigo
           AND u.activo = 1
         LIMIT 1"
    );
    $stmt->execute([':uid' => $id_usuario, ':codigo' => $codigo_permiso]);
    return (bool)$stmt->fetchColumn();
}

function esAdmin($db, $id_usuario) {
    $stmt = $db->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :uid AND activo = 1 LIMIT 1");
    $stmt->execute([':uid' => $id_usuario]);
    return (int)$stmt->fetchColumn() === 1;
}

function requirePermiso($db, $id_usuario, $codigo_permiso, $json = false) {
    if (esAdmin($db, $id_usuario)) {
        return; // admin siempre puede
    }
    if (!tienePermiso($db, $id_usuario, $codigo_permiso)) {
        if ($json) {
            http_response_code(403);
            echo json_encode(['error' => 'Sin permiso para esta acción.']);
            exit;
        }
        // Redirigir al dashboard según el rol del usuario
        $stmt = $db->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :uid LIMIT 1");
        $stmt->execute([':uid' => $id_usuario]);
        $rol = (int)$stmt->fetchColumn();
        $_SESSION['toast'] = ['text' => 'No tienes permiso para realizar esta acción.', 'type' => 'error'];
        $dest = $rol === 1 ? '../views/dashboards/admin.php' : '../views/dashboards/trabajador.php';
        header('Location: ' . $dest); exit;
    }
}
