<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function normalize_qr(string $value): string
{
    $value = trim($value);
    return str_replace(['+', '＋', '–', '—'], '-', $value);
}

function fail(string $error): void
{
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$pdo = db();
$qr = normalize_qr((string)($_GET['qr'] ?? ''));
$participantId = (int)($_GET['participant_id'] ?? 0);

if ($qr === '' && $participantId <= 0) {
    fail('missing_identifier');
}

try {
    $pdo->beginTransaction();

    if ($participantId > 0) {
        $pStmt = $pdo->prepare('SELECT id, qr_code, first_name, last_name FROM participants WHERE id = ? LIMIT 1');
        $pStmt->execute([$participantId]);
    } else {
        $pStmt = $pdo->prepare('SELECT id, qr_code, first_name, last_name FROM participants WHERE qr_code = ? LIMIT 1');
        $pStmt->execute([$qr]);
    }

    $participant = $pStmt->fetch();
    if (!$participant) {
        throw new RuntimeException('participant_not_found');
    }

    $activeStmt = $pdo->prepare("SELECT id, checkin_time
        FROM storage_sessions
        WHERE participant_id = ? AND status = 'checked_in'
        LIMIT 1
        FOR UPDATE");
    $activeStmt->execute([(int)$participant['id']]);
    $active = $activeStmt->fetch();

    $action = 'checked_in';
    $periodSeconds = 0;

    if ($active) {
        $action = 'checked_out';
        $update = $pdo->prepare("UPDATE storage_sessions
            SET checkout_time = NOW(), status = 'checked_out'
            WHERE id = ? AND status = 'checked_in'");
        $update->execute([(int)$active['id']]);

        if ($update->rowCount() <= 0) {
            throw new RuntimeException('session_not_checked_in');
        }

        $periodStmt = $pdo->prepare('SELECT COALESCE(TIMESTAMPDIFF(SECOND, checkin_time, checkout_time), 0) FROM storage_sessions WHERE id = ?');
        $periodStmt->execute([(int)$active['id']]);
        $periodSeconds = (int)$periodStmt->fetchColumn();
    } else {
        $insert = $pdo->prepare("INSERT INTO storage_sessions(participant_id, checkin_time, status)
            VALUES (?, NOW(), 'checked_in')");
        $insert->execute([(int)$participant['id']]);
    }

    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(
            CASE
                WHEN status = 'checked_out' AND checkout_time IS NOT NULL THEN TIMESTAMPDIFF(SECOND, checkin_time, checkout_time)
                WHEN status = 'checked_in' THEN TIMESTAMPDIFF(SECOND, checkin_time, NOW())
                ELSE 0
            END
        ), 0)
        FROM storage_sessions
        WHERE participant_id = ?");
    $totalStmt->execute([(int)$participant['id']]);
    $totalSeconds = (int)$totalStmt->fetchColumn();

    log_event($pdo, 'storage_toggle', $action === 'checked_in' ? 'Generell oppbevaring inn' : 'Generell oppbevaring ut', [
        'participant_id' => (int)$participant['id'],
        'qr' => (string)$participant['qr_code'],
        'action' => $action,
        'period_seconds' => $periodSeconds,
        'total_seconds' => $totalSeconds,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'action' => $action,
        'name' => trim((string)$participant['first_name'] . ' ' . (string)$participant['last_name']),
        'qr' => (string)$participant['qr_code'],
        'period_seconds' => $periodSeconds,
        'total_seconds' => $totalSeconds,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $known = ['participant_not_found', 'session_not_checked_in'];
    $msg = $e->getMessage();
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        $msg = 'session_conflict';
    }

    fail(in_array($msg, $known, true) ? $msg : 'server_error');
}
