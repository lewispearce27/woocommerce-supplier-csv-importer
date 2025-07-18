<?php
/**
 * Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCSCI_Importer {
    
    private $data;
    private $mapping;
    private $markup;
    private $product_type;
    private $product_name;
    private $parent_sku;
    private $attributes;
    private $filtered_values;
    private $parent_id = null;
    
    public function __construct($data, $mapping, $markup, $product_type, $product_name, $parent_sku, $attributes = array(), $filtered_values = array()) {
        $this->data = $data;
        $this->mapping = $mapping;
        $this->markup = $markup;
        $this->product_type = $product_type;
        $this->product_name = $product_name;
        $this->parent_sku = $parent_sku;
        $this->attributes = $attributes;
        $this->filtered_values = $filtered_values;
    }
    
    public function set_parent_id($parent_id) {
        $this->parent_id = $parent_id;
    }
    
    public function create_parent_product() {
        // Check if exists
        $existing_id = wc_get_product_id_by_sku($this->parent_sku);
        if ($existing_id) {
            $product = wc_get_product($existing_id);
            if ($product->get_type() !== 'variable') {
                // Delete and recreate as variable
                wp_delete_post($existing_id, true);
                $product = new WC_Product_Variable();
            }
        } else {
            $product = new WC_Product_Variable();
        }
        
        $product->set_name($this->product_name);
        $product->set_sku($this->parent_sku);
        $product->set_status('publish');
        
        // Set attributes
        $product_attributes = array();
        foreach ($this->attributes as $attr_name) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name(sanitize_title($attr_name));
            $attribute->set_options($this->get_attribute_values($attr_name));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            
            $product_attributes[sanitize_title($attr_name)] = $attribute;
        }
        
        $product->set_attributes($product_attributes);
        
        return $product->save();
    }
    
    public function import() {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        if ($this->product_type === 'variable') {
            // For batch processing, parent product is created separately
            if (!$this->parent_id) {
                $this->parent_id = $this->create_parent_product();
                
                if (!$this->parent_id) {
                    $results['errors'][] = 'Failed to create parent product';
                    return $results;
                }
            }
            
            // Import variations
            foreach ($this->data as $row) {
                // Check if this row matches our filters
                if (!$this->matches_filters($row)) {
                    $results['skipped']++;
                    continue;
                }
                
                try {
                    $variation_id = $this->create_variation($this->parent_id, $row);
                    if ($variation_id) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = $e->getMessage();
                }
            }
            
        } else {
            // Import simple products
            foreach ($this->data as $row) {
                try {
                    $product_id = $this->create_simple_product($row);
                    if ($product_id) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = $e->getMessage();
                }
            }
        }
        
        return $results;
    }
    
    private function create_parent_product() {
        // Check if exists
        $existing_id = wc_get_product_id_by_sku($this->parent_sku);
        if ($existing_id) {
            $product = wc_get_product($existing_id);
            if ($product->get_type() !== 'variable') {
                // Delete and recreate as variable
                wp_delete_post($existing_id, true);
                $product = new WC_Product_Variable();
            }
        } else {
            $product = new WC_Product_Variable();
        }
        
        $product->set_name($this->product_name);
        $product->set_sku($this->parent_sku);
        $product->set_status('publish');
        
        // Set attributes
        $product_attributes = array();
        foreach ($this->attributes as $attr_name) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name(sanitize_title($attr_name));
            $attribute->set_options($this->get_attribute_values($attr_name));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            
            $product_attributes[sanitize_title($attr_name)] = $attribute;
        }
        
        $product->set_attributes($product_attributes);
        
        return $product->save();
    }
    
    private function create_variation($parent_id, $row) {
        // Get SKU
        $sku = $this->get_mapped_value($row, 'sku');
        if (empty($sku)) {
            throw new Exception('SKU is required');
        }
        
        // Check if exists
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            $variation = wc_get_product($existing_id);
            if ($variation->get_parent_id() !== $parent_id) {
                throw new Exception('SKU already exists for different product');
            }
        } else {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parent_id);
        }
        
        $variation->set_sku($sku);
        
        // Set attributes
        $attributes = array();
        foreach ($this->attributes as $attr_name) {
            $attr_key = sanitize_title($attr_name);
            $attr_value = isset($row[$attr_name]) ? trim($row[$attr_name]) : '';
            if (!empty($attr_value)) {
                $attributes[$attr_key] = $attr_value;
            }
        }
        $variation->set_attributes($attributes);
        
        // Set price
        $cost_price = $this->get_mapped_value($row, 'cost_price');
        if ($cost_price) {
            $cost = floatval($cost_price);
            $price = $cost + ($cost * ($this->markup / 100));
            $variation->set_regular_price($price);
            $variation->update_meta_data('_cost_price', $cost);
        }
        
        // Set other fields
        if ($description = $this->get_mapped_value($row, 'description')) {
            $variation->set_description($description);
        }
        
        // Stock management at parent level for variable products
        $variation->set_manage_stock(false);
        $variation->set_stock_status('instock');
        
        return $variation->save();
    }
    
    private function create_simple_product($row) {
        $sku = $this->get_mapped_value($row, 'sku');
        if (empty($sku)) {
            throw new Exception('SKU is required');
        }
        
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            $product = wc_get_product($existing_id);
        } else {
            $product = new WC_Product_Simple();
        }
        
        $product->set_sku($sku);
        
        if ($name = $this->get_mapped_value($row, 'name')) {
            $product->set_name($name);
        }
        
        if ($description = $this->get_mapped_value($row, 'description')) {
            $product->set_description($description);
        }
        
        // Set price
        $cost_price = $this->get_mapped_value($row, 'cost_price');
        if ($cost_price) {
            $cost = floatval($cost_price);
            $price = $cost + ($cost * ($this->markup / 100));
            $product->set_regular_price($price);
            $product->update_meta_data('_cost_price', $cost);
        }
        
        // Set stock
        if ($stock = $this->get_mapped_value($row, 'stock')) {
            $product->set_stock_quantity(intval($stock));
            $product->set_manage_stock(true);
        }
        
        $product->set_status('publish');
        
        return $product->save();
    }
    
    private function get_mapped_value($row, $field) {
        if (!isset($this->mapping[$field]) || empty($this->mapping[$field])) {
            return '';
        }
        
        $column = $this->mapping[$field];
        return isset($row[$column]) ? trim($row[$column]) : '';
    }
    
    private function matches_filters($row) {
        if (empty($this->filtered_values)) {
            return true;
        }
        
        foreach ($this->attributes as $attr_name) {
            if (isset($this->filtered_values[$attr_name]) && !empty($this->filtered_values[$attr_name])) {
                $row_value = isset($row[$attr_name]) ? trim($row[$attr_name]) : '';
                if (!in_array($row_value, $this->filtered_values[$attr_name])) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function get_attribute_values($attr_name) {
        if (isset($this->filtered_values[$attr_name]) && !empty($this->filtered_values[$attr_name])) {
            return $this->filtered_values[$attr_name];
        }
        
        // Get all unique values from data
        $values = array();
        foreach ($this->data as $row) {
            if (isset($row[$attr_name]) && !empty($row[$attr_name])) {
                $values[] = trim($row[$attr_name]);
            }
        }
        
        return array_unique($values);
    }
}
