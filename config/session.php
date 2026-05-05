<?php
/**
 * SIGVOS — Helper de sesión segura
 * Centraliza el arranque de sesión y funciones de seguridad CSRF.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de la cookie de sesión
    session_set_cookie_params([
        'lifetime' => 0,          // Hasta que se cierre el navegador
        'path'     => '/',
        'secure'   => false,      // Cambiar a true si usas HTTPS
        'httponly' => true,       // Impide acceso desde JavaScript
        'samesite' => 'Lax',      // Protección adicional CSRF
    ]);
    session_start();
}

// ─── CSRF ────────────────────────────────────────────────────────────────────

/**
 * Genera (o reutiliza) el token CSRF de la sesión actual.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Imprime un campo hidden con el token CSRF listo para insertar en formularios.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verifica que el token CSRF del POST sea válido.
 * Finaliza la ejecución con 403 si no lo es.
 *
 * @param bool $json  Si true, responde con JSON en lugar de HTML.
 */
function csrf_verify(bool $json = false): void
{
    $token_recibido = $_POST['_csrf_token'] ?? '';
    if (
        empty($token_recibido) ||
        !hash_equals($_SESSION['_csrf_token'] ?? '', $token_recibido)
    ) {
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token de seguridad inválido. Recarga la página.']);
            exit;
        }
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error — SIGVOS</title>
              <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
              height:100vh;background:#f0fdf4;}.box{text-align:center;padding:2rem;border-radius:1rem;
              background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.08);}h1{color:#065f46;}p{color:#6b7280;}</style>
              </head><body><div class="box"><h1>⛔ Solicitud rechazada</h1>
              <p>Token de seguridad inválido.<br>Por favor, recarga la página e intenta de nuevo.</p>
              <a href="javascript:history.back()" style="color:#3aa574;font-weight:600;">← Volver</a>
              </div></body></html>';
        exit;
    }
    // Rotar el token tras validación exitosa
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

// ─── Remember Me ─────────────────────────────────────────────────────────────

/**
 * Si existe la cookie "sigvos_remember" y la sesión no está activa,
 * intenta restaurar la sesión desde la BD.
 */
function remember_me_check(PDO $db): void
{
    if (isset($_SESSION['id_usuario'])) {
        return; // Ya hay sesión activa
    }

    $cookie = $_COOKIE['sigvos_remember'] ?? '';
    if (empty($cookie)) {
        return;
    }

    $tokenHash = hash('sha256', $cookie);

    $stmt = $db->prepare(
        "SELECT s.id_usuario, u.nombre, u.correo, u.id_rol, r.nombre AS nombre_rol
         FROM sesiones_usuario s
         JOIN usuarios u ON s.id_usuario = u.id_usuario
         JOIN roles r ON u.id_rol = r.id_rol
         WHERE s.token_hash = :h
           AND s.estado = 'activa'
           AND s.expira_en > NOW()
           AND u.activo = 1
         LIMIT 1"
    );
    $stmt->execute([':h' => $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $_SESSION['id_usuario'] = $row['id_usuario'];
        $_SESSION['nombre']     = $row['nombre'];
        $_SESSION['correo']     = $row['correo'];
        $_SESSION['id_rol']     = $row['id_rol'];
        $_SESSION['rol']        = $row['nombre_rol'];
    } else {
        // Cookie inválida: limpiar
        setcookie('sigvos_remember', '', time() - 3600, '/', '', false, true);
    }
}

/**
 * Crea una cookie "remember me" y registra el token en la BD.
 */
function remember_me_set(PDO $db, int $id_usuario, string $dispositivo, string $ip): void
{
    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expira    = date('Y-m-d H:i:s', time() + 30 * 24 * 3600); // 30 días

    $db->prepare(
        "INSERT INTO sesiones_usuario
             (id_usuario, token_hash, dispositivo, ip_origen, estado, iniciada_en, expira_en)
         VALUES (:u, :h, :d, :ip, 'activa', NOW(), :exp)"
    )->execute([
        ':u'   => $id_usuario,
        ':h'   => $tokenHash,
        ':d'   => $dispositivo,
        ':ip'  => $ip,
        ':exp' => $expira,
    ]);

    setcookie('sigvos_remember', $token, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => false, // true en HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Elimina la cookie "remember me" y marca la sesión como cerrada en BD.
 */
function remember_me_clear(PDO $db): void
{
    $cookie = $_COOKIE['sigvos_remember'] ?? '';
    if ($cookie) {
        $tokenHash = hash('sha256', $cookie);
        $db->prepare(
            "UPDATE sesiones_usuario SET estado = 'cerrada', cerrada_en = NOW() WHERE token_hash = :h"
        )->execute([':h' => $tokenHash]);
        setcookie('sigvos_remember', '', time() - 3600, '/', '', false, true);
    }
}
