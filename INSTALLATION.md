# Ultimate Multisite - Reseller Panel v2.0.0

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

## Installation

### Standalone Installation (Works Without Ultimate Multisite)

1. Upload plugin to `/wp-content/plugins/wu-reseller-panel/`
2. Network Activate the plugin
3. **If you get HTTP 500 error**, follow the Critical Fix above
4. Go to Network Admin → Reseller Panel (appears as its own menu)

### With Ultimate Multisite (Optional Enhancement)

1. First install Ultimate Multisite from WordPress.org: https://wordpress.org/plugins/ultimate-multisite/
2. Network Activate Ultimate Multisite
3. Upload this plugin to `/wp-content/plugins/wu-reseller-panel/`
4. Network Activate this plugin
5. **If you get HTTP 500 error**, follow the Critical Fix above
6. Go to Network Admin → Reseller Panel

## Requirements

- WordPress Multisite (required)
- PHP 7.8 or higher
- Ultimate Multisite v2.0.0+ (optional, enhances integration)

## Features

- Multi-provider support (OpenSRS, NameCheap)
- Service routing with intelligent fallback
- Domain registration integration
- API credential management
- Domain import tools

## Troubleshooting

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

### Database Tables Missing After Reactivation

**Why:** The activation hook requires the main class but may fail silently

**Solution:** Use the manual `create-tables.php` script instead of relying on activation hook

## Database Tables

The plugin creates these tables:

- `{prefix}_reseller_panel_services` - Service configuration
- `{prefix}_reseller_panel_providers` - Provider credentials
- `{prefix}_reseller_panel_fallback_logs` - Fallback event logs

## Support

If you encounter issues:

1. Run the diagnostic script first
2. Check your server's PHP error log
3. Look for errors starting with "Reseller Panel -"
4. Create an issue with diagnostic output

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

## License

GPL v2 or later

## Credits

Developed for Ultimate Multisite platform

---

**Documentation Written by:** Anthropic Claude AI
**Date:** November 23, 2025  
**Status:** ✅ FULLY COMPATIBLE
