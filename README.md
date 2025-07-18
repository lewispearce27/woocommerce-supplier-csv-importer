# WooCommerce Supplier CSV Importer

A powerful WordPress plugin that imports supplier CSV files into WooCommerce with intelligent field mapping, price markup calculations, and advanced filtering capabilities.

## 🚀 Key Features

- **📦 Flexible Product Import** - Import as simple products or variable products with multiple variations
- **🔄 Batch Processing** - Handles large files (10,000+ rows) without timeouts or memory issues
- **🎯 Smart Column Mapping** - Intuitive interface to map CSV columns to WooCommerce fields
- **💰 Automatic Price Markup** - Apply percentage markups to supplier cost prices
- **🔍 Advanced Variation Filtering** - Select exactly which product variations to import
- **🏷️ Auto-SKU Generation** - Automatically generate parent SKUs from product names
- **✅ Built-in Validation** - Ensures data integrity with required field checking
- **📊 Real-time Progress** - Live import progress with batch-by-batch updates
- **🔧 HPOS Compatible** - Fully compatible with WooCommerce High-Performance Order Storage

## 📋 Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Maximum PHP execution time of at least 300 seconds (for large imports)

## 🛠️ Installation

### Method 1: WordPress Admin Upload

1. Download the latest release from [Releases](https://github.com/yourname/woocommerce-supplier-csv-importer/releases)
2. Navigate to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin** and select the downloaded ZIP file
4. Click **Install Now** and then **Activate**

### Method 2: Manual Installation

1. Download this repository as a ZIP file
2. Extract the ZIP file
3. Upload the `woocommerce-supplier-csv-importer` folder to `/wp-content/plugins/`
4. Activate the plugin through the **Plugins** menu in WordPress

## 📖 Usage Guide

### Step 1: Select Product Type
Navigate to **WooCommerce > Supplier CSV Import**

Choose your import type:
- **Simple Products**: Each CSV row creates a separate product
- **Variable Product**: All CSV rows become variations of a single parent product

### Step 2: Product Details & CSV Upload
**For Variable Products:**
- Enter the product name (e.g., "Business Cards")
- Parent SKU is auto-generated (can be edited)
- Upload your supplier's CSV file

**For Simple Products:**
- Simply upload your CSV file

### Step 3: Map CSV Columns
Map your CSV columns to WooCommerce fields:

**Required Fields:**
- SKU (always required)
- Product Name (simple products only)
- Cost Price (for markup calculation)

**Optional Fields:**
- Description
- Stock Quantity
- Weight, Length, Width, Height
- And more...

### Step 4: Select Attributes (Variable Products Only)
Choose which CSV columns represent product variations:
- Size, Color, Material, Quantity, etc.
- Any column can become a variation attribute
- Skip columns that are already mapped (SKU, Price, etc.)

### Step 5: Filter Variations (Variable Products Only)
Fine-tune which variations to import:
- View all unique values for each attribute
- Use "Select All" / "Select None" for quick selection
- Preview shows total variations to be created
- Perfect for importing only specific sizes, colors, or quantities

### Step 6: Set Markup & Import
- Enter your price markup percentage (e.g., 25 for 25% markup)
- Review the import summary
- Click **Start Import** to begin

**Large File Processing:**
- Files over 500 rows are automatically processed in batches
- Real-time progress updates show current batch and totals
- Import continues even if individual items fail

## 📊 CSV Format Examples

### Simple Products CSV
```csv
SKU,Product Name,Cost Price,Description,Stock
PROD-001,Premium Widget,10.00,High quality widget,100
PROD-002,Deluxe Widget,15.00,Premium deluxe widget,50
PROD-003,Basic Widget,5.00,Entry level widget,200
```

### Variable Products CSV (e.g., Flyers)
```csv
SKU,Quantity,Finished Size,Paper Type,Print Type,Turnaround,Price £
FLY-A5-100,100,A5,170gsm Silk,Double Sided,3-4 Working Days,25.00
FLY-A5-250,250,A5,170gsm Silk,Double Sided,3-4 Working Days,35.00
FLY-A5-500,500,A5,170gsm Silk,Double Sided,3-4 Working Days,45.00
FLY-A5-1000,1000,A5,170gsm Silk,Double Sided,3-4 Working Days,65.00
```

## 🏗️ Technical Details

### File Structure
```
woocommerce-supplier-csv-importer/
├── woocommerce-supplier-csv-importer.php    # Main plugin file
├── includes/
│   ├── class-wcsci-admin.php               # Admin interface & UI
│   ├── class-wcsci-ajax.php                # AJAX handlers & batch processing
│   └── class-wcsci-importer.php            # Core import logic
├── assets/
│   ├── admin.js                            # Frontend JavaScript
│   └── admin.css                           # Admin styling
└── README.md                               # This file
```

### Performance
- **Batch Size**: 500 rows per batch
- **Memory Efficient**: Only loads current batch into memory
- **Timeout Prevention**: Each batch processes independently
- **Large File Support**: Tested with 10,000+ row CSV files

### Data Processing
- **Unique Value Collection**: Scans entire CSV for all attribute values
- **Smart Filtering**: Only imports selected variation combinations
- **Duplicate Prevention**: Updates existing products by SKU match
- **Error Collection**: Captu
