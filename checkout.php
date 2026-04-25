<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

if ($id <= 0 && $token === '') {
    echo json_encode(['success' => false, 'error' => 'missing_identifier']);
    exit;
}

$stmt = null;
if ($id > 0) {
    $stmt = $pdo->prepare("
        UPDATE phone_sessions
        SET checkout_time = NOW(),
            status = 'checked_out'
        WHERE id = ?
          AND status = 'checked_in'
    ");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("
        UPDATE phone_sessions
        SET checkout_time = NOW(),
            status = 'checked_out'
        WHERE session_token = ?
          AND status = 'checked_in'
    ");
    $stmt->execute([$token]);
}

$ok = $stmt->rowCount() > 0;

echo json_encode([
    'success' => $ok,
    'error' => $ok ? null : 'session_not_checked_in'
]);
