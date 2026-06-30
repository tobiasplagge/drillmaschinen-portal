<?php
declare(strict_types=1);

class Auth
{
    // ─── Web-Session ─────────────────────────────────────────────────────────

    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name(SESSION_NAME);
            session_start();
        }
    }

    public static function login(string $username, string $password): bool
    {
        $row = Database::fetchOne(
            'SELECT id, username, password_hash, role FROM users WHERE username = ? AND active = 1',
            [$username]
        );
        if (!$row || !password_verify($password, $row['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']   = $row['id'];
        $_SESSION['username']  = $row['username'];
        $_SESSION['role']      = $row['role'];
        $_SESSION['logged_in'] = true;

        Database::query(
            'UPDATE users SET last_login = NOW() WHERE id = ?',
            [$row['id']]
        );
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Kein Zugriff.');
        }
    }

    public static function currentUser(): array
    {
        return [
            'id'       => $_SESSION['user_id'] ?? 0,
            'username' => $_SESSION['username'] ?? '',
            'role'     => $_SESSION['role'] ?? '',
        ];
    }

    // ─── API-Token ───────────────────────────────────────────────────────────

    public static function generateApiToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function validateApiToken(string $rawToken): array|false
    {
        $hash = self::hashToken($rawToken);
        return Database::fetchOne(
            'SELECT id, username, role FROM users
             WHERE api_token_hash = ? AND active = 1
               AND (api_token_expires_at IS NULL OR api_token_expires_at > NOW())',
            [$hash]
        );
    }

    public static function authenticateApi(): array
    {
        // Apache/PHP-FPM may pass the header under different keys depending on setup
        $allHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? $allHeaders['authorization']
               ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            $user = self::validateApiToken(trim($m[1]));
            if ($user) {
                return $user;
            }
        }
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized: ungültiger oder fehlender Bearer Token']);
        exit;
    }

    // ─── CSRF ────────────────────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            exit('CSRF-Fehler: ungültiges Token.');
        }
    }
}
