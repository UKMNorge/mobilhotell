#!/usr/bin/env php
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

function parse_day(array $argv): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--day=')) {
            $value = trim(substr($arg, strlen('--day=')));
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1) {
                $y = (int)$m[1];
                $mo = (int)$m[2];
                $d = (int)$m[3];
                if (checkdate($mo, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
            }
        }
    }

    return gmdate('Y-m-d');
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

function fetch_digital_detox_items(PDO $pdo, string $day): array
{
    $dayStart = $day . ' 00:00:00';
    $nextDay = date('Y-m-d 00:00:00', strtotime($day . ' +1 day'));
    $checkinDeadline = $day . ' 09:30:00';
    $detoxDeadline = $day . ' 18:30:00';

    $stmt = $pdo->prepare("SELECT
        p.qr_code,
        p.first_name,
        p.last_name,
        p.phone_number,
        p.county,
        p.participant_type,
        MIN(CASE
            WHEN ps.checkin_time < ?
             AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
            THEN GREATEST(ps.checkin_time, ?)
            ELSE NULL
        END) AS first_checkin,
        MAX(CASE
            WHEN ps.checkin_time < ?
             AND ps.status = 'checked_out'
             AND ps.checkout_time >= ?
            THEN LEAST(ps.checkout_time, ?)
            ELSE NULL
        END) AS checkout_time
      FROM phone_sessions ps
      JOIN participants p ON p.id = ps.participant_id
      WHERE ps.checkin_time < ?
        AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
      GROUP BY p.id, p.qr_code, p.first_name, p.last_name, p.phone_number, p.county, p.participant_type
      HAVING
        MAX(CASE
            WHEN ps.checkin_time < ?
             AND (ps.checkout_time IS NULL OR ps.checkout_time >= ?)
            THEN 1 ELSE 0
        END) = 1
        AND MAX(CASE
            WHEN ps.checkin_time < ?
             AND (
                ps.status = 'checked_in'
                OR (ps.status = 'checked_out' AND ps.checkout_time >= ?)
             )
            THEN 1 ELSE 0
        END) = 1
      ORDER BY p.last_name ASC, p.first_name ASC");
    $stmt->execute([
        $checkinDeadline,
        $checkinDeadline,
        $checkinDeadline,
        $checkinDeadline,
        $detoxDeadline,
        $detoxDeadline,
        $nextDay,
        $dayStart,
        $checkinDeadline,
        $checkinDeadline,
        $checkinDeadline,
        $detoxDeadline,
    ]);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['name'] = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
    }

    return $rows;
}

function print_list(Printer $printer, string $day, array $items): void
{
    $printer->initialize();
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
    $printer->text("DIGITAL DETOX\n");
    $printer->selectPrintMode();
    $printer->text('Dag: ' . $day . "\n");
    $printer->text("Innlevert for 09:30\n");
    $printer->text("Beholdt til minst 18:30\n");
    $printer->text(str_repeat('-', 32) . "\n");
    $printer->setJustification(Printer::JUSTIFY_LEFT);

    if (!$items) {
      $printer->text("Ingen har klart Digital Detox enda.\n");
    } else {
      $i = 1;
      foreach ($items as $row) {
        $name = trim((string)$row['name']);
        $qr = trim((string)$row['qr_code']);
                $phone = trim((string)($row['phone_number'] ?? ''));
                $county = trim((string)($row['county'] ?? ''));
                $role = trim((string)($row['participant_type'] ?? ''));
        $printer->text(sprintf('%02d. %s', $i, $name) . "\n");
        $printer->text('    QR: ' . $qr . "\n");
                $printer->text('    Telefon: ' . ($phone !== '' ? $phone : '-') . "\n");
                $printer->text('    Fylke: ' . ($county !== '' ? $county : '-') . "\n");
                $printer->text('    Funksjon: ' . ($role !== '' ? $role : '-') . "\n");
        $i++;
      }
    }

    $printer->text(str_repeat('-', 32) . "\n");
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->text('Antall: ' . count($items) . "\n\n");
    $printer->feed(4);
    $printer->cut();
  }

$day = parse_day($argv);
$pdo = db();
$items = fetch_digital_detox_items($pdo, $day);

$connector = create_print_connector();
$printer = new Printer($connector);

try {
    print_list($printer, $day, $items);
} finally {
    $printer->close();
}

echo 'Printed Digital Detox list for ' . $day . ' (' . count($items) . ')' . "\n";
