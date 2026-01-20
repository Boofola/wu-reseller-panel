# Changelog - Bug Fixes

## Version [Current] - Bug Fixes Applied

### Fixed Issues

#### 1. Services Settings Page Empty
- **Issue:** Services Settings page displayed no services even with configured providers
- **Cause:** Default services were never seeded in the database on plugin activation
- **Fix:** Added `populate_default_services()` method to automatically create domains, SSL, hosting, email, and marketing services
- **File:** `inc/class-reseller-panel.php`

#### 2. OpenSRS Domain Import Failing
- **Issue:** Domain import shows "✗ No domains found from provider" error
- **Cause:** XML response parser only extracted top-level fields, not nested `dt_array`/`dt_assoc` structures from OpenSRS API
- **Fix:** Enhanced `parse_xml_response()` with recursive parsing methods for nested XML data structures
- **Methods Added:**
  - `extract_nested_xml_data()` - Detects and handles nested structures
  - `parse_dt_array()` - Recursively parses indexed arrays
  - `parse_dt_assoc()` - Recursively parses associative arrays
- **File:** `inc/providers/class-opensrs-provider.php`

#### 3. OpenSRS Pricing Markup/Fee
- **Issue:** No way to add custom markup to OpenSRS domain pricing
- **Context:** OpenSRS changed pricing model and no longer provides prices in reseller panel
- **Solution:** Added optional `domain_fee` configuration field to OpenSRS provider
- **Features:**
  - Optional markup field (leave blank for no markup)
  - Applied to all pricing types: registration, renewal, transfer
  - Stored in provider configuration alongside credentials
- **File:** `inc/providers/class-opensrs-provider.php`

### Enhanced Logging
- Domain importer now logs detailed import progress to WordPress error logs
- Useful for debugging import issues
- **File:** `inc/importers/class-domain-importer.php`

### Backward Compatibility
All changes are fully backward compatible:
- Existing configurations continue to work
- Optional features default to safe values
- No database schema changes required
- No breaking changes to API or functionality

