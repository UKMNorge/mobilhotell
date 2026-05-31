<?php

declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

const DEFAULT_LABEL_PRINTER = 'DYMO-LabelWriter-400';
const DEFAULT_LABEL_MEDIA = 'w162h288';
const LABEL_WIDTH = 1280;
const LABEL_HEIGHT = 720;
const LABEL_PADDING = 18;
const LABEL_GAP = 18;

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function get_font_path(): ?string
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

    throw new RuntimeException('qr_render_failed');
}

function text_width(string $font, int $size, string $text): int
{
    $box = imagettfbbox($size, 0, $font, $text);
    if (!is_array($box)) {
        return 0;
    }
    return (int)abs($box[2] - $box[0]);
}

function wrap_lines(string $font, int $size, string $text, int $maxWidth, int $maxLines = 2): array
{
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    if (!$words) {
        return [''];
    }

    $lines = [];
    $line = (string)array_shift($words);
    foreach ($words as $word) {
        $candidate = $line . ' ' . $word;
        if (text_width($font, $size, $candidate) <= $maxWidth) {
            $line = $candidate;
            continue;
        }
        $lines[] = $line;
        $line = $word;
        if (count($lines) >= $maxLines - 1) {
            break;
        }
    }
    $lines[] = $line;

    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
    }

    while (count($lines) < $maxLines) {
        $lines[] = '';
    }

    return $lines;
}

function fit_text_size(string $font, string $text, int $maxWidth, int $startSize, int $minSize): int
{
    $size = $startSize;
    while ($size > $minSize && text_width($font, $size, $text) > $maxWidth) {
        $size--;
    }
    return $size;
}

function draw_label(array $participant): string
{
    $font = get_font_path();
    if ($font === null) {
        throw new RuntimeException('font_not_found');
    }

    $label = imagecreatetruecolor(LABEL_WIDTH, LABEL_HEIGHT);
    if (!$label) {
        throw new RuntimeException('label_allocate_failed');
    }

    $white = imagecolorallocate($label, 255, 255, 255);
    $black = imagecolorallocate($label, 0, 0, 0);
    $gray = imagecolorallocate($label, 90, 90, 90);

    imagefilledrectangle($label, 0, 0, LABEL_WIDTH, LABEL_HEIGHT, $white);

    $qrSize = LABEL_HEIGHT - (LABEL_PADDING * 2);
    $qrX = LABEL_WIDTH - LABEL_PADDING - $qrSize;
    $qrY = LABEL_PADDING;
    $textX = LABEL_PADDING;
    $textW = $qrX - LABEL_GAP - LABEL_PADDING;

    // Large section marker fills the left header zone and makes the print use the full label visually.
    imagefilledrectangle($label, $textX, LABEL_PADDING, $qrX - LABEL_GAP, LABEL_PADDING + 110, $black);
    $headerText = 'GENERELL OPPBEVARING';
    $headerSize = fit_text_size($font, $headerText, $textW - 24, 44, 28);
    imagettftext($label, $headerSize, 0, $textX + 12, LABEL_PADDING + 78, $white, $font, $headerText);

    $qr = make_qr_image((string)$participant['qr_code']);
    $scaledQr = imagecreatetruecolor($qrSize, $qrSize);
    if (!$scaledQr) {
        imagedestroy($label);
        imagedestroy($qr);
        throw new RuntimeException('qr_allocate_failed');
    }

    imagefilledrectangle($scaledQr, 0, 0, $qrSize, $qrSize, $white);
    imagecopyresampled($scaledQr, $qr, 0, 0, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
    imagedestroy($qr);

    imagecopy($label, $scaledQr, $qrX, $qrY, 0, 0, $qrSize, $qrSize);
    imagedestroy($scaledQr);

    $name = trim((string)$participant['first_name'] . ' ' . (string)$participant['last_name']);
    $phone = trim((string)($participant['phone_number'] ?? ''));
    $phoneLine = $phone !== '' ? $phone : '-';

    $labelSize = 32;
    $nameSize = 70;
    $valueSize = 60;

    $y = LABEL_PADDING + 148;
    imagettftext($label, $labelSize, 0, $textX, $y, $gray, $font, 'NAVN');
    // Extra breathing room between field label and value improves readability.
    $y += 92;

    $nameLines = wrap_lines($font, $nameSize, $name, $textW, 2);
    foreach ($nameLines as $line) {
        imagettftext($label, $nameSize, 0, $textX, $y, $black, $font, $line);
        $y += 76;
    }

    $y += 10;
    imagettftext($label, $labelSize, 0, $textX, $y, $gray, $font, 'TELEFON');
    $y += 70;
    imagettftext($label, $valueSize, 0, $textX, $y, $black, $font, $phoneLine);

    // Bottom black stripe gives high contrast and ensures the lower area is used.
    $stripeTop = LABEL_HEIGHT - 88;
    imagefilledrectangle($label, 0, $stripeTop, LABEL_WIDTH, LABEL_HEIGHT, $black);
    imagettftext($label, $labelSize, 0, $textX, $stripeTop + 54, $white, $font, 'QR: ' . (string)$participant['qr_code']);

    $tmp = tempnam(sys_get_temp_dir(), 'mobilhotell-label-');
    if ($tmp === false) {
        imagedestroy($label);
        throw new RuntimeException('tempfile_failed');
    }

    $pngPath = $tmp . '.png';
    rename($tmp, $pngPath);

    if (!imagepng($label, $pngPath, 0)) {
        imagedestroy($label);
        @unlink($pngPath);
        throw new RuntimeException('image_save_failed');
    }

    imagedestroy($label);
    return $pngPath;
}

function print_label_file(string $path): void
{
    $printer = trim((string)getenv('MOBILHOTELL_LABEL_PRINTER'));
    if ($printer === '') {
        $printer = trim((string)getenv('MOBILHOTELL_PRINTER'));
    }
    if ($printer === '') {
        $printer = DEFAULT_LABEL_PRINTER;
    }

    $media = trim((string)getenv('MOBILHOTELL_LABEL_MEDIA'));
    if ($media === '') {
        $media = DEFAULT_LABEL_MEDIA;
    }

    $parts = [
        'lp',
        '-d',
        $printer,
        '-o',
        'media=' . $media,
        '-o',
        'orientation-requested=4',
        '-o',
        'scaling=100',
        '-o',
        'fit-to-page',
    ];

    $parts[] = $path;

    $cmd = '';
    foreach ($parts as $part) {
        $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg($part);
    }

    $out = [];
    $exit = 0;
    exec($cmd . ' 2>&1', $out, $exit);

    if ($exit !== 0) {
        throw new RuntimeException('label_print_failed: ' . implode(' | ', $out));
    }
}

$pdo = db();
$storageSessionId = (int)($_GET['storage_session_id'] ?? 0);

if ($storageSessionId <= 0) {
    out(['success' => false, 'error' => 'missing_storage_session_id'], 400);
}

$stmt = $pdo->prepare("SELECT p.id AS participant_id, p.first_name, p.last_name, p.qr_code, p.phone_number
    FROM storage_sessions ss
    JOIN participants p ON p.id = ss.participant_id
    WHERE ss.id = ?
    LIMIT 1");
$stmt->execute([$storageSessionId]);
$participant = $stmt->fetch();

if (!$participant) {
    out(['success' => false, 'error' => 'session_not_found'], 404);
}

$pngPath = '';

try {
    $pngPath = draw_label($participant);
    print_label_file($pngPath);

    log_event($pdo, 'storage_label_printed', 'Etikett skrevet ut', [
        'storage_session_id' => $storageSessionId,
        'participant_id' => (int)$participant['participant_id'],
        'qr' => (string)$participant['qr_code'],
    ]);

    out([
        'success' => true,
        'storage_session_id' => $storageSessionId,
        'participant_id' => (int)$participant['participant_id'],
    ]);
} catch (Throwable $e) {
    log_event($pdo, 'storage_label_print_error', 'Etikettutskrift feilet', [
        'storage_session_id' => $storageSessionId,
        'participant_id' => (int)$participant['participant_id'],
        'error' => $e->getMessage(),
    ]);

    out(['success' => false, 'error' => 'print_failed', 'detail' => $e->getMessage()], 500);
} finally {
    if ($pngPath !== '' && is_file($pngPath)) {
        @unlink($pngPath);
    }
}
