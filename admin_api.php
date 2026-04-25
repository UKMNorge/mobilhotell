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
                OR lower(p.first_name || ' ' || p.last_name) LIKE ?
                OR lower(p.last_name || ' ' || p.first_name) LIKE ?
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
        SET checkout_time = datetime('now'), status = 'checked_out'
        WHERE id = ? AND status = 'checked_in'");
    $stmt->execute([$sessionId]);

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

    out(['success' => $stmt->rowCount() > 0]);
}

out(['success' => false, 'error' => 'unknown_action'], 404);
