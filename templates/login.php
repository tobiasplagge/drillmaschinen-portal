<?php declare(strict_types=1); ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> – Anmelden</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="itd-login-body">

<div class="itd-login-wrap">
  <div class="itd-login-card">
    <div class="itd-login-logo">
      <svg viewBox="0 0 64 64" width="56" height="56" fill="none" aria-hidden="true">
        <circle cx="32" cy="32" r="30" fill="#2d5a27"/>
        <path d="M12 42 Q20 16 32 20 Q44 24 52 42" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round"/>
        <circle cx="14" cy="44" r="6" fill="#fff"/>
        <circle cx="50" cy="44" r="6" fill="#fff"/>
        <rect x="24" y="26" width="16" height="12" rx="3" fill="#d4870a"/>
      </svg>
      <h1><?= APP_NAME ?></h1>
      <p>Drillmaschinen-Auswertung &amp; Flottenübersicht</p>
    </div>

    <?php if (!empty($loginError)): ?>
    <div class="alert alert-danger" role="alert">
      <?= e($loginError) ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/login" novalidate>
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

      <div class="mb-3">
        <label for="username" class="form-label fw-semibold">Benutzername</label>
        <input type="text" id="username" name="username" class="form-control form-control-lg"
               autocomplete="username" required autofocus
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>

      <div class="mb-4">
        <label for="password" class="form-label fw-semibold">Passwort</label>
        <input type="password" id="password" name="password" class="form-control form-control-lg"
               autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn itd-btn-login w-100">Anmelden</button>
    </form>

    <p class="itd-login-footer">IT-Design Online &copy; <?= date('Y') ?></p>
  </div>
</div>

</body>
</html>
