<?php
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/session.php';
if (!isset($_SESSION['id_usuario'])) { header('Location: ../auth/login.php'); exit; }
if ((int)($_SESSION['id_rol'] ?? 0) === 1) { header('Location: admin.php'); exit; }

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once $rootPath . '/config/database.php';
require_once $rootPath . '/models/AsignacionLote.php';
require_once $rootPath . '/models/Cultivo.php';

$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Trabajador');
$iniciales      = strtoupper(substr($_SESSION['nombre'] ?? 'T', 0, 2));
$id_usuario     = (int)$_SESSION['id_usuario'];

$fecha_hoy = (new DateTime())->format('l, j \d\e F \d\e Y');
$dias  = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$meses = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
$fecha_es = strtr($fecha_hoy, array_merge($dias, $meses));

$db = (new Database())->conectar();

// Lotes asignados al trabajador (solo activos)
$asignacionModel = new AsignacionLote($db);
$misLotes = $asignacionModel->obtenerPorTrabajador($id_usuario);
$lotesActivos = array_filter($misLotes, fn($l) => $l['activo'] == 1);
$idsLotesActivos = array_column(array_values($lotesActivos), 'id_lote') ?? [];

// Todas las actividades asignadas al trabajador
require_once $rootPath . '/models/Actividad.php';
$actividadModel  = new Actividad($db);
$misActividades  = $actividadModel->obtenerPorTrabajador($id_usuario);

// Cultivos activos en mis lotes
$misCultivos = [];
if (!empty($idsLotesActivos)) {
    $placeholders = implode(',', array_fill(0, count($idsLotesActivos), '?'));
    $stmt = $db->prepare(
        "SELECT c.id_cultivo, c.codigo, c.estado, c.fecha_siembra, c.fecha_cosecha_estimada, c.observaciones, c.fotografia,
                l.id_lote, l.identificador AS lote_id, l.nombre AS lote_nombre, l.area_ha,
                v.nombre AS variedad_nombre, tc.nombre AS tipo_nombre
         FROM cultivos c
         JOIN lotes l ON c.id_lote = l.id_lote
         JOIN variedades v ON c.id_variedad = v.id_variedad
         JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
         WHERE c.id_lote IN ($placeholders) AND c.activo_en_lote IS NOT NULL
         ORDER BY c.fecha_cosecha_estimada ASC"
    );
    $stmt->execute($idsLotesActivos);
    $misCultivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Notificaciones del trabajador
$notifStmt = $db->prepare(
    "SELECT id_notificacion, tipo, prioridad, titulo, mensaje, leida, creada_en
     FROM notificaciones WHERE id_usuario = :u ORDER BY leida ASC, creada_en DESC LIMIT 20"
);
$notifStmt->execute([':u' => $id_usuario]);
$notificaciones_t   = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
$notifs_no_leidas_t = count(array_filter($notificaciones_t, fn($n) => !$n['leida']));

// última Sincronización
$syncStmt = $db->prepare("SELECT finalizada_en FROM sincronizaciones WHERE id_usuario = :u ORDER BY finalizada_en DESC LIMIT 1");
$syncStmt->execute([':u' => $id_usuario]);
$ultimaSyncT = $syncStmt->fetchColumn() ?: null;

$misFotosCultivos = [];
foreach ($misCultivos as $c) {
    if (!empty($c['fotografia'])) $misFotosCultivos[] = $c;
}
$misFotosActividades = [];
foreach ($misActividades as $a) {
    if (!empty($a['fotografia'] ?? '')) $misFotosActividades[] = $a;
}

// Actividades pendientes del día (para los cultivos del trabajador)
$actividadesHoy = [];
if (!empty($misCultivos)) {
    $idsCultivos = array_column($misCultivos, 'id_cultivo');
    $ph = implode(',', array_fill(0, count($idsCultivos), '?'));
    $stmt2 = $db->prepare(
        "SELECT a.id_actividad, a.descripcion, a.fecha_programada, a.estado,
                ta.nombre AS tipo_actividad, l.nombre AS lote_nombre, l.identificador AS lote_id
         FROM actividades a
         JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
         JOIN cultivos c ON a.id_cultivo = c.id_cultivo
         JOIN lotes l ON c.id_lote = l.id_lote
         WHERE a.id_cultivo IN ($ph)
           AND (a.id_asignado_a = ? OR a.id_asignado_a IS NULL)
           AND a.estado = 'pendiente'
           AND a.fecha_programada = CURDATE()
         ORDER BY a.fecha_programada ASC"
    );
    $stmt2->execute(array_merge($idsCultivos, [$id_usuario]));
    $actividadesHoy = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

$tieneAsignaciones = !empty($lotesActivos);

// Perfil del trabajador
$stmtPerfil = $db->prepare("SELECT id_usuario, nombre, correo, telefono, creado_en, ultimo_acceso FROM usuarios WHERE id_usuario = :id LIMIT 1");
$stmtPerfil->execute([':id' => $id_usuario]);
$perfilTrabajador = $stmtPerfil->fetch(PDO::FETCH_ASSOC) ?: [
    'id_usuario' => $id_usuario, 'nombre' => $_SESSION['nombre'] ?? '',
    'correo' => $_SESSION['correo'] ?? '', 'telefono' => '',
    'creado_en' => null, 'ultimo_acceso' => null, 'foto_perfil' => null
];
try {
    $fotoT = $db->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $fotoT->execute([':id' => $id_usuario]);
    $perfilTrabajador['foto_perfil'] = $fotoT->fetchColumn() ?: null;
} catch (PDOException $e) {
    $perfilTrabajador['foto_perfil'] = null;
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGVOS | Panel Trabajador</title>
    <link rel="icon" href="../../public/img/icono-pagina.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --sidebar-w:280px; }
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
        .mini-dot { width:10px; height:10px; border-radius:999px; margin-top:5px; flex-shrink:0; }
        .hero-panel { background:linear-gradient(135deg, #1d4533 0%, #2b845a 100%); border-radius:28px; padding:28px; color:white; position:relative; overflow:hidden; box-shadow: 0 15px 35px rgba(43,132,90,0.2); }
        .hero-panel::after { content:""; position:absolute; right:-60px; bottom:-60px; width:220px; height:220px; background:radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius:50%; }
        .module { display:none; } .module.active { display:block; animation:fadeUp .4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeUp { from{opacity:0;transform:translateY(15px)} to{opacity:1;transform:translateY(0)} }
        .badge-worker { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:999px; font-size:0.65rem; font-weight:700; border: 1px solid #10b981; }
        .offline-banner { background:#fef3c7; border:1px solid #fcd34d; border-radius:14px; padding:12px 16px; display:flex; align-items:center; gap:10px; font-size:0.75rem; color:#92400e; }
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

<?php include '../../views/partials/trabajador/sidebar.php'; ?>

<!-- MAIN -->
<div id="main-content">
<?php include '../../views/partials/trabajador/header.php'; ?>

    <main class="flex-1 px-6 pb-6 pt-3">

        <!-- -- MÓDULO: MI PANEL -- -->
        <div id="module-panel" class="module active">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Hola, <?= $nombre_usuario ?></h2>
                <p class="text-sm text-slate-500 mt-1">Aquí está tu resumen de trabajo de hoy.</p>
            </div>

            <?php if (!$tieneAsignaciones): ?>
            <!-- Sin asignaciones activas -->
            <div class="glass-card rounded-2xl p-10 text-center mb-6">
                <i class="fas fa-map-location-dot text-5xl text-slate-200 mb-4 block"></i>
                <h3 class="text-lg font-bold text-slate-500 mb-2">Sin asignaciones activas</h3>
                <p class="text-sm text-slate-400">Aún no tienes lotes asignados. Contacta al administrador.</p>
            </div>
            <?php else: ?>

            <div class="hero-panel mb-6">
                <div class="relative z-10 max-w-xl">
                    <p class="text-white/70 text-xs uppercase tracking-widest mb-2">Resumen del día</p>
                    <h3 class="text-2xl font-extrabold leading-snug mb-3">Tus tareas y cultivos asignados</h3>
                    <div class="flex flex-wrap gap-2">
                        <span class="bg-white/15 border border-white/10 px-4 py-1.5 rounded-full text-xs"><?= count($lotesActivos) ?> lote(s) asignado(s)</span>
                        <span class="bg-white/15 border border-white/10 px-4 py-1.5 rounded-full text-xs"><?= count($misCultivos) ?> cultivo(s) activo(s)</span>
                        <span class="bg-white/15 border border-white/10 px-4 py-1.5 rounded-full text-xs"><?= count($actividadesHoy) ?> actividad(es) hoy</span>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4 cursor-pointer hover:shadow-lg transition" onclick="switchModule('lotes',document.getElementById('nav-lotes'))">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#065f46,#10b981);"><i class="fas fa-map"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= count($lotesActivos) ?></p><p class="text-xs text-slate-500">Lotes asignados</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4 cursor-pointer hover:shadow-lg transition" onclick="switchModule('cultivos',document.getElementById('nav-cultivos'))">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#0d7a5a,#34d399);"><i class="fas fa-seedling"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= count($misCultivos) ?></p><p class="text-xs text-slate-500">Cultivos activos</p></div>
                </div>
                <div class="glass-card rounded-2xl p-5 flex items-center gap-4 cursor-pointer hover:shadow-lg transition" onclick="switchModule('actividades',document.getElementById('nav-actividades'))">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0" style="background:linear-gradient(135deg,#b45309,#fbbf24);"><i class="fas fa-bolt"></i></div>
                    <div><p class="text-2xl font-extrabold"><?= count($actividadesHoy) ?></p><p class="text-xs text-slate-500">Actividades hoy</p></div>
                </div>            </div>

            <!-- Tabla cultivos + actividades hoy -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 glass-card rounded-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-5 border-b border-[#d8eee4]">
                        <div><h3 class="font-bold text-[#16332b]">Mis cultivos</h3><p class="text-xs text-slate-500 mt-0.5">Lotes bajo tu responsabilidad</p></div>
                        <a onclick="switchModule('cultivos',document.getElementById('nav-cultivos'))" class="text-xs font-semibold text-[#0f8f67] hover:underline cursor-pointer">Ver todos</a>
                    </div>
                    <?php if (empty($misCultivos)): ?>
                    <div class="p-8 text-center text-slate-400 text-sm">No hay cultivos activos en tus lotes.</div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead><tr class="bg-[#f5fbf8]">
                                <?php foreach (['Código','Variedad','Lote','Cosecha estimada','Estado'] as $h): ?>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php
                                $badgeC = ['sembrado'=>'bg-blue-100 text-blue-700','desarrollo'=>'bg-yellow-100 text-yellow-700','maduro'=>'bg-emerald-100 text-emerald-700','cosechado'=>'bg-slate-100 text-slate-600'];
                                foreach ($misCultivos as $c):
                                    $cls = $badgeC[$c['estado']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                <tr class="border-b border-[#eef4f1] hover:bg-[#f5fbf8] transition">
                                    <td class="px-6 py-4 text-sm font-semibold text-[#0f8f67]"><?= htmlspecialchars($c['codigo']) ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($c['variedad_nombre']) ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-500"><?= htmlspecialchars($c['lote_id'] . ' — ' . $c['lote_nombre']) ?></td>
                                    <td class="px-6 py-4 text-sm font-semibold text-slate-700"><?= date('d/m/Y', strtotime($c['fecha_cosecha_estimada'])) ?></td>
                                    <td class="px-6 py-4"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $cls ?>"><?= ucfirst($c['estado']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="glass-card rounded-2xl p-6">
                    <div class="mb-4"><h3 class="font-bold text-[#16332b]">Actividades de hoy</h3><p class="text-xs text-slate-500 mt-0.5"><?= date('d/m/Y') ?></p></div>
                    <?php if (empty($actividadesHoy)): ?>
                    <div class="text-center py-6 text-slate-400 text-sm"><i class="fas fa-circle-check text-3xl text-emerald-200 block mb-2"></i>Sin actividades pendientes hoy</div>
                    <?php else: ?>
                    <?php foreach ($actividadesHoy as $i => $act): ?>
                    <div class="flex items-start gap-3 py-3 <?= $i > 0 ? 'border-t border-[#eef4f1]' : '' ?>">
                        <span class="mini-dot bg-amber-400 mt-1.5"></span>
                        <div>
                            <p class="font-semibold text-sm text-[#16332b]"><?= htmlspecialchars($act['tipo_actividad']) ?> — <?= htmlspecialchars($act['lote_id']) ?></p>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($act['descripcion']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- -- MÓDULO: MIS LOTES -- -->
        <div id="module-lotes" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Mis Lotes</h2>
                <p class="text-sm text-slate-500 mt-1">Lotes asignados a tu cargo. Solo puedes ver los que te corresponden.</p>
            </div>

            <?php if (!$tieneAsignaciones): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-lock text-5xl text-slate-200 mb-4 block"></i>
                <h3 class="text-lg font-bold text-slate-400 mb-2">Sin asignaciones activas</h3>
                <p class="text-sm text-slate-400">No tienes lotes asignados actualmente.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php foreach ($lotesActivos as $lote):
                    // Buscar cultivo activo en este lote
                    $cultivoLote = null;
                    foreach ($misCultivos as $c) {
                        if ($c['id_lote'] == $lote['id_lote']) { $cultivoLote = $c; break; }
                    }
                    $badgeEstado = ['disponible'=>'bg-emerald-100 text-emerald-700','ocupado'=>'bg-blue-100 text-blue-700','en_descanso'=>'bg-yellow-100 text-yellow-700','inactivo'=>'bg-red-100 text-red-700'];
                    $clsLote = $badgeEstado[$lote['lote_estado']] ?? 'bg-slate-100 text-slate-600';
                ?>
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl font-extrabold flex-shrink-0" style="background:linear-gradient(135deg,#065f46,#10b981);">
                            <?= htmlspecialchars($lote['lote_id']) ?>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $clsLote ?>"><?= ucfirst(str_replace('_',' ',$lote['lote_estado'])) ?></span>
                    </div>
                    <h3 class="font-bold text-[#16332b] text-base mb-1"><?= htmlspecialchars($lote['lote_nombre']) ?></h3>
                    <p class="text-xs text-slate-500 mb-4"><?= number_format($lote['area_ha'], 2) ?> ha — Asignado el <?= date('d/m/Y', strtotime($lote['creado_en'])) ?></p>

                    <?php if ($cultivoLote): ?>
                    <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-100">
                        <p class="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-1">Cultivo activo</p>
                        <p class="text-sm font-semibold text-[#065f46]"><?= htmlspecialchars($cultivoLote['codigo']) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($cultivoLote['variedad_nombre']) ?> — <?= htmlspecialchars($cultivoLote['tipo_nombre']) ?></p>
                        <p class="text-xs text-slate-500 mt-1">Cosecha estimada: <span class="font-semibold"><?= date('d/m/Y', strtotime($cultivoLote['fecha_cosecha_estimada'])) ?></span></p>
                    </div>
                    <?php else: ?>
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 text-center">
                        <p class="text-xs text-slate-400">Sin cultivo activo en este lote</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Historial de asignaciones -->
            <?php $historial = array_filter($misLotes, fn($l) => $l['activo'] == 0); ?>
            <?php if (!empty($historial)): ?>
            <div class="glass-card rounded-2xl overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-[#d8eee4]">
                    <h3 class="font-bold text-[#16332b]">Historial de asignaciones</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Lotes que estuvieron bajo tu cargo anteriormente</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-[#f0fdf4]">
                        <tr>
                            <?php foreach (['Lote','Área','Estado lote','Asignado el'] as $h): ?>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0fdf4]">
                        <?php foreach ($historial as $h): ?>
                        <tr class="hover:bg-[#f9fefb] transition opacity-60">
                            <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($h['lote_id'] . ' — ' . $h['lote_nombre']) ?></td>
                            <td class="px-5 py-3 text-gray-500"><?= number_format($h['area_ha'], 2) ?> ha</td>
                            <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">Desactivado</span></td>
                            <td class="px-5 py-3 text-gray-500"><?= date('d/m/Y', strtotime($h['creado_en'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- -- MÓDULO: CULTIVOS -- -->
        <div id="module-cultivos" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Cultivos</h2>
                <p class="text-sm text-slate-500 mt-1">Detalle de los cultivos activos en tus lotes asignados.</p>
            </div>

            <?php if (!$tieneAsignaciones): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-lock text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">Acceso bloqueado — no tienes lotes asignados.</p>
            </div>
            <?php elseif (empty($misCultivos)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-seedling text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">No hay cultivos activos en tus lotes.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php
                $badgeC = ['sembrado'=>'bg-blue-100 text-blue-700','desarrollo'=>'bg-yellow-100 text-yellow-700','maduro'=>'bg-emerald-100 text-emerald-700','cosechado'=>'bg-slate-100 text-slate-600'];
                foreach ($misCultivos as $c):
                    $cls = $badgeC[$c['estado']] ?? 'bg-slate-100 text-slate-600';
                    $diasRestantes = (new DateTime())->diff(new DateTime($c['fecha_cosecha_estimada']))->days;
                    $esPasado = new DateTime($c['fecha_cosecha_estimada']) < new DateTime();
                ?>
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider mb-1"><?= htmlspecialchars($c['tipo_nombre']) ?></p>
                            <h3 class="font-extrabold text-[#065f46] text-lg"><?= htmlspecialchars($c['codigo']) ?></h3>
                            <p class="text-sm text-slate-500"><?= htmlspecialchars($c['variedad_nombre']) ?></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $cls ?>"><?= ucfirst($c['estado']) ?></span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                        <div class="bg-slate-50 rounded-xl p-3">
                            <p class="text-xs text-slate-400 mb-0.5">Lote</p>
                            <p class="font-semibold text-[#16332b]"><?= htmlspecialchars($c['lote_id'] . ' — ' . $c['lote_nombre']) ?></p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-3">
                            <p class="text-xs text-slate-400 mb-0.5">Siembra</p>
                            <p class="font-semibold text-[#16332b]"><?= date('d/m/Y', strtotime($c['fecha_siembra'])) ?></p>
                        </div>
                    </div>
                    <div class="<?= $esPasado ? 'bg-red-50 border-red-100' : 'bg-emerald-50 border-emerald-100' ?> rounded-xl p-3 border flex items-center justify-between">
                        <div>
                            <p class="text-xs <?= $esPasado ? 'text-red-500' : 'text-emerald-600' ?> font-bold uppercase tracking-wider mb-0.5">Cosecha estimada</p>
                            <p class="font-semibold text-sm <?= $esPasado ? 'text-red-700' : 'text-emerald-700' ?>"><?= date('d/m/Y', strtotime($c['fecha_cosecha_estimada'])) ?></p>
                        </div>
                        <span class="text-xs font-bold <?= $esPasado ? 'text-red-500' : 'text-emerald-600' ?>">
                            <?= $esPasado ? 'Vencida' : $diasRestantes . ' días' ?>
                        </span>
                    </div>
                    <?php if (!empty($c['observaciones'])): ?>
                    <p class="text-xs text-slate-400 mt-3 italic"><?= htmlspecialchars($c['observaciones']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- -- MÓDULO: ACTIVIDADES -- -->
        <div id="module-actividades" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Mis Actividades</h2>
                <p class="text-sm text-slate-500 mt-1">Gestiona y actualiza el estado de tus tareas asignadas.</p>
            </div>

            <!-- Tabs -->
            <div class="glass-card rounded-2xl p-1 mb-5 inline-flex gap-1">
                <button onclick="cambiarTabActividad('hoy')" id="tab-act-hoy"
                    class="px-5 py-2 text-sm font-semibold rounded-xl bg-[#065f46] text-white transition">
                    <i class="fas fa-calendar-day mr-1"></i> Hoy
                    <?php if (!empty($actividadesHoy)): ?>
                    <span class="ml-1 bg-amber-400 text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?= count($actividadesHoy) ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="cambiarTabActividad('todas')" id="tab-act-todas"
                    class="px-5 py-2 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 transition">
                    <i class="fas fa-list-check mr-1"></i> Todas (<?= count($misActividades) ?>)
                </button>
            </div>

            <!-- TAB: Hoy -->
            <div id="tab-act-content-hoy">
                <?php if (!$tieneAsignaciones): ?>
                <div class="glass-card rounded-2xl p-12 text-center">
                    <i class="fas fa-lock text-5xl text-slate-200 mb-4 block"></i>
                    <p class="text-slate-400 text-sm">No tienes lotes asignados.</p>
                </div>
                <?php elseif (empty($actividadesHoy)): ?>
                <div class="glass-card rounded-2xl p-12 text-center">
                    <i class="fas fa-circle-check text-5xl text-emerald-200 mb-4 block"></i>
                    <h3 class="text-lg font-bold text-slate-400 mb-2">Sin actividades pendientes hoy</h3>
                    <p class="text-sm text-slate-400"><?= date('d/m/Y') ?> — No tienes tareas programadas para hoy.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($actividadesHoy as $act): ?>
                    <div class="glass-card rounded-2xl p-5 flex items-center gap-5">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                            style="background:linear-gradient(135deg,#b45309,#fbbf24);">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-[#16332b]"><?= htmlspecialchars($act['tipo_actividad']) ?></p>
                            <p class="text-sm text-slate-500"><?= htmlspecialchars($act['descripcion']) ?></p>
                            <p class="text-xs text-slate-400 mt-1">Lote: <?= htmlspecialchars($act['lote_id'] . ' — ' . $act['lote_nombre']) ?></p>
                        </div>
                        <form action="../../controllers/TrabajadorActividadController.php" method="POST">
                            <input type="hidden" name="id_actividad" value="<?= $act['id_actividad'] ?>">
                            <select name="estado" onchange="this.form.submit()"
                                class="text-xs px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-400 cursor-pointer">
                                <option value="pendiente" selected>Pendiente</option>
                                <option value="en_proceso">En proceso</option>
                                <option value="completada">Completada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Todas -->
            <div id="tab-act-content-todas" class="hidden">
                <?php if (empty($misActividades)): ?>
                <div class="glass-card rounded-2xl p-12 text-center">
                    <i class="fas fa-list-check text-5xl text-slate-200 mb-4 block"></i>
                    <p class="text-slate-400 text-sm">No tienes actividades asignadas aún.</p>
                </div>
                <?php else:
                    $badgeAct = [
                        'pendiente'  => 'bg-amber-100 text-amber-700',
                        'en_proceso' => 'bg-blue-100 text-blue-700',
                        'completada' => 'bg-emerald-100 text-emerald-700',
                        'cancelada'  => 'bg-red-100 text-red-600',
                    ];
                    $grupos = ['pendiente'=>[],'en_proceso'=>[],'completada'=>[],'cancelada'=>[]];
                    foreach ($misActividades as $act) $grupos[$act['estado']][] = $act;
                    $labels = ['pendiente'=>'Pendientes','en_proceso'=>'En proceso','completada'=>'Completadas','cancelada'=>'Canceladas'];
                ?>
                <!-- KPIs -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <?php foreach ($grupos as $estado => $items): ?>
                    <div class="glass-card rounded-xl p-4 text-center">
                        <p class="text-2xl font-extrabold text-[#16332b]"><?= count($items) ?></p>
                        <p class="text-xs mt-1 font-semibold <?= str_replace('bg-','text-', explode(' ',$badgeAct[$estado])[0]) ?> <?= explode(' ',$badgeAct[$estado])[1] ?>"><?= $labels[$estado] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tabla -->
                <div class="glass-card rounded-2xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f0fdf4]">
                            <tr>
                                <?php foreach (['Tipo','Descripción','Cultivo','Lote','Fecha','Estado','Acción'] as $h): ?>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-[#6b9e8a] uppercase tracking-wider border-b border-[#d8eee4]"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0fdf4]">
                            <?php foreach ($misActividades as $act):
                                $cls = $badgeAct[$act['estado']] ?? 'bg-slate-100 text-slate-600';
                                $esHoy    = $act['fecha_programada'] === date('Y-m-d');
                                $esPasada = $act['fecha_programada'] < date('Y-m-d') && $act['estado'] === 'pendiente';
                            ?>
                            <tr class="hover:bg-[#f9fefb] transition <?= $esPasada ? 'bg-red-50/40' : '' ?>">
                                <td class="px-5 py-3 font-semibold text-[#065f46]"><?= htmlspecialchars($act['tipo_actividad']) ?></td>
                                <td class="px-5 py-3 text-gray-700 max-w-xs truncate"><?= htmlspecialchars($act['descripcion']) ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($act['cultivo_codigo']) ?></td>
                                <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($act['lote_id'] . ' — ' . $act['lote_nombre']) ?></td>
                                <td class="px-5 py-3">
                                    <?php if ($act['fecha_programada']): ?>
                                    <span class="<?= $esHoy ? 'font-bold text-emerald-600' : ($esPasada ? 'text-red-500 font-semibold' : 'text-gray-500') ?>">
                                        <?= date('d/m/Y', strtotime($act['fecha_programada'])) ?>
                                        <?= $esHoy ? ' (Hoy)' : ($esPasada ? ' (Pasada)' : '') ?>
                                    </span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $cls ?>">
                                        <?= ucfirst(str_replace('_',' ',$act['estado'])) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <?php if ($act['estado'] !== 'completada' && $act['estado'] !== 'cancelada'): ?>
                                    <form action="../../controllers/TrabajadorActividadController.php" method="POST">
                                        <input type="hidden" name="id_actividad" value="<?= $act['id_actividad'] ?>">
                                        <select name="estado" onchange="this.form.submit()"
                                            class="text-xs px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-400 cursor-pointer">
                                            <option value="pendiente"  <?= $act['estado']==='pendiente'  ?'selected':'' ?>>Pendiente</option>
                                            <option value="en_proceso" <?= $act['estado']==='en_proceso' ?'selected':'' ?>>En proceso</option>
                                            <option value="completada">Completada</option>
                                            <option value="cancelada">Cancelada</option>
                                        </select>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">Finalizada</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- -- MÓDULO: FOTOGRAFÍAS -- -->
        <div id="module-fotos" class="module">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-extrabold text-[#16332b]">Fotografías de mis cultivos</h2>
                    <p class="text-sm text-slate-500 mt-1">Documenta visualmente el progreso de los cultivos.</p>
                </div>
                <?php if ($tieneAsignaciones && !empty($misCultivos)): ?>
                <button onclick="document.getElementById('modal-subir-foto-t').classList.remove('hidden'); document.getElementById('modal-subir-foto-t').style.display=''"
                    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fas fa-camera"></i> Subir foto
                </button>
                <?php endif; ?>
            </div>

            <?php if (!$tieneAsignaciones || empty($misCultivos)): ?>
            <div class="glass-card rounded-2xl p-12 text-center">
                <i class="fas fa-camera text-5xl text-slate-200 mb-4 block"></i>
                <p class="text-slate-400 text-sm">No tienes cultivos asignados para fotografiar.</p>
            </div>
            <?php else: ?>

            <!-- Fotos de cultivos -->
            <h3 class="font-bold text-[#16332b] mb-3">Mis cultivos</h3>
            <div id="fotos-cultivos-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-6">
                <?php foreach ($misCultivos as $c): ?>
                <div class="glass-card rounded-2xl overflow-hidden" data-cultivo-id="<?= $c['id_cultivo'] ?>">
                    <?php if (!empty($c['fotografia'])): ?>
                    <div class="aspect-square overflow-hidden cursor-pointer"
                        onclick="verFotoT('../../<?= htmlspecialchars($c['fotografia']) ?>','<?= htmlspecialchars(addslashes($c['codigo'])) ?>')">
                        <img src="../../<?= htmlspecialchars($c['fotografia']) ?>" class="w-full h-full object-cover hover:scale-105 transition duration-300">
                    </div>
                    <?php else: ?>
                    <div class="aspect-square bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-seedling text-3xl text-slate-200"></i>
                    </div>
                    <?php endif; ?>
                    <div class="p-3 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-[#065f46]"><?= htmlspecialchars($c['codigo']) ?></p>
                            <p class="text-[10px] text-slate-400"><?= htmlspecialchars($c['lote_id']) ?></p>
                        </div>
                        <form action="../../controllers/FotoController.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="subir_cultivo">
                            <input type="hidden" name="id_cultivo" value="<?= $c['id_cultivo'] ?>">
                            <label class="bg-[#10b981] hover:bg-[#059669] text-white text-[10px] px-2 py-1.5 rounded-lg font-semibold cursor-pointer transition flex items-center gap-1">
                                <i class="fas fa-camera"></i>
                                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
                            </label>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Fotos de actividades -->
            <?php if (!empty($misFotosActividades)): ?>
            <h3 class="font-bold text-[#16332b] mb-3">Mis actividades con foto</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <?php foreach ($misFotosActividades as $a): ?>
                <div class="group relative rounded-2xl overflow-hidden bg-slate-100 aspect-square cursor-pointer"
                    onclick="verFotoT('../../<?= htmlspecialchars($a['fotografia']) ?>','<?= htmlspecialchars(addslashes($a['tipo_actividad'] ?? '')) ?>')">
                    <img src="../../<?= htmlspecialchars($a['fotografia']) ?>" class="w-full h-full object-cover hover:scale-105 transition duration-300">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-end justify-end p-2">
                        <a href="#"
                            onclick="event.stopPropagation(); confirmarEliminarFotoT('../../controllers/FotoController.php?accion=eliminar_actividad&id=<?= $a['id_actividad'] ?>')"
                            class="bg-red-500 hover:bg-red-600 text-white text-[10px] px-2 py-1 rounded-lg font-semibold transition">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 px-2 py-1.5">
                        <p class="text-white text-[10px] font-semibold"><?= htmlspecialchars($a['tipo_actividad'] ?? '') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- MODAL: Subir foto trabajador -->
        <div id="modal-subir-foto-t" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-[#065f46]">Subir fotografía</h3>
                    <button onclick="document.getElementById('modal-subir-foto-t').classList.add('hidden'); document.getElementById('modal-subir-foto-t').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
                </div>
                <form action="../../controllers/FotoController.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="accion" id="foto-accion-t" value="subir_cultivo">

                    <!-- Selector tipo -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Tipo de fotografía *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-3 p-3 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer" id="tipo-cultivo-lbl-t">
                                <input type="radio" name="tipo_foto" value="cultivo" checked onchange="cambiarTipoFotoT(this.value)" class="accent-emerald-600">
                                <div>
                                    <p class="text-sm font-semibold text-[#065f46]"><i class="fas fa-seedling mr-1"></i> Cultivo</p>
                                    <p class="text-[10px] text-slate-400">Progreso del cultivo</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer" id="tipo-actividad-lbl-t">
                                <input type="radio" name="tipo_foto" value="actividad" onchange="cambiarTipoFotoT(this.value)" class="accent-emerald-600">
                                <div>
                                    <p class="text-sm font-semibold text-slate-600"><i class="fas fa-calendar-days mr-1"></i> Actividad</p>
                                    <p class="text-[10px] text-slate-400">Evidencia de labor</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Selector cultivo -->
                    <div id="sel-cultivo-wrap-t">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Cultivo *</label>
                        <select name="id_cultivo" id="sel-cultivo-t" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($misCultivos as $c): ?>
                            <option value="<?= $c['id_cultivo'] ?>"><?= htmlspecialchars($c['codigo'] . ' — ' . $c['lote_id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selector actividad -->
                    <div id="sel-actividad-wrap-t" class="hidden">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Actividad *</label>
                        <select name="id_actividad" id="sel-actividad-t" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($misActividades as $a): ?>
                            <option value="<?= $a['id_actividad'] ?>"><?= htmlspecialchars($a['tipo_actividad'] . ' — ' . $a['lote_id'] . ' — ' . ($a['fecha_programada'] ? date('d/m/Y', strtotime($a['fecha_programada'])) : 'Sin fecha')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Foto * (JPG/PNG/WEBP, máx 5MB)</label>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm"
                            onchange="previsualizarFotoT(this)">
                        <img id="foto-preview-t" src="" alt="" class="hidden mt-3 rounded-xl max-h-40 object-cover w-full">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('modal-subir-foto-t').classList.add('hidden'); document.getElementById('modal-subir-foto-t').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold transition">Subir</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Ver foto ampliada trabajador -->
        <div id="modal-ver-foto-t" style="display:none" class="hidden fixed inset-0 z-[999] flex items-center justify-center bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-ver-foto-t').classList.add('hidden'); document.getElementById('modal-ver-foto-t').style.display='none'">
            <div class="relative max-w-3xl w-full mx-4" onclick="event.stopPropagation()">
                <button onclick="document.getElementById('modal-ver-foto-t').classList.add('hidden'); document.getElementById('modal-ver-foto-t').style.display='none'" class="absolute -top-10 right-0 text-white/70 hover:text-white text-2xl"><i class="fas fa-times"></i></button>
                <img id="foto-ampliada-t" src="" class="w-full rounded-2xl shadow-2xl max-h-[75vh] object-contain">
                <div class="bg-white/10 backdrop-blur-md rounded-xl mt-3 px-4 py-3 text-white text-sm">
                    <p id="foto-desc-t" class="font-semibold"></p>
                </div>
            </div>
        </div>

        <!-- MODAL: Editar descripción trabajador -->
        <div id="modal-desc-foto-t" style="display:none" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 p-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-[#065f46]">Editar descripción</h3>
                    <button onclick="document.getElementById('modal-desc-foto-t').classList.add('hidden'); document.getElementById('modal-desc-foto-t').style.display='none'" class="text-gray-400 hover:text-gray-600 text-xl"><i class="fas fa-times"></i></button>
                </div>
                <form action="../../controllers/FotoController.php" method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="editar_descripcion_actividad">
                    <input type="hidden" name="id_actividad" id="desc_id_foto_t">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Descripción</label>
                        <input type="text" name="descripcion" id="desc_texto_t" maxlength="300"
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="document.getElementById('modal-desc-foto-t').classList.add('hidden'); document.getElementById('modal-desc-foto-t').style.display='none'"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-gray-50 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- -- MÓDULO: PERFIL -- -->
        <div id="module-perfil" class="module">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-[#16332b]">Mi Perfil</h2>
                <p class="text-sm text-slate-500 mt-1">Tu información personal y configuración de cuenta.</p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                <!-- Tarjeta lateral -->
                <div class="xl:col-span-1">
                    <div class="glass-card rounded-2xl p-6 text-center">
                        <div class="relative inline-block mb-4">
                            <?php 
                            $fotoPathPr = $perfilTrabajador['foto_perfil'] ?? '';
                            if ($fotoPathPr && strpos($fotoPathPr, 'storage/') === 0 && strpos($fotoPathPr, 'public/') !== 0) {
                                $fotoPathPr = 'public/' . $fotoPathPr;
                            }
                            ?>
                            <?php if (!empty($fotoPathPr)): ?>
                                <img id="perfil-foto-preview-t" src="../../<?= htmlspecialchars($fotoPathPr) ?>"
                                    class="w-28 h-28 rounded-full object-cover border-4 border-[#d1fae5] shadow-lg">
                            <?php else: ?>
                                <div id="perfil-foto-preview-div-t" class="w-28 h-28 rounded-full flex items-center justify-center text-white text-3xl font-extrabold border-4 border-[#d1fae5] shadow-lg"
                                    style="background:linear-gradient(135deg,#065f46,#10b981)">
                                    <?= $iniciales ?>
                                </div>
                                <img id="perfil-foto-preview-t" class="hidden w-28 h-28 rounded-full object-cover border-4 border-[#d1fae5] shadow-lg">
                            <?php endif; ?>
                            <label for="input-foto-perfil-t"
                                class="absolute bottom-0 right-0 w-9 h-9 bg-[#10b981] hover:bg-[#059669] rounded-full flex items-center justify-center cursor-pointer shadow-md transition"
                                title="Cambiar foto">
                                <i class="fas fa-camera text-white text-sm"></i>
                            </label>
                            <input type="file" id="input-foto-perfil-t" accept="image/jpeg,image/png,image/webp" class="hidden"
                                onchange="previsualizarFotoPerfilT(this)">
                        </div>
                        <h3 class="text-lg font-extrabold text-[#16332b]"><?= $nombre_usuario ?></h3>
                        <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($perfilTrabajador['correo'] ?? '') ?></p>
                        <span class="inline-block mt-2 px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">Trabajador</span>
                        <div class="mt-5 pt-5 border-t border-[#eef4f1] text-left space-y-3">
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <i class="fas fa-calendar-plus w-4 text-[#10b981]"></i>
                                <span>Registrado el <?= $perfilTrabajador['creado_en'] ? date('d/m/Y', strtotime($perfilTrabajador['creado_en'])) : '—' ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <i class="fas fa-map w-4 text-[#10b981]"></i>
                                <span><?= count($lotesActivos) ?> lote(s) asignado(s)</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-slate-500">
                                <i class="fas fa-seedling w-4 text-[#10b981]"></i>
                                <span><?= count($misCultivos) ?> cultivo(s) activo(s)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formularios -->
                <div class="xl:col-span-2 space-y-5">

                    <!-- Datos personales -->
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-[#d8eee4] bg-[#f0fdf4]">
                            <div>
                                <h3 class="font-bold text-[#16332b]">Información personal</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Actualiza tus datos de contacto</p>
                            </div>
                            <button onclick="document.getElementById('form-perfil-datos-t').classList.toggle('hidden'); document.getElementById('form-perfil-view-t').classList.toggle('hidden')"
                                class="w-9 h-9 rounded-xl bg-[#e6f9f0] hover:bg-[#d1fae5] flex items-center justify-center text-[#065f46] transition">
                                <i class="fas fa-pen text-sm"></i>
                            </button>
                        </div>
                        <!-- Vista -->
                        <div id="form-perfil-view-t" class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Nombre Completo</p>
                                <p class="font-semibold text-[#16332b]"><?= $nombre_usuario ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Correo Electrónico</p>
                                <p class="font-semibold text-[#16332b]"><?= htmlspecialchars($perfilTrabajador['correo'] ?? '') ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Teléfono</p>
                                <p class="font-semibold text-[#16332b]"><?= htmlspecialchars($perfilTrabajador['telefono'] ?? 'No registrado') ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-1">Fecha de Registro</p>
                                <p class="font-semibold text-[#16332b]"><?= $perfilTrabajador['creado_en'] ? date('d/m/Y', strtotime($perfilTrabajador['creado_en'])) : '—' ?></p>
                            </div>
                        </div>
                        <!-- Edición -->
                        <form id="form-perfil-datos-t" action="../../controllers/PerfilController.php" method="POST" class="hidden p-6 space-y-4">
                            <input type="hidden" name="accion" value="actualizar_datos">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nombre completo</label>
                                    <input type="text" name="nombre" value="<?= $nombre_usuario ?>" required
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Correo electrónico</label>
                                    <input type="email" name="correo" value="<?= htmlspecialchars($perfilTrabajador['correo'] ?? '') ?>" required
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Teléfono</label>
                                    <input type="tel" name="telefono" value="<?= htmlspecialchars($perfilTrabajador['telefono'] ?? '') ?>"
                                        class="w-full border border-[#d8eee4] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#10b981]">
                                </div>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button"
                                    onclick="document.getElementById('form-perfil-datos-t').classList.add('hidden'); document.getElementById('form-perfil-view-t').classList.remove('hidden')"
                                    class="px-5 py-2.5 border border-[#d8eee4] text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition text-sm">Cancelar</button>
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
                            <button onclick="document.getElementById('form-perfil-pass-t').classList.toggle('hidden')"
                                class="w-9 h-9 rounded-xl bg-[#e6f9f0] hover:bg-[#d1fae5] flex items-center justify-center text-[#065f46] transition">
                                <i class="fas fa-lock text-sm"></i>
                            </button>
                        </div>
                        <div class="px-6 py-4 text-sm text-slate-500 flex items-center gap-3">
                            <i class="fas fa-shield-halved text-[#10b981]"></i>
                            Contraseña protegida con encriptación bcrypt.
                        </div>
                        <form id="form-perfil-pass-t" action="../../controllers/PerfilController.php" method="POST" class="hidden px-6 pb-6 space-y-4">
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
                                <button type="button" onclick="document.getElementById('form-perfil-pass-t').classList.add('hidden')"
                                    class="px-5 py-2.5 border border-[#d8eee4] text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition text-sm">Cancelar</button>
                                <button type="submit"
                                    class="px-5 py-2.5 bg-[#065f46] hover:bg-[#054d38] text-white font-semibold rounded-xl transition text-sm flex items-center gap-2">
                                    <i class="fas fa-key"></i> Actualizar contraseña
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Form foto oculto -->
                    <form id="form-foto-perfil-t" action="../../controllers/PerfilController.php" method="POST" enctype="multipart/form-data" class="hidden">
                        <input type="hidden" name="accion" value="subir_foto">
                        <input type="file" name="foto" id="input-foto-perfil-form-t" accept="image/jpeg,image/png,image/webp">
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
const BASE_URL = '../..';
const SID = '<?= session_id() ?>';
const headerMeta = {
    panel:            { icon:'fa-house',        title:'Mi Panel' },
    lotes:            { icon:'fa-map',           title:'Mis Lotes' },
    cultivos:         { icon:'fa-seedling',      title:'Cultivos' },
    actividades:      { icon:'fa-calendar-days', title:'Mis Actividades' },
    fotos:            { icon:'fa-camera',        title:'Fotografías' },
    perfil:           { icon:'fa-circle-user',   title:'Mi Perfil' },
};

function switchModule(name, el) {
    console.log('Switching to module:', name);
    document.querySelectorAll('.module').forEach(m => {
        m.classList.remove('active');
        m.style.display = 'none';
    });
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    
    const target = document.getElementById('module-' + name);
    if (target) {
        target.classList.add('active');
        target.style.display = 'block';
    }
    if (el) el.classList.add('active');
    
    const m = headerMeta[name] || headerMeta.panel;
    const hIcon = document.getElementById('header-icon');
    const hTitle = document.getElementById('header-title');
    if (hIcon) hIcon.innerHTML = `<i class="fas ${m.icon}"></i>`;
    if (hTitle) hTitle.textContent = m.title;
}

// -- ACTIVIDADES TABS ------------------------------------------------------
function cambiarTabActividad(tab) {
    const esHoy = tab === 'hoy';
    const contentHoy = document.getElementById('tab-act-content-hoy');
    const contentTodas = document.getElementById('tab-act-content-todas');
    const btnHoy = document.getElementById('tab-act-hoy');
    const btnTodas = document.getElementById('tab-act-todas');
    
    if (contentHoy) contentHoy.classList.toggle('hidden', !esHoy);
    if (contentTodas) contentTodas.classList.toggle('hidden', esHoy);
    
    if (btnHoy) btnHoy.className = esHoy
        ? 'px-5 py-2 text-sm font-semibold rounded-xl bg-[#065f46] text-white transition'
        : 'px-5 py-2 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 transition';
    if (btnTodas) btnTodas.className = !esHoy
        ? 'px-5 py-2 text-sm font-semibold rounded-xl bg-[#065f46] text-white transition'
        : 'px-5 py-2 text-sm font-semibold rounded-xl text-slate-600 hover:bg-slate-50 transition';
}

// -- PERFIL TRABAJADOR -----------------------------------------------------
function previsualizarFotoPerfilT(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        const previewImg = document.getElementById('perfil-foto-preview-t');
        const previewDiv = document.getElementById('perfil-foto-preview-div-t');
        if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.classList.remove('hidden');
        }
        if (previewDiv) previewDiv.classList.add('hidden');
    };
    reader.readAsDataURL(file);
    
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
            const dt = new DataTransfer();
            dt.items.add(file);
            const formInput = document.getElementById('input-foto-perfil-form-t');
            if (formInput) {
                formInput.files = dt.files;
                document.getElementById('form-foto-perfil-t').submit();
            }
        }
    });
}

// -- FOTOGRAFÍAS TRABAJADOR ------------------------------------------------
function cambiarTipoFotoT(tipo) {
    const esCultivo = tipo === 'cultivo';
    const acc = document.getElementById('foto-accion-t');
    if (acc) acc.value = esCultivo ? 'subir_cultivo' : 'subir_actividad';
    
    const wrapC = document.getElementById('sel-cultivo-wrap-t');
    const wrapA = document.getElementById('sel-actividad-wrap-t');
    if (wrapC) wrapC.classList.toggle('hidden', !esCultivo);
    if (wrapA) wrapA.classList.toggle('hidden', esCultivo);
    
    const selC = document.getElementById('sel-cultivo-t');
    const selA = document.getElementById('sel-actividad-t');
    if (selC) selC.required = esCultivo;
    if (selA) selA.required = !esCultivo;
    
    const lblC = document.getElementById('tipo-cultivo-lbl-t');
    const lblA = document.getElementById('tipo-actividad-lbl-t');
    if (lblC) lblC.className = esCultivo
        ? 'flex items-center gap-3 p-3 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer'
        : 'flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer';
    if (lblA) lblA.className = !esCultivo
        ? 'flex items-center gap-3 p-3 border-2 border-emerald-400 bg-emerald-50 rounded-xl cursor-pointer'
        : 'flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer';
}

function previsualizarFotoT(input) {
    const preview = document.getElementById('foto-preview-t');
    if (input.files && input.files[0] && preview) {
        const reader = new FileReader();
        reader.onload = e => { 
            preview.src = e.target.result; 
            preview.classList.remove('hidden'); 
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function verFotoT(src, desc) {
    const img = document.getElementById('foto-ampliada-t');
    const d = document.getElementById('foto-desc-t');
    const modal = document.getElementById('modal-ver-foto-t');
    if (img) img.src = src;
    if (d) d.textContent = desc || '';
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function confirmarEliminarFotoT(url) {
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

// -- OFFLINE / SYNC --------------------------------------------------------
window.addEventListener('offline', () => {
    const ind = document.getElementById('offline-indicator');
    if (ind) { ind.classList.remove('hidden'); ind.classList.add('flex'); }
});
window.addEventListener('online', () => {
    const ind = document.getElementById('offline-indicator');
    if (ind) { ind.classList.add('hidden'); ind.classList.remove('flex'); }
    fetch(`${BASE_URL}/controllers/NotificacionController.php?accion=sincronizar`)
        .then(() => {
            const st = document.getElementById('sync-status-t');
            if (st) st.textContent = 'Sincronizado: ' + new Date().toLocaleString('es-CO');
        });
});

(function initFotosRefresh() {
    const INTERVAL = 30000;
    function buildCard(c) {
        const foto = c.fotografia
            ? `<div class="aspect-square overflow-hidden cursor-pointer" onclick="verFotoT('../../${c.fotografia}','${c.codigo.replace(/'/g,"\\'")}')">
                    <img src="../../${c.fotografia}" class="w-full h-full object-cover hover:scale-105 transition duration-300">
               </div>`
            : `<div class="aspect-square bg-slate-50 flex items-center justify-center">
                    <i class="fas fa-seedling text-3xl text-slate-200"></i>
               </div>`;
        return `<div class="glass-card rounded-2xl overflow-hidden" data-cultivo-id="${c.id_cultivo}">
            ${foto}
            <div class="p-3 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-[#065f46]">${c.codigo}</p>
                    <p class="text-[10px] text-slate-400">${c.lote_id}</p>
                </div>
                <form action="../../controllers/FotoController.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="subir_cultivo">
                    <input type="hidden" name="id_cultivo" value="${c.id_cultivo}">
                    <label class="bg-[#10b981] hover:bg-[#059669] text-white text-[10px] px-2 py-1.5 rounded-lg font-semibold cursor-pointer transition flex items-center gap-1">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
            </div>
        </div>`;
    }
    function refreshFotos() {
        fetch(`../../controllers/FotoController.php?accion=fotos_json&PHPSESSID=${SID}`)
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data || !data.cultivos) return;
                const grid = document.getElementById('fotos-cultivos-grid');
                if (!grid) return;
                data.cultivos.forEach(c => {
                    const card = grid.querySelector(`[data-cultivo-id="${c.id_cultivo}"]`);
                    if (card) {
                        const imgActual = card.querySelector('img');
                        const srcActual = imgActual ? imgActual.getAttribute('src') : null;
                        const srcNuevo  = c.fotografia ? `../../${c.fotografia}` : null;
                        if (srcActual !== srcNuevo) card.outerHTML = buildCard(c);
                    }
                });
            }).catch(() => {});
    }
    document.addEventListener('DOMContentLoaded', () => {
        setInterval(refreshFotos, INTERVAL);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refreshFotos();
        });
    });
})();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id^="modal-"]').forEach(m => m.classList.add('hidden'));
    const hashModulos = {
        '#perfil':       'perfil',
        '#actividades':  'actividades',
        '#lotes':        'lotes',
        '#cultivos':     'cultivos',
        '#fotos':        'fotos',
    };
    const hash = window.location.hash;
    if (hash && hashModulos[hash]) {
        switchModule(hashModulos[hash], document.getElementById('nav-' + hashModulos[hash]));
        history.replaceState(null, '', window.location.pathname);
    }
});
</script>
</body>
</html>



