<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json');

function fail(string $error): void
{
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$pdo = db();
$qr = trim((string)($_GET['qr'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));

if ($qr === '' || !in_array($type, ['storage', 'charging'], true)) {
    fail('bad_request');
}

try {
    $pdo->beginTransaction();

    $pStmt = $pdo->prepare('SELECT * FROM participants WHERE qr_code = ? LIMIT 1');
    $pStmt->execute([$qr]);
    $participant = $pStmt->fetch();
    if (!$participant) {
        throw new RuntimeException('participant_not_found');
    }

    $activeStmt = $pdo->prepare("SELECT id FROM phone_sessions WHERE participant_id = ? AND status = 'checked_in' LIMIT 1");
    $activeStmt->execute([(int)$participant['id']]);
    if ($activeStmt->fetch()) {
        throw new RuntimeException('already_checked_in');
    }

    $slotStmt = $pdo->prepare("SELECT s.id, s.slot_number
        FROM slots s
        WHERE s.slot_type = ?
          AND s.is_active = 1
          AND NOT EXISTS (
            SELECT 1 FROM phone_sessions ps WHERE ps.slot_id = s.id AND ps.status = 'checked_in'
          )
        ORDER BY s.slot_number
        LIMIT 1");
    $slotStmt->execute([$type]);
    $slot = $slotStmt->fetch();
    if (!$slot) {
        throw new RuntimeException('no_free_slot');
    }

    $token = bin2hex(random_bytes(16));
    $insert = $pdo->prepare("INSERT INTO phone_sessions(participant_id, slot_id, delivery_type, checkin_time, status, session_token)
        VALUES (?, ?, ?, datetime('now'), 'checked_in', ?)");
    $insert->execute([(int)$participant['id'], (int)$slot['id'], $type, $token]);

    $sessionId = (int)$pdo->lastInsertId();
    $pdo->commit();

    $checkoutUrl = 'checkout.php?token=' . rawurlencode($token);

    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'slot' => $slot['slot_number'],
        'name' => trim($participant['first_name'] . ' ' . $participant['last_name']),
        'type' => $type,
        'checked_in_at' => gmdate('Y-m-d H:i:s'),
        'session_token' => $token,
        'checkout_url' => $checkoutUrl,
        'checkout_qr_text' => $checkoutUrl
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $known = ['participant_not_found', 'already_checked_in', 'no_free_slot'];
    $msg = $e->getMessage();
    fail(in_array($msg, $known, true) ? $msg : 'server_error');
}
