<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$pdo = db();
$dbFile = __DIR__ . '/data/mobilhotell.sqlite';

if (!is_file($dbFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Databasefil ikke funnet';
    exit;
}

log_event($pdo, 'backup_download', 'Backup lastet ned');

header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string)filesize($dbFile));
header('Content-Disposition: attachment; filename="mobilhotell-backup-' . gmdate('Ymd-His') . '.sqlite"');
header('Cache-Control: no-store, no-cache, must-revalidate');

readfile($dbFile);
