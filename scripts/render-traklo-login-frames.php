<?php

/**
 * Build Traklo login animation frames.
 *
 *   php scripts/render-traklo-login-frames.php
 *   php scripts/render-traklo-login-frames.php --frames=24 --size=720
 */

declare(strict_types=1);

$opts = getopt('', ['frames::', 'size::']);
$frameCount = max(12, min(36, (int) ($opts['frames'] ?? 24)));
$size = max(320, min(1024, (int) ($opts['size'] ?? 720)));
$repo = dirname(__DIR__);
$srcPath = $repo.'/public/downloads/traklo-icon.png';
$outDir = $repo.'/public/downloads/traklo-login-frames';

if (! is_file($srcPath)) {
    fwrite(STDERR, "Missing {$srcPath}\n");
    exit(1);
}
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$src = imagecreatefrompng($srcPath);
$w = imagesx($src);
$h = imagesy($src);

function rgbAt(GdImage $im, int $x, int $y): array
{
    $rgb = imagecolorat($im, $x, $y);
    return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF, ($rgb >> 24) & 0x7F];
}

function isWhiteish(int $r, int $g, int $b): bool
{
    return $r > 175 && $g > 175 && $b > 175 && abs($r - $b) < 45;
}

function isRoadBlue(int $r, int $g, int $b): bool
{
    // light cyan road stroke on icon
    return $b > 160 && $g > 140 && $r < 200 && $b >= $r && ($b - $r) > 20;
}

function truckBBox(GdImage $im, int $w, int $h): array
{
    $minX = $w;
    $maxX = 0;
    $minY = $h;
    $maxY = 0;
    for ($y = (int) ($h * 0.34); $y < (int) ($h * 0.56); $y++) {
        for ($x = (int) ($w * 0.34); $x < (int) ($w * 0.66); $x++) {
            [$r, $g, $b, $a] = rgbAt($im, $x, $y);
            if ($a < 40 && isWhiteish($r, $g, $b)) {
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }
    }
    $padX = 6;
    $padY = 4;

    return [
        'minX' => max(0, $minX - $padX),
        'maxX' => min($w - 1, $maxX + $padX),
        'minY' => max(0, $minY - $padY),
        'maxY' => min($h - 1, $maxY + $padY),
    ];
}

/** Build soft mask of truck (1 = truck) from whites + nearby non-road pixels. */
function truckMask(GdImage $im, array $bbox): array
{
    $mask = [];
    for ($y = $bbox['minY']; $y <= $bbox['maxY']; $y++) {
        for ($x = $bbox['minX']; $x <= $bbox['maxX']; $x++) {
            [$r, $g, $b, $a] = rgbAt($im, $x, $y);
            if ($a > 40) {
                $mask[$y][$x] = 0.0;
                continue;
            }
            if (isWhiteish($r, $g, $b)) {
                $mask[$y][$x] = 1.0;
            } elseif (isRoadBlue($r, $g, $b)) {
                $mask[$y][$x] = 0.0;
            } else {
                // glass/shadow leftovers near whites — mark weakly, dilate later
                $mask[$y][$x] = 0.0;
            }
        }
    }
    // Dilate white mask so glass edges are included
    for ($pass = 0; $pass < 3; $pass++) {
        $next = $mask;
        for ($y = $bbox['minY'] + 1; $y <= $bbox['maxY'] - 1; $y++) {
            for ($x = $bbox['minX'] + 1; $x <= $bbox['maxX'] - 1; $x++) {
                if (($mask[$y][$x] ?? 0) >= 1) {
                    continue;
                }
                $sum = 0;
                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $sum += $mask[$y + $dy][$x + $dx] ?? 0;
                    }
                }
                if ($sum >= 2) {
                    [$r, $g, $b] = rgbAt($im, $x, $y);
                    if (! isRoadBlue($r, $g, $b)) {
                        $next[$y][$x] = 1.0;
                    }
                }
            }
        }
        $mask = $next;
    }

    return $mask;
}

function paintOutTruck(GdImage $dst, GdImage $src, array $bbox, array $mask): void
{
    // Solid blue patch over truck region (ignore road — redraw it after).
    $sr = $sg = $sb = $n = 0;
    for ($y = (int) (imagesy($src) * 0.20); $y < (int) (imagesy($src) * 0.28); $y += 3) {
        for ($x = (int) (imagesx($src) * 0.40); $x < (int) (imagesx($src) * 0.60); $x += 3) {
            [$r, $g, $b, $a] = rgbAt($src, $x, $y);
            if ($a < 40 && $b > 140 && ! isWhiteish($r, $g, $b)) {
                $sr += $r;
                $sg += $g;
                $sb += $b;
                $n++;
            }
        }
    }
    if ($n === 0) {
        $sr = 23;
        $sg = 180;
        $sb = 246;
        $n = 1;
    }
    $fill = imagecolorallocate($dst, (int) round($sr / $n), (int) round($sg / $n), (int) round($sb / $n));

    // Expand wipe a bit so ghost truck is fully gone
    $minX = max(0, $bbox['minX'] - 2);
    $maxX = min(imagesx($dst) - 1, $bbox['maxX'] + 2);
    $minY = max(0, $bbox['minY'] - 2);
    $maxY = min(imagesy($dst) - 1, $bbox['maxY'] + 2);
    imagefilledrectangle($dst, $minX, $minY, $maxX, $maxY, $fill);

    // Soften wipe edges by blending a 1px ring toward neighbors
    for ($y = $minY; $y <= $maxY; $y++) {
        for ($x = $minX; $x <= $maxX; $x++) {
            $edge = $x === $minX || $x === $maxX || $y === $minY || $y === $maxY;
            if (! $edge) {
                continue;
            }
            $nx = $x === $minX ? $x - 1 : ($x === $maxX ? $x + 1 : $x);
            $ny = $y === $minY ? $y - 1 : ($y === $maxY ? $y + 1 : $y);
            $nx = max(0, min(imagesx($src) - 1, $nx));
            $ny = max(0, min(imagesy($src) - 1, $ny));
            [$nr, $ng, $nb] = rgbAt($src, $nx, $ny);
            [$cr, $cg, $cb] = rgbAt($dst, $x, $y);
            imagesetpixel($dst, $x, $y, imagecolorallocate(
                $dst,
                (int) round(($nr + $cr) / 2),
                (int) round(($ng + $cg) / 2),
                (int) round(($nb + $cb) / 2)
            ));
        }
    }

    // Redraw route segment across the wipe (approx icon path)
    $road = imagecolorallocate($dst, 155, 205, 248);
    imagesetthickness($dst, max(5, (int) round(imagesx($dst) * 0.01)));
    $w = imagesx($dst);
    $h = imagesy($dst);
    $pts = [
        [(int) ($w * 0.22), (int) ($h * 0.32)],
        [(int) ($w * 0.38), (int) ($h * 0.43)],
        [(int) ($w * 0.52), (int) ($h * 0.46)],
        [(int) ($w * 0.68), (int) ($h * 0.38)],
        [(int) ($w * 0.80), (int) ($h * 0.31)],
    ];
    for ($i = 0; $i < count($pts) - 1; $i++) {
        imageline($dst, $pts[$i][0], $pts[$i][1], $pts[$i + 1][0], $pts[$i + 1][1], $road);
    }
    imagesetthickness($dst, 1);
}

function extractTruck(GdImage $src, array $bbox, array $mask): GdImage
{
    $tw = $bbox['maxX'] - $bbox['minX'] + 1;
    $th = $bbox['maxY'] - $bbox['minY'] + 1;
    $truck = imagecreatetruecolor($tw, $th);
    imagesavealpha($truck, true);
    imagealphablending($truck, false);
    $transparent = imagecolorallocatealpha($truck, 0, 0, 0, 127);
    imagefill($truck, 0, 0, $transparent);

    for ($y = $bbox['minY']; $y <= $bbox['maxY']; $y++) {
        for ($x = $bbox['minX']; $x <= $bbox['maxX']; $x++) {
            if (($mask[$y][$x] ?? 0) < 0.5) {
                continue;
            }
            [$r, $g, $b, $a] = rgbAt($src, $x, $y);
            if ($a > 40 || isRoadBlue($r, $g, $b)) {
                continue;
            }
            // keep whites / light greys of truck
            if ($r > 140 && $g > 140 && $b > 140) {
                imagesetpixel(
                    $truck,
                    $x - $bbox['minX'],
                    $y - $bbox['minY'],
                    imagecolorallocatealpha($truck, $r, $g, $b, 0)
                );
            }
        }
    }

    return $truck;
}

function resizeCopy(GdImage $im, int $nw, int $nh): GdImage
{
    $out = imagecreatetruecolor($nw, $nh);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $transparent);
    imagealphablending($out, true);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, imagesx($im), imagesy($im));

    return $out;
}

/** @param list<array{0:float,1:float}> $pts */
function bezierPoint(array $pts, float $t): array
{
    $u = 1 - $t;
    $tt = $t * $t;
    $uu = $u * $u;
    $uuu = $uu * $u;
    $ttt = $tt * $t;

    return [
        $uuu * $pts[0][0] + 3 * $uu * $t * $pts[1][0] + 3 * $u * $tt * $pts[2][0] + $ttt * $pts[3][0],
        $uuu * $pts[0][1] + 3 * $uu * $t * $pts[1][1] + 3 * $u * $tt * $pts[2][1] + $ttt * $pts[3][1],
    ];
}

echo "Source {$w}x{$h}, frames={$frameCount}, size={$size}\n";
$bbox = truckBBox($src, $w, $h);
$mask = truckMask($src, $bbox);
echo sprintf(
    "bbox x %.1f–%.1f%% y %.1f–%.1f%%\n",
    100 * $bbox['minX'] / $w,
    100 * $bbox['maxX'] / $w,
    100 * $bbox['minY'] / $h,
    100 * $bbox['maxY'] / $h
);

$empty = imagecreatetruecolor($w, $h);
imagesavealpha($empty, true);
imagealphablending($empty, false);
imagecopy($empty, $src, 0, 0, 0, 0, $w, $h);
imagealphablending($empty, true);
paintOutTruck($empty, $src, $bbox, $mask);
imagepng($empty, $repo.'/public/downloads/traklo-icon-empty.png');

$truck = extractTruck($src, $bbox, $mask);
imagepng($truck, $repo.'/public/downloads/traklo-truck-sprite.png');

$emptySized = resizeCopy($empty, $size, $size);
$truckW = max(24, (int) round($size * ($bbox['maxX'] - $bbox['minX'] + 1) / $w));
$truckH = max(16, (int) round($size * ($bbox['maxY'] - $bbox['minY'] + 1) / $h));
$truckSized = resizeCopy($truck, $truckW, $truckH);

// Path: left pin → park (no rotation — truck always faces right like the icon art)
$route = [
    [0.30, 0.39],
    [0.36, 0.44],
    [0.43, 0.46],
    [0.475, 0.425],
];

$manifest = [
    'size' => $size,
    'frames' => $frameCount,
    'duration_ms' => 1700,
    'final' => '/downloads/traklo-icon.png',
    'files' => [],
];

for ($i = 0; $i < $frameCount; $i++) {
    $t = $i / max(1, $frameCount - 1);
    $te = 1 - (1 - $t) * (1 - $t); // ease-out
    [$cx, $cy] = bezierPoint($route, $te);

    $frame = imagecreatetruecolor($size, $size);
    imagesavealpha($frame, true);
    imagealphablending($frame, false);
    $transparent = imagecolorallocatealpha($frame, 0, 0, 0, 127);
    imagefill($frame, 0, 0, $transparent);
    imagealphablending($frame, true);
    imagecopy($frame, $emptySized, 0, 0, 0, 0, $size, $size);

    $dstX = (int) round($cx * $size - $truckW / 2);
    $dstY = (int) round($cy * $size - $truckH / 2);
    imagecopy($frame, $truckSized, $dstX, $dstY, 0, 0, $truckW, $truckH);

    $name = sprintf('frame-%02d.webp', $i);
    $path = $outDir.'/'.$name;
    imagewebp($frame, $path, 82);
    imagedestroy($frame);
    $manifest['files'][] = '/downloads/traklo-login-frames/'.$name;
    echo "  {$name}\n";
}

// Also keep a webp of empty for first paint
imagewebp($emptySized, $outDir.'/empty.webp', 82);
$manifest['empty'] = '/downloads/traklo-login-frames/empty.webp';

file_put_contents($outDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

// cleanup old png frames if any
foreach (glob($outDir.'/frame-*.png') ?: [] as $old) {
    @unlink($old);
}
@unlink($outDir.'/frame-final.png');

echo "Done → {$outDir}\n";
