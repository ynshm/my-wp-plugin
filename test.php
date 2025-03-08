
<?php
// Simple test script to verify PHP is working
echo "PHP is working! Version: " . PHP_VERSION . "\n";

// Test includes directory access
$includes_dir = __DIR__ . '/includes';
if (file_exists($includes_dir)) {
    echo "Includes directory exists.\n";
    
    // Test file access and parsing
    $files = scandir($includes_dir);
    echo "Files in includes directory:\n";
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            echo "- $file\n";
            
            // Try including one PHP file to test for syntax errors
            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                try {
                    $full_path = $includes_dir . '/' . $file;
                    include_once($full_path);
                    echo "  Successfully included $file\n";
                } catch (Exception $e) {
                    echo "  Error including $file: " . $e->getMessage() . "\n";
                }
            }
        }
    }
} else {
    echo "Includes directory does not exist!\n";
}
?>
