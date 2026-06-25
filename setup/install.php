<?php
declare(strict_types=1);
/**
 * ITD Landmaschinen Manager – Einrichtungsassistent
 * Einmalig aufrufen: https://ihre-domain.de/setup/install.php
 * Danach: Datei löschen oder in .htaccess sperren!
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = [];

// ─── Schritt 2: Datenbank anlegen ────────────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminPass2 = $_POST['admin_pass2'] ?? '';

    if (strlen($adminUser) < 3) $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein.';
    if (strlen($adminPass) < 8) $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
    if ($adminPass !== $adminPass2) $errors[] = 'Passwörter stimmen nicht überein.';

    if (empty($errors)) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Datenbank erstellen
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            // Schema einlesen und ausführen
            $sql = file_get_contents(BASE_PATH . '/sql/install.sql');
            // Kommentare und CREATE DATABASE / USE entfernen
            $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
            $sql = preg_replace('/CREATE DATABASE.*?;/si', '', $sql);
            $sql = preg_replace('/USE.*?;/si', '', $sql);

            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt) $pdo->exec($stmt);
            }

            // Admin-User anlegen
            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')")
                ->execute([$adminUser, $hash]);

            $success[] = "Datenbank wurde erfolgreich erstellt.";
            $success[] = "Admin-Benutzer '$adminUser' wurde angelegt.";
            $success[] = "Sie können sich jetzt unter <a href='/login'>login</a> anmelden.";
            $success[] = "<strong>Bitte diese Datei (setup/install.php) jetzt löschen!</strong>";
            $step = 3;
        } catch (PDOException $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Einrichtung – ITD Landmaschinen Manager</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body { background: linear-gradient(135deg, #1e3a1e, #2d5a27); min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .setup-card { background:#fff; border-radius:14px; padding:36px 40px; max-width:520px; width:100%; box-shadow:0 8px 36px rgba(0,0,0,.3); }
  h1 { color:#1e3a1e; font-size:20px; font-weight:700; margin-bottom:4px; }
  .sub { color:#5a6b5a; font-size:13px; margin-bottom:24px; }
  .btn-setup { background:#2d5a27; color:#fff; border:none; width:100%; padding:10px; border-radius:7px; font-size:15px; font-weight:600; cursor:pointer; }
  .btn-setup:hover { background:#1e3a1e; }
  label { font-size:13px; font-weight:600; }
</style>
</head>
<body>
<div class="setup-card">
  <div class="text-center mb-3">
    <div style="font-size:40px;">🌱</div>
    <h1>ITD Landmaschinen Manager</h1>
    <div class="sub">Einrichtungsassistent</div>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e, ENT_QUOTES) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($step === 3): ?>
    <div class="alert alert-success">
      <?php foreach ($success as $s): ?><div>✅ <?= $s ?></div><?php endforeach; ?>
    </div>
    <a href="/login" class="btn-setup d-block text-center text-decoration-none mt-3">Zum Login →</a>

  <?php elseif ($step <= 2): ?>

    <!-- Schritt 1: Verbindungstest -->
    <?php if ($step === 1): ?>
    <p class="text-muted small">Stellen Sie sicher, dass <code>config/config.php</code> korrekt ausgefüllt ist.</p>
    <div class="mb-3 p-3 rounded" style="background:#f5f8f0; font-size:13px;">
      <strong>Datenbankverbindung:</strong><br>
      Host: <code><?= htmlspecialchars(DB_HOST) ?>:<?= DB_PORT ?></code><br>
      Datenbank: <code><?= htmlspecialchars(DB_NAME) ?></code><br>
      Benutzer: <code><?= htmlspecialchars(DB_USER) ?></code>
    </div>
    <?php
    try {
        $testDsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $testPdo = new PDO($testDsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<div class="alert alert-success">✅ Datenbankverbindung erfolgreich!</div>';
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">❌ Verbindungsfehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<p class="small text-muted">Bitte <code>config/config.php</code> prüfen.</p>';
    }
    ?>
    <a href="?step=2" class="btn-setup d-block text-center text-decoration-none">Weiter →</a>

    <?php else: ?>

    <!-- Schritt 2: Admin anlegen -->
    <form method="post" action="?step=2">
      <div class="mb-3">
        <label>Admin-Benutzername</label>
        <input type="text" name="admin_user" class="form-control" value="admin" required minlength="3">
      </div>
      <div class="mb-3">
        <label>Admin-Passwort (min. 8 Zeichen)</label>
        <input type="password" name="admin_pass" class="form-control" required minlength="8">
      </div>
      <div class="mb-4">
        <label>Passwort wiederholen</label>
        <input type="password" name="admin_pass2" class="form-control" required>
      </div>
      <button type="submit" class="btn-setup">Einrichtung abschließen</button>
    </form>

    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
