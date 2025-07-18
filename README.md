
# WooCommerce Supplier CSV Importer

A WordPress plugin that imports supplier CSV files into WooCommerce with customizable field mapping and price markup functionality.

## Features

- 🎯 **Simple or Variable Products** - Choose to import each row as a separate product or as variations of a single product
- 📊 **Smart Column Mapping** - Automatically map CSV columns to WooCommerce fields
- 💰 **Price Markup** - Add percentage markup to cost prices automatically
- 🔍 **Variation Filtering** - Select exactly which variations to import
- 🏷️ **Auto-SKU Generation** - Automatically generate parent SKUs from product names
- ✅ **Validation** - Built-in validation for required fields

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Upload via WordPress Admin

1. Download this repository as a ZIP file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

### Method 2: Manual Installation

1. Download this repository
2. Upload the `woocommerce-supplier-csv-importer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

### Step 1: Select Product Type
Navigate to **WooCommerce > Supplier CSV Import** and choose:
- **Simple Products**: Each CSV row creates a separate product
- **Variable Product**: All CSV rows become variations of one product

### Step 2: Upload CSV & Product Details
- For variable products, enter the product name (auto-generates parent SKU)
- Select your CSV file and upload

### Step 3: Map CSV Columns
Map your CSV columns to WooCommerce fields:
- SKU (required)
- Product Name (required for simple products)
- Cost Price (required)
- Description
- Stock Quantity

### Step 4: Select Attributes (Variable Products)
Choose which CSV columns should be used as product variations (e.g., Size, Color, Material)

### Step 5: Filter Variations (Variable Products)
Select exactly which variation combinations to import:
- Use "Select All" / "Select None" buttons for quick selection
- Preview shows total variations to be created

### Step 6: Set Markup & Import
- Enter your price markup percentage
- Review import summary
- Click "Start Import"

## CSV Format Example

### For Simple Products:
```csv
SKU,Product Name,Cost Price,Description,Stock
PROD-001,Widget A,10.00,High quality widget,100
PROD-002,Widget B,15.00,Premium widget,50
```

### For Variable Products:
```csv
SKU,Size,Color,Cost Price,Description
WIDGET-S-RED,Small,Red,10.00,Red widget small
WIDGET-M-RED,Medium,Red,12.00,Red widget medium
WIDGET-L-RED,Large,Red,14.00,Red widget large
WIDGET-S-BLUE,Small,Blue,10.00,Blue widget small
```

## File Structure

```
woocommerce-supplier-csv-importer/
├── woocommerce-supplier-csv-importer.php    # Main plugin file
├── includes/
│   ├── class-wcsci-admin.php               # Admin interface
│   ├── class-wcsci-ajax.php                # AJAX handlers
│   └── class-wcsci-importer.php            # Import logic
├── assets/
│   ├── admin.js                            # JavaScript
│   └── admin.css                           # Styles
└── README.md                               # This file
```

## Changelog

### Version 2.0.0
- Complete rebuild with streamlined architecture
- Added variation filtering for selective import
- Improved error handling and validation
- Auto-SKU generation from product names
- Better support for large CSV files

## Support

For issues or feature requests, please use the [GitHub Issues](https://github.com/yourname/woocommerce-supplier-csv-importer/issues) page.

## License

GPL v2 or later

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.
