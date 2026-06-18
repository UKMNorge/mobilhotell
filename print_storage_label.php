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

const DEFAULT_LABEL_PRINTER = 'Brother-QL-800';
const DEFAULT_LABEL_BACKEND = 'cups';
const DEFAULT_LABEL_MEDIA = '';
const DEFAULT_LABEL_MEDIA_TYPE = '';
const DEFAULT_LABEL_PAGE_SIZE = '';
const DEFAULT_LABEL_AUTO_CUT = '';
const DEFAULT_LABEL_AUTO_EJECT = '';
const DEFAULT_LABEL_ORIENTATION = '';
const DEFAULT_LABEL_SCALING = '';
const DEFAULT_LABEL_FIT_TO_PAGE = '';
// With brother_ql rotate=90, input image must be 991x413 for 39x90 labels.
const LABEL_WIDTH = 991;
const LABEL_HEIGHT = 413;
const LABEL_PADDING = 16;
const LABEL_GAP = 16;

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function get_label_fonts(): array
{
    $regularCandidates = [
        __DIR__ . '/assets/Inter-VariableFont_opsz,wght.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
    ];
    $boldCandidates = [
        __DIR__ . '/assets/Inter-VariableFont_opsz,wght.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
    ];

    $regular = null;
    foreach ($regularCandidates as $path) {
        if (is_file($path)) {
            $regular = $path;
            break;
        }
    }

    $bold = null;
    foreach ($boldCandidates as $path) {
        if (is_file($path)) {
            $bold = $path;
            break;
        }
    }

    if ($regular === null && $bold === null) {
        return ['regular' => null, 'bold' => null];
    }

    if ($regular === null) {
        $regular = $bold;
    }
    if ($bold === null) {
        $bold = $regular;
    }

    return ['regular' => $regular, 'bold' => $bold];
}

function clip_text_to_width(string $font, int $size, string $text, int $maxWidth): string
{
    if ($maxWidth <= 0) {
        return '';
    }

    $clean = trim($text);
    if ($clean === '') {
        return '';
    }

    if (text_width($font, $size, $clean) <= $maxWidth) {
        return $clean;
    }

    $ellipsis = '...';
    $chars = preg_split('//u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    while (!empty($chars)) {
        array_pop($chars);
        $candidate = rtrim(implode('', $chars));
        if ($candidate === '') {
            return $ellipsis;
        }
        if (text_width($font, $size, $candidate . $ellipsis) <= $maxWidth) {
            return $candidate . $ellipsis;
        }
    }

    return $ellipsis;
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

function force_monochrome(GdImage $image, int $threshold = 190): void
{
    $w = imagesx($image);
    $h = imagesy($image);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $luma = (int)round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
            imagesetpixel($image, $x, $y, $luma >= $threshold ? $white : $black);
        }
    }
}

function draw_label(array $participant): string
{
    $fonts = get_label_fonts();
    $fontRegular = $fonts['regular'];
    $fontBold = $fonts['bold'];
    if (!is_string($fontRegular) || $fontRegular === '' || !is_string($fontBold) || $fontBold === '') {
        throw new RuntimeException('font_not_found');
    }

    $label = imagecreatetruecolor(LABEL_WIDTH, LABEL_HEIGHT);
    if (!$label) {
        throw new RuntimeException('label_allocate_failed');
    }

    imagealphablending($label, true);
    imagesavealpha($label, false);

    $white = imagecolorallocate($label, 255, 255, 255);
    $black = imagecolorallocate($label, 0, 0, 0);
    $gray = imagecolorallocate($label, 90, 90, 90);

    imagefilledrectangle($label, 0, 0, LABEL_WIDTH, LABEL_HEIGHT, $white);

    // Give text more room by slightly reducing QR size and centering it vertically.
    $qrSize = min(LABEL_HEIGHT - (LABEL_PADDING * 2), 356);
    $qrX = LABEL_WIDTH - LABEL_PADDING - $qrSize;
    $qrY = (int)floor((LABEL_HEIGHT - $qrSize) / 2);
    $textX = LABEL_PADDING;
    $textW = $qrX - LABEL_GAP - LABEL_PADDING;

    $headerText = 'GENERELL OPPBEVARING';
    $headerSize = fit_text_size($fontBold, $headerText, $textW, 36, 18);
    $headerLines = wrap_lines($fontBold, $headerSize, $headerText, $textW, 2);
    $y = LABEL_PADDING + 34;
    $headerLineHeight = max(18, (int)round($headerSize * 1.2));

    foreach ($headerLines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $safeLine = clip_text_to_width($fontBold, $headerSize, $line, $textW);
        imagettftext($label, $headerSize, 0, $textX, $y, $black, $fontBold, $safeLine);
        $y += $headerLineHeight;
    }

    $qr = make_qr_image((string)$participant['qr_code']);

    $scaledQr = imagecreatetruecolor($qrSize, $qrSize);
    if (!$scaledQr) {
        imagedestroy($label);
        imagedestroy($qr);
        throw new RuntimeException('qr_allocate_failed');
    }

    imagefilledrectangle($scaledQr, 0, 0, $qrSize, $qrSize, $white);
    // Keep modules crisp to avoid dithering artifacts on thermal label printers.
    imagecopyresized($scaledQr, $qr, 0, 0, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
    imagedestroy($qr);

    imagecopy($label, $scaledQr, $qrX, $qrY, 0, 0, $qrSize, $qrSize);
    imagedestroy($scaledQr);

    $name = trim((string)$participant['first_name'] . ' ' . (string)$participant['last_name']);
    $phone = trim((string)($participant['phone_number'] ?? ''));
    $phoneLine = $phone !== '' ? $phone : '-';

    $labelSize = 20;
    $valueSize = 32;

    $y += 12;
    imagettftext($label, $labelSize, 0, $textX, $y, $gray, $fontRegular, 'NAVN');
    $nameTopY = $y + 64;

    // Anchor phone block to the lower text area so it always stays inside the label.
    $phoneLabelY = LABEL_HEIGHT - 124;
    $phoneValueY = LABEL_HEIGHT - 74;
    $availableNameHeight = max(24, $phoneLabelY - $nameTopY - 8);

    $nameSize = fit_text_size($fontBold, $name, $textW, 42, 16);
    $nameLines = wrap_lines($fontBold, $nameSize, $name, $textW, 2);
    $nameLineHeight = max(20, (int)round($nameSize * 1.12));

    while ($nameSize > 16) {
        $lineCount = 0;
        foreach ($nameLines as $line) {
            if (trim($line) !== '') {
                $lineCount++;
            }
        }
        if (($lineCount * $nameLineHeight) <= $availableNameHeight) {
            break;
        }

        $nameSize--;
        $nameLines = wrap_lines($fontBold, $nameSize, $name, $textW, 2);
        $nameLineHeight = max(20, (int)round($nameSize * 1.12));
    }

    $y = $nameTopY;
    foreach ($nameLines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $safeLine = clip_text_to_width($fontBold, $nameSize, $line, $textW);
        imagettftext($label, $nameSize, 0, $textX, $y, $black, $fontBold, $safeLine);
        $y += $nameLineHeight;
    }

    $phoneLabel = clip_text_to_width($fontRegular, $labelSize, 'TELEFON', $textW);
    imagettftext($label, $labelSize, 0, $textX, $phoneLabelY, $gray, $fontRegular, $phoneLabel);

    $phoneSize = fit_text_size($fontBold, $phoneLine, $textW, $valueSize, 14);
    $safePhone = clip_text_to_width($fontBold, $phoneSize, $phoneLine, $textW);
    imagettftext($label, $phoneSize, 0, $textX, $phoneValueY, $black, $fontBold, $safePhone);

    $qrLineSize = fit_text_size($fontRegular, 'QR: ' . (string)$participant['qr_code'], $textW, 17, 10);
    $safeQrLine = clip_text_to_width($fontRegular, $qrLineSize, 'QR: ' . (string)$participant['qr_code'], $textW);
    imagettftext($label, $qrLineSize, 0, $textX, LABEL_HEIGHT - 12, $black, $fontRegular, $safeQrLine);

    // Force pure black/white output so the printer doesn't create colored/halftone artifacts.
    force_monochrome($label, 188);

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

function cups_print_label_file(string $path): void
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

    $mediaType = trim((string)getenv('MOBILHOTELL_LABEL_MEDIA_TYPE'));
    if ($mediaType === '') {
        $mediaType = DEFAULT_LABEL_MEDIA_TYPE;
    }

    $pageSize = trim((string)getenv('MOBILHOTELL_LABEL_PAGE_SIZE'));
    if ($pageSize === '') {
        $pageSize = DEFAULT_LABEL_PAGE_SIZE;
    }

    $autoCut = trim((string)getenv('MOBILHOTELL_LABEL_AUTO_CUT'));
    if ($autoCut === '') {
        $autoCut = DEFAULT_LABEL_AUTO_CUT;
    }

    $autoEject = trim((string)getenv('MOBILHOTELL_LABEL_AUTO_EJECT'));
    if ($autoEject === '') {
        $autoEject = DEFAULT_LABEL_AUTO_EJECT;
    }

    $orientation = trim((string)getenv('MOBILHOTELL_LABEL_ORIENTATION'));
    if ($orientation === '') {
        $orientation = DEFAULT_LABEL_ORIENTATION;
    }

    $scaling = trim((string)getenv('MOBILHOTELL_LABEL_SCALING'));
    if ($scaling === '') {
        $scaling = DEFAULT_LABEL_SCALING;
    }

    $fitToPage = trim((string)getenv('MOBILHOTELL_LABEL_FIT_TO_PAGE'));
    if ($fitToPage === '') {
        $fitToPage = DEFAULT_LABEL_FIT_TO_PAGE;
    }

    $parts = ['lp', '-d', $printer];

    if ($media !== '') {
        $parts[] = '-o';
        $parts[] = 'media=' . $media;
    }

    if ($mediaType !== '') {
        $parts[] = '-o';
        $parts[] = 'MediaType=' . $mediaType;
    }

    if ($pageSize !== '') {
        $parts[] = '-o';
        $parts[] = 'PageSize=' . $pageSize;
    }

    if ($autoCut !== '') {
        $parts[] = '-o';
        $parts[] = 'AutoCut=' . $autoCut;
    }

    if ($autoEject !== '') {
        $parts[] = '-o';
        $parts[] = 'AutoEject=' . $autoEject;
    }

    if ($orientation !== '') {
        $parts[] = '-o';
        $parts[] = 'orientation-requested=' . $orientation;
    }

    if ($scaling !== '') {
        $parts[] = '-o';
        $parts[] = 'scaling=' . $scaling;
    }

    if ($fitToPage !== '') {
        $parts[] = '-o';
        $parts[] = $fitToPage;
    }

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

function allow_cups_fallback(): bool
{
    $value = strtolower(trim((string)getenv('MOBILHOTELL_LABEL_ALLOW_CUPS_FALLBACK')));
    if ($value === '') {
        return true;
    }

    return !in_array($value, ['0', 'false', 'no', 'off'], true);
}

function label_forward_url(): string
{
    return trim((string)getenv('MOBILHOTELL_LABEL_FORWARD_URL'));
}

function maybe_forward_label_print(int $storageSessionId): ?array
{
    $baseUrl = label_forward_url();
    if ($baseUrl === '') {
        return null;
    }

    if ((string)($_GET['_forwarded'] ?? '') === '1') {
        return null;
    }

    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    $url = $baseUrl
        . $separator
        . 'storage_session_id=' . rawurlencode((string)$storageSessionId)
        . '&_ts=' . rawurlencode((string)time())
        . '&_forwarded=1';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('label_forward_failed: request_failed');
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $headerLine, $m) === 1) {
            $status = (int)$m[1];
            break;
        }
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw new RuntimeException('label_forward_failed: invalid_json');
    }

    $ok = !empty($payload['success']);
    if ($status >= 400 || !$ok) {
        $detail = (string)($payload['detail'] ?? $payload['error'] ?? 'remote_error');
        throw new RuntimeException('label_forward_failed: ' . $detail);
    }

    return $payload;
}

function print_label_file(string $path): void
{
    $backend = trim((string)getenv('MOBILHOTELL_LABEL_BACKEND'));
    if ($backend === '') {
        $backend = DEFAULT_LABEL_BACKEND;
    }

    if ($backend === 'brother_ql') {
        $printer = trim((string)getenv('MOBILHOTELL_LABEL_PRINTER'));
        if ($printer === '') {
            $printer = trim((string)getenv('MOBILHOTELL_PRINTER'));
        }
        if ($printer === '') {
            $printer = 'QL800';
        }

        $model = trim((string)getenv('MOBILHOTELL_LABEL_BROTHER_MODEL'));
        if ($model === '') {
            $model = 'QL-800';
        }

        $label = trim((string)getenv('MOBILHOTELL_LABEL_BROTHER_LABEL'));
        if ($label === '') {
            // brother_ql uses 39x90 identifier for DK-11208 class labels.
            $label = '39x90';
        }

        $rotate = trim((string)getenv('MOBILHOTELL_LABEL_BROTHER_ROTATE'));
        if ($rotate === '') {
            $rotate = '90';
        }

        $useRed = strtolower(trim((string)getenv('MOBILHOTELL_LABEL_BROTHER_RED')));
        if ($useRed === '') {
            $useRed = '0';
        }

        $tmpBin = tempnam(sys_get_temp_dir(), 'mobilhotell-qlraw-');
        if ($tmpBin === false) {
            throw new RuntimeException('tempfile_failed');
        }
        $binPath = $tmpBin . '.bin';
        rename($tmpBin, $binPath);

        $parts = ['python3', '-m', 'brother_ql.brother_ql_create', '-m', $model, '-s', $label, '-r', $rotate, '--compress'];

        if (in_array($useRed, ['1', 'true', 'yes', 'on'], true)) {
            $parts[] = '--red';
        }

        $parts[] = $path;
        $parts[] = $binPath;

        $cmd = '';
        foreach ($parts as $part) {
            $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg($part);
        }

        try {
            $out = [];
            $exit = 0;
            exec($cmd . ' 2>&1', $out, $exit);

            if ($exit !== 0) {
                throw new RuntimeException('label_print_failed: ' . implode(' | ', $out));
            }

            $lpParts = ['lp', '-d', $printer, '-o', 'raw', $binPath];
            $lpCmd = '';
            foreach ($lpParts as $part) {
                $lpCmd .= ($lpCmd === '' ? '' : ' ') . escapeshellarg($part);
            }

            $lpOut = [];
            $lpExit = 0;
            exec($lpCmd . ' 2>&1', $lpOut, $lpExit);

            if ($lpExit !== 0) {
                throw new RuntimeException('label_print_failed: ' . implode(' | ', $lpOut));
            }

            @unlink($binPath);
            return;
        } catch (Throwable $e) {
            @unlink($binPath);
            if (!allow_cups_fallback()) {
                throw $e;
            }

            // On clients, brother_ql may be unavailable while a shared CUPS queue still works.
            cups_print_label_file($path);
            return;
        }
    }

    cups_print_label_file($path);
}

$pdo = db();
$storageSessionId = (int)($_GET['storage_session_id'] ?? 0);
$participantId = (int)($_GET['participant_id'] ?? 0);

if ($storageSessionId <= 0 && $participantId <= 0) {
    out(['success' => false, 'error' => 'missing_identifier'], 400);
}

if ($storageSessionId > 0) {
    try {
        $forwarded = maybe_forward_label_print($storageSessionId);
        if (is_array($forwarded)) {
            out([
                'success' => true,
                'storage_session_id' => (int)($forwarded['storage_session_id'] ?? $storageSessionId),
                'participant_id' => (int)($forwarded['participant_id'] ?? 0),
                'forwarded' => true,
            ]);
        }
    } catch (Throwable $e) {
        out(['success' => false, 'error' => 'forward_failed', 'detail' => $e->getMessage()], 502);
    }
}

$stmt = null;
if ($storageSessionId > 0) {
    $stmt = $pdo->prepare("SELECT p.id AS participant_id, p.first_name, p.last_name, p.qr_code, p.phone_number
        FROM storage_sessions ss
        JOIN participants p ON p.id = ss.participant_id
        WHERE ss.id = ?
        LIMIT 1");
    $stmt->execute([$storageSessionId]);
} else {
    $stmt = $pdo->prepare("SELECT p.id AS participant_id, p.first_name, p.last_name, p.qr_code, p.phone_number
        FROM participants p
        WHERE p.id = ?
        LIMIT 1");
    $stmt->execute([$participantId]);
}
$participant = $stmt->fetch();

if (!$participant) {
    out(['success' => false, 'error' => 'participant_not_found'], 404);
}

$pngPath = '';

try {
    $pngPath = draw_label($participant);
    print_label_file($pngPath);

    log_event($pdo, 'storage_label_printed', 'Etikett skrevet ut', [
        'storage_session_id' => $storageSessionId > 0 ? $storageSessionId : null,
        'participant_id' => (int)$participant['participant_id'],
        'qr' => (string)$participant['qr_code'],
    ]);

    out([
        'success' => true,
        'storage_session_id' => $storageSessionId > 0 ? $storageSessionId : null,
        'participant_id' => (int)$participant['participant_id'],
    ]);
} catch (Throwable $e) {
    log_event($pdo, 'storage_label_print_error', 'Etikettutskrift feilet', [
        'storage_session_id' => $storageSessionId > 0 ? $storageSessionId : null,
        'participant_id' => (int)$participant['participant_id'],
        'error' => $e->getMessage(),
    ]);

    out(['success' => false, 'error' => 'print_failed', 'detail' => $e->getMessage()], 500);
} finally {
    if ($pngPath !== '' && is_file($pngPath)) {
        @unlink($pngPath);
    }
}
