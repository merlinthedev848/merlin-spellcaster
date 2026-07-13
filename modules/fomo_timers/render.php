<?php
declare(strict_types=1);

// This endpoint generates a basic PNG showing time remaining.
// For true animated GIFs, a PHP GIF encoder library is typically needed.
// We'll generate a static PNG representing the time at the exact moment of open.
// (In production, an animated GIF generator class is usually bundled).

$endStr = $_GET['end'] ?? date('Y-m-d\T23:59:59');
$endTime = strtotime($endStr);
$now = time();
$diff = $endTime - $now;

if ($diff < 0) $diff = 0;

$days = floor($diff / 86400);
$hours = floor(($diff % 86400) / 3600);
$minutes = floor(($diff % 3600) / 60);
$seconds = $diff % 60;

$text = sprintf("%02d DAYS  %02d HRS  %02d MINS  %02d SECS", $days, $hours, $minutes, $seconds);
if ($diff == 0) {
    $text = "OFFER EXPIRED";
}

$width = 600;
$height = 100;

$image = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($image, 17, 24, 39); // #111827 (dark slate)
$text_color = imagecolorallocate($image, 244, 63, 94); // #f43f5e (rose-500)

imagefill($image, 0, 0, $bg);

// Using built-in font for simplicity, though TTF is better
$font = 5;
$fw = imagefontwidth($font) * strlen($text);
$fh = imagefontheight($font);

// Center the text
$x = (int)(($width - $fw) / 2);
$y = (int)(($height - $fh) / 2);

imagestring($image, $font, $x, $y, $text, $text_color);

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
imagepng($image);
imagedestroy($image);
