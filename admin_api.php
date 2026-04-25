<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function read_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function list_active_checkins(PDO $pdo, string $search): array
{
    $sql = "
        SELECT
            ps.id AS session_id,
            s.id AS slot_id,
            s.slot_number,
            s.slot_type,
            p.qr_code,
            p.first_name,
            p.last_name,
            ps.checkin_time
        FROM phone_sessions ps
        INNER JOIN participants p ON p.id = ps.participant_id
        INNER JOIN slots s ON s.id = ps.slot_id
        WHERE ps.status = 'checked_in'
    ";

    $params = [];
    if ($search !== '') {
        $sql .= "
            AND (
                p.qr_code LIKE ?
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?
                OR CONCAT(p.last_name, ' ', p.first_name) LIKE ?
            )
        ";
        $needle = '%' . $search . '%';
        $params = [$needle, $needle, $needle];
    }

    $sql .= " ORDER BY ps.checkin_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    }

    return $rows;
}

function slot_grid(PDO $pdo): array
{
    $stmt = $pdo->query(" 
        SELECT
            s.id AS slot_id,
            s.slot_number,
            s.slot_type,
            s.is_active,
            ps.id AS session_id,
            p.qr_code,
            p.first_name,
            p.last_name
        FROM slots s
        LEFT JOIN phone_sessions ps
            ON ps.slot_id = s.id
           AND ps.status = 'checked_in'
        LEFT JOIN participants p
            ON p.id = ps.participant_id
        ORDER BY s.slot_number ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $status = 'free';
        if ((int)$row['is_active'] !== 1) {
            $status = 'disabled';
        } elseif (!empty($row['session_id'])) {
            $status = 'busy';
        }

        $row['status'] = $status;
        $row['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }

    return $rows;
}

function slot_detail(PDO $pdo, string $slotNumber): ?array
{
    $stmt = $pdo->prepare(" 
        SELECT
            s.id AS slot_id,
            s.slot_number,
            s.slot_type,
            s.is_active,
            ps.id AS session_id,
            ps.checkin_time,
            p.qr_code,
            p.first_name,
            p.last_name,
            p.county,
            p.participant_type
        FROM slots s
        LEFT JOIN phone_sessions ps
            ON ps.slot_id = s.id
           AND ps.status = 'checked_in'
        LEFT JOIN participants p
            ON p.id = ps.participant_id
        WHERE s.slot_number = ?
        LIMIT 1
    ");
    $stmt->execute([$slotNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $row['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if ((int)$row['is_active'] !== 1) {
        $row['status'] = 'disabled';
    } elseif (!empty($row['session_id'])) {
        $row['status'] = 'busy';
    } else {
        $row['status'] = 'free';
    }

    return $row;
}

function manual_checkout(PDO $pdo, int $sessionId): bool
{
    $stmt = $pdo->prepare(" 
        UPDATE phone_sessions
        SET checkout_time = NOW(),
            status = 'checked_out'
        WHERE id = ?
          AND status = 'checked_in'
    ");
    $stmt->execute([$sessionId]);

    return $stmt->rowCount() > 0;
}

function set_slot_active(PDO $pdo, int $slotId, int $isActive): bool
{
    $stmt = $pdo->prepare(" 
        UPDATE slots
        SET is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([$isActive, $slotId]);

    return $stmt->rowCount() > 0;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = read_input();

try {
    if ($action === 'active_list' && $method === 'GET') {
        $q = trim($_GET['q'] ?? '');
        json_response([
            'success' => true,
            'items' => list_active_checkins($pdo, $q)
        ]);
    }

    if ($action === 'slot_grid' && $method === 'GET') {
        json_response([
            'success' => true,
            'items' => slot_grid($pdo)
        ]);
    }

    if ($action === 'slot_detail' && $method === 'GET') {
        $slotNumber = trim($_GET['slot_number'] ?? '');
        if ($slotNumber === '') {
            json_response(['success' => false, 'error' => 'missing_slot_number'], 400);
        }

        $slot = slot_detail($pdo, $slotNumber);
        if (!$slot) {
            json_response(['success' => false, 'error' => 'slot_not_found'], 404);
        }

        json_response(['success' => true, 'slot' => $slot]);
    }

    if ($action === 'manual_checkout' && $method === 'POST') {
        $sessionId = (int)($input['session_id'] ?? ($_POST['session_id'] ?? 0));
        if ($sessionId <= 0) {
            json_response(['success' => false, 'error' => 'missing_session_id'], 400);
        }

        $ok = manual_checkout($pdo, $sessionId);
        json_response(['success' => $ok, 'error' => $ok ? null : 'session_not_checked_in']);
    }

    if ($action === 'set_slot_active' && $method === 'POST') {
        $slotId = (int)($input['slot_id'] ?? ($_POST['slot_id'] ?? 0));
        $isActive = (int)($input['is_active'] ?? ($_POST['is_active'] ?? 0));

        if ($slotId <= 0 || !in_array($isActive, [0, 1], true)) {
            json_response(['success' => false, 'error' => 'bad_request'], 400);
        }

        $ok = set_slot_active($pdo, $slotId, $isActive);
        json_response(['success' => $ok, 'error' => $ok ? null : 'slot_not_found']);
    }

    json_response(['success' => false, 'error' => 'unknown_action'], 404);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'error' => 'server_error',
        'message' => $e->getMessage()
    ], 500);
}
