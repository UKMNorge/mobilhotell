<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fail fast on lock waits during concurrent check-in bursts.
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 8');

    initialize_schema($pdo);

    return $pdo;
}

function db_config(): array
{
    $apacheSetEnv = [];
    if (PHP_SAPI === 'cli') {
        $apacheSetEnv = apache_setenv_config();
    }

    $host = getenv('MOBILHOTELL_DB_HOST') ?: ($apacheSetEnv['MOBILHOTELL_DB_HOST'] ?? '127.0.0.1');
    $port = (int)(getenv('MOBILHOTELL_DB_PORT') ?: ($apacheSetEnv['MOBILHOTELL_DB_PORT'] ?? '3306'));
    $name = getenv('MOBILHOTELL_DB_NAME') ?: ($apacheSetEnv['MOBILHOTELL_DB_NAME'] ?? 'mobilhotell');
    $user = getenv('MOBILHOTELL_DB_USER') ?: ($apacheSetEnv['MOBILHOTELL_DB_USER'] ?? 'mobilhotell');
    $pass = getenv('MOBILHOTELL_DB_PASS');
    if ($pass === false || $pass === '') {
        $pass = $apacheSetEnv['MOBILHOTELL_DB_PASS'] ?? '';
    }

    return [
        'host' => $host,
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'charset' => 'utf8mb4',
    ];
}

function apache_setenv_config(): array
{
    $paths = [
        '/etc/apache2/conf-enabled/mobilhotell-db.conf',
        '/etc/apache2/conf-available/mobilhotell-db.conf',
    ];

    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }

        $cfg = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*SetEnv\s+([A-Z0-9_]+)\s+(.*)$/', $line, $m) !== 1) {
                continue;
            }

            $key = $m[1];
            $value = trim($m[2]);
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            $cfg[$key] = $value;
        }

        if ($cfg !== []) {
            return $cfg;
        }
    }

    return [];
}

function db_dump_command(): string
{
    return getenv('MOBILHOTELL_DB_DUMP_CMD') ?: '/usr/bin/mysqldump';
}

function dump_database_to_path(string $targetPath): void
{
    $cfg = db_config();
    $dumpBin = db_dump_command();

    $cmd = sprintf(
        '%s --single-transaction --quick --skip-lock-tables --default-character-set=%s --host=%s --port=%d --user=%s %s',
        escapeshellcmd($dumpBin),
        escapeshellarg($cfg['charset']),
        escapeshellarg($cfg['host']),
        $cfg['port'],
        escapeshellarg($cfg['user']),
        escapeshellarg($cfg['name'])
    );

    $env = $_ENV;
    if ($cfg['pass'] !== '') {
        $env['MYSQL_PWD'] = $cfg['pass'];
    }

    $errPipe = null;
    $process = proc_open(
        $cmd,
        [
            1 => ['file', $targetPath, 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        __DIR__,
        $env
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Kunne ikke starte mysqldump');
    }

    if (isset($pipes[2]) && is_resource($pipes[2])) {
        $errPipe = $pipes[2];
    }

    $stderr = '';
    if (is_resource($errPipe)) {
        $stderr = (string)stream_get_contents($errPipe);
        fclose($errPipe);
    }

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        @unlink($targetPath);
        throw new RuntimeException('mysqldump feilet: ' . trim($stderr));
    }
}

function initialize_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version INT NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $version = schema_version($pdo);

    if ($version < 1) {
        $pdo->beginTransaction();

        $pdo->exec("CREATE TABLE participants (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            qr_code VARCHAR(191) NOT NULL,
            first_name VARCHAR(191) NOT NULL,
            last_name VARCHAR(191) NOT NULL,
            county VARCHAR(191) NOT NULL DEFAULT '',
            participant_type VARCHAR(191) NOT NULL DEFAULT '',
            image_path VARCHAR(255) NOT NULL DEFAULT 'images/default.png',
            PRIMARY KEY (id),
            UNIQUE KEY uq_participants_qr_code (qr_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE slots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slot_number VARCHAR(32) NOT NULL,
            slot_type ENUM('storage', 'charging') NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uq_slots_slot_number (slot_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE phone_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            participant_id INT UNSIGNED NOT NULL,
            slot_id INT UNSIGNED NOT NULL,
            delivery_type ENUM('storage', 'charging') NOT NULL,
            checkin_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            checkout_time DATETIME NULL,
            status ENUM('checked_in', 'checked_out') NOT NULL DEFAULT 'checked_in',
            session_token VARCHAR(64) NULL,
            active_slot_id INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'checked_in' THEN slot_id ELSE NULL END) STORED,
            active_participant_id INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'checked_in' THEN participant_id ELSE NULL END) STORED,
            PRIMARY KEY (id),
            UNIQUE KEY uq_phone_sessions_session_token (session_token),
            UNIQUE KEY uq_phone_sessions_active_slot (active_slot_id),
            UNIQUE KEY uq_phone_sessions_active_participant (active_participant_id),
            KEY idx_sessions_participant_status (participant_id, status),
            KEY idx_sessions_slot_status (slot_id, status),
            KEY idx_sessions_status_checkin (status, checkin_time),
            CONSTRAINT fk_phone_sessions_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
            CONSTRAINT fk_phone_sessions_slot FOREIGN KEY (slot_id) REFERENCES slots(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE event_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(80) NOT NULL,
            message VARCHAR(255) NOT NULL,
            metadata_json JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_logs_created_at (created_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        seed_slots($pdo);
        seed_demo_participants($pdo);

        mark_schema_version($pdo, 1);
        $pdo->commit();

        $version = 1;
    }

    if ($version < 2) {
        seed_image_participants($pdo, __DIR__ . '/images');
        mark_schema_version($pdo, 2);
        $version = 2;
    }

    if ($version < 3) {
        backfill_participant_images($pdo, __DIR__ . '/images');
        mark_schema_version($pdo, 3);
        $version = 3;
    }

    if ($version < 4) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS event_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(80) NOT NULL,
            message VARCHAR(255) NOT NULL,
            metadata_json JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_logs_created_at (created_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        mark_schema_version($pdo, 4);
        $version = 4;
    }

    if ($version < 5) {
        ensure_slot_capacity($pdo, 'storage', 'S', 180);
        ensure_slot_capacity($pdo, 'charging', 'L', 120);
        mark_schema_version($pdo, 5);
    }
}

function schema_version(PDO $pdo): int
{
    $value = $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
    return (int)$value;
}

function mark_schema_version(PDO $pdo, int $version): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES (?)');
    $stmt->execute([$version]);
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

    $insert = $pdo->prepare('INSERT IGNORE INTO participants(qr_code, first_name, last_name, county, participant_type, image_path) VALUES (?, ?, ?, ?, ?, ?)');
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
    $stmt = $pdo->prepare('INSERT INTO event_logs(event_type, message, metadata_json, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([
        $eventType,
        $message,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
    ]);
}
