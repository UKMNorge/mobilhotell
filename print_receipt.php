#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

const PRINTER_DEVICE = '/dev/usb/lp0';
const LOGO_PATH = '/var/www/mobilhotell/assets/UKM Logo Sort RGB.png';
const LOGO_MAX_WIDTH = 250;
const RECEIPT_WIDTH = 576;
const RECEIPT_PADDING = 10;
const RECEIPT_GAP = 14;
const QR_SIZE = 210;
const RECEIPT_MAX_HEIGHT = 760;

function usage(): void
{
    fwrite(STDERR, "Usage: php print_receipt.php --session-id=123\n");
}

function parse_session_id(array $argv): ?int
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--session-id=')) {
            $value = substr($arg, strlen('--session-id='));
            if ($value !== '' && ctype_digit($value)) {
                return (int)$value;
            }
        }
    }
    return null;
}

function fetch_receipt_data(PDO $pdo, int $sessionId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            p.first_name,
            p.last_name,
            p.qr_code,
            s.slot_number,
            ps.checkin_time,
            ps.delivery_type
         FROM phone_sessions ps
         JOIN participants p ON p.id = ps.participant_id
         JOIN slots s ON s.id = ps.slot_id
         WHERE ps.id = ?
         LIMIT 1"
    );
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        'qr' => (string)$row['qr_code'],
        'slot' => (string)$row['slot_number'],
        'time' => (string)($row['checkin_time'] ?? ''),
        'type' => ((string)$row['delivery_type'] === 'charging') ? 'Lading' : 'Oppbevaring',
    ];
}

function print_logo(Printer $printer): void
{
    if (!is_file(LOGO_PATH)) {
        return;
    }

    try {
        $prepared = prepare_logo_image(LOGO_PATH);
        $logo = EscposImage::load($prepared, false);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        // bitImage keeps payload smaller than full-page graphics raster.
        $printer->bitImage($logo);
        $printer->feed();
        @unlink($prepared);
    } catch (Throwable) {
        // Continue without logo if image conversion fails for any reason.
    }
}

function font_path(): ?string
{
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function text_width_ttf(string $fontPath, int $size, string $text): int
{
    $box = imagettfbbox($size, 0, $fontPath, $text);
    if (!is_array($box)) {
        return 0;
    }
    return (int)abs($box[2] - $box[0]);
}

function wrap_text_ttf(string $fontPath, int $size, string $text, int $maxWidth): array
{
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    if (!$words) {
        return [''];
    }

    $lines = [];
    $line = (string)array_shift($words);
    foreach ($words as $word) {
        $candidate = $line . ' ' . $word;
        if (text_width_ttf($fontPath, $size, $candidate) <= $maxWidth) {
            $line = $candidate;
            continue;
        }
        $lines[] = $line;
        $line = $word;
    }
    $lines[] = $line;
    return $lines;
}

function make_qr_image(string $text): GdImage
{
    $options = new QROptions([
        'outputInterface' => QRGdImagePNG::class,
        'eccLevel' => EccLevel::M,
        'scale' => 8,
        'imageBase64' => false,
        'returnResource' => false,
    ]);
    $rendered = (new QRCode($options))->render($text);

    if ($rendered instanceof GdImage) {
        return $rendered;
    }

    if (is_string($rendered)) {
        if (str_starts_with($rendered, 'data:image/')) {
            $comma = strpos($rendered, ',');
            if ($comma !== false) {
                $rendered = base64_decode(substr($rendered, $comma + 1), true) ?: '';
            }
        }
        $qr = @imagecreatefromstring($rendered);
        if ($qr instanceof GdImage) {
            return $qr;
        }
    }

    throw new RuntimeException('Could not generate QR image');
}

function resize_image(GdImage $src, int $targetW, int $targetH): GdImage
{
    $dst = imagecreatetruecolor($targetW, $targetH);
    if (!$dst) {
        throw new RuntimeException('Could not allocate resized image');
    }
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, imagesx($src), imagesy($src));
    return $dst;
}

function prepare_logo_bitmap(?string $fontPath = null): ?GdImage
{
    if (!is_file(LOGO_PATH)) {
        return null;
    }

    $src = @imagecreatefrompng(LOGO_PATH);
    if (!$src) {
        return null;
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $dstW = min(LOGO_MAX_WIDTH, $srcW);
    $scale = $srcW > 0 ? ($dstW / $srcW) : 1.0;
    $dstH = max(1, (int)round($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    if (!$dst) {
        imagedestroy($src);
        return null;
    }

    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagefilter($dst, IMG_FILTER_GRAYSCALE);
    imagefilter($dst, IMG_FILTER_CONTRAST, -20);
    imagedestroy($src);
    return $dst;
}

function save_receipt_bitmap(array $data): string
{
    $canvas = imagecreatetruecolor(RECEIPT_WIDTH, RECEIPT_MAX_HEIGHT);
    if (!$canvas) {
        throw new RuntimeException('Could not allocate receipt canvas');
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 0, 0, 0);
    imagefilledrectangle($canvas, 0, 0, RECEIPT_WIDTH, RECEIPT_MAX_HEIGHT, $white);

    $font = font_path();
    if ($font === null) {
        imagedestroy($canvas);
        throw new RuntimeException('No TTF font available');
    }

    $baseSize = 19;
    $slotSize = 23;
    $boldSize = 26;
    $smallSize = 14;

    $y = 2;
    $logo = prepare_logo_bitmap();
    if ($logo !== null) {
        $lx = (int)((RECEIPT_WIDTH - imagesx($logo)) / 2);
        imagecopy($canvas, $logo, $lx, $y, 0, 0, imagesx($logo), imagesy($logo));
        $y += imagesy($logo) + 3;
        imagedestroy($logo);
    }

    imagettftext($canvas, $boldSize, 0, RECEIPT_PADDING, $y + 18, $black, $font, 'Din mobil er trygg');
    imagettftext($canvas, $smallSize, 0, RECEIPT_PADDING, $y + 36, $black, $font, 'Vis denne ved utlevering');
    $y += 44;
    imageline($canvas, RECEIPT_PADDING, $y, RECEIPT_WIDTH - RECEIPT_PADDING, $y, $black);
    $y += 8;

    $qrX = RECEIPT_WIDTH - RECEIPT_PADDING - QR_SIZE;
    $leftX = RECEIPT_PADDING;
    $leftWidth = $qrX - $leftX - RECEIPT_GAP;
    $lineHeight = 24;
    $slotLineHeight = 28;

    $raw = [
        'Navn: ' . ($data['name'] !== '' ? $data['name'] : 'Ukjent deltaker'),
        'Type: ' . $data['type'],
        'Slot: ' . $data['slot'],
        'Tid: ' . $data['time'],
    ];

    $lines = [];
    foreach ($raw as $row) {
        if ($row === '') {
            $lines[] = '';
            continue;
        }
        foreach (wrap_text_ttf($font, $baseSize, $row, $leftWidth) as $line) {
            $lines[] = $line;
        }
    }

    $textY = $y;
    foreach ($lines as $line) {
        $isSlotLine = str_starts_with($line, 'Slot:');
        if ($isSlotLine) {
            $textY += 6;
        }
        if ($line !== '') {
            $fontSize = $isSlotLine ? $slotSize : $baseSize;
            $baseline = $isSlotLine ? ($textY + 25) : ($textY + 24);
            imagettftext($canvas, $fontSize, 0, $leftX, $baseline, $black, $font, $line);
        }
        $textY += ($isSlotLine ? $slotLineHeight : $lineHeight);
    }

    $qrText = $data['qr'] !== '' ? $data['qr'] : 'N/A';
    $qr = make_qr_image($qrText);
    $qrScaled = resize_image($qr, QR_SIZE, QR_SIZE);
    imagedestroy($qr);
    imagecopy($canvas, $qrScaled, $qrX, $y, 0, 0, QR_SIZE, QR_SIZE);
    imagedestroy($qrScaled);
    imagettftext($canvas, $smallSize, 0, $qrX + 6, $y + QR_SIZE + 18, $black, $font, $qrText);

    $bottom = max($textY, $y + QR_SIZE + 24) + 2;
    $height = min(RECEIPT_MAX_HEIGHT, $bottom);

    $final = imagecreatetruecolor(RECEIPT_WIDTH, $height);
    if (!$final) {
        imagedestroy($canvas);
        throw new RuntimeException('Could not allocate final bitmap');
    }
    imagefilledrectangle($final, 0, 0, RECEIPT_WIDTH, $height, $white);
    imagecopy($final, $canvas, 0, 0, 0, 0, RECEIPT_WIDTH, $height);
    imagedestroy($canvas);

    imagefilter($final, IMG_FILTER_GRAYSCALE);
    imagefilter($final, IMG_FILTER_CONTRAST, -20);

    $tmp = tempnam(sys_get_temp_dir(), 'mh_receipt_');
    if ($tmp === false) {
        imagedestroy($final);
        throw new RuntimeException('Could not create temporary output file');
    }
    $tmpPng = $tmp . '.png';
    @unlink($tmp);
    imagepng($final, $tmpPng, 9);
    imagedestroy($final);
    return $tmpPng;
}

function prepare_logo_image(string $source): string
{
    $img = @imagecreatefrompng($source);
    if (!$img) {
        throw new RuntimeException('Could not open logo image');
    }

    $srcW = imagesx($img);
    $srcH = imagesy($img);
    $dstW = min(LOGO_MAX_WIDTH, $srcW);
    $scale = $srcW > 0 ? ($dstW / $srcW) : 1.0;
    $dstH = max(1, (int)round($srcH * $scale));

    $canvas = imagecreatetruecolor($dstW, $dstH);
    if (!$canvas) {
        imagedestroy($img);
        throw new RuntimeException('Could not allocate image canvas');
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $white);
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

    imagefilter($canvas, IMG_FILTER_GRAYSCALE);
    imagefilter($canvas, IMG_FILTER_CONTRAST, -20);

    $tmp = tempnam(sys_get_temp_dir(), 'mh_logo_');
    if ($tmp === false) {
        imagedestroy($img);
        imagedestroy($canvas);
        throw new RuntimeException('Could not create temporary file');
    }

    $tmpPng = $tmp . '.png';
    @unlink($tmp);
    imagepng($canvas, $tmpPng, 9);

    imagedestroy($img);
    imagedestroy($canvas);
    return $tmpPng;
}

function print_receipt(Printer $printer, array $data): void
{
    $printer->initialize();
    $bitmapPath = save_receipt_bitmap($data);
    $img = EscposImage::load($bitmapPath, false);
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->bitImage($img);
    @unlink($bitmapPath);
    $printer->cut(Printer::CUT_FULL, 0);
}

function main(array $argv): int
{
    $sessionId = parse_session_id($argv);
    if ($sessionId === null) {
        usage();
        return 2;
    }

    $pdo = db();
    $data = fetch_receipt_data($pdo, $sessionId);
    if ($data === null) {
        fwrite(STDERR, "Session not found\n");
        return 3;
    }

    $printer = null;
    try {
        // Convert runtime write warnings/notices into exceptions, but ignore deprecations from vendor code.
        set_error_handler(static function (int $severity, string $message): bool {
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                return false;
            }
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE);
        $connector = new FilePrintConnector(PRINTER_DEVICE);
        $printer = new Printer($connector);
        print_receipt($printer, $data);
    } catch (Throwable $e) {
        fwrite(STDERR, "Unable to print: " . $e->getMessage() . "\n");
        return 4;
    } finally {
        if ($printer instanceof Printer) {
            try {
                $printer->close();
            } catch (Throwable) {
            }
        }
        restore_error_handler();
    }

    return 0;
}

exit(main($argv));
