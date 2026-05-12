<?php

declare(strict_types=1);

const USB_STATUS_MOUNT = '/mnt/usb';
const USB_STATUS_BACKUP_ROOT = 'mobilhotell-backup';

function usb_status_sync(PDO $pdo): bool
{
    if (!is_dir(USB_STATUS_MOUNT) || !is_writable(USB_STATUS_MOUNT)) {
        return false;
    }

    $root = USB_STATUS_MOUNT . '/' . USB_STATUS_BACKUP_ROOT;
    $latestDir = $root . '/latest';

    usb_status_ensure_dir($root);
    usb_status_ensure_dir($latestDir);

    $rows = usb_status_fetch_active_sessions($pdo);

    $payload = [
        'generated_at' => date('c'),
        'active_count' => count($rows),
        'items' => array_map(static function (array $row): array {
            return [
                'session_id' => (int)$row['session_id'],
                'slot' => (string)$row['slot_number'],
                'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
                'qr' => (string)$row['qr_code'],
                'type' => ((string)$row['delivery_type'] === 'charging') ? 'Lading' : 'Oppbevaring',
                'checkin_time' => (string)$row['checkin_time'],
            ];
        }, $rows),
    ];

    usb_status_write_atomic(
        $latestDir . '/active-sessions-latest.json',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
    );
    usb_status_write_atomic($latestDir . '/active-sessions-latest.csv', usb_status_as_csv($rows));
    usb_status_write_atomic($latestDir . '/active-sessions-latest.txt', usb_status_as_text($rows));

    return true;
}

function usb_status_fetch_active_sessions(PDO $pdo): array
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

function usb_status_as_csv(array $rows): string
{
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        return '';
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

function usb_status_as_text(array $rows): string
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

function usb_status_ensure_dir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    @mkdir($path, 0775, true);
}

function usb_status_write_atomic(string $path, string $content): void
{
    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $content) === false) {
        return;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
    }
}
