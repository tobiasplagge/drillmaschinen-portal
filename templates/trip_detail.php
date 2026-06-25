<?php
declare(strict_types=1);
$pageTitle = 'Fahrt #' . $trip['id'] . ' – ' . $trip['field_name'];

// Berechne Dauer
$duration = '–';
if ($trip['started_at'] && $trip['ended_at']) {
    $diff = (new DateTime($trip['ended_at']))->getTimestamp()
          - (new DateTime($trip['started_at']))->getTimestamp();
    $h = (int)($diff / 3600);
    $m = (int)(($diff % 3600) / 60);
    $duration = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

$faultCount  = count(array_filter($events, fn($e) => $e['type'] === 'fault'));
$blowerCount = count(array_filter($events, fn($e) => $e['type'] === 'blower'));

// Durchschnittswerte aus GPS-Punkten
$avgSpeed    = 0.0;
$avgBlower   = 0.0;
$avgSeedRate = 0.0;
$gpsCount    = count($gpsPoints);
if ($gpsCount > 0) {
    $avgSpeed    = array_sum(array_column($gpsPoints, 'speed_kmh'))  / $gpsCount;
    $avgBlower   = array_sum(array_column($gpsPoints, 'blower_pressure_mbar')) / $gpsCount;
    $avgSeedRate = array_sum(array_column($gpsPoints, 'seed_rate_kgha')) / $gpsCount;
}

// GPS-Punkte für Leaflet (komprimiert – nur lat/lon + Arbeitsstatus)
$leafletPoints = array_map(fn($p) => [
    (float)$p['lat'],
    (float)$p['lon'],
    (bool)$p['working'],
], $gpsPoints);

// Chart-Daten (1 von n Punkten für Performance)
$step = max(1, (int)($gpsCount / 200));
$chartData = [];
for ($i = 0; $i < $gpsCount; $i += $step) {
    $p = $gpsPoints[$i];
    $chartData[] = [
        't' => substr($p['recorded_at'], 11, 5),
        's' => round((float)$p['speed_kmh'], 1),
        'b' => round((float)$p['blower_pressure_mbar'], 1),
        'r' => round((float)$p['seed_rate_kgha'], 1),
    ];
}

// Ereignis-Marker für Leaflet
$leafletEvents = array_map(fn($ev) => [
    'lat'     => $ev['lat'] ? (float)$ev['lat'] : null,
    'lon'     => $ev['lon'] ? (float)$ev['lon'] : null,
    'type'    => $ev['type'],
    'message' => $ev['message'],
    'time'    => $ev['recorded_at'] ? (new DateTime($ev['recorded_at']))->format('H:i') : '',
], $events);

require BASE_PATH . '/templates/layout.php';
?>

<div class="container-fluid itd-container">

  <!-- Kopfzeile -->
  <div class="itd-page-header">
    <div>
      <div class="itd-breadcrumb">
        <a href="/dashboard">← Alle Fahrten</a> / Fahrt #<?= $trip['id'] ?>
      </div>
      <h1 class="itd-page-title">
        <?= e($trip['machine_id']) ?> · <?= e($trip['field_name']) ?>
        <?php if ($faultCount > 0): ?>
          <span class="itd-badge itd-badge-error ms-2">● Störung</span>
        <?php else: ?>
          <span class="itd-badge itd-badge-ok ms-2">● OK</span>
        <?php endif; ?>
      </h1>
      <p class="itd-page-sub">
        <?= (new DateTime($trip['started_at']))->format('d.m.Y') ?>
        · <?= (new DateTime($trip['started_at']))->format('H:i') ?> – <?= $trip['ended_at'] ? (new DateTime($trip['ended_at']))->format('H:i') : 'laufend' ?> Uhr
        · <?= $duration ?>
        <?php if ($trip['area_ha']): ?>
          · <?= number_format((float)$trip['area_ha'], 1, ',', '.') ?> ha
        <?php endif; ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a href="/api/v1/trips/<?= $trip['id'] ?>" target="_blank" class="btn btn-sm itd-btn-outline">
        📥 JSON Export
      </a>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav itd-tabs" id="tripTabs" role="tablist">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabMap">🗺️ Karte &amp; Fahrspur</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabChart" id="chartTabBtn">📈 Verlauf</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEvents">⚠️ Ereignisliste</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabInfo">📋 Details</button>
    </li>
  </ul>

  <div class="tab-content" id="tripTabContent">

    <!-- ─── TAB: KARTE ──────────────────────────────────────────────────────── -->
    <div class="tab-pane fade show active" id="tabMap">
      <div class="itd-detail-grid">

        <!-- Karte -->
        <div class="itd-map-wrap">
          <div id="map" style="height: 520px; width: 100%; border-radius: 10px;"></div>
        </div>

        <!-- Seitenleiste -->
        <div class="itd-sidebar">
          <div class="itd-sidebar-header">
            <div class="itd-sidebar-machine">Maschine: <?= e($trip['machine_id']) ?></div>
            <div class="itd-sidebar-field"><?= e($trip['field_name']) ?></div>
          </div>

          <div class="itd-info-block">
            <div class="itd-info-title">📊 Fahrtdaten</div>
            <div class="itd-info-row"><span>Datum</span><strong><?= (new DateTime($trip['started_at']))->format('d.m.Y') ?></strong></div>
            <div class="itd-info-row"><span>Start</span><strong><?= (new DateTime($trip['started_at']))->format('H:i') ?> Uhr</strong></div>
            <div class="itd-info-row"><span>Ende</span><strong><?= $trip['ended_at'] ? (new DateTime($trip['ended_at']))->format('H:i') . ' Uhr' : '–' ?></strong></div>
            <div class="itd-info-row"><span>Dauer</span><strong><?= $duration ?></strong></div>
            <div class="itd-info-row"><span>Fläche</span><strong><?= $trip['area_ha'] ? number_format((float)$trip['area_ha'], 1, ',', '.') . ' ha' : '–' ?></strong></div>
            <div class="itd-info-row"><span>GPS-Punkte</span><strong><?= number_format($gpsCount) ?></strong></div>
            <div class="itd-info-row"><span>Ø Geschwindigkeit</span><strong><?= $avgSpeed > 0 ? number_format($avgSpeed, 1, ',', '.') . ' km/h' : '–' ?></strong></div>
          </div>

          <?php if ($trip['seed_type'] || $trip['working_width_m'] || $trip['blower_pressure_mbar']): ?>
          <div class="itd-info-block">
            <div class="itd-info-title">⚙️ Maschineneinstellungen</div>
            <?php if ($trip['working_width_m']): ?>
            <div class="itd-info-row"><span>Arbeitsbreite</span><strong><?= number_format((float)$trip['working_width_m'], 1, ',', '.') ?> m</strong></div>
            <?php endif; ?>
            <?php if ($trip['seed_type']): ?>
            <div class="itd-info-row"><span>Saatgut</span><strong><?= e($trip['seed_type']) ?></strong></div>
            <?php endif; ?>
            <?php if ($trip['seed_rate_kgha']): ?>
            <div class="itd-info-row"><span>Sollsaatmenge</span><strong><?= number_format((float)$trip['seed_rate_kgha'], 0) ?> kg/ha</strong></div>
            <?php endif; ?>
            <?php if ($avgSeedRate > 0): ?>
            <div class="itd-info-row"><span>Ø Ist-Saatmenge</span><strong><?= number_format($avgSeedRate, 0) ?> kg/ha</strong></div>
            <?php endif; ?>
            <?php if ($trip['blower_pressure_mbar']): ?>
            <div class="itd-info-row"><span>Gebl.-Solldruck</span><strong><?= number_format((float)$trip['blower_pressure_mbar'], 0) ?> mbar</strong></div>
            <?php endif; ?>
            <?php if ($avgBlower > 0): ?>
            <div class="itd-info-row"><span>Ø Ist-Druck</span><strong><?= number_format($avgBlower, 1, ',', '.') ?> mbar</strong></div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Ereignis-Zusammenfassung -->
          <div class="itd-info-block">
            <div class="itd-info-title">📋 Ereignisse (<?= count($events) ?>)</div>
            <div class="itd-info-row">
              <span>⚠️ Störungen</span>
              <strong class="<?= $faultCount > 0 ? 'itd-color-red' : '' ?>"><?= $faultCount ?></strong>
            </div>
            <div class="itd-info-row">
              <span>💨 Gebläse</span>
              <strong class="itd-color-amber"><?= $blowerCount ?></strong>
            </div>
          </div>

          <!-- Letzten Ereignisse -->
          <?php if (!empty($events)): ?>
          <div class="itd-events-list">
            <?php foreach (array_slice($events, 0, 8) as $ev): ?>
            <div class="itd-event-item itd-event-<?= e($ev['type']) ?>">
              <span class="itd-event-icon">
                <?= match($ev['type']) {
                    'fault'   => '🔴',
                    'blower'  => '🟠',
                    'warning' => '🟡',
                    default   => '🔵',
                } ?>
              </span>
              <div>
                <div class="itd-event-msg"><?= e($ev['message']) ?></div>
                <div class="itd-event-meta">
                  <?= $ev['recorded_at'] ? (new DateTime($ev['recorded_at']))->format('H:i') . ' Uhr' : '' ?>
                  <?php if ($ev['lat']): ?>
                    · <?= number_format((float)$ev['lat'], 4, '.', '') ?>°N,
                    <?= number_format((float)$ev['lon'], 4, '.', '') ?>°O
                  <?php endif; ?>
                </div>
                <?php if (!$ev['resolved'] && $ev['type'] === 'fault' && $currentUser['role'] === 'admin'): ?>
                <form method="post" action="/trips/<?= $trip['id'] ?>" class="mt-1">
                  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                  <input type="hidden" name="resolve_event" value="<?= $ev['id'] ?>">
                  <button type="submit" class="btn btn-xs itd-btn-resolve">✓ Behoben</button>
                </form>
                <?php elseif ($ev['resolved']): ?>
                <span class="itd-event-resolved">✓ behoben</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (count($events) > 8): ?>
            <div class="text-center mt-2">
              <button class="btn btn-sm itd-btn-outline" data-bs-toggle="tab" data-bs-target="#tabEvents">
                Alle <?= count($events) ?> Ereignisse anzeigen →
              </button>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ─── TAB: VERLAUFSDIAGRAMM ───────────────────────────────────────────── -->
    <div class="tab-pane fade" id="tabChart">
      <?php if (empty($chartData)): ?>
        <div class="text-center py-5 text-muted">Keine GPS-Daten vorhanden.</div>
      <?php else: ?>
      <div class="itd-card p-3 mt-0">
        <div class="row g-3">
          <div class="col-12">
            <h6 class="itd-chart-title">Geschwindigkeit (km/h)</h6>
            <canvas id="chartSpeed" height="80"></canvas>
          </div>
          <div class="col-12 col-md-6">
            <h6 class="itd-chart-title">Gebläsedruck (mbar)</h6>
            <canvas id="chartBlower" height="100"></canvas>
          </div>
          <div class="col-12 col-md-6">
            <h6 class="itd-chart-title">Saatmenge (kg/ha)</h6>
            <canvas id="chartSeedRate" height="100"></canvas>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ─── TAB: EREIGNISLISTE ──────────────────────────────────────────────── -->
    <div class="tab-pane fade" id="tabEvents">
      <div class="itd-card mt-0">
        <div class="itd-card-header">
          <span class="itd-card-title">Alle Ereignisse (<?= count($events) ?>)</span>
        </div>
        <?php if (empty($events)): ?>
        <div class="text-center py-5 text-muted">Keine Ereignisse aufgezeichnet.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="itd-table">
            <thead>
              <tr>
                <th>Typ</th>
                <th>Zeit</th>
                <th>Meldung</th>
                <th>Position</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
              <tr>
                <td>
                  <?= match($ev['type']) {
                      'fault'   => '<span class="itd-badge itd-badge-error">⚠️ Störung</span>',
                      'blower'  => '<span class="itd-badge itd-badge-warn">💨 Gebläse</span>',
                      'warning' => '<span class="itd-badge itd-badge-warn">⚡ Warnung</span>',
                      default   => '<span class="itd-badge itd-badge-info">ℹ️ Info</span>',
                  } ?>
                </td>
                <td class="text-muted small">
                  <?= $ev['recorded_at'] ? (new DateTime($ev['recorded_at']))->format('d.m.Y H:i:s') : '–' ?>
                </td>
                <td><?= e($ev['message']) ?></td>
                <td class="text-muted small font-monospace">
                  <?php if ($ev['lat']): ?>
                    <?= number_format((float)$ev['lat'], 6, '.', '') ?>,
                    <?= number_format((float)$ev['lon'], 6, '.', '') ?>
                  <?php else: ?>–<?php endif; ?>
                </td>
                <td>
                  <?php if ($ev['type'] === 'fault'): ?>
                    <?php if ($ev['resolved']): ?>
                      <span class="itd-badge itd-badge-ok">✓ Behoben</span>
                    <?php else: ?>
                      <span class="itd-badge itd-badge-error">Offen</span>
                      <?php if ($currentUser['role'] === 'admin'): ?>
                      <form method="post" action="/trips/<?= $trip['id'] ?>" class="d-inline ms-1">
                        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="resolve_event" value="<?= $ev['id'] ?>">
                        <button class="btn btn-xs itd-btn-resolve">✓</button>
                      </form>
                      <?php endif; ?>
                    <?php endif; ?>
                  <?php else: ?>–<?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ─── TAB: DETAILS ───────────────────────────────────────────────────── -->
    <div class="tab-pane fade" id="tabInfo">
      <div class="row g-3 mt-0">
        <div class="col-md-6">
          <div class="itd-card p-3">
            <div class="itd-info-title mb-2">📋 Fahrtdaten</div>
            <div class="itd-info-row"><span>Fahrt-ID</span><strong>#<?= $trip['id'] ?></strong></div>
            <div class="itd-info-row"><span>Maschine</span><strong><?= e($trip['machine_id']) ?> – <?= e($trip['machine_name'] ?? '') ?></strong></div>
            <div class="itd-info-row"><span>Schlag</span><strong><?= e($trip['field_name']) ?></strong></div>
            <div class="itd-info-row"><span>Start</span><strong><?= (new DateTime($trip['started_at']))->format('d.m.Y H:i') ?></strong></div>
            <div class="itd-info-row"><span>Ende</span><strong><?= $trip['ended_at'] ? (new DateTime($trip['ended_at']))->format('d.m.Y H:i') : '–' ?></strong></div>
            <div class="itd-info-row"><span>Dauer</span><strong><?= $duration ?></strong></div>
            <div class="itd-info-row"><span>Fläche</span><strong><?= $trip['area_ha'] ? number_format((float)$trip['area_ha'], 1, ',', '.') . ' ha' : '–' ?></strong></div>
            <div class="itd-info-row"><span>GPS-Punkte</span><strong><?= number_format($gpsCount) ?></strong></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="itd-card p-3">
            <div class="itd-info-title mb-2">⚙️ Maschineneinstellungen</div>
            <div class="itd-info-row"><span>Saatgut</span><strong><?= $trip['seed_type'] ? e($trip['seed_type']) : '–' ?></strong></div>
            <div class="itd-info-row"><span>Soll-Saatmenge</span><strong><?= $trip['seed_rate_kgha'] ? number_format((float)$trip['seed_rate_kgha'], 0) . ' kg/ha' : '–' ?></strong></div>
            <div class="itd-info-row"><span>Ø Ist-Saatmenge</span><strong><?= $avgSeedRate > 0 ? number_format($avgSeedRate, 1, ',', '.') . ' kg/ha' : '–' ?></strong></div>
            <div class="itd-info-row"><span>Arbeitsbreite</span><strong><?= $trip['working_width_m'] ? number_format((float)$trip['working_width_m'], 1, ',', '.') . ' m' : '–' ?></strong></div>
            <div class="itd-info-row"><span>Gebl.-Solldruck</span><strong><?= $trip['blower_pressure_mbar'] ? number_format((float)$trip['blower_pressure_mbar'], 0) . ' mbar' : '–' ?></strong></div>
            <div class="itd-info-row"><span>Ø Gebl.-Istdruck</span><strong><?= $avgBlower > 0 ? number_format($avgBlower, 1, ',', '.') . ' mbar' : '–' ?></strong></div>
            <div class="itd-info-row"><span>Ø Geschwindigkeit</span><strong><?= $avgSpeed > 0 ? number_format($avgSpeed, 1, ',', '.') . ' km/h' : '–' ?></strong></div>
          </div>
        </div>
        <?php if ($trip['notes']): ?>
        <div class="col-12">
          <div class="itd-card p-3">
            <div class="itd-info-title mb-2">📝 Notizen</div>
            <p class="mb-0"><?= nl2br(e($trip['notes'])) ?></p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- tab-content -->
</div><!-- container -->

</div><!-- .itd-page -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const GPS_POINTS  = <?= json_encode($leafletPoints, JSON_UNESCAPED_UNICODE) ?>;
const EVENTS      = <?= json_encode($leafletEvents,  JSON_UNESCAPED_UNICODE) ?>;
const CHART_DATA  = <?= json_encode($chartData,      JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/trip_detail.js"></script>
</body>
</html>
