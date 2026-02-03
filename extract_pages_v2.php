<?php
/**
 * Script untuk memisahkan semua halaman dari index.php
 * Menggunakan line numbers yang sudah diidentifikasi
 */

echo "Starting comprehensive page extraction...\n\n";

$sourceFile = __DIR__ . '/index.php.backup-before-refactor';
$lines = file($sourceFile);
$totalLines = count($lines);

echo "Total lines in source file: $totalLines\n\n";

// Define exact line ranges for each page based on the structure
$pages = [
    'landing' => ['start' => 6482, 'end' => 6725],
    'login' => ['start' => 6726, 'end' => 6790],
    'register' => ['start' => 6791, 'end' => 6920],
    'forgot-password' => ['start' => 6921, 'end' => 6958],
    'verify-otp' => ['start' => 6959, 'end' => 6994],
    'reset-password' => ['start' => 6995, 'end' => 7040],
    'app' => ['start' => 7041, 'end' => 16500],
    'admin' => ['start' => 16501, 'end' => 18050],
];

foreach ($pages as $pageName => $range) {
    echo "Extracting: $pageName (lines {$range['start']}-{$range['end']})\n";
    
    $start = $range['start'] - 1; // Convert to 0-indexed
    $end = $range['end'] - 1;
    
    if ($start >= $totalLines || $end >= $totalLines) {
        echo "  WARNING: Line range exceeds file length, skipping\n";
        continue;
    }
    
    $pageLines = array_slice($lines, $start, $end - $start + 1);
    $pageContent = implode('', $pageLines);
    
    // Clean up the content - remove the opening PHP tag if it's just the elseif
    $pageContent = preg_replace('/^<\?php\s+elseif\s*\(\s*\$page\s*===\s*[\'"]' . preg_quote($pageName, '/') . '[\'"]\s*\)\s*:\s*\?>\s*/s', '', $pageContent);
    
    // Save to file
    $filename = __DIR__ . '/pages/' . $pageName . '.php';
    file_put_contents($filename, $pageContent);
    
    $size = strlen($pageContent);
    echo "  Saved: $filename ($size bytes)\n";
}

echo "\n✅ Extraction complete!\n";
echo "\nNext steps:\n";
echo "1. Review extracted files in pages/ folder\n";
echo "2. Replace index.php with the new router\n";
echo "3. Test all pages\n";
