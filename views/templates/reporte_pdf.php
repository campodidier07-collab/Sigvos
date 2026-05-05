<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1d4533; background: #fff; }
  .hdr { background-color: #1d4533; width: 100%; }
  .hdr td { padding: 14px 20px; vertical-align: middle; }
  .brand { font-size: 18px; font-weight: 800; color: #fff; letter-spacing: 1px; }
  .brand-sub { font-size: 8px; color: rgba(255,255,255,.75); margin-top: 2px; }
  .rpt-title { font-size: 12px; font-weight: 700; color: #fff; text-align: right; }
  .rpt-sub { font-size: 8px; color: rgba(255,255,255,.75); text-align: right; margin-top: 3px; }
  .pills-row { background-color: #25694a; width: 100%; }
  .pills-row td { padding: 5px 20px; }
  .pill { display: inline-block; background-color: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; font-size: 8px; padding: 2px 9px; border-radius: 999px; margin-right: 5px; }
  .metrics-wrap { width: 100%; border-collapse: collapse; border-bottom: 1px solid #c4ebd4; }
  .metric-cell { width: 25%; text-align: center; padding: 10px 8px; border: 1px solid #96d9b4; background-color: #fff; }
  .metric-val { font-size: 16px; font-weight: 800; color: #25694a; }
  .metric-lbl { font-size: 7.5px; color: #5fc18f; margin-top: 2px; text-transform: uppercase; letter-spacing: .4px; }
  .section-wrap { padding: 14px 20px 0; }
  .section-title { font-size: 10px; font-weight: 700; color: #1d4533; border-bottom: 2px solid #3aa574; padding-bottom: 4px; margin-bottom: 10px; }
  .data-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
  .data-table thead tr { background-color: #25694a; }
  .data-table thead th { color: #fff; padding: 6px 7px; text-align: left; font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }
  .data-table tbody tr:nth-child(even) { background-color: #f2fbf5; }
  .data-table tbody tr:nth-child(odd) { background-color: #fff; }
  .data-table tbody td { padding: 5px 7px; border-bottom: 1px solid #e1f6e8; vertical-align: middle; }
  .td-right { text-align: right; font-weight: 600; color: #25694a; }
  .td-muted { color: #94a3b8; font-size: 8px; }
  .td-bold { font-weight: 700; color: #1d4533; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 7.5px; font-weight: 700; }
  .b-green { background-color: #c4ebd4; color: #25694a; }
  .b-yellow { background-color: #fef3c7; color: #92400e; }
  .b-blue { background-color: #dbeafe; color: #1e40af; }
  .b-red { background-color: #fee2e2; color: #991b1b; }
  .b-gray { background-color: #f1f5f9; color: #475569; }
  .bar-outer { background-color: #e1f6e8; border-radius: 3px; height: 7px; width: 100%; }
  .bar-inner { background-color: #3aa574; border-radius: 3px; height: 7px; }
  .bar-pct { font-size: 7.5px; color: #25694a; text-align: right; margin-top: 1px; }
  .footer-wrap { width: 100%; border-top: 1px solid #e1f6e8; margin-top: 16px; }
  .footer-wrap td { padding: 7px 20px; font-size: 7.5px; color: #94a3b8; }
  .empty { text-align: center; padding: 30px; color: #94a3b8; font-size: 10px; }
</style>
</head>
<body>

<table class="hdr" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding:14px 20px;vertical-align:middle;width:60%;">
      <table cellpadding="0" cellspacing="0" style="width:auto;">
        <tr>
          <?php if ($logoB64): ?>
          <td style="vertical-align:middle;padding-right:8px;width:1%;">
            <img src="<?= $logoB64 ?>" alt="SIGVOS" style="width:42px;height:42px;display:block;">
          </td>
          <?php endif; ?>
          <td style="vertical-align:middle;white-space:nowrap;">
            <div class="brand">SIGVOS</div>
            <div class="brand-sub">Sistema de Gestión de Cultivos</div>
          </td>
        </tr>
      </table>
    </td>
    <td style="padding:14px 20px 14px 0;vertical-align:middle;text-align:right;">
      <div class="rpt-title"><?= htmlspecialchars($titulo) ?></div>
      <div class="rpt-sub"><?= htmlspecialchars($subtitulo) ?></div>
    </td>
  </tr>
</table>

<table class="pills-row" cellpadding="0" cellspacing="0">
  <tr>
    <td>
      <span class="pill">Periodo: <?= $periodo ?></span>
      <span class="pill">Tipo: <?= htmlspecialchars($tipo_label) ?></span>
      <span class="pill">Generado: <?= $generado ?></span>
      <span class="pill"><?= count($datos) ?> registros</span>
    </td>
  </tr>
</table>

<table class="metrics-wrap" cellpadding="0" cellspacing="0">
  <tr>
    <td class="metric-cell">
      <div class="metric-val"><?= $m['cultivos_activos'] ?></div>
      <div class="metric-lbl">Cultivos activos</div>
    </td>
    <td class="metric-cell">
      <div class="metric-val"><?= number_format((float)$m['kg_total'], 0, ',', '.') ?> kg</div>
      <div class="metric-lbl">Producción en periodo</div>
    </td>
    <td class="metric-cell">
      <div class="metric-val"><?= $m['actividades_completadas'] ?></div>
      <div class="metric-lbl">Actividades completadas</div>
    </td>
    <td class="metric-cell">
      <div class="metric-val"><?= $m['total_lotes'] ?></div>
      <div class="metric-lbl">Lotes registrados</div>
    </td>
  </tr>
</table>

<div class="section-wrap">
  <div class="section-title"><?= htmlspecialchars($titulo) ?></div>

  <?php if (empty($datos)): ?>
  <div class="empty">No hay datos para los filtros seleccionados.</div>

  <?php elseif ($tipo === 'produccion'):
    $max_kg = max(array_column($datos, 'total_kg')) ?: 1;
  ?>
  <table class="data-table" cellpadding="0" cellspacing="0">
    <thead><tr>
      <th style="width:12%">Lote</th>
      <th style="width:14%">Tipo cultivo</th>
      <th style="width:9%;text-align:right">Área (ha)</th>
      <th style="width:9%;text-align:right">Cosechas</th>
      <th style="width:12%;text-align:right">Total kg</th>
      <th style="width:12%;text-align:right">Promedio kg</th>
      <th style="width:10%;text-align:right">kg/ha</th>
      <th style="width:22%">Rendimiento</th>
    </tr></thead>
    <tbody>
    <?php foreach ($datos as $r):
      $pct = $max_kg > 0 ? round(($r['total_kg'] / $max_kg) * 100) : 0;
    ?>
      <tr>
        <td><span class="td-bold"><?= htmlspecialchars($r['lote_id']) ?></span><br><span class="td-muted"><?= htmlspecialchars($r['lote_nombre']) ?></span></td>
        <td><?= htmlspecialchars($r['tipo_cultivo']) ?></td>
        <td class="td-right"><?= number_format((float)$r['area_ha'], 2) ?></td>
        <td class="td-right"><?= (int)$r['total_cultivos'] ?></td>
        <td class="td-right"><?= number_format((float)$r['total_kg'], 1, ',', '.') ?></td>
        <td class="td-right"><?= number_format((float)$r['promedio_kg'], 1, ',', '.') ?></td>
        <td class="td-right"><?= number_format((float)$r['kg_por_ha'], 1, ',', '.') ?></td>
        <td>
          <div class="bar-outer"><div class="bar-inner" style="width:<?= $pct ?>%"></div></div>
          <div class="bar-pct"><?= $pct ?>%</div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif ($tipo === 'actividades'):
    $badges = ['completada'=>'b-green','pendiente'=>'b-yellow','en_proceso'=>'b-blue','cancelada'=>'b-red'];
  ?>
  <table class="data-table" cellpadding="0" cellspacing="0">
    <thead><tr>
      <th style="width:9%">Fecha</th>
      <th style="width:13%">Tipo actividad</th>
      <th style="width:10%">Cultivo</th>
      <th style="width:14%">Lote</th>
      <th style="width:11%">Tipo cultivo</th>
      <th style="width:13%">Trabajador</th>
      <th style="width:10%">Estado</th>
      <th style="width:20%">Descripción</th>
    </tr></thead>
    <tbody>
    <?php foreach ($datos as $r):
      $bc = $badges[$r['estado']] ?? 'b-gray';
    ?>
      <tr>
        <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($r['fecha_programada'])) ?></td>
        <td><?= htmlspecialchars($r['tipo_actividad']) ?></td>
        <td class="td-bold"><?= htmlspecialchars($r['cultivo_codigo']) ?></td>
        <td><?= htmlspecialchars($r['lote_id']) ?> - <?= htmlspecialchars($r['lote_nombre']) ?></td>
        <td><?= htmlspecialchars($r['tipo_cultivo']) ?></td>
        <td><?= htmlspecialchars($r['trabajador']) ?></td>
        <td><span class="badge <?= $bc ?>"><?= ucfirst(str_replace('_',' ',$r['estado'])) ?></span></td>
        <td class="td-muted"><?= htmlspecialchars(mb_substr($r['descripcion'],0,55)) ?><?= mb_strlen($r['descripcion'])>55?'...':'' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif ($tipo === 'cosechas'):
    $est_badges = ['sembrado'=>'b-blue','desarrollo'=>'b-yellow','maduro'=>'b-green'];
  ?>
  <table class="data-table" cellpadding="0" cellspacing="0">
    <thead><tr>
      <th style="width:10%">Código</th>
      <th style="width:12%">Variedad</th>
      <th style="width:10%">Tipo</th>
      <th style="width:14%">Lote</th>
      <th style="width:7%;text-align:right">Área (ha)</th>
      <th style="width:9%">Siembra</th>
      <th style="width:10%">Cosecha est.</th>
      <th style="width:9%">Días rest.</th>
      <th style="width:12%">Trabajador</th>
      <th style="width:7%">Estado</th>
    </tr></thead>
    <tbody>
    <?php foreach ($datos as $r):
      $dias = (int)$r['dias_restantes'];
      $ub   = $dias <= 7 ? 'b-red' : ($dias <= 30 ? 'b-yellow' : 'b-green');
      $eb   = $est_badges[$r['estado']] ?? 'b-gray';
    ?>
      <tr>
        <td class="td-bold"><?= htmlspecialchars($r['codigo']) ?></td>
        <td><?= htmlspecialchars($r['variedad_nombre']) ?></td>
        <td><?= htmlspecialchars($r['tipo_cultivo']) ?></td>
        <td><?= htmlspecialchars($r['lote_id']) ?> - <?= htmlspecialchars($r['lote_nombre']) ?></td>
        <td class="td-right"><?= number_format((float)$r['area_ha'],2) ?></td>
        <td><?= date('d/m/Y', strtotime($r['fecha_siembra'])) ?></td>
        <td><strong><?= date('d/m/Y', strtotime($r['fecha_cosecha_estimada'])) ?></strong></td>
        <td><span class="badge <?= $ub ?>"><?= $dias ?> días</span></td>
        <td><?= htmlspecialchars($r['trabajador']) ?></td>
        <td><span class="badge <?= $eb ?>"><?= ucfirst($r['estado']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<table class="footer-wrap" cellpadding="0" cellspacing="0">
  <tr>
    <td style="text-align:right">Generado el <?= $generado ?> por <?= $generado_por ?></td>
  </tr>
</table>

</body>
</html>
