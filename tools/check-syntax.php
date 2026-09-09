<?php
/**
 * PHP Syntax Checker for Repair Booking Form Plugin
 * Run this file to check for syntax errors
 */

echo "=== PHP SYNTAX CHECKER ===\n";
echo "Checking repair-booking-form.php for syntax errors...\n\n";

$file_path = __DIR__ . '/repair-booking-form.php';

if (!file_exists($file_path)) {
    echo "ERROR: File not found: $file_path\n";
    exit(1);
}

// Check file size
$file_size = filesize($file_path);
echo "File size: " . number_format($file_size) . " bytes\n";

// Check PHP syntax using php -l
echo "Running PHP syntax check...\n";
$output = shell_exec("php -l \"$file_path\" 2>&1");

if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ PHP syntax is valid!\n";
} else {
    echo "❌ PHP syntax errors found:\n";
    echo $output;
}

echo "\n=== DETAILED ANALYSIS ===\n";

// Read file content
$content = file_get_contents($file_path);
$lines = explode("\n", $content);
$total_lines = count($lines);

echo "Total lines: $total_lines\n";

// Check for common issues
$issues = [];

// Check brace balance
$brace_count = 0;
$parenthesis_count = 0;
$bracket_count = 0;

foreach ($lines as $line_num => $line) {
    $line_num++; // Convert to 1-based line numbers
    
    // Count brackets
    $brace_count += substr_count($line, '{') - substr_count($line, '}');
    $parenthesis_count += substr_count($line, '(') - substr_count($line, ')');
    $bracket_count += substr_count($line, '[') - substr_count($line, ']');
    
    // Check for potential issues around line 3589
    if ($line_num >= 3580 && $line_num <= 3600) {
        echo "Line $line_num: " . trim($line) . "\n";
        
        // Check for common syntax issues
        if (preg_match('/^\s*public\s+function\s+(\w+)\s*\(/', $line, $matches)) {
            echo "  -> Found public function: " . $matches[1] . "\n";
        }
        
        if (preg_match('/^\s*class\s+(\w+)/', $line, $matches)) {
            echo "  -> Found class: " . $matches[1] . "\n";
        }
        
        // Check for missing semicolons or braces
        if (preg_match('/^\s*[^\/\/]*[^;{}]\s*$/', $line) && 
            !preg_match('/^\s*(if|for|while|foreach|switch|class|function|public|private|protected|abstract|final|interface|trait|namespace|use|require|include)\b/', $line) &&
            !preg_match('/^\s*$/', $line) &&
            !preg_match('/^\s*\/\//', $line) &&
            !preg_match('/^\s*\*/', $line) &&
            !preg_match('/^\s*\/\*/', $line) &&
            !preg_match('/^\s*\*\//', $line)) {
            echo "  -> WARNING: Possible missing semicolon\n";
        }
    }
}

echo "\n=== BRACKET BALANCE ===\n";
echo "Braces: $brace_count\n";
echo "Parentheses: $parenthesis_count\n";
echo "Brackets: $bracket_count\n";

if ($brace_count !== 0) {
    echo "❌ Unbalanced braces detected!\n";
    $issues[] = "Unbalanced braces: $brace_count";
}

if ($parenthesis_count !== 0) {
    echo "❌ Unbalanced parentheses detected!\n";
    $issues[] = "Unbalanced parentheses: $parenthesis_count";
}

if ($bracket_count !== 0) {
    echo "❌ Unbalanced brackets detected!\n";
    $issues[] = "Unbalanced brackets: $bracket_count";
}

if (empty($issues)) {
    echo "\n✅ No obvious syntax issues detected\n";
} else {
    echo "\n❌ Issues found:\n";
    foreach ($issues as $issue) {
        echo "- $issue\n";
    }
}

echo "\n=== SYNTAX CHECK COMPLETE ===\n";
?>
