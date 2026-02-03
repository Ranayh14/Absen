<?php
$file = 'd:/xampp/htdocs/Magang/Absen/pages/app.php';
$lines = file($file);
$output = [];

echo "Total lines: " . count($lines) . "\n";

// Keep 0 to 1549 (lines 1 to 1550)
for ($i = 0; $i <= 1549; $i++) {
    if (isset($lines[$i])) $output[] = $lines[$i];
}

$output[] = "// Camera attendance logic moved to assets/js/attendance.js\n";

// Keep 5007 to end (lines 5008+)
// Verify what line 5007 is
if (isset($lines[5007])) {
    echo "Line 5008 content (index 5007): " . trim($lines[5007]) . "\n";
}

for ($i = 5007; $i < count($lines); $i++) {
    if (isset($lines[$i])) $output[] = $lines[$i];
}

file_put_contents($file, implode("", $output));
echo "Done modification.\n";
?>
