# GitHub Copilot Instructions for wu-reseller-panel

## Project Overview

This is the **Ultimate Multisite - Reseller Panel**, a WordPress network plugin that enables selling and managing domains, SSL certificates, hosting, and email services through Ultimate Multisite (WP Ultimo).

- **Type**: WordPress Network Plugin (mu-plugin compatible)
- **Primary Language**: PHP 7.4+ (8.x recommended)
- **Framework**: WordPress Multisite + Ultimate Multisite (WP Ultimo)
- **Architecture**: Provider-agnostic service layer with multiple service providers
- **Version**: 2.0.1

## Core Architecture

### Design Patterns

1. **Singleton Pattern**: Main classes like `Reseller_Panel`, `Provider_Manager`, `Service_Router`
2. **Factory Pattern**: `Provider_Manager` creates and manages provider instances
3. **Strategy Pattern**: Provider implementations via `Service_Provider_Interface`
4. **Dependency Injection**: Providers receive configuration through constructor/setters

### Key Components

```
reseller-panel.php                    # Main plugin file with constants and bootstrap
├── inc/
│   ├── class-reseller-panel.php     # Main addon class (Singleton)
│   ├── class-provider-manager.php   # Factory for managing providers
│   ├── class-service-router.php     # Service execution with fallback logic
│   ├── interfaces/
│   │   ├── class-service-provider-interface.php  # Provider contract
│   │   └── class-domain-importer-interface.php   # Importer contract
│   ├── abstract/
│   │   └── class-base-service-provider.php       # Base provider class
│   ├── providers/
│   │   ├── class-opensrs-provider.php      # OpenSRS implementation
│   │   └── class-namecheap-provider.php    # NameCheap implementation
│   ├── product-types/
│   │   └── class-domain-product-type.php   # Domain product type
│   ├── importers/
│   │   └── class-domain-importer.php       # TLD/pricing importer
│   └── admin-pages/
│       ├── class-admin-page.php                 # Base page class
│       ├── class-services-settings-page.php    # Service configuration
│       └── class-provider-settings-page.php    # Provider configuration
└── assets/
    ├── css/admin.css                # Admin interface styling
    └── js/admin.js                  # Admin interface scripts
```

## Coding Standards

### PHP Standards

1. **Follow WordPress Coding Standards**: Use WordPress PHP Coding Standards for all PHP code
2. **Namespace**: Use organized sub-namespaces under `Reseller_Panel`:
   - Core classes: `namespace Reseller_Panel;`
   - Providers: `namespace Reseller_Panel\Providers;`
   - Admin pages: `namespace Reseller_Panel\Admin_Pages;`
   - Interfaces: `namespace Reseller_Panel\Interfaces;`
   - Abstract classes: `namespace Reseller_Panel\Abstract_Classes;`
   - Product types: `namespace Reseller_Panel\Product_Types;`
   - Importers: `namespace Reseller_Panel\Importers;`
3. **File Naming**: 
   - Class files: `class-{classname-in-kebab-case}.php`
   - Interface files: `class-{interface-name}-interface.php`
   - Example: `class-service-provider-interface.php`
4. **Class Naming**: 
   - PascalCase with underscores: `Service_Provider_Interface`, `Reseller_Panel`
   - Match filename: `class-reseller-panel.php` contains `class Reseller_Panel`
5. **DocBlocks**: Required for all classes, methods, and properties
   - Use `@package`, `@var`, `@param`, `@return` tags
   - Include description for all public methods
6. **Indentation**: Use tabs (not spaces)
7. **Security**: 
   - Always use `esc_html()`, `esc_attr()`, `esc_url()` for output
   - Use `wp_verify_nonce()` for form submissions
   - Sanitize all input with `sanitize_text_field()`, `sanitize_email()`, etc.
   - Check capabilities with `current_user_can()`
   - Use `! defined( 'ABSPATH' )` check at the top of each file

### Database Standards

1. **Tables**: Prefix with `{$wpdb->prefix}reseller_panel_`
   - `reseller_panel_services`
   - `reseller_panel_providers`
   - `reseller_panel_fallback_logs`
2. **Queries**: Always use `$wpdb->prepare()` for parameterized queries
3. **Charset**: Use `$wpdb->get_charset_collate()` when creating tables

### JavaScript/CSS

1. **Dependencies**: Enqueue WordPress built-in scripts when possible (jQuery, etc.)
2. **Naming**: Use kebab-case for CSS classes, camelCase for JS functions
3. **Localization**: Use `wp_localize_script()` to pass data from PHP to JS

## Development Workflow

### Testing & Validation

**PHP Syntax Checking (Cross-platform)**:
```bash
# Check single file
php -l reseller-panel.php

# Check all PHP files (Unix/Linux/Mac)
find . -name "*.php" -exec php -l {} \;
```

**PowerShell Helper (Windows)**:
```powershell
# Use the PowerShell helper script
.\dev-check.ps1

# Or manually check all PHP files
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

**No automated tests currently exist** - manual testing required in a WordPress Multisite environment.

### Prerequisites for Development

- WordPress Multisite installation
- Ultimate Multisite (WP Ultimo) plugin installed and active
- PHP 7.4+ with cURL extension enabled
- Access to OpenSRS or NameCheap API credentials for testing

### Key Constants

Defined in `reseller-panel.php`:
- `RESELLER_PANEL_VERSION` - Plugin version
- `RESELLER_PANEL_FILE` - Main plugin file path
- `RESELLER_PANEL_PATH` - Plugin directory path
- `RESELLER_PANEL_URL` - Plugin URL
- `RESELLER_PANEL_BASENAME` - Plugin basename

## Provider Development

### Creating a New Provider

1. **Implement Interface**: Create class implementing `Service_Provider_Interface`
2. **Extend Base Class**: Extend `Base_Service_Provider` for common functionality
3. **File Location**: Place in `inc/providers/class-{provider-name}-provider.php`
4. **Registration**: Register in `Provider_Manager::register_provider()`
5. **Required Methods**:
   - `get_key()` - Unique provider identifier
   - `get_name()` - Human-readable name
   - `get_supported_services()` - Array of services (domains, ssl, hosting, emails, marketing)
   - `is_configured()` - Check if credentials are set
   - `test_connection()` - Verify API connectivity
   - `get_config_fields()` - Return form fields for admin settings

### Service Types

Supported services:
- `domains` - Domain registration/management
- `ssl` - SSL certificate provisioning
- `hosting` - Hosting account management
- `emails` - Email service management
- `marketing` - Marketing service management

## Common Patterns

### Singleton Implementation

```php
private static $instance = null;

public static function get_instance() {
    if ( null === self::$instance ) {
        self::$instance = new self();
    }
    return self::$instance;
}

private function __construct() {
    // Initialize
}
```

### Loading Configuration

```php
// Use Base_Service_Provider methods
$api_key = $this->get_config_value('api_key', '');
$username = $this->get_config_value('username', '');
```

### Error Handling

```php
// Return WP_Error on failure
if ( ! $success ) {
    return new WP_Error(
        'provider_error',
        __( 'Error message', 'ultimate-multisite' )
    );
}

// Return true/data on success
return true;
```

### Admin Page Registration

```php
add_action( 'network_admin_menu', array( $this, 'register_admin_pages' ), 10 );
```

## Security Best Practices

1. **Input Validation**: Always sanitize user input
2. **Output Escaping**: Always escape output based on context
3. **Nonce Verification**: Use nonces for all forms and AJAX requests
4. **Capability Checks**: Verify user permissions before operations
5. **SQL Injection Prevention**: Use `$wpdb->prepare()` for all queries
6. **Direct Access Prevention**: Include `! defined( 'ABSPATH' )` check
7. **Sensitive Data**: Store API credentials in the database, never in code
8. **API Security**: Use proper authentication for external API calls

## Text Domain & Internationalization

- **Text Domain**: `ultimate-multisite`
  - **Note**: This plugin uses the parent Ultimate Multisite text domain for consistency across the addon ecosystem
  - All translations are centralized in the parent plugin's language files
- **Function**: Use `__()`, `esc_html__()`, `esc_attr__()` for all user-facing strings
- **Domain Path**: `/languages`

## Important Notes

1. **WordPress Multisite Only**: This plugin requires a WordPress Multisite installation
2. **Network Activation**: Must be network-activated
3. **Ultimate Multisite Dependency**: Requires Ultimate Multisite (WP Ultimo) to be installed
4. **PHP Version**: Minimum PHP 7.4, recommends 8.x
5. **Database Tables**: Created automatically on activation
6. **Cron Jobs**: Scheduled automatically for pricing updates and renewals

## Documentation References

- **Architecture**: See `ARCHITECTURE.md` for detailed architecture overview
- **Installation**: See `INSTALL.md` for setup and configuration
- **Quick Start**: See `QUICK_START.md` for getting started
- **README**: See `README.md` for recent changes and development info

## When Making Changes

1. **Minimal Changes**: Make the smallest possible changes to achieve the goal
2. **Test Syntax**: Run `php -l` on modified files or use `.\dev-check.ps1`
3. **Check Dependencies**: Ensure Ultimate Multisite compatibility
4. **Update Documentation**: Update relevant .md files if architecture changes
5. **Follow Patterns**: Match existing code patterns and conventions
6. **Namespace**: Use appropriate sub-namespaces (e.g., `Reseller_Panel\Providers`, `Reseller_Panel\Admin_Pages`)
7. **Security**: Never commit API credentials or sensitive data
8. **Backwards Compatibility**: Maintain compatibility with WordPress 6.2+ and PHP 7.4+

## Prohibited Actions

- Do not remove the Ultimate Multisite dependency check
- Do not change the plugin text domain
- Do not modify database table names without migration
- Do not remove security checks (nonce verification, capability checks, escaping)
- Do not add dependencies that conflict with WordPress or Ultimate Multisite
