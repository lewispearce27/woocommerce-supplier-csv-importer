<?php
/**
 * AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCSCI_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_wcsci_upload_csv', array($this, 'handle_upload'));
        add_action('wp_ajax_wcsci_import_products', array($this, 'handle_import'));
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
            'parent_sku' => $parent_sku
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
        
        require_once WCSCI_PLUGIN_DIR . 'includes/class-wcsci-importer.php';
        
        $importer = new WCSCI_Importer(
            $session_data['data'],
            $mapping,
            $markup,
            $session_data['product_type'],
            $session_data['product_name'],
            $session_data['parent_sku'],
            $attributes,
            $filtered_values
        );
        
        $results = $importer->import();
        
        // Clean up
        delete_transient($session_key);
        if (file_exists($session_data['filepath'])) {
            unlink($session_data['filepath']);
        }
        
        wp_send_json_success($results);
    }
}
