<?php
declare(strict_types=1);

// ─── Datenbank ────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'itd_landmaschinen');
define('DB_USER', 'itd_user');
define('DB_PASS', 'BITTE_AENDERN');
define('DB_CHARSET', 'utf8mb4');

// ─── Anwendung ─────────────────────────────────────────────────────────────────
define('APP_NAME', 'ITD Landmaschinen Manager');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', false);      // auf true setzen für Entwicklung
define('APP_TIMEZONE', 'Europe/Berlin');

// ─── Session ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200);   // 2 Stunden in Sekunden
define('SESSION_NAME', 'itd_lm_session');

// ─── API ───────────────────────────────────────────────────────────────────────
define('API_TOKEN_LIFETIME', 86400 * 30);   // 30 Tage

// ─── Pfade (werden in index.php überschrieben) ─────────────────────────────────
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

date_default_timezone_set(APP_TIMEZONE);
