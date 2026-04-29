<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json');

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($id <= 0 && $token === '') {
    echo json_encode(['success' => false, 'error' => 'missing_identifier']);
    exit;
}

if ($id > 0) {
    $find = $pdo->prepare("SELECT ps.id, ps.participant_id, p.first_name, p.last_name
        FROM phone_sessions ps
        JOIN participants p ON p.id = ps.participant_id
        WHERE ps.id = ? AND ps.status = 'checked_in'
        LIMIT 1");
    $find->execute([$id]);
} else {
    $find = $pdo->prepare("SELECT ps.id, ps.participant_id, p.first_name, p.last_name
        FROM phone_sessions ps
        JOIN participants p ON p.id = ps.participant_id
        WHERE ps.session_token = ? AND ps.status = 'checked_in'
        LIMIT 1");
    $find->execute([$token]);
}

$session = $find->fetch();
if (!$session) {
    echo json_encode(['success' => false, 'error' => 'session_not_checked_in']);
    exit;
}

$update = $pdo->prepare("UPDATE phone_sessions
    SET checkout_time = datetime('now'), status = 'checked_out'
    WHERE id = ? AND status = 'checked_in'");
$update->execute([(int)$session['id']]);

if ($update->rowCount() <= 0) {
    echo json_encode(['success' => false, 'error' => 'session_not_checked_in']);
    exit;
}

$sum = $pdo->prepare("SELECT COALESCE(SUM(strftime('%s', checkout_time) - strftime('%s', checkin_time)), 0) AS seconds
    FROM phone_sessions
    WHERE participant_id = ?
      AND checkout_time IS NOT NULL");
$sum->execute([(int)$session['participant_id']]);
$seconds = (int)$sum->fetchColumn();

log_event($pdo, 'checkout', 'Telefon utlevert', [
    'session_id' => (int)$session['id'],
    'participant_id' => (int)$session['participant_id'],
    'name' => trim((string)$session['first_name'] . ' ' . (string)$session['last_name']),
    'screenfree_seconds' => $seconds
]);

echo json_encode([
    'success' => true,
    'error' => null,
    'name' => trim((string)$session['first_name'] . ' ' . (string)$session['last_name']),
    'screenfree_seconds' => $seconds
]);
