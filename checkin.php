<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json');

$qr = trim($_GET['qr'] ?? '');
$type = $_GET['type'] ?? '';

if ($qr === '' || !in_array($type, ['storage', 'charging'], true)) {
    echo json_encode(['success' => false, 'error' => 'bad_request']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM participants WHERE qr_code = ? LIMIT 1");
    $stmt->execute([$qr]);
    $p = $stmt->fetch();

    if (!$p) {
        throw new Exception('participant_not_found');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM phone_sessions
        WHERE participant_id = ?
          AND status = 'checked_in'
        LIMIT 1
    ");
    $stmt->execute([$p['id']]);

    if ($stmt->fetch()) {
        throw new Exception('already_checked_in');
    }

    $stmt = $pdo->prepare("
        SELECT s.id, s.slot_number
        FROM slots s
        WHERE s.slot_type = ?
          AND s.is_active = 1
          AND NOT EXISTS (
              SELECT 1
              FROM phone_sessions ps
              WHERE ps.slot_id = s.id
                AND ps.status = 'checked_in'
          )
        ORDER BY s.slot_number
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$type]);
    $slot = $stmt->fetch();

    if (!$slot) {
        throw new Exception('no_free_slot');
    }

    $stmt = $pdo->prepare("
        INSERT INTO phone_sessions
        (participant_id, slot_id, delivery_type, checkin_time, status)
        VALUES (?, ?, ?, NOW(), 'checked_in')
    ");
    $stmt->execute([$p['id'], $slot['id'], $type]);

    $sessionId = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'slot' => $slot['slot_number'],
        'name' => $p['first_name'] . ' ' . $p['last_name'],
        'type' => $type
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
