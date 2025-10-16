<?php
/**
 * Generate Avatar Image
 * Creates a simple avatar using SVG (no GD extension required)
 */

// Get parameters from URL
$background = $_GET['background'] ?? '64748b'; // Default gray background
$color = $_GET['color'] ?? 'ffffff'; // Default white text
$name = $_GET['name'] ?? 'A'; // Default initial
$size = (int)($_GET['size'] ?? 32); // Default size

// Remove # if present in color codes
$background = ltrim($background, '#');
$color = ltrim($color, '#');

// Get first letter of name
$initial = strtoupper(substr($name, 0, 1));

// Calculate font size (about 60% of image size)
$fontSize = max(8, $size * 0.6);

// Create SVG
$svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="' . $size . '" height="' . $size . '" xmlns="http://www.w3.org/2000/svg">
  <rect width="100%" height="100%" fill="#' . $background . '"/>
  <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="' . $fontSize . '" font-weight="bold" text-anchor="middle" dominant-baseline="central" fill="#' . $color . '">' . htmlspecialchars($initial) . '</text>
</svg>';

// Set content type
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

// Output SVG
echo $svg;
?>
