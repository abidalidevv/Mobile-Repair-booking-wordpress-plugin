<?php
/**
 * LXCELL Supplier Provider Adapter for EFIX Repair Booking Form
 *
 * Implements RBF_Supplier_Interface for LXCELL (Dubai & International).
 * Supports public catalog inspection, structured B2B CSV price feed parsing,
 * and normalized product extraction without web scraping or fake APIs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Supplier_LXCELL implements RBF_Supplier_Interface {

    const SUPPLIER_ID = 'lxcell';
    const SUPPLIER_NAME = 'LXCELL (Dubai)';
    const BASE_URL = 'https://lxcell.com';

    public function get_id() {
        return self::SUPPLIER_ID;
    }

    public function get_name() {
        return self::SUPPLIER_NAME;
    }

    public function get_currency() {
        return 'AED';
    }

    public function get_status() {
        global $wpdb;
        $feed_url = get_option('rbf_lxcell_feed_url', '');
        $feed_configured = !empty($feed_url);

        $has_cached_wholesale = false;
        if ($wpdb) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rbf_supplier_prices WHERE supplier_id = %s AND current_price > 0 AND verification_status = 'verified'",
                self::SUPPLIER_ID
            ));
            $has_cached_wholesale = ($count && intval($count) > 0);
        }

        $wholesale_available = ($feed_configured || $has_cached_wholesale);

        return array(
            'public_catalog'            => 'Available',
            'wholesale_pricing'         => 'Not available publicly',
            'wholesale_feed'            => $wholesale_available ? 'Active' : 'Needs configuration',
            'provider_available'        => true,
            'public_catalog_available'  => true,
            'wholesale_price_available' => $wholesale_available,
            'status'                    => $wholesale_available ? 'configured' : 'needs_configuration',
            'badge_type'                => $wholesale_available ? 'active' : 'warning',
            'badge_text'                => $wholesale_available ? 'Wholesale Feed Active' : 'Catalog Discovered — Wholesale Gated',
            'message'                   => $wholesale_available 
                ? 'Wholesale prices actively loaded from supplier feed.' 
                : 'Public catalog accessible. Commercial wholesale prices are not available publicly and require a configured B2B wholesale feed or price sheet import.'
        );
    }

    /**
     * Fetch products from LXCELL public catalog or configured B2B feed URL
     *
     * @param array $options
     * @return array Array of normalized product arrays
     */
    public function fetch_products($options = array()) {
        $products = array();

        // 1. Check if a private/authenticated B2B feed URL is configured in options
        $feed_url = get_option('rbf_lxcell_feed_url', '');
        if (!empty($feed_url)) {
            $response = wp_remote_get($feed_url, array(
                'timeout'    => 25,
                'user-agent' => 'EfixRepairBot/1.0 (WordPress; RepairBookingForm)'
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                return $this->parse_feed_data($body, 'csv');
            }
        }

        // 2. If no B2B feed configured, do NOT generate fake/zero wholesale prices from public catalog.
        // The public catalog does not expose commercial wholesale prices.
        // Return empty products and log requirement for feed configuration.
        return $products;
    }

    /**
     * Parse structured feed data (CSV or JSON) into normalized product structures
     *
     * @param string $raw_content
     * @param string $format
     * @return array
     */
    public function parse_feed_data($raw_content, $format = 'csv') {
        $normalized_products = array();

        if (empty($raw_content)) {
            return $normalized_products;
        }

        // OpenXML (.xlsx) detection (by format argument or PK zip magic header)
        if ($format === 'xlsx' || substr($raw_content, 0, 4) === "PK\x03\x04") {
            return $this->parse_xlsx_data($raw_content);
        }

        if ($format === 'json') {
            $items = json_decode($raw_content, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $prod = $this->normalize_array_item($item);
                    if ($prod) {
                        $normalized_products[] = $prod;
                    }
                }
            }
            return $normalized_products;
        }

        // CSV / TSV parsing
        $lines = preg_split('/\r\n|\r|\n/', trim($raw_content));
        if (empty($lines)) {
            return $normalized_products;
        }

        $header_line = array_shift($lines);
        $headers = str_getcsv($header_line);
        $header_map = array();

        foreach ($headers as $idx => $h) {
            $clean = strtolower(trim(str_replace(array('"', "'", '_', '-'), ' ', $h)));
            if (preg_match('/(sku|code|part number|mpn|item code)/i', $clean)) {
                $header_map['sku'] = $idx;
            } elseif (preg_match('/(product name|item name|title|description)/i', $clean)) {
                $header_map['name'] = $idx;
            } elseif (preg_match('/(model|compatible|device)/i', $clean)) {
                $header_map['model'] = $idx;
            } elseif (preg_match('/(part type|category|service|component)/i', $clean)) {
                $header_map['part_type'] = $idx;
            } elseif (preg_match('/(price|cost|aed|wholesale)/i', $clean)) {
                $header_map['price'] = $idx;
            } elseif (preg_match('/(stock|availability|qty)/i', $clean)) {
                $header_map['stock'] = $idx;
            }
        }

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (empty($row)) continue;

            $sku = isset($header_map['sku']) ? trim($row[$header_map['sku']] ?? '') : '';
            $name = isset($header_map['name']) ? trim($row[$header_map['name']] ?? '') : '';
            $model = isset($header_map['model']) ? trim($row[$header_map['model']] ?? '') : '';
            $part_type = isset($header_map['part_type']) ? trim($row[$header_map['part_type']] ?? '') : '';
            $raw_price = isset($header_map['price']) ? trim($row[$header_map['price']] ?? '0') : '0';
            $stock_val = isset($header_map['stock']) ? trim($row[$header_map['stock']] ?? 'in_stock') : 'in_stock';

            // Clean price
            $clean_price = floatval(preg_replace('/[^0-9.]/', '', $raw_price));

            if (empty($sku) && !empty($name)) {
                $sku = sanitize_title($name);
            }

            if (!empty($sku)) {
                $normalized_products[] = array(
                    'supplier_id'      => self::SUPPLIER_ID,
                    'supplier_sku'     => $sku,
                    'product_name'     => !empty($name) ? $name : $sku,
                    'model_name'       => $model,
                    'model_identifier' => $model,
                    'part_type'        => $part_type,
                    'quality_grade'    => 'OEM Grade / INCELL',
                    'price'            => $clean_price,
                    'currency'         => 'AED',
                    'stock_status'     => (strtolower($stock_val) === 'out of stock' || $stock_val === '0') ? 'out_of_stock' : 'in_stock',
                    'product_url'      => self::BASE_URL . '/product/' . sanitize_title($sku),
                    'raw_data'         => $line
                );
            }
        }

        return $normalized_products;
    }

    /**
     * Normalize a product extracted from public slug URL
     */
    private function normalize_slug_product($slug, $product_url) {
        $clean_title = ucwords(str_replace('-', ' ', $slug));
        
        $part_type = 'General Part';
        if (preg_match('/(incell|oled|lcd|screen|display)/i', $slug)) {
            $part_type = 'Screen Replacement';
        } elseif (preg_match('/(battery)/i', $slug)) {
            $part_type = 'Battery Replacement';
        } elseif (preg_match('/(flex|charge|port)/i', $slug)) {
            $part_type = 'Charging Port Repair';
        }

        return array(
            'supplier_id'      => self::SUPPLIER_ID,
            'supplier_sku'     => 'LXCELL-' . strtoupper($slug),
            'product_name'     => $clean_title,
            'model_name'       => $clean_title,
            'model_identifier' => $slug,
            'part_type'        => $part_type,
            'quality_grade'    => 'OEM Grade',
            'price'            => 0.00, // Public web pages gate wholesale price; zero requires feed/auth
            'currency'         => 'AED',
            'stock_status'     => 'in_stock',
            'product_url'      => $product_url,
            'raw_data'         => json_encode(array('slug' => $slug, 'source' => 'public_sitemap'))
        );
    }

    private function normalize_array_item($item) {
        $sku = $item['sku'] ?? $item['supplier_sku'] ?? '';
        if (empty($sku)) return null;

        return array(
            'supplier_id'      => self::SUPPLIER_ID,
            'supplier_sku'     => sanitize_text_field($sku),
            'product_name'     => sanitize_text_field($item['name'] ?? $item['product_name'] ?? $sku),
            'model_name'       => sanitize_text_field($item['model'] ?? $item['model_name'] ?? ''),
            'model_identifier' => sanitize_text_field($item['model_identifier'] ?? ''),
            'part_type'        => sanitize_text_field($item['part_type'] ?? 'Screen Replacement'),
            'quality_grade'    => sanitize_text_field($item['quality_grade'] ?? 'OEM'),
            'price'            => floatval($item['price'] ?? 0),
            'currency'         => 'AED',
            'stock_status'     => sanitize_text_field($item['stock_status'] ?? 'in_stock'),
            'product_url'      => esc_url_raw($item['product_url'] ?? (self::BASE_URL . '/product/' . $sku)),
            'raw_data'         => is_array($item) ? json_encode($item) : $item
        );
    }

    /**
     * Native, zero-dependency OpenXML (.xlsx) parser
     * Reads sharedStrings.xml and worksheet XML using native PHP (ZipArchive if available, or gzinflate fallback)
     *
     * @param string $raw_content Binary content or file path
     * @return array Normalized products
     */
    public function parse_xlsx_data($raw_content) {
        $normalized_products = array();

        // If a file path was passed, read its contents
        if (is_string($raw_content) && strlen($raw_content) < 1024 && @file_exists($raw_content)) {
            $raw_content = file_get_contents($raw_content);
        }

        if (empty($raw_content) || substr($raw_content, 0, 4) !== "PK\x03\x04") {
            return $normalized_products;
        }

        $entries = $this->extract_zip_entries($raw_content);
        if (empty($entries)) {
            return $normalized_products;
        }

        // 1. Read shared strings
        $shared_strings = array();
        if (isset($entries['xl/sharedStrings.xml'])) {
            $ss_xml = @simplexml_load_string($entries['xl/sharedStrings.xml']);
            if ($ss_xml && isset($ss_xml->si)) {
                foreach ($ss_xml->si as $si) {
                    if (isset($si->t)) {
                        $shared_strings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $str = '';
                        foreach ($si->r as $r) {
                            $str .= (string)$r->t;
                        }
                        $shared_strings[] = $str;
                    } else {
                        $shared_strings[] = '';
                    }
                }
            }
        }

        // 2. Locate worksheet (sheet1.xml or first available sheet)
        $sheet_xml_str = null;
        if (isset($entries['xl/worksheets/sheet1.xml'])) {
            $sheet_xml_str = $entries['xl/worksheets/sheet1.xml'];
        } else {
            foreach ($entries as $fname => $fdata) {
                if (strpos($fname, 'xl/worksheets/sheet') !== false) {
                    $sheet_xml_str = $fdata;
                    break;
                }
            }
        }

        if (!$sheet_xml_str) {
            return $normalized_products;
        }

        $sheet_xml = @simplexml_load_string($sheet_xml_str);
        if (!$sheet_xml || !isset($sheet_xml->sheetData) || !isset($sheet_xml->sheetData->row)) {
            return $normalized_products;
        }

        // 3. Extract rows and cell values
        $rows = array();
        foreach ($sheet_xml->sheetData->row as $r) {
            $current_row = array();
            foreach ($r->c as $c) {
                $val = (string)$c->v;
                $type = (string)$c['t'];
                if ($type === 's' && isset($shared_strings[intval($val)])) {
                    $val = $shared_strings[intval($val)];
                }
                // Cell column letter to index
                $cell_ref = (string)$c['r'];
                $col_letter = preg_replace('/[0-9]/', '', $cell_ref);
                $col_idx = 0;
                for ($ci = 0; $ci < strlen($col_letter); $ci++) {
                    $col_idx = $col_idx * 26 + (ord($col_letter[$ci]) - 64);
                }
                $current_row[$col_idx - 1] = $val;
            }
            if (!empty($current_row)) {
                $max_col = max(array_keys($current_row));
                $row_data = array();
                for ($idx = 0; $idx <= $max_col; $idx++) {
                    $row_data[$idx] = $current_row[$idx] ?? '';
                }
                $rows[] = $row_data;
            }
        }

        if (empty($rows)) {
            return $normalized_products;
        }

        // 4. Detect headers
        $header_row = array_shift($rows);
        $header_map = array();

        foreach ($header_row as $idx => $h) {
            $clean = strtolower(trim(str_replace(array('"', "'", '_', '-'), ' ', (string)$h)));
            if (preg_match('/(sku|code|part number|mpn|item code)/i', $clean)) {
                $header_map['sku'] = $idx;
            } elseif (preg_match('/(product name|item name|title|description)/i', $clean)) {
                $header_map['name'] = $idx;
            } elseif (preg_match('/(model|compatible|device)/i', $clean)) {
                $header_map['model'] = $idx;
            } elseif (preg_match('/(part type|category|service|component)/i', $clean)) {
                $header_map['part_type'] = $idx;
            } elseif (preg_match('/(price|cost|aed|wholesale)/i', $clean)) {
                $header_map['price'] = $idx;
            } elseif (preg_match('/(stock|availability|qty)/i', $clean)) {
                $header_map['stock'] = $idx;
            }
        }

        // 5. Build normalized product items
        foreach ($rows as $row) {
            $sku = isset($header_map['sku']) ? trim((string)($row[$header_map['sku']] ?? '')) : '';
            $name = isset($header_map['name']) ? trim((string)($row[$header_map['name']] ?? '')) : '';
            $model = isset($header_map['model']) ? trim((string)($row[$header_map['model']] ?? '')) : '';
            $part_type = isset($header_map['part_type']) ? trim((string)($row[$header_map['part_type']] ?? '')) : '';
            $raw_price = isset($header_map['price']) ? trim((string)($row[$header_map['price']] ?? '0')) : '0';
            $stock_val = isset($header_map['stock']) ? trim((string)($row[$header_map['stock']] ?? 'in_stock')) : 'in_stock';

            $clean_price = floatval(preg_replace('/[^0-9.]/', '', $raw_price));

            if (empty($sku) && !empty($name)) {
                $sku = sanitize_title($name);
            }

            if (!empty($sku)) {
                $normalized_products[] = array(
                    'supplier_id'      => self::SUPPLIER_ID,
                    'supplier_sku'     => $sku,
                    'product_name'     => !empty($name) ? $name : $sku,
                    'model_name'       => $model,
                    'model_identifier' => $model,
                    'part_type'        => !empty($part_type) ? $part_type : 'Screen Replacement',
                    'quality_grade'    => 'OEM Grade',
                    'price'            => $clean_price,
                    'currency'         => 'AED',
                    'stock_status'     => (strtolower($stock_val) === 'out of stock' || $stock_val === '0') ? 'out_of_stock' : 'in_stock',
                    'product_url'      => self::BASE_URL . '/product/' . sanitize_title($sku),
                    'raw_data'         => json_encode($row)
                );
            }
        }

        return $normalized_products;
    }

    /**
     * Native PKZip reader using ZipArchive or gzinflate
     */
    private function extract_zip_entries($zip_data) {
        $entries = array();

        if (class_exists('ZipArchive')) {
            $temp_file = tempnam(sys_get_temp_dir(), 'xlsx_');
            file_put_contents($temp_file, $zip_data);
            $zip = new ZipArchive();
            if ($zip->open($temp_file) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $entries[$name] = $zip->getFromIndex($i);
                }
                $zip->close();
            }
            @unlink($temp_file);
            if (!empty($entries)) return $entries;
        }

        // Pure PHP fallback via gzinflate
        $offset = 0;
        $len = strlen($zip_data);

        while ($offset + 30 <= $len) {
            $sig = substr($zip_data, $offset, 4);
            if ($sig !== "PK\x03\x04") {
                break;
            }

            $header = unpack("vversion/vflag/vmethod/vtime/vdate/Vcrc/Vcomp_size/Vuncomp_size/vname_len/vextra_len", substr($zip_data, $offset + 4, 26));
            $name_len = $header['name_len'];
            $extra_len = $header['extra_len'];
            $comp_size = $header['comp_size'];
            $method = $header['method'];

            $filename = substr($zip_data, $offset + 30, $name_len);
            $data_offset = $offset + 30 + $name_len + $extra_len;
            $compressed_data = substr($zip_data, $data_offset, $comp_size);

            if ($method === 8) {
                $uncompressed = @gzinflate($compressed_data);
            } elseif ($method === 0) {
                $uncompressed = $compressed_data;
            } else {
                $uncompressed = false;
            }

            if ($uncompressed !== false) {
                $entries[$filename] = $uncompressed;
            }

            $offset = $data_offset + $comp_size;
        }

        return $entries;
    }

}
