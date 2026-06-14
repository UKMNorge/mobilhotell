<?php

declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

function resolve_output_path(array $argv): string
{
    $default = getcwd() . '/deltakere_qr_oversikt.pdf';

    foreach ($argv as $i => $arg) {
        if ($i === 0) {
            continue;
        }

        if (str_starts_with($arg, '--output=')) {
            $value = substr($arg, 9);
            return $value !== '' ? $value : $default;
        }

        if ($arg === '--output' && isset($argv[$i + 1]) && trim((string)$argv[$i + 1]) !== '') {
            return (string)$argv[$i + 1];
        }

        if ($arg !== '' && !str_starts_with($arg, '--')) {
            return $arg;
        }
    }

    return $default;
}

$pdo = db();

$stmt = $pdo->query("SELECT first_name, last_name, county, participant_type, image_path, qr_code
    FROM participants
    ORDER BY
        CASE WHEN county IS NULL OR TRIM(county) = '' THEN 1 ELSE 0 END,
        county,
        last_name,
        first_name");
$participants = $stmt->fetchAll();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function qr_temp_file(string $text, array &$tmpFiles): string
{
    $options = new QROptions([
        'outputInterface' => QRGdImagePNG::class,
        'eccLevel' => EccLevel::M,
        'scale' => 4,
        'imageBase64' => false,
        'returnResource' => false,
    ]);

    $rendered = (new QRCode($options))->render($text);

    $tmp = tempnam(sys_get_temp_dir(), 'mobilhotell-qr-');
    if ($tmp === false) {
        return '';
    }

    $qrPath = $tmp . '.png';
    rename($tmp, $qrPath);

    if ($rendered instanceof GdImage) {
        imagepng($rendered, $qrPath, 0);
        imagedestroy($rendered);
        $tmpFiles[] = $qrPath;
        return 'file://' . $qrPath;
    }

    if (is_string($rendered)) {
        if (str_starts_with($rendered, 'data:image/')) {
            $comma = strpos($rendered, ',');
            if ($comma !== false) {
                $raw = base64_decode(substr($rendered, $comma + 1), true);
                if ($raw !== false && file_put_contents($qrPath, $raw) !== false) {
                    $tmpFiles[] = $qrPath;
                    return 'file://' . $qrPath;
                }
            }
            @unlink($qrPath);
            return '';
        }
        if (file_put_contents($qrPath, $rendered) !== false) {
            $tmpFiles[] = $qrPath;
            return 'file://' . $qrPath;
        }
    }

    @unlink($qrPath);
    return '';
}

function participant_image_src(string $path, array &$tmpFiles): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $trimmed) === 1) {
        return '';
    }

    $fullPath = $trimmed;
    if (!str_starts_with($fullPath, '/')) {
        $fullPath = __DIR__ . '/' . ltrim($fullPath, './');
    }

    if (!is_file($fullPath) || !is_readable($fullPath)) {
        return '';
    }

    $raw = @file_get_contents($fullPath);
    if ($raw === false || $raw === '') {
        return '';
    }

    $src = @imagecreatefromstring($raw);
    if (!$src instanceof GdImage) {
        return 'file://' . $fullPath;
    }

    $target = imagecreatetruecolor(64, 64);
    if (!$target instanceof GdImage) {
        imagedestroy($src);
        return 'file://' . $fullPath;
    }

    $white = imagecolorallocate($target, 255, 255, 255);
    imagefilledrectangle($target, 0, 0, 64, 64, $white);

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $scale = min(64 / max(1, $srcW), 64 / max(1, $srcH));
    $drawW = max(1, (int)round($srcW * $scale));
    $drawH = max(1, (int)round($srcH * $scale));
    $dstX = (int)floor((64 - $drawW) / 2);
    $dstY = (int)floor((64 - $drawH) / 2);

    imagecopyresampled($target, $src, $dstX, $dstY, 0, 0, $drawW, $drawH, $srcW, $srcH);
    imagedestroy($src);

    $tmp = tempnam(sys_get_temp_dir(), 'mobilhotell-photo-');
    if ($tmp === false) {
        imagedestroy($target);
        return 'file://' . $fullPath;
    }

    $thumbPath = $tmp . '.jpg';
    rename($tmp, $thumbPath);
    imagejpeg($target, $thumbPath, 72);
    imagedestroy($target);

    $tmpFiles[] = $thumbPath;
    return 'file://' . $thumbPath;
}

$items = [];
$index = 1;
$tmpQrFiles = [];
foreach ($participants as $p) {
    $name = trim((string)$p['first_name'] . ' ' . (string)$p['last_name']);
    $county = (string)($p['county'] ?? '');
    $type = (string)($p['participant_type'] ?? '');
    $imagePath = (string)($p['image_path'] ?? '');
    $qr = (string)($p['qr_code'] ?? '');
    $qrImg = qr_temp_file($qr, $tmpQrFiles);
    $participantImg = participant_image_src($imagePath, $tmpQrFiles);

    $meta = trim($county . ($county !== '' && $type !== '' ? ' | ' : '') . $type);

    $items[] = '<td class="num">' . $index . '</td>'
        . '<td class="photo">' . ($participantImg !== '' ? '<img src="' . h($participantImg) . '" alt="Bilde" />' : '-') . '</td>'
        . '<td class="qr">' . ($qrImg !== '' ? '<img src="' . h($qrImg) . '" alt="QR" />' : h($qr)) . '</td>'
        . '<td class="person"><div class="name">' . h($name) . '</div><div class="meta">' . h($meta) . '</div></td>';

    $index++;
}

$rowsHtml = '';

if ($items === []) {
    $rowsHtml = '<tr><td colspan="8">Ingen deltakere funnet.</td></tr>';
} else {
    $count = count($items);
    for ($i = 0; $i < $count; $i += 2) {
        $left = $items[$i];
        $right = $items[$i + 1] ?? '<td class="num"></td><td class="photo"></td><td class="qr"></td><td class="person"></td>';
        $rowsHtml .= '<tr>' . $left . $right . '</tr>';
    }
}

$html = '<!doctype html><html lang="no"><head><meta charset="utf-8"><style>'
    . 'body{font-family:DejaVu Sans, sans-serif; font-size:7pt;}'
    . 'table.list{width:100%; table-layout:fixed; border-collapse:collapse;}'
    . 'table.list th, table.list td{border:1px solid #888; padding:0; vertical-align:middle; line-height:1.0;}'
    . 'table.list th{background:#f0f0f0; font-weight:700;}'
    . 'table.list td{height:18px;}'
    . 'td.num{width:12px; text-align:right;}'
    . 'td.photo{width:22px; text-align:center;}'
    . 'td.photo img{width:16px; height:16px; object-fit:cover;}'
    . 'td.qr{width:22px; text-align:center;}'
    . 'td.qr img{width:16px; height:16px;}'
    . 'td.person{padding:0 1px;}'
    . 'td.person .name{font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}'
    . 'td.person .meta{font-size:6pt; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}'
    . '</style></head><body>'
    . '<table class="list">'
    . '<thead><tr><th>#</th><th>B</th><th>QR</th><th>Deltaker</th><th>#</th><th>B</th><th>QR</th><th>Deltaker</th></tr></thead>'
    . '<tbody>' . $rowsHtml . '</tbody>'
    . '</table>'
    . '</body></html>';

$mpdf = new Mpdf([
    'format' => 'A4',
    'margin_left' => 3,
    'margin_right' => 3,
    'margin_top' => 3,
    'margin_bottom' => 3,
]);

$mpdf->SetTitle('Deltakerliste med QR-koder');
$mpdf->WriteHTML($html);

try {
    if (PHP_SAPI === 'cli') {
        $outputPath = resolve_output_path($_SERVER['argv'] ?? []);
        $mpdf->Output($outputPath, Destination::FILE);
        fwrite(STDOUT, "PDF laget: {$outputPath}\n");
        exit(0);
    }

    $mpdf->Output('deltakere_qr_oversikt.pdf', Destination::INLINE);
} finally {
    foreach ($tmpQrFiles as $tmpQrFile) {
        if (is_string($tmpQrFile) && $tmpQrFile !== '' && is_file($tmpQrFile)) {
            @unlink($tmpQrFile);
        }
    }
}
