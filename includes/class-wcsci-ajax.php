<?php
/**
 * AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCSCI_Ajax {
    
    private $batch_size = 500; // Process 500 rows at a time
    
    public function __construct() {
        add_action('wp_ajax_wcsci_upload_csv', array($this, 'handle_upload'));
        add_action('wp_ajax_wcsci_import_products', array($this, 'handle_import'));
        add_action('wp_ajax_wcsci_import_batch', array($this, 'handle_import_batch'));
    }
    
    public function handle_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcsci_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['csv_file'];
        $product_type = sanitize_text_field($_POST['product_type']);
        $product_name = sanitize_text_field($_POST['product_name']);
        $parent_sku = sanitize_text_field($_POST['parent_sku']);
        
        // Move uploaded file
        $upload_dir = wp_upload_dir();
        $filename = 'wcsci_' . time() . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error('Failed to save file');
        }
        
        // Parse CSV
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            wp_send_json_error('Could not read file');
        }
        
        // Get headers
        $headers = fgetcsv($handle);
        
        // Get sample data and all data for filtering
        $data = array();
        $sample_data = array();
        $row_count = 0;
        $attribute_values = array();
        
        // Initialize attribute value arrays
        foreach ($headers as $header) {
            $attribute_values[$header] = array();
        }
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $row_data = array_combine($headers, $row);
                
                // Collect unique attribute values
                foreach ($row_data as $key => $value) {
                    if (!empty($value) && !in_array($value, $attribute_values[$key])) {
                        $attribute_values[$key][] = $value;
                    }
                }
                
                // Store full data (limited for memory)
                if ($row_count < 1000) {
                    $data[] = $row_data;
                }
                
                if ($row_count < 5) {
                    $sample_data[] = $row_data;
                }
                $row_count++;
            }
        }
        
        fclose($handle);
        
        // Store in session/transient
        $session_key = 'wcsci_import_' . get_current_user_id();
        set_transient($session_key, array(
            'filepath' => $filepath,
            'headers' => $headers,
            'data' => $data,
            'product_type' => $product_type,
            'product_name' => $product_name,
            'parent_sku' => $parent_sku,
            'attribute_values' => $attribute_values,
            'total_rows' => $row_count
        ), HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'headers' => $headers,
            'sample_data' => $sample_data,
            'total_rows' => $row_count,
            'attribute_values' => $attribute_values
        ));
    }
    
    public function handle_import() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcsci_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $session_key = 'wcsci_import_' . get_current_user_id();
        $session_data = get_transient($session_key);
        
        if (!$session_data) {
            wp_send_json_error('Session expired');
        }
        
        $mapping = json_decode(stripslashes($_POST['mapping']), true);
        $attributes = json_decode(stripslashes($_POST['attributes']), true);
        $filtered_values = json_decode(stripslashes($_POST['filtered_values']), true);
        $markup = floatval($_POST['markup']);
        
        // Store import settings for batch processing
        set_transient($session_key . '_settings', array(
            'mapping' => $mapping,
            'attributes' => $attributes,
            'filtered_values' => $filtered_values,
            'markup' => $markup
        ), HOUR_IN_SECONDS);
        
        // Initialize batch processing
        $total_rows = $session_data['total_rows'];
        $total_batches = ceil($total_rows / $this->batch_size);
        
        // Initialize results tracking
        set_transient($session_key . '_results', array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array(),
            'parent_id' => null
        ), HOUR_IN_SECONDS);
        
        // For variable products, create parent product first
        if ($session_data['product_type'] === 'variable') {
            require_once WCSCI_PLUGIN_DIR . 'includes/class-wcsci-importer.php';
            
            $importer = new WCSCI_Importer(
                array(),
                $mapping,
                $markup,
                'variable',
                $session_data['product_name'],
                $session_data['parent_sku'],
                $attributes,
                $filtered_values
            );
            
            $parent_id = $importer->create_parent_product();
            
            if (!$parent_id) {
                wp_send_json_error('Failed to create parent product');
            }
            
            $results = get_transient($session_key . '_results');
            $results['parent_id'] = $parent_id;
            set_transient($session_key . '_results', $results, HOUR_IN_SECONDS);
        }
        
        wp_send_json_success(array(
            'total_batches' => $total_batches,
            'batch_size' => $this->batch_size,
            'total_rows' => $total_rows
        ));
    }
    
    public function handle_import_batch() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcsci_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $batch_number = intval($_POST['batch_number']);
        $session_key = 'wcsci_import_' . get_current_user_id();
        
        $session_data = get_transient($session_key);
        $settings = get_transient($session_key . '_settings');
        $results = get_transient($session_key . '_results');
        
        if (!$session_data || !$settings || !$results) {
            wp_send_json_error('Session expired');
        }
        
        // Calculate offset
        $offset = ($batch_number - 1) * $this->batch_size;
        
        // Read batch from CSV
        $handle = fopen($session_data['filepath'], 'r');
        if (!$handle) {
            wp_send_json_error('Could not read CSV file');
        }
        
        // Skip to offset
        $headers = fgetcsv($handle);
        for ($i = 0; $i < $offset; $i++) {
            fgetcsv($handle);
        }
        
        // Read batch data
        $batch_data = array();
        $count = 0;
        while (($row = fgetcsv($handle)) !== false && $count < $this->batch_size) {
            if (count($row) === count($headers)) {
                $batch_data[] = array_combine($headers, $row);
                $count++;
            }
        }
        
        fclose($handle);
        
        // Process batch
        require_once WCSCI_PLUGIN_DIR . 'includes/class-wcsci-importer.php';
        
        $importer = new WCSCI_Importer(
            $batch_data,
            $settings['mapping'],
            $settings['markup'],
            $session_data['product_type'],
            $session_data['product_name'],
            $session_data['parent_sku'],
            $settings['attributes'],
            $settings['filtered_values']
        );
        
        // Set parent ID for variable products
        if ($session_data['product_type'] === 'variable' && $results['parent_id']) {
            $importer->set_parent_id($results['parent_id']);
        }
        
        $batch_results = $importer->import();
        
        // Update cumulative results
        $results['success'] += $batch_results['success'];
        $results['failed'] += $batch_results['failed'];
        $results['skipped'] += $batch_results['skipped'];
        $results['errors'] = array_merge($results['errors'], array_slice($batch_results['errors'], 0, 50));
        
        set_transient($session_key . '_results', $results, HOUR_IN_SECONDS);
        
        // Check if this is the last batch
        $is_last_batch = $batch_number >= ceil($session_data['total_rows'] / $this->batch_size);
        
        if ($is_last_batch) {
            // Sync variable product
            if ($session_data['product_type'] === 'variable' && $results['parent_id']) {
                WC_Product_Variable::sync($results['parent_id']);
            }
            
            // Clean up
            delete_transient($session_key);
            delete_transient($session_key . '_settings');
            if (file_exists($session_data['filepath'])) {
                unlink($session_data['filepath']);
            }
        }
        
        wp_send_json_success(array(
            'batch_complete' => true,
            'processed' => count($batch_data),
            'cumulative_results' => $results,
            'is_last_batch' => $is_last_batch
        ));
    }
}
