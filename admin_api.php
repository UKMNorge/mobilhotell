<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json');

$pdo = db();
$action = trim((string)($_GET['action'] ?? ''));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function input_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function node_info(): array
{
    $rawRole = strtolower(trim((string)(getenv('MOBILHOTELL_NODE_ROLE') ?: 'hoved')));
    $role = in_array($rawRole, ['hoved', 'klient'], true) ? $rawRole : 'hoved';

    return [
        'role' => $role,
    ];
}

function parse_day(string $value): string
{
        $value = trim($value);
        if ($value === '') {
                return gmdate('Y-m-d');
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
                return gmdate('Y-m-d');
        }

        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        if (!checkdate($mo, $d, $y)) {
                return gmdate('Y-m-d');
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

function fetch_digital_detox_items(PDO $pdo, string $day): array
{
        $dayStart = $day . ' 00:00:00';
        $nextDay = date('Y-m-d 00:00:00', strtotime($day . ' +1 day'));
        $checkinDeadline = $day . ' 09:30:00';
        $detoxDeadline = $day . ' 18:30:00';

        $stmt = $pdo->prepare("SELECT
                p.id AS participant_id,
                p.qr_code,
                p.first_name,
                p.last_name,
                MIN(ps.checkin_time) AS first_checkin,
                MAX(CASE WHEN ps.status = 'checked_out' THEN ps.checkout_time ELSE NULL END) AS checkout_time
            FROM phone_sessions ps
            JOIN participants p ON p.id = ps.participant_id
            WHERE ps.checkin_time >= ?
                AND ps.checkin_time < ?
                AND ps.checkin_time <= ?
            GROUP BY p.id, p.qr_code, p.first_name, p.last_name
            HAVING (
                MAX(CASE WHEN ps.status = 'checked_out' AND ps.checkout_time >= ? THEN 1 ELSE 0 END) = 1
                OR (
                    MAX(CASE WHEN ps.status = 'checked_in' THEN 1 ELSE 0 END) = 1
                    AND NOW() >= ?
                )
            )
            ORDER BY p.last_name ASC, p.first_name ASC");
        $stmt->execute([$dayStart, $nextDay, $checkinDeadline, $detoxDeadline, $detoxDeadline]);

        $items = $stmt->fetchAll();
        foreach ($items as &$row) {
                $row['name'] = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
        }

        return $items;
}

function parse_csv_upload(PDO $pdo): array
{
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        return ['ok' => false, 'error' => 'missing_file'];
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'upload_failed'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return ['ok' => false, 'error' => 'invalid_upload'];
    }

    $handle = fopen($tmp, 'r');
    if ($handle === false) {
        return ['ok' => false, 'error' => 'cannot_open_upload'];
    }

    $header = fgetcsv($handle);
    if (!is_array($header)) {
        fclose($handle);
        return ['ok' => false, 'error' => 'empty_csv'];
    }

    $cols = array_map(static fn($c) => strtolower(trim((string)$c)), $header);
    $required = ['qr_code', 'first_name', 'last_name'];
    foreach ($required as $r) {
        if (!in_array($r, $cols, true)) {
            fclose($handle);
            return ['ok' => false, 'error' => 'missing_column_' . $r];
        }
    }

    $idx = array_flip($cols);
    $phoneCol = null;
    foreach (['phone_number', 'phone', 'telefon', 'mobil', 'mobile'] as $candidate) {
        if (array_key_exists($candidate, $idx)) {
            $phoneCol = $candidate;
            break;
        }
    }
    $inserted = 0;
    $updated = 0;

    $select = $pdo->prepare('SELECT id FROM participants WHERE qr_code = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO participants(qr_code, first_name, last_name, phone_number, county, participant_type, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $update = $pdo->prepare('UPDATE participants SET first_name = ?, last_name = ?, phone_number = ?, county = ?, participant_type = ?, image_path = ? WHERE id = ?');

    while (($row = fgetcsv($handle)) !== false) {
        $qr = trim((string)($row[$idx['qr_code']] ?? ''));
        $first = trim((string)($row[$idx['first_name']] ?? ''));
        $last = trim((string)($row[$idx['last_name']] ?? ''));
        $phone = $phoneCol !== null ? trim((string)($row[$idx[$phoneCol]] ?? '')) : '';
        $county = trim((string)($row[$idx['county']] ?? ''));
        $ptype = trim((string)($row[$idx['participant_type']] ?? ''));
        $image = trim((string)($row[$idx['image_path']] ?? ''));

        if ($qr === '' || $first === '' || $last === '') {
            continue;
        }

        if ($image === '') {
            $image = 'images/default.png';
        }

        $select->execute([$qr]);
        $existing = $select->fetch();
        if ($existing) {
            $update->execute([$first, $last, $phone, $county, $ptype, $image, (int)$existing['id']]);
            $updated++;
        } else {
            $insert->execute([$qr, $first, $last, $phone, $county, $ptype, $image]);
            $inserted++;
        }
    }

    fclose($handle);
    return ['ok' => true, 'inserted' => $inserted, 'updated' => $updated];
}

if ($action === 'active_list' && $method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $needle = '%' . mb_strtolower($q) . '%';

    if ($q === '') {
        $stmt = $pdo->query("SELECT ps.id AS session_id, s.id AS slot_id, s.slot_number, s.slot_type,
            p.qr_code, p.first_name, p.last_name, ps.checkin_time
            FROM phone_sessions ps
            JOIN participants p ON p.id = ps.participant_id
            JOIN slots s ON s.id = ps.slot_id
            WHERE ps.status = 'checked_in'
            ORDER BY ps.checkin_time ASC");
    } else {
        $stmt = $pdo->prepare("SELECT ps.id AS session_id, s.id AS slot_id, s.slot_number, s.slot_type,
            p.qr_code, p.first_name, p.last_name, ps.checkin_time
            FROM phone_sessions ps
            JOIN participants p ON p.id = ps.participant_id
            JOIN slots s ON s.id = ps.slot_id
            WHERE ps.status = 'checked_in'
              AND (
                lower(p.qr_code) LIKE ?
                                OR lower(CONCAT(p.first_name, ' ', p.last_name)) LIKE ?
                                OR lower(CONCAT(p.last_name, ' ', p.first_name)) LIKE ?
              )
            ORDER BY ps.checkin_time ASC");
        $stmt->execute([$needle, $needle, $needle]);
    }

    $items = $stmt->fetchAll();
    foreach ($items as &$row) {
        $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    }

    out(['success' => true, 'items' => $items]);
}

if ($action === 'storage_active_list' && $method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $needle = '%' . mb_strtolower($q) . '%';

    if ($q === '') {
        $stmt = $pdo->query("SELECT ss.id AS session_id, p.qr_code, p.first_name, p.last_name, ss.checkin_time
            FROM storage_sessions ss
            JOIN participants p ON p.id = ss.participant_id
            WHERE ss.status = 'checked_in'
            ORDER BY ss.checkin_time ASC");
    } else {
        $stmt = $pdo->prepare("SELECT ss.id AS session_id, p.qr_code, p.first_name, p.last_name, ss.checkin_time
            FROM storage_sessions ss
            JOIN participants p ON p.id = ss.participant_id
            WHERE ss.status = 'checked_in'
              AND (
                lower(p.qr_code) LIKE ?
                OR lower(CONCAT(p.first_name, ' ', p.last_name)) LIKE ?
                OR lower(CONCAT(p.last_name, ' ', p.first_name)) LIKE ?
              )
            ORDER BY ss.checkin_time ASC");
        $stmt->execute([$needle, $needle, $needle]);
    }

    $items = $stmt->fetchAll();
    foreach ($items as &$row) {
        $row['name'] = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
    }

    out(['success' => true, 'items' => $items]);
}

if ($action === 'slot_grid' && $method === 'GET') {
    $stmt = $pdo->query("SELECT s.id AS slot_id, s.slot_number, s.slot_type, s.is_active,
        ps.id AS session_id, p.qr_code, p.first_name, p.last_name
        FROM slots s
        LEFT JOIN phone_sessions ps ON ps.slot_id = s.id AND ps.status = 'checked_in'
        LEFT JOIN participants p ON p.id = ps.participant_id
        ORDER BY s.slot_number ASC");

    $items = $stmt->fetchAll();
    foreach ($items as &$row) {
        $row['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ((int)$row['is_active'] !== 1) {
            $row['status'] = 'disabled';
        } elseif (!empty($row['session_id'])) {
            $row['status'] = 'busy';
        } else {
            $row['status'] = 'free';
        }
    }

    out(['success' => true, 'items' => $items]);
}

if ($action === 'slot_detail' && $method === 'GET') {
    $slotNumber = trim((string)($_GET['slot_number'] ?? ''));
    if ($slotNumber === '') {
        out(['success' => false, 'error' => 'missing_slot_number'], 400);
    }

    $stmt = $pdo->prepare("SELECT s.id AS slot_id, s.slot_number, s.slot_type, s.is_active,
        ps.id AS session_id, ps.checkin_time, p.qr_code, p.first_name, p.last_name
        FROM slots s
        LEFT JOIN phone_sessions ps ON ps.slot_id = s.id AND ps.status = 'checked_in'
        LEFT JOIN participants p ON p.id = ps.participant_id
        WHERE s.slot_number = ?
        LIMIT 1");
    $stmt->execute([$slotNumber]);
    $slot = $stmt->fetch();

    if (!$slot) {
        out(['success' => false, 'error' => 'slot_not_found'], 404);
    }

    $slot['name'] = trim(($slot['first_name'] ?? '') . ' ' . ($slot['last_name'] ?? ''));
    if ((int)$slot['is_active'] !== 1) {
        $slot['status'] = 'disabled';
    } elseif (!empty($slot['session_id'])) {
        $slot['status'] = 'busy';
    } else {
        $slot['status'] = 'free';
    }

    out(['success' => true, 'slot' => $slot]);
}

if ($action === 'manual_checkout' && $method === 'POST') {
    $input = input_json();
    $sessionId = (int)($input['session_id'] ?? 0);
    if ($sessionId <= 0) {
        out(['success' => false, 'error' => 'missing_session_id'], 400);
    }

    $stmt = $pdo->prepare("UPDATE phone_sessions
        SET checkout_time = NOW(), status = 'checked_out'
        WHERE id = ? AND status = 'checked_in'");
    $stmt->execute([$sessionId]);

    if ($stmt->rowCount() > 0) {
        log_event($pdo, 'admin_manual_checkout', 'Utlevering registrert fra admin', [
            'session_id' => $sessionId
        ]);
    }

    out(['success' => $stmt->rowCount() > 0]);
}

if ($action === 'storage_manual_checkout' && $method === 'POST') {
    $input = input_json();
    $sessionId = (int)($input['session_id'] ?? 0);
    if ($sessionId <= 0) {
        out(['success' => false, 'error' => 'missing_session_id'], 400);
    }

    $stmt = $pdo->prepare("UPDATE storage_sessions
        SET checkout_time = NOW(), status = 'checked_out'
        WHERE id = ? AND status = 'checked_in'");
    $stmt->execute([$sessionId]);

    if ($stmt->rowCount() > 0) {
        log_event($pdo, 'admin_storage_manual_checkout', 'Generell oppbevaring utlevering fra admin', [
            'session_id' => $sessionId,
        ]);
    }

    out(['success' => $stmt->rowCount() > 0]);
}

if ($action === 'set_slot_active' && $method === 'POST') {
    $input = input_json();
    $slotId = (int)($input['slot_id'] ?? 0);
    $isActive = (int)($input['is_active'] ?? -1);

    if ($slotId <= 0 || !in_array($isActive, [0, 1], true)) {
        out(['success' => false, 'error' => 'bad_request'], 400);
    }

    $stmt = $pdo->prepare('UPDATE slots SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive, $slotId]);

    if ($stmt->rowCount() > 0) {
        log_event($pdo, 'admin_slot_toggle', 'Slot-status oppdatert', [
            'slot_id' => $slotId,
            'is_active' => $isActive
        ]);
    }

    out(['success' => $stmt->rowCount() > 0]);
}

if ($action === 'health' && $method === 'GET') {
    $active = (int)$pdo->query("SELECT COUNT(*) FROM phone_sessions WHERE status = 'checked_in'")->fetchColumn();
    $activeStorage = (int)$pdo->query("SELECT COUNT(*) FROM storage_sessions WHERE status = 'checked_in'")->fetchColumn();
    $slotsTotal = (int)$pdo->query('SELECT COUNT(*) FROM slots')->fetchColumn();
    $slotsActive = (int)$pdo->query('SELECT COUNT(*) FROM slots WHERE is_active = 1')->fetchColumn();
    $slotsBusy = (int)$pdo->query("SELECT COUNT(*) FROM slots s WHERE EXISTS (SELECT 1 FROM phone_sessions ps WHERE ps.slot_id = s.id AND ps.status = 'checked_in')")->fetchColumn();
    $participants = (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();
    $node = node_info();

    out([
        'success' => true,
        'summary' => [
            'active_checkins' => $active,
            'active_storage_checkins' => $activeStorage,
            'slots_total' => $slotsTotal,
            'slots_active' => $slotsActive,
            'slots_busy' => $slotsBusy,
            'slots_free_active' => max(0, $slotsActive - $slotsBusy),
            'participants_total' => $participants,
            'server_time' => gmdate('Y-m-d H:i:s'),
            'node_role' => $node['role'],
        ]
    ]);
}

if ($action === 'capacity' && $method === 'GET') {
    $targets = [
        'storage' => 180,
        'charging_usb_a' => 60,
        'charging_usb_c' => 120,
    ];

    $occupiedStmt = $pdo->query("SELECT s.slot_number, COUNT(*) AS c
        FROM phone_sessions ps
        JOIN slots s ON s.id = ps.slot_id
        WHERE ps.status = 'checked_in'
        GROUP BY s.slot_number");
    $occupiedMap = ['storage' => 0, 'charging_usb_a' => 0, 'charging_usb_c' => 0];
    foreach ($occupiedStmt->fetchAll() as $row) {
        $slotNumber = strtoupper((string)$row['slot_number']);
        $count = (int)$row['c'];
        if (str_starts_with($slotNumber, 'O')) {
            $occupiedMap['storage'] += $count;
        } elseif (str_starts_with($slotNumber, 'A')) {
            $occupiedMap['charging_usb_a'] += $count;
        } elseif (str_starts_with($slotNumber, 'C')) {
            $occupiedMap['charging_usb_c'] += $count;
        }
    }

    $byType = [];
    $totalTarget = 0;
    $totalOccupied = 0;
    foreach ($targets as $type => $total) {
        $occupied = $occupiedMap[$type] ?? 0;
        $free = max(0, $total - $occupied);
        $percent = $total > 0 ? round(($occupied / $total) * 100, 1) : 0.0;
        $byType[$type] = [
            'total' => $total,
            'occupied' => $occupied,
            'free' => $free,
            'percent' => $percent,
        ];
        $totalTarget += $total;
        $totalOccupied += $occupied;
    }

    out([
        'success' => true,
        'capacity' => [
            'storage' => $byType['storage'],
            'charging_usb_a' => $byType['charging_usb_a'],
            'charging_usb_c' => $byType['charging_usb_c'],
            'charging' => [
                'total' => $byType['charging_usb_a']['total'] + $byType['charging_usb_c']['total'],
                'occupied' => $byType['charging_usb_a']['occupied'] + $byType['charging_usb_c']['occupied'],
                'free' => $byType['charging_usb_a']['free'] + $byType['charging_usb_c']['free'],
                'percent' => ($byType['charging_usb_a']['total'] + $byType['charging_usb_c']['total']) > 0
                    ? round((($byType['charging_usb_a']['occupied'] + $byType['charging_usb_c']['occupied']) / ($byType['charging_usb_a']['total'] + $byType['charging_usb_c']['total'])) * 100, 1)
                    : 0.0,
            ],
            'overall' => [
                'total' => $totalTarget,
                'occupied' => $totalOccupied,
                'free' => max(0, $totalTarget - $totalOccupied),
                'percent' => $totalTarget > 0 ? round(($totalOccupied / $totalTarget) * 100, 1) : 0.0,
            ],
        ],
    ]);
}

if ($action === 'recent_events' && $method === 'GET') {
    $limit = (int)($_GET['limit'] ?? 50);
    if ($limit < 1) $limit = 1;
    if ($limit > 200) $limit = 200;

    $stmt = $pdo->prepare('SELECT id, event_type, message, metadata_json, created_at FROM event_logs ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $meta = json_decode((string)($item['metadata_json'] ?? ''), true);
        $item['metadata'] = is_array($meta) ? $meta : null;
    }

    out(['success' => true, 'items' => $items]);
}

if ($action === 'screentime_overview' && $method === 'GET') {
    $limit = (int)($_GET['limit'] ?? 500);
    if ($limit < 1) $limit = 1;
    if ($limit > 2000) $limit = 2000;

    $stmt = $pdo->prepare("SELECT
        p.id,
        p.qr_code,
        p.first_name,
        p.last_name,
        p.county,
        p.participant_type,
        COALESCE(SUM(
            CASE
                WHEN ps.status = 'checked_out' AND ps.checkout_time IS NOT NULL THEN TIMESTAMPDIFF(SECOND, ps.checkin_time, ps.checkout_time)
                WHEN ps.status = 'checked_in' THEN TIMESTAMPDIFF(SECOND, ps.checkin_time, NOW())
                ELSE 0
            END
        ), 0) AS screenfree_seconds,
        MAX(CASE WHEN ps.status = 'checked_in' THEN 1 ELSE 0 END) AS checked_in
        FROM participants p
        LEFT JOIN phone_sessions ps ON ps.participant_id = p.id
        GROUP BY p.id, p.qr_code, p.first_name, p.last_name, p.county, p.participant_type
        HAVING checked_in = 1 OR screenfree_seconds > 0
        ORDER BY screenfree_seconds DESC, p.last_name ASC, p.first_name ASC
        LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll();
    foreach ($items as &$row) {
        $row['name'] = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
        $row['screenfree_seconds'] = (int)$row['screenfree_seconds'];
        $row['checked_in'] = ((int)$row['checked_in']) === 1;
    }

    out(['success' => true, 'items' => $items]);
}

if ($action === 'clear_screentime_log' && $method === 'POST') {
    // Clear historical screentime and reset ongoing timer for active check-ins.
    $pdo->beginTransaction();

    $resetStmt = $pdo->prepare("UPDATE phone_sessions SET checkin_time = NOW() WHERE status = 'checked_in'");
    $resetStmt->execute();
    $resetActive = $resetStmt->rowCount();

    $deleteStmt = $pdo->prepare("DELETE FROM phone_sessions WHERE status = 'checked_out'");
    $deleteStmt->execute();
    $deleted = $deleteStmt->rowCount();

    $pdo->commit();

    log_event($pdo, 'admin_clear_screentime', 'Skjermtidlogg tomt', [
        'deleted_sessions' => $deleted,
        'reset_active_sessions' => $resetActive,
    ]);

    out([
        'success' => true,
        'deleted_sessions' => $deleted,
        'reset_active_sessions' => $resetActive,
    ]);
}

if ($action === 'import_csv' && $method === 'POST') {
    $result = parse_csv_upload($pdo);
    if (!$result['ok']) {
        out(['success' => false, 'error' => $result['error']], 400);
    }

    log_event($pdo, 'admin_import_csv', 'CSV import gjennomfort', [
        'inserted' => $result['inserted'],
        'updated' => $result['updated']
    ]);

    out([
        'success' => true,
        'inserted' => $result['inserted'],
        'updated' => $result['updated']
    ]);
}

if ($action === 'digital_detox_report' && $method === 'GET') {
    $day = parse_day((string)($_GET['day'] ?? ''));
    $items = fetch_digital_detox_items($pdo, $day);
    $detoxDeadline = $day . ' 18:30:00';

    out([
        'success' => true,
        'day' => $day,
        'checkin_deadline' => $day . ' 09:30:00',
        'detox_deadline' => $detoxDeadline,
        'day_complete' => (gmdate('Y-m-d H:i:s') >= $detoxDeadline),
        'count' => count($items),
        'items' => $items,
    ]);
}

if ($action === 'digital_detox_print' && $method === 'POST') {
    $input = input_json();
    $day = parse_day((string)($input['day'] ?? ''));
    $script = __DIR__ . '/print_digital_detox.php';

    if (!is_file($script)) {
        out(['success' => false, 'error' => 'print_script_missing'], 500);
    }

    $phpBin = is_executable('/usr/bin/php') ? '/usr/bin/php' : 'php';
    $cmd = sprintf(
        '%s %s --day=%s 2>&1',
        escapeshellcmd($phpBin),
        escapeshellarg($script),
        escapeshellarg($day)
    );
    $output = [];
    $exit = 1;
    exec($cmd, $output, $exit);

    if ($exit !== 0) {
        log_event($pdo, 'digital_detox_print_error', 'Digital Detox utskrift feilet', [
            'day' => $day,
            'exit_code' => $exit,
            'output' => implode("\n", $output),
        ]);
        out(['success' => false, 'error' => 'print_failed', 'detail' => implode("\n", $output)], 500);
    }

    log_event($pdo, 'digital_detox_print', 'Digital Detox liste skrevet ut', [
        'day' => $day,
    ]);

    out(['success' => true, 'day' => $day]);
}

out(['success' => false, 'error' => 'unknown_action'], 404);
