<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/config/config.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/Auth.php';
require BASE_PATH . '/src/TripRepository.php';
require BASE_PATH . '/src/ApiController.php';

// Helper-Funktion für sichere HTML-Ausgabe
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Session starten (außer bei API-Aufrufen) ──────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isApi  = str_starts_with($uri, '/api/');

if (!$isApi) {
    Auth::startSession();
}

// ─── Routing ──────────────────────────────────────────────────────────────────
$path = rtrim($uri, '/') ?: '/';

// API
if ($isApi) {
    ApiController::handle();
    exit;
}

// Login
if ($path === '/login' || $path === '/') {
    if (Auth::isLoggedIn()) {
        header('Location: /dashboard');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::verifyCsrf();
        if (Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
            header('Location: /dashboard');
            exit;
        }
        $loginError = 'Ungültiger Benutzername oder Passwort.';
    }
    require BASE_PATH . '/templates/login.php';
    exit;
}

// Abmelden
if ($path === '/logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

// Ab hier: Anmeldung erforderlich
Auth::requireLogin();
$currentUser = Auth::currentUser();

// Dashboard
if ($path === '/dashboard') {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $filters = array_filter([
        'machine_id' => $_GET['machine_id'] ?? '',
        'status'     => $_GET['status']     ?? '',
        'date_from'  => $_GET['date_from']  ?? '',
        'date_to'    => $_GET['date_to']    ?? '',
        'search'     => $_GET['search']     ?? '',
    ]);
    $total    = TripRepository::countAll($filters);
    $trips    = TripRepository::list($filters, $perPage, ($page - 1) * $perPage);
    $kpis     = TripRepository::kpis();
    $machines = TripRepository::allMachines();
    $pages    = (int)ceil($total / $perPage);
    require BASE_PATH . '/templates/dashboard.php';
    exit;
}

// Fahrt-Detail
if (preg_match('#^/trips/(\d+)$#', $path, $m)) {
    $tripId = (int)$m[1];
    $trip   = TripRepository::find($tripId);
    if (!$trip) {
        http_response_code(404);
        echo '<h1>Fahrt nicht gefunden</h1>';
        exit;
    }
    $gpsPoints = TripRepository::gpsPoints($tripId);
    $events    = TripRepository::events($tripId);

    // Störung als behoben markieren
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_event'])) {
        Auth::verifyCsrf();
        TripRepository::resolveEvent((int)$_POST['resolve_event']);
        header("Location: /trips/$tripId");
        exit;
    }

    require BASE_PATH . '/templates/trip_detail.php';
    exit;
}

// Einstellungen
if ($path === '/settings') {
    Auth::requireAdmin();
    $machines = TripRepository::allMachines();
    $message  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::verifyCsrf();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'generate_token':
                    $userId   = (int)$_POST['user_id'];
                    $rawToken = Auth::generateApiToken();
                    Database::query(
                        'UPDATE users SET api_token_hash = ? WHERE id = ?',
                        [Auth::hashToken($rawToken), $userId]
                    );
                    $newToken = $rawToken;
                    break;

                case 'save_machine':
                    TripRepository::saveMachine(
                        trim($_POST['machine_id']),
                        trim($_POST['machine_name']),
                        trim($_POST['machine_desc'] ?? '')
                    );
                    $message = 'Maschine gespeichert.';
                    break;

                case 'change_password':
                    $old = $_POST['old_password'] ?? '';
                    $new = $_POST['new_password'] ?? '';
                    $row = Database::fetchOne(
                        'SELECT password_hash FROM users WHERE id = ?',
                        [$currentUser['id']]
                    );
                    if ($row && password_verify($old, $row['password_hash']) && strlen($new) >= 8) {
                        Database::query(
                            'UPDATE users SET password_hash = ? WHERE id = ?',
                            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $currentUser['id']]
                        );
                        $message = 'Passwort geändert.';
                    } else {
                        $message = 'Fehler: Altes Passwort falsch oder neues Passwort zu kurz (min. 8 Zeichen).';
                    }
                    break;
            }
        }
    }

    $users = Database::fetchAll('SELECT id, username, role, active, last_login FROM users ORDER BY id');
    require BASE_PATH . '/templates/settings.php';
    exit;
}

// 404
http_response_code(404);
require BASE_PATH . '/templates/layout.php';
echo '<div class="container" style="padding-top:60px; text-align:center;">
    <h1 style="font-size:64px; color:#ccc;">404</h1>
    <p>Seite nicht gefunden. <a href="/dashboard">Zurück zur Übersicht</a></p>
</div>';
echo '</main></div></body></html>';
