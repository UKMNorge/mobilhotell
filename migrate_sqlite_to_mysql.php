#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function mysql_from_env(): PDO
{
    $host = trim((string)getenv('MOBILHOTELL_DB_HOST')) ?: '127.0.0.1';
    $port = (int)(getenv('MOBILHOTELL_DB_PORT') ?: '3306');
    $dbName = trim((string)getenv('MOBILHOTELL_DB_NAME')) ?: 'mobilhotell';
    $user = trim((string)getenv('MOBILHOTELL_DB_USER')) ?: 'mobilhotell';
    $pass = (string)(getenv('MOBILHOTELL_DB_PASS') ?: '');
    $charset = trim((string)getenv('MOBILHOTELL_DB_CHARSET')) ?: 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}

function sqlite_source(string $file): PDO
{
    if (!is_file($file) || !is_readable($file)) {
        fail('Fant ikke lesbar SQLite-fil: ' . $file);
    }

    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function copy_rows(PDO $src, PDO $dst, string $selectSql, string $insertSql, string $tableName): int
{
    $rows = $src->query($selectSql)->fetchAll();
    if (!$rows) {
        return 0;
    }

    $insert = $dst->prepare($insertSql);
    foreach ($rows as $row) {
        $insert->execute($row);
    }

    return count($rows);
}

$sourceFile = __DIR__ . '/data/mobilhotell.sqlite';
$src = sqlite_source($sourceFile);
$dst = mysql_from_env();

// Ensure schema exists in MySQL according to current app migrations.
initialize_schema($dst);

$tables = [
    'phone_sessions',
    'event_logs',
    'slots',
    'participants',
];

try {
    $dst->beginTransaction();
    $dst->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $dst->exec('TRUNCATE TABLE ' . $table);
    }

    $participants = copy_rows(
        $src,
        $dst,
        'SELECT id, qr_code, first_name, last_name, county, participant_type, image_path FROM participants ORDER BY id',
        'INSERT INTO participants(id, qr_code, first_name, last_name, county, participant_type, image_path)
         VALUES (:id, :qr_code, :first_name, :last_name, :county, :participant_type, :image_path)',
        'participants'
    );

    $slots = copy_rows(
        $src,
        $dst,
        'SELECT id, slot_number, slot_type, is_active FROM slots ORDER BY id',
        'INSERT INTO slots(id, slot_number, slot_type, is_active)
         VALUES (:id, :slot_number, :slot_type, :is_active)',
        'slots'
    );

    $sessions = copy_rows(
        $src,
        $dst,
        "SELECT id, participant_id, slot_id, delivery_type, checkin_time, checkout_time, status, session_token FROM phone_sessions ORDER BY id",
        'INSERT INTO phone_sessions(id, participant_id, slot_id, delivery_type, checkin_time, checkout_time, status, session_token)
         VALUES (:id, :participant_id, :slot_id, :delivery_type, :checkin_time, :checkout_time, :status, :session_token)',
        'phone_sessions'
    );

    $events = copy_rows(
        $src,
        $dst,
        'SELECT id, event_type, message, metadata_json, created_at FROM event_logs ORDER BY id',
        'INSERT INTO event_logs(id, event_type, message, metadata_json, created_at)
         VALUES (:id, :event_type, :message, :metadata_json, :created_at)',
        'event_logs'
    );

    $dst->exec('SET FOREIGN_KEY_CHECKS=1');
    $dst->commit();

    echo 'Migrering ferdig.' . PHP_EOL;
    echo 'participants: ' . $participants . PHP_EOL;
    echo 'slots: ' . $slots . PHP_EOL;
    echo 'phone_sessions: ' . $sessions . PHP_EOL;
    echo 'event_logs: ' . $events . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    if ($dst->inTransaction()) {
        $dst->rollBack();
    }
    try {
        $dst->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable) {
    }
    fail('Migrering feilet: ' . $e->getMessage());
}
