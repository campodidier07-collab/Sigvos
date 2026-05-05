<?php
$rootPath = dirname(__DIR__);

// Fallback: si viene PHPSESSID por GET, usarlo (para fetch AJAX sin cookie)
if (!empty($_GET['PHPSESSID']) && session_status() === PHP_SESSION_NONE) {
    session_id(preg_replace('/[^a-zA-Z0-9,-]/', '', $_GET['PHPSESSID']));
}

require_once $rootPath . '/config/session.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    if (!empty($_GET['accion']) && $_GET['accion'] === 'ajax') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sesión expirada']);
        exit;
    }
    header('Location: ../views/auth/login.php'); exit;
}
$dompdfPath = $rootPath . '/vendor/dompdf/vendor/autoload.php';

if (file_exists($dompdfPath)) {
    require_once $dompdfPath;
}

require_once $rootPath . '/config/database.php';

$db          = (new Database())->conectar();
$accion      = $_GET['accion']  ?? '';
$tipo        = $_GET['tipo']    ?? 'produccion';
$fecha_desde = $_GET['desde']   ?? date('Y-01-01');
$fecha_hasta = $_GET['hasta']   ?? date('Y-m-d');
$id_tipo     = (int)($_GET['id_tipo'] ?? 0);

// ── Función central: consultas de datos ───────────────────────────────────
function obtenerDatosReporte(PDO $db, string $tipo, string $desde, string $hasta, int $id_tipo): array {
    $titulo = ''; $subtitulo = '';

    switch ($tipo) {
        case 'produccion':
            $titulo    = 'Reporte de Producción Comparativo entre Lotes';
            $subtitulo = 'Análisis de rendimiento y productividad por lote';
            $sql = "SELECT l.identificador AS lote_id, l.nombre AS lote_nombre, l.area_ha,
                           COALESCE(MAX(tc.nombre),'—') AS tipo_cultivo,
                           COUNT(c.id_cultivo) AS total_cultivos,
                           COALESCE(SUM(c.cantidad_cosechada_kg),0) AS total_kg,
                           COALESCE(AVG(c.cantidad_cosechada_kg),0) AS promedio_kg,
                           COALESCE(MAX(c.cantidad_cosechada_kg),0) AS max_kg,
                           COALESCE(SUM(c.cantidad_cosechada_kg)/NULLIF(l.area_ha,0),0) AS kg_por_ha
                    FROM lotes l
                    LEFT JOIN cultivos c ON c.id_lote = l.id_lote AND c.estado = 'cosechado'
                        AND c.fecha_cosecha_real BETWEEN :desde AND :hasta
                    LEFT JOIN variedades v ON c.id_variedad = v.id_variedad
                    LEFT JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
                    WHERE l.activo = 1";
            $params = [':desde' => $desde, ':hasta' => $hasta];
            if ($id_tipo) { $sql .= " AND tc.id_tipo = :id_tipo"; $params[':id_tipo'] = $id_tipo; }
            $sql .= " GROUP BY l.id_lote, l.identificador, l.nombre, l.area_ha ORDER BY total_kg DESC";
            break;

        case 'actividades':
            $titulo    = 'Reporte de Actividades Realizadas en Período';
            $subtitulo = 'Actividades ejecutadas en el período seleccionado';
            $sql = "SELECT ta.nombre AS tipo_actividad,
                           l.identificador AS lote_id, l.nombre AS lote_nombre,
                           c.codigo AS cultivo_codigo,
                           COALESCE(u.nombre,'Sin asignar') AS trabajador,
                           a.estado, a.fecha_programada, a.descripcion,
                           tc.nombre AS tipo_cultivo
                    FROM actividades a
                    JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
                    JOIN cultivos c ON a.id_cultivo = c.id_cultivo
                    JOIN lotes l ON c.id_lote = l.id_lote
                    JOIN variedades v ON c.id_variedad = v.id_variedad
                    JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
                    LEFT JOIN usuarios u ON a.id_asignado_a = u.id_usuario
                    WHERE a.fecha_programada BETWEEN :desde AND :hasta";
            $params = [':desde' => $desde, ':hasta' => $hasta];
            if ($id_tipo) { $sql .= " AND tc.id_tipo = :id_tipo"; $params[':id_tipo'] = $id_tipo; }
            $sql .= " ORDER BY a.fecha_programada DESC";
            break;

        case 'cosechas':
            $titulo    = 'Reporte de Próximas Cosechas';
            $subtitulo = 'Cultivos con cosecha estimada en el período indicado';
            $sql = "SELECT c.codigo, c.fecha_siembra, c.fecha_cosecha_estimada,
                           DATEDIFF(c.fecha_cosecha_estimada, CURDATE()) AS dias_restantes,
                           c.estado,
                           l.identificador AS lote_id, l.nombre AS lote_nombre, l.area_ha,
                           v.nombre AS variedad_nombre, tc.nombre AS tipo_cultivo,
                           COALESCE(u.nombre,'Sin asignar') AS trabajador
                    FROM cultivos c
                    JOIN lotes l ON c.id_lote = l.id_lote
                    JOIN variedades v ON c.id_variedad = v.id_variedad
                    JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
                    LEFT JOIN usuarios_lotes ul ON ul.id_lote = l.id_lote AND ul.activo = 1
                    LEFT JOIN usuarios u ON ul.id_usuario = u.id_usuario
                    WHERE c.estado != 'cosechado'
                      AND c.fecha_cosecha_estimada BETWEEN CURDATE() AND :hasta";
            $params = [':hasta' => $hasta];
            if ($id_tipo) { $sql .= " AND tc.id_tipo = :id_tipo"; $params[':id_tipo'] = $id_tipo; }
            $sql .= " ORDER BY c.fecha_cosecha_estimada ASC";
            break;

        default:
            return ['datos' => [], 'titulo' => '', 'subtitulo' => ''];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return ['datos' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'titulo' => $titulo, 'subtitulo' => $subtitulo];
}

// ── Función central: métricas resumen ─────────────────────────────────────
function obtenerMetricas(PDO $db, string $desde, string $hasta): array {
    $stmt = $db->prepare(
        "SELECT
            (SELECT COUNT(*) FROM cultivos WHERE estado NOT IN ('cosechado', 'cancelado')) AS cultivos_activos,
            (SELECT COUNT(*) FROM cultivos WHERE estado='cosechado'
                AND fecha_cosecha_real BETWEEN :d1 AND :h1) AS cosechas_periodo,
            (SELECT COALESCE(SUM(cantidad_cosechada_kg),0) FROM cultivos WHERE estado='cosechado'
                AND fecha_cosecha_real BETWEEN :d2 AND :h2) AS kg_total,
            (SELECT COUNT(*) FROM actividades WHERE estado='completada'
                AND fecha_programada BETWEEN :d3 AND :h3) AS actividades_completadas,
            (SELECT COUNT(*) FROM actividades WHERE estado='pendiente') AS actividades_pendientes,
            (SELECT COUNT(*) FROM cultivos WHERE fecha_cosecha_estimada BETWEEN CURDATE()
                AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND estado NOT IN ('cosechado', 'cancelado')) AS cosechas_proximas,
            (SELECT COUNT(*) FROM lotes WHERE activo=1) AS total_lotes"
    );
    $stmt->execute([':d1'=>$desde,':h1'=>$hasta,':d2'=>$desde,':h2'=>$hasta,':d3'=>$desde,':h3'=>$hasta]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Acción: JSON para AJAX ─────────────────────────────────────────────────
if ($accion === 'ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $reporte  = obtenerDatosReporte($db, $tipo, $fecha_desde, $fecha_hasta, $id_tipo);
    $metricas = obtenerMetricas($db, $fecha_desde, $fecha_hasta);
    echo json_encode([
        'ok'        => true,
        'tipo'      => $tipo,
        'titulo'    => $reporte['titulo'],
        'subtitulo' => $reporte['subtitulo'],
        'total'     => count($reporte['datos']),
        'datos'     => $reporte['datos'],
        'metricas'  => $metricas,
    ]);
    exit;
}

// ── Acción: PDF ────────────────────────────────────────────────────────────
if ($accion === 'pdf') {
    if (!file_exists($dompdfPath)) { die('Error: dompdf no encontrado en ' . $dompdfPath); }

    // Aumentar límites para Dompdf
    ini_set('memory_limit', '512M');
    set_time_limit(120);

    try {

        $reporte  = obtenerDatosReporte($db, $tipo, $fecha_desde, $fecha_hasta, $id_tipo);
        $metricas = obtenerMetricas($db, $fecha_desde, $fecha_hasta);
        $datos    = $reporte['datos'];
        $titulo   = $reporte['titulo'];
        $subtitulo = $reporte['subtitulo'];
        $m        = $metricas;

        if ($id_tipo) {
            $tlStmt = $db->prepare("SELECT nombre FROM tipos_cultivo WHERE id_tipo = ?");
            $tlStmt->execute([$id_tipo]);
            $tipo_label = $tlStmt->fetchColumn() ?: 'Todos';
        } else {
            $tipo_label = 'Todos';
        }

        $generado     = date('d/m/Y H:i');
        $periodo      = date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta));
        $generado_por = htmlspecialchars($_SESSION['nombre'] ?? 'Admin');

        // Logo del reporte (intentar varias rutas)
        $logoPath = $rootPath . '/public/img/icono.png';
        if (!file_exists($logoPath)) {
            $logoPath = $rootPath . '/public/img/icono-pagina.png';
        }
        
        $logoB64 = '';
        if (file_exists($logoPath)) {
            $logoB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }


        ob_start();
        include_once $rootPath . '/views/templates/reporte_pdf.php';
        $html = ob_get_clean();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Necesario para fuentes externas o imágenes
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', $rootPath);


        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('SIGVOS_' . $tipo . '_' . date('Ymd_Hi') . '.pdf', ['Attachment' => true]);
        exit;
    } catch (\Throwable $e) {
        if (ob_get_length()) ob_end_clean();
        
        // Log para el desarrollador
        error_log("SIGVOS PDF Error: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
        
        if (isset($_GET['debug'])) {
            die("<h3>Error al generar PDF</h3>" . 
                "<p><b>Mensaje:</b> " . htmlspecialchars($e->getMessage()) . "</p>" .
                "<p><b>Archivo:</b> " . $e->getFile() . ":" . $e->getLine() . "</p>" .
                "<pre>" . $e->getTraceAsString() . "</pre>");
        }

        $_SESSION['toast'] = ['text' => 'Error crítico al generar el PDF. Revisa los logs.', 'type' => 'error'];
        header('Location: ../views/dashboards/admin.php#reportes');
        exit;
    }

}

// Si no hay acción reconocida, redirigir al dashboard
header('Location: ../views/dashboards/admin.php#reportes');
exit;
