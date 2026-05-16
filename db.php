<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = strtolower(trim((string)getenv('MOBILHOTELL_DB_DRIVER')));
    if ($driver === '') {
        $driver = 'sqlite';
    }

    if ($driver === 'mysql') {
        $host = trim((string)getenv('MOBILHOTELL_DB_HOST')) ?: '127.0.0.1';
        $port = (int)(getenv('MOBILHOTELL_DB_PORT') ?: '3306');
        $dbName = trim((string)getenv('MOBILHOTELL_DB_NAME')) ?: 'mobilhotell';
        $user = trim((string)getenv('MOBILHOTELL_DB_USER')) ?: 'mobilhotell';
        $pass = (string)(getenv('MOBILHOTELL_DB_PASS') ?: '');
        $charset = trim((string)getenv('MOBILHOTELL_DB_CHARSET')) ?: 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $dataDir . '/mobilhotell.sqlite');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if (db_is_sqlite($pdo)) {
        // Runtime tuning for concurrent kiosk/admin traffic on constrained hardware.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA temp_store = MEMORY');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo->exec("SET time_zone = '+00:00'");
    }

    initialize_schema($pdo);

    return $pdo;
}

function db_is_sqlite(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

function db_is_mysql(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

function db_now_expr(PDO $pdo): string
{
    return db_is_mysql($pdo) ? 'UTC_TIMESTAMP()' : "datetime('now')";
}

function db_unix_ts_expr(PDO $pdo, string $columnExpr): string
{
    return db_is_mysql($pdo) ? 'UNIX_TIMESTAMP(' . $columnExpr . ')' : "strftime('%s', " . $columnExpr . ')';
}

function db_name_concat_expr(PDO $pdo, string $firstExpr, string $lastExpr): string
{
    if (db_is_mysql($pdo)) {
        return 'CONCAT(' . $firstExpr . ", ' ', " . $lastExpr . ')';
    }

    return $firstExpr . " || ' ' || " . $lastExpr;
}

function db_insert_ignore_prefix(PDO $pdo): string
{
    return db_is_mysql($pdo) ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
}

function get_schema_version(PDO $pdo): int
{
    if (db_is_sqlite($pdo)) {
        return (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_meta (
        id TINYINT UNSIGNED PRIMARY KEY,
        schema_version INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->query('SELECT schema_version FROM schema_meta WHERE id = 1');
    $v = $stmt ? $stmt->fetchColumn() : false;
    return $v === false ? 0 : (int)$v;
}

function set_schema_version(PDO $pdo, int $version): void
{
    if (db_is_sqlite($pdo)) {
        $pdo->exec('PRAGMA user_version = ' . $version);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO schema_meta(id, schema_version) VALUES (1, ?) ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version)');
    $stmt->execute([$version]);
}

function initialize_schema(PDO $pdo): void
{
    $version = get_schema_version($pdo);

    if ($version < 1) {
        $pdo->beginTransaction();

        if (db_is_mysql($pdo)) {
            $pdo->exec("CREATE TABLE participants (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                qr_code VARCHAR(191) NOT NULL UNIQUE,
                first_name VARCHAR(191) NOT NULL,
                last_name VARCHAR(191) NOT NULL,
                county VARCHAR(191) NOT NULL DEFAULT '',
                participant_type VARCHAR(191) NOT NULL DEFAULT '',
                image_path VARCHAR(255) NOT NULL DEFAULT 'images/default.png'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE slots (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slot_number VARCHAR(32) NOT NULL UNIQUE,
                slot_type ENUM('storage', 'charging') NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE phone_sessions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                participant_id INT UNSIGNED NOT NULL,
                slot_id INT UNSIGNED NOT NULL,
                delivery_type ENUM('storage', 'charging') NOT NULL,
                checkin_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                checkout_time DATETIME NULL,
                status ENUM('checked_in', 'checked_out') NOT NULL DEFAULT 'checked_in',
                session_token VARCHAR(191) UNIQUE,
                CONSTRAINT fk_phone_sessions_participant FOREIGN KEY(participant_id) REFERENCES participants(id),
                CONSTRAINT fk_phone_sessions_slot FOREIGN KEY(slot_id) REFERENCES slots(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                qr_code TEXT NOT NULL UNIQUE,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                county TEXT NOT NULL DEFAULT '',
                participant_type TEXT NOT NULL DEFAULT '',
                image_path TEXT NOT NULL DEFAULT 'images/default.png'
            )");

            $pdo->exec("CREATE TABLE slots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slot_number TEXT NOT NULL UNIQUE,
                slot_type TEXT NOT NULL CHECK(slot_type IN ('storage', 'charging')),
                is_active INTEGER NOT NULL DEFAULT 1
            )");

            $pdo->exec("CREATE TABLE phone_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                participant_id INTEGER NOT NULL,
                slot_id INTEGER NOT NULL,
                delivery_type TEXT NOT NULL CHECK(delivery_type IN ('storage', 'charging')),
                checkin_time TEXT NOT NULL DEFAULT (datetime('now')),
                checkout_time TEXT,
                status TEXT NOT NULL DEFAULT 'checked_in' CHECK(status IN ('checked_in', 'checked_out')),
                session_token TEXT UNIQUE,
                FOREIGN KEY(participant_id) REFERENCES participants(id),
                FOREIGN KEY(slot_id) REFERENCES slots(id)
            )");
        }

        $pdo->exec('CREATE INDEX idx_sessions_participant_status ON phone_sessions(participant_id, status)');
        $pdo->exec('CREATE INDEX idx_sessions_slot_status ON phone_sessions(slot_id, status)');
        $pdo->exec('CREATE INDEX idx_sessions_status_checkin ON phone_sessions(status, checkin_time)');

        seed_slots($pdo);
        seed_demo_participants($pdo);

        set_schema_version($pdo, 1);
        $pdo->commit();

        $version = 1;
    }

    if ($version < 2) {
        seed_image_participants($pdo, __DIR__ . '/images');
        set_schema_version($pdo, 2);
        $version = 2;
    }

    if ($version < 3) {
        backfill_participant_images($pdo, __DIR__ . '/images');
        set_schema_version($pdo, 3);
        $version = 3;
    }

    if ($version < 4) {
        if (db_is_mysql($pdo)) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS event_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(191) NOT NULL,
                message TEXT NOT NULL,
                metadata_json LONGTEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS event_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                message TEXT NOT NULL,
                metadata_json TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )");
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_event_logs_created_at ON event_logs(created_at DESC)');
        set_schema_version($pdo, 4);
        $version = 4;
    }

    if ($version < 5) {
        ensure_slot_capacity($pdo, 'storage', 'S', 180);
        ensure_slot_capacity($pdo, 'charging', 'L', 120);
        set_schema_version($pdo, 5);
    }
}

function ensure_slot_capacity(PDO $pdo, string $slotType, string $prefix, int $targetCount): void
{
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM slots WHERE slot_type = ?');
    $countStmt->execute([$slotType]);
    $existingCount = (int)$countStmt->fetchColumn();
    if ($existingCount >= $targetCount) {
        return;
    }

    $maxStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTR(slot_number, 2) AS INTEGER)) FROM slots WHERE slot_type = ?");
    $maxStmt->execute([$slotType]);
    $maxNumber = (int)$maxStmt->fetchColumn();

    $insertStmt = $pdo->prepare('INSERT INTO slots(slot_number, slot_type, is_active) VALUES (?, ?, 1)');
    for ($n = $maxNumber + 1; $existingCount < $targetCount; $n++, $existingCount++) {
        $slotNumber = $prefix . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
        $insertStmt->execute([$slotNumber, $slotType]);
    }
}

function seed_slots(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO slots(slot_number, slot_type, is_active) VALUES (?, ?, 1)');
    for ($i = 1; $i <= 24; $i++) {
        $stmt->execute(['S' . str_pad((string)$i, 2, '0', STR_PAD_LEFT), 'storage']);
    }
    for ($i = 1; $i <= 12; $i++) {
        $stmt->execute(['L' . str_pad((string)$i, 2, '0', STR_PAD_LEFT), 'charging']);
    }
}

function seed_demo_participants(PDO $pdo): void
{
    $images = list_image_paths(__DIR__ . '/images');
    $img1 = $images[0] ?? 'images/default.png';
    $img2 = $images[1] ?? $img1;

    $stmt = $pdo->prepare('INSERT INTO participants(qr_code, first_name, last_name, county, participant_type, image_path) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute(['DEMO-001', 'Demo', 'Deltaker', 'Vestland', 'Utøver', $img1]);
    $stmt->execute(['DEMO-002', 'Test', 'Person', 'Trøndelag', 'Crew', $img2]);
}

function list_image_paths(string $imagesDir): array
{
    if (!is_dir($imagesDir)) {
        return [];
    }

    $files = scandir($imagesDir);
    if ($files === false) {
        return [];
    }

    $paths = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            continue;
        }
        $paths[] = 'images/' . $file;
    }

    sort($paths);
    return $paths;
}

function seed_image_participants(PDO $pdo, string $imagesDir): void
{
    $paths = list_image_paths($imagesDir);
    if (!$paths) {
        return;
    }

    $insert = $pdo->prepare(db_insert_ignore_prefix($pdo) . ' INTO participants(qr_code, first_name, last_name, county, participant_type, image_path) VALUES (?, ?, ?, ?, ?, ?)');
    $index = 1;

    foreach ($paths as $imagePath) {
        $base = (string)pathinfo($imagePath, PATHINFO_FILENAME);
        $clean = preg_replace('/[^a-zA-Z0-9]+/', ' ', $base);
        $clean = trim((string)$clean);
        if ($clean === '') {
            $clean = 'Deltaker ' . $index;
        }

        $parts = preg_split('/\s+/', $clean) ?: [];
        $firstName = ucfirst(strtolower((string)($parts[0] ?? ('Deltaker' . $index))));
        $lastName = ucfirst(strtolower((string)($parts[1] ?? 'Eksempel')));
        $qr = 'IMG-' . strtoupper(str_pad((string)$index, 3, '0', STR_PAD_LEFT));

        $insert->execute([
            $qr,
            $firstName,
            $lastName,
            'Eksempel',
            'Utøver',
            $imagePath
        ]);

        $index++;
    }
}

function backfill_participant_images(PDO $pdo, string $imagesDir): void
{
    $paths = list_image_paths($imagesDir);
    if (!$paths) {
        return;
    }

    $participants = $pdo->query('SELECT id, image_path FROM participants ORDER BY id')->fetchAll();
    if (!$participants) {
        return;
    }

    $update = $pdo->prepare('UPDATE participants SET image_path = ? WHERE id = ?');
    $i = 0;

    foreach ($participants as $p) {
        $current = trim((string)($p['image_path'] ?? ''));
        $fullCurrentPath = __DIR__ . '/' . ltrim($current, '/');
        $hasValidPath = $current !== '' && is_file($fullCurrentPath);

        if ($hasValidPath) {
            continue;
        }

        $imagePath = $paths[$i % count($paths)];
        $update->execute([$imagePath, (int)$p['id']]);
        $i++;
    }
}

function log_event(PDO $pdo, string $eventType, string $message, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO event_logs(event_type, message, metadata_json, created_at) VALUES (?, ?, ?, ' . db_now_expr($pdo) . ')');
    $stmt->execute([
        $eventType,
        $message,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
    ]);
}
