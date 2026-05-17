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
    $inserted = 0;
    $updated = 0;

    $select = $pdo->prepare('SELECT id FROM participants WHERE qr_code = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO participants(qr_code, first_name, last_name, county, participant_type, image_path) VALUES (?, ?, ?, ?, ?, ?)');
    $update = $pdo->prepare('UPDATE participants SET first_name = ?, last_name = ?, county = ?, participant_type = ?, image_path = ? WHERE id = ?');

    while (($row = fgetcsv($handle)) !== false) {
        $qr = trim((string)($row[$idx['qr_code']] ?? ''));
        $first = trim((string)($row[$idx['first_name']] ?? ''));
        $last = trim((string)($row[$idx['last_name']] ?? ''));
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
            $update->execute([$first, $last, $county, $ptype, $image, (int)$existing['id']]);
            $updated++;
        } else {
            $insert->execute([$qr, $first, $last, $county, $ptype, $image]);
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
    $slotsTotal = (int)$pdo->query('SELECT COUNT(*) FROM slots')->fetchColumn();
    $slotsActive = (int)$pdo->query('SELECT COUNT(*) FROM slots WHERE is_active = 1')->fetchColumn();
    $slotsBusy = (int)$pdo->query("SELECT COUNT(*) FROM slots s WHERE EXISTS (SELECT 1 FROM phone_sessions ps WHERE ps.slot_id = s.id AND ps.status = 'checked_in')")->fetchColumn();
    $participants = (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();

    out([
        'success' => true,
        'summary' => [
            'active_checkins' => $active,
            'slots_total' => $slotsTotal,
            'slots_active' => $slotsActive,
            'slots_busy' => $slotsBusy,
            'slots_free_active' => max(0, $slotsActive - $slotsBusy),
            'participants_total' => $participants,
            'server_time' => gmdate('Y-m-d H:i:s')
        ]
    ]);
}

if ($action === 'capacity' && $method === 'GET') {
    $targets = [
        'storage' => 180,
        'charging' => 120,
    ];

    $occupiedStmt = $pdo->query("SELECT s.slot_type, COUNT(*) AS c
        FROM phone_sessions ps
        JOIN slots s ON s.id = ps.slot_id
        WHERE ps.status = 'checked_in'
        GROUP BY s.slot_type");
    $occupiedMap = ['storage' => 0, 'charging' => 0];
    foreach ($occupiedStmt->fetchAll() as $row) {
        $t = (string)$row['slot_type'];
        if (isset($occupiedMap[$t])) {
            $occupiedMap[$t] = (int)$row['c'];
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
            'charging' => $byType['charging'],
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

out(['success' => false, 'error' => 'unknown_action'], 404);
