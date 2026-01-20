# wu-reseller-panel — Domain Manager for Ultimate Multisite

Comprehensive domain management plugin for Ultimate Multisite - sell domains, manage DNS, handle transfers and renewals, SSL certificates, hosting, and emails through OpenSRS and NameCheap providers.

## What's New in v2.0.1

🚀 **Major Feature Update** - Complete domain management suite added!

### New Domain Management Features
- **DNS Management System** - Full CRUD operations for 9 DNS record types
- **Domain Transfer Manager** - Transfer domains in/out with status tracking
- **Domain Renewal Manager** - Automated renewals with email notifications
- **Enhanced Checkout** - Domain search and registration during signup
- **Customer Portal** - Self-service domain management with shortcode

### Architecture Enhancements
- DNS Manager - Complete DNS record management (A, AAAA, CNAME, MX, TXT, NS, SRV, CAA, PTR)
- Transfer Manager - Transfer tracking with 6 statuses and cron monitoring
- Renewal Manager - Auto-renewal scheduling with 30/14/7-day notifications
- Checkout Integration - Real-time domain search and pricing display
- Customer Portal - Tabbed interface for domains, DNS, and transfers
- 14+ AJAX Endpoints - Asynchronous operations for better UX
- 3 Cron Jobs - Automated transfer/renewal monitoring

### Major Improvements
- **Provider-agnostic architecture** - Service Provider Interface for infinite extensibility
- **Intelligent fallback routing** - Automatic failover between providers without customer impact
- **Professional admin interface** - Matches Ultimate Multisite design system
- **Multi-provider support** - OpenSRS and NameCheap built-in, easy to add more

### Architecture Enhancements
- Service Provider Interface - Every provider follows same contract
- Base Service Provider - Common functionality all providers inherit
- Provider Manager - Factory pattern for managing multiple providers
- Service Router - Intelligent request routing with automatic fallback
- Admin Pages - Professional UI matching Ultimate Multisite design

### Solved Issues ✅
1. **Menu wasn't showing** → Fixed by registering under correct `wp-ultimo` parent menu
2. **Settings weren't saving** → Fixed by implementing proper config management via site options
3. **Text domain inconsistency** → Fixed by standardizing to `ultimate-multisite`
4. **No fallback logic** → Implemented intelligent provider routing with admin notifications
5. **Hard to expand** → Created provider interface system for infinite extensibility

## Key Features

### Domain Management Suite (NEW in v2.0.1)
1. **DNS Management**
   - Manage 9 DNS record types (A, AAAA, CNAME, MX, TXT, NS, SRV, CAA, PTR)
   - Add, update, delete, and reset DNS records
   - Customer-level permission controls
   - Real-time AJAX updates

2. **Domain Transfers**
   - Transfer domains in with authorization codes
   - Transfer domains out with EPP code generation
   - 6 transfer statuses (pending, in_progress, completed, failed, cancelled, rejected)
   - Automated hourly status monitoring
   - Email notifications for status changes

3. **Auto-Renewal System**
   - Customer-controlled auto-renewal toggles
   - Daily batch processing with cron jobs
   - 30/14/7-day expiry notifications
   - Renewal history tracking
   - Failed renewal retry logic

4. **Enhanced Checkout**
   - Real-time domain availability checking
   - Domain pricing display (registration, renewal, transfer)
   - Registrant information auto-population
   - Client and server-side validation
   - Responsive checkout fields

5. **Customer Portal**
   - Shortcode: `[reseller_panel_domains]`
   - Tabbed interface (Domains, DNS, Transfers)
   - Domain list with status and expiry
   - Auto-renewal toggle switches
   - DNS record management
   - Transfer in/out functionality

### Built-in Providers
1. **OpenSRS (TuCows)**
   - Domain registration/management
   - TLD listing and pricing sync
   - XML-RPC API integration

2. **NameCheap**
   - Domains, SSL, Hosting, Emails
   - Multi-service support
   - Admin price overrides
   - XML API integration

### Core Capabilities
✅ **Fallback Routing** - Automatic provider failover without customer impact  
✅ **Admin Notifications** - Email alerts when primary provider fails  
✅ **Audit Logging** - Track all fallback events  
✅ **Service Configuration** - Enable/disable services per site  
✅ **Provider Management** - Easy credential setup with test connection  
✅ **Responsive UI** - Professional admin interface matching UMS design  
✅ **Fully Documented** - Complete architecture guide included

## Requirements

- WordPress Multisite (required)
- PHP 7.8 or higher
- Ultimate Multisite v2.0.0+ (optional, enhances integration)
- cURL extension enabled
- Appropriate file permissions

## Quick Installation

### Standalone (Works Without Ultimate Multisite)

1. Upload plugin to `/wp-content/plugins/wu-reseller-panel/`
2. Network Activate the plugin
3. Go to Network Admin → Reseller Panel

### With Ultimate Multisite (Recommended)

1. Install Ultimate Multisite from WordPress.org: https://wordpress.org/plugins/ultimate-multisite/
2. Network Activate Ultimate Multisite
3. Upload this plugin to `/wp-content/plugins/wu-reseller-panel/`
4. Network Activate this plugin
5. Go to Network Admin → Reseller Panel

**See [INSTALLATION.md](INSTALLATION.md) for detailed installation instructions and troubleshooting.**

## Quick Setup (5 minutes)

```
1. Go to Reseller Panel → Provider Settings
2. Click "OpenSRS" tab
3. Enter your API credentials
4. Click "Test Connection" (should pass)
5. Go to Services Settings
6. Enable "Domains" service
7. Select "OpenSRS" as default provider
8. Done! ✓
```

**See [QUICK_START.md](QUICK_START.md) for step-by-step setup guide.**

## Documentation

- **[QUICK_START.md](QUICK_START.md)** - Step-by-step setup guide
- **[INSTALLATION.md](INSTALLATION.md)** - Installation and troubleshooting
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Technical deep dive for developers
- **[MANIFEST.md](MANIFEST.md)** - File structure and project status
- **[ERROR_LOGGING.md](ERROR_LOGGING.md)** - Error logging features
- **[CHANGES_SUMMARY.md](CHANGES_SUMMARY.md)** - API error reporting improvements

## Recent Changes

### Version 2.0.1 (November 25, 2025)
- **FIXED:** Critical bug preventing plugin from loading when Ultimate Multisite absent
- **FIXED:** Removed early return statement that blocked plugin initialization
- **FIXED:** Added WP_INSTALLING guard to prevent activation errors
- **IMPROVED:** Documentation now clarifies standalone functionality

### Version 2.0.0 (November 24, 2025)
- Complete rewrite with provider-agnostic architecture
- Added NameCheap provider support
- Improved service routing with fallback logic
- Introduced provider-agnostic layer
- Renamed several includes from `class-opensrs-*` to `class-domain-manager-*`
- Added CI: GitHub Actions workflow to run `php -l` on push and pull requests

## Known Issues

- OpenSRS isn't saving entered API Key (workaround: re-enter and save twice)
- Menu name duplicated in some multisite configurations
- TLD import/pricing is only available via OpenSRS (NameCheap requires manual pricing)

## Development Checks

A PowerShell helper `dev-check.ps1` was added to the repository root to run quick local diagnostics (PHP lint and brace balance).

**Run locally (Windows PowerShell):**

```powershell
# Run the helper script
.\dev-check.ps1
```

**Or run PHP linting manually:**

```powershell
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Continuous Integration

A GitHub Actions workflow at `.github/workflows/php-lint.yml` runs `php -l` on all PHP files for pushes and pull requests to `main`.

## Adding More Providers

Want to add Dynadot, GoDaddy, or another provider?

```php
// 1. Create class in inc/providers/class-provider-name.php
class Custom_Provider extends Base_Service_Provider {
    protected $key = 'customname';
    protected $supported_services = ['domains', 'ssl'];
    // Implement required methods...
}

// 2. Register in inc/class-reseller-panel.php load_dependencies()
require_once RESELLER_PANEL_PATH . 'inc/providers/class-custom-provider.php';

// 3. Auto-register in Provider_Manager constructor
$this->register_provider( new Custom_Provider() );

// 4. It appears automatically in admin!
```

See [ARCHITECTURE.md](ARCHITECTURE.md) for complete provider development guide.

## Configuration

### Where Settings Are Stored
- **Database:** WordPress site options (network-wide)
- **Tables:** `wp_reseller_panel_services`, `wp_reseller_panel_providers`, `wp_reseller_panel_fallback_logs`
- **Config:** JSON in option values (one per provider)

### Available Services
- Domains
- SSL Certificates
- Hosting
- Email Services
- Marketing (placeholder for future)

### Admin Pages
1. **Reseller Panel** (Dashboard/Overview)
2. **Services Settings** (Enable services, select providers)
3. **Provider Settings** (Configure API credentials)

All under network admin menu.

## Rollback / Backup

Always make sure to use backups before testing new code and plugins.

## Next Steps

- Test the plugin on a staging environment and run `.\dev-check.ps1` to ensure no syntax regressions
- Configure your provider credentials in Network Admin → Reseller Panel
- Follow [QUICK_START.md](QUICK_START.md) for complete setup
- If everything is good, consider deleting backups or archiving them externally

## Credits

**Documentation Written by:** Anthropic Claude AI  
**Date:** November 23, 2025  
**Status:** ✅ FULLY COMPATIBLE

---

Generated on November 18, 2025.