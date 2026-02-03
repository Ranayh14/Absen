<?php
/**
 * Script untuk memisahkan index.php menjadi file-file terpisah
 * Run: php extract_pages.php
 */

echo "Starting page extraction...\n";

$indexFile = __DIR__ . '/index.php.backup-before-refactor';
$content = file_get_contents($indexFile);

// Define page markers - these are the elseif conditions in the original file
$pages = [
    'landing' => ['start' => '<?php elseif ($page === \'landing\' || !isset($_SESSION[\'user\'])): ?>', 'end' => '<?php elseif ($page === \'login\'):'],
    'login' => ['start' => '<?php elseif ($page === \'login\'): ?>', 'end' => '<?php elseif ($page === \'register\'):'],
    'register' => ['start' => '<?php elseif ($page === \'register\'): ?>', 'end' => '<?php elseif ($page === \'forgot-password\'):'],
    'forgot-password' => ['start' => '<?php elseif ($page === \'forgot-password\'): ?>', 'end' => '<?php elseif ($page === \'verify-otp\'):'],
    'verify-otp' => ['start' => '<?php elseif ($page === \'verify-otp\'): ?>', 'end' => '<?php elseif ($page === \'reset-password\'):'],
    'reset-password' => ['start' => '<?php elseif ($page === \'reset-password\'): ?>', 'end' => '<?php elseif ($page === \'app\'):'],
    'app' => ['start' => '<?php elseif ($page === \'app\'): ?>', 'end' => '<?php elseif ($page === \'admin\'):'],
    'admin' => ['start' => '<?php elseif ($page === \'admin\'): ?>', 'end' => '<?php endif; ?>'],
];

// Extract each page
foreach ($pages as $pageName => $markers) {
    echo "Extracting page: $pageName\n";
    
    $startPos = strpos($content, $markers['start']);
    $endPos = strpos($content, $markers['end']);
    
    if ($startPos === false || $endPos === false) {
        echo "  Warning: Could not find markers for $pageName\n";
        continue;
    }
    
    // Extract content between markers
    $pageContent = substr($content, $startPos + strlen($markers['start']), $endPos - $startPos - strlen($markers['start']));
    
    // Clean up and wrap in PHP tags if needed
    $pageContent = trim($pageContent);
    if (!str_starts_with($pageContent, '<?php')) {
        $pageContent = "<?php\n// Page: $pageName\n?>\n" . $pageContent;
    }
    
    // Save to file
    $filename = __DIR__ . '/pages/' . $pageName . '.php';
    file_put_contents($filename, $pageContent);
    echo "  Saved: $filename\n";
}

echo "\nExtraction complete!\n";
echo "Next steps:\n";
echo "1. Review extracted files in pages/ folder\n";
echo "2. Replace index.php with index_new.php\n";
echo "3. Test all pages\n";
