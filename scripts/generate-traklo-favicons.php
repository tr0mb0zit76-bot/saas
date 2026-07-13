<?php

declare(strict_types=1);

/**
 * Regenerate public/assets/favicon/* icons.
 *
 * From master (default):
 *   php scripts/generate-traklo-favicons.php
 *   Source: public/downloads/traklo-icon.png (1024×1024)
 *
 * After hand-editing favicon-96x96.png:
 *   php scripts/generate-traklo-favicons.php --from-96
 *   Keeps favicon-96x96.png, rebuilds 16/32/48 + PWA sizes + real favicon.ico.
 */

$from96 = in_array('--from-96', $argv, true);

$outDir = __DIR__.'/../public/assets/favicon';
$rootManifest = __DIR__.'/../public/manifest.webmanifest';
$srcPath = $from96
    ? $outDir.'/favicon-96x96.png'
    : __DIR__.'/../public/downloads/traklo-icon.png';

if (! is_file($srcPath)) {
    fwrite(STDERR, "Missing source: {$srcPath}\n");
    exit(1);
}

/** @return array{0:int,1:int,2:int,3:int} */
function tightContentBounds(\GdImage $im): array
{
    $w = imagesx($im);
    $h = imagesy($im);
    $minX = $w;
    $minY = $h;
    $maxX = 0;
    $maxY = 0;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($im, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;

            if ($alpha >= 110) {
                continue;
            }

            if ($r < 28 && $g < 28 && $b < 40) {
                continue;
            }

            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }
    }

    if ($maxX < $minX || $maxY < $minY) {
        return [0, 0, $w - 1, $h - 1];
    }

    $padX = (int) max(2, round(($maxX - $minX) * 0.03));
    $padY = (int) max(2, round(($maxY - $minY) * 0.03));

    return [
        max(0, $minX - $padX),
        max(0, $minY - $padY),
        min($w - 1, $maxX + $padX),
        min($h - 1, $maxY + $padY),
    ];
}

function cropToSquare(\GdImage $src, array $bounds): \GdImage
{
    [$x1, $y1, $x2, $y2] = $bounds;
    $cw = $x2 - $x1 + 1;
    $ch = $y2 - $y1 + 1;
    $side = max($cw, $ch);
    $dst = imagecreatetruecolor($side, $side);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $side, $side, $transparent);
    $dx = (int) floor(($side - $cw) / 2);
    $dy = (int) floor(($side - $ch) / 2);
    imagecopy($dst, $src, $dx, $dy, $x1, $y1, $cw, $ch);

    return $dst;
}

function resizeSquare(\GdImage $src, int $targetSize): \GdImage
{
    $side = imagesx($src);
    $dst = imagecreatetruecolor($targetSize, $targetSize);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $targetSize, $targetSize, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetSize, $targetSize, $side, $side);

    return $dst;
}

function savePng(\GdImage $im, string $path): void
{
    imagepng($im, $path, 6);
}

/** @param array<int, string> $sizeToPath */
function writeIcoFromPngs(array $sizeToPath, string $icoPath): void
{
    ksort($sizeToPath);
    $count = count($sizeToPath);
    $header = pack('vvv', 0, 1, $count);
    $entries = '';
    $data = '';
    $offset = 6 + (16 * $count);

    foreach ($sizeToPath as $size => $path) {
        $png = file_get_contents($path);
        if ($png === false) {
            throw new RuntimeException("Could not read {$path}");
        }

        $w = $size >= 256 ? 0 : $size;
        $h = $size >= 256 ? 0 : $size;
        $entries .= pack('CCCCvvVV', $w, $h, 0, 0, 1, 32, strlen($png), $offset);
        $data .= $png;
        $offset += strlen($png);
    }

    file_put_contents($icoPath, $header.$entries.$data);
}

$raw = imagecreatefrompng($srcPath);
if ($raw === false) {
    fwrite(STDERR, "Could not read PNG: {$srcPath}\n");
    exit(1);
}

imagealphablending($raw, false);
imagesavealpha($raw, true);

$cropped = cropToSquare($raw, tightContentBounds($raw));
imagedestroy($raw);

if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Could not create: {$outDir}\n");
    exit(1);
}

$targets = $from96
    ? [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'sidebar-48.png' => 48,
        'apple-touch-icon.png' => 180,
        'web-app-manifest-192x192.png' => 192,
        'web-app-manifest-512x512.png' => 512,
    ]
    : [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'sidebar-48.png' => 48,
        'favicon-96x96.png' => 96,
        'apple-touch-icon.png' => 180,
        'web-app-manifest-192x192.png' => 192,
        'web-app-manifest-512x512.png' => 512,
    ];

foreach ($targets as $name => $px) {
    $path = $outDir.'/'.$name;
    $resized = resizeSquare($cropped, $px);
    savePng($resized, $path);
    imagedestroy($resized);
    echo 'wrote '.$path.PHP_EOL;
}

$icoSizes = [
    16 => $outDir.'/favicon-16x16.png',
    32 => $outDir.'/favicon-32x32.png',
    48 => $outDir.'/sidebar-48.png',
];

writeIcoFromPngs($icoSizes, $outDir.'/favicon.ico');
copy($outDir.'/favicon.ico', __DIR__.'/../public/favicon.ico');
echo 'wrote '.$outDir.'/favicon.ico + public/favicon.ico'.PHP_EOL;

$manifest = [
    'id' => '/',
    'name' => 'Traklo Pro',
    'short_name' => 'Traklo',
    'description' => 'CRM для экспедиторов: лиды, заказы, документы, график оплат.',
    'lang' => 'ru-RU',
    'dir' => 'ltr',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#0B1220',
    'theme_color' => '#18181b',
    'icons' => [
        [
            'src' => '/assets/favicon/web-app-manifest-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => '/assets/favicon/web-app-manifest-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => '/assets/favicon/web-app-manifest-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

file_put_contents($rootManifest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
echo 'wrote '.$rootManifest.PHP_EOL;

imagedestroy($cropped);
