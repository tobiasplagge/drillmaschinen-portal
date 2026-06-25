<?php
declare(strict_types=1);
$pageTitle = 'Fahrtenübersicht';
require BASE_PATH . '/templates/layout.php';

function statusBadge(string $status, int $faults): string {
    if ($faults > 0 || $status === 'error') {
        return '<span class="itd-badge itd-badge-error">● Fehler</span>';
    }
    return match($status) {
        'active'    => '<span class="itd-badge itd-badge-active">● Aktiv</span>',
        'completed' => '<span class="itd-badge itd-badge-ok">● OK</span>',
        default     => '<span class="itd-badge itd-badge-warn">● Warnung</span>',
    };
}

function durationStr(?string $start, ?string $end): string {
    if (!$start || !$end) return '–';
    $diff = (new DateTime($end))->getTimestamp() - (new DateTime($start))->getTimestamp();
    $h = (int)($diff / 3600);
    $m = (int)(($diff % 3600) / 60);
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}
?>

<div class="container-fluid itd-container">

  <!-- Breadcrumb + Seitenkopf -->
  <div class="itd-page-header">
    <div>
      <h1 class="itd-page-title">Fahrtenübersicht</h1>
      <p class="itd-page-sub">Alle übermittelten Fahrten · Stand: <?= date('d.m.Y, H:i') ?> Uhr</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/api/v1/trips?<?= http_build_query(array_merge($_GET, ['limit' => 1000])) ?>"
         target="_blank" class="btn btn-sm itd-btn-outline">📥 JSON Export</a>
    </div>
  </div>

  <!-- KPI-Karten -->
  <div class="itd-kpi-grid">
    <div class="itd-kpi-card">
      <div class="itd-kpi-icon itd-kpi-green">🌾</div>
      <div>
        <div class="itd-kpi-val"><?= number_format($kpis['total_trips']) ?></div>
        <div class="itd-kpi-label">Fahrten gesamt</div>
      </div>
    </div>
    <div class="itd-kpi-card">
      <div class="itd-kpi-icon itd-kpi-amber">📐</div>
      <div>
        <div class="itd-kpi-val"><?= number_format($kpis['total_area'], 1, ',', '.') ?> ha</div>
        <div class="itd-kpi-label">Bearbeitete Fläche</div>
      </div>
    </div>
    <div class="itd-kpi-card <?= $kpis['open_faults'] > 0 ? 'itd-kpi-card--alert' : '' ?>">
      <div class="itd-kpi-icon itd-kpi-red">⚠️</div>
      <div>
        <div class="itd-kpi-val"><?= $kpis['open_faults'] ?></div>
        <div class="itd-kpi-label">Offene Störungen</div>
      </div>
    </div>
    <div class="itd-kpi-card">
      <div class="itd-kpi-icon itd-kpi-blue">🚜</div>
      <div>
        <div class="itd-kpi-val"><?= $kpis['active_machines'] ?></div>
        <div class="itd-kpi-label">Aktive Maschinen</div>
      </div>
    </div>
  </div>

  <!-- Tabelle -->
  <div class="itd-card">
    <div class="itd-card-header">
      <span class="itd-card-title">🚜 Letzte Fahrten</span>
      <span class="text-muted small">Klick auf eine Zeile für Detailansicht</span>
    </div>

    <!-- Filter -->
    <form method="get" action="/dashboard" class="itd-filter-bar">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="🔍 Maschine, Feld …" value="<?= e($_GET['search'] ?? '') ?>">
      <select name="machine_id" class="form-select form-select-sm">
        <option value="">Alle Maschinen</option>
        <?php foreach ($machines as $mc): ?>
          <option value="<?= e($mc['machine_id']) ?>"
            <?= (($_GET['machine_id'] ?? '') === $mc['machine_id']) ? 'selected' : '' ?>>
            <?= e($mc['machine_id']) ?> – <?= e($mc['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-select form-select-sm">
        <option value="">Alle Status</option>
        <option value="completed" <?= (($_GET['status'] ?? '') === 'completed') ? 'selected' : '' ?>>OK / Abgeschlossen</option>
        <option value="error"     <?= (($_GET['status'] ?? '') === 'error')     ? 'selected' : '' ?>>Fehler</option>
        <option value="active"    <?= (($_GET['status'] ?? '') === 'active')    ? 'selected' : '' ?>>Aktiv</option>
      </select>
      <input type="date" name="date_from" class="form-control form-control-sm"
             value="<?= e($_GET['date_from'] ?? '') ?>">
      <input type="date" name="date_to" class="form-control form-control-sm"
             value="<?= e($_GET['date_to'] ?? '') ?>">
      <button type="submit" class="btn btn-sm itd-btn-primary">Filtern</button>
      <a href="/dashboard" class="btn btn-sm itd-btn-outline">✕ Reset</a>
    </form>

    <!-- Tabelle -->
    <div class="table-responsive">
      <table class="itd-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Maschine</th>
            <th>Schlag / Feld</th>
            <th>Datum</th>
            <th>Dauer</th>
            <th>Fläche</th>
            <th>Störungen</th>
            <th>Gebläse</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($trips)): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-4">Keine Fahrten gefunden.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($trips as $t): ?>
          <tr class="itd-table-row" onclick="location.href='/trips/<?= $t['id'] ?>'">
            <td class="text-muted small">#<?= $t['id'] ?></td>
            <td class="fw-semibold itd-color-green"><?= e($t['machine_id']) ?></td>
            <td><?= e($t['field_name']) ?></td>
            <td class="text-muted small">
              <?= (new DateTime($t['started_at']))->format('d.m.Y · H:i') ?>
            </td>
            <td><?= durationStr($t['started_at'], $t['ended_at']) ?></td>
            <td class="fw-semibold">
              <?= $t['area_ha'] !== null ? number_format((float)$t['area_ha'], 1, ',', '.') . ' ha' : '–' ?>
            </td>
            <td>
              <?php if ((int)$t['fault_count'] > 0): ?>
                <span class="itd-color-red">⚠️ <?= $t['fault_count'] ?></span>
              <?php else: ?>
                <span class="itd-color-green">✅ 0</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)$t['blower_count'] > 0): ?>
                <span class="itd-color-amber">💨 <?= $t['blower_count'] ?></span>
              <?php else: ?>
                <span class="text-muted">0</span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($t['status'], (int)$t['fault_count']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="itd-pagination">
      <span class="text-muted small">
        Zeige <?= count($trips) ?> von <?= $total ?> Fahrten
        (Seite <?= $page ?> / <?= $pages ?>)
      </span>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php
          $qs = http_build_query(array_merge($_GET, ['page' => $page - 1]));
          ?>
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="/dashboard?<?= $qs ?>">‹</a>
          </li>
          <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
            <?php $qs = http_build_query(array_merge($_GET, ['page' => $p])); ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="/dashboard?<?= $qs ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <?php $qs = http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>
          <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
            <a class="page-link" href="/dashboard?<?= $qs ?>">›</a>
          </li>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
  </div>

</div>

</div><!-- .itd-page -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
