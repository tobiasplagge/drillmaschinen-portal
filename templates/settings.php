<?php
declare(strict_types=1);
$pageTitle = 'Einstellungen';
require BASE_PATH . '/templates/layout.php';
?>

<div class="container-fluid itd-container">
  <div class="itd-page-header">
    <div>
      <h1 class="itd-page-title">⚙️ Einstellungen</h1>
      <p class="itd-page-sub">Benutzerverwaltung, Maschinen und API-Zugangsdaten</p>
    </div>
  </div>

  <?php if (!empty($message)): ?>
  <div class="alert <?= str_starts_with($message, 'Fehler') ? 'alert-danger' : 'alert-success' ?> alert-dismissible fade show">
    <?= e($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <?php if (!empty($newToken)): ?>
  <div class="alert alert-warning">
    <strong>Neuer API-Token generiert – nur einmal sichtbar:</strong><br>
    <code class="user-select-all fs-6"><?= e($newToken) ?></code><br>
    <small>Diesen Token in der Drillmaschinen-Software eintragen. Er wird nur einmal angezeigt!</small>
  </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- ─── Maschinen ──────────────────────────────────────────────────────── -->
    <div class="col-lg-6">
      <div class="itd-card">
        <div class="itd-card-header"><span class="itd-card-title">🚜 Maschinen verwalten</span></div>
        <div class="p-3">
          <table class="itd-table mb-3">
            <thead>
              <tr><th>Kennung</th><th>Name</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($machines as $mc): ?>
              <tr>
                <td class="fw-semibold"><?= e($mc['machine_id']) ?></td>
                <td><?= e($mc['name']) ?></td>
                <td><?= $mc['active'] ? '<span class="itd-badge itd-badge-ok">Aktiv</span>' : '<span class="itd-badge">Inaktiv</span>' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <hr>
          <p class="fw-semibold mb-2">Maschine hinzufügen / aktualisieren</p>
          <form method="post" action="/settings">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="save_machine">
            <div class="row g-2">
              <div class="col-4">
                <input type="text" name="machine_id" class="form-control form-control-sm"
                       placeholder="Kennung z.B. DR-004" required>
              </div>
              <div class="col-5">
                <input type="text" name="machine_name" class="form-control form-control-sm"
                       placeholder="Name" required>
              </div>
              <div class="col-3">
                <button type="submit" class="btn btn-sm itd-btn-primary w-100">Speichern</button>
              </div>
              <div class="col-12">
                <input type="text" name="machine_desc" class="form-control form-control-sm"
                       placeholder="Beschreibung (optional)">
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ─── Benutzer & API-Token ────────────────────────────────────────────── -->
    <div class="col-lg-6">
      <div class="itd-card">
        <div class="itd-card-header"><span class="itd-card-title">👤 Benutzer &amp; API-Token</span></div>
        <div class="p-3">
          <table class="itd-table mb-3">
            <thead>
              <tr><th>Benutzer</th><th>Rolle</th><th>Letzter Login</th><th>API-Token</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td class="fw-semibold"><?= e($u['username']) ?></td>
                <td><?= e($u['role']) ?></td>
                <td class="text-muted small">
                  <?= $u['last_login'] ? (new DateTime($u['last_login']))->format('d.m.Y H:i') : '–' ?>
                </td>
                <td>
                  <form method="post" action="/settings" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="action" value="generate_token">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-xs itd-btn-amber"
                            onclick="return confirm('Neuen API-Token für <?= e($u['username']) ?> generieren? Der alte Token wird ungültig.')">
                      🔑 Neu generieren
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ─── Passwort ändern ─────────────────────────────────────────────────── -->
    <div class="col-lg-6">
      <div class="itd-card">
        <div class="itd-card-header"><span class="itd-card-title">🔐 Passwort ändern</span></div>
        <div class="p-3">
          <form method="post" action="/settings">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-2">
              <label class="form-label small fw-semibold">Aktuelles Passwort</label>
              <input type="password" name="old_password" class="form-control form-control-sm" required>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Neues Passwort (min. 8 Zeichen)</label>
              <input type="password" name="new_password" class="form-control form-control-sm" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-sm itd-btn-primary">Passwort ändern</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ─── API-Dokumentation ───────────────────────────────────────────────── -->
    <div class="col-12">
      <div class="itd-card">
        <div class="itd-card-header"><span class="itd-card-title">📡 API-Dokumentation</span></div>
        <div class="p-3">
          <p class="text-muted small mb-3">
            Basis-URL: <code><?= isset($_SERVER['HTTPS']) ? 'https' : 'http' ?>://<?= e($_SERVER['HTTP_HOST']) ?>/api/v1</code>
            · Alle Anfragen mit <code>Content-Type: application/json</code>
            · Authentifizierung via <code>Authorization: Bearer &lt;token&gt;</code>
          </p>

          <?php
          $endpoints = [
              ['POST', '/auth/token',           'Token ausstellen',              '{ "username": "...", "password": "..." }'],
              ['POST', '/trips',                 'Fahrt mit Batch-Daten anlegen', '{ "machine_id": "DR-001", "field_name": "Nordfeld", "started_at": "2026-06-25T06:12:00", "ended_at": "...", "area_ha": 18.4, "seed_type": "Winterweizen", "gps_points": [...], "events": [...] }'],
              ['GET',  '/trips',                 'Fahrten abrufen',               'Query-Parameter: page, limit, machine_id, status, date_from, date_to, search'],
              ['GET',  '/trips/{id}',            'Einzelne Fahrt abrufen',        '– (inkl. GPS-Punkte und Ereignisse)'],
              ['POST', '/trips/{id}/events',     'Einzelnes Ereignis hinzufügen', '{ "timestamp": "...", "type": "fault|blower|warning|info", "message": "...", "lat": 51.48, "lon": 9.21 }'],
              ['PATCH','/trips/{id}/finish',     'Fahrt abschließen',             '{ "ended_at": "...", "area_ha": 18.4 }'],
          ];
          ?>
          <div class="table-responsive">
            <table class="itd-table">
              <thead>
                <tr><th>Methode</th><th>Pfad</th><th>Beschreibung</th><th>Body / Parameter</th></tr>
              </thead>
              <tbody>
                <?php foreach ($endpoints as [$method, $path, $desc, $body]): ?>
                <tr>
                  <td><span class="itd-badge itd-badge-method-<?= strtolower($method) ?>"><?= $method ?></span></td>
                  <td><code>/api/v1<?= $path ?></code></td>
                  <td><?= $desc ?></td>
                  <td><small class="text-muted font-monospace"><?= e($body) ?></small></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            <p class="fw-semibold small mb-2">Beispiel: Fahrt mit GPS-Punkten übermitteln (cURL)</p>
            <pre class="itd-code-block"><code>curl -X POST https://<?= e($_SERVER['HTTP_HOST']) ?>/api/v1/trips \
  -H "Authorization: Bearer DEIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "machine_id": "DR-001",
    "field_name": "Schlag Nordfeld",
    "started_at": "2026-06-25T06:12:00",
    "ended_at":   "2026-06-25T09:57:00",
    "area_ha": 18.4,
    "seed_type": "Winterweizen",
    "seed_rate_kgha": 220,
    "working_width_m": 6.0,
    "blower_pressure_mbar": 35,
    "gps_points": [
      {
        "timestamp": "2026-06-25T06:12:00",
        "lat": 51.4823,
        "lon": 9.2145,
        "speed_kmh": 7.2,
        "blower_rpm": 2400,
        "blower_pressure_mbar": 35.2,
        "seed_rate_kgha": 221.5,
        "working": true
      }
    ],
    "events": [
      {
        "timestamp": "2026-06-25T07:34:00",
        "type": "fault",
        "message": "Verstopfung Reihe 4",
        "lat": 51.4823,
        "lon": 9.2145
      }
    ]
  }'</code></pre>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

</div><!-- .itd-page -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
