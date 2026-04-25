<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dataDir . '/mobilhotell.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initialize_schema($pdo);

    return $pdo;
}

function initialize_schema(PDO $pdo): void
{
    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();

    if ($version < 1) {
        $pdo->beginTransaction();

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

        $pdo->exec('CREATE INDEX idx_sessions_participant_status ON phone_sessions(participant_id, status)');
        $pdo->exec('CREATE INDEX idx_sessions_slot_status ON phone_sessions(slot_id, status)');
        $pdo->exec('CREATE INDEX idx_sessions_status_checkin ON phone_sessions(status, checkin_time)');

        seed_slots($pdo);
        seed_demo_participants($pdo);

        $pdo->exec('PRAGMA user_version = 1');
        $pdo->commit();

        $version = 1;
    }

    if ($version < 2) {
        seed_image_participants($pdo, __DIR__ . '/images');
        $pdo->exec('PRAGMA user_version = 2');
        $version = 2;
    }

    if ($version < 3) {
        backfill_participant_images($pdo, __DIR__ . '/images');
        $pdo->exec('PRAGMA user_version = 3');
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

    $insert = $pdo->prepare('INSERT OR IGNORE INTO participants(qr_code, first_name, last_name, county, participant_type, image_path) VALUES (?, ?, ?, ?, ?, ?)');
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
