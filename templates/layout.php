<?php
declare(strict_types=1);
$pageTitle = ($pageTitle ?? '') ? e($pageTitle) . ' – ' . APP_NAME : APP_NAME;
$user      = $currentUser ?? ['username' => '', 'role' => ''];
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<nav class="itd-navbar">
  <a class="itd-brand" href="/dashboard">
    <svg viewBox="0 0 32 32" fill="none" width="30" height="30" aria-hidden="true">
      <circle cx="16" cy="16" r="14" fill="#4a8c3f"/>
      <path d="M6 20 Q10 8 16 10 Q22 12 26 20" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round"/>
      <circle cx="8" cy="21" r="3.5" fill="#fff"/>
      <circle cx="24" cy="21" r="3.5" fill="#fff"/>
      <rect x="12" y="13" width="8" height="6" rx="1.5" fill="#d4870a"/>
    </svg>
    <span>ITD <strong>Landmaschinen</strong></span>
  </a>

  <button class="itd-nav-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>

  <div class="collapse itd-nav-menu" id="navMenu">
    <a href="/dashboard" class="itd-nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/dashboard') ? 'active' : '' ?>">
      📊 Übersicht
    </a>
    <a href="/dashboard?status=error" class="itd-nav-link <?= (($_GET['status'] ?? '') === 'error') ? 'active' : '' ?>">
      ⚠️ Störungen
    </a>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="/settings" class="itd-nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/settings') ? 'active' : '' ?>">
      ⚙️ Einstellungen
    </a>
    <?php endif; ?>
  </div>

  <div class="itd-nav-right">
    <span class="itd-user-badge">👤 <?= e($user['username']) ?></span>
    <a href="/logout" class="itd-btn-logout">Abmelden</a>
  </div>
</nav>

<div class="itd-page">
