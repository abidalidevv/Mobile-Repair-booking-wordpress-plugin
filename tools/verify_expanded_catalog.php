<?php
/**
 * Verification Test for Expanded Catalog & Images
 */

define('ABSPATH', 'C:/Users/Ali/Desktop/repair-booking-form/');
define('RBF_PLUGIN_PATH', 'C:/Users/Ali/Desktop/repair-booking-form/');
define('RBF_PLUGIN_URL', 'http://localhost/wp-content/plugins/repair-booking-form/');
define('HOUR_IN_SECONDS', 3600);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');

function add_action($tag, $callback) {}
function add_filter($tag, $callback) {}
function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
function sanitize_textarea_field($s) { return trim(strip_tags((string)$s)); }
function current_time($t) { return date('Y-m-d H:i:s'); }

$json_file = RBF_PLUGIN_PATH . 'brands_models_data.json';
if (!file_exists($json_file)) {
    die("ERROR: brands_models_data.json does not exist.\n");
}

$data = json_decode(file_get_contents($json_file), true);
if (!$data || empty($data['brands'])) {
    die("ERROR: Invalid JSON structure in brands_models_data.json\n");
}

echo "=================================================================\n";
echo "   VERIFYING EXPANDED DEVICE CATALOG & DOWNLOADED IMAGES        \n";
echo "=================================================================\n\n";

$brands_count = count($data['brands']);
$total_models = 0;
$missing_images = 0;
$valid_images = 0;

echo "Brands Breakdown:\n";
foreach ($data['brands'] as $b) {
    $m_list = $b['models'] ?? array();
    $m_count = count($m_list);
    $total_models += $m_count;
    $logo_path = RBF_PLUGIN_PATH . ltrim($b['logo'] ?? '', '/');
    $logo_exists = file_exists($logo_path) ? "✅ Logo OK" : "❌ Logo Missing ({$b['logo']})";
    echo sprintf(" - %-15s (ID: %2d): %3d models | %s\n", $b['name'], $b['id'], $m_count, $logo_exists);

    // Check models image existence
    foreach ($m_list as $m) {
        $img_rel = $m['image'] ?? '';
        $img_full = RBF_PLUGIN_PATH . ltrim($img_rel, '/');
        if (file_exists($img_full) && filesize($img_full) > 500) {
            $valid_images++;
        } else {
            $missing_images++;
        }
    }
}

echo "\n-----------------------------------------------------------------\n";
echo "Total Brands in Catalog: {$brands_count}\n";
echo "Total Models in Catalog: {$total_models}\n";
echo "Valid Images Verified on Disk: {$valid_images}\n";
echo "Missing/Fallback Images: {$missing_images}\n";
echo "Image Availability Rate: " . round(($valid_images / $total_models) * 100, 1) . "%\n";
echo "-----------------------------------------------------------------\n\n";

// Test tier assignments with class-rbf-catalog.php
require_once RBF_PLUGIN_PATH . 'includes/class-rbf-catalog.php';
$catalog = RBF_Catalog::get_instance();

$sample_checks = array(
    array('Xiaomi 15 Ultra', 'Xiaomi', 3),      // Flagship
    array('Vivo X Fold5', 'Vivo', 4),           // Premium/Foldable
    array('Tecno Phantom V Fold 2', 'Tecno', 4),// Premium/Foldable
    array('Realme GT 7', 'Realme', 3),          // Flagship
    array('Infinix Smart 8', 'Infinix', 1),     // Economy
    array('Infinix Hot 40', 'Infinix', 1),      // Economy
    array('Honor Magic 6 Pro', 'Honor', 3),     // Flagship
    array('Samsung Galaxy S24 Ultra', 'Samsung', 3), // Flagship
    array('Samsung Galaxy A15', 'Samsung', 1),  // Economy
    array('Vivo V30', 'Vivo', 2)                // Mid-Range
);

$tier_names = array(1 => 'Economy', 2 => 'Mid-Range', 3 => 'Flagship', 4 => 'Premium/Foldable');

echo "Verifying Smart Tier Classification for New/Expanded Models:\n";
$tier_pass = 0;
foreach ($sample_checks as $chk) {
    list($model, $brand, $expected_tier) = $chk;
    $guessed = $catalog->guess_tier_for_model($model, $brand);
    $ok = ($guessed === $expected_tier);
    $status = $ok ? "✅ PASS" : "❌ FAIL";
    if ($ok) $tier_pass++;
    echo sprintf(" %s %-30s (%-8s) -> %s (Expected: %s)\n", 
        $status, $model, $brand, $tier_names[$guessed], $tier_names[$expected_tier]);
}

echo "\nTier Heuristic Tests: {$tier_pass} / " . count($sample_checks) . " Passed.\n";

if ($missing_images > 10 || $tier_pass < count($sample_checks)) {
    echo "\n❌ AUDIT FAILED!\n";
    exit(1);
}

echo "\n✅ EXPANDED CATALOG VERIFICATION PASSED SUCCESSFULLY!\n";
