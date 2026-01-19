# Installation Guide — wu-reseller-panel

Complete installation, configuration, and troubleshooting guide for the Domain Manager plugin (Reseller Panel for Ultimate Multisite).

---

## ⚠️ CRITICAL: Database Table Creation Issue

**If you see HTTP 500 error after activation**, the database tables were not created. This is a known issue with the activation hook.

### 🔧 IMMEDIATE FIX (2 minutes):

1. **Run the table creation script:**
   - Go to: `https://your-site.com/wp-content/plugins/wu-reseller-panel/create-tables.php`
   - Wait for "SUCCESS!" message
   
2. **Verify it worked:**
   - Refresh: `https://your-site.com/wp-content/plugins/wu-reseller-panel/diagnostic.php`
   - All tables should show "✓ Exists"
   
3. **Clean up (IMPORTANT):**
   - Delete `create-tables.php` from the plugin folder for security

4. **You're done!**
   - Go to Network Admin → Reseller Panel

---

## Prerequisites

Before installing, ensure your environment meets these requirements:

- **WordPress Multisite** with Ultimate Multisite / WP Ultimo plugin installed and active
  - Ultimate Multisite: https://github.com/Multisite-Ultimate/ultimate-multisite
  - Or from WordPress.org: https://wordpress.org/plugins/ultimate-multisite/
- **PHP 7.8 or higher** (8.x recommended)
- **cURL extension enabled** (used by provider API wrappers)
- **Appropriate file permissions** to upload plugins and create DB tables
  - Files: 644
  - Folders: 755

---

## Installation

### Standalone Installation (Works Without Ultimate Multisite)

1. Upload plugin to `/wp-content/plugins/wu-reseller-panel/`
2. Network Activate the plugin from Network Admin > Plugins
3. **If you get HTTP 500 error**, follow the Critical Fix above
4. Go to Network Admin → Reseller Panel (appears as its own menu)

### Installation with Ultimate Multisite (Recommended)

1. First install Ultimate Multisite from WordPress.org: https://wordpress.org/plugins/ultimate-multisite/
2. Network Activate Ultimate Multisite
3. Upload this plugin to `/wp-content/plugins/wu-reseller-panel/`
   - Or copy to `wp-content/mu-plugins/` for must-use plugin installation
4. Network Activate this plugin
5. **If you get HTTP 500 error**, follow the Critical Fix above
6. After activation, the plugin will create its DB tables (pricing and domains) and schedule cron jobs
7. Go to Network Admin → Reseller Panel

---

## Features

The plugin provides comprehensive reseller capabilities:

- **Multi-provider support** (OpenSRS, NameCheap)
- **Service routing** with intelligent fallback
- **Domain registration** integration
- **API credential management** with test connection
- **Domain import tools** (TLD listing and pricing sync)
- **Automatic renewals** and expiration notifications
- **WHOIS privacy** options
- **Admin notifications** on provider failures
- **Audit logging** for troubleshooting

---

## Configuration (Network Admin)

### Initial Setup

1. Visit: **Network Admin → Reseller Panel → Provider Settings**
2. Configure your provider credentials:

#### OpenSRS Configuration
- **Reseller Username:** Your OpenSRS reseller username
- **API Key:** Your OpenSRS API key
- **Mode:** Choose `test` or `live` environment

#### NameCheap Configuration
- **ApiUser:** Your NameCheap API user
- **ApiKey:** Your NameCheap API key
- **UserName:** Your NameCheap username
- **Client IP:** Your server's authorized public IP address
- **Mode:** Choose `sandbox` or `live` environment

3. **Set the default provider** under "Default Domain Provider" (OpenSRS or NameCheap)
4. **Test the connection** using the Provider Connection Test buttons to verify each provider's connection
   - The UI will show success/error messages

### Service Configuration

1. Visit: **Network Admin → Reseller Panel → Services Settings**
2. Enable the services you want to offer:
   - Domains
   - SSL Certificates
   - Hosting
   - Email Services
3. Select default and fallback providers for each service
4. Save your configuration

---

## Product Setup (Per Product)

1. Create a product and set its type to **Domain** (the plugin registers this product type)
2. In the product editor, under **Domain Product Settings**:
   - Add the allowed TLDs for that product (import TLDs from Settings if needed)
   - Choose pricing strategy:
     - **Dynamic pricing** - Fetch provider price
     - **Fixed pricing** - Use product price
   - Toggle default **auto-renew** option
   - Toggle **WHOIS privacy** option
   - Optionally **override provider selection** for the product (choose OpenSRS or NameCheap) to make the product provider-specific

---

## Importing TLDs and Pricing

1. Visit: **Network Admin → Reseller Panel → TLD Management**
2. Import available TLDs from the configured provider
3. Refresh pricing as needed
4. Imported TLDs are stored in the database table `wp_wu_opensrs_pricing` (or a different prefix if your site uses one)

**Note:** TLD import/pricing is only available via OpenSRS. NameCheap does not expose a direct pricing import endpoint in this integration; you should set product prices manually or keep OpenSRS as the pricing source.

---

## Checkout Experience

- When customers purchase a Domain product, the checkout shows a **domain-search UI** and **registrant contact fields**
- If NameCheap is selected as provider (per-product or default), registrant contact fields are **required**
  - NameCheap domain registrations require full contact details
- The checkout enforces this server-side via the `wu_setup_checkout` hook and will block completion (or show inline error) if missing

---

## Renewals and Auto-Renew

The plugin automatically schedules renewal tasks:

- **Daily pricing updates** - Keep TLD prices current
- **Weekly renewal checks** - Monitor expiring domains
- **Monthly expiration notifications** - Email notifications to customers
- **Auto-renew attempts** - Performed for domains with `auto_renew` enabled when they are close to expiration

These cron hooks are scheduled during activation.

---

## Database Tables Created

The plugin creates these tables automatically on activation:

- **`{prefix}_reseller_panel_services`** - Service configuration (enabled/disabled, default providers)
- **`{prefix}_reseller_panel_providers`** - Provider credentials and settings
- **`{prefix}_reseller_panel_fallback_logs`** - Fallback event logs for troubleshooting

---

## Complete Troubleshooting Guide

### HTTP 500 Error on Provider Settings Page

**Cause:** Database tables not created during activation

**Solution:** Run `create-tables.php` script (see Critical Fix section above)

### "WP Ultimo Class Not Found" Warning

**Impact:** Plugin will work, but some integrations may be limited

**Solution:** Install and activate WP Ultimo v2.0.0+ for full functionality

### Classes Not Loading

**Diagnostic:** Run `diagnostic.php` to see which classes failed to load

**Solution:** 
1. Check file permissions (should be 644 for files, 755 for folders)
2. Verify all files are uploaded correctly
3. Check server error logs for PHP fatal errors
4. If you renamed classes/files while experimenting, revert local renames and ensure files use the plugin's original class names (e.g., `WU_OpenSRS_API`, `WU_OpenSRS_Settings`)

### Database Tables Missing After Reactivation

**Why:** The activation hook requires the main class but may fail silently

**Solution:** Use the manual `create-tables.php` script instead of relying on activation hook

### Provider Testing Fails

**Solution:**
- Re-check credentials and the API mode (sandbox vs live)
- For NameCheap, confirm that your server IP is authorized in the NameCheap control panel
- Try using the sandbox/test environment first
- Check with provider - API key might need activation

### Menu Not Showing

**Solution:**
- Verify plugin is **Network Activated** (not single-site)
- Verify you're in **Network Admin** (not single site admin)
- Clear browser cache

### Settings Not Saving

**Solution:**
- Verify you have **manage_network** capability
- Check browser console for JavaScript errors
- Ensure you clicked the **Save** button

### Parse/Syntax Errors

**Solution:**
- Run the `dev-check.ps1` script or `php -l` locally
- Reboot the server if PHP was recently updated and the CLI is not working

---

## Developer Tools & Diagnostics

### dev-check.ps1

A PowerShell helper in the repository root that will run `php -l` across PHP files (if PHP CLI is available) and perform a lightweight brace-balance check.

**Run locally (PowerShell):**

```powershell
# Run the helper
.\dev-check.ps1

# Or lint PHP files directly (requires PHP CLI):
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

### Continuous Integration

The repository includes a GitHub Actions workflow `.github/workflows/php-lint.yml` which runs `php -l` on all PHP files for pushes and PRs to `main`.

---

## Rollback / Backup

- **Always make backups** before testing new code and plugins
- Legacy files were archived under `archive/includes/` in this repository
- Restore them if needed

---

## Support

If you encounter issues:

1. Run the diagnostic script first (`diagnostic.php`)
2. Check your server's PHP error log
3. Look for errors starting with "Reseller Panel -"
4. Create an issue with diagnostic output
5. See **[QUICK_START.md](QUICK_START.md)** for more troubleshooting

The plugin stores provider-specific implementations in `includes/class-opensrs-api.php` and `includes/class-namecheap-api.php`. Plugin-level provider-agnostic helpers are in files named `class-domain-manager-*.php`.

---

## Changelog

### Version 2.0.1 (November 25, 2025)
- **FIXED:** Critical bug preventing plugin from loading when Ultimate Multisite absent
- **FIXED:** Removed early return statement that blocked plugin initialization
- **FIXED:** Added WP_INSTALLING guard to prevent activation errors
- **FIXED:** Changed admin_notices to network_admin_notices for multisite
- **IMPROVED:** Documentation now clarifies standalone functionality
- **IMPROVED:** Diagnostic messages show Ultimate Multisite as optional (warning, not error)

### Version 2.0.0 (November 24, 2025)
- **FIXED:** Removed WP Ultimo requirement from activation hook
- **FIXED:** Added manual table creation script for reliability
- **FIXED:** Improved error logging throughout plugin
- **FIXED:** Fixed class loading order issues
- **ADDED:** Comprehensive diagnostic script
- **ADDED:** Better error handling in admin pages
- Complete rewrite with provider-agnostic architecture
- Added NameCheap provider support
- Improved service routing with fallback logic

### Version 1.x
- Original OpenSRS-only implementation

---

## License

GPL v2 or later

## Credits

Developed for Ultimate Multisite platform

---

**Documentation Written by:** Anthropic Claude AI  
**Date:** November 23, 2025  
**Status:** ✅ FULLY COMPATIBLE
