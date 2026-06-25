# ITD Landmaschinen Manager – Deployment auf Netcup Webspace

## Voraussetzungen
- PHP 8.1 oder höher
- MySQL 5.7 / MariaDB 10.4 oder höher
- mod_rewrite aktiviert (Apache)

## Schritt 1: Dateien hochladen

Laden Sie alle Dateien per FTP/SFTP auf den Netcup Webspace hoch.
**Wichtig:** Der Web-Root muss auf das Verzeichnis `public/` zeigen.

Typische Struktur auf Netcup:
```
/var/www/html/                  ← oder individueller Pfad
  ├── public/                   ← WEB ROOT (document root hier eintragen)
  ├── config/
  ├── src/
  ├── templates/
  ├── sql/
  └── setup/
```

Im Netcup Kundencenter (CCP) unter Domain-Einstellungen den Document Root
auf `.../itd-landmaschinen-manager/public/` setzen.

## Schritt 2: Datenbank anlegen

Im Netcup Webspace-Panel (Plesk/cPanel) eine neue MySQL-Datenbank erstellen:
- Datenbankname: `itd_landmaschinen`
- Benutzer: `itd_user`
- Passwort: sicheres Passwort wählen

## Schritt 3: Konfiguration

Datei `config/config.php` anpassen:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'itd_landmaschinen');
define('DB_USER', 'itd_user');
define('DB_PASS', 'IHR_DATENBANKPASSWORT');
```

## Schritt 4: Installation ausführen

Einmalig im Browser aufrufen:
```
https://ihre-domain.de/setup/install.php
```

- Schritt 1: Verbindungstest
- Schritt 2: Admin-Benutzer anlegen

**Nach der Installation: `setup/install.php` löschen!**

## Schritt 5: Login

```
https://ihre-domain.de/login
```

## Schritt 6: API-Token für Drillmaschinen-Software

1. Einstellungen → Benutzer & API-Token
2. Token für den gewünschten Benutzer generieren
3. Token in der Drillmaschinen-Software eintragen

---

## API-Integration (Drillmaschinen-Software)

### Token abrufen
```http
POST https://ihre-domain.de/api/v1/auth/token
Content-Type: application/json

{
  "username": "machine_user",
  "password": "passwort"
}
```

### Fahrt übermitteln (Batch nach Fahrtende)
```http
POST https://ihre-domain.de/api/v1/trips
Authorization: Bearer IHR_TOKEN
Content-Type: application/json

{
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
    },
    {
      "timestamp": "2026-06-25T07:41:00",
      "type": "blower",
      "message": "Gebläse ausgelöst",
      "lat": 51.4831,
      "lon": 9.2198
    }
  ]
}
```

Antwort:
```json
{ "trip_id": 1, "message": "Fahrt erfolgreich übermittelt" }
```

### Ereignis-Typen
| Typ       | Bedeutung               |
|-----------|------------------------|
| `fault`   | Störung / Fehler        |
| `blower`  | Gebläse ausgelöst       |
| `warning` | Warnung                 |
| `info`    | Allgemeine Information  |

### GPS-Punkt Felder
| Feld                   | Pflicht | Typ     | Beschreibung              |
|------------------------|---------|---------|---------------------------|
| `timestamp`            | ✅      | string  | ISO 8601 Datetime         |
| `lat`                  | ✅      | float   | Breitengrad               |
| `lon`                  | ✅      | float   | Längengrad                |
| `speed_kmh`            | –       | float   | Geschwindigkeit km/h      |
| `blower_rpm`           | –       | int     | Gebläsedrehzahl U/min     |
| `blower_pressure_mbar` | –       | float   | Gebläsedruck mbar         |
| `seed_rate_kgha`       | –       | float   | Ist-Saatmenge kg/ha       |
| `working`              | –       | bool    | true = in Arbeit          |
