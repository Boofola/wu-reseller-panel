# Reseller Panel Features

## Overview

The Reseller Panel plugin provides comprehensive domain management capabilities for WordPress Multisite installations running Ultimate Multisite. This document outlines all features available in the plugin.

## Core Features

### 1. DNS Management System

Full-featured DNS record management with support for all major record types.

#### Supported Record Types
- **A Records** - IPv4 address mapping
- **AAAA Records** - IPv6 address mapping  
- **CNAME Records** - Canonical name aliases
- **MX Records** - Mail exchange servers
- **TXT Records** - Text records (SPF, DKIM, verification)
- **NS Records** - Name server delegation
- **SRV Records** - Service locator records
- **CAA Records** - Certificate authority authorization
- **PTR Records** - Reverse DNS lookups

#### DNS Features
- **CRUD Operations** - Create, Read, Update, Delete DNS records
- **Bulk Management** - Manage multiple records efficiently
- **Reset to Default** - Restore provider default DNS settings
- **Permission Control** - Customer-level access controls
- **AJAX Interface** - Real-time DNS record updates
- **Auto-refresh** - Automatic propagation checking

#### AJAX Endpoints
- `reseller_panel_get_dns_records` - Retrieve DNS records for a domain
- `reseller_panel_add_dns_record` - Add a new DNS record
- `reseller_panel_update_dns_record` - Update existing DNS record
- `reseller_panel_delete_dns_record` - Remove a DNS record
- `reseller_panel_reset_dns_records` - Reset to provider defaults

---

### 2. Domain Transfer Manager

Comprehensive domain transfer functionality for transferring domains in and out.

#### Transfer In Features
- **Authorization Code Support** - EPP/auth code validation
- **Registrant Information** - Collect required contact details
- **Status Tracking** - Monitor transfer progress
- **Email Notifications** - Automated status updates
- **Transfer History** - Complete audit trail

#### Transfer Out Features
- **Auth Code Generation** - Generate EPP codes for customers
- **Transfer Lock** - Prevent unauthorized transfers
- **Status Monitoring** - Track outgoing transfers

#### Transfer Statuses
- `pending` - Transfer initiated, awaiting approval
- `in_progress` - Transfer is processing
- `completed` - Transfer successful
- `failed` - Transfer encountered an error
- `cancelled` - Transfer was cancelled
- `rejected` - Transfer was rejected

#### Automated Monitoring
- **Hourly Cron Job** - Automatic status updates
- **Email Notifications** - Alert customers of status changes
- **Transfer Logs** - Complete transfer history

#### AJAX Endpoints
- `reseller_panel_transfer_domain` - Initiate domain transfer
- `reseller_panel_check_transfer_status` - Check transfer status
- `reseller_panel_get_auth_code` - Get authorization code
- `reseller_panel_cancel_transfer` - Cancel pending transfer

---

### 3. Domain Renewal Manager

Automated renewal system with scheduling and notifications.

#### Auto-Renewal Features
- **Automatic Scheduling** - Set it and forget it renewals
- **Grace Period Management** - Configurable renewal windows
- **Payment Integration** - Automatic payment processing
- **Renewal Notifications** - Email reminders before expiry
- **Batch Processing** - Efficient bulk renewals

#### Renewal Settings
- **Notice Period** - Days before expiry to send notices (default: 30)
- **Retry Period** - Days to retry failed renewals (default: 7)
- **Auto-Renewal Toggle** - Customer-controlled per domain
- **Renewal History** - Track all renewal attempts

#### Cron Jobs
- **Daily Batch Processing** - `reseller_panel_daily_renewal_batch`
- **Expiry Checks** - `reseller_panel_check_domain_expiry` (twice daily)

#### Notifications
- **30-Day Notice** - First renewal reminder
- **14-Day Notice** - Second renewal reminder
- **7-Day Notice** - Final renewal reminder
- **Success Notification** - Renewal completed
- **Failure Notification** - Renewal failed, manual action required

#### AJAX Endpoints
- `reseller_panel_renew_domain` - Manually renew a domain
- `reseller_panel_toggle_auto_renew` - Enable/disable auto-renewal
- `reseller_panel_get_renewal_info` - Get domain renewal information

---

### 4. Enhanced Checkout Integration

Seamless domain registration during signup and checkout.

#### Checkout Features
- **Domain Search** - Real-time availability checking
- **Pricing Display** - Show registration, renewal, and transfer prices
- **Auto-Population** - Pre-fill registrant information from customer data
- **Validation** - Client and server-side validation
- **Integration Hooks** - Integrate with Ultimate Multisite checkout

#### Registrant Fields
- First Name / Last Name
- Organization (optional)
- Email Address
- Phone Number
- Street Address
- City, State/Province, Zip/Postal Code
- Country

#### Field Validation
- **Required Fields** - Enforce mandatory information
- **Email Validation** - Verify email format
- **Phone Validation** - Check phone number format
- **Domain Format** - Validate domain name syntax

#### AJAX Endpoints
- `reseller_panel_search_domain` - Check domain availability
- `reseller_panel_get_domain_price` - Get domain pricing

---

### 5. Customer Domain Management Portal

Self-service portal for customers to manage their domains.

#### Portal Features
- **Tabbed Interface** - Domains, DNS Management, Transfers
- **Domain List** - View all registered domains
- **Status Indicators** - Visual status badges (active, pending, expired)
- **Expiry Dates** - Display expiration information
- **Auto-Renewal Toggles** - Customer control over auto-renewals
- **Quick Actions** - Renew, transfer, manage DNS

#### Shortcode
```
[reseller_panel_domains]
```

#### Shortcode Attributes
- `limit` - Maximum number of domains to display (default: 50)

#### Example Usage
```
[reseller_panel_domains limit="100"]
```

#### Portal Tabs

**Domains Tab**
- List of all customer domains
- Status, expiry date, auto-renewal status
- Quick action buttons (DNS, Renew, Transfer)
- Toggle auto-renewal on/off

**DNS Management Tab**
- Domain selector
- List all DNS records
- Add/Edit/Delete DNS records
- Reset to defaults option

**Transfers Tab**
- Transfer domain in (with auth code)
- Transfer domain out (get auth code)
- View active transfer status
- Cancel pending transfers

---

### 6. Provider Architecture

Extensible provider system supporting multiple registrars.

#### Built-in Providers
- **OpenSRS (TuCows)** - Full domain management support
- **NameCheap** - Domains, SSL, hosting, emails

#### Provider Capabilities
Each provider implements standardized methods:

**Domain Operations**
- `check_domain_availability()` - Check if domain is available
- `register_domain()` - Register a new domain
- `renew_domain()` - Renew an existing domain
- `get_domain_info()` - Get domain details
- `get_domain_pricing()` - Get pricing information

**DNS Operations**
- `get_dns_records()` - Retrieve all DNS records
- `add_dns_record()` - Add a new DNS record
- `update_dns_record()` - Update existing DNS record
- `delete_dns_record()` - Remove a DNS record
- `reset_dns_records()` - Reset to provider defaults

**Transfer Operations**
- `transfer_domain()` - Initiate domain transfer
- `check_transfer_status()` - Check transfer progress
- `get_auth_code()` - Get authorization code
- `cancel_transfer()` - Cancel a transfer

#### Adding New Providers
1. Create provider class extending `Base_Service_Provider`
2. Implement `Service_Provider_Interface`
3. Implement required methods for supported services
4. Register provider in `Provider_Manager`

---

## Security Features

### Permission Controls
- **Super Admin** - Full access to all features
- **Customer-Level** - Domain-specific permissions
- **User Ownership** - Customers can only manage their own domains

### Data Protection
- **Nonce Verification** - All AJAX requests verified
- **Input Sanitization** - All user input sanitized
- **Output Escaping** - All output properly escaped
- **Capability Checks** - Permission verification on all operations

### Secure Storage
- **Database Encryption** - Sensitive data encrypted at rest
- **No Credentials in Code** - API keys stored in database
- **Protected Log Directory** - .htaccess protection for logs

---

## Logging &amp; Monitoring

### Error Logging
- **API Errors** - Log all provider API errors
- **System Errors** - Log WordPress/PHP errors
- **User Actions** - Audit trail of customer actions

### Log Levels
- `ERROR` - Critical errors requiring attention
- `INFO` - Informational messages
- `API` - API call tracking

### Log Location
```
/wp-content/uploads/wu-logs/reseller-panel.log
```

### Log Methods
```php
\Reseller_Panel\Logger::log_error($provider, $message, $context);
\Reseller_Panel\Logger::log_info($provider, $message, $context);
\Reseller_Panel\Logger::log_api_call($provider, $action, $method, $context);
```

---

## Integration Hooks

### Filters

#### Domain Ownership
```php
// Check if user can manage DNS for a domain
apply_filters('reseller_panel_user_can_manage_dns', false, $domain, $user_id);

// Check if user can manage transfers for a domain  
apply_filters('reseller_panel_user_can_manage_transfer', false, $domain, $user_id);

// Check if user can manage renewals for a domain
apply_filters('reseller_panel_user_can_manage_renewal', false, $domain, $user_id);
```

#### Checkout Integration
```php
// Check if current page is checkout page
apply_filters('reseller_panel_is_checkout_page', false);

// Check if domain purchase enabled for plan
apply_filters('reseller_panel_domain_enabled_for_plan', true, $plan_id);

// Filter customer data for auto-population
apply_filters('reseller_panel_checkout_customer_data', $data);
```

#### Customer Portal
```php
// Filter customer domains list
apply_filters('reseller_panel_customer_domains', $domains, $user_id);

// Get customer object
apply_filters('reseller_panel_get_customer', null, $user_id);

// Control asset loading
apply_filters('reseller_panel_load_customer_assets', false);

// Customize country list
apply_filters('reseller_panel_checkout_countries', $countries);
```

### Actions

#### Checkout Process
```php
// Add domain fields to checkout
do_action('wu_checkout_form_after_plan', $plan_id, $checkout_data);

// Process domain purchase after checkout
do_action('wu_checkout_processed', $membership_id, $post_data);
```

#### Validation
```php
// Validate domain checkout fields
apply_filters('wu_checkout_validation_errors', $errors, $post_data);
```

---

## JavaScript Events

### Domain Checkout
```javascript
// Triggered when domain availability is checked
$(document).on('reseller_panel_domain_checked', function(e, domain, available) {});

// Triggered when domain pricing is loaded
$(document).on('reseller_panel_pricing_loaded', function(e, domain, pricing) {});
```

### Customer Portal
```javascript
// Triggered when domain tab is switched
$(document).on('reseller_panel_tab_changed', function(e, tabName) {});

// Triggered when DNS records are loaded
$(document).on('reseller_panel_dns_loaded', function(e, domain, records) {});

// Triggered when auto-renewal is toggled
$(document).on('reseller_panel_auto_renew_toggled', function(e, domain, enabled) {});
```

---

## Frontend Assets

### JavaScript Files
- `/assets/js/admin.js` - Admin interface functionality
- `/assets/js/domain-checkout.js` - Checkout domain search and selection
- `/assets/js/customer-domain-manager.js` - Customer portal interactions
- `/assets/js/reseller-panel-checkout.js` - Enhanced checkout features

### CSS Files
- `/assets/css/admin.css` - Admin interface styling
- `/assets/css/domain-checkout.css` - Checkout domain fields styling  
- `/assets/css/customer-domain-manager.css` - Customer portal styling

---

## Database Tables

### Custom Tables
- `wp_reseller_panel_services` - Service configurations
- `wp_reseller_panel_providers` - Provider configurations
- `wp_reseller_panel_fallback_logs` - Fallback event logs

### Site Options
- `reseller_panel_transfers` - Transfer records
- `reseller_panel_auto_renewals` - Auto-renewal preferences
- `reseller_panel_renewal_history` - Renewal attempt history
- `reseller_panel_{provider}_config` - Provider configurations

---

## Cron Jobs

### Scheduled Tasks
- **Hourly** - `reseller_panel_check_transfer_status` - Monitor active transfers
- **Daily** - `reseller_panel_daily_renewal_batch` - Process auto-renewals
- **Twice Daily** - `reseller_panel_check_domain_expiry` - Check for expiring domains

---

## Requirements

### WordPress
- WordPress 6.2 or higher
- WordPress Multisite (required)
- Ultimate Multisite 2.0.0+ (recommended)

### PHP
- PHP 7.4 or higher (PHP 8.x recommended)
- PHP Extensions:
  - cURL (required)
  - DOM (required for OpenSRS)
  - JSON (required)

### Server
- HTTPS (recommended for production)
- Cron or WP-Cron enabled
- File write permissions for logs directory

---

## Browser Support

### Desktop
- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)

### Mobile
- iOS Safari (latest 2 versions)
- Chrome Android (latest 2 versions)
- Responsive design for all screen sizes

---

## Performance

### Optimization
- **Lazy Loading** - Assets only load when needed
- **AJAX Requests** - Asynchronous operations for better UX
- **Caching** - Provider responses cached appropriately
- **Batch Processing** - Efficient bulk operations

### Scalability
- **Provider Failover** - Automatic fallback to backup providers
- **Rate Limiting** - Prevent API abuse
- **Queue System** - Background processing for long operations

---

## Support &amp; Documentation

### Documentation Files
- `README.md` - Project overview and installation
- `ARCHITECTURE.md` - Technical architecture details
- `INSTALLATION.md` - Detailed installation guide
- `QUICK_START.md` - Quick setup guide
- `FEATURES.md` - This file

### Support Resources
- GitHub Issues: https://github.com/Boofola/wu-reseller-panel/issues
- Ultimate Multisite Forum: https://wordpress.org/support/plugin/ultimate-multisite/

---

## Version History

### Version 2.0.1 (Current)
- Added DNS Management System
- Added Domain Transfer Manager
- Added Domain Renewal Manager
- Added Enhanced Checkout Integration
- Added Customer Domain Management Portal
- Added comprehensive AJAX API
- Added frontend shortcode support
- Added auto-renewal scheduling
- Added transfer monitoring system
- Added customer self-service features

### Version 2.0.0
- Complete architectural rebuild
- Provider-agnostic architecture
- Intelligent fallback routing
- Professional admin interface
- Multi-provider support (OpenSRS, NameCheap)

---

## License

GPL v2 or later

---

*For technical details, see ARCHITECTURE.md*
*For setup instructions, see INSTALLATION.md*
