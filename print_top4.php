<?php

declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\Printer;

const CUPS_PRINTER = 'CT-E351';
const PRINTER_DEVICE = '/dev/usb/lp0';

function parse_date_input(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return $fallback;
    }

    $y = (int)$m[1];
    $mo = (int)$m[2];
    $d = (int)$m[3];
    if (!checkdate($mo, $d, $y)) {
        return $fallback;
    }

    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

function parse_prize_scope(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === 'filter') {
        return 'filter';
    }

    return 'all';
}

function format_duration(int $seconds): string
{
    $hours = intdiv(max(0, $seconds), 3600);
    $minutes = intdiv(max(0, $seconds) % 3600, 60);
    return sprintf('%dt %02dm', $hours, $minutes);
}

function overlap_seconds(string $start, string $end, string $windowStart, string $windowEnd): int
{
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    $windowStartTs = strtotime($windowStart);
    $windowEndTs = strtotime($windowEnd);

    if ($startTs === false || $endTs === false || $windowStartTs === false || $windowEndTs === false) {
        return 0;
    }

    $fromTs = max($startTs, $windowStartTs);
    $toTs = min($endTs, $windowEndTs);

    return max(0, $toTs - $fromTs);
}

function overlap_for_period(string $start, string $end, string $fromDate, string $toDate): int
{
    $periodStart = $fromDate . ' 00:00:00';
    $periodEndExclusive = date('Y-m-d 00:00:00', strtotime($toDate . ' +1 day'));
    if ($periodEndExclusive === false) {
        return 0;
    }

    return overlap_seconds($start, $end, $periodStart, $periodEndExclusive);
}

function printer_destination(): string
{
    $env = trim((string)getenv('MOBILHOTELL_PRINTER'));
    return $env !== '' ? $env : CUPS_PRINTER;
}

function printer_device(): string
{
    $env = trim((string)getenv('MOBILHOTELL_PRINTER_DEVICE'));
    return $env !== '' ? $env : PRINTER_DEVICE;
}

function raw_device_available(string $path): bool
{
    return file_exists($path) && is_writable($path);
}

function create_print_connector(): PrintConnector
{
    $dest = printer_destination();
    $device = printer_device();
    if ($dest !== '') {
        try {
            return new CupsPrintConnector($dest);
        } catch (Throwable $e) {
            if (raw_device_available($device)) {
                return new FilePrintConnector($device);
            }
            throw new RuntimeException(
                "Unable to use CUPS printer '" . $dest . "': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    if (raw_device_available($device)) {
        return new FilePrintConnector($device);
    }

    throw new RuntimeException('No usable printer destination configured');
}

function redirect_back(string $fromDate, string $toDate, string $prizeScope, string $status, string $message = '', int $count = 0): void
{
    $query = [
        'from' => $fromDate,
        'to' => $toDate,
        'prize_scope' => $prizeScope,
        'print_top4_status' => $status,
        'print_top4_count' => max(0, $count),
    ];

    if ($message !== '') {
        $query['print_top4_message'] = $message;
    }

    header('Location: digital_detox_stats.php?' . http_build_query($query));
    exit;
}

function print_top4_list(Printer $printer, string $fromDate, string $toDate, array $items): void
{
    $printer->initialize();
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
    $printer->text("TOPP 4 SKJERMTID\n");
    $printer->selectPrintMode();
    $printer->text($fromDate . ' til ' . $toDate . "\n");
    $printer->text(str_repeat('-', 32) . "\n");
    $printer->setJustification(Printer::JUSTIFY_LEFT);

    if ($items === []) {
        $printer->text("Ingen kvalifiserte deltakere.\n");
    } else {
        foreach ($items as $index => $row) {
            $name = trim((string)($row['name'] ?? ''));
            $qr = trim((string)($row['qr_code'] ?? ''));
            $role = trim((string)($row['participant_type'] ?? ''));
            $totalSeconds = (int)($row['total_seconds'] ?? 0);
            $checkins = (int)($row['checkins'] ?? 0);
            $checkouts = (int)($row['checkouts'] ?? 0);

            $printer->text(sprintf('%d. %s', ((int)$index + 1), $name) . "\n");
            $printer->text('   QR: ' . ($qr !== '' ? $qr : '-') . "\n");
            $printer->text('   Rolle: ' . ($role !== '' ? $role : '-') . "\n");
            $printer->text('   Innleveringer: ' . $checkins . "\n");
            $printer->text('   Utleveringer: ' . $checkouts . "\n");
            $printer->text('   Skjermfri tid: ' . format_duration($totalSeconds) . "\n");
        }
    }

    $printer->text(str_repeat('-', 32) . "\n");
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->text('Antall: ' . count($items) . "\n\n");
    $printer->feed(4);
    $printer->cut();
}

$pdo = db();
$clock = $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m-%d') AS today_local, DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS now_local")->fetch();
$bounds = $pdo->query("SELECT DATE_FORMAT(MIN(checkin_time), '%Y-%m-%d') AS first_session_day FROM phone_sessions")->fetch();

$today = (string)($clock['today_local'] ?? date('Y-m-d'));
$now = (string)($clock['now_local'] ?? date('Y-m-d H:i:s'));
$firstSessionDay = (string)($bounds['first_session_day'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstSessionDay)) {
    $firstSessionDay = $today;
}

$fromDate = parse_date_input((string)($_GET['from'] ?? ''), $firstSessionDay);
$toDate = parse_date_input((string)($_GET['to'] ?? ''), $today);
$prizeScope = parse_prize_scope((string)($_GET['prize_scope'] ?? 'all'));

if ($fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
}

$prizeFromDate = $firstSessionDay;
$prizeToDate = $today;
if ($prizeScope === 'filter') {
    $prizeFromDate = $fromDate;
    $prizeToDate = $toDate;
}

$prizeRangeStart = $prizeFromDate . ' 00:00:00';
$prizeRangeEndExclusive = date('Y-m-d 00:00:00', strtotime($prizeToDate . ' +1 day'));
if ($prizeRangeEndExclusive === false) {
    redirect_back($fromDate, $toDate, $prizeScope, 'error', 'Ugyldig datoperiode.');
}

$stmt = $pdo->prepare("SELECT
    ps.participant_id,
    p.qr_code,
    p.first_name,
    p.last_name,
    p.participant_type,
    ps.checkin_time,
    ps.checkout_time
    FROM phone_sessions ps
    JOIN participants p ON p.id = ps.participant_id
    WHERE ps.checkin_time < ?
    AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
    ORDER BY ps.checkin_time DESC, ps.id DESC");
$stmt->execute([$prizeRangeEndExclusive, $prizeRangeStart]);
$rows = $stmt->fetchAll();

$leaderboardByParticipant = [];
foreach ($rows as $row) {
    $participantKey = (string)$row['participant_id'];
    if (!isset($leaderboardByParticipant[$participantKey])) {
        $leaderboardByParticipant[$participantKey] = [
            'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
            'qr_code' => (string)$row['qr_code'],
            'participant_type' => trim((string)$row['participant_type']) !== '' ? (string)$row['participant_type'] : 'Ukjent',
            'total_seconds' => 0,
            'checkins' => 0,
            'checkouts' => 0,
        ];
    }

    $effectiveEnd = (string)($row['checkout_time'] ?? '') !== '' ? (string)$row['checkout_time'] : $now;
    $leaderboardByParticipant[$participantKey]['checkins']++;
    if ((string)($row['checkout_time'] ?? '') !== '') {
        $leaderboardByParticipant[$participantKey]['checkouts']++;
    }
    $leaderboardByParticipant[$participantKey]['total_seconds'] += max(
        0,
        overlap_for_period((string)$row['checkin_time'], $effectiveEnd, $prizeFromDate, $prizeToDate)
    );
}

$leaderboard = array_values($leaderboardByParticipant);
usort($leaderboard, static function (array $a, array $b): int {
    if ((int)$a['total_seconds'] !== (int)$b['total_seconds']) {
        return ((int)$b['total_seconds']) <=> ((int)$a['total_seconds']);
    }

    return strcmp((string)$a['name'], (string)$b['name']);
});

$topPrizeCandidates = array_slice($leaderboard, 0, 4);

try {
    $connector = create_print_connector();
    $printer = new Printer($connector);

    try {
        print_top4_list($printer, $prizeFromDate, $prizeToDate, $topPrizeCandidates);
    } finally {
        $printer->close();
    }
} catch (Throwable $e) {
    redirect_back($fromDate, $toDate, $prizeScope, 'error', $e->getMessage());
}

redirect_back($fromDate, $toDate, $prizeScope, 'ok', '', count($topPrizeCandidates));
