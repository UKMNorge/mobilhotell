<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$pdo = db();

$tmpFile = tempnam(sys_get_temp_dir(), 'mobilhotell-db-');
if ($tmpFile === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Kunne ikke opprette midlertidig backupfil';
    exit;
}

try {
    dump_database_to_path($tmpFile);
} catch (Throwable $e) {
    @unlink($tmpFile);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Databasebackup feilet';
    exit;
}

log_event($pdo, 'backup_download', 'Backup lastet ned');

header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string)filesize($tmpFile));
header('Content-Disposition: attachment; filename="mobilhotell-backup-' . gmdate('Ymd-His') . '.sql"');
header('Cache-Control: no-store, no-cache, must-revalidate');

readfile($tmpFile);
@unlink($tmpFile);
