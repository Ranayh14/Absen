<?php
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

$name = $_GET['name'] ?? 'A';
$background = $_GET['background'] ?? '6366f1';
$color = $_GET['color'] ?? 'fff';
$size = $_GET['size'] ?? '64';

// Clean and format name
$name = htmlspecialchars(urldecode($name));
$initials = '';
$words = explode(' ', $name);
foreach ($words as $word) {
    if (!empty($word)) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
}
if (empty($initials)) {
    $initials = 'A';
}
$initials = substr($initials, 0, 2); // Max 2 characters

// Generate SVG
$svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '" xmlns="http://www.w3.org/2000/svg">
    <rect width="' . $size . '" height="' . $size . '" fill="#' . $background . '" rx="' . ($size / 2) . '"/>
    <text x="50%" y="50%" font-family="Inter, -apple-system, BlinkMacSystemFont, sans-serif" font-size="' . ($size * 0.4) . '" font-weight="600" fill="#' . $color . '" text-anchor="middle" dominant-baseline="central">' . $initials . '</text>
</svg>';

echo $svg;
?>
