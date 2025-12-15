Possible future improvements:
- Add test buttons next to each provider field in the settings UI for more granular diagnostics.
Domain Manager for Ultimate Multisite
- Add Dynadot integration
- Add GoDaddy integration
- Add CentralNic integration
- Add WHMCS integration
- Add cPanel integration for selling emails
- Add 201 integration
- Add DomainNameAPI integration
- Add ResellerClub integration
- Add NamesIlo integrations
- Add ResellerPanel integration
- Add ability to sell emails, marketing, and hosting through those who support integration

Purpose:
This add-on allows selling and managing domains through Ultimate Multisite.

NameCheap integration
- The plugin supports NameCheap as a domain provider.
- Configure NameCheap credentials at Network Admin → Ultimate Multisite → Reseller Panel.
- Provide `ApiUser`, `ApiKey`, `UserName`, and `ClientIp` (authorized server IP) in the settings.

OpenSRS integration
- The plugin supports OpenSRS as a domain provider.
- Configure OpenSRS credentials at Network Admin → Ultimate Multisite → Reseller Panel.
- Provide `UserName`, and `ApiKey` in the settings.


Notes and limitations
- TLD import/pricing is only available via OpenSRS. NameCheap does not expose a direct pricing import endpoint in this integration; you should set product prices manually or keep OpenSRS as the pricing source.
- NameCheap domain registrations require full contact details. The checkout now includes a contact section — populate those fields when using NameCheap.
- Use the "Test Connection" button in settings to validate the selected default provider.

If you renamed classes/files while experimenting, this plugin attempts to preserve the original class names. If you find fatal errors, revert local renames and ensure files use the plugin's original class names (e.g. `WU_OpenSRS_API`, `WU_OpenSRS_Settings`).

Known Issues:
- OpenSRS isn't saving entered API Key.
- Menu name duplicated.
- Menu not showing under Ultimate Multisite menu.

---

**Documentation Written by:** Anthropic Claude AI
**Date:** November 23, 2025  
**Status:** ✅ FULLY COMPATIBLE