<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'missing_id']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE phone_sessions
    SET checkout_time = NOW(),
        status = 'checked_out'
    WHERE id = ?
      AND status = 'checked_in'
");
$stmt->execute([$id]);

echo json_encode([
    'success' => $stmt->rowCount() > 0
]);
