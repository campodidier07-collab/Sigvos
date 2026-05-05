    <header class="bg-white/80 backdrop-blur-md border-b border-[#d8eee4] sticky top-0 z-40 flex items-center justify-between px-6 h-16" style="box-shadow:0 1px 12px rgba(11,93,70,.05);">
        <div class="flex items-center gap-3">
            <div id="header-icon" class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:linear-gradient(135deg,#065f46,#10b981);">
                <i class="fas fa-house"></i>
            </div>
            <div>
                <h1 id="header-title" class="text-base font-bold text-[#16332b]">Mi Panel</h1>
                <p class="text-xs text-slate-400"><?= $fecha_es ?></p>
            </div>
        </div>
        <!-- Indicador offline -->
        <div id="offline-indicator" class="hidden text-xs bg-amber-100 text-amber-700 px-3 py-1.5 rounded-full font-semibold flex items-center gap-1.5">
            <i class="fas fa-wifi-slash text-xs"></i> Sin conexión — modo offline
        </div>
        <!-- Campana notificaciones + sincronización -->
        <div class="flex items-center gap-2">
            <div class="relative">
                <button onclick="document.getElementById('panel-notif-t').classList.toggle('hidden')"
                    class="relative w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <i class="fas fa-bell text-slate-500 text-sm"></i>
                    <?php if ($notifs_no_leidas_t > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center"><?= $notifs_no_leidas_t ?></span>
                    <?php endif; ?>
                </button>
                <div id="panel-notif-t" class="hidden absolute right-0 top-11 w-[480px] bg-white rounded-2xl shadow-2xl border border-[#d8eee4] z-50 overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-[#d8eee4] bg-[#f0fdf4]">
                        <p class="font-bold text-base text-[#065f46]">Notificaciones</p>
                        <?php if ($notifs_no_leidas_t > 0): ?>
                        <a href="../../controllers/NotificacionController.php?accion=marcar_todas"
                            class="text-xs text-[#0f8f67] hover:underline font-semibold">Marcar todas</a>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-96 overflow-y-auto divide-y divide-[#f0fdf4]">
                        <?php if (empty($notificaciones_t)): ?>
                        <div class="p-10 text-center text-slate-400 text-base">Sin notificaciones</div>
                        <?php else: foreach ($notificaciones_t as $n):
                            $prioColor = ['alta'=>'bg-red-100 text-red-600','media'=>'bg-amber-100 text-amber-600','baja'=>'bg-slate-100 text-slate-500'];
                            $cls = $prioColor[$n['prioridad']] ?? 'bg-slate-100 text-slate-500';
                        ?>
                        <div class="flex items-start gap-4 px-6 py-4 <?= $n['leida'] ? 'opacity-50' : 'bg-white' ?> hover:bg-slate-50 transition">
                            <span class="mt-1 w-3 h-3 rounded-full flex-shrink-0 <?= $n['leida'] ? 'bg-slate-300' : 'bg-[#0f8f67]' ?>"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#16332b] truncate"><?= htmlspecialchars($n['titulo']) ?></p>
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($n['mensaje']) ?></p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $cls ?>"><?= ucfirst($n['prioridad']) ?></span>
                                    <span class="text-xs text-slate-300"><?= date('d/m H:i', strtotime($n['creada_en'])) ?></span>
                                </div>
                            </div>
                            <?php if (!$n['leida']): ?>
                            <a href="../../controllers/NotificacionController.php?accion=marcar_leida&id=<?= $n['id_notificacion'] ?>"
                                class="text-slate-300 hover:text-[#0f8f67] transition flex-shrink-0" title="Marcar leída">
                                <i class="fas fa-check text-sm"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <!-- Sincronización -->
                    <div class="px-6 py-4 border-t border-[#d8eee4] bg-[#f0fdf4]">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-600">Sincronización</p>
                                <p class="text-xs text-slate-400 mt-0.5" id="sync-status-t">
                                    <?= $ultimaSyncT ? 'Última: ' . date('d/m/Y H:i', strtotime($ultimaSyncT)) : 'Sin sincronizar' ?>
                                </p>
                            </div>
                            <a href="../../controllers/NotificacionController.php?accion=sincronizar"
                                id="btn-sync-t"
                                class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2">
                                <i class="fas fa-rotate"></i> Sincronizar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
