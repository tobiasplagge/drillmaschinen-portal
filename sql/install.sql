-- ITD Landmaschinen Manager – Datenbankschema
-- Version 1.0.0
-- Ausführen: mysql -u root -p < install.sql

CREATE DATABASE IF NOT EXISTS `itd_landmaschinen`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `itd_landmaschinen`;

-- ─── Benutzer ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT            NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(64)    NOT NULL,
    `password_hash`  VARCHAR(255)   NOT NULL,
    `api_token_hash` VARCHAR(64)    NULL COMMENT 'SHA-256 Hex des Raw-Tokens',
    `role`           ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
    `active`         TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login`     DATETIME       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Maschinen ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `machines` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `machine_id`  VARCHAR(32)  NOT NULL COMMENT 'z.B. DR-001',
    `name`        VARCHAR(128) NOT NULL,
    `description` TEXT         NULL,
    `active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_machine_id` (`machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Fahrten ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `trips` (
    `id`                    INT            NOT NULL AUTO_INCREMENT,
    `machine_id`            VARCHAR(32)    NOT NULL,
    `field_name`            VARCHAR(128)   NOT NULL,
    `started_at`            DATETIME       NOT NULL,
    `ended_at`              DATETIME       NULL,
    `area_ha`               DECIMAL(8,2)   NULL,
    `seed_type`             VARCHAR(64)    NULL,
    `seed_rate_kgha`        DECIMAL(8,2)   NULL  COMMENT 'Durchschnittliche Saatmenge kg/ha',
    `working_width_m`       DECIMAL(5,2)   NULL,
    `blower_pressure_mbar`  DECIMAL(8,2)   NULL  COMMENT 'Gebl. Solldruck',
    `status`                ENUM('active','completed','error') NOT NULL DEFAULT 'completed',
    `notes`                 TEXT           NULL,
    `created_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_machine_id` (`machine_id`),
    KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── GPS-Punkte ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `gps_points` (
    `id`                    BIGINT        NOT NULL AUTO_INCREMENT,
    `trip_id`               INT           NOT NULL,
    `recorded_at`           DATETIME      NOT NULL,
    `lat`                   DECIMAL(10,7) NOT NULL,
    `lon`                   DECIMAL(10,7) NOT NULL,
    `speed_kmh`             DECIMAL(6,2)  NULL,
    `blower_rpm`            INT           NULL,
    `blower_pressure_mbar`  DECIMAL(8,2)  NULL,
    `seed_rate_kgha`        DECIMAL(8,2)  NULL,
    `working`               TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_trip_id` (`trip_id`),
    CONSTRAINT `fk_gps_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Ereignisse ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `events` (
    `id`          INT            NOT NULL AUTO_INCREMENT,
    `trip_id`     INT            NOT NULL,
    `recorded_at` DATETIME       NOT NULL,
    `type`        ENUM('fault','blower','info','warning') NOT NULL,
    `message`     VARCHAR(512)   NOT NULL,
    `lat`         DECIMAL(10,7)  NULL,
    `lon`         DECIMAL(10,7)  NULL,
    `resolved`    TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_trip_id` (`trip_id`),
    CONSTRAINT `fk_event_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Standard-Maschinen (Beispieldaten) ────────────────────────────────────────
INSERT INTO `machines` (`machine_id`, `name`, `description`) VALUES
    ('DR-001', 'Drillmaschine 1', 'Hauptmaschine'),
    ('DR-002', 'Drillmaschine 2', 'Zweite Maschine'),
    ('DR-003', 'Drillmaschine 3', 'Reserve');

-- ─── Admin-Benutzer ────────────────────────────────────────────────────────────
-- Passwort wird über setup/install.php gesetzt ODER manuell:
-- PHP: echo password_hash('IhrPasswort', PASSWORD_BCRYPT, ['cost'=>12]);
-- Platzhalter für manuelles Setup:
-- INSERT INTO users (username, password_hash, role) VALUES ('admin', '$2y$12$...', 'admin');
