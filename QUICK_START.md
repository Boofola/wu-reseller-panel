# Reseller Panel - Quick Start Guide

## Installation

1. **Activate the Plugin**
   - Go to Ultimate Multisite network admin
   - Navigate to Network → Plugins
   - Search for "Reseller Panel"
   - Click "Network Activate"

2. **Verify Menu Appears**
   - In network admin, look for "Reseller Panel" under the main Ultimate Multisite menu
   - You should see three sub-pages:
     - Reseller Panel (Overview)
     - Services Settings
     - Provider Settings

## Quick Configuration

### Step 1: Configure OpenSRS (Domains)

1. Go to **Reseller Panel → Provider Settings**
2. Click the **OpenSRS** tab
3. Enter your OpenSRS credentials:
   - **API Key**: Your OpenSRS Private Key from manage.opensrs.com
   - **Reseller Username**: Your OpenSRS reseller username
   - **Environment**: Choose "Sandbox" for testing or "Production" for live
4. Click **"Save OpenSRS Settings"**
5. Click **"Test Connection"** to verify credentials work

### Step 2: Configure NameCheap (Domains + SSL + Hosting + Emails)

1. Go to **Reseller Panel → Provider Settings**
2. Click the **NameCheap** tab
3. Enter your NameCheap credentials:
   - **API User**: Your NameCheap API username (usually same as account username)
   - **API Key**: Your API key from namecheap.com/profile/tools/apiaccess/
   - **Username**: Your NameCheap account username
   - **Client IP** (optional): Your server IP if your API is IP-restricted
   - **Environment**: Choose "Sandbox" for testing or "Production" for live
   - **Base Domain Price**: Default price for domains (optional override)
   - **Base SSL Price**: Default price for SSL certs (optional override)
4. Click **"Save NameCheap Settings"**
5. Click **"Test Connection"** to verify credentials work

### Step 3: Configure Services

1. Go to **Reseller Panel → Services Settings**
2. For each service (Domains, SSL, Hosting, Emails, Marketing):
   - **Enable** the service with the checkbox
   - **Select Default Provider**: Choose primary provider from dropdown
   - **Select Fallback Provider** (optional): Choose secondary provider
3. Note: Greyed-out providers don't support that service
4. Click **"Save Services Settings"**

**Example Configuration:**
- **Domains Service:**
  - ✓ Enabled
  - Default Provider: OpenSRS
  - Fallback Provider: NameCheap
  
- **SSL Service:**
  - ✓ Enabled
  - Default Provider: NameCheap
  - Fallback Provider: (none)

## Understanding Fallback

When a service request is made (e.g., registering a domain):

1. **Primary Provider Tries First** - OpenSRS attempts to register domain
2. **If Primary Fails** - System automatically tries NameCheap
3. **If Fallback Works** - Domain registers via NameCheap (customer doesn't see error)
4. **Admin Gets Notified** - Email sent to admin explaining issue with primary provider
5. **Request Succeeds** - Customer completes transaction without interruption

This means **zero downtime** if one provider has issues!

## Database Tables Created

### reseller_panel_services
Tracks enabled services and their default/fallback providers:
```
Service Key | Name      | Enabled | Default Provider | Fallback Provider
domains     | Domains   | 1       | opensrs          | namecheap
ssl         | SSL       | 1       | namecheap        | (empty)
emails      | Emails    | 0       | (empty)          | (empty)
```

### reseller_panel_providers
Stores provider credentials (encrypted in JSON config field):
```
Provider Key | Name       | Config (JSON)
opensrs      | OpenSRS    | {"api_key": "...", "username": "..."}
namecheap    | NameCheap  | {"api_user": "...", "api_key": "..."}
```

### reseller_panel_fallback_logs
Audit trail of fallback events:
```
Service | Primary  | Fallback | Error Message              | When
domains | opensrs  | namecheap| API timeout               | 2024-01-15 14:23:45
ssl     | namecheap| (none)   | Invalid API key           | 2024-01-15 14:25:10
```

## API Usage Examples

### Getting Started in Custom Code

```php
<?php
// In your plugin or theme (not directly in template)

// Get provider manager
$manager = \Reseller_Panel\Provider_Manager::get_instance();

// Get all configured providers
$providers = $manager->get_configured_providers();

// Get providers supporting a specific service
$domain_providers = $manager->get_providers_by_service( 'domains' );

// Get a specific provider
$opensrs = $manager->get_provider( 'opensrs' );

// Check if provider is configured
if ( $opensrs && $opensrs->is_configured() ) {
    echo "OpenSRS is ready!";
}
```

### Executing a Service

```php
<?php
// Route service request through fallback system
$router = \Reseller_Panel\Service_Router::get_instance();

$result = $router->execute_service( 'domains', 'register', [
    'domain' => 'example.com',
    'period' => 1,
    'registrant' => 'John Doe'
] );

if ( is_wp_error( $result ) ) {
    // Handle error - service failed even with fallback
    echo "Error: " . $result->get_error_message();
} else {
    // Service succeeded (via primary or fallback provider)
    echo "Domain registered successfully!";
}
```

### Viewing Fallback Logs

```php
<?php
// Get recent fallback events
$router = \Reseller_Panel\Service_Router::get_instance();
$logs = $router->get_fallback_logs( 10 ); // Last 10 events

foreach ( $logs as $log ) {
    echo "Service: " . $log->service_key . "\n";
    echo "Failed: " . $log->primary_provider . "\n";
    echo "Used: " . $log->fallback_provider . "\n";
    echo "Reason: " . $log->error_message . "\n";
    echo "---\n";
}
```

## Common Issues & Solutions

### Menu Not Appearing
**Problem:** Reseller Panel menu doesn't show under Ultimate Multisite
**Solution:**
- Verify plugin is Network Activated (not just activated for single site)
- Verify you're in network admin (not single site admin)
- Clear browser cache
- Deactivate and reactivate plugin

### Settings Not Saving
**Problem:** Credentials entered but not saved
**Solution:**
- Verify you're logged in as network admin
- Verify you clicked "Save Settings" button
- Check browser console for JavaScript errors
- Try different browser

### Connection Test Fails
**Problem:** "Connection failed" when testing provider
**Solution for OpenSRS:**
- Verify API Key is correct (not username)
- Verify username matches OpenSRS account
- Try Sandbox environment first
- Check API key has proper permissions in OpenSRS account

**Solution for NameCheap:**
- Verify API key from namecheap.com/profile/tools/apiaccess/
- Verify API user is spelled correctly
- If IP-restricted, ensure Client IP field is correct
- Try Sandbox environment first

### Fallback Not Working
**Problem:** Service fails even though fallback provider is configured
**Solution:**
- Verify both providers are configured (Save Settings complete)
- Verify fallback provider actually supports the service (check Services page)
- Check admin email for fallback notifications (they're working!)
- Check fallback logs for errors

## File Structure

```
reseller-panel.php                          # Main plugin file
├── inc/
│   ├── class-reseller-panel.php           # Core addon class
│   ├── class-provider-manager.php         # Provider factory
│   ├── class-service-router.php           # Service execution + fallback
│   ├── interfaces/
│   │   └── class-service-provider-interface.php
│   ├── abstract/
│   │   └── class-base-service-provider.php
│   ├── providers/
│   │   ├── class-opensrs-provider.php     # OpenSRS integration
│   │   └── class-namecheap-provider.php   # NameCheap integration
│   └── admin-pages/
│       ├── class-admin-page.php           # Base admin page
│       ├── class-services-settings-page.php
│       └── class-provider-settings-page.php
├── assets/
│   ├── css/admin.css                      # Admin styling
│   └── js/admin.js                        # Admin scripts
└── docs/
    ├── ARCHITECTURE.md                    # Technical documentation
    └── RESTRUCTURE_SUMMARY.md             # Project summary
```

## Testing Your Setup

### 1. Verify Plugin Activation
```
In network admin → Plugins
Search for "Reseller Panel" → Should be "Network Activated"
```

### 2. Test OpenSRS Connection
```
Admin → Reseller Panel → Provider Settings → OpenSRS
Enter test credentials
Click "Test Connection"
Should show: "Connection successful!"
```

### 3. Test NameCheap Connection
```
Admin → Reseller Panel → Provider Settings → NameCheap
Enter test credentials
Click "Test Connection"
Should show: "Connection successful!"
```

### 4. Configure Services
```
Admin → Reseller Panel → Services Settings
Check "Enabled" for Domains
Select "OpenSRS" as Default Provider
Select "NameCheap" as Fallback Provider
Click "Save Services Settings"
```

### 5. Test Fallback (Advanced)
```
Temporarily disable OpenSRS credentials
Execute domain registration (or test code)
Should use NameCheap instead
Should send admin email about fallback
```

## Support & Help

### Getting API Keys

**OpenSRS:**
- Login to manage.opensrs.com
- Go to Account → Settings → API
- Copy "Private Key" (this is your API Key)
- Document: https://manage.opensrs.com/account/settings/api

**NameCheap:**
- Login to namecheap.com
- Go to Profile → Tools → API Access
- Enable API Access
- Copy your API Key
- Document: https://www.namecheap.com/support/api/intro/

### Documentation
- Full Architecture Guide: `ARCHITECTURE.md`
- Project Summary: `RESTRUCTURE_SUMMARY.md`
- This Guide: `QUICK_START.md`

### For Developers
- See `ARCHITECTURE.md` for adding new providers
- See `inc/providers/` for provider implementation examples
- See `inc/admin-pages/` for extending admin interface

## What's Next?

After basic setup, consider:

1. **Add More Providers**
   - Dynadot, GoDaddy, CentralNic, WHMCS, cPanel, etc.
   - Use `inc/providers/class-opensrs-provider.php` as template
   - Just extend `Base_Service_Provider` and implement required methods

2. **Integrate with UMS Checkout**
   - Add domain registration to checkout flow
   - Tie into UMS order/customer systems
   - Use `Service_Router` to execute domain operations

3. **Create Customer Dashboard**
   - Let customers manage their domains/SSL/email
   - Show domain renewals needed
   - Display SSL certificates expiring

4. **Sync Data to UMS Products**
   - Import TLD pricing from OpenSRS
   - Create UMS Products for each domain TLD
   - Allow admins to override pricing

## Version Information

- **Plugin Version:** 2.0.0
- **Architecture:** Ultimate Multisite Addon Pattern
- **PHP Minimum:** 7.4
- **WordPress Minimum:** 5.9
- **Ultimate Multisite Minimum:** 1.0

---

**Ready to get started?** Head to **Network Admin → Reseller Panel** and configure your first provider!

---

**Documentation Written by:** Anthropic Claude AI
**Date:** November 23, 2025  
**Status:** ✅ FULLY COMPATIBLE