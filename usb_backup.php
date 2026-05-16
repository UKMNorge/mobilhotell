#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

const USB_MOUNT = '/mnt/usb';
const BACKUP_ROOT_NAME = 'mobilhotell-backup';
const MAX_DB_BACKUPS = 1440; // ~1 day if run every minute
const MAX_STATUS_BACKUPS = 1440;

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . "\n");
    exit($code);
}

function ensure_dir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fail('Kunne ikke opprette mappe: ' . $path);
    }
}

function write_atomic(string $path, string $content): void
{
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $content) === false) {
        fail('Kunne ikke skrive fil: ' . $path);
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        fail('Kunne ikke fullfoere skriving av fil: ' . $path);
    }
}

function prune_old(string $dir, string $prefix, string $suffix, int $keep): void
{
    $files = glob($dir . '/' . $prefix . '*' . $suffix) ?: [];
    if (count($files) <= $keep) {
        return;
    }
    sort($files, SORT_STRING);
    $remove = array_slice($files, 0, count($files) - $keep);
    foreach ($remove as $file) {
        @unlink($file);
    }
}

function fetch_active_sessions(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT
            ps.id AS session_id,
            p.first_name,
            p.last_name,
            p.qr_code,
            s.slot_number,
            ps.delivery_type,
            ps.checkin_time
         FROM phone_sessions ps
         JOIN participants p ON p.id = ps.participant_id
         JOIN slots s ON s.id = ps.slot_id
         WHERE ps.status = 'checked_in'
         ORDER BY s.slot_number ASC"
    );
    return $stmt ? $stmt->fetchAll() : [];
}

function as_csv(array $rows): string
{
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        fail('Kunne ikke bygge CSV');
    }

    fputcsv($out, ['slot', 'navn', 'qr', 'type', 'innlevert_tid', 'session_id']);
    foreach ($rows as $row) {
        $name = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
        $type = ((string)$row['delivery_type'] === 'charging') ? 'Lading' : 'Oppbevaring';
        fputcsv($out, [
            (string)$row['slot_number'],
            $name,
            (string)$row['qr_code'],
            $type,
            (string)$row['checkin_time'],
            (string)$row['session_id'],
        ]);
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv === false ? '' : $csv;
}

function as_text(array $rows): string
{
    $lines = [];
    $lines[] = 'Mobilhotell - aktive innleveringer';
    $lines[] = 'Generert: ' . date('Y-m-d H:i:s');
    $lines[] = 'Antall aktive: ' . count($rows);
    $lines[] = str_repeat('-', 70);

    if (!$rows) {
        $lines[] = 'Ingen aktive innleveringer akkurat naa.';
        return implode("\n", $lines) . "\n";
    }

    foreach ($rows as $row) {
        $name = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
        $type = ((string)$row['delivery_type'] === 'charging') ? 'Lading' : 'Oppbevaring';
        $lines[] = sprintf(
            'Slot %-6s | %-28s | %-15s | %-11s | %s',
            (string)$row['slot_number'],
            mb_strimwidth($name, 0, 28, ''),
            (string)$row['qr_code'],
            $type,
            (string)$row['checkin_time']
        );
    }

    return implode("\n", $lines) . "\n";
}

if (!is_dir(USB_MOUNT) || !is_writable(USB_MOUNT)) {
    fail('USB-mappe ikke tilgjengelig eller skrivbar: ' . USB_MOUNT, 2);
}

$backupRoot = USB_MOUNT . '/' . BACKUP_ROOT_NAME;
$dbDir = $backupRoot . '/db';
$statusDir = $backupRoot . '/status';
$latestDir = $backupRoot . '/latest';

ensure_dir($backupRoot);
ensure_dir($dbDir);
ensure_dir($statusDir);
ensure_dir($latestDir);

$pdo = db();
$driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

$stamp = date('Ymd-His');
$dbTarget = null;
if ($driver === 'sqlite') {
    $dbFile = __DIR__ . '/data/mobilhotell.sqlite';
    if (!is_file($dbFile) || !is_readable($dbFile)) {
        fail('Databasefil ikke lesbar: ' . $dbFile);
    }

    $pdo->exec('PRAGMA wal_checkpoint(FULL)');

    $dbTarget = $dbDir . '/mobilhotell-' . $stamp . '.sqlite';
    if (!copy($dbFile, $dbTarget)) {
        fail('Kunne ikke kopiere database til USB');
    }
}

$sessions = fetch_active_sessions($pdo);

$statusJson = [
    'generated_at' => date('c'),
    'active_count' => count($sessions),
    'items' => array_map(static function (array $row): array {
        return [
            'session_id' => (int)$row['session_id'],
            'slot' => (string)$row['slot_number'],
            'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
            'qr' => (string)$row['qr_code'],
            'type' => ((string)$row['delivery_type'] === 'charging') ? 'Lading' : 'Oppbevaring',
            'checkin_time' => (string)$row['checkin_time'],
        ];
    }, $sessions),
];

$statusJsonTarget = $statusDir . '/active-sessions-' . $stamp . '.json';
$statusCsvTarget = $statusDir . '/active-sessions-' . $stamp . '.csv';
$statusTxtTarget = $statusDir . '/active-sessions-' . $stamp . '.txt';

write_atomic(
    $statusJsonTarget,
    json_encode($statusJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);
write_atomic($statusCsvTarget, as_csv($sessions));
write_atomic($statusTxtTarget, as_text($sessions));

if ($dbTarget !== null && !copy($dbTarget, $latestDir . '/mobilhotell-latest.sqlite')) {
    fail('Kunne ikke oppdatere latest database backup');
}
if (!copy($statusJsonTarget, $latestDir . '/active-sessions-latest.json')) {
    fail('Kunne ikke oppdatere latest status JSON');
}
if (!copy($statusCsvTarget, $latestDir . '/active-sessions-latest.csv')) {
    fail('Kunne ikke oppdatere latest status CSV');
}
if (!copy($statusTxtTarget, $latestDir . '/active-sessions-latest.txt')) {
    fail('Kunne ikke oppdatere latest status TXT');
}

$readme = [
    'Mobilhotell USB-backup',
    'Generert: ' . date('Y-m-d H:i:s'),
    '',
    'Viktigste filer:',
    '- latest/active-sessions-latest.txt',
    '- latest/active-sessions-latest.csv',
    '- latest/active-sessions-latest.json',
    '',
    'Hvis systemet gaar ned: bruk active-sessions-latest.txt for aa finne hvilke mobiler',
    'som ligger i hvilke slots akkurat naa.',
    '',
    'Historikk:',
    '- db/: tidsstemplede database-backuper (kun SQLite)',
    '- status/: tidsstemplede lister over aktive innleveringer',
];

if ($dbTarget !== null) {
    array_splice($readme, 4, 0, '- latest/mobilhotell-latest.sqlite');
} else {
    $readme[] = '';
    $readme[] = 'Merk: aktiv database-driver er ' . $driver . ', saa SQLite-filbackup hoppes over.';
    $readme[] = 'Bruk database-server backup (f.eks. mysqldump) i tillegg.';
}
write_atomic($backupRoot . '/README-RECOVERY.txt', implode("\n", $readme) . "\n");

if ($dbTarget !== null) {
    prune_old($dbDir, 'mobilhotell-', '.sqlite', MAX_DB_BACKUPS);
}
prune_old($statusDir, 'active-sessions-', '.json', MAX_STATUS_BACKUPS);
prune_old($statusDir, 'active-sessions-', '.csv', MAX_STATUS_BACKUPS);
prune_old($statusDir, 'active-sessions-', '.txt', MAX_STATUS_BACKUPS);

echo 'Backup OK: ' . $stamp . "\n";