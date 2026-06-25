<?php
declare(strict_types=1);

class TripRepository
{
    // ─── Fahrten-Liste (Dashboard) ────────────────────────────────────────────

    public static function countAll(array $filters = []): int
    {
        [$where, $params] = self::buildWhere($filters);
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS n FROM trips t $where",
            $params
        );
        return (int)($row['n'] ?? 0);
    }

    public static function list(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = self::buildWhere($filters);
        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT
                t.id,
                t.machine_id,
                COALESCE(m.name, t.machine_id) AS machine_name,
                t.field_name,
                t.started_at,
                t.ended_at,
                t.area_ha,
                t.seed_type,
                t.status,
                COUNT(CASE WHEN e.type = 'fault'   THEN 1 END) AS fault_count,
                COUNT(CASE WHEN e.type = 'blower'  THEN 1 END) AS blower_count,
                COUNT(CASE WHEN e.type = 'warning' THEN 1 END) AS warning_count,
                COUNT(e.id) AS event_count
             FROM trips t
             LEFT JOIN machines m ON m.machine_id = t.machine_id
             LEFT JOIN events e   ON e.trip_id    = t.id
             $where
             GROUP BY t.id
             ORDER BY t.started_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    // ─── KPIs ─────────────────────────────────────────────────────────────────

    public static function kpis(): array
    {
        $row = Database::fetchOne(
            "SELECT
                COUNT(*)          AS total_trips,
                COALESCE(SUM(area_ha), 0) AS total_area
             FROM trips"
        );
        $faults = Database::fetchOne(
            "SELECT COUNT(*) AS n FROM events WHERE type = 'fault' AND resolved = 0"
        );
        $machines = Database::fetchOne(
            "SELECT COUNT(*) AS n FROM machines WHERE active = 1"
        );
        return [
            'total_trips'   => (int)$row['total_trips'],
            'total_area'    => (float)$row['total_area'],
            'open_faults'   => (int)$faults['n'],
            'active_machines' => (int)$machines['n'],
        ];
    }

    // ─── Einzelne Fahrt ───────────────────────────────────────────────────────

    public static function find(int $id): array|false
    {
        return Database::fetchOne(
            "SELECT t.*,
                    COALESCE(m.name, t.machine_id) AS machine_name
             FROM trips t
             LEFT JOIN machines m ON m.machine_id = t.machine_id
             WHERE t.id = ?",
            [$id]
        );
    }

    public static function gpsPoints(int $tripId): array
    {
        return Database::fetchAll(
            "SELECT recorded_at, lat, lon, speed_kmh,
                    blower_rpm, blower_pressure_mbar, seed_rate_kgha, working
             FROM gps_points
             WHERE trip_id = ?
             ORDER BY recorded_at ASC",
            [$tripId]
        );
    }

    public static function events(int $tripId): array
    {
        return Database::fetchAll(
            "SELECT id, recorded_at, type, message, lat, lon, resolved
             FROM events
             WHERE trip_id = ?
             ORDER BY recorded_at ASC",
            [$tripId]
        );
    }

    // ─── Maschinen ────────────────────────────────────────────────────────────

    public static function allMachines(): array
    {
        return Database::fetchAll(
            "SELECT machine_id, name, description, active FROM machines ORDER BY machine_id"
        );
    }

    public static function machineById(string $machineId): array|false
    {
        return Database::fetchOne(
            "SELECT * FROM machines WHERE machine_id = ?",
            [$machineId]
        );
    }

    public static function saveMachine(string $machineId, string $name, string $desc): void
    {
        Database::query(
            "INSERT INTO machines (machine_id, name, description)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)",
            [$machineId, $name, $desc]
        );
    }

    // ─── Fahrt anlegen (API Batch-Upload) ─────────────────────────────────────

    public static function createTrip(array $data): int
    {
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            // Maschine auto-anlegen wenn nicht vorhanden
            $pdo->prepare(
                "INSERT IGNORE INTO machines (machine_id, name) VALUES (?, ?)"
            )->execute([$data['machine_id'], $data['machine_id']]);

            // Fahrt
            $pdo->prepare(
                "INSERT INTO trips
                    (machine_id, field_name, started_at, ended_at, area_ha,
                     seed_type, seed_rate_kgha, working_width_m,
                     blower_pressure_mbar, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $data['machine_id'],
                $data['field_name'],
                $data['started_at'],
                $data['ended_at']              ?? null,
                $data['area_ha']               ?? null,
                $data['seed_type']             ?? null,
                $data['seed_rate_kgha']        ?? null,
                $data['working_width_m']       ?? null,
                $data['blower_pressure_mbar']  ?? null,
                $data['status']                ?? 'completed',
                $data['notes']                 ?? null,
            ]);
            $tripId = (int)$pdo->lastInsertId();

            // GPS-Punkte (Bulk-Insert für Performance)
            if (!empty($data['gps_points'])) {
                self::insertGpsPoints($pdo, $tripId, $data['gps_points']);
            }

            // Ereignisse
            if (!empty($data['events'])) {
                $stmtEv = $pdo->prepare(
                    "INSERT INTO events (trip_id, recorded_at, type, message, lat, lon)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($data['events'] as $ev) {
                    $stmtEv->execute([
                        $tripId,
                        $ev['timestamp'],
                        $ev['type'],
                        $ev['message'],
                        $ev['lat'] ?? null,
                        $ev['lon'] ?? null,
                    ]);
                }
            }

            $pdo->commit();
            return $tripId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function insertGpsPoints(PDO $pdo, int $tripId, array $points): void
    {
        $chunkSize = 500;
        $chunks = array_chunk($points, $chunkSize);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?)'));
            $stmt = $pdo->prepare(
                "INSERT INTO gps_points
                    (trip_id, recorded_at, lat, lon, speed_kmh,
                     blower_rpm, blower_pressure_mbar, seed_rate_kgha, working)
                 VALUES $placeholders"
            );
            $values = [];
            foreach ($chunk as $p) {
                $values[] = $tripId;
                $values[] = $p['timestamp'];
                $values[] = $p['lat'];
                $values[] = $p['lon'];
                $values[] = $p['speed_kmh']            ?? null;
                $values[] = $p['blower_rpm']           ?? null;
                $values[] = $p['blower_pressure_mbar'] ?? null;
                $values[] = $p['seed_rate_kgha']       ?? null;
                $values[] = isset($p['working']) ? (int)$p['working'] : 1;
            }
            $stmt->execute($values);
        }
    }

    // ─── Störung als behoben markieren ────────────────────────────────────────

    public static function resolveEvent(int $eventId): void
    {
        Database::query(
            "UPDATE events SET resolved = 1 WHERE id = ?",
            [$eventId]
        );
    }

    // ─── WHERE-Builder ────────────────────────────────────────────────────────

    private static function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['machine_id'])) {
            $conditions[] = 't.machine_id = ?';
            $params[]     = $filters['machine_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 't.status = ?';
            $params[]     = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 't.started_at >= ?';
            $params[]     = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 't.started_at <= ?';
            $params[]     = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(t.field_name LIKE ? OR t.machine_id LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
