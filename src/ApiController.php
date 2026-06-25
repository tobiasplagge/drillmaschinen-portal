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

        try {
            // POST /api/trips/upload – ESP32-Kompatibilitäts-Endpunkt (multipart/form-data)
            if ($method === 'POST' && rtrim($path, '/') === '/api/trips/upload') {
                Auth::authenticateApi();
                self::handleEsp32Upload();
                return;
            }

            // Basis-Pfad /api/v1 entfernen
            $path = preg_replace('#^/api/v1#', '', $path);
            $path = rtrim($path, '/') ?: '/';

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

    // ─── POST /api/trips/upload – ESP32 multipart/form-data Upload ───────────────
    //
    // Das ESP32-Gerät sendet:
    //   trip_id          (string)    Fahrt-ID, z.B. "M01-B000001-F0001"
    //   device_id        (string)    Geräte-ID
    //   metadata         (JSON-Datei) Metadaten der Fahrt
    //   gps_csv          (CSV-Datei)  GPS-Spur
    //   main_events_csv  (CSV-Datei)  Hauptsignal-Ereignisse (Störungen)
    //   sensor_events_csv(CSV-Datei)  Sensor-Auslösungen
    //   combined_geojson (Datei)      GeoJSON (wird hier ignoriert)

    private static function handleEsp32Upload(): void
    {
        // Metadaten lesen
        $metadata = [];
        if (!empty($_FILES['metadata']['tmp_name'])) {
            $raw = file_get_contents($_FILES['metadata']['tmp_name']);
            $metadata = json_decode($raw, true) ?? [];
        }

        $tripId   = $_POST['trip_id']  ?? $metadata['trip_id']  ?? '';
        $deviceId = $_POST['device_id'] ?? $metadata['device_id'] ?? '';

        if (!$tripId) {
            self::error(400, 'trip_id fehlt');
        }

        // Doppelten Upload verhindern (trip_id ist unique-ähnlich über machine_id + field)
        $existing = Database::fetchOne(
            "SELECT id FROM trips WHERE machine_id = ? AND notes LIKE ?",
            [$deviceId ?: 'ESP32', '%esp32_trip_id=' . $tripId . '%']
        );
        if ($existing) {
            self::json([
                'trip_id'  => (int)$existing['id'],
                'message'  => 'Fahrt bereits vorhanden (doppelter Upload ignoriert)',
                'skipped'  => true,
            ], 200);
        }

        // GPS-CSV parsen
        $gpsPoints = [];
        $maxUptimeMs = 0;
        if (!empty($_FILES['gps_csv']['tmp_name'])) {
            $gpsPoints   = self::parseGpsCsv($_FILES['gps_csv']['tmp_name']);
            $maxUptimeMs = !empty($gpsPoints) ? max(array_column($gpsPoints, '_uptime_ms')) : 0;
        }

        // Zeitbasis schätzen: uploaded_at minus letzter Uptime-Wert
        $uploadedAt = $metadata['uploaded_at'] ?? date('Y-m-d\TH:i:s\Z');
        $baseTs     = strtotime($uploadedAt) - (int)($maxUptimeMs / 1000);

        // GPS-Punkte mit echten Timestamps versehen, _uptime_ms entfernen
        foreach ($gpsPoints as &$pt) {
            $ts = $baseTs + (int)($pt['_uptime_ms'] / 1000);
            $pt['timestamp'] = date('Y-m-d H:i:s', $ts);
            unset($pt['_uptime_ms']);
        }
        unset($pt);

        // Trip-Start/-Ende aus GPS-Punkten ableiten
        $startedAt = $gpsPoints ? $gpsPoints[0]['timestamp']  : date('Y-m-d H:i:s', $baseTs);
        $endedAt   = $gpsPoints ? end($gpsPoints)['timestamp'] : $uploadedAt;

        // Hauptsignal-Ereignisse parsen (Störungen / erkannte Signale)
        $events = [];
        if (!empty($_FILES['main_events_csv']['tmp_name'])) {
            $events = array_merge($events,
                self::parseMainEventsCsv($_FILES['main_events_csv']['tmp_name'], $baseTs)
            );
        }

        // Sensor-Ereignisse parsen (Kanal-Auslösungen)
        if (!empty($_FILES['sensor_events_csv']['tmp_name'])) {
            $events = array_merge($events,
                self::parseSensorEventsCsv($_FILES['sensor_events_csv']['tmp_name'], $baseTs)
            );
        }

        // Fahrt speichern
        $tripData = [
            'machine_id'  => $deviceId ?: 'ESP32',
            'field_name'  => $metadata['field_name'] ?? 'Unbekannt',
            'started_at'  => $startedAt,
            'ended_at'    => $endedAt,
            'area_ha'     => null,
            'seed_type'   => $metadata['crop_name'] ?? null,
            'status'      => 'completed',
            'notes'       => sprintf(
                "esp32_trip_id=%s\nfirmware=%s\ngps_points=%d\nuploaded_at=%s",
                $tripId,
                $metadata['firmware_version'] ?? '',
                count($gpsPoints),
                $uploadedAt
            ),
            'gps_points'  => $gpsPoints,
            'events'      => $events,
        ];

        $newTripId = TripRepository::createTrip($tripData);

        self::json([
            'trip_id' => $newTripId,
            'message' => 'Fahrt erfolgreich übermittelt',
            'stats'   => [
                'gps_points' => count($gpsPoints),
                'events'     => count($events),
            ],
        ], 201);
    }

    // ─── CSV-Parser: GPS-Log ──────────────────────────────────────────────────
    // Spalten: uptime_ms,crop_name,field_name,latitude,longitude,accuracy_m,
    //          speed_mps,heading_deg,satellites,live_mask,main_mask

    private static function parseGpsCsv(string $file): array
    {
        $points = [];
        $handle = fopen($file, 'r');
        if (!$handle) return $points;

        $header = fgetcsv($handle); // Header überspringen
        if (!$header) { fclose($handle); return $points; }

        $col = array_flip(array_map('trim', $header));

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue;
            $lat = isset($col['latitude'])  ? (float)$row[$col['latitude']]  : null;
            $lon = isset($col['longitude']) ? (float)$row[$col['longitude']] : null;
            if (!$lat && !$lon) continue;

            $speedMps   = isset($col['speed_mps']) ? (float)$row[$col['speed_mps']] : null;
            $uptimeMs   = isset($col['uptime_ms']) ? (int)$row[$col['uptime_ms']]   : 0;
            $mainMask   = isset($col['main_mask']) ? (int)$row[$col['main_mask']]   : 0;

            $points[] = [
                '_uptime_ms'           => $uptimeMs,
                'timestamp'            => '',        // wird später gesetzt
                'lat'                  => $lat,
                'lon'                  => $lon,
                'speed_kmh'            => $speedMps !== null ? round($speedMps * 3.6, 2) : null,
                'blower_rpm'           => null,
                'blower_pressure_mbar' => null,
                'seed_rate_kgha'       => null,
                'working'              => $mainMask > 0,
            ];
        }
        fclose($handle);
        return $points;
    }

    // ─── CSV-Parser: Hauptsignal-Ereignisse ───────────────────────────────────
    // Spalten: index,uptime_ms,channel,detected,crop_name,latitude,longitude,
    //          accuracy_m,live_mask,main_mask

    private static function parseMainEventsCsv(string $file, int $baseTs): array
    {
        $events = [];
        $handle = fopen($file, 'r');
        if (!$handle) return $events;

        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return $events; }
        $col = array_flip(array_map('trim', $header));

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) continue;
            $detected = isset($col['detected']) ? (int)$row[$col['detected']] : 0;
            if (!$detected) continue; // nur "Erkannt"-Ereignisse

            $uptimeMs = isset($col['uptime_ms']) ? (int)$row[$col['uptime_ms']] : 0;
            $channel  = isset($col['channel'])   ? (int)$row[$col['channel']]   : 0;
            $lat      = (isset($col['latitude'])  && $row[$col['latitude']] !== '')  ? (float)$row[$col['latitude']]  : null;
            $lon      = (isset($col['longitude']) && $row[$col['longitude']] !== '') ? (float)$row[$col['longitude']] : null;

            $ts = $baseTs + (int)($uptimeMs / 1000);

            $events[] = [
                'timestamp' => date('Y-m-d H:i:s', $ts),
                'type'      => 'fault',
                'message'   => sprintf('Hauptsignal erkannt: Kanal %d', $channel),
                'lat'       => $lat,
                'lon'       => $lon,
            ];
        }
        fclose($handle);
        return $events;
    }

    // ─── CSV-Parser: Sensor-Ereignisse ────────────────────────────────────────
    // Spalten: index,start_uptime_ms,end_uptime_ms,duration_ms,duration_s,
    //          channel,channel_name,crop_name,start_latitude,start_longitude,...

    private static function parseSensorEventsCsv(string $file, int $baseTs): array
    {
        $events = [];
        $handle = fopen($file, 'r');
        if (!$handle) return $events;

        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return $events; }
        $col = array_flip(array_map('trim', $header));

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6) continue;

            $startMs     = isset($col['start_uptime_ms']) ? (int)$row[$col['start_uptime_ms']] : 0;
            $durationS   = isset($col['duration_s'])      ? (float)$row[$col['duration_s']]    : 0;
            $channelName = isset($col['channel_name'])    ? trim($row[$col['channel_name']])    : '';
            $channel     = isset($col['channel'])         ? (int)$row[$col['channel']]          : 0;
            $lat         = (isset($col['start_latitude'])  && $row[$col['start_latitude']] !== '')  ? (float)$row[$col['start_latitude']]  : null;
            $lon         = (isset($col['start_longitude']) && $row[$col['start_longitude']] !== '') ? (float)$row[$col['start_longitude']] : null;

            $label = $channelName ?: "Kanal $channel";
            $ts    = $baseTs + (int)($startMs / 1000);

            $events[] = [
                'timestamp' => date('Y-m-d H:i:s', $ts),
                'type'      => 'info',
                'message'   => sprintf('Sensor ausgelöst: %s (%.1f s)', $label, $durationS),
                'lat'       => $lat,
                'lon'       => $lon,
            ];
        }
        fclose($handle);
        return $events;
    }

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
