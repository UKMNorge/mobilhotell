<?php

declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/usb_status_sync.php';

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
        VALUES (?, ?, ?, " . db_now_expr($pdo) . ", 'checked_in', ?)");
    $insert->execute([(int)$participant['id'], (int)$slot['id'], $type, $token]);

    $sessionId = (int)$pdo->lastInsertId();
    $pdo->commit();

    // Non-blocking direct print from check-in flow with lock/timeout.
    $printScript = __DIR__ . '/print_receipt.php';
    $printStarted = false;
    if (is_file($printScript)) {
        $phpBin = is_executable('/usr/bin/php') ? '/usr/bin/php' : 'php';
        $flockBin = is_executable('/usr/bin/flock') ? '/usr/bin/flock' : 'flock';
        $timeoutBin = is_executable('/usr/bin/timeout') ? '/usr/bin/timeout' : 'timeout';
        $logFile = __DIR__ . '/data/print.log';
        $lockFile = __DIR__ . '/data/print.lock';

        if (!is_file($lockFile)) {
            @touch($lockFile);
        }
        if (!is_file($logFile)) {
            @touch($logFile);
        }
        @chmod($lockFile, 0666);
        @chmod($logFile, 0666);

        $inner = sprintf(
            '%s -w 8 %s %s 20s %s %s --session-id=%d',
            escapeshellcmd($flockBin),
            escapeshellarg($lockFile),
            escapeshellcmd($timeoutBin),
            escapeshellcmd($phpBin),
            escapeshellarg($printScript),
            $sessionId
        );
        $cmd = 'nohup /bin/sh -c ' . escapeshellarg($inner) . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
        $out = [];
        $code = 1;
        exec($cmd, $out, $code);
        $printStarted = ($code === 0);

        if (!$printStarted) {
            try {
                log_event($pdo, 'print_enqueue_error', 'Klarte ikke starte utskrift', [
                    'session_id' => $sessionId,
                    'cmd' => $cmd,
                    'exit_code' => $code,
                ]);
            } catch (Throwable) {
                // Logging must not break the check-in response path.
            }
        }
    }

    try {
        log_event($pdo, 'checkin', 'Telefon innlevert', [
            'session_id' => $sessionId,
            'participant_id' => (int)$participant['id'],
            'qr' => $participant['qr_code'],
            'slot' => $slot['slot_number'],
            'type' => $type,
            'print_started' => $printStarted
        ]);
    } catch (Throwable) {
        // Logging must not break the check-in response path.
    }

    try {
        usb_status_sync($pdo);
    } catch (Throwable) {
        // USB sync is best-effort and must not break check-in response.
    }

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
    try {
        log_event($pdo, 'checkin_error', 'Innsjekk feilet', [
            'qr' => $qr,
            'type' => $type,
            'error' => in_array($msg, $known, true) ? $msg : 'server_error'
        ]);
    } catch (Throwable) {
        // Logging must not break the check-in response path.
    }
    fail(in_array($msg, $known, true) ? $msg : 'server_error');
}
