# ITD Landmaschinen Manager

Webbasiertes Portal zur Auswertung von Drillmaschinen-Daten.  
Fahrten, GPS-Spuren, Störungen und Gebläse-Ereignisse werden über eine REST-API von der Maschinensteuerung übermittelt und in einer übersichtlichen Weboberfläche ausgewertet.

![Mockup](mockup.html)

---

## Funktionen

| Bereich | Beschreibung |
|---|---|
| **Login** | Benutzeranmeldung mit Session-Authentifizierung und CSRF-Schutz |
| **Dashboard** | Übersicht aller Fahrten mit KPI-Karten, Filter, Suche und Pagination |
| **Kartenansicht** | GPS-Fahrspur auf OpenStreetMap (Leaflet.js) mit farbcodierten Markern für Störungen und Gebläse-Ereignisse |
| **Verlaufsdiagramme** | Geschwindigkeit, Gebläsedruck und Saatmenge als Zeitverlauf (Chart.js) |
| **Ereignisliste** | Tabellarische Übersicht aller Störungen und Ereignisse einer Fahrt |
| **REST-API** | Gesicherter Batch-Upload von Fahrten, GPS-Punkten und Ereignissen |
| **Einstellungen** | Maschinenverwaltung, API-Token-Generierung, Passwortverwaltung |
| **Setup-Assistent** | Webbasierte Ersteinrichtung mit Datenbankverbindungstest |

---

## Technologie

| Schicht | Technologie |
|---|---|
| Backend | PHP 8.1+ |
| Datenbank | MySQL 5.7 / MariaDB 10.4+ |
| Frontend | Bootstrap 5, Leaflet.js 1.9, Chart.js 4 |
| Hosting | Apache mit mod_rewrite (z.B. Netcup Webspace) |
| Karten | OpenStreetMap (kostenlos, keine API-Key erforderlich) |

---

## Projektstruktur

```
drillmaschinen-portal/
├── public/                  ← Web-Root (Document Root hier eintragen)
│   ├── index.php            ← Einstiegspunkt & Router
│   ├── .htaccess            ← URL-Rewriting & Sicherheitsheader
│   └── assets/
│       ├── css/app.css      ← Design-System (Farben, Layout)
│       └── js/trip_detail.js ← Leaflet-Karte + Chart.js
├── src/
│   ├── Database.php         ← PDO-Singleton
│   ├── Auth.php             ← Session- & API-Token-Authentifizierung
│   ├── TripRepository.php   ← Datenbankzugriff (Fahrten, GPS, Ereignisse)
│   └── ApiController.php    ← REST-API Handler
├── templates/
│   ├── layout.php           ← HTML-Rahmen & Navigation
│   ├── login.php            ← Anmeldemaske
│   ├── dashboard.php        ← Fahrtenübersicht
│   ├── trip_detail.php      ← Karte, Diagramme, Ereignisliste
│   └── settings.php         ← Administration
├── config/
│   └── config.php           ← Datenbank- & App-Konfiguration
├── sql/
│   └── install.sql          ← Datenbankschema
├── setup/
│   └── install.php          ← Einrichtungsassistent (nach Setup löschen)
├── mockup.html              ← Design-Mockup (alle Ansichten)
└── DEPLOY.md                ← Detaillierte Deployment-Anleitung
```

---

## Installation (Netcup Webspace)

### 1. Dateien hochladen

Alle Dateien per FTP/SFTP auf den Webspace laden.  
Im Netcup-Kundencenter (CCP) den **Document Root der Domain** auf das Verzeichnis `public/` setzen.

### 2. Datenbank anlegen

Im Webspace-Panel (Plesk) eine neue MySQL-Datenbank erstellen:

- Datenbankname: `itd_landmaschinen`
- Benutzer und Passwort nach Wahl

### 3. Konfiguration anpassen

Datei `config/config.php` öffnen und die Zugangsdaten eintragen:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'itd_landmaschinen');
define('DB_USER', 'itd_user');
define('DB_PASS', 'IHR_DATENBANKPASSWORT');
```

### 4. Einrichtungsassistent aufrufen

```
https://ihre-domain.de/setup/install.php
```

Der Assistent prüft die Datenbankverbindung, erstellt alle Tabellen und legt den ersten Admin-Benutzer an.

> **Wichtig:** `setup/install.php` nach der Einrichtung löschen.

### 5. Anmelden

```
https://ihre-domain.de/login
```

---

## API-Dokumentation

Alle Endpunkte unter `/api/v1/` · Authentifizierung via `Authorization: Bearer <token>`

### Token ausstellen

```http
POST /api/v1/auth/token
Content-Type: application/json

{
  "username": "api_user",
  "password": "passwort"
}
```

Antwort:
```json
{
  "token": "abc123...",
  "expires_at": "2026-07-25T12:00:00Z"
}
```

Den Token einmalig abrufen und in der Maschinensteuerung speichern.  
API-Token werden im Einstellungsbereich verwaltet (**Einstellungen → Benutzer & API-Token → Neu generieren**).

---

### Fahrt übermitteln (Batch nach Fahrtende)

```http
POST /api/v1/trips
Authorization: Bearer <token>
Content-Type: application/json

{
  "machine_id":            "DR-001",
  "field_name":            "Schlag Nordfeld",
  "started_at":            "2026-06-25T06:12:00",
  "ended_at":              "2026-06-25T09:57:00",
  "area_ha":               18.4,
  "seed_type":             "Winterweizen",
  "seed_rate_kgha":        220,
  "working_width_m":       6.0,
  "blower_pressure_mbar":  35,
  "gps_points": [
    {
      "timestamp":             "2026-06-25T06:12:00",
      "lat":                   51.4823,
      "lon":                   9.2145,
      "speed_kmh":             7.2,
      "blower_rpm":            2400,
      "blower_pressure_mbar":  35.2,
      "seed_rate_kgha":        221.5,
      "working":               true
    }
  ],
  "events": [
    {
      "timestamp": "2026-06-25T07:34:00",
      "type":      "fault",
      "message":   "Verstopfung Reihe 4",
      "lat":       51.4823,
      "lon":       9.2145
    },
    {
      "timestamp": "2026-06-25T07:41:00",
      "type":      "blower",
      "message":   "Gebläse ausgelöst",
      "lat":       51.4831,
      "lon":       9.2198
    }
  ]
}
```

Antwort:
```json
{ "trip_id": 42, "message": "Fahrt erfolgreich übermittelt" }
```

---

### Alle Endpunkte

| Methode | Pfad | Beschreibung |
|---|---|---|
| `POST` | `/api/v1/auth/token` | API-Token ausstellen |
| `POST` | `/api/v1/trips` | Fahrt mit GPS-Punkten und Ereignissen übermitteln |
| `GET`  | `/api/v1/trips` | Fahrten abrufen (Parameter: `page`, `limit`, `machine_id`, `status`, `date_from`, `date_to`, `search`) |
| `GET`  | `/api/v1/trips/{id}` | Einzelne Fahrt abrufen (inkl. GPS-Punkte und Ereignisse) |
| `POST` | `/api/v1/trips/{id}/events` | Einzelnes Ereignis zu einer Fahrt hinzufügen |
| `PATCH`| `/api/v1/trips/{id}/finish` | Fahrt nachträglich abschließen |

---

### GPS-Punkt Felder

| Feld | Pflicht | Typ | Beschreibung |
|---|---|---|---|
| `timestamp` | ✅ | string | ISO 8601 Datetime |
| `lat` | ✅ | float | Breitengrad |
| `lon` | ✅ | float | Längengrad |
| `speed_kmh` | – | float | Fahrgeschwindigkeit in km/h |
| `blower_rpm` | – | int | Gebläsedrehzahl in U/min |
| `blower_pressure_mbar` | – | float | Ist-Gebläsedruck in mbar |
| `seed_rate_kgha` | – | float | Ist-Saatmenge in kg/ha |
| `working` | – | bool | `true` = Arbeitsfahrt, `false` = Wendefahrt |

### Ereignis-Typen

| Typ | Bedeutung |
|---|---|
| `fault` | Störung oder Fehler (wird als offene Störung geführt) |
| `blower` | Gebläse-Auslösung |
| `warning` | Warnung |
| `info` | Allgemeine Information |

---

## Datenbankschema

```
users         – Benutzerkonten (Web-Login & API-Token)
machines      – Maschinenverzeichnis (machine_id, Name)
trips         – Fahrten (Maschine, Feld, Zeit, Fläche, Einstellungen)
gps_points    – GPS-Trackpunkte je Fahrt (lat, lon, speed, Gebläse, Saatmenge)
events        – Ereignisse je Fahrt (Störungen, Gebläse-Auslösungen)
```

Das vollständige Schema: [`sql/install.sql`](sql/install.sql)

---

## Sicherheit

- **Passwörter** werden mit bcrypt (cost 12) gehasht gespeichert
- **API-Tokens** werden als SHA-256-Hash in der Datenbank abgelegt, nie im Klartext
- **SQL-Injection** wird durch PDO Prepared Statements verhindert
- **XSS** wird durch konsequentes `htmlspecialchars()` in allen Templates verhindert
- **CSRF** wird durch CSRF-Token in allen Formularen verhindert
- **Session-Sicherheit** durch `session_regenerate_id()` nach dem Login
- **Sicherheitsheader** (X-Content-Type-Options, X-Frame-Options, etc.) via `.htaccess`

---

## Voraussetzungen

- PHP 8.1 oder höher
- MySQL 5.7 / MariaDB 10.4 oder höher
- Apache mit `mod_rewrite` (Standard auf Netcup Webspace)

---

## Lizenz

IT-Design Online · Tobias Plagge  
Alle Rechte vorbehalten.
