const wcsci = {
    currentStep: 1,
    productType: 'simple',
    csvHeaders: [],
    csvData: [],
    totalRows: 0,
    attributeValues: {},
    mapping: {},
    attributes: [],
    filteredValues: {},
    
    init() {
        // Product type change
        jQuery('input[name="product_type"]').on('change', function() {
            wcsci.productType = jQuery(this).val();
            if (wcsci.productType === 'variable') {
                jQuery('#product-name-section').show();
            } else {
                jQuery('#product-name-section').hide();
            }
        });
        
        // Auto-generate SKU from product name
        jQuery('#product_name').on('input', function() {
            const name = jQuery(this).val();
            const sku = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .substring(0, 50);
            jQuery('#parent_sku').val(sku);
        });
        
        // Make SKU editable on click
        jQuery('#parent_sku').on('click', function() {
            jQuery(this).prop('readonly', false);
        });
    },
    
    nextStep() {
        if (this.currentStep === 1) {
            // Check product type selection
            this.productType = jQuery('input[name="product_type"]:checked').val();
            if (this.productType === 'variable') {
                jQuery('#product-name-section').show();
            }
        }
        
        jQuery('.wcsci-step').hide();
        this.currentStep++;
        jQuery('#step-' + this.currentStep).show();
    },
    
    prevStep() {
        jQuery('.wcsci-step').hide();
        this.currentStep--;
        jQuery('#step-' + this.currentStep).show();
    },
    
    async uploadFile() {
        const fileInput = document.getElementById('csv_file');
        if (!fileInput.files.length) {
            alert('Please select a CSV file');
            return;
        }
        
        // Validate variable product fields
        if (this.productType === 'variable') {
            const productName = jQuery('#product_name').val().trim();
            const parentSku = jQuery('#parent_sku').val().trim();
            
            if (!productName || !parentSku) {
                alert('Please enter product name and SKU for variable product');
                return;
            }
        }
        
        const formData = new FormData();
        formData.append('action', 'wcsci_upload_csv');
        formData.append('nonce', wcsci_ajax.nonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('product_type', this.productType);
        formData.append('product_name', jQuery('#product_name').val());
        formData.append('parent_sku', jQuery('#parent_sku').val());
        
        try {
            const response = await jQuery.ajax({
                url: wcsci_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });
            
            if (response.success) {
                this.csvHeaders = response.data.headers;
                this.csvData = response.data.sample_data;
                this.totalRows = response.data.total_rows;
                this.attributeValues = response.data.attribute_values || {};
                this.showMapping();
                this.nextStep();
            } else {
                alert('Error: ' + response.data);
            }
        } catch (error) {
            alert('Upload failed: ' + error);
        }
    },
    
    showMapping() {
        let fields = {};
        
        if (this.productType === 'variable') {
            fields = {
                'sku': 'Variation SKU',
                'cost_price': 'Cost Price',
                'description': 'Description (optional)'
            };
        } else {
            fields = {
                'sku': 'SKU',
                'name': 'Product Name', 
                'cost_price': 'Cost Price',
                'description': 'Description',
                'stock': 'Stock Quantity'
            };
        }
        
        let html = '<table class="widefat">';
        html += '<thead><tr><th>WooCommerce Field</th><th>CSV Column</th><th>Sample Data</th></tr></thead>';
        html += '<tbody>';
        
        for (const [field, label] of Object.entries(fields)) {
            html += '<tr>';
            html += '<td>' + label + '</td>';
            html += '<td><select id="map_' + field + '" onchange="wcsci.updateSample(\'' + field + '\')">';
            html += '<option value="">-- Select --</option>';
            
            this.csvHeaders.forEach(header => {
                html += '<option value="' + header + '">' + header + '</option>';
            });
            
            html += '</select></td>';
            html += '<td id="sample_' + field + '"></td>';
            html += '</tr>';
        }
        
        html += '</tbody></table>';
        
        jQuery('#mapping-container').html(html);
    },
    
    updateSample(field) {
        const column = jQuery('#map_' + field).val();
        if (column && this.csvData.length > 0) {
            const samples = this.csvData.slice(0, 3).map(row => row[column]).filter(v => v).join(', ');
            jQuery('#sample_' + field).text(samples);
        } else {
            jQuery('#sample_' + field).text('');
        }
    },
    
    processMapping() {
        // Collect mapping
        this.mapping = {};
        jQuery('[id^="map_"]').each(function() {
            const field = this.id.replace('map_', '');
            const value = jQuery(this).val();
            if (value) {
                wcsci.mapping[field] = value;
            }
        });
        
        // Validate required fields
        const required = this.productType === 'variable' ? ['sku', 'cost_price'] : ['sku', 'name', 'cost_price'];
        for (const field of required) {
            if (!this.mapping[field]) {
                alert('Please map the ' + field + ' field');
                return;
            }
        }
        
        if (this.productType === 'variable') {
            this.showAttributes();
            this.nextStep();
        } else {
            // Skip to markup step for simple products
            this.currentStep = 5; // Skip steps 4 and 5
            this.showSummary();
            this.nextStep();
        }
    },
    
    showAttributes() {
        let html = '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
        
        this.csvHeaders.forEach(header => {
            // Skip mapped columns
            const isMapped = Object.values(this.mapping).includes(header);
            if (!isMapped) {
                html += '<label style="display: block; margin: 5px 0;">';
                html += '<input type="checkbox" value="' + header + '"> ';
                html += header;
                html += '</label>';
            }
        });
        
        html += '</div>';
        
        jQuery('#attributes-container').html(html);
    },
    
    processAttributes() {
        this.attributes = [];
        jQuery('#attributes-container input:checked').each(function() {
            wcsci.attributes.push(jQuery(this).val());
        });
        
        if (this.attributes.length === 0) {
            alert('Please select at least one attribute');
            return;
        }
        
        this.showVariationFilters();
        this.nextStep();
    },
    
    showVariationFilters() {
        // Use pre-collected attribute values from server
        const attributeValues = {};
        
        this.attributes.forEach(attr => {
            if (this.attributeValues && this.attributeValues[attr]) {
                // Sort the values - handle numeric values properly
                attributeValues[attr] = this.attributeValues[attr].sort((a, b) => {
                    // Try to parse as numbers first
                    const numA = parseFloat(a);
                    const numB = parseFloat(b);
                    if (!isNaN(numA) && !isNaN(numB)) {
                        return numA - numB;
                    }
                    // Otherwise sort as strings
                    return a.localeCompare(b);
                });
            } else {
                attributeValues[attr] = [];
            }
        });
        
        let html = '';
        
        for (const [attr, values] of Object.entries(attributeValues)) {
            html += '<div class="attribute-filter-group">';
            html += '<h4>' + attr + '</h4>';
            html += '<div style="margin-bottom: 10px;">';
            html += '<button type="button" class="button button-small" onclick="wcsci.selectAll(\'' + attr + '\')">Select All</button> ';
            html += '<button type="button" class="button button-small" onclick="wcsci.selectNone(\'' + attr + '\')">Select None</button>';
            html += '</div>';
            html += '<div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
            
            values.forEach(value => {
                html += '<label style="display: block; margin: 3px 0;">';
                html += '<input type="checkbox" class="attr-' + this.sanitizeClass(attr) + '" value="' + value + '" checked> ';
                html += value;
                html += '</label>';
            });
            
            html += '</div>';
            html += '</div>';
        }
        
        jQuery('#variation-filters').html(html);
        
        // Add change listener
        jQuery('#variation-filters input').on('change', () => this.updateVariationCount());
        this.updateVariationCount();
    },
    
    sanitizeClass(str) {
        return str.replace(/[^a-z0-9]/gi, '-').toLowerCase();
    },
    
    selectAll(attr) {
        jQuery('.attr-' + this.sanitizeClass(attr)).prop('checked', true);
        this.updateVariationCount();
    },
    
    selectNone(attr) {
        jQuery('.attr-' + this.sanitizeClass(attr)).prop('checked', false);
        this.updateVariationCount();
    },
    
    updateVariationCount() {
        this.filteredValues = {};
        let count = 1;
        
        this.attributes.forEach(attr => {
            this.filteredValues[attr] = [];
            jQuery('.attr-' + this.sanitizeClass(attr) + ':checked').each(function() {
                wcsci.filteredValues[attr].push(jQuery(this).val());
            });
            
            if (this.filteredValues[attr].length > 0) {
                count *= this.filteredValues[attr].length;
            }
        });
        
        jQuery('#variation-count').text(count);
    },
    
    showSummary() {
        let html = '<table class="widefat">';
        html += '<tr><td>Product Type:</td><td>' + (this.productType === 'variable' ? 'Variable Product' : 'Simple Products') + '</td></tr>';
        
        if (this.productType === 'variable') {
            html += '<tr><td>Product Name:</td><td>' + jQuery('#product_name').val() + '</td></tr>';
            html += '<tr><td>Parent SKU:</td><td>' + jQuery('#parent_sku').val() + '</td></tr>';
            
            let variationCount = 1;
            this.attributes.forEach(attr => {
                if (this.filteredValues[attr] && this.filteredValues[attr].length > 0) {
                    variationCount *= this.filteredValues[attr].length;
                }
            });
            html += '<tr><td>Variations to Import:</td><td>' + variationCount + '</td></tr>';
        }
        
        html += '</table>';
        
        jQuery('#import-summary').html(html);
    },
    
    async startImport() {
        const markup = jQuery('#markup').val();
        
        jQuery('.wcsci-step').hide();
        jQuery('#import-progress').show();
        jQuery('#import-status').html('<p>Starting import...</p>');
        
        const data = {
            action: 'wcsci_import_products',
            nonce: wcsci_ajax.nonce,
            mapping: JSON.stringify(this.mapping),
            attributes: JSON.stringify(this.attributes),
            filtered_values: JSON.stringify(this.filteredValues),
            markup: markup
        };
        
        try {
            // Initialize import
            const response = await jQuery.ajax({
                url: wcsci_ajax.ajax_url,
                type: 'POST',
                data: data
            });
            
            if (response.success) {
                // Check if we need batch processing
                if (response.data.total_batches > 1) {
                    await this.processBatches(response.data.total_batches, response.data.batch_size, response.data.total_rows);
                } else {
                    // Small file, process normally
                    await this.processBatches(1, response.data.total_rows, response.data.total_rows);
                }
            } else {
                jQuery('#import-results').html('<div class="notice notice-error"><p>Import failed: ' + response.data + '</p></div>');
            }
        } catch (error) {
            jQuery('#import-results').html('<div class="notice notice-error"><p>Import failed: ' + error + '</p></div>');
        }
    },
    
    async processBatches(totalBatches, batchSize, totalRows) {
        let currentBatch = 1;
        let cumulativeResults = {
            success: 0,
            failed: 0,
            skipped: 0,
            errors: []
        };
        
        while (currentBatch <= totalBatches) {
            const progress = Math.min(95, (currentBatch / totalBatches) * 100);
            jQuery('.wcsci-progress-fill').css('width', progress + '%');
            
            jQuery('#import-status').html(
                '<p><strong>Processing batch ' + currentBatch + ' of ' + totalBatches + '</strong></p>' +
                '<p>Progress: ' + cumulativeResults.success + ' imported, ' + 
                cumulativeResults.failed + ' failed, ' + 
                cumulativeResults.skipped + ' skipped</p>'
            );
            
            try {
                const response = await jQuery.ajax({
                    url: wcsci_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcsci_import_batch',
                        nonce: wcsci_ajax.nonce,
                        batch_number: currentBatch
                    }
                });
                
                if (response.success) {
                    cumulativeResults = response.data.cumulative_results;
                    currentBatch++;
                    
                    if (response.data.is_last_batch) {
                        jQuery('.wcsci-progress-fill').css('width', '100%');
                        this.showResults(cumulativeResults);
                        break;
                    }
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                jQuery('#import-results').html(
                    '<div class="notice notice-error">' +
                    '<p>Batch ' + currentBatch + ' failed: ' + error + '</p>' +
                    '<p>Processed so far: ' + cumulativeResults.success + ' products</p>' +
                    '</div>'
                );
                break;
            }
        }
    },
    
    showResults(results) {
        let html = '<div class="notice notice-success">';
        html += '<h3>Import Complete!</h3>';
        html += '<p>Successfully imported: ' + results.success + ' items</p>';
        
        if (results.failed > 0) {
            html += '<p>Failed: ' + results.failed + ' items</p>';
        }
        
        if (results.skipped > 0) {
            html += '<p>Skipped (filtered out): ' + results.skipped + ' items</p>';
        }
        
        html += '</div>';
        
        if (results.errors && results.errors.length > 0) {
            html += '<div class="notice notice-error">';
            html += '<h4>Errors:</h4>';
            html += '<ul>';
            results.errors.slice(0, 10).forEach(error => {
                html += '<li>' + error + '</li>';
            });
            if (results.errors.length > 10) {
                html += '<li>... and ' + (results.errors.length - 10) + ' more errors</li>';
            }
            html += '</ul>';
            html += '</div>';
        }
        
        html += '<p>';
        html += '<a href="edit.php?post_type=product" class="button button-primary">View Products</a> ';
        html += '<button type="button" class="button" onclick="location.reload()">Import More</button>';
        html += '</p>';
        
        jQuery('#import-results').html(html);
    }
};

jQuery(document).ready(() => wcsci.init());
