<?php
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/session.php';
if (!isset($_SESSION['id_usuario'])) { header('Location: ../auth/login.php'); exit; }
if ((int)($_SESSION['id_rol'] ?? 0) !== 1) { header('Location: trabajador.php'); exit; }

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once $rootPath . '/config/database.php';

$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Admin');
$iniciales      = strtoupper(substr($_SESSION['nombre'] ?? 'A', 0, 2));

$fecha_hoy = (new DateTime())->format('l, j \d\e F \d\e Y');
$dias  = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$meses = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
$fecha_es = strtr($fecha_hoy, array_merge($dias, $meses));

$db = (new Database())->conectar();
$total_usuarios  = $db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 2")->fetchColumn();
$total_activos   = $db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 2 AND activo = 1")->fetchColumn();
$total_trabajadores = $db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol != 1")->fetchColumn();

$stmt  = $db->query("SELECT id_usuario, nombre, correo, telefono, activo, creado_en, ultimo_acceso FROM usuarios WHERE id_rol = 2 ORDER BY creado_en DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lotes
require_once $rootPath . '/models/Lote.php';
$loteModel  = new Lote($db);
$lotes      = $loteModel->obtenerTodos();
$estadosLotes = $loteModel->obtenerEstados();
$tiposCultivo = $db->query("SELECT id_tipo, nombre FROM tipos_cultivo WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Cultivos
require_once $rootPath . '/models/Cultivo.php';
$cultivoModel    = new Cultivo($db);
$cultivos        = $cultivoModel->obtenerTodos();
$variedades      = $cultivoModel->obtenerVariedades();
$lotesDisponibles = $cultivoModel->obtenerLotesDisponibles();

// Asignaciones
require_once $rootPath . '/models/AsignacionLote.php';
$asignacionModel      = new AsignacionLote($db);
$asignacionesActivas  = $asignacionModel->obtenerAsignacionesActivas();
$trabajadores         = $asignacionModel->obtenerTrabajadores();
$lotesParaAsignar     = $asignacionModel->obtenerLotesDisponibles();

// Asignaciones de cultivos (cultivos activos asignados a trabajadores)
$asignacionesCultivos = $db->query(
    "SELECT c.id_cultivo, c.codigo, c.estado, c.fecha_cosecha_estimada,
            l.identificador AS lote_id, l.nombre AS lote_nombre,
            v.nombre AS variedad_nombre, tc.nombre AS tipo_nombre,
            u.id_usuario, u.nombre AS trabajador, u.correo,
            ul.id_asignacion, ul.creado_en
     FROM cultivos c
     JOIN lotes l ON c.id_lote = l.id_lote
     JOIN variedades v ON c.id_variedad = v.id_variedad
     JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
     JOIN usuarios_lotes ul ON ul.id_lote = l.id_lote AND ul.activo = 1
     JOIN usuarios u ON ul.id_usuario = u.id_usuario
     WHERE c.activo_en_lote IS NOT NULL
     ORDER BY c.fecha_cosecha_estimada ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Actividades
require_once $rootPath . '/models/Actividad.php';
$actividadModel    = new Actividad($db);
$actividades       = $actividadModel->obtenerTodas();
$tiposActividad    = $actividadModel->obtenerTipos();
$cultivosActivos   = $actividadModel->obtenerCultivosActivos();
$trabajadoresAct   = $actividadModel->obtenerTrabajadores();

// Cosechas
$historialCosechas = $cultivoModel->obtenerHistorialCosechas();
$cultivosParaCosechar = array_filter($cultivos, fn($c) => in_array($c['estado'], ['sembrado','desarrollo','maduro']));

// -- KPIs dinómicos -------------------------------------------------------
$kpi_cultivos_activos   = $db->query("SELECT COUNT(*) FROM cultivos WHERE activo_en_lote IS NOT NULL")->fetchColumn();
$kpi_act_pendientes     = $db->query("SELECT COUNT(*) FROM actividades WHERE estado = 'pendiente'")->fetchColumn();
$kpi_cosechas_mes       = $db->query("SELECT COUNT(*) FROM cultivos WHERE estado = 'cosechado' AND MONTH(fecha_cosecha_real) = MONTH(CURDATE()) AND YEAR(fecha_cosecha_real) = YEAR(CURDATE())")->fetchColumn();

// Tipos cultivo para reportes (reutiliza $tiposCultivo ya cargado arriba)
$tipos_cultivo_reporte = $tiposCultivo;


// Perfil del admin
$adminPerfil = $db->prepare("SELECT id_usuario, nombre, correo, telefono, creado_en, ultimo_acceso FROM usuarios WHERE id_usuario = :id LIMIT 1");
$adminPerfil->execute([':id' => $_SESSION['id_usuario']]);
$adminPerfil = $adminPerfil->fetch(PDO::FETCH_ASSOC);
// foto_perfil: cargar solo si la columna existe
try {
    $fotoStmt = $db->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $fotoStmt->execute([':id' => $_SESSION['id_usuario']]);
    $adminPerfil['foto_perfil'] = $fotoStmt->fetchColumn() ?: null;
} catch (PDOException $e) {
    $adminPerfil['foto_perfil'] = null;
}

// Notificaciones
$notifs = $db->prepare(
    "SELECT id_notificacion, tipo, prioridad, titulo, mensaje, leida, creada_en
     FROM notificaciones WHERE id_usuario = :u ORDER BY leida ASC, creada_en DESC LIMIT 30"
);
$notifs->execute([':u' => $_SESSION['id_usuario']]);
$notificaciones   = $notifs->fetchAll(PDO::FETCH_ASSOC);
$notifs_no_leidas = count(array_filter($notificaciones, fn($n) => !$n['leida']));

// Última sincronización
$ultimaSync = $db->prepare("SELECT finalizada_en FROM sincronizaciones WHERE id_usuario = :u ORDER BY finalizada_en DESC LIMIT 1");
$ultimaSync->execute([':u' => $_SESSION['id_usuario']]);
$ultimaSyncFecha = $ultimaSync->fetchColumn() ?: null;
$cultivosFoto = $db->query(
    "SELECT c.id_cultivo, c.codigo, c.fotografia, c.estado,
            l.identificador AS lote_id, l.nombre AS lote_nombre
     FROM cultivos c
     JOIN lotes l ON c.id_lote = l.id_lote
     WHERE c.activo_en_lote IS NOT NULL
     ORDER BY c.codigo"
)->fetchAll(PDO::FETCH_ASSOC);

$actividadesFoto = $db->query(
    "SELECT a.id_actividad, a.fotografia, a.descripcion, a.fecha_programada,
            ta.nombre AS tipo_actividad,
            l.identificador AS lote_id,
            u.nombre AS trabajador
     FROM actividades a
     JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
     JOIN cultivos c ON a.id_cultivo = c.id_cultivo
     JOIN lotes l ON c.id_lote = l.id_lote
     LEFT JOIN usuarios u ON a.id_asignado_a = u.id_usuario
     WHERE a.fotografia IS NOT NULL AND a.fotografia != ''
     ORDER BY a.actualizado_en DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Datos para el calendario (actividades + cosechas)
$eventosCalendario = [];
foreach ($actividades as $a) {
    if (!$a['fecha_programada']) continue;
    $eventosCalendario[] = [
        'fecha'     => $a['fecha_programada'],
        'titulo'    => $a['tipo_actividad'] . ' — ' . $a['lote_id'],
        'tipo'      => $a['tipo_actividad'],
        'estado'    => $a['estado'],
        'trabajador'=> $a['trabajador'] ?? 'Sin asignar',
        'cultivo'   => $a['cultivo_codigo'],
        'lote'      => $a['lote_id'] . ' — ' . $a['lote_nombre'],
        'descripcion'=> $a['descripcion'] ?? '',
        'es_cosecha'=> false,
    ];
}
foreach ($cultivos as $c) {
    $eventosCalendario[] = [
        'fecha'     => $c['fecha_cosecha_estimada'],
        'titulo'    => 'Cosecha: ' . $c['codigo'],
        'tipo'      => '__cosecha__',
        'estado'    => $c['estado'],
        'trabajador'=> '',
        'cultivo'   => $c['codigo'],
        'lote'      => $c['lote_id'] . ' — ' . $c['lote_nombre'],
        'descripcion'=> $c['variedad_nombre'] . ' — ' . $c['tipo_nombre'],
        'es_cosecha'=> true,
    ];
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGVOS | Panel Administrador</title>
    <link rel="icon" href="../../public/img/icono-pagina.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --sidebar-w:280px; --green-main:#3aa574; }
        html { font-size: 18px; }
        body { font-family:'Inter', sans-serif; background-color:#f2fbf5; color:#1d4533; font-size:1rem; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
        #sidebar { width:var(--sidebar-w); height:100vh; position:fixed; top:0; left:0; z-index:50; background:linear-gradient(180deg, #1d4533 0%, #25694a 100%); color:white; padding:24px 16px; box-shadow:8px 0 30px rgba(29,69,51,.15); display:flex; flex-direction:column; overflow:hidden; }
        #sidebar nav { overflow-y:auto; flex:1; }
        #sidebar nav::-webkit-scrollbar { width:3px; } #sidebar nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.2); border-radius:10px; }
        #main-content { margin-left:var(--sidebar-w); min-height:100vh; display:flex; flex-direction:column; }
        .brand-box { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px); }
        .nav-link { display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:14px; color:rgba(255,255,255,0.7); font-size:0.85rem; font-weight:500; text-decoration:none; transition:.3s ease; cursor:pointer; }
        .nav-link:hover { background:rgba(255,255,255,0.1); color:#fff; transform: translateX(4px); }
        .nav-link.active { background:rgba(58,165,116,0.3); color:#fff; border-left: 3px solid #3aa574; }
        .glass-card { background:rgba(255,255,255,0.95); backdrop-filter: blur(10px); border:1px solid rgba(58,165,116,0.15); box-shadow:0 8px 30px rgba(29,69,51,0.04); }
        .status-pill { display:inline-block; padding:4px 12px; border-radius:999px; font-size:0.75rem; font-weight:600; }
        .status-ok { background:#e1f6e8; color:#25694a; } .status-mid { background:#eef7ff; color:#2563eb; } .status-warn { background:#fff6df; color:#b7791f; }
        .mini-dot { width:10px; height:10px; border-radius:999px; margin-top:5px; flex-shrink:0; }
        .hero-panel { background:linear-gradient(135deg, #1d4533 0%, #2b845a 100%); border-radius:28px; padding:28px; color:white; position:relative; overflow:hidden; box-shadow: 0 15px 35px rgba(43,132,90,0.2); }
        .hero-panel::after { content:""; position:absolute; right:-60px; bottom:-60px; width:220px; height:220px; background:radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius:50%; }
        .module { display:none; } .module.active { display:block; animation:fadeUp .4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeUp { from{opacity:0;transform:translateY(15px)} to{opacity:1;transform:translateY(0)} }
        .trow:hover { background:#f2fbf5; transition: background 0.2s; }
        .toggle { width:42px; height:23px; border-radius:12px; position:relative; cursor:pointer; transition:background .3s; display:inline-block; flex-shrink:0; }
        .toggle::after { content:''; position:absolute; top:3px; left:3px; width:17px; height:17px; border-radius:50%; background:white; transition:transform .3s cubic-bezier(0.4, 0.0, 0.2, 1); box-shadow:0 2px 5px rgba(0,0,0,.2); }
        .toggle.on { background:var(--green-main); } .toggle.off { background:#cbd5e1; } .toggle.on::after { transform:translateX(19px); }
        .modal-overlay { position:fixed; inset:0; background:rgba(15,39,29,.6); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; z-index:999; padding:16px; }
        .modal-box { background:white; border-radius:28px; box-shadow:0 30px 60px rgba(0,0,0,.2); width:100%; max-width:520px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; }
        .badge-admin { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:999px; font-size:0.65rem; font-weight:700; border: 1px solid #fde68a; }
        @media(max-width:1024px){ #sidebar{transform:translateX(-100%); transition: transform 0.3s; z-index: 1000;} #main-content{margin-left:0;} .sidebar-open #sidebar {transform:translateX(0);} }
        ::-webkit-scrollbar{width:6px;} ::-webkit-scrollbar-thumb{background:#96d9b4;border-radius:10px;} ::-webkit-scrollbar-track {background: transparent;}
        
        /* Adiciones visuales agro */
        .btn-agro { background-color: #3aa574; color: white; transition: all 0.3s; box-shadow: 0 4px 14px rgba(58,165,116,0.3); }
        .btn-agro:hover { background-color: #2b845a; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(58,165,116,0.4); }
    </style>
</head>
<body>
<?php if ($toast): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: t => {
            t.addEventListener('mouseenter', Swal.stopTimer);
            t.addEventListener('mouseleave', Swal.resumeTimer);
        }
    }).fire({
        icon: <?= json_encode($toast['type'] === 'success' ? 'success' : ($toast['type'] === 'warning' ? 'warning' : 'error')) ?>,
        title: <?= json_encode($toast['text']) ?>
    });
});
</script>
<?php endif; ?>
<?php include '../../views/partials/admin/sidebar.php'; ?>
<!-- MAIN -->
<div id="main-content">
<?php include '../../views/partials/admin/header.php'; ?>

    <main class="flex-1 px-6 pb-6 pt-3">

        <!-- MÓDULO: PANEL ADMIN -->
        <div id="module-panel" class="module active">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Bienvenido, <?= $nombre_usuario ?></h2>
                <p class="text-sm text-slate-500 mt-1">Vista completa del sistema SIGVOS.</p>
            </div>
            <div class="hero-panel mb-6">
                <div class="relative z-10 max-w-xl">
                    <p class="text-white/70 text-xs uppercase tracking-widest mb-2">Administración general</p>
                    <h3 class="text-2xl font-extrabold leading-snug mb-3">Control total de la finca</h3>
                    <p class="text-white/80 text-sm leading-relaxed mb-5">Gestiona usuarios, cultivos, lotes, actividades y genera reportes estratégicos desde un solo lugar.</p>
                    <div class="flex flex-wrap gap-2">
                        <span class="bg-white/15 border border-white/10 px-4 py-1.5 rounded-full text-xs"><?= $kpi_cultivos_activos ?> cultivos activos</span>
                        <span class="bg-white/15 border border-white/10 px-4 py-1.5 rounded-full text-xs"><?= $total_activos ?> productores activos</span>
                        <span class="bg-white/15 border border-white/10 px-4 py-1.5 rounded-full text-xs"><?= $kpi_act_pendientes ?> actividades pendientes</span>
                    </div>
                </div>
            </div>
            <!-- KPIs Admin -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4 cursor-pointer hover:shadow-lg transition" onclick="switchModule('trabajadores',document.getElementById('nav-trabajadores'))">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa);"><i class="fas fa-users"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= $total_activos ?><span class="text-sm font-normal text-slate-400">/<?= $total_usuarios ?></span></p><p class="text-xs text-slate-500">Productores activos</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#0b6b4f,#18b57a);"><i class="fas fa-seedling"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= $kpi_cultivos_activos ?></p><p class="text-xs text-slate-500">Cultivos activos</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#b45309,#fbbf24);"><i class="fas fa-bolt"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= $kpi_act_pendientes ?></p><p class="text-xs text-slate-500">Actividades pendientes</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);"><i class="fas fa-chart-line"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= $kpi_cosechas_mes ?></p><p class="text-xs text-slate-500">Cosechas este mes</p></div>
                </div>
            </div>
            <!-- Tabla cultivos + pendientes -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 glass-card rounded-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-5 border-b border-[#d8eee4]">
                        <div><h3 class="font-bold text-[#16332b]">Cultivos activos</h3><p class="text-xs text-slate-500 mt-0.5">Vista resumida de lotes productivos</p></div>
                        <a onclick="switchModule('cultivos',document.getElementById('nav-cultivos'))" class="text-xs font-semibold text-[#0f8f67] hover:underline cursor-pointer">Ver todos</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead><tr class="bg-[#f5fbf8]">
                                <?php foreach (['Código','Variedad','Lote','Fecha estimada','Estado'] as $h): ?>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php
                                $badgePanel = ['sembrado'=>'status-mid','desarrollo'=>'status-warn','maduro'=>'status-ok','cosechado'=>'status-ok'];
                                $cultivosPanel = array_slice($cultivos, 0, 5);
                                if (empty($cultivosPanel)): ?>
                                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 text-sm">No hay cultivos activos registrados.</td></tr>
                                <?php else: foreach ($cultivosPanel as $c):
                                    $cls = $badgePanel[$c['estado']] ?? 'status-mid';
                                ?>
                                <tr class="border-b border-[#eef4f1] hover:bg-[#f5fbf8] transition">
                                    <td class="px-6 py-4 text-sm font-semibold text-[#0f8f67]"><?= htmlspecialchars($c['codigo']) ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($c['variedad_nombre']) ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-500"><?= htmlspecialchars($c['lote_id'] . ' — ' . $c['lote_nombre']) ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-500"><?= date('d/m/Y', strtotime($c['fecha_cosecha_estimada'])) ?></td>
                                    <td class="px-6 py-4"><span class="status-pill <?= $cls ?>"><?= ucfirst($c['estado']) ?></span></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="space-y-5">
                    <div class="glass-card rounded-2xl p-6">
                        <div class="mb-4"><h3 class="font-bold text-[#16332b]">Pendientes</h3><p class="text-xs text-slate-500 mt-0.5">Actividades que requieren atención</p></div>
                        <?php
                        $pendientesPanel = array_slice(array_filter($actividades, fn($a) => $a['estado'] === 'pendiente'), 0, 3);
                        $coloresPend = ['bg-red-500','bg-amber-400','bg-emerald-500'];
                        if (empty($pendientesPanel)): ?>
                        <p class="text-sm text-slate-400 text-center py-4">Sin actividades pendientes.</p>
                        <?php else: foreach (array_values($pendientesPanel) as $i => $p): ?>
                        <div class="flex items-start gap-3 py-3 <?= $i > 0 ? 'border-t border-[#eef4f1]' : '' ?>">
                            <span class="mini-dot <?= $coloresPend[$i % 3] ?>"></span>
                            <div>
                                <p class="font-semibold text-sm text-[#16332b]"><?= htmlspecialchars($p['tipo_actividad']) ?> — <?= htmlspecialchars($p['lote_id']) ?></p>
                                <p class="text-xs text-slate-400"><?= htmlspecialchars($p['descripcion']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="glass-card rounded-2xl p-6">
                        <div class="mb-4"><h3 class="font-bold text-[#16332b]">Actividad reciente</h3><p class="text-xs text-slate-500 mt-0.5">Últimas 12 semanas</p></div>
                        <canvas id="actividadesChart" height="140"></canvas>
                    </div>
                </div>
            </div>
        </div>



        <!-- MÓDULO: CULTIVOS -->
        <div id="module-cultivos" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Cultivos</h2>
                    <p class="text-sm text-slate-500 mt-1">Registro y seguimiento de cultivos por lote.</p>
                </div>
                <button onclick="document.getElementById('modal-cultivo').classList.remove('hidden'); document.getElementById('modal-cultivo').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nuevo Cultivo
                </button>
            </div>

            <?php if (empty($cultivos)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-seedling text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">No hay cultivos registrados aón.</p>
            </div>
            <?php else: ?>
            <div class="glass-card rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f0fdf4]">
                        <tr>
                            <?php foreach (['Img','Código','Variedad','Tipo','Lote','Siembra','Cosecha estimada','Estado','Acciones'] as $h): ?>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0fdf4]">
                        <?php
                        $badgeCultivo = [
                            'sembrado'   => 'bg-blue-100 text-blue-700',
                            'desarrollo' => 'bg-yellow-100 text-yellow-700',
                            'maduro'     => 'bg-emerald-100 text-emerald-700',
                            'cosechado'  => 'bg-slate-100 text-slate-600',
                        ];
                        foreach ($cultivos as $c):
                            $cls = $badgeCultivo[$c['estado']] ?? 'bg-slate-100 text-slate-600';
                        ?>
                        <tr class="hover:bg-[#f9fefb] transition">
                            <td class="px-5 py-3">
                                <?php if (!empty($c['fotografia'])): ?>
                                <img src="../../<?= htmlspecialchars($c['fotografia']) ?>" alt="Cultivo" class="w-10 h-10 object-cover rounded-lg shadow-sm cursor-pointer hover:opacity-80 transition" onclick="abrirVisorImagen('../../<?= htmlspecialchars($c['fotografia']) ?>', 'Cultivo: <?= htmlspecialchars($c['codigo']) ?>', 'Lote: <?= htmlspecialchars($c['lote_id']) ?>')">
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center text-gray-400"><i class="fas fa-seedling text-sm"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 font-bold text-[#065f46]"><?= htmlspecialchars($c['codigo']) ?></td>
                            <td class="px-5 py-3 text-gray-700"><?= htmlspecialchars($c['variedad_nombre']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($c['tipo_nombre']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($c['lote_id'] . ' — ' . $c['lote_nombre']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= date('d/m/Y', strtotime($c['fecha_siembra'])) ?></td>
                            <td class="px-5 py-3 text-gray-700 font-semibold"><?= date('d/m/Y', strtotime($c['fecha_cosecha_estimada'])) ?></td>
                            <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $cls ?>"><?= ucfirst($c['estado']) ?></span></td>
                            <td class="px-5 py-3">
                                <div class="flex gap-2">
                                    <button onclick='abrirEditarCultivo(<?= json_encode($c) ?>)'
                                        class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-pen mr-1"></i>Editar
                                    </button>
                                    <a href="#"
                                        onclick="confirmarEliminar(event,'../../controllers/CultivoController.php?accion=eliminar_cultivo&id=<?= $c['id_cultivo'] ?>','¿Eliminar cultivo <?= htmlspecialchars(addslashes($c['codigo'])) ?>?','Esta acción no se puede deshacer.')"
                                        class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-trash mr-1"></i>Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- MODAL: Nuevo Cultivo -->
        <div id="modal-cultivo" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Registrar Nuevo Cultivo</h3>
                    <button onclick="document.getElementById('modal-cultivo').classList.add('hidden'); document.getElementById('modal-cultivo').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="../../controllers/CultivoController.php" method="POST" class="space-y-5" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="crear_cultivo">

                    <div class="grid grid-cols-2 gap-5">
                        <!-- Lote disponible -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Lote disponible *</label>
                            <?php if (empty($lotesDisponibles)): ?>
                            <p class="text-sm text-red-500 bg-red-50 rounded-xl px-4 py-2.5">No hay lotes disponibles.</p>
                            <input type="hidden" name="id_lote" value="">
                            <?php else: ?>
                            <select name="id_lote" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Seleccionar lote</option>
                                <?php foreach ($lotesDisponibles as $l): ?>
                                <option value="<?= $l['id_lote'] ?>"><?= htmlspecialchars($l['identificador'] . ' — ' . $l['nombre'] . ' (' . $l['area_ha'] . ' ha)') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>

                        <!-- Variedad -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Variedad *</label>
                            <select name="id_variedad" id="sel_variedad" required onchange="calcularCosecha()"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Seleccionar variedad</option>
                                <?php
                                $tipoActual = '';
                                foreach ($variedades as $v):
                                    if ($v['tipo_nombre'] !== $tipoActual):
                                        if ($tipoActual !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($v['tipo_nombre']) . '">';
                                        $tipoActual = $v['tipo_nombre'];
                                    endif;
                                ?>
                                <option value="<?= $v['id_variedad'] ?>" data-dias="<?= $v['dias_cosecha_promedio'] ?>">
                                    <?= htmlspecialchars($v['nombre']) ?> (<?= $v['dias_cosecha_promedio'] ?> días)
                                </option>
                                <?php endforeach; if ($tipoActual !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <!-- Fecha siembra -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha de siembra *</label>
                            <input type="date" name="fecha_siembra" id="fecha_siembra" required onchange="calcularCosecha()"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                        </div>

                        <!-- Fecha cosecha estimada (calculada) -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cosecha estimada</label>
                            <div id="cosecha_preview" class="w-full px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-semibold min-h-[42px] flex items-center">
                                Se calculará automáticamente
                            </div>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</label>
                        <textarea name="observaciones" rows="3" placeholder="Notas adicionales sobre el cultivo..."
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Imagen del Cultivo (Opcional)</label>
                        <input type="file" name="fotografia" accept="image/jpeg, image/png, image/webp" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-cultivo').classList.add('hidden'); document.getElementById('modal-cultivo').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit" <?= empty($lotesDisponibles) ? 'disabled' : '' ?>
                            class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Registrar Cultivo
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Editar Cultivo -->
        <div id="modal-editar-cultivo" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Editar Cultivo</h3>
                    <button onclick="document.getElementById('modal-editar-cultivo').classList.add('hidden'); document.getElementById('modal-editar-cultivo').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="../../controllers/CultivoController.php" method="POST" class="space-y-5">
                    <input type="hidden" name="accion" value="editar_cultivo">
                    <input type="hidden" name="id_cultivo" id="ec_id_cultivo">

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Variedad *</label>
                            <select name="id_variedad" id="ec_id_variedad" required onchange="calcularCosechaEditar()"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">— Seleccionar variedad —</option>
                                <?php
                                $tipoActual = '';
                                foreach ($variedades as $v):
                                    if ($v['tipo_nombre'] !== $tipoActual):
                                        if ($tipoActual !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($v['tipo_nombre']) . '">';
                                        $tipoActual = $v['tipo_nombre'];
                                    endif;
                                ?>
                                <option value="<?= $v['id_variedad'] ?>" data-dias="<?= $v['dias_cosecha_promedio'] ?>">
                                    <?= htmlspecialchars($v['nombre']) ?> (<?= $v['dias_cosecha_promedio'] ?> días)
                                </option>
                                <?php endforeach; if ($tipoActual !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Estado *</label>
                            <select name="estado" id="ec_estado" required
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="sembrado">Sembrado</option>
                                <option value="desarrollo">En desarrollo</option>
                                <option value="maduro">Maduro</option>
                                <option value="cosechado">Cosechado</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha de siembra *</label>
                            <input type="date" name="fecha_siembra" id="ec_fecha_siembra" required onchange="calcularCosechaEditar()"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cosecha estimada</label>
                            <div id="ec_cosecha_preview" class="w-full px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-semibold min-h-[42px] flex items-center">
                                —
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</label>
                        <textarea name="observaciones" id="ec_observaciones" rows="3"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-editar-cultivo').classList.add('hidden'); document.getElementById('modal-editar-cultivo').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MÓDULO: LOTES -->
        <div id="module-lotes" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Lotes</h2>
                    <p class="text-sm text-slate-500 mt-1">Gestión y registro de lotes de la finca.</p>
                </div>
                <button onclick="document.getElementById('modal-lote').classList.remove('hidden'); document.getElementById('modal-lote').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nuevo Lote
                </button>
            </div>

            <!-- Resumen de estados -->
            <?php if (!empty($estadosLotes)): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <?php
                $colores = ['disponible'=>'emerald','ocupado'=>'blue','en_descanso'=>'yellow','inactivo'=>'red'];
                foreach ($estadosLotes as $e):
                    $c = $colores[$e['estado']] ?? 'slate';
                ?>
                <div class="glass-card rounded-xl p-4 text-center">
                    <p class="text-2xl font-extrabold text-[#16332b]"><?= $e['total'] ?></p>
                    <p class="text-xs text-slate-500 mt-1 capitalize"><?= str_replace('_',' ', $e['estado']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Tabla de lotes -->
            <?php if (empty($lotes)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-map text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">No hay lotes registrados aón.</p>
            </div>
            <?php else: ?>
            <div class="glass-card rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f0fdf4]">
                        <tr>
                            <?php foreach (['Img','ID','Nombre','Ubicación','Área (ha)','Tipo','Estado','Acciones'] as $h): ?>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0fdf4]">
                        <?php foreach ($lotes as $l): ?>
                        <tr class="hover:bg-[#f9fefb] transition">
                            <td class="px-5 py-3">
                                <?php if (!empty($l['fotografia'])): ?>
                                <img src="../../<?= htmlspecialchars($l['fotografia']) ?>" alt="Lote" class="w-10 h-10 object-cover rounded-lg shadow-sm cursor-pointer hover:opacity-80 transition" onclick="abrirVisorImagen('../../<?= htmlspecialchars($l['fotografia']) ?>', 'Lote: <?= htmlspecialchars($l['nombre']) ?>', 'ID: <?= htmlspecialchars($l['identificador']) ?> - <?= htmlspecialchars($l['ubicacion']) ?>')">
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center text-gray-400"><i class="fas fa-map text-sm"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 font-bold text-[#065f46]"><?= htmlspecialchars($l['identificador']) ?></td>
                            <td class="px-5 py-3 text-gray-700"><?= htmlspecialchars($l['nombre']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($l['ubicacion']) ?></td>
                            <td class="px-5 py-3 text-gray-700"><?= number_format($l['area_ha'], 2) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($l['tipo_nombre'] ?? '—') ?></td>
                            <td class="px-5 py-3">
                                <?php
                                $badge = ['disponible'=>'bg-emerald-100 text-emerald-700','ocupado'=>'bg-blue-100 text-blue-700','en_descanso'=>'bg-yellow-100 text-yellow-700','inactivo'=>'bg-red-100 text-red-700'];
                                $cls = $badge[$l['estado']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $cls ?>"><?= ucfirst(str_replace('_',' ',$l['estado'])) ?></span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex gap-2">
                                    <button onclick='abrirEditarLote(<?= json_encode($l) ?>)'
                                        class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-pen mr-1"></i>Editar
                                    </button>
                                    <a href="#"
                                        onclick="confirmarEliminar(event,'../../controllers/LoteController.php?accion=eliminar_lote&id=<?= $l['id_lote'] ?>','¿Eliminar el lote <?= htmlspecialchars(addslashes($l['nombre'])) ?>?','Esta acción no se puede deshacer.')"
                                        class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-trash mr-1"></i>Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- MODAL: Nuevo Lote -->
        <div id="modal-lote" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Registrar Nuevo Lote</h3>
                    <button onclick="document.getElementById('modal-lote').classList.add('hidden'); document.getElementById('modal-lote').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="../../controllers/LoteController.php" method="POST" class="space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="crear_lote">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Identificador *</label>
                            <input type="text" name="identificador" maxlength="1" required placeholder="Ej: A"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Área (ha) *</label>
                            <input type="number" name="area_ha" step="0.01" min="0.01" required placeholder="Ej: 2.5"
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nombre *</label>
                        <input type="text" name="nombre" required maxlength="100" placeholder="Nombre del lote"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Ubicación *</label>
                        <input type="text" name="ubicacion" required maxlength="200" placeholder="Descripción de la ubicación"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo de cultivo</label>
                            <select name="id_tipo_preferido" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Sin preferencia</option>
                                <?php foreach ($tiposCultivo as $t): ?>
                                <option value="<?= $t['id_tipo'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end pb-2.5">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                                <input type="checkbox" name="es_alternativo" class="accent-emerald-600 w-4 h-4">
                                Lote alternativo
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Imagen del Lote (Opcional)</label>
                        <input type="file" name="fotografia" accept="image/jpeg, image/png, image/webp" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-lote').classList.add('hidden'); document.getElementById('modal-lote').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition">
                            Registrar Lote
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Editar Lote -->
        <div id="modal-editar-lote" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Editar Lote</h3>
                    <button onclick="document.getElementById('modal-editar-lote').classList.add('hidden'); document.getElementById('modal-editar-lote').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="../../controllers/LoteController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="editar_lote">
                    <input type="hidden" name="id_lote" id="edit_id_lote">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Identificador *</label>
                            <input type="text" name="identificador" id="edit_identificador" maxlength="1" required
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Área (ha) *</label>
                            <input type="number" name="area_ha" id="edit_area_ha" step="0.01" min="0.01" required
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nombre *</label>
                        <input type="text" name="nombre" id="edit_nombre" required maxlength="100"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Ubicación *</label>
                        <input type="text" name="ubicacion" id="edit_ubicacion" required maxlength="200"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo de cultivo</label>
                            <select name="id_tipo_preferido" id="edit_id_tipo" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">— Sin preferencia —</option>
                                <?php foreach ($tiposCultivo as $t): ?>
                                <option value="<?= $t['id_tipo'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Estado</label>
                            <select name="estado" id="edit_estado" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="disponible">Disponible</option>
                                <option value="ocupado">Ocupado</option>
                                <option value="en_descanso">En descanso</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-2.5">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                                <input type="checkbox" name="es_alternativo" id="edit_es_alternativo" class="accent-emerald-600 w-4 h-4">
                                Lote alternativo
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-editar-lote').classList.add('hidden'); document.getElementById('modal-editar-lote').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold transition">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MÓDULO: ASIGNACIONES -->
        <div id="module-asignaciones" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Asignaciones</h2>
                    <p class="text-sm text-slate-500 mt-1">Asigna lotes o cultivos específicos a trabajadores.</p>
                </div>
                <button onclick="document.getElementById('modal-asignar').classList.remove('hidden'); document.getElementById('modal-asignar').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nueva Asignación
                </button>
            </div>

            <!-- Tabs -->
            <div class="glass-card rounded-2xl p-1 mb-5 inline-flex gap-1">
                <button onclick="cambiarTabAsignacion('lotes')" id="tab-asig-lotes"
                    class="px-5 py-2 text-sm font-semibold rounded-xl bg-[#065f46] text-white transition">
                    <i class="fas fa-map mr-1"></i> Lotes (<?= count($asignacionesActivas) ?>)
                </button>
                <button onclick="cambiarTabAsignacion('cultivos')" id="tab-asig-cultivos"
                    class="px-5 py-2 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 transition">
                    <i class="fas fa-seedling mr-1"></i> Cultivos (<?= count($asignacionesCultivos) ?>)
                </button>
            </div>

            <!-- TAB: Lotes -->
            <div id="tab-content-lotes">
                <?php if (empty($asignacionesActivas)): ?>
                <div class="glass-card rounded-2xl p-12 text-center">
                    <i class="fas fa-map text-5xl text-slate-200 mb-4 block"></i>
                    <p class="text-slate-400 text-sm">No hay lotes asignados actualmente.</p>
                </div>
                <?php else: ?>
                <div class="glass-card rounded-2xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f0fdf4]">
                            <tr>
                                <?php foreach (['Trabajador','Correo','Lote','Área (ha)','Asignado el','Acciones'] as $h): ?>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0fdf4]">
                            <?php foreach ($asignacionesActivas as $a): ?>
                            <tr class="hover:bg-[#f9fefb] transition">
                                <td class="px-5 py-3 font-semibold text-[#16332b]"><?= htmlspecialchars($a['trabajador']) ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($a['correo']) ?></td>
                                <td class="px-5 py-3 font-bold text-[#065f46]"><?= htmlspecialchars($a['lote_id'] . ' — ' . $a['lote_nombre']) ?></td>
                                <td class="px-5 py-3 text-gray-700"><?= number_format($a['area_ha'], 2) ?> ha</td>
                                <td class="px-5 py-3 text-gray-500"><?= date('d/m/Y', strtotime($a['creado_en'])) ?></td>
                                <td class="px-5 py-3">
                                    <a href="#"
                                        onclick="confirmarEliminar(event,'../../controllers/AsignacionController.php?accion=desactivar&id=<?= $a['id_asignacion'] ?>','¿Desactivar esta asignación?','El historial se conservará.')"
                                        class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-ban mr-1"></i>Desactivar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Cultivos -->
            <div id="tab-content-cultivos" class="hidden">
                <?php if (empty($asignacionesCultivos)): ?>
                <div class="glass-card rounded-2xl p-12 text-center">
                    <i class="fas fa-seedling text-5xl text-slate-200 mb-4 block"></i>
                    <p class="text-slate-400 text-sm">No hay cultivos activos en lotes asignados.</p>
                </div>
                <?php else: ?>
                <div class="glass-card rounded-2xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f0fdf4]">
                            <tr>
                                <?php foreach (['Cultivo','Variedad','Tipo','Lote','Trabajador','Estado','Cosecha estimada'] as $h): ?>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0fdf4]">
                            <?php
                            $badgeEstC = ['sembrado'=>'bg-blue-100 text-blue-700','desarrollo'=>'bg-amber-100 text-amber-700','maduro'=>'bg-emerald-100 text-emerald-700'];
                            foreach ($asignacionesCultivos as $ac):
                                $bc = $badgeEstC[$ac['estado']] ?? 'bg-slate-100 text-slate-500';
                            ?>
                            <tr class="hover:bg-[#f9fefb] transition">
                                <td class="px-5 py-3 font-bold text-[#065f46]"><?= htmlspecialchars($ac['codigo']) ?></td>
                                <td class="px-5 py-3 text-gray-700"><?= htmlspecialchars($ac['variedad_nombre']) ?></td>
                                <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($ac['tipo_nombre']) ?></td>
                                <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($ac['lote_id'] . ' — ' . $ac['lote_nombre']) ?></td>
                                <td class="px-5 py-3 font-semibold text-[#16332b]"><?= htmlspecialchars($ac['trabajador']) ?></td>
                                <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $bc ?>"><?= ucfirst($ac['estado']) ?></span></td>
                                <td class="px-5 py-3 text-gray-600"><?= date('d/m/Y', strtotime($ac['fecha_cosecha_estimada'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL: Nueva Asignación -->
        <div id="modal-asignar" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-8">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-xl font-bold text-[#065f46]">Nueva Asignación</h3>
                    <button onclick="document.getElementById('modal-asignar').classList.add('hidden'); document.getElementById('modal-asignar').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
                </div>

                <!-- Selector tipo -->
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">¿Qué deseas asignar?</p>
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <label id="tipo-lote-lbl" onclick="cambiarTipoAsignacion('lote')"
                        class="flex items-center gap-3 p-4 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer transition">
                        <input type="radio" name="tipo_asig" value="lote" checked class="w-4 h-4 accent-emerald-600">
                        <div>
                            <p class="font-bold text-[#065f46] text-sm"><i class="fas fa-map mr-1"></i> Lote completo</p>
                            <p class="text-xs text-slate-500">Asignar un lote al trabajador</p>
                        </div>
                    </label>
                    <label id="tipo-cultivo-lbl" onclick="cambiarTipoAsignacion('cultivo')"
                        class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer transition">
                        <input type="radio" name="tipo_asig" value="cultivo" class="w-4 h-4 accent-emerald-600">
                        <div>
                            <p class="font-bold text-slate-600 text-sm"><i class="fas fa-seedling mr-1"></i> Cultivo específico</p>
                            <p class="text-xs text-slate-400">Asignar por cultivo activo</p>
                        </div>
                    </label>
                </div>

                <!-- Form lote -->
                <form id="form-asignar-lote" action="../../controllers/AsignacionController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="asignar">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Trabajador *</label>
                        <select name="id_usuario" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="">Seleccionar trabajador</option>
                            <?php foreach ($trabajadores as $t): ?>
                            <option value="<?= $t['id_usuario'] ?>"><?= htmlspecialchars($t['nombre'] . ' — ' . $t['correo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Lote disponible *</label>
                        <?php if (empty($lotesParaAsignar)): ?>
                        <p class="text-sm text-amber-600 bg-amber-50 rounded-xl px-4 py-2.5">No hay lotes disponibles actualmente.</p>
                        <input type="hidden" name="id_lote" value="">
                        <?php else: ?>
                        <select name="id_lote" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="">Seleccionar lote</option>
                            <?php foreach ($lotesParaAsignar as $l): ?>
                            <option value="<?= $l['id_lote'] ?>"><?= htmlspecialchars($l['identificador'] . ' — ' . $l['nombre'] . ' (' . $l['area_ha'] . ' ha)') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-asignar').classList.add('hidden'); document.getElementById('modal-asignar').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition">
                            Asignar Lote
                        </button>
                    </div>
                </form>

                <!-- Form cultivo -->
                <form id="form-asignar-cultivo" action="../../controllers/AsignacionController.php" method="POST" class="hidden space-y-4">
                    <input type="hidden" name="accion" value="asignar_cultivo">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Trabajador *</label>
                        <select name="id_usuario" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value=""> Seleccionar trabajador </option>
                            <?php foreach ($trabajadores as $t): ?>
                            <option value="<?= $t['id_usuario'] ?>"><?= htmlspecialchars($t['nombre'] . ' — ' . $t['correo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cultivo activo *</label>
                        <select name="id_cultivo" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value=""> Seleccionar cultivo </option>
                            <?php foreach ($cultivos as $c): ?>
                            <option value="<?= $c['id_cultivo'] ?>"><?= htmlspecialchars($c['codigo'] . ' — Lote ' . $c['lote_id'] . ' (' . $c['variedad_nombre'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Se asignará el lote donde está el cultivo. El trabajador verá todos los cultivos de ese lote.
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-asignar').classList.add('hidden'); document.getElementById('modal-asignar').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition">
                            Asignar Cultivo
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MÓDULO: ACTIVIDADES -->
        <div id="module-actividades" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Actividades</h2>
                    <p class="text-sm text-slate-500 mt-1">Registro y asignación de actividades a trabajadores.</p>
                </div>
                <button onclick="document.getElementById('modal-actividad').classList.remove('hidden'); document.getElementById('modal-actividad').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nueva Actividad
                </button>
            </div>

            <?php if (empty($actividades)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-calendar-days text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">No hay actividades registradas aón.</p>
            </div>
            <?php else: ?>
            <div class="glass-card rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f0fdf4]">
                        <tr>
                            <?php foreach (['Tipo','Cultivo','Lote','Trabajador','Fecha','Estado','Acciones'] as $h): ?>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0fdf4]">
                        <?php
                        $badgeAct = [
                            'pendiente'  => 'bg-amber-100 text-amber-700',
                            'en_proceso' => 'bg-blue-100 text-blue-700',
                            'completada' => 'bg-emerald-100 text-emerald-700',
                            'cancelada'  => 'bg-red-100 text-red-600',
                        ];
                        foreach ($actividades as $a):
                            $cls = $badgeAct[$a['estado']] ?? 'bg-slate-100 text-slate-600';
                        ?>
                        <tr class="hover:bg-[#f9fefb] transition">
                            <td class="px-5 py-3 font-semibold text-[#065f46]"><?= htmlspecialchars($a['tipo_actividad']) ?></td>
                            <td class="px-5 py-3 text-gray-700"><?= htmlspecialchars($a['cultivo_codigo']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($a['lote_id'] . ' — ' . $a['lote_nombre']) ?></td>
                            <td class="px-5 py-3 text-gray-700"><?= $a['trabajador'] ? htmlspecialchars($a['trabajador']) : '<span class="text-slate-400 text-xs">Sin asignar</span>' ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= $a['fecha_programada'] ? date('d/m/Y', strtotime($a['fecha_programada'])) : '—' ?></td>
                            <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $cls ?>"><?= ucfirst(str_replace('_',' ',$a['estado'])) ?></span></td>
                            <td class="px-5 py-3">
                                <div class="flex gap-2">
                                    <button onclick='abrirEditarActividad(<?= json_encode($a) ?>)'
                                        class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-pen mr-1"></i>Editar
                                    </button>
                                    <a href="#"
                                        onclick="confirmarEliminar(event,'../../controllers/ActividadController.php?accion=eliminar_actividad&id=<?= $a['id_actividad'] ?>','¿Eliminar esta actividad?','Esta acción no se puede deshacer.')"
                                        class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-lg font-semibold transition">
                                        <i class="fas fa-trash mr-1"></i>Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- MODAL: Nueva Actividad -->
        <div id="modal-actividad" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Registrar Nueva Actividad</h3>
                    <button onclick="document.getElementById('modal-actividad').classList.add('hidden'); document.getElementById('modal-actividad').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="../../controllers/ActividadController.php" method="POST" class="space-y-5">
                    <input type="hidden" name="accion" value="crear_actividad">

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo de actividad *</label>
                            <?php if (empty($tiposActividad)): ?>
                            <p class="text-sm text-red-500 bg-red-50 rounded-xl px-4 py-2.5">Ejecuta el SQL de tipos_actividad primero.</p>
                            <input type="hidden" name="id_tipo_actividad" value="">
                            <?php else: ?>
                            <select name="id_tipo_actividad" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value=""> Seleccionar tipo </option>
                                <?php foreach ($tiposActividad as $t): ?>
                                <option value="<?= $t['id_tipo_actividad'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cultivo *</label>
                            <?php if (empty($cultivosActivos)): ?>
                            <p class="text-sm text-red-500 bg-red-50 rounded-xl px-4 py-2.5">No hay cultivos activos.</p>
                            <input type="hidden" name="id_cultivo" value="">
                            <?php else: ?>
                            <select name="id_cultivo" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value=""> Seleccionar cultivo </option>
                                <?php foreach ($cultivosActivos as $c): ?>
                                <option value="<?= $c['id_cultivo'] ?>"><?= htmlspecialchars($c['codigo'] . ' — ' . $c['lote_id'] . ' ' . $c['lote_nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Asignar a trabajador</label>
                            <select name="id_asignado_a" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value=""> Sin asignar </option>
                                <?php foreach ($trabajadoresAct as $t): ?>
                                <option value="<?= $t['id_usuario'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha programada *</label>
                            <input type="date" name="fecha_programada" id="act_fecha" required
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción *</label>
                        <input type="text" name="descripcion" required maxlength="500" placeholder="Describe la actividad a realizar..."
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</label>
                        <textarea name="observaciones" rows="2" placeholder="Notas adicionales..."
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-actividad').classList.add('hidden'); document.getElementById('modal-actividad').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit" <?= (empty($tiposActividad) || empty($cultivosActivos)) ? 'disabled' : '' ?>
                            class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Registrar y Asignar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Editar Actividad -->
        <div id="modal-editar-actividad" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Editar Actividad</h3>
                    <button onclick="document.getElementById('modal-editar-actividad').classList.add('hidden'); document.getElementById('modal-editar-actividad').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="../../controllers/ActividadController.php" method="POST" class="space-y-5">
                    <input type="hidden" name="accion" value="editar_actividad">
                    <input type="hidden" name="id_actividad" id="ea_id">

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Tipo de actividad *</label>
                            <select name="id_tipo_actividad" id="ea_tipo" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value=""> Seleccionar </option>
                                <?php foreach ($tiposActividad as $t): ?>
                                <option value="<?= $t['id_tipo_actividad'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Estado *</label>
                            <select name="estado" id="ea_estado" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">En proceso</option>
                                <option value="completada">Completada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Asignar a trabajador</label>
                            <select name="id_asignado_a" id="ea_trabajador" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value=""> Sin asignar</option>
                                <?php foreach ($trabajadoresAct as $t): ?>
                                <option value="<?= $t['id_usuario'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha programada *</label>
                            <input type="date" name="fecha_programada" id="ea_fecha" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción *</label>
                        <input type="text" name="descripcion" id="ea_descripcion" required maxlength="500" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</label>
                        <textarea name="observaciones" id="ea_observaciones" rows="2" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-editar-actividad').classList.add('hidden'); document.getElementById('modal-editar-actividad').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MÓDULO: COSECHA -->
        <div id="module-cosecha" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Cosecha</h2>
                    <p class="text-sm text-slate-500 mt-1">Registra cosechas y consulta el historial de producción por lote.</p>
                </div>
                <button onclick="document.getElementById('modal-cosecha').classList.remove('hidden'); document.getElementById('modal-cosecha').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-wheat-awn"></i> Registrar cosecha
                </button>
            </div>

            <!-- Cultivos listos para cosechar -->
            <?php $listos = array_filter($cultivos, fn($c) => $c['estado'] === 'maduro'); ?>
            <?php if (!empty($listos)): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-5 flex items-center gap-3">
                <i class="fas fa-triangle-exclamation text-amber-500 text-lg"></i>
                <p class="text-sm text-amber-700 font-semibold"><?= count($listos) ?> cultivo(s) en estado <span class="font-bold">Maduro</span> listos para cosechar.</p>
            </div>
            <?php endif; ?>

            <!-- Historial de cosechas -->
            <?php if (empty($historialCosechas)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-wheat-awn text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">No hay cosechas registradas aón.</p>
            </div>
            <?php else: ?>
            <!-- KPIs -->
            <?php
            $totalKg   = array_sum(array_column($historialCosechas, 'cantidad_cosechada_kg'));
            $porLote   = [];
            foreach ($historialCosechas as $h) $porLote[$h['lote_id']][] = $h['cantidad_cosechada_kg'];
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#065f46,#10b981);">
                        <i class="fas fa-wheat-awn"></i>
                    </div>
                    <div><p class="text-2xl font-extrabold"><?= count($historialCosechas) ?></p><p class="text-xs text-slate-500">Cosechas registradas</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#b45309,#fbbf24);">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                    <div><p class="text-2xl font-extrabold"><?= number_format($totalKg, 1) ?> kg</p><p class="text-xs text-slate-500">Producción total</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa);">
                        <i class="fas fa-map"></i>
                    </div>
                    <div><p class="text-2xl font-extrabold"><?= count($porLote) ?></p><p class="text-xs text-slate-500">Lotes con historial</p></div>
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f0fdf4]">
                        <tr>
                            <?php foreach (['Código','Variedad','Lote','Siembra','Est. cosecha','Cosecha real','Kg cosechados','Diferencia','Observaciones'] as $h): ?>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0fdf4]">
                        <?php foreach ($historialCosechas as $h):
                            $dtEst  = new DateTime($h['fecha_cosecha_estimada']);
                            $dtReal = new DateTime($h['fecha_cosecha_real']);
                            $diff   = (int)$dtEst->diff($dtReal)->days;
                            $antes  = $dtReal <= $dtEst;
                            $diffTxt = $diff === 0 ? 'En fecha' : ($antes ? "-{$diff}d antes" : "+{$diff}d despuós");
                            $diffCls = $diff === 0 ? 'text-emerald-600' : ($antes ? 'text-blue-600' : 'text-amber-600');
                        ?>
                        <tr class="hover:bg-[#f9fefb] transition">
                            <td class="px-4 py-3 font-bold text-[#065f46]"><?= htmlspecialchars($h['codigo']) ?></td>
                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($h['variedad_nombre']) ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($h['lote_id'] . ' — ' . $h['lote_nombre']) ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= date('d/m/Y', strtotime($h['fecha_siembra'])) ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= date('d/m/Y', strtotime($h['fecha_cosecha_estimada'])) ?></td>
                            <td class="px-4 py-3 font-semibold text-gray-700"><?= date('d/m/Y', strtotime($h['fecha_cosecha_real'])) ?></td>
                            <td class="px-4 py-3 font-bold text-[#065f46]"><?= number_format($h['cantidad_cosechada_kg'], 2) ?> kg</td>
                            <td class="px-4 py-3 font-semibold <?= $diffCls ?>"><?= $diffTxt ?></td>
                            <td class="px-4 py-3 text-gray-400 text-xs max-w-[150px] truncate"><?= htmlspecialchars($h['observaciones'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- MODAL: Registrar cosecha -->
        <div id="modal-cosecha" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Registrar Cosecha</h3>
                    <button onclick="document.getElementById('modal-cosecha').classList.add('hidden'); document.getElementById('modal-cosecha').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
                </div>
                <form action="../../controllers/CosechaController.php" method="POST" class="space-y-5">
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cultivo *</label>
                            <?php $paraCosechar = array_filter($cultivos, fn($c) => in_array($c['estado'], ['sembrado','desarrollo','maduro'])); ?>
                            <?php if (empty($paraCosechar)): ?>
                            <p class="text-sm text-red-500 bg-red-50 rounded-xl px-4 py-2.5">No hay cultivos activos para cosechar.</p>
                            <input type="hidden" name="id_cultivo" value="">
                            <?php else: ?>
                            <select name="id_cultivo" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                                <option value=""> Seleccionar cultivo </option>
                                <?php foreach ($paraCosechar as $c): ?>
                                <option value="<?= $c['id_cultivo'] ?>" data-est="<?= $c['fecha_cosecha_estimada'] ?>">
                                    <?= htmlspecialchars($c['codigo'] . ' — ' . $c['lote_id'] . ' (' . $c['estado'] . ')') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Fecha de cosecha real *</label>
                            <input type="date" name="fecha_cosecha_real" id="cos_fecha" required
                                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cantidad cosechada (kg) * — mínimo 0.1 kg</label>
                        <input type="number" name="cantidad_kg" step="0.01" min="0.1" required placeholder="Ej: 250.5"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</label>
                        <textarea name="observaciones" rows="3" placeholder="Condiciones del cultivo, calidad, incidencias..."
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none resize-none"></textarea>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 text-xs text-emerald-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Al registrar la cosecha el cultivo se cerrará y el lote quedará <strong>disponible</strong> automáticamente.
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-cosecha').classList.add('hidden'); document.getElementById('modal-cosecha').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" <?= empty($paraCosechar) ? 'disabled' : '' ?>
                            class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Registrar cosecha
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MÓDULO: FOTOGRAFóAS -->
        <div id="module-fotos" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Fotografías de cultivos</h2>
                    <p class="text-sm text-slate-500 mt-1">Foto de progreso por cultivo y por actividad.</p>
                </div>
                <button onclick="document.getElementById('modal-subir-foto').classList.remove('hidden'); document.getElementById('modal-subir-foto').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-camera"></i> Subir fotografía
                </button>
            </div>

            <!-- Fotos por cultivo -->
            <h3 class="font-bold text-[#16332b] mb-3">Por cultivo</h3>
            <?php $conFoto = array_filter($cultivosFoto, fn($c) => !empty($c['fotografia'])); ?>
            <?php if (empty($conFoto)): ?>
            <div class="glass-card rounded-2xl p-8 text-center mb-5">
                <i class="fas fa-seedling text-4xl text-slate-200 mb-3 block"></i>
                <p class="text-slate-400 text-sm">Ningón cultivo tiene fotografía aón.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4 mb-6">
                <?php foreach ($conFoto as $c): ?>
                <div class="group relative rounded-2xl overflow-hidden bg-slate-100 aspect-square cursor-pointer"
                    onclick="verFoto('../../<?= htmlspecialchars($c['fotografia']) ?>','<?= htmlspecialchars(addslashes($c['codigo'])) ?>')">
                    <img src="../../<?= htmlspecialchars($c['fotografia']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex flex-col items-end justify-start p-2 gap-1">
                        <form action="../../controllers/FotoController.php" method="POST" enctype="multipart/form-data" onclick="event.stopPropagation()">
                            <input type="hidden" name="accion" value="subir_cultivo">
                            <input type="hidden" name="id_cultivo" value="<?= $c['id_cultivo'] ?>">
                            <label class="bg-blue-500 hover:bg-blue-600 text-white text-[10px] px-2 py-1 rounded-lg font-semibold cursor-pointer transition">
                                <i class="fas fa-pen"></i>
                                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
                            </label>
                        </form>
                        <a href="#"
                            onclick="event.stopPropagation(); confirmarEliminarFoto('../../controllers/FotoController.php?accion=eliminar_cultivo&id=<?= $c['id_cultivo'] ?>')"
                            class="bg-red-500 hover:bg-red-600 text-white text-[10px] px-2 py-1 rounded-lg font-semibold transition">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 px-2 py-1.5">
                        <p class="text-white text-[10px] font-semibold truncate"><?= htmlspecialchars($c['codigo']) ?></p>
                        <p class="text-white/60 text-[9px]"><?= htmlspecialchars($c['lote_id']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Fotos por actividad -->
            <h3 class="font-bold text-[#16332b] mb-3">Por actividad</h3>
            <?php if (empty($actividadesFoto)): ?>
            <div class="glass-card rounded-2xl p-8 text-center">
                <i class="fas fa-calendar-days text-4xl text-slate-200 mb-3 block"></i>
                <p class="text-slate-400 text-sm">Ninguna actividad tiene fotografía aón.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
                <?php foreach ($actividadesFoto as $a): ?>
                <div class="group relative rounded-2xl overflow-hidden bg-slate-100 aspect-square cursor-pointer"
                    onclick="verFoto('../../<?= htmlspecialchars($a['fotografia']) ?>','<?= htmlspecialchars(addslashes($a['tipo_actividad'] . ' — ' . $a['lote_id'])) ?>')">
                    <img src="../../<?= htmlspecialchars($a['fotografia']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex flex-col items-end justify-start p-2">
                        <a href="#"
                            onclick="event.stopPropagation(); confirmarEliminarFoto('../../controllers/FotoController.php?accion=eliminar_actividad&id=<?= $a['id_actividad'] ?>')"
                            class="bg-red-500 hover:bg-red-600 text-white text-[10px] px-2 py-1 rounded-lg font-semibold transition">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 px-2 py-1.5">
                        <p class="text-white text-[10px] font-semibold truncate"><?= htmlspecialchars($a['tipo_actividad']) ?></p>
                        <p class="text-white/60 text-[9px]"><?= $a['fecha_programada'] ? date('d/m/Y', strtotime($a['fecha_programada'])) : '' ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- MODAL: Subir foto a cultivo -->
        <div id="modal-subir-foto" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Subir fotografía</h3>
                    <button onclick="document.getElementById('modal-subir-foto').classList.add('hidden'); document.getElementById('modal-subir-foto').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
                </div>
                <form action="../../controllers/FotoController.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="accion" id="foto-accion" value="subir_cultivo">

                    <!-- Selector tipo -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Tipo de fotografía *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-3 p-3 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer" id="tipo-cultivo-lbl">
                                <input type="radio" name="tipo_foto" value="cultivo" checked onchange="cambiarTipoFoto(this.value)" class="accent-emerald-600">
                                <div>
                                    <p class="text-sm font-semibold text-[#065f46]"><i class="fas fa-seedling mr-1"></i> Cultivo</p>
                                    <p class="text-[10px] text-slate-400">Progreso del cultivo</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer" id="tipo-actividad-lbl">
                                <input type="radio" name="tipo_foto" value="actividad" onchange="cambiarTipoFoto(this.value)" class="accent-emerald-600">
                                <div>
                                    <p class="text-sm font-semibold text-slate-600"><i class="fas fa-calendar-days mr-1"></i> Actividad</p>
                                    <p class="text-[10px] text-slate-400">Evidencia de labor</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Selector cultivo -->
                    <div id="sel-cultivo-wrap">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cultivo *</label>
                        <select name="id_cultivo" id="sel-cultivo" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value=""> Seleccionar cultivo</option>
                            <?php foreach ($cultivosFoto as $c): ?>
                            <option value="<?= $c['id_cultivo'] ?>"><?= htmlspecialchars($c['codigo'] . ' — ' . $c['lote_id'] . ' ' . $c['lote_nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selector actividad -->
                    <div id="sel-actividad-wrap" class="hidden">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Actividad *</label>
                        <select name="id_actividad" id="sel-actividad" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value=""> Seleccionar actividad </option>
                            <?php foreach ($actividades as $a): ?>
                            <option value="<?= $a['id_actividad'] ?>"><?= htmlspecialchars($a['tipo_actividad'] . ' — ' . $a['lote_id'] . ' — ' . ($a['fecha_programada'] ? date('d/m/Y', strtotime($a['fecha_programada'])) : 'Sin fecha')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Foto * (JPG/PNG/WEBP, móx 5MB)</label>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm"
                            onchange="previsualizarFoto(this)">
                        <img id="foto-preview" src="" alt="" class="hidden mt-3 rounded-xl max-h-40 object-cover w-full">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-subir-foto').classList.add('hidden'); document.getElementById('modal-subir-foto').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition">Subir</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Ver foto ampliada -->
        <div id="modal-ver-foto" style="display:none" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-ver-foto').classList.add('hidden'); document.getElementById('modal-ver-foto').style.display='none'">
            <div class="relative max-w-3xl w-full mx-4" onclick="event.stopPropagation()">
                <button onclick="document.getElementById('modal-ver-foto').classList.add('hidden'); document.getElementById('modal-ver-foto').style.display='none'" class="absolute -top-10 right-0 text-white/70 hover:text-white text-2xl"><i class="fas fa-times"></i></button>
                <img id="foto-ampliada" src="" class="w-full rounded-2xl shadow-2xl max-h-[75vh] object-contain">
                <div class="bg-white/10 backdrop-blur-md rounded-xl mt-3 px-4 py-3 text-white text-sm">
                    <p id="foto-desc-ampliada" class="font-semibold"></p>
                </div>
            </div>
        </div>

        <!-- MÓDULO: CALENDARIO -->
        <div id="module-calendario" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Calendario agrócola</h2>
                    <p class="text-sm text-slate-500 mt-1">Actividades programadas y fechas de cosecha.</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Filtro por tipo -->
                    <select id="cal-filtro" onchange="renderCalendario()"
                        class="px-3 py-2 bg-white border border-[#d8eee4] rounded-xl text-sm text-slate-600 focus:outline-none focus:border-emerald-400">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tiposActividad as $t): ?>
                        <option value="<?= htmlspecialchars($t['nombre']) ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                        <?php endforeach; ?>
                        <option value="__cosecha__">Cosecha estimada</option>
                    </select>
                    <!-- Vista -->
                    <div class="flex bg-white border border-[#d8eee4] rounded-xl overflow-hidden">
                        <button onclick="setVista('mes')" id="btn-vista-mes"
                            class="px-4 py-2 text-sm font-semibold transition bg-[#0f8f67] text-white">Mes</button>
                        <button onclick="setVista('semana')" id="btn-vista-semana"
                            class="px-4 py-2 text-sm font-semibold transition text-slate-500 hover:bg-slate-50">Semana</button>
                    </div>
                    <!-- Navegación -->
                    <div class="flex items-center gap-2">
                        <button onclick="navCalendario(-1)" class="w-8 h-8 rounded-lg bg-white border border-[#d8eee4] flex items-center justify-center hover:bg-slate-50 transition">
                            <i class="fas fa-chevron-left text-xs text-slate-500"></i>
                        </button>
                        <span id="cal-titulo" class="text-sm font-bold text-[#16332b] min-w-[160px] text-center"></span>
                        <button onclick="navCalendario(1)" class="w-8 h-8 rounded-lg bg-white border border-[#d8eee4] flex items-center justify-center hover:bg-slate-50 transition">
                            <i class="fas fa-chevron-right text-xs text-slate-500"></i>
                        </button>
                        <button onclick="irHoy()" class="px-3 py-1.5 text-xs font-semibold bg-white border border-[#d8eee4] rounded-lg hover:bg-slate-50 transition text-slate-600">Hoy</button>
                    </div>
                </div>
            </div>

            <!-- Leyenda -->
            <div class="flex flex-wrap gap-3 mb-4">
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>Pendiente</span>
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span>En proceso</span>
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>Completada</span>
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span>Cancelada</span>
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-3 h-3 rounded-full bg-purple-500 inline-block"></span>Cosecha estimada</span>
            </div>

            <!-- Grid del calendario -->
            <div id="cal-container" class="glass-card rounded-2xl overflow-hidden"></div>

            <!-- Panel detalle actividad -->
            <div id="cal-detalle" class="hidden mt-4 glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-[#16332b]" id="det-titulo">Detalle</h3>
                    <button onclick="document.getElementById('cal-detalle').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="det-contenido" class="space-y-2 text-sm text-slate-600"></div>
            </div>
        </div>

        <!-- MÓDULO: REPORTES -->
        <div id="module-reportes" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Reportes</h2>
                <p class="text-sm text-slate-500 mt-1">Genera, filtra y exporta reportes estratégicos del sistema.</p>
            </div>

            <!-- Métricas clave dinómicas -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6" id="reporte-metricas">
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#065f46,#10b981)"><i class="fas fa-seedling"></i></div>
                    <div><p class="text-xl font-extrabold" id="met-cultivos">—</p><p class="text-xs text-slate-500">Cultivos activos</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#0d7a5a,#34d399)"><i class="fas fa-weight-hanging"></i></div>
                    <div><p class="text-xl font-extrabold" id="met-kg">—</p><p class="text-xs text-slate-500">kg producidos</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-list-check"></i></div>
                    <div><p class="text-xl font-extrabold" id="met-act">—</p><p class="text-xs text-slate-500">Actividades completadas</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#b45309,#fbbf24)"><i class="fas fa-basket-shopping"></i></div>
                    <div><p class="text-xl font-extrabold" id="met-cosechas">—</p><p class="text-xs text-slate-500">Cosechas próximas (30d)</p></div>
                </div>
            </div>

            <!-- Selector de tipo de reporte -->
            <div class="glass-card rounded-2xl p-5 mb-5">
                <p class="text-xs font-bold text-[#6b9e8a] uppercase tracking-widest mb-3">Tipo de reporte</p>
                <div class="flex flex-wrap gap-3">
                    <?php
                    $reportes_tipos = [
                        ['id'=>'produccion',  'icon'=>'fa-chart-bar',        'label'=>'Producción comparativa', 'color'=>'#065f46,#10b981'],
                        ['id'=>'actividades', 'icon'=>'fa-calendar-check',   'label'=>'Actividades por período','color'=>'#7c3aed,#a78bfa'],
                        ['id'=>'cosechas',    'icon'=>'fa-basket-shopping',  'label'=>'Próximas cosechas',      'color'=>'#b45309,#fbbf24'],
                    ];
                    foreach ($reportes_tipos as $rt): ?>
                    <button onclick="seleccionarReporte('<?= $rt['id'] ?>', this)"
                        data-tipo="<?= $rt['id'] ?>"
                        class="reporte-tipo-btn flex items-center gap-2 px-4 py-2.5 rounded-xl border-2 border-transparent bg-slate-50 hover:bg-[#f0fdf4] text-sm font-semibold text-slate-600 transition">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs flex-shrink-0"
                              style="background:linear-gradient(135deg,<?= $rt['color'] ?>)">
                            <i class="fas <?= $rt['icon'] ?>"></i>
                        </span>
                        <?= $rt['label'] ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Filtros -->
            <div class="glass-card rounded-2xl p-5 mb-5">
                <p class="text-xs font-bold text-[#6b9e8a] uppercase tracking-widest mb-3">Filtros</p>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 block mb-1">Desde</label>
                        <input type="date" id="r-desde" value="<?= date('Y-01-01') ?>"
                            class="w-full border border-[#d8eee4] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 block mb-1">Hasta</label>
                        <input type="date" id="r-hasta" value="<?= date('Y-m-d') ?>"
                            class="w-full border border-[#d8eee4] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 block mb-1">Tipo de cultivo</label>
                        <select id="r-tipo" class="w-full border border-[#d8eee4] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                            <option value="0">Todos los tipos</option>
                            <?php foreach ($tipos_cultivo_reporte as $tc): ?>
                            <option value="<?= $tc['id_tipo'] ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cargarReporte()"
                            class="flex-1 bg-[#065f46] hover:bg-[#054d38] text-white text-sm font-semibold px-4 py-2 rounded-xl transition flex items-center justify-center gap-2">
                            <i class="fas fa-magnifying-glass"></i> Generar
                        </button>
                        <button onclick="exportarPDF()"
                            class="flex-1 bg-[#7c3aed] hover:bg-[#6d28d9] text-white text-sm font-semibold px-4 py-2 rounded-xl transition flex items-center justify-center gap-2">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resultado del reporte -->
            <div id="reporte-resultado" class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#d8eee4] bg-[#f0fdf4]">
                    <div>
                        <h3 class="font-bold text-[#16332b]" id="reporte-titulo">Selecciona un tipo de reporte</h3>
                        <p class="text-xs text-slate-400 mt-0.5" id="reporte-subtitulo">Elige el tipo y aplica los filtros para ver los datos</p>
                    </div>
                    <span id="reporte-badge" class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-500">—</span>
                </div>
                <div id="reporte-body" class="p-6">
                    <div class="text-center py-16 text-slate-300">
                        <i class="fas fa-chart-bar text-5xl block mb-3"></i>
                        <p class="text-sm">Haz clic en "Generar" para ver el reporte</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MÓDULO: TRABAJADORES -->
        <div id="module-trabajadores" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Trabajadores</h2>
                <p class="text-sm text-slate-500 mt-1">Usuarios registrados con rol de trabajador. Actívalos o desactívalos según necesites.</p>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#065f46,#10b981)">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold"><?= count($users) ?></p>
                        <p class="text-xs text-slate-500">Total registrados</p>
                    </div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#0d7a5a,#34d399)">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold"><?= count(array_filter($users, fn($u) => $u['activo'] == 1)) ?></p>
                        <p class="text-xs text-slate-500">Activos</p>
                    </div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#b45309,#fbbf24)">
                        <i class="fas fa-circle-xmark"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold"><?= count(array_filter($users, fn($u) => $u['activo'] == 0)) ?></p>
                        <p class="text-xs text-slate-500">Inactivos</p>
                    </div>
                </div>
            </div>

            <!-- Buscador y Crear -->
            <div class="glass-card rounded-2xl p-4 mb-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-1 min-w-[280px] bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-200">
                    <i class="fas fa-magnifying-glass text-slate-400"></i>
                    <input type="text" id="buscar-trabajador" placeholder="Buscar por nombre o correo..."
                        oninput="filtrarTrabajadores(this.value)"
                        class="flex-1 bg-transparent text-sm focus:outline-none text-[#16332b] placeholder-slate-400">
                </div>
                <div class="flex items-center gap-3">
                    <select id="filtro-estado-trab" onchange="filtrarTrabajadores(document.getElementById('buscar-trabajador').value)"
                        class="text-sm border border-[#d8eee4] bg-white rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#10b981] text-slate-600">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                    <button onclick="document.getElementById('modal-crear-trabajador').classList.remove('hidden'); document.getElementById('modal-crear-trabajador').style.display='flex'" class="bg-[#10b981] hover:bg-[#059669] text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition flex items-center gap-2">
                        <i class="fas fa-plus"></i> Crear trabajador
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#d8eee4] bg-[#f0fdf4]">
                    <div>
                        <h3 class="font-bold text-[#16332b]">Lista de trabajadores</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Haz clic en el toggle para cambiar el estado</p>
                    </div>
                    <span class="text-xs font-semibold text-[#6b9e8a]" id="trab-count"><?= count($users) ?> registros</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="tabla-trabajadores">
                        <thead class="bg-[#f5fbf8]">
                            <tr>
                                <?php foreach (['Trabajador','Correo','Teléfono','Registrado','Último acceso','Estado','Acciones'] as $h): ?>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="tbody-trabajadores">
                        <?php foreach ($users as $u): ?>
                            <tr class="trow border-b border-[#eef4f1] hover:bg-[#f5fbf8] transition trabajador-row"
                                data-nombre="<?= strtolower(htmlspecialchars($u['nombre'])) ?>"
                                data-correo="<?= strtolower(htmlspecialchars($u['correo'])) ?>"
                                data-activo="<?= $u['activo'] ?>">
                                <!-- Avatar + nombre -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                                             style="background:linear-gradient(135deg,#065f46,#10b981)">
                                            <?= strtoupper(substr($u['nombre'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-[#16332b]"><?= htmlspecialchars($u['nombre']) ?></p>
                                            <p class="text-xs text-slate-400">ID #<?= $u['id_usuario'] ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?= htmlspecialchars($u['correo']) ?></td>
                                <td class="px-5 py-4 text-slate-500"><?= htmlspecialchars($u['telefono'] ?? '—') ?></td>
                                <td class="px-5 py-4 text-slate-500 text-xs"><?= date('d/m/Y', strtotime($u['creado_en'])) ?></td>
                                <td class="px-5 py-4 text-slate-500 text-xs">
                                    <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '<span class="text-slate-300">Nunca</span>' ?>
                                </td>
                                <!-- Estado badge -->
                                <td class="px-5 py-4">
                                    <span id="badge-trab-<?= $u['id_usuario'] ?>"
                                          class="px-3 py-1 rounded-full text-xs font-bold <?= $u['activo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?>">
                                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <!-- Acciones -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <!-- Toggle activo/inactivo -->
                                        <button onclick="toggleTrabajador(<?= $u['id_usuario'] ?>, <?= $u['activo'] ?>, this)"
                                            class="toggle <?= $u['activo'] ? 'on' : 'off' ?>"
                                            title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        </button>
                                        <!-- Editar -->
                                        <button onclick="abrirEditarTrabajador(<?= htmlspecialchars(json_encode([
                                            'id_usuario' => $u['id_usuario'],
                                            'nombre'     => $u['nombre'],
                                            'correo'     => $u['correo'],
                                            'telefono'   => $u['telefono'] ?? '',
                                            'activo'     => $u['activo'],
                                        ])) ?>)"
                                            class="text-xs bg-[#f0fdf4] text-[#065f46] hover:bg-[#d1fae5] px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1">
                                            <i class="fas fa-pen text-xs"></i> Editar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">No hay trabajadores registrados.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL: Crear Trabajador -->
            <div id="modal-crear-trabajador" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-10">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-[#065f46]">Crear trabajador</h3>
                            <p class="text-xs text-slate-500 mt-1">Registra un nuevo usuario en el sistema.</p>
                        </div>
                        <button onclick="document.getElementById('modal-crear-trabajador').classList.add('hidden'); document.getElementById('modal-crear-trabajador').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
                    </div>
                    <form action="../../controllers/UsuarioController.php" method="POST" class="space-y-4">
                        <input type="hidden" name="accion" value="crear_usuario">
                        <?= csrf_field() ?>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nombre completo *</label>
                            <input type="text" name="nombre" required placeholder="Ej: Juan Pérez"
                                class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Correo electrónico *</label>
                            <input type="email" name="correo" required placeholder="Ej: juan@ejemplo.com"
                                class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Teléfono</label>
                            <input type="tel" name="telefono" placeholder="Ej: +57 300 0000000"
                                class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Contraseña *</label>
                            <input type="password" name="contrasena" required minlength="8" placeholder="Mínimo 8 caracteres"
                                class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Estado inicial</label>
                            <select name="estado" class="w-full border border-[#d8eee4] bg-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="document.getElementById('modal-crear-trabajador').classList.add('hidden'); document.getElementById('modal-crear-trabajador').style.display='none'"
                                class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                            <button type="submit"
                                class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition">Guardar trabajador</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MÓDULO: PERFIL -->
        <div id="module-perfil" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Mi Perfil</h2>
                <p class="text-sm text-slate-500 mt-1">Gestiona tu información personal y foto de perfil.</p>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                <!-- Tarjeta de perfil -->
                <div class="xl:col-span-1">
                    <div class="glass-card rounded-2xl p-6 text-center">
                        <!-- Foto -->
                        <div class="relative inline-block mb-4">
                            <?php 
                            $fotoPathAd = $adminPerfil['foto_perfil'] ?? '';
                            if ($fotoPathAd && strpos($fotoPathAd, 'storage/') === 0 && strpos($fotoPathAd, 'public/') !== 0) {
                                $fotoPathAd = 'public/' . $fotoPathAd;
                            }
                            ?>
                            <?php if (!empty($fotoPathAd)): ?>
                                <img id="perfil-foto-preview" src="../../<?= htmlspecialchars($fotoPathAd) ?>"
                                    class="w-28 h-28 rounded-full object-cover border-4 border-[#d1fae5] shadow-lg">
                                <div id="perfil-foto-preview-div" class="hidden w-28 h-28 rounded-full flex items-center justify-center text-white text-3xl font-extrabold border-4 border-[#d1fae5] shadow-lg"
                                    style="background:linear-gradient(135deg,#065f46,#10b981)">
                                    <?= $iniciales ?>
                                </div>
                            <?php else: ?>
                                <div id="perfil-foto-preview-div" class="w-28 h-28 rounded-full flex items-center justify-center text-white text-3xl font-extrabold border-4 border-[#d1fae5] shadow-lg"
                                    style="background:linear-gradient(135deg,#065f46,#10b981)">
                                    <?= $iniciales ?>
                                </div>
                                <img id="perfil-foto-preview" class="hidden w-28 h-28 rounded-full object-cover border-4 border-[#d1fae5] shadow-lg">
                            <?php endif; ?>
                            <!-- Botón cambiar foto -->
                            <label for="input-foto-perfil"
                                class="absolute bottom-0 right-0 w-9 h-9 bg-[#10b981] hover:bg-[#059669] rounded-full flex items-center justify-center cursor-pointer shadow-md transition"
                                title="Cambiar foto">
                                <i class="fas fa-camera text-white text-sm"></i>
                            </label>
                            <input type="file" id="input-foto-perfil" accept="image/jpeg,image/png,image/webp" class="hidden"
                                onchange="previsualizarFotoPerfil(this)">
                        </div>

                        <h3 class="text-lg font-extrabold text-[#16332b]"><?= $nombre_usuario ?></h3>
                        <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($adminPerfil['correo'] ?? '') ?></p>
                        <span class="inline-block mt-2 px-3 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full">Administrador</span>

                        <div class="mt-5 pt-5 border-t border-[#eef4f1] text-left space-y-3">
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <i class="fas fa-calendar-plus w-4 text-[#10b981]"></i>
                                <span>Registrado el <?= $adminPerfil['creado_en'] ? date('d/m/Y', strtotime($adminPerfil['creado_en'])) : '—' ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <i class="fas fa-clock w-4 text-[#10b981]"></i>
                                <span>Último acceso: <?= $adminPerfil['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($adminPerfil['ultimo_acceso'])) : 'Ahora' ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <i class="fas fa-users w-4 text-[#10b981]"></i>
                                <span><?= $total_trabajadores ?> trabajadores en el sistema</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de datos -->
                <div class="xl:col-span-2 space-y-5">

                    <!-- Datos personales -->
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-[#d8eee4] bg-[#f0fdf4]">
                            <div>
                                <h3 class="font-bold text-[#16332b]">Información personal</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Actualiza tus datos de contacto</p>
                            </div>
                            <button onclick="document.getElementById('form-perfil-datos').classList.toggle('hidden'); document.getElementById('form-perfil-view').classList.toggle('hidden')"
                                class="w-9 h-9 rounded-xl bg-[#e6f9f0] hover:bg-[#d1fae5] flex items-center justify-center text-[#065f46] transition" title="Editar">
                                <i class="fas fa-pen text-sm"></i>
                            </button>
                        </div>

                        <!-- Vista (solo lectura) -->
                        <div id="form-perfil-view" class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Nombre Completo</p>
                                <p class="font-semibold text-[#16332b]" id="view-nombre"><?= $nombre_usuario ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Correo Electrónico</p>
                                <p class="font-semibold text-[#16332b]" id="view-correo"><?= htmlspecialchars($adminPerfil['correo'] ?? '') ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Teléfono</p>
                                <p class="font-semibold text-[#16332b]" id="view-telefono"><?= htmlspecialchars($adminPerfil['telefono'] ?? 'No registrado') ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Fecha de Registro</p>
                                <p class="font-semibold text-[#16332b]"><?= $adminPerfil['creado_en'] ? date('d/m/Y', strtotime($adminPerfil['creado_en'])) : '—' ?></p>
                            </div>
                        </div>

                        <!-- Formulario edición -->
                        <form id="form-perfil-datos" action="../../controllers/PerfilController.php" method="POST" class="hidden p-6 space-y-4">
                            <input type="hidden" name="accion" value="actualizar_datos">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nombre completo</label>
                                    <input type="text" name="nombre" value="<?= $nombre_usuario ?>" required
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Correo electrónico</label>
                                    <input type="email" name="correo" value="<?= htmlspecialchars($adminPerfil['correo'] ?? '') ?>" required
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Teléfono</label>
                                    <input type="tel" name="telefono" value="<?= htmlspecialchars($adminPerfil['telefono'] ?? '') ?>"
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button"
                                    onclick="document.getElementById('form-perfil-datos').classList.add('hidden'); document.getElementById('form-perfil-view').classList.remove('hidden')"
                                    class="px-5 py-2.5 border border-[#d8eee4] text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition text-sm">
                                    Cancelar
                                </button>
                                <button type="submit"
                                    class="px-5 py-2.5 bg-[#065f46] hover:bg-[#054d38] text-white font-semibold rounded-xl transition text-sm flex items-center gap-2">
                                    <i class="fas fa-floppy-disk"></i> Guardar cambios
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Cambiar contraseña -->
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-[#d8eee4] bg-[#f0fdf4]">
                            <div>
                                <h3 class="font-bold text-[#16332b]">Seguridad</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Cambia tu contraseña de acceso</p>
                            </div>
                            <button onclick="document.getElementById('form-perfil-pass').classList.toggle('hidden')"
                                class="w-9 h-9 rounded-xl bg-[#e6f9f0] hover:bg-[#d1fae5] flex items-center justify-center text-[#065f46] transition" title="Cambiar contraseña">
                                <i class="fas fa-lock text-sm"></i>
                            </button>
                        </div>
                        <div class="px-6 py-4 text-sm text-slate-500 flex items-center gap-3">
                            <i class="fas fa-shield-halved text-[#10b981]"></i>
                            Contraseña protegida con encriptación bcrypt.
                        </div>
                        <form id="form-perfil-pass" action="../../controllers/PerfilController.php" method="POST" class="hidden px-6 pb-6 space-y-4">
                            <input type="hidden" name="accion" value="cambiar_password">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Contraseña actual</label>
                                    <input type="password" name="pass_actual" required autocomplete="current-password"
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                                <div></div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nueva contraseña</label>
                                    <input type="password" name="pass_nueva" required minlength="8" autocomplete="new-password"
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Confirmar nueva contraseña</label>
                                    <input type="password" name="pass_confirmar" required minlength="8" autocomplete="new-password"
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button"
                                    onclick="document.getElementById('form-perfil-pass').classList.add('hidden')"
                                    class="px-5 py-2.5 border border-[#d8eee4] text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition text-sm">
                                    Cancelar
                                </button>
                                <button type="submit"
                                    class="px-5 py-2.5 bg-[#065f46] hover:bg-[#054d38] text-white font-semibold rounded-xl transition text-sm flex items-center gap-2">
                                    <i class="fas fa-key"></i> Actualizar contraseña
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Subir foto (form oculto, se activa desde el input) -->
                    <form id="form-foto-perfil" action="../../controllers/PerfilController.php" method="POST" enctype="multipart/form-data" class="hidden">
                        <input type="hidden" name="accion" value="subir_foto">
                        <input type="file" name="foto" id="input-foto-perfil-form" accept="image/jpeg,image/png,image/webp">
                    </form>

                </div>
            </div>
        </div>

    </main>
</div>

<!-- MODAL: EDITAR TRABAJADOR -->
<div id="modal-editar-trabajador" class="modal-overlay hidden" style="display:none" onclick="if(event.target===this){this.classList.add('hidden');this.style.display='none';}">
    <div class="modal-box">
        <div class="flex items-center justify-between px-6 py-5 border-b border-[#d8eee4]">
            <h3 class="font-bold text-lg text-[#16332b]">Editar trabajador</h3>
            <button onclick="document.getElementById('modal-editar-trabajador').classList.add('hidden'); document.getElementById('modal-editar-trabajador').style.display='none'"
                class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition">
                <i class="fas fa-xmark text-sm"></i>
            </button>
        </div>
        <form action="../../controllers/UsuarioController.php" method="POST" class="p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="accion" value="editar_usuario">
            <?= csrf_field() ?>
            <input type="hidden" name="id_usuario" id="et_id">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nombre completo</label>
                <input type="text" name="nombre" id="et_nombre" required
                    class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Correo electrónico</label>
                <input type="email" name="correo" id="et_correo" required
                    class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Teléfono</label>
                <input type="tel" name="telefono" id="et_telefono"
                    class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nueva contraseña <span class="text-slate-300 normal-case font-normal">(dejar vacío para no cambiar)</span></label>
                <input type="password" name="contrasena" id="et_pass" autocomplete="new-password"
                    class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Estado</label>
                <select name="estado" id="et_estado"
                    class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                    <option value="Activo">Activo</option>
                    <option value="Inactivo">Inactivo</option>
                </select>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-editar-trabajador').classList.add('hidden'); document.getElementById('modal-editar-trabajador').style.display='none'"
                    class="flex-1 border border-[#d8eee4] text-slate-600 font-semibold py-2.5 rounded-xl hover:bg-slate-50 transition text-sm">
                    Cancelar
                </button>
                <button type="submit"
                    class="flex-1 bg-[#065f46] hover:bg-[#054d38] text-white font-semibold py-2.5 rounded-xl transition text-sm">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE_URL = '../..';
const SID = '<?= session_id() ?>';
// -- Solo navegación entre módulos -----------------------------------------
const headerMeta = {
    panel:         { icon:'fa-house',        title:'Panel',        btn:'Nueva actividad' },
    usuarios:      { icon:'fa-users',         title:'Trabajadores', btn:'—' },
    trabajadores:  { icon:'fa-hard-hat',      title:'Trabajadores', btn:'—' },
    perfil:        { icon:'fa-circle-user',   title:'Mi Perfil',    btn:'—' },
    cultivos:      { icon:'fa-seedling',      title:'Cultivos',     btn:'Nuevo cultivo' },
    lotes:         { icon:'fa-map',           title:'Lotes',        btn:'Nuevo lote' },
    asignaciones:  { icon:'fa-user-check',    title:'Asignaciones', btn:'Nueva asignación' },
    reportes:      { icon:'fa-chart-line',    title:'Reportes',     btn:'Exportar reporte' },
    calendario:    { icon:'fa-calendar-alt',  title:'Calendario',   btn:'Nueva actividad' },
    cosecha:       { icon:'fa-basket-shopping', title:'Cosecha',    btn:'Registrar cosecha' },
    fotos:         { icon:'fa-camera',        title:'Fotografías',  btn:'Subir foto' },
    actividades:   { icon:'fa-calendar-days', title:'Actividades',  btn:'Nueva actividad' },
};

function switchModule(name, el) {
    const target = document.getElementById('module-' + name);
    if (!target) {
        console.error('Module not found:', name);
        return;
    }
    document.querySelectorAll('.module').forEach(m => m.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    target.classList.add('active');
    if (el) el.classList.add('active');
    const m = headerMeta[name] || headerMeta.panel;
    const iconEl = document.getElementById('header-icon');
    const titleEl = document.getElementById('header-title');
    if (iconEl) iconEl.innerHTML = `<i class="fas ${m.icon}"></i>`;
    if (titleEl) titleEl.textContent = m.title;
    const btnEl = document.getElementById('header-btn-text');
    if (btnEl) btnEl.textContent = m.btn;
    if (name === 'calendario' && typeof renderCalendario === 'function') renderCalendario();
    if (name === 'reportes' && typeof cargarMetricas === 'function') cargarMetricas();
}

// -- ASIGNACIONES ---------------------------------------------------------
function cambiarTabAsignacion(tab) {
    const esLotes = tab === 'lotes';
    document.getElementById('tab-content-lotes').classList.toggle('hidden', !esLotes);
    document.getElementById('tab-content-cultivos').classList.toggle('hidden', esLotes);
    document.getElementById('tab-asig-lotes').className   = esLotes
        ? 'px-5 py-2 text-sm font-semibold rounded-xl bg-[#065f46] text-white transition'
        : 'px-5 py-2 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 transition';
    document.getElementById('tab-asig-cultivos').className = !esLotes
        ? 'px-5 py-2 text-sm font-semibold rounded-xl bg-[#065f46] text-white transition'
        : 'px-5 py-2 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 transition';
}

function cambiarTipoAsignacion(tipo) {
    const esLote = tipo === 'lote';
    document.getElementById('form-asignar-lote').classList.toggle('hidden', !esLote);
    document.getElementById('form-asignar-cultivo').classList.toggle('hidden', esLote);
    document.getElementById('tipo-lote-lbl').className    = esLote
        ? 'flex items-center gap-3 p-4 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer transition'
        : 'flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer transition';
    document.getElementById('tipo-cultivo-lbl').className = !esLote
        ? 'flex items-center gap-3 p-4 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer transition'
        : 'flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer transition';
}

// -- HELPERS SWEETALERT2 ---------------------------------------------------
function _toast(icon, title, timer = 3500) {
    Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer,
        timerProgressBar: true,
        didOpen: t => {
            t.addEventListener('mouseenter', Swal.stopTimer);
            t.addEventListener('mouseleave', Swal.resumeTimer);
        }
    }).fire({ icon, title });
}

// -- CONFIRMACIONES SWEETALERT2 --------------------------------------------
function confirmarEliminar(e, url, titulo, texto) {
    e.preventDefault();
    Swal.fire({
        title: titulo,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar',
        borderRadius: '16px',
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: 'rounded-xl font-semibold',
            cancelButton: 'rounded-xl font-semibold',
        }
    }).then(r => { if (r.isConfirmed) window.location.href = url; });
}

function confirmarEliminarFoto(url) {
    Swal.fire({
        title: '¿Eliminar fotografía?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: 'rounded-xl font-semibold',
            cancelButton: 'rounded-xl font-semibold',
        }
    }).then(r => { if (r.isConfirmed) window.location.href = url; });
}

// -- REPORTES --------------------------------------------------------------
const REPORTE_URL = '../../controllers/ReporteController.php';
let reporteActivo = 'produccion';

function seleccionarReporte(tipo, btn) {
    reporteActivo = tipo;
    document.querySelectorAll('.reporte-tipo-btn').forEach(b => {
        b.classList.remove('border-[#10b981]', 'bg-[#f0fdf4]', 'text-[#065f46]');
        b.classList.add('border-transparent', 'bg-slate-50', 'text-slate-600');
    });
    btn.classList.remove('border-transparent', 'bg-slate-50', 'text-slate-600');
    btn.classList.add('border-[#10b981]', 'bg-[#f0fdf4]', 'text-[#065f46]');
}

function cargarMetricas() {
    const desde = document.getElementById('r-desde').value;
    const hasta = document.getElementById('r-hasta').value;
    fetch(`${REPORTE_URL}?accion=ajax&tipo=produccion&desde=${desde}&hasta=${hasta}&id_tipo=0&PHPSESSID=${SID}`, {credentials: 'same-origin'})
        .then(r => r.json()).then(d => {
            if (!d.ok) return;
            const m = d.metricas;
            document.getElementById('met-cultivos').textContent  = m.cultivos_activos;
            document.getElementById('met-kg').textContent        = Number(m.kg_total).toLocaleString('es') + ' kg';
            document.getElementById('met-act').textContent       = m.actividades_completadas;
            document.getElementById('met-cosechas').textContent  = m.cosechas_proximas;
        });
}

let _reporteDatos = [];
let _reportePagina = 1;
const _reportePorPagina = 20;

function cargarReporte() {
    const desde  = document.getElementById('r-desde').value;
    const hasta  = document.getElementById('r-hasta').value;
    const idTipo = document.getElementById('r-tipo').value;
    const body   = document.getElementById('reporte-body');

    body.innerHTML = `<div class="text-center py-16 text-slate-400"><i class="fas fa-spinner fa-spin text-3xl block mb-3 text-[#10b981]"></i><p class="text-sm">Cargando reporte...</p></div>`;

    fetch(`${REPORTE_URL}?accion=ajax&tipo=${reporteActivo}&desde=${desde}&hasta=${hasta}&id_tipo=${idTipo}&PHPSESSID=${SID}`, {credentials: 'same-origin'})
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { body.innerHTML = `<div class="text-center py-10 text-red-400">Error: ${d.error || 'sin datos'}</div>`; return; }

            const m = d.metricas;
            document.getElementById('met-cultivos').textContent  = m.cultivos_activos;
            document.getElementById('met-kg').textContent        = Number(m.kg_total).toLocaleString('es') + ' kg';
            document.getElementById('met-act').textContent       = m.actividades_completadas;
            document.getElementById('met-cosechas').textContent  = m.cosechas_proximas;

            document.getElementById('reporte-titulo').textContent    = d.titulo;
            document.getElementById('reporte-subtitulo').textContent = d.subtitulo;
            document.getElementById('reporte-badge').textContent     = d.total + ' registros';

            if (d.datos.length === 0) {
                body.innerHTML = `<div class="text-center py-16 text-slate-300"><i class="fas fa-inbox text-4xl block mb-3"></i><p class="text-sm">Sin datos para los filtros seleccionados.</p></div>`;
                return;
            }

            _reporteDatos  = d.datos;
            _reportePagina = 1;
            renderPaginaReporte(d.tipo);
        })
        .catch(err => { body.innerHTML = `<div class="text-center py-10 text-red-400">Error de conexión.</div>`; });
}

function renderPaginaReporte(tipo) {
    tipo = tipo || reporteActivo;
    const total   = _reporteDatos.length;
    const totalPag = Math.ceil(total / _reportePorPagina);
    const inicio  = (_reportePagina - 1) * _reportePorPagina;
    const pagData = _reporteDatos.slice(inicio, inicio + _reportePorPagina);

    const body = document.getElementById('reporte-body');
    body.innerHTML = renderTabla(tipo, pagData) + renderPaginacion(total, totalPag);
}

function renderPaginacion(total, totalPag) {
    if (totalPag <= 1) return '';
    let btns = '';
    for (let i = 1; i <= totalPag; i++) {
        const activo = i === _reportePagina;
        btns += `<button onclick="irPaginaReporte(${i})"
            class="w-8 h-8 rounded-lg text-xs font-semibold transition ${activo ? 'bg-[#065f46] text-white' : 'bg-slate-100 text-slate-600 hover:bg-[#d1fae5]'}">${i}</button>`;
    }
    return `<div class="flex items-center justify-between px-5 py-4 border-t border-[#d8eee4] bg-[#f8fffe]">
        <span class="text-xs text-slate-400">${total} registros — página ${_reportePagina} de ${totalPag}</span>
        <div class="flex gap-1">${btns}</div>
    </div>`;
}

function irPaginaReporte(p) {
    _reportePagina = p;
    renderPaginaReporte();
}

function renderTabla(tipo, datos) {
    if (tipo === 'produccion') {
        const maxKg = Math.max(..._reporteDatos.map(r => parseFloat(r.total_kg) || 0)) || 1;
        let rows = datos.map(r => {
            const pct = Math.round((parseFloat(r.total_kg) / maxKg) * 100);
            return `<tr class="border-b border-[#eef4f1] hover:bg-[#f5fbf8] transition">
                <td class="px-5 py-3"><span class="font-bold text-[#065f46]">${r.lote_id}</span><br><span class="text-xs text-slate-400">${r.lote_nombre}</span></td>
                <td class="px-5 py-3 text-sm">${r.tipo_cultivo}</td>
                <td class="px-5 py-3 text-sm text-right">${parseFloat(r.area_ha).toFixed(2)}</td>
                <td class="px-5 py-3 text-sm text-right font-semibold">${r.total_cultivos}</td>
                <td class="px-5 py-3 text-sm text-right font-bold text-[#065f46]">${Number(r.total_kg).toLocaleString('es', {minimumFractionDigits:1})} kg</td>
                <td class="px-5 py-3 text-sm text-right">${Number(r.promedio_kg).toLocaleString('es', {minimumFractionDigits:1})}</td>
                <td class="px-5 py-3 text-sm text-right">${Number(r.kg_por_ha).toLocaleString('es', {minimumFractionDigits:1})}</td>
                <td class="px-5 py-3 w-36">
                    <div class="bg-[#e2f5ec] rounded-full h-2"><div class="bg-gradient-to-r from-[#065f46] to-[#10b981] h-2 rounded-full" style="width:${pct}%"></div></div>
                    <p class="text-right text-[10px] text-[#065f46] mt-0.5">${pct}%</p>
                </td>
            </tr>`;
        }).join('');
        const thClasses = [
            'text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-right px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-right px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-right px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-right px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-right px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]',
            'text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4] w-36',
        ];
        const headers = ['Lote','Tipo cultivo','Área (ha)','Cosechas','Total kg','Promedio kg','kg/ha','Rendimiento'];
        return `<div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="bg-[#f0fdf4]"><tr>
                ${headers.map((h,i)=>`<th class="${thClasses[i]}">${h}</th>`).join('')}
            </tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    if (tipo === 'actividades') {
        const badges = {completada:'bg-emerald-100 text-emerald-700',pendiente:'bg-amber-100 text-amber-700',en_proceso:'bg-blue-100 text-blue-700',cancelada:'bg-red-100 text-red-700'};
        let rows = datos.map(r => {
            const bc = badges[r.estado] || 'bg-slate-100 text-slate-500';
            return `<tr class="border-b border-[#eef4f1] hover:bg-[#f5fbf8] transition">
                <td class="px-5 py-3 text-sm">${r.fecha_programada}</td>
                <td class="px-5 py-3 text-sm font-semibold">${r.tipo_actividad}</td>
                <td class="px-5 py-3 text-sm text-[#065f46] font-bold">${r.cultivo_codigo}</td>
                <td class="px-5 py-3 text-sm">${r.lote_id} — ${r.lote_nombre}</td>
                <td class="px-5 py-3 text-sm">${r.tipo_cultivo}</td>
                <td class="px-5 py-3 text-sm">${r.trabajador}</td>
                <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold ${bc}">${r.estado.replace('_',' ')}</span></td>
                <td class="px-5 py-3 text-xs text-slate-400">${r.descripcion.substring(0,50)}${r.descripcion.length>50?'—':''}</td>
            </tr>`;
        }).join('');
        return `<div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="bg-[#f0fdf4]"><tr>
                ${['Fecha','Tipo','Cultivo','Lote','Tipo cultivo','Trabajador','Estado','Descripción'].map(h=>`<th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]">${h}</th>`).join('')}
            </tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    if (tipo === 'cosechas') {
        const urgBadge = d => parseInt(d) <= 7 ? 'bg-red-100 text-red-700' : parseInt(d) <= 30 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700';
        const estBadge = {sembrado:'bg-blue-100 text-blue-700',desarrollo:'bg-amber-100 text-amber-700',maduro:'bg-emerald-100 text-emerald-700'};
        let rows = datos.map(r => {
            const ub = urgBadge(r.dias_restantes);
            const eb = estBadge[r.estado] || 'bg-slate-100 text-slate-500';
            return `<tr class="border-b border-[#eef4f1] hover:bg-[#f5fbf8] transition">
                <td class="px-5 py-3 text-sm font-bold text-[#065f46]">${r.codigo}</td>
                <td class="px-5 py-3 text-sm">${r.variedad_nombre}</td>
                <td class="px-5 py-3 text-sm">${r.tipo_cultivo}</td>
                <td class="px-5 py-3 text-sm">${r.lote_id} — ${r.lote_nombre}</td>
                <td class="px-5 py-3 text-sm text-right">${parseFloat(r.area_ha).toFixed(2)}</td>
                <td class="px-5 py-3 text-sm">${r.fecha_siembra}</td>
                <td class="px-5 py-3 text-sm font-semibold">${r.fecha_cosecha_estimada}</td>
                <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold ${ub}">${r.dias_restantes} días</span></td>
                <td class="px-5 py-3 text-sm">${r.trabajador}</td>
                <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold ${eb}">${r.estado}</span></td>
            </tr>`;
        }).join('');
        return `<div class="overflow-x-auto"><table class="w-full text-sm">
            <thead class="bg-[#f0fdf4]"><tr>
                ${['Código','Variedad','Tipo','Lote','Área (ha)','Siembra','Cosecha estimada','Días restantes','Trabajador','Estado'].map(h=>`<th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]">${h}</th>`).join('')}
            </tr></thead><tbody>${rows}</tbody></table></div>`;
    }
    return '';
}

function exportarPDF() {
    const desde  = document.getElementById('r-desde').value;
    const hasta  = document.getElementById('r-hasta').value;
    const idTipo = document.getElementById('r-tipo').value;
    window.open(`${REPORTE_URL}?accion=pdf&tipo=${reporteActivo}&desde=${desde}&hasta=${hasta}&id_tipo=${idTipo}`, '_blank');
}

// -- FOTOGRAFÍAS -----------------------------------------------------------
function cambiarTipoFoto(tipo) {
    const esCultivo = tipo === 'cultivo';
    document.getElementById('foto-accion').value       = esCultivo ? 'subir_cultivo' : 'subir_actividad';
    document.getElementById('sel-cultivo-wrap').classList.toggle('hidden', !esCultivo);
    document.getElementById('sel-actividad-wrap').classList.toggle('hidden', esCultivo);
    document.getElementById('sel-cultivo').required   = esCultivo;
    document.getElementById('sel-actividad').required = !esCultivo;
    document.getElementById('tipo-cultivo-lbl').className   = esCultivo
        ? 'flex items-center gap-3 p-3 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer'
        : 'flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer';
    document.getElementById('tipo-actividad-lbl').className = !esCultivo
        ? 'flex items-center gap-3 p-3 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer'
        : 'flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer';
}
function previsualizarFoto(input) {
    const preview = document.getElementById('foto-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
        reader.readAsDataURL(input.files[0]);
    }
}
function verFoto(src, desc) {
    document.getElementById('foto-ampliada').src = src;
    document.getElementById('foto-desc-ampliada').textContent = desc || '';
    document.getElementById('modal-ver-foto').classList.remove('hidden'); document.getElementById('modal-ver-foto').style.display='';
}
// -- FIN FOTOGRAFÍAS --------------------------------------------------------
const CAL_EVENTOS = <?= json_encode($eventosCalendario, JSON_UNESCAPED_UNICODE) ?>;

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const DIAS  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
const COLOR_ESTADO = { pendiente:'bg-amber-400', en_proceso:'bg-blue-400', completada:'bg-emerald-500', cancelada:'bg-red-400', __cosecha__:'bg-purple-500' };
const COLOR_BORDE  = { pendiente:'border-amber-400', en_proceso:'border-blue-400', completada:'border-emerald-500', cancelada:'border-red-400', __cosecha__:'border-purple-500' };

let calFecha  = new Date();
let calVista  = 'mes'; // 'mes' | 'semana'

function setVista(v) {
    calVista = v;
    document.getElementById('btn-vista-mes').className    = v === 'mes'    ? 'px-4 py-2 text-sm font-semibold bg-[#0f8f67] text-white transition' : 'px-4 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50 transition';
    document.getElementById('btn-vista-semana').className = v === 'semana' ? 'px-4 py-2 text-sm font-semibold bg-[#0f8f67] text-white transition' : 'px-4 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50 transition';
    renderCalendario();
}

function navCalendario(dir) {
    if (calVista === 'mes') {
        calFecha.setMonth(calFecha.getMonth() + dir);
    } else {
        calFecha.setDate(calFecha.getDate() + dir * 7);
    }
    renderCalendario();
}

function irHoy() { calFecha = new Date(); renderCalendario(); }

function filtroActivo() { return document.getElementById('cal-filtro')?.value || ''; }

function eventosDia(fechaStr) {
    const filtro = filtroActivo();
    return CAL_EVENTOS.filter(e => {
        if (e.fecha !== fechaStr) return false;
        if (filtro && e.tipo !== filtro) return false;
        return true;
    });
}

function fmtFecha(y, m, d) {
    return y + '-' + String(m+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
}

function renderCalendario() {
    calVista === 'mes' ? renderMes() : renderSemana();
    document.getElementById('cal-detalle').classList.add('hidden');
}

function renderMes() {
    const y = calFecha.getFullYear(), m = calFecha.getMonth();
    document.getElementById('cal-titulo').textContent = MESES[m] + ' ' + y;
    const hoy = new Date(); const hoyStr = fmtFecha(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());

    const primerDia = new Date(y, m, 1).getDay();
    const diasMes   = new Date(y, m+1, 0).getDate();

    let html = '<div class="grid grid-cols-7">';
    DIAS.forEach(d => { html += `<div class="py-2 text-center text-xs font-bold text-[#6b9e8a] uppercase border-b border-[#d8eee4] bg-[#f0fdf4]">${d}</div>`; });

    // Celdas vacías inicio
    for (let i = 0; i < primerDia; i++) html += '<div class="min-h-[90px] border-b border-r border-[#f0fdf4] bg-slate-50/50"></div>';

    for (let d = 1; d <= diasMes; d++) {
        const fechaStr = fmtFecha(y, m, d);
        const eventos  = eventosDia(fechaStr);
        const esHoy    = fechaStr === hoyStr;
        html += `<div class="min-h-[90px] border-b border-r border-[#f0fdf4] p-1.5 ${esHoy ? 'bg-emerald-50' : 'hover:bg-slate-50'} transition cursor-pointer group" onclick="abrirModalActividad('${fechaStr}')">`;
        html += `<div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold ${esHoy ? 'w-6 h-6 rounded-full bg-[#0f8f67] text-white flex items-center justify-center' : 'text-slate-500'}">${d}</span>
                    <span class="hidden group-hover:inline text-[10px] text-[#0f8f67] font-semibold">+ añadir</span>
                 </div>`;
        eventos.slice(0,3).forEach(e => {
            const col = e.es_cosecha ? 'bg-purple-500' : (COLOR_ESTADO[e.estado] || 'bg-slate-400');
            html += `<div onclick='event.stopPropagation(); mostrarDetalle(${JSON.stringify(e).replace(/'/g,"&#39;")})' 
                        class="cursor-pointer mb-0.5 px-1.5 py-0.5 rounded text-white text-[10px] font-semibold truncate ${col} hover:opacity-80 transition"
                        title="${e.titulo}">${e.titulo}</div>`;
        });
        if (eventos.length > 3) html += `<div class="text-[10px] text-slate-400 pl-1">+${eventos.length-3} más</div>`;
        html += '</div>';
    }

    // Celdas vacías fin
    const total = primerDia + diasMes;
    const resto = total % 7 === 0 ? 0 : 7 - (total % 7);
    for (let i = 0; i < resto; i++) html += '<div class="min-h-[90px] border-b border-r border-[#f0fdf4] bg-slate-50/50"></div>';
    html += '</div>';
    document.getElementById('cal-container').innerHTML = html;
}

function renderSemana() {
    // Lunes de la semana actual
    const base = new Date(calFecha);
    const dow  = base.getDay(); // 0=dom
    base.setDate(base.getDate() - (dow === 0 ? 6 : dow - 1));

    const dias = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(base); d.setDate(base.getDate() + i);
        dias.push(d);
    }

    const primerDia = dias[0], ultimoDia = dias[6];
    const fmt = d => d.getDate() + ' ' + MESES[d.getMonth()].slice(0,3);
    document.getElementById('cal-titulo').textContent = fmt(primerDia) + ' — ' + fmt(ultimoDia) + ' ' + ultimoDia.getFullYear();

    const hoy = new Date(); const hoyStr = fmtFecha(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());

    let html = '<div class="grid grid-cols-7">';
    dias.forEach(d => {
        const fechaStr = fmtFecha(d.getFullYear(), d.getMonth(), d.getDate());
        const esHoy = fechaStr === hoyStr;
        html += `<div class="py-2 text-center border-b border-r border-[#d8eee4] ${esHoy ? 'bg-emerald-50' : 'bg-[#f0fdf4]'}">
                    <p class="text-[10px] font-bold text-[#6b9e8a] uppercase">${DIAS[d.getDay()]}</p>
                    <p class="text-sm font-extrabold ${esHoy ? 'text-[#0f8f67]' : 'text-[#16332b]'}">${d.getDate()}</p>
                 </div>`;
    });

    dias.forEach(d => {
        const fechaStr = fmtFecha(d.getFullYear(), d.getMonth(), d.getDate());
        const eventos  = eventosDia(fechaStr);
        const esHoy    = fechaStr === hoyStr;
        html += `<div class="min-h-[160px] border-b border-r border-[#f0fdf4] p-2 ${esHoy ? 'bg-emerald-50/40' : 'hover:bg-slate-50'} transition cursor-pointer group" onclick="abrirModalActividad('${fechaStr}')">`;
        eventos.forEach(e => {
            const col = e.es_cosecha ? 'bg-purple-500' : (COLOR_ESTADO[e.estado] || 'bg-slate-400');
            html += `<div onclick='event.stopPropagation(); mostrarDetalle(${JSON.stringify(e).replace(/'/g,"&#39;")})' 
                        class="cursor-pointer mb-1 px-2 py-1 rounded-lg text-white text-[11px] font-semibold ${col} hover:opacity-80 transition">${e.titulo}</div>`;
        });
        if (eventos.length === 0) html += `<div class="text-[10px] text-slate-300 text-center mt-4 group-hover:text-[#0f8f67] group-hover:font-semibold transition">+ añadir</div>`;
        html += '</div>';
    });
    html += '</div>';
    document.getElementById('cal-container').innerHTML = html;
}

function mostrarDetalle(e) {
    const badgeColor = { pendiente:'bg-amber-100 text-amber-700', en_proceso:'bg-blue-100 text-blue-700', completada:'bg-emerald-100 text-emerald-700', cancelada:'bg-red-100 text-red-600', __cosecha__:'bg-purple-100 text-purple-700' };
    const cls = badgeColor[e.estado] || 'bg-slate-100 text-slate-600';
    document.getElementById('det-titulo').textContent = e.titulo;
    document.getElementById('det-contenido').innerHTML = `
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50 rounded-xl p-3"><p class="text-xs text-slate-400 mb-0.5">Fecha</p><p class="font-semibold">${e.fecha}</p></div>
            <div class="bg-slate-50 rounded-xl p-3"><p class="text-xs text-slate-400 mb-0.5">Estado</p><span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${e.estado.replace('_',' ')}</span></div>
            <div class="bg-slate-50 rounded-xl p-3"><p class="text-xs text-slate-400 mb-0.5">Cultivo</p><p class="font-semibold">${e.cultivo}</p></div>
            <div class="bg-slate-50 rounded-xl p-3"><p class="text-xs text-slate-400 mb-0.5">Lote</p><p class="font-semibold">${e.lote}</p></div>
            ${e.trabajador ? `<div class="bg-slate-50 rounded-xl p-3 col-span-2"><p class="text-xs text-slate-400 mb-0.5">Trabajador</p><p class="font-semibold">${e.trabajador}</p></div>` : ''}
            ${e.descripcion ? `<div class="bg-slate-50 rounded-xl p-3 col-span-2"><p class="text-xs text-slate-400 mb-0.5">Descripción</p><p>${e.descripcion}</p></div>` : ''}
        </div>
        ${!e.es_cosecha ? `<div class="mt-3"><button onclick="document.getElementById('modal-actividad').classList.remove('hidden'); document.getElementById('modal-actividad').style.display=''" class="text-xs bg-[#10b981] hover:bg-[#059669] text-white px-4 py-2 rounded-xl font-semibold transition"><i class="fas fa-plus mr-1"></i>Agregar actividad en esta fecha</button></div>` : ''}
    `;
    document.getElementById('cal-detalle').classList.remove('hidden');
    document.getElementById('cal-detalle').scrollIntoView({ behavior:'smooth', block:'nearest' });
}

// Inicializar calendario cuando se abre el módulo
// (integrado en switchModule principal, no redefinir aquí)
// Cerrar panel notificaciones al hacer clic fuera
document.addEventListener('click', e => {
    const panel = document.getElementById('panel-notif');
    if (panel && !panel.classList.contains('hidden') && !panel.closest) return;
    if (panel && !panel.contains(e.target) && !e.target.closest('[onclick*="panel-notif"]')) {
        panel.classList.add('hidden');
    }
});
// -- FIN CALENDARIO ---------------------------------------------------------
// -- PERFIL ----------------------------------------------------------------
function previsualizarFotoPerfil(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    // Previsualizar
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('perfil-foto-preview');
        if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
        } else {
            // Era un div con iniciales, reemplazar por img
            const img = document.createElement('img');
            img.id = 'perfil-foto-preview';
            img.src = e.target.result;
            img.className = 'w-28 h-28 rounded-full object-cover border-4 border-[#d1fae5] shadow-lg';
            preview.replaceWith(img);
        }
    };
    reader.readAsDataURL(file);
    // Confirmar y subir
    Swal.fire({
        title: '¿Actualizar foto de perfil?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#065f46',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, subir',
        cancelButtonText: 'Cancelar',
        customClass: { popup: 'rounded-2xl' }
    }).then(r => {
        if (r.isConfirmed) {
            // Copiar el archivo al form oculto y enviar
            const formFoto = document.getElementById('form-foto-perfil');
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('input-foto-perfil-form').files = dt.files;
            formFoto.submit();
        }
    });
}

// -- TRABAJADORES ----------------------------------------------------------
function toggleTrabajador(id, estadoActual, btn) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    Swal.fire({
        title: nuevoEstado ? '¿Activar trabajador?' : '¿Desactivar trabajador?',
        text: nuevoEstado ? 'El trabajador podrá acceder al sistema.' : 'El trabajador no podrá iniciar sesión.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado ? '#065f46' : '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: nuevoEstado ? '? Activar' : '? Desactivar',
        cancelButtonText: 'Cancelar',
        customClass: { popup: 'rounded-2xl' }
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch(`../../controllers/UsuarioController.php?accion=toggleEstado&id=${id}&estado=${estadoActual}&PHPSESSID=${SID}`)
            .then(res => {
                if (!res.ok) throw new Error();
                // Actualizar toggle
                btn.classList.toggle('on', nuevoEstado == 1);
                btn.classList.toggle('off', nuevoEstado == 0);
                btn.setAttribute('onclick', `toggleTrabajador(${id}, ${nuevoEstado}, this)`);
                btn.title = nuevoEstado ? 'Desactivar' : 'Activar';
                // Actualizar badge
                const badge = document.getElementById('badge-trab-' + id);
                if (badge) {
                    badge.textContent = nuevoEstado ? 'Activo' : 'Inactivo';
                    badge.className = nuevoEstado
                        ? 'px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700'
                        : 'px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-600';
                }
                // Actualizar data-activo de la fila
                btn.closest('tr').dataset.activo = nuevoEstado;
                _toast(nuevoEstado ? 'success' : 'warning', nuevoEstado ? 'Trabajador activado' : 'Trabajador desactivado');
            })
            .catch(() => {
                _toast('error', 'Error al cambiar el estado');
            });
    });
}

function abrirEditarTrabajador(u) {
    document.getElementById('et_id').value      = u.id_usuario;
    document.getElementById('et_nombre').value  = u.nombre;
    document.getElementById('et_correo').value  = u.correo;
    document.getElementById('et_telefono').value= u.telefono || '';
    document.getElementById('et_pass').value    = '';
    document.getElementById('et_estado').value  = u.activo == 1 ? 'Activo' : 'Inactivo';
    const m = document.getElementById('modal-editar-trabajador');
    m.classList.remove('hidden');
    m.style.display = 'flex';
}

function filtrarTrabajadores(q) {
    const filtroEstado = document.getElementById('filtro-estado-trab').value;
    const texto = q.toLowerCase().trim();
    let visibles = 0;
    document.querySelectorAll('.trabajador-row').forEach(row => {
        const nombre = row.dataset.nombre || '';
        const correo = row.dataset.correo || '';
        const activo = row.dataset.activo;
        const matchTexto = !texto || nombre.includes(texto) || correo.includes(texto);
        const matchEstado = filtroEstado === '' || activo === filtroEstado;
        const visible = matchTexto && matchEstado;
        row.classList.toggle('hidden', !visible);
        if (visible) visibles++;
    });
    const cnt = document.getElementById('trab-count');
    if (cnt) cnt.textContent = visibles + ' registros';
}

function filterTable()  { /* deshabilitado */ }
function toggleEstado() { /* deshabilitado */ }

function abrirModalActividad(fechaStr) {
    document.getElementById('act_fecha').value = fechaStr || '';
    document.getElementById('modal-actividad').classList.remove('hidden'); document.getElementById('modal-actividad').style.display='';
}

function abrirEditarActividad(a) {
    document.getElementById('ea_id').value            = a.id_actividad;
    document.getElementById('ea_tipo').value          = a.id_tipo_actividad ?? '';
    document.getElementById('ea_estado').value        = a.estado ?? 'pendiente';
    document.getElementById('ea_trabajador').value    = a.id_asignado_a ?? '';
    document.getElementById('ea_fecha').value         = a.fecha_programada ?? '';
    document.getElementById('ea_descripcion').value   = a.descripcion ?? '';
    document.getElementById('ea_observaciones').value = a.observaciones ?? '';
    document.getElementById('modal-editar-actividad').classList.remove('hidden'); document.getElementById('modal-editar-actividad').style.display='';
}

function abrirEditarCultivo(c) {
    document.getElementById('ec_id_cultivo').value    = c.id_cultivo;
    document.getElementById('ec_id_variedad').value   = c.id_variedad ?? '';
    document.getElementById('ec_estado').value        = c.estado ?? 'sembrado';
    document.getElementById('ec_fecha_siembra').value = c.fecha_siembra ?? '';
    document.getElementById('ec_observaciones').value = c.observaciones ?? '';
    calcularCosechaEditar();
    document.getElementById('modal-editar-cultivo').classList.remove('hidden'); document.getElementById('modal-editar-cultivo').style.display='';
}

function calcularCosechaEditar() {
    const sel    = document.getElementById('ec_id_variedad');
    const fechaEl = document.getElementById('ec_fecha_siembra');
    const preview = document.getElementById('ec_cosecha_preview');
    const opt    = sel.options[sel.selectedIndex];
    const dias   = parseInt(opt?.dataset?.dias);
    const fecha  = fechaEl.value;
    if (!dias || !fecha) { preview.textContent = '—'; return; }
    const dt = new Date(fecha + 'T00:00:00');
    dt.setDate(dt.getDate() + dias);
    preview.textContent = '?? ' + dt.toLocaleDateString('es-CO', { day:'2-digit', month:'2-digit', year:'numeric' }) + ' (' + dias + ' días)';
}

// Cólculo automótico de fecha de cosecha
function calcularCosecha() {
    const sel    = document.getElementById('sel_variedad');
    const fechaEl = document.getElementById('fecha_siembra');
    const preview = document.getElementById('cosecha_preview');
    const opt    = sel.options[sel.selectedIndex];
    const dias   = parseInt(opt?.dataset?.dias);
    const fecha  = fechaEl.value;

    if (!dias || !fecha) {
        preview.textContent = 'Se calculará automáticamente';
        return;
    }
    const dt = new Date(fecha + 'T00:00:00');
    dt.setDate(dt.getDate() + dias);
    const formatted = dt.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    preview.textContent = '?? ' + formatted + ' (' + dias + ' días desde siembra)';
}

function abrirEditarLote(lote) {
    document.getElementById('edit_id_lote').value       = lote.id_lote;
    document.getElementById('edit_identificador').value = lote.identificador;
    document.getElementById('edit_nombre').value        = lote.nombre;
    document.getElementById('edit_ubicacion').value     = lote.ubicacion;
    document.getElementById('edit_area_ha').value       = lote.area_ha;
    document.getElementById('edit_id_tipo').value       = lote.id_tipo_preferido ?? '';
    document.getElementById('edit_estado').value        = lote.estado ?? 'disponible';
    document.getElementById('edit_es_alternativo').checked = lote.es_alternativo == 1;
    document.getElementById('modal-editar-lote').classList.remove('hidden'); document.getElementById('modal-editar-lote').style.display='';
}
function openModal()    { /* deshabilitado */ }
function closeModal()   { /* deshabilitado */ }
function submitForm(e)  { e.preventDefault(); }
function exportarReporte() { /* deshabilitado */ }

function abrirVisorImagen(src, titulo, desc) {
    document.getElementById('visor-img').src = src;
    document.getElementById('visor-titulo').textContent = titulo;
    document.getElementById('visor-desc').textContent = desc;
    document.getElementById('modal-visor-imagen').classList.remove('hidden');
    document.getElementById('modal-visor-imagen').style.display = 'flex';
}

// Cerrar todos los modales al cargar o al volver desde bfcache (pageshow cubre bfcache)
function cerrarTodosLosModales() {
    document.querySelectorAll('[id^="modal-"]').forEach(m => {
        m.classList.add('hidden');
        m.style.display = 'none';
    });
}
window.addEventListener('pageshow', cerrarTodosLosModales);

document.addEventListener('DOMContentLoaded', () => {
    cerrarTodosLosModales();

    // -- Activar módulo según hash de la URL ------------------------------
    const hashModulos = {
        '#cultivos':     'cultivos',
        '#lotes':        'lotes',
        '#actividades':  'actividades',
        '#asignaciones': 'asignaciones',
        '#cosecha':      'cosecha',
        '#fotos':        'fotos',
        '#reportes':     'reportes',
        '#calendario':   'calendario',
        '#trabajadores': 'trabajadores',
        '#usuarios':     'trabajadores',
        '#perfil':       'perfil',
    };
    const hash = window.location.hash;
    if (hash && hashModulos[hash]) {
        const modulo = hashModulos[hash];
        const navEl  = document.getElementById('nav-' + modulo);
        switchModule(modulo, navEl);
        // Limpiar el hash de la URL sin recargar
        history.replaceState(null, '', window.location.pathname);
    }
    // --------------------------------------------------------------------
    new Chart(document.getElementById('actividadesChart'), {
        type: 'bar',
        data: {
            labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
            datasets: [{ data: [4,6,5,8,7,9,6,8,10,7,5,6], backgroundColor: 'rgba(15,143,103,0.15)', borderColor: '#0f8f67', borderWidth: 2, borderRadius: 6 }]
        },
        options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#f1f5f9' }, ticks: { stepSize: 2 } } } }
    });
    new Chart(document.getElementById('reportesChart'), {
        type: 'line',
        data: {
            labels: ['Ene','Feb','Mar','Abr','May','Jun'],
            datasets: [
                { label: 'Café', data: [12,15,14,18,16,20], borderColor: '#0b6b4f', tension: 0.4, fill: false },
                { label: 'Maóz', data: [8,10,9,12,11,14], borderColor: '#f59e0b', tension: 0.4, fill: false }
            ]
        },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#f1f5f9' } } } }
    });
});
</script>
<!-- Visor de Imágenes -->
<div id="modal-visor-imagen" style="display:none" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-md p-4">
    <div class="relative max-w-4xl w-full flex flex-col items-center">
        <button onclick="document.getElementById('modal-visor-imagen').classList.add('hidden'); document.getElementById('modal-visor-imagen').style.display='none'" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-3xl">
            <i class="fas fa-times"></i>
        </button>
        <img id="visor-img" src="" alt="Vista ampliada" class="max-h-[80vh] object-contain rounded-xl shadow-2xl mb-4">
        <div class="bg-white/10 backdrop-blur-md text-white px-6 py-3 rounded-2xl text-center max-w-lg">
            <h4 id="visor-titulo" class="font-bold text-lg"></h4>
            <p id="visor-desc" class="text-sm text-gray-300"></p>
        </div>
    </div>
</div>

</body>
</html>





