<?php
$size = $_GET['size'] ?? 192;
$size = (int)$size;

// Create image
$image = imagecreatetruecolor($size, $size);

// Colors
$background = imagecolorallocate($image, 99, 102, 241); // #6366f1
$white = imagecolorallocate($image, 255, 255, 255);

// Fill background
imagefill($image, 0, 0, $background);

// Draw face circle
$faceRadius = $size * 0.25;
$faceX = $size / 2;
$faceY = $size / 2 - $size * 0.1;
imagefilledellipse($image, $faceX, $faceY, $faceRadius * 2, $faceRadius * 2, $white);

// Draw eyes
$eyeRadius = $size * 0.03;
$eyeY = $faceY - $size * 0.05;
imagefilledellipse($image, $faceX - $size * 0.08, $eyeY, $eyeRadius * 2, $eyeRadius * 2, $background);
imagefilledellipse($image, $faceX + $size * 0.08, $eyeY, $eyeRadius * 2, $eyeRadius * 2, $background);

// Draw smile
$smileRadius = $size * 0.15;
$smileY = $faceY + $size * 0.05;
imagearc($image, $faceX, $smileY, $smileRadius * 2, $smileRadius * 2, 0, 180, $background);

// Output
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');
imagepng($image);
imagedestroy($image);
?>
