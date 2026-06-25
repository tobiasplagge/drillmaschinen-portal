<?php
declare(strict_types=1);

class ApiController
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Basis-Pfad /api/v1 entfernen
        $path = preg_replace('#^/api/v1#', '', $path);
        $path = rtrim($path, '/') ?: '/';

        try {
            // POST /auth/token – Token ausstellen
            if ($method === 'POST' && $path === '/auth/token') {
                self::handleAuthToken();
                return;
            }

            // Ab hier: Bearer-Token erforderlich
            $apiUser = Auth::authenticateApi();

            // POST /trips – Fahrt mit Batch-Daten anlegen
            if ($method === 'POST' && $path === '/trips') {
                self::handleCreateTrip($apiUser);
                return;
            }

            // GET /trips – Fahrten abrufen
            if ($method === 'GET' && $path === '/trips') {
                self::handleListTrips();
                return;
            }

            // GET /trips/{id}
            if ($method === 'GET' && preg_match('#^/trips/(\d+)$#', $path, $m)) {
                self::handleGetTrip((int)$m[1]);
                return;
            }

            // POST /trips/{id}/events – Einzelnes Ereignis hinzufügen
            if ($method === 'POST' && preg_match('#^/trips/(\d+)/events$#', $path, $m)) {
                self::handleAddEvent((int)$m[1]);
                return;
            }

            // PATCH /trips/{id}/finish – Fahrt abschließen
            if ($method === 'PATCH' && preg_match('#^/trips/(\d+)/finish$#', $path, $m)) {
                self::handleFinishTrip((int)$m[1]);
                return;
            }

            self::error(404, 'Endpunkt nicht gefunden');
        } catch (Throwable $e) {
            $msg = APP_DEBUG ? $e->getMessage() : 'Interner Serverfehler';
            self::error(500, $msg);
        }
    }

    // ─── POST /auth/token ─────────────────────────────────────────────────────

    private static function handleAuthToken(): void
    {
        $body = self::readJson();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$password) {
            self::error(400, 'username und password erforderlich');
        }

        $row = Database::fetchOne(
            'SELECT id, password_hash FROM users WHERE username = ? AND active = 1',
            [$username]
        );
        if (!$row || !password_verify($password, $row['password_hash'])) {
            self::error(401, 'Ungültige Zugangsdaten');
        }

        $rawToken = Auth::generateApiToken();
        $hash     = Auth::hashToken($rawToken);
        $expires  = date('Y-m-d\TH:i:s\Z', time() + API_TOKEN_LIFETIME);

        Database::query(
            'UPDATE users SET api_token_hash = ? WHERE id = ?',
            [$hash, $row['id']]
        );

        self::json([
            'token'      => $rawToken,
            'expires_at' => $expires,
            'note'       => 'Token im Header senden: Authorization: Bearer <token>',
        ], 200);
    }

    // ─── POST /trips ──────────────────────────────────────────────────────────

    private static function handleCreateTrip(array $apiUser): void
    {
        $body = self::readJson();

        $required = ['machine_id', 'field_name', 'started_at'];
        foreach ($required as $f) {
            if (empty($body[$f])) {
                self::error(400, "Pflichtfeld fehlt: $f");
            }
        }

        // Datumsformat validieren
        foreach (['started_at', 'ended_at'] as $f) {
            if (!empty($body[$f]) && !self::isValidDatetime($body[$f])) {
                self::error(400, "Ungültiges Datumsformat für $f (ISO 8601 erwartet)");
            }
        }

        // Typ-Validierung GPS-Punkte
        if (!empty($body['gps_points'])) {
            foreach ($body['gps_points'] as $i => $p) {
                if (!isset($p['timestamp'], $p['lat'], $p['lon'])) {
                    self::error(400, "gps_points[$i]: timestamp, lat, lon erforderlich");
                }
                if (!is_numeric($p['lat']) || !is_numeric($p['lon'])) {
                    self::error(400, "gps_points[$i]: lat/lon müssen numerisch sein");
                }
            }
        }

        // Typ-Validierung Ereignisse
        $allowedTypes = ['fault', 'blower', 'info', 'warning'];
        if (!empty($body['events'])) {
            foreach ($body['events'] as $i => $ev) {
                if (!isset($ev['timestamp'], $ev['type'], $ev['message'])) {
                    self::error(400, "events[$i]: timestamp, type, message erforderlich");
                }
                if (!in_array($ev['type'], $allowedTypes, true)) {
                    self::error(400, "events[$i]: type muss eine von " . implode(', ', $allowedTypes) . " sein");
                }
            }
        }

        $tripId = TripRepository::createTrip($body);

        self::json([
            'trip_id' => $tripId,
            'message' => 'Fahrt erfolgreich übermittelt',
        ], 201);
    }

    // ─── GET /trips ───────────────────────────────────────────────────────────

    private static function handleListTrips(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset  = ($page - 1) * $limit;
        $filters = array_intersect_key($_GET, array_flip(['machine_id', 'status', 'date_from', 'date_to', 'search']));

        $total = TripRepository::countAll($filters);
        $trips = TripRepository::list($filters, $limit, $offset);

        self::json([
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
            'trips'  => $trips,
        ]);
    }

    // ─── GET /trips/{id} ──────────────────────────────────────────────────────

    private static function handleGetTrip(int $id): void
    {
        $trip = TripRepository::find($id);
        if (!$trip) {
            self::error(404, 'Fahrt nicht gefunden');
        }
        $trip['gps_points'] = TripRepository::gpsPoints($id);
        $trip['events']     = TripRepository::events($id);

        self::json($trip);
    }

    // ─── POST /trips/{id}/events ──────────────────────────────────────────────

    private static function handleAddEvent(int $tripId): void
    {
        $trip = TripRepository::find($tripId);
        if (!$trip) {
            self::error(404, 'Fahrt nicht gefunden');
        }

        $body = self::readJson();
        if (empty($body['type']) || empty($body['message']) || empty($body['timestamp'])) {
            self::error(400, 'type, message und timestamp erforderlich');
        }

        Database::query(
            "INSERT INTO events (trip_id, recorded_at, type, message, lat, lon)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$tripId, $body['timestamp'], $body['type'], $body['message'],
             $body['lat'] ?? null, $body['lon'] ?? null]
        );

        self::json(['message' => 'Ereignis gespeichert'], 201);
    }

    // ─── PATCH /trips/{id}/finish ─────────────────────────────────────────────

    private static function handleFinishTrip(int $tripId): void
    {
        $trip = TripRepository::find($tripId);
        if (!$trip) {
            self::error(404, 'Fahrt nicht gefunden');
        }

        $body = self::readJson();
        Database::query(
            "UPDATE trips SET ended_at = ?, area_ha = ?, status = 'completed' WHERE id = ?",
            [$body['ended_at'] ?? date('Y-m-d H:i:s'), $body['area_ha'] ?? null, $tripId]
        );

        self::json(['message' => 'Fahrt abgeschlossen']);
    }

    // ─── Hilfsmethoden ────────────────────────────────────────────────────────

    private static function readJson(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error(400, 'Ungültiger JSON-Body: ' . json_last_error_msg());
        }
        return $data ?? [];
    }

    private static function isValidDatetime(string $dt): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?/', $dt);
    }

    private static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function error(int $status, string $message): never
    {
        http_response_code($status);
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
