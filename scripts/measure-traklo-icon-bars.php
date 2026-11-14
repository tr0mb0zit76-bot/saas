<?php

$im = imagecreatefrompng(__DIR__.'/../public/downloads/traklo-icon.png');
$w = imagesx($im);
$h = imagesy($im);
echo "size {$w}x{$h}\n";

$rows = [];
for ($y = 0; $y < $h; $y += 2) {
    $whites = 0;
    $minX = $w;
    $maxX = 0;
    for ($x = (int) ($w * 0.12); $x < (int) ($w * 0.88); $x++) {
        $rgb = imagecolorat($im, $x, $y);
        $a = ($rgb >> 24) & 0x7F;
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        // GD alpha: 0=opaque, 127=transparent
        if ($a < 30 && $r > 215 && $g > 215 && $b > 215) {
            $whites++;
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
        }
    }
    if ($whites > 25) {
        $rows[] = [
            'y' => $y,
            'yp' => round(100 * $y / $h, 2),
            'whites' => $whites,
            'xminp' => round(100 * $minX / $w, 2),
            'xmaxp' => round(100 * $maxX / $w, 2),
        ];
    }
}

// cluster contiguous white rows into bars
$clusters = [];
$current = null;
foreach ($rows as $row) {
    if ($current === null || $row['y'] - $current['yEnd'] > 6) {
        if ($current !== null) {
            $clusters[] = $current;
        }
        $current = [
            'yStart' => $row['y'],
            'yEnd' => $row['y'],
            'xminp' => $row['xminp'],
            'xmaxp' => $row['xmaxp'],
            'maxWhites' => $row['whites'],
        ];
    } else {
        $current['yEnd'] = $row['y'];
        $current['xminp'] = min($current['xminp'], $row['xminp']);
        $current['xmaxp'] = max($current['xmaxp'], $row['xmaxp']);
        $current['maxWhites'] = max($current['maxWhites'], $row['whites']);
    }
}
if ($current !== null) {
    $clusters[] = $current;
}

foreach ($clusters as $i => $c) {
    $top = round(100 * $c['yStart'] / $h, 2);
    $bottom = round(100 * $c['yEnd'] / $h, 2);
    $height = round($bottom - $top, 2);
    echo sprintf(
        "cluster %d: y %.2f%%-%.2f%% (h=%.2f%%) x %.2f%%-%.2f%% whites=%d\n",
        $i,
        $top,
        $bottom,
        $height,
        $c['xminp'],
        $c['xmaxp'],
        $c['maxWhites'],
    );
}
