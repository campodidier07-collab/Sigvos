<!-- SIDEBAR ADMIN -->
<aside id="sidebar" class="flex flex-col">
    <div class="brand-box rounded-2xl p-4 mb-8">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center text-xl"><img src="../../public/img/icono.png" class="w-7 h-7 object-contain"></div>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight">SIGVOS</h1>
                <p class="text-xs text-white/70">Panel Administrador</p>
            </div>
        </div>
    </div>
    <nav class="space-y-1 flex-1">
        <p class="text-xs font-bold text-white/40 uppercase tracking-widest px-3 mb-2">Principal</p>
        <a onclick="switchModule('panel',this)" class="nav-link active" id="nav-panel">
            <i class="fas fa-house text-sm w-4 text-center"></i> Panel
        </a>

        <p class="text-xs font-bold text-white/40 uppercase tracking-widest px-3 mt-4 mb-2">Gestión</p>
        <a onclick="switchModule('cultivos',this)" class="nav-link" id="nav-cultivos">
            <i class="fas fa-seedling text-sm w-4 text-center"></i> Cultivos
        </a>
        <a onclick="switchModule('lotes',this)" class="nav-link" id="nav-lotes">
            <i class="fas fa-map text-sm w-4 text-center"></i> Lotes
        </a>
        <a onclick="switchModule('asignaciones',this)" class="nav-link" id="nav-asignaciones">
            <i class="fas fa-user-check text-sm w-4 text-center"></i> Asignaciones
        </a>
        <a onclick="switchModule('actividades',this)" class="nav-link" id="nav-actividades">
            <i class="fas fa-calendar-days text-sm w-4 text-center"></i> Actividades
        </a>
        <a onclick="switchModule('cosecha',this)" class="nav-link" id="nav-cosecha">
            <i class="fas fa-basket-shopping text-sm w-4 text-center"></i> Cosecha
        </a>
        <a onclick="switchModule('fotos',this)" class="nav-link" id="nav-fotos">
            <i class="fas fa-camera text-sm w-4 text-center"></i> Fotografías
        </a>
        <p class="text-xs font-bold text-white/40 uppercase tracking-widest px-3 mt-4 mb-2">Análisis</p>
        <a onclick="switchModule('trabajadores',this)" class="nav-link" id="nav-trabajadores">
            <i class="fas fa-hard-hat text-sm w-4 text-center"></i> Trabajadores
        </a>
        <a onclick="switchModule('calendario',this)" class="nav-link" id="nav-calendario">
            <i class="fas fa-calendar-alt text-sm w-4 text-center"></i> Calendario
        </a>
        <a onclick="switchModule('reportes',this)" class="nav-link" id="nav-reportes">
            <i class="fas fa-chart-line text-sm w-4 text-center"></i> Reportes
        </a>
        <a onclick="switchModule('perfil',this)" class="nav-link" id="nav-perfil">
            <i class="fas fa-circle-user text-sm w-4 text-center"></i> Mi Perfil
        </a>
    </nav>
    <div class="brand-box rounded-2xl p-4 flex items-center gap-3 mt-4">
        <?php 
        $fotoPathAdmin = $adminPerfil['foto_perfil'] ?? '';
        if ($fotoPathAdmin && strpos($fotoPathAdmin, 'storage/') === 0 && strpos($fotoPathAdmin, 'public/') !== 0) {
            $fotoPathAdmin = 'public/' . $fotoPathAdmin;
        }
        ?>
        <?php if (!empty($fotoPathAdmin)): ?>
        <img src="../../<?= htmlspecialchars($fotoPathAdmin) ?>" alt="Foto"
             class="w-10 h-10 rounded-full object-cover flex-shrink-0 border-2 border-white/30">
        <?php else: ?>
        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-bold text-sm flex-shrink-0"><?= $iniciales ?></div>
        <?php endif; ?>
        <div class="flex-1 overflow-hidden">
            <p class="font-semibold text-sm truncate"><?= $nombre_usuario ?></p>
            <span class="badge-admin">Administrador</span>
        </div>
        <a href="../../controllers/UsuarioController.php?accion=logout" class="text-white/60 hover:text-red-300 transition" title="Cerrar sesión">
            <i class="fas fa-right-from-bracket text-sm"></i>
        </a>
    </div>
</aside>
