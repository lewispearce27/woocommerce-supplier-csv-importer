<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCSCI_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('Supplier CSV Import', 'wcsci'),
            __('Supplier CSV Import', 'wcsci'),
            'manage_woocommerce',
            'wcsci-import',
            array($this, 'render_page')
        );
    }
    
    public function enqueue_assets($hook) {
        if ('woocommerce_page_wcsci-import' !== $hook) {
            return;
        }
        
        wp_enqueue_style('wcsci-admin', WCSCI_PLUGIN_URL . 'assets/admin.css', array(), WCSCI_VERSION);
        wp_enqueue_script('wcsci-admin', WCSCI_PLUGIN_URL . 'assets/admin.js', array('jquery'), WCSCI_VERSION, true);
        
        wp_localize_script('wcsci-admin', 'wcsci_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcsci_nonce')
        ));
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div id="wcsci-container">
                <!-- Step 1: Product Type Selection -->
                <div id="step-1" class="wcsci-step">
                    <h2>Step 1: Select Product Type</h2>
                    <div class="wcsci-card">
                        <p>What type of product are you importing?</p>
                        <label class="wcsci-radio-card">
                            <input type="radio" name="product_type" value="simple" checked>
                            <div class="radio-card-content">
                                <strong>Simple Products</strong>
                                <p>Each row in the CSV will create a separate product</p>
                            </div>
                        </label>
                        <label class="wcsci-radio-card">
                            <input type="radio" name="product_type" value="variable">
                            <div class="radio-card-content">
                                <strong>Variable Product</strong>
                                <p>All rows will become variations of a single product</p>
                            </div>
                        </label>
                        <button type="button" class="button button-primary" onclick="wcsci.nextStep()">Next</button>
                    </div>
                </div>
                
                <!-- Step 2: File Upload & Product Name -->
                <div id="step-2" class="wcsci-step" style="display:none;">
                    <h2>Step 2: Upload CSV & Product Details</h2>
                    <div class="wcsci-card">
                        <div id="product-name-section" style="display:none;">
                            <label for="product_name">Product Name:</label>
                            <input type="text" id="product_name" class="regular-text" placeholder="e.g., Business Cards">
                            <p class="description">This will be used as the product title and to generate the parent SKU</p>
                            
                            <label for="parent_sku">Parent SKU:</label>
                            <input type="text" id="parent_sku" class="regular-text" readonly>
                            <p class="description">Auto-generated from product name (click to edit)</p>
                        </div>
                        
                        <label for="csv_file">Select CSV File:</label>
                        <input type="file" id="csv_file" accept=".csv" required>
                        
                        <button type="button" class="button" onclick="wcsci.prevStep()">Back</button>
                        <button type="button" class="button button-primary" onclick="wcsci.uploadFile()">Upload & Continue</button>
                    </div>
                </div>
                
                <!-- Step 3: Column Mapping -->
                <div id="step-3" class="wcsci-step" style="display:none;">
                    <h2>Step 3: Map CSV Columns</h2>
                    <div class="wcsci-card">
                        <div id="mapping-container">
                            <!-- Mapping table will be inserted here -->
                        </div>
                        <button type="button" class="button" onclick="wcsci.prevStep()">Back</button>
                        <button type="button" class="button button-primary" onclick="wcsci.processMapping()">Next</button>
                    </div>
                </div>
                
                <!-- Step 4: Select Attributes (Variable Products Only) -->
                <div id="step-4" class="wcsci-step" style="display:none;">
                    <h2>Step 4: Select Product Attributes</h2>
                    <div class="wcsci-card">
                        <p>Select which columns should be used as product variations:</p>
                        <div id="attributes-container">
                            <!-- Attribute checkboxes will be inserted here -->
                        </div>
                        <button type="button" class="button" onclick="wcsci.prevStep()">Back</button>
                        <button type="button" class="button button-primary" onclick="wcsci.processAttributes()">Next</button>
                    </div>
                </div>
                
                <!-- Step 5: Filter Variations -->
                <div id="step-5" class="wcsci-step" style="display:none;">
                    <h2>Step 5: Select Which Variations to Import</h2>
                    <div class="wcsci-card">
                        <p>Select which variation values you want to import:</p>
                        <div id="variation-filters">
                            <!-- Variation filters will be inserted here -->
                        </div>
                        <div id="variation-preview" class="notice notice-info">
                            <p><strong>Variations to create:</strong> <span id="variation-count">0</span></p>
                        </div>
                        <button type="button" class="button" onclick="wcsci.prevStep()">Back</button>
                        <button type="button" class="button button-primary" onclick="wcsci.nextStep()">Next</button>
                    </div>
                </div>
                
                <!-- Step 6: Markup & Import -->
                <div id="step-6" class="wcsci-step" style="display:none;">
                    <h2>Step 6: Set Markup & Import</h2>
                    <div class="wcsci-card">
                        <label for="markup">Price Markup (%):</label>
                        <input type="number" id="markup" value="0" min="0" step="0.01">
                        <p class="description">Percentage to add to cost price (e.g., 25 for 25% markup)</p>
                        
                        <div id="import-summary">
                            <!-- Summary will be shown here -->
                        </div>
                        
                        <button type="button" class="button" onclick="wcsci.prevStep()">Back</button>
                        <button type="button" class="button button-primary" onclick="wcsci.startImport()">Start Import</button>
                    </div>
                </div>
                
                <!-- Progress -->
                <div id="import-progress" style="display:none;">
                    <h2>Import Progress</h2>
                    <div class="wcsci-progress-bar">
                        <div class="wcsci-progress-fill"></div>
                    </div>
                    <div id="import-status"></div>
                    <div id="import-results"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
