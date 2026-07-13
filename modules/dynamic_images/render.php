<?php
declare(strict_types=1);

// This endpoint dynamically creates an image with text.
// If 'text' is empty or still the literal {{first_name}} macro, we fall back to "there" or just "Hi".

$text = trim($_GET['text'] ?? '');
if ($text === '' || strpos($text, '{{') !== false) {
    $text = "there";
}

$width = 400;
$height = 250;

$image = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($image, 238, 242, 255); // #eef2ff (indigo-50)
$box = imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 49, 46, 129); // #312e81 (indigo-900)
$shadow_color = imagecolorallocate($image, 199, 210, 254); // #c7d2fe (indigo-200)

imagefill($image, 0, 0, $bg);

// Draw a simple "card" in the middle
imagefilledrectangle($image, 50, 50, 350, 200, $shadow_color);
imagefilledrectangle($image, 45, 45, 345, 195, $box);

$greeting = "Specially for you,";
$nameLine = strtoupper($text) . "!";

$font = 5; // Built in font

// Center greeting
$fw = imagefontwidth($font) * strlen($greeting);
$x1 = (int)(($width - $fw) / 2);
imagestring($image, $font, $x1, 90, $greeting, $text_color);

// Center name
$fw2 = imagefontwidth($font) * strlen($nameLine);
$x2 = (int)(($width - $fw2) / 2);
imagestring($image, $font, $x2, 120, $nameLine, $text_color);

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
imagepng($image);
imagedestroy($image);
