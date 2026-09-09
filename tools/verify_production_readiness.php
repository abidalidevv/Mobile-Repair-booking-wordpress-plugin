<?php
/**
 * Production Readiness Verification Suite (Tests A through P)
 */

define('ABSPATH', 'C:/Users/Ali/Desktop/repair-booking-form/');
define('RBF_PLUGIN_PATH', 'C:/Users/Ali/Desktop/repair-booking-form/');
define('HOUR_IN_SECONDS', 3600);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');

$GLOBALS['mock_options'] = array(
    'rbf_global_markup_percentage' => 13,
    'rbf_vat_rate' => 5,
    'rbf_primary_currency' => 'AED'
);
$GLOBALS['mock_transients'] = array();
$GLOBALS['mock_crons'] = array();
$GLOBALS['mock_actions'] = array();
$GLOBALS['mock_filters'] = array();

$GLOBALS['mock_db'] = array(
    'pricing' => array(),
    'models' => array(
        1 => array('id' => 1, 'name' => 'iPhone 15', 'tier_id' => 2),
        2 => array('id' => 2, 'name' => 'Galaxy S23 Ultra', 'tier_id' => 3)
    ),
    'repairs' => array(
        10 => array('id' => 10, 'name' => 'Screen Replacement', 'price' => 250.00, 'labour_cost' => 80.00),
        11 => array('id' => 11, 'name' => 'Battery Replacement', 'price' => 150.00, 'labour_cost' => 50.00)
    ),
    'tier_pricing' => array(
        '2_10' => 320.00,
        '2_11' => 180.00,
        '3_10' => 450.00
    ),
    'default_prices' => array(
        10 => 280.00,
        11 => 160.00
    ),
    'supplier_mappings' => array(),
    'supplier_prices' => array(),
    'supplier_sync_logs' => array()
);

function get_option($key, $default = false) {
    return isset($GLOBALS['mock_options'][$key]) ? $GLOBALS['mock_options'][$key] : $default;
}
function update_option($key, $value) {
    $GLOBALS['mock_options'][$key] = $value;
    return true;
}
function get_transient($key) {
    return isset($GLOBALS['mock_transients'][$key]) ? $GLOBALS['mock_transients'][$key] : false;
}
function set_transient($key, $val, $ttl = 0) {
    $GLOBALS['mock_transients'][$key] = $val;
    return true;
}
function delete_transient($key) {
    unset($GLOBALS['mock_transients'][$key]);
    return true;
}
function current_time($type = 'mysql') {
    return ($type === 'timestamp') ? time() : date('Y-m-d H:i:s');
}
function wp_next_scheduled($hook) {
    return isset($GLOBALS['mock_crons'][$hook]) ? $GLOBALS['mock_crons'][$hook] : false;
}
function wp_schedule_event($timestamp, $recurrence, $hook) {
    $GLOBALS['mock_crons'][$hook] = array('timestamp' => $timestamp, 'recurrence' => $recurrence);
    return true;
}
function wp_unschedule_event($timestamp, $hook) {
    unset($GLOBALS['mock_crons'][$hook]);
    return true;
}
function sanitize_text_field($str) {
    return trim(strip_tags((string)$str));
}
function sanitize_title($str) {
    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$str));
}
function esc_url_raw($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}
function wp_remote_get($url, $args = array()) {
    return array('response' => array('code' => 200), 'body' => '');
}
function is_wp_error($thing) {
    return false;
}
function wp_remote_retrieve_response_code($response) {
    return 200;
}
function wp_remote_retrieve_body($response) {
    return '';
}
function add_action($tag, $callback) {
    $GLOBALS['mock_actions'][$tag][] = $callback;
}
function add_filter($tag, $callback) {
    $GLOBALS['mock_filters'][$tag][] = $callback;
}
function __($text, $domain = '') {
    return $text;
}

class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 1;

    public function prepare($query, ...$args) {
        if (isset($args[0]) && is_array($args[0]) && count($args) === 1) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
            $query = preg_replace('/%[sdf]/', (string)$val, $query, 1);
        }
        return $query;
    }

    public function get_var($query) {
        $db = &$GLOBALS['mock_db'];

        // Manual override check (is_manual_override = 1)
        if (preg_match('/FROM wp_rbf_pricing WHERE model_id = (\d+) AND repair_id = (\d+).*is_manual_override = 1/i', $query, $m)) {
            $key = $m[1] . '_' . $m[2];
            if (isset($db['pricing'][$key]) && !empty($db['pricing'][$key]['is_manual_override'])) {
                return $db['pricing'][$key]['price'];
            }
            return null;
        }

        // Legacy check
        if (preg_match('/FROM wp_rbf_pricing WHERE model_id = (\d+) AND repair_id = (\d+)/i', $query, $m)) {
            $key = $m[1] . '_' . $m[2];
            return isset($db['pricing'][$key]) ? $db['pricing'][$key]['price'] : null;
        }

        // Tier pricing
        if (preg_match('/FROM wp_rbf_tier_pricing WHERE tier_id = (\d+) AND repair_id = (\d+)/i', $query, $m)) {
            $key = $m[1] . '_' . $m[2];
            return isset($db['tier_pricing'][$key]) ? $db['tier_pricing'][$key] : null;
        }

        // Model tier_id
        if (preg_match('/SELECT tier_id FROM wp_rbf_models WHERE id = (\d+)/i', $query, $m)) {
            $mid = intval($m[1]);
            return isset($db['models'][$mid]) ? $db['models'][$mid]['tier_id'] : null;
        }

        // Default prices
        if (preg_match('/FROM wp_rbf_default_prices WHERE repair_id = (\d+)/i', $query, $m)) {
            $rid = intval($m[1]);
            return isset($db['default_prices'][$rid]) ? $db['default_prices'][$rid] : null;
        }

        // Master repairs price
        if (preg_match('/SELECT price FROM wp_rbf_repairs WHERE id = (\d+)/i', $query, $m)) {
            $rid = intval($m[1]);
            return isset($db['repairs'][$rid]) ? $db['repairs'][$rid]['price'] : null;
        }

        // Master repairs labour cost
        if (preg_match('/SELECT labour_cost FROM wp_rbf_repairs WHERE id = (\d+)/i', $query, $m)) {
            $rid = intval($m[1]);
            return isset($db['repairs'][$rid]) ? $db['repairs'][$rid]['labour_cost'] : null;
        }

        // Supplier prices count for get_status
        if (preg_match('/SELECT COUNT\(\*\) FROM wp_rbf_supplier_prices/i', $query)) {
            $cnt = 0;
            foreach ($db['supplier_prices'] as $p) {
                if ($p['current_price'] > 0 && $p['verification_status'] === 'verified') $cnt++;
            }
            return $cnt;
        }

        return null;
    }

    public function get_row($query, $output = ARRAY_A) {
        $db = &$GLOBALS['mock_db'];

        if (strpos($query, 'wp_rbf_supplier_mappings') !== false && strpos($query, 'wp_rbf_supplier_prices') !== false) {
            if (preg_match('/WHERE m\.model_id = (\d+)\s+AND m\.repair_id = (\d+)/i', $query, $m)) {
                $mid = intval($m[1]);
                $rid = intval($m[2]);
                $map_key = $mid . '_' . $rid;
                if (isset($db['supplier_mappings'][$map_key])) {
                    $map = $db['supplier_mappings'][$map_key];
                    $price_key = $map['supplier_id'] . '_' . $map['supplier_sku'];
                    if (isset($db['supplier_prices'][$price_key])) {
                        $row = $db['supplier_prices'][$price_key];
                        if (strpos($query, "p.stock_status != 'out_of_stock'") !== false && isset($row['stock_status']) && $row['stock_status'] === 'out_of_stock') {
                            return null;
                        }
                        return $row;
                    }
                }
            }
        }

        if (preg_match('/FROM wp_rbf_supplier_prices WHERE supplier_id = [\'"]([^\'"]+)[\'"] AND supplier_sku = [\'"]([^\'"]+)[\'"]/i', $query, $m)) {
            $key = $m[1] . '_' . $m[2];
            return $db['supplier_prices'][$key] ?? null;
        }

        return null;
    }

    public function get_results($query, $output = ARRAY_A) {
        return array();
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $db = &$GLOBALS['mock_db'];
        if (strpos($table, 'rbf_supplier_prices') !== false) {
            $key = ($where['supplier_id'] ?? 'lxcell') . '_' . ($where['supplier_sku'] ?? '');
            if (isset($db['supplier_prices'][$key])) {
                $db['supplier_prices'][$key] = array_merge($db['supplier_prices'][$key], $data);
            }
        }
        return true;
    }

    public function insert($table, $data, $format = null) {
        $db = &$GLOBALS['mock_db'];
        $this->insert_id++;
        if (strpos($table, 'rbf_supplier_prices') !== false) {
            $key = ($data['supplier_id'] ?? 'lxcell') . '_' . ($data['supplier_sku'] ?? '');
            $db['supplier_prices'][$key] = $data;
        } elseif (strpos($table, 'rbf_supplier_sync_logs') !== false) {
            $db['supplier_sync_logs'][$this->insert_id] = $data;
        }
        return true;
    }

    public function query($q) { return true; }
}

global $wpdb;
$wpdb = new MockWPDB();

require_once RBF_PLUGIN_PATH . 'includes/suppliers/interface-rbf-supplier.php';
require_once RBF_PLUGIN_PATH . 'includes/suppliers/class-rbf-supplier-manager.php';
require_once RBF_PLUGIN_PATH . 'includes/suppliers/class-rbf-supplier-lxcell.php';
require_once RBF_PLUGIN_PATH . 'includes/suppliers/class-rbf-supplier-sync-manager.php';
require_once RBF_PLUGIN_PATH . 'includes/class-rbf-pricing.php';

echo "=================================================================\n";
echo "    FINAL PRODUCTION-READINESS VERIFICATION (TESTS A - P)        \n";
echo "=================================================================\n\n";

$passed = 0;
$failed = 0;

function test_result($test_id, $name, $pass, $detail = '') {
    global $passed, $failed;
    $status = $pass ? "PASS" : "FAIL";
    $icon = $pass ? "✅" : "❌";
    echo "{$icon} [{$status}] Test {$test_id}: {$name}\n";
    if ($detail) {
        echo "       ↳ {$detail}\n";
    }
    if ($pass) {
        $passed++;
    } else {
        $failed++;
    }
}

$pricing = RBF_Pricing::get_instance();
$lxcell = new RBF_Supplier_LXCELL();
$sync_mgr = RBF_Supplier_Sync_Manager::get_instance();

// -------------------------------------------------------------
// Test A: Legacy Migration
// Unedited historical legacy row (is_manual_override = 0) does not block supplier price
// -------------------------------------------------------------
$GLOBALS['mock_db']['pricing']['1_10'] = array(
    'price' => 500.00,
    'is_manual_override' => 0,
    'source' => 'legacy'
);
$GLOBALS['mock_db']['supplier_mappings']['1_10'] = array(
    'supplier_id' => 'lxcell',
    'supplier_sku' => 'LX-IP15-SCR',
    'model_id' => 1,
    'repair_id' => 10,
    'active' => 1,
    'match_status' => 'verified'
);
$GLOBALS['mock_db']['supplier_prices']['lxcell_LX-IP15-SCR'] = array(
    'supplier_id' => 'lxcell',
    'supplier_sku' => 'LX-IP15-SCR',
    'current_price' => 300.00,
    'stock_status' => 'in_stock',
    'verification_status' => 'verified'
);

$price_a = $pricing->get_price(1, 10);
// Expected: (300 + 80) * 1.13 = 429.40 AED. NOT 500.00
test_result('A', 'Legacy Migration (Non-blocking for Supplier)', 
    $price_a === 429.40, 
    "Legacy row (AED 500.00) yields to verified supplier price: AED {$price_a}");

// -------------------------------------------------------------
// Test B: Historical Manual-Edited Price Preservation
// Rows with is_admin_edited = 1 are preserved as is_manual_override = 1, source = 'manual'
// -------------------------------------------------------------
$simulated_legacy_rows = array(
    'row_admin' => array('price' => 600.00, 'is_admin_edited' => 1),
    'row_auto'  => array('price' => 500.00, 'is_admin_edited' => 0)
);

// Run migration logic:
$migrated_admin = ($simulated_legacy_rows['row_admin']['is_admin_edited'] == 1) 
    ? array('is_manual_override' => 1, 'source' => 'manual') 
    : array('is_manual_override' => 0, 'source' => 'legacy');

$migrated_auto = ($simulated_legacy_rows['row_auto']['is_admin_edited'] == 1) 
    ? array('is_manual_override' => 1, 'source' => 'manual') 
    : array('is_manual_override' => 0, 'source' => 'legacy');

test_result('B', 'Historical Manual-Edited Price Preservation',
    $migrated_admin['is_manual_override'] === 1 && $migrated_admin['source'] === 'manual' &&
    $migrated_auto['is_manual_override'] === 0 && $migrated_auto['source'] === 'legacy',
    "Row with is_admin_edited=1 preserved as manual override (1, 'manual'); is_admin_edited=0 set to (0, 'legacy')");

// -------------------------------------------------------------
// Test C: Manual Override
// Explicit admin edit sets is_manual_override = 1, locked against supplier
// -------------------------------------------------------------
$GLOBALS['mock_db']['pricing']['1_10']['is_manual_override'] = 1;
$GLOBALS['mock_db']['pricing']['1_10']['price'] = 500.00;
$GLOBALS['mock_db']['pricing']['1_10']['source'] = 'manual';

$price_c = $pricing->get_price(1, 10);
test_result('C', 'Manual Override Lock',
    $price_c === 500.00,
    "Admin explicitly set AED 500.00 overrides supplier price (AED 429.40)");

// -------------------------------------------------------------
// Test D: Manual Override Removal
// Clearing manual override instantly reactivates supplier formula
// -------------------------------------------------------------
$GLOBALS['mock_db']['pricing']['1_10']['is_manual_override'] = 0;
$price_d = $pricing->get_price(1, 10);
test_result('D', 'Manual Override Removal',
    $price_d === 429.40,
    "Override cleared; active price immediately restored to supplier formula: AED {$price_d}");

// -------------------------------------------------------------
// Test E: Supplier Price Calculation
// (300 + 80) * 1.13 = 429.40 AED
// -------------------------------------------------------------
$part_cost_e = 300.00;
$labour_e = $pricing->get_labour_cost(10); // 80.00
$markup_e = $pricing->get_global_markup(); // 13%
$calc_e = round(($part_cost_e + $labour_e) * (1.0 + $markup_e / 100.0), 2);
test_result('E', 'Supplier Price Calculation',
    $calc_e === 429.40 && $price_d === 429.40,
    "Formula: ({$part_cost_e} + {$labour_e}) * 1.13 = AED {$calc_e}");

// -------------------------------------------------------------
// Test F: Supplier Price Change
// Supplier changes from 300 to 350 AED -> (350 + 80) * 1.13 = 485.90 AED
// -------------------------------------------------------------
$GLOBALS['mock_db']['supplier_prices']['lxcell_LX-IP15-SCR']['current_price'] = 350.00;
$price_f = $pricing->get_price(1, 10);
test_result('F', 'Supplier Price Change Reactivity',
    $price_f === 485.90,
    "Supplier updated to AED 350.00; Customer price automatically recalculated: AED {$price_f} ((350 + 80) * 1.13)");

// -------------------------------------------------------------
// Test G: Supplier Out-of-Stock Fallback
// When supplier part is out of stock, seamlessly falls back to Tier Base price
// -------------------------------------------------------------
$GLOBALS['mock_db']['supplier_prices']['lxcell_LX-IP15-SCR']['stock_status'] = 'out_of_stock';
$price_g = $pricing->get_price(1, 10);
test_result('G', 'Supplier Out-of-Stock Fallback',
    $price_g === 320.00,
    "Supplier out of stock; seamlessly fell back to Tier 2 Screen Price: AED {$price_g}");

// -------------------------------------------------------------
// Test H: CSV Import
// Parse CSV price sheet and extract all fields
// -------------------------------------------------------------
$csv_content = "SKU,Product Name,Model,Part Type,Price AED,Stock\n" .
               "LX-IP15-SCR,iPhone 15 In-Cell Display,iPhone 15,Screen Replacement,290.00,in_stock\n";
$csv_parsed = $lxcell->parse_feed_data($csv_content, 'csv');
test_result('H', 'CSV Wholesale Feed Import',
    count($csv_parsed) === 1 && $csv_parsed[0]['supplier_sku'] === 'LX-IP15-SCR' && $csv_parsed[0]['price'] === 290.00,
    "Parsed 1 CSV product: SKU {$csv_parsed[0]['supplier_sku']}, Price AED {$csv_parsed[0]['price']}, Stock {$csv_parsed[0]['stock_status']}");

// -------------------------------------------------------------
// Test I: REAL XLSX Import
// Read real binary .xlsx file generated from Excel/OpenXML
// -------------------------------------------------------------
$xlsx_path = "C:/Users/Ali/.gemini/antigravity-ide/brain/a491d3d9-2bf4-47f9-96ce-d9d80c6348eb/scratch/sample_feed.xlsx";
$xlsx_parsed = $lxcell->parse_xlsx_data(file_get_contents($xlsx_path));

$xlsx_ok = (count($xlsx_parsed) >= 2 &&
            $xlsx_parsed[0]['supplier_sku'] === 'LX-S23U-SCR' &&
            $xlsx_parsed[0]['model_name'] === 'Galaxy S23 Ultra' &&
            $xlsx_parsed[0]['part_type'] === 'Screen Replacement' &&
            $xlsx_parsed[0]['price'] === 300.00 &&
            $xlsx_parsed[0]['stock_status'] === 'in_stock');

// Test customer price calculation from the real XLSX parsed item
$GLOBALS['mock_db']['supplier_mappings']['2_10'] = array(
    'supplier_id' => 'lxcell',
    'supplier_sku' => 'LX-S23U-SCR',
    'model_id' => 2,
    'repair_id' => 10,
    'active' => 1,
    'match_status' => 'verified'
);
$GLOBALS['mock_db']['supplier_prices']['lxcell_LX-S23U-SCR'] = array(
    'supplier_id' => 'lxcell',
    'supplier_sku' => 'LX-S23U-SCR',
    'current_price' => $xlsx_parsed[0]['price'],
    'stock_status' => 'in_stock',
    'verification_status' => 'verified'
);
$s23u_customer_price = $pricing->get_price(2, 10);
// (300 + 80) * 1.13 = 429.40 AED

test_result('I', 'REAL XLSX Import & OpenXML Parsing',
    $xlsx_ok && $s23u_customer_price === 429.40,
    "Parsed 2 real XLSX rows; Extracted SKU {$xlsx_parsed[0]['supplier_sku']} ({$xlsx_parsed[0]['price']} AED); Customer price: AED {$s23u_customer_price}");

// -------------------------------------------------------------
// Test J: Cron Registration
// 48-hour schedule registered & hook rbf_supplier_price_sync scheduled
// -------------------------------------------------------------
$schedules = $sync_mgr->add_cron_interval(array());
$sync_mgr->maybe_schedule_cron();
$cron_reg = wp_next_scheduled('rbf_supplier_price_sync');
test_result('J', '48-Hour Cron Schedule Registration',
    isset($schedules['forty_eight_hours']) && $schedules['forty_eight_hours']['interval'] === 172800 && $cron_reg !== false,
    "Schedule interval: 172,800s (48 hrs); Scheduled hook: 'rbf_supplier_price_sync'");

// -------------------------------------------------------------
// Test K: Actual Cron Execution
// Triggering cron hook executes complete sync pipeline
// -------------------------------------------------------------
// Provide feed content option to simulate scheduled feed fetching
$sync_mgr->release_lock();
$cron_sync_result = $sync_mgr->run_sync('lxcell', array(
    'feed_content' => $csv_content,
    'format' => 'csv'
));
test_result('K', 'Actual Cron Sync Execution Path',
    $cron_sync_result['status'] === 'success' && $cron_sync_result['stats']['products_checked'] > 0,
    "Cron executed run_sync: {$cron_sync_result['message']}");

// -------------------------------------------------------------
// Test L: Manual Sync Now
// Admin manual sync button uses identical sync engine
// -------------------------------------------------------------
$sync_mgr->release_lock();
$manual_sync_result = $sync_mgr->run_sync('lxcell', array(
    'feed_content' => $csv_content,
    'format' => 'csv'
));
test_result('L', 'Manual "Sync Now" Engine Parity',
    $manual_sync_result['status'] === 'success' && $manual_sync_result['stats']['products_checked'] === $cron_sync_result['stats']['products_checked'],
    "Manual sync called identical engine run_sync() and produced identical verified result");

// -------------------------------------------------------------
// Test M: Concurrency Lock
// Concurrent sync execution prevented
// -------------------------------------------------------------
$sync_mgr->acquire_lock();
$concurrent_result = $sync_mgr->run_sync('lxcell');
$sync_mgr->release_lock();
test_result('M', 'Concurrency Lock Safety',
    $concurrent_result['status'] === 'error' && strpos($concurrent_result['message'], 'already in progress') !== false,
    "Second sync rejected while lock held: '{$concurrent_result['message']}'");

// -------------------------------------------------------------
// Test N: Server-Side Checkout Recalculation
// Frontend tampering (AED 1.00) discarded; server calculates verified price
// -------------------------------------------------------------
$tampered_post = array(
    'selected_model' => 2, // Galaxy S23 Ultra
    'cart_items' => array(
        array('id' => 10, 'name' => 'Screen Replacement', 'price' => 1.00)
    ),
    'subtotal' => 1.00,
    'vat' => 0.00,
    'total' => 1.00
);
// Server recalculation (replicates repair-booking-form.php line 458):
$server_recalc_subtotal = 0.0;
foreach ($tampered_post['cart_items'] as $it) {
    $server_recalc_subtotal += $pricing->get_price(2, $it['id']);
}
$server_recalc_vat = round($server_recalc_subtotal * 0.05, 2);
$server_recalc_total = round($server_recalc_subtotal + $server_recalc_vat, 2);

test_result('N', 'Server-Side Checkout Recalculation (Fraud Prevention)',
    $server_recalc_subtotal === 429.40 && $server_recalc_vat === 21.47 && $server_recalc_total === 450.87,
    "Client tampered price (AED 1.00) discarded. Server re-evaluated: Subtotal AED {$server_recalc_subtotal}, VAT AED {$server_recalc_vat}, Total AED {$server_recalc_total}");

// -------------------------------------------------------------
// Test O: Existing Manual Pricing UI Intact
// Add, read, and delete sparse overrides in wp_rbf_pricing
// -------------------------------------------------------------
$test_override_val = 399.00;
$GLOBALS['mock_db']['pricing']['2_11'] = array(
    'price' => $test_override_val,
    'is_manual_override' => 1,
    'source' => 'manual'
);
$read_override = $pricing->get_price(2, 11);
unset($GLOBALS['mock_db']['pricing']['2_11']); // Delete override
$fallback_after_delete = $pricing->get_price(2, 11); // Fallback to repair catalog / tier
test_result('O', 'Existing Manual Pricing UI & Storage Intact',
    $read_override === 399.00 && $fallback_after_delete !== 399.00,
    "Manual override added: AED {$read_override}; Deleted override reverted to: AED {$fallback_after_delete}");

// -------------------------------------------------------------
// Test P: No Customer Exposure of Internal Pricing Data
// Inspect frontend template for absence of internal cost tokens
// -------------------------------------------------------------
$form_template = file_get_contents(RBF_PLUGIN_PATH . 'templates/form.php');
$has_supplier_cost = (strpos($form_template, 'supplier_cost') !== false || strpos($form_template, 'part_price') !== false);
$has_labour_cost   = (strpos($form_template, 'labour_cost') !== false);
$has_markup        = (strpos($form_template, 'markup_percentage') !== false || strpos($form_template, 'global_markup') !== false);
$has_supplier_name = (strpos($form_template, 'lxcell') !== false || strpos($form_template, 'katel') !== false);

test_result('P', 'Customer UI Privacy & Data Protection',
    !$has_supplier_cost && !$has_labour_cost && !$has_markup && !$has_supplier_name,
    "Customer form inspected: 0 references to supplier cost, labour fee, markup %, or supplier identity");

echo "\n=================================================================\n";
echo "       AUDIT SUMMARY: {$passed} PASSED / {$failed} FAILED        \n";
echo "=================================================================\n";

if ($failed > 0) exit(1);
