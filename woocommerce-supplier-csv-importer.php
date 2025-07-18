<?php
/**
 * Plugin Name: WooCommerce Supplier CSV Importer
 * Plugin URI: https://github.com/yourname/woocommerce-supplier-csv-importer
 * Description: Import supplier CSV files into WooCommerce with customizable field mapping and price markup
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wcsci
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCSCI_VERSION', '2.0.0');
define('WCSCI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSCI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCSCI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
function wcsci_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="error notice">
                <p><?php _e('WooCommerce Supplier CSV Importer requires WooCommerce to be installed and active.', 'wcsci'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

// Initialize plugin
add_action('plugins_loaded', function() {
    if (!wcsci_check_woocommerce()) {
        return;
    }
    
    // Load classes
    require_once WCSCI_PLUGIN_DIR . 'includes/class-wcsci-admin.php';
    require_once WCSCI_PLUGIN_DIR . 'includes/class-wcsci-importer.php';
    require_once WCSCI_PLUGIN_DIR . 'includes/class-wcsci-ajax.php';
    
    // Initialize
    new WCSCI_Admin();
    new WCSCI_Ajax();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    update_option('wcsci_version', WCSCI_VERSION);
});
