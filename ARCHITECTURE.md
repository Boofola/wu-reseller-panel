# Reseller Panel - Plugin Architecture Guide

## Overview

The Reseller Panel is a complete rewrite following Ultimate Multisite (WP Ultimo) addon architecture standards. It provides a flexible, provider-agnostic system for managing domains, SSL certificates, hosting, emails, and marketing services across multiple providers.

Requires Ultimate Multisite: https://github.com/Multisite-Ultimate/ultimate-multisite

## Architecture

### Core Structure

```
reseller-panel.php                    # Main plugin file
├── inc/
│   ├── class-reseller-panel.php     # Main addon class (Singleton)
│   ├── class-provider-manager.php   # Factory for managing providers
│   ├── class-service-router.php     # Service execution with fallback logic
│   ├── interfaces/
│   │   └── class-service-provider-interface.php  # Provider contract
│   ├── abstract/
│   │   └── class-base-service-provider.php       # Base provider class
│   ├── providers/
│   │   ├── class-opensrs-provider.php      # OpenSRS implementation
│   │   └── class-namecheap-provider.php    # NameCheap implementation
│   └── admin-pages/
│       ├── class-admin-page.php                 # Base page class
│       ├── class-services-settings-page.php    # Service configuration UI
│       └── class-provider-settings-page.php    # Provider configuration UI
├── assets/
│   ├── css/admin.css                # Admin interface styling
│   └── js/admin.js                  # Admin interface scripts
```

### Database Schema

#### reseller_panel_services
Tracks available services and their configuration:
- `id` - Primary key
- `service_key` - Unique service identifier (domains, ssl, hosting, emails, marketing)
- `service_name` - Human-readable name
- `description` - Service description
- `enabled` - Whether service is enabled (1/0)
- `default_provider` - Primary provider for this service
- `fallback_provider` - Secondary provider if primary fails
- `created_at` - Timestamp
- `updated_at` - Timestamp

#### reseller_panel_providers
Stores provider credentials and configuration:
- `id` - Primary key
- `provider_key` - Unique provider identifier (opensrs, namecheap, etc.)
- `provider_name` - Human-readable name
- `status` - Provider status
- `config` - JSON field with provider-specific configuration
- `supported_services` - JSON array of services provider supports
- `priority` - Provider priority for selection
- `created_at` - Timestamp
- `updated_at` - Timestamp

#### reseller_panel_fallback_logs (Auto-created)
Tracks fallback events for audit trail:
- `id` - Primary key
- `service_key` - Service that failed
- `primary_provider` - Provider that failed
- `fallback_provider` - Provider that handled request
- `error_message` - Failure reason
- `timestamp` - When fallback occurred

## Key Classes

### Service_Provider_Interface
Contract all providers must implement:

```php
interface Service_Provider_Interface {
    public function get_key(): string;
    public function get_name(): string;
    public function get_supported_services(): array;
    public function is_configured(): bool;
    public function test_connection(): bool|WP_Error;
    public function get_config_fields(): array;
}
```

### Base_Service_Provider (Abstract)
Common functionality for all providers:
- `load_config()` - Load provider config from database
- `save_config($config)` - Save provider config
- `get_config_value($key, $default)` - Get specific config value
- `supports_service($service)` - Check if provider supports service
- `is_configured()` - Verify provider has necessary credentials

### Provider_Manager (Singleton)
Factory for managing providers:
- `get_provider($key)` - Get provider by key
- `get_all_providers()` - Get all registered providers
- `get_providers_by_service($service)` - Find providers supporting service
- `get_available_providers($service)` - Get configured providers for service
- `register_provider($provider)` - Register new provider implementation

### Service_Router (Singleton)
Intelligent service execution with fallback:
- `execute_service($service, $action, $params)` - Execute service with fallback
- `get_fallback_logs($limit)` - Retrieve fallback audit trail

**Fallback Flow:**
1. Check if service is enabled
2. Try default provider
3. If fails and fallback configured:
   - Send admin email notification
   - Log fallback event
   - Try fallback provider
4. Return result or error

## Configuration

### Adding a New Provider

1. **Create provider class** in `inc/providers/class-{provider}-provider.php`:

```php
namespace Reseller_Panel\Providers;

use Reseller_Panel\Abstract_Classes\Base_Service_Provider;

class Custom_Provider extends Base_Service_Provider {
    protected $key = 'custom';
    protected $name = 'Custom Provider';
    protected $supported_services = array('domains', 'ssl');
    
    public function get_config_fields() {
        return array(
            'api_key' => array(
                'label' => 'API Key',
                'type' => 'password',
                'description' => 'Your API key',
                'link' => 'https://...',
                'link_text' => 'Get API Key'
            )
        );
    }
    
    public function test_connection() {
        // Implement API test
    }
}
```

2. **Register provider** in `inc/class-reseller-panel.php` load_dependencies():

```php
require_once RESELLER_PANEL_PATH . 'inc/providers/class-custom-provider.php';
```

3. **Auto-register in Provider_Manager** constructor:

```php
$this->register_provider( new Custom_Provider() );
```

4. The new provider appears in Provider Settings admin page

### Admin Interface

**Three Menu Pages:**
1. **Reseller Panel** (Overview) - Dashboard with links to configuration
2. **Services Settings** - Configure which services are available and select default/fallback providers
3. **Provider Settings** - Configure API credentials for each provider (tabbed interface)

**Features:**
- Greyed-out services when no compatible provider is available
- Visual status indicators (✓ configured, ✗ warnings, etc.)
- Documentation links in each provider section
- Test Connection button for each provider
- Fallback explanation help section

## Supported Providers

### OpenSRS (TuCows)
- **Services:** Domains
- **Features:**
  - TLD list retrieval
  - Pricing synchronization
  - XML-RPC API
  - Test/Live environment switching
  
**Configuration Fields:**
- API Key (Private Key)
- Reseller Username
- Environment (Sandbox/Production)

### NameCheap
- **Services:** Domains, SSL, Hosting, Emails
- **Features:**
  - Multi-service support
  - Base pricing with admin override
  - XML API
  - Test/Live environment switching

**Configuration Fields:**
- API User
- API Key
- Username
- Client IP (Optional, for IP-restricted APIs)
- Environment (Sandbox/Production)
- Base Domain Price
- Base SSL Price

### Planned Providers
- Dynadot
- GoDaddy
- CentralNic
- WHMCS
- cPanel
- 201domains
- DomainNameAPI
- ResellerClub
- NamesIlo
- ResellerPanel

## Text Domain

All strings use the `ultimate-multisite` text domain for consistency with Ultimate Multisite:

```php
__( 'String', 'ultimate-multisite' )
esc_html__( 'String', 'ultimate-multisite' )
_e( 'String', 'ultimate-multisite' )
```

## Database Options

Configuration is stored as site options (network-wide):
- `reseller_panel_{provider_key}_config` - Provider configuration (JSON)
- `reseller_panel_version` - Plugin version

## Menu Integration

Plugin registers menu under Ultimate Multisite's main menu:
- Parent: `wp-ultimo` (main menu slug)
- Menu Position: Automatically positioned under WP Ultimo

**Menu Items:**
1. Reseller Panel (overview/dashboard)
2. Services Settings (service configuration)
3. Provider Settings (API configuration)

All require `manage_network` capability.

## Hooks & Filters

Future expansion points:
- `reseller_panel_register_provider` - Register custom providers
- `reseller_panel_execute_action` - Hook into service execution
- `reseller_panel_fallback_occurred` - React to fallback events
- `reseller_panel_before_execute_service` - Pre-execution validation
- `reseller_panel_after_execute_service` - Post-execution hooks

## Security

- **Nonce verification** on all form submissions
- **Capability checking** (manage_network) for all admin pages
- **Sanitization** of all input data
- **Escaping** of all output
- **Site options** for network-wide settings (not user-specific)
- **Password fields** for API keys (not stored in logs)

## Development Notes

### Naming Conventions
- Classes use PascalCase: `Service_Provider_Interface`
- Constants use UPPERCASE: `RESELLER_PANEL_VERSION`
- Database tables use snake_case: `reseller_panel_services`
- Namespaces follow structure: `Reseller_Panel\Providers\OpenSRS_Provider`

### File Organization
- Interfaces in `inc/interfaces/`
- Abstract classes in `inc/abstract/`
- Concrete implementations in `inc/providers/`
- Admin pages in `inc/admin-pages/`

### Singleton Pattern
Core classes use singleton pattern for single instance:

```php
private static $instance = null;

public static function get_instance() {
    if ( null === self::$instance ) {
        self::$instance = new self();
    }
    return self::$instance;
}

private function __construct() {
    // Initialization
}
```

## Troubleshooting

### Menu Not Appearing
- Verify Ultimate Multisite is installed and activated
- Check user has `manage_network` capability
- Verify parent slug is `wp-ultimo`

### Provider Not Available
- Check provider class is registered in Provider_Manager
- Verify provider implements Service_Provider_Interface
- Check provider is included in load_dependencies()

### Settings Not Saving
- Verify POST data includes provider_key
- Check nonce is correct
- Ensure user has manage_network capability

### Fallback Not Triggering
- Verify both default and fallback providers are configured
- Check fallback provider supports the service
- Review fallback logs at `/wp-admin/network/admin.php?page=reseller-panel`

## API Examples

### Execute Service with Fallback
```php
$router = \Reseller_Panel\Service_Router::get_instance();
$result = $router->execute_service( 'domains', 'register', [
    'domain' => 'example.com',
    'period' => 1
] );

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
}
```

### Get Available Providers
```php
$manager = \Reseller_Panel\Provider_Manager::get_instance();
$providers = $manager->get_available_providers( 'domains' );

foreach ( $providers as $key => $provider ) {
    echo $provider->get_name();
}
```

### Test Provider Connection
```php
$provider = $manager->get_provider( 'opensrs' );
$provider->load_config();
$test = $provider->test_connection();

if ( is_wp_error( $test ) ) {
    echo 'Connection failed: ' . $test->get_error_message();
}
```

## Version

Current Version: 2.0.0
Architecture: Ultimate Multisite Addon Pattern
PHP Minimum: 7.4
WordPress Minimum: 5.9
Ultimate Multisite Minimum: 1.0

---

**Documentation Written by:** Anthropic Claude AI
**Date:** November 23, 2025  
**Status:** ✅ FULLY COMPATIBLE