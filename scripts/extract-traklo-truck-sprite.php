<?php

$src = imagecreatefrompng(__DIR__.'/../public/downloads/traklo-icon.png');
$w = imagesx($src);
$h = imagesy($src);

function isBlueish(int $r, int $g, int $b): bool
{
    return $b > 140 && $b > $r + 15 && $b > $g;
}

function isWhiteish(int $r, int $g, int $b): bool
{
    return $r > 200 && $g > 200 && $b > 200;
}

// Scan truck zone (between pins, above bars)
$minX = $w;
$maxX = 0;
$minY = $h;
$maxY = 0;
for ($y = (int) ($h * 0.34); $y < (int) ($h * 0.58); $y++) {
    for ($x = (int) ($w * 0.34); $x < (int) ($w * 0.66); $x++) {
        $rgb = imagecolorat($src, $x, $y);
        $a = ($rgb >> 24) & 0x7F;
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($a < 40 && isWhiteish($r, $g, $b)) {
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }
    }
}

$pad = 8;
$minX = max(0, $minX - $pad);
$minY = max(0, $minY - $pad);
$maxX = min($w - 1, $maxX + $pad);
$maxY = min($h - 1, $maxY + $pad);
$tw = $maxX - $minX + 1;
$th = $maxY - $minY + 1;

echo sprintf("truck bbox: x=%.1f%%-%.1f%% y=%.1f%%-%.1f%% (%dx%d)\n",
    100 * $minX / $w, 100 * $maxX / $w, 100 * $minY / $h, 100 * $maxY / $h, $tw, $th);

$truck = imagecreatetruecolor($tw, $th);
imagesavealpha($truck, true);
$transparent = imagecolorallocatealpha($truck, 0, 0, 0, 127);
imagefill($truck, 0, 0, $transparent);

// Sample bubble blue near truck for cover + chroma key
$sample = imagecolorat($src, (int) ($w * 0.50), (int) ($h * 0.28));
$sr = ($sample >> 16) & 0xFF;
$sg = ($sample >> 8) & 0xFF;
$sb = $sample & 0xFF;

for ($y = 0; $y < $th; $y++) {
    for ($x = 0; $x < $tw; $x++) {
        $rgb = imagecolorat($src, $minX + $x, $minY + $y);
        $a = ($rgb >> 24) & 0x7F;
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($a > 50) {
            imagesetpixel($truck, $x, $y, $transparent);
            continue;
        }
        // Keep white truck body + soft shadow greys; drop blue road/fill
        if (isBlueish($r, $g, $b) || ($b > $r && $b > 120 && $r < 180)) {
            imagesetpixel($truck, $x, $y, $transparent);
            continue;
        }
        if ($r > 160 && $g > 160 && $b > 160) {
            imagesetpixel($truck, $x, $y, $rgb);
        } else {
            imagesetpixel($truck, $x, $y, $transparent);
        }
    }
}

$dir = __DIR__.'/../public/downloads';
imagepng($truck, $dir.'/traklo-truck-sprite.png');

// Icon without truck (paint bubble blue over truck bbox with soft edge)
$clean = imagecreatetruecolor($w, $h);
imagesavealpha($clean, true);
imagealphablending($clean, false);
imagecopy($clean, $src, 0, 0, 0, 0, $w, $h);
imagealphablending($clean, true);

$cover = imagecolorallocatealpha($clean, $sr, $sg, $sb, 0);
for ($y = $minY; $y <= $maxY; $y++) {
    for ($x = $minX; $x <= $maxX; $x++) {
        $rgb = imagecolorat($src, $x, $y);
        $a = ($rgb >> 24) & 0x7F;
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($a < 40 && (isWhiteish($r, $g, $b) || ($r > 160 && $g > 160 && $b > 160))) {
            // Only paint over truck whites, keep road (blueish already skipped)
            if (! isBlueish($r, $g, $b)) {
                imagesetpixel($clean, $x, $y, imagecolorallocate($clean, $sr, $sg, $sb));
            }
        }
    }
}

imagepng($clean, $dir.'/traklo-icon-track.png');
echo "wrote traklo-truck-sprite.png and traklo-icon-track.png\n";
echo "sample blue rgb($sr,$sg,$sb)\n";
