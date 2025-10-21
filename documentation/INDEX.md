# Documentation Index

**Square Menu Display Premium Deluxe Pro v3.1**

> **Note**: All documentation in this folder was AI-generated (ChatGPT & Claude) for reference and development purposes. While comprehensive and technically accurate, please verify critical implementation details against the actual codebase.

---

## 📋 Overview

This folder contains comprehensive technical documentation for the plugin, including development guides, deployment checklists, testing procedures, and technical analyses.

For general plugin information, see [README.md](../README.md) in the root directory.

---

## 📚 Documentation Files

### Deployment & Production

| Document | Description |
|----------|-------------|
| **[PRODUCTION-READY.md](PRODUCTION-READY.md)** | Complete production deployment checklist with all fixes, security improvements, and performance optimizations applied to v3.0 |

### Features & Implementation

| Document | Description |
|----------|-------------|
| **[CONTENT-CHANGE-DETECTION.md](CONTENT-CHANGE-DETECTION.md)** | Technical documentation of the intelligent content change detection system that automatically detects menu updates and triggers appropriate refresh type |
| **[FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md](FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md)** | Details fixes for sold-out badge styling and custom category item count issues, including new-style vs old-style mapping |
| **[PWA-SCOPE-FIX.md](PWA-SCOPE-FIX.md)** | Explains how PWA install prompts were restricted to menu app pages only, preventing install buttons from appearing site-wide |

### Testing & Quality Assurance

| Document | Description |
|----------|-------------|
| **[TESTING-GUIDE.md](TESTING-GUIDE.md)** | Step-by-step testing procedures for the intelligent content change detection system, including all test scenarios and success criteria |

### Code Analysis

| Document | Description |
|----------|-------------|
| **[CSS-IMPORTANT-ANALYSIS.md](CSS-IMPORTANT-ANALYSIS.md)** | Comprehensive analysis of all 68 `!important` declarations in CSS files, with justifications and refactoring recommendations |
| **[LOGGING_AUDIT.md](LOGGING_AUDIT.md)** | Security audit results showing removal of 67 console.log statements from production JavaScript files |

---

## 🗂️ Documentation by Category

### For Developers

If you're working on the codebase:
1. Start with [PRODUCTION-READY.md](PRODUCTION-READY.md) to understand what's been fixed
2. Review [CONTENT-CHANGE-DETECTION.md](CONTENT-CHANGE-DETECTION.md) for the refresh system architecture
3. Check [FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md](FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md) for mapping system details
4. Use [CSS-IMPORTANT-ANALYSIS.md](CSS-IMPORTANT-ANALYSIS.md) when working with styles

### For QA/Testing

If you're testing the plugin:
1. Use [TESTING-GUIDE.md](TESTING-GUIDE.md) for comprehensive test scenarios
2. Reference [PRODUCTION-READY.md](PRODUCTION-READY.md) for the complete list of features to test

### For Deployment

If you're deploying to production:
1. Follow [PRODUCTION-READY.md](PRODUCTION-READY.md) checklist
2. Review [PWA-SCOPE-FIX.md](PWA-SCOPE-FIX.md) for PWA deployment notes
3. Check [LOGGING_AUDIT.md](LOGGING_AUDIT.md) to verify console logs are removed

---

## 🔍 Quick Reference

### Key Technical Concepts

- **New-Style Mapping**: Uses `instance_id` to allow same item in multiple custom categories
- **Old-Style Mapping**: Direct `item_id` mapping (one item per category)
- **Content Hash**: MD5 hash for detecting menu content changes
- **Manifest Scope**: Restricts PWA installation to specific URL paths
- **Sold-Out Priority**: Manual override > Square location_overrides > item-level flag

### Files Modified in v3.1

**PHP Files**:
- `includes/class-ajax-handler.php` - Enhanced for new-style mapping support
- `includes/class-shortcode.php` - Uses instance_id consistently
- `includes/class-sync-manager.php` - Fixed sold-out sync, removed duplicates
- `includes/class-manifest-generator.php` - Dynamic PWA scope

**JavaScript Files**:
- `assets/js/refresh.js` - Intelligent content change detection
- `assets/js/pwa-install.js` - Added menu app page check
- `assets/js/menu-app-frontend.js` - Removed console.logs
- `assets/js/help-admin.js` - Removed console.logs
- `assets/js/pwa-install.js` - Removed console.logs
- `assets/js/service-worker.js` - Removed console.logs

**CSS Files**:
- `assets/css/smdp-structural.css` - Contains all 68 `!important` declarations (justified)

### Performance Metrics

- **~250x faster** sold-out-only updates (50KB → 200 bytes)
- **67 console.log statements** removed
- **Database autoload** optimized for large options
- **Duplicate API calls** eliminated

---

## 📝 Documentation Standards

All documentation in this folder follows these standards:

### Structure
- **Problem/Issue**: What was wrong
- **Root Cause**: Why it was wrong
- **Solution**: How it was fixed
- **Testing**: How to verify the fix
- **Files Modified**: What changed and where

### Code References
- File paths use forward slashes
- Line numbers included where applicable
- Before/After code examples provided
- Links to specific file locations when possible

### Version History
- All docs dated with YYYY-MM-DD format
- Version number specified (e.g., v3.1)
- Changes tracked in PRODUCTION-READY.md

---

## 🔗 Related Resources

### External Documentation
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)
- [Square Catalog API](https://developer.squareup.com/docs/catalog-api/what-it-does)
- [PWA Manifest Specification](https://www.w3.org/TR/appmanifest/)
- [Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)

### Plugin Files
- Main plugin file: `square-menu-display-pwa.php`
- Admin settings: `includes/class-admin-settings.php`
- Menu builder: `includes/class-menu-app-builder.php`
- Sync manager: `includes/class-sync-manager.php`

---

## 💡 Tips

### Finding Information Quickly

1. **Looking for a specific fix?**
   - Check [PRODUCTION-READY.md](PRODUCTION-READY.md) changelog

2. **Need to understand how feature X works?**
   - Search all docs for the feature name
   - Check relevant file in "Files Modified" sections

3. **Setting up for testing?**
   - Start with [TESTING-GUIDE.md](TESTING-GUIDE.md)

4. **Debugging an issue?**
   - Check if it's a known fix in [PRODUCTION-READY.md](PRODUCTION-READY.md)
   - Review related feature docs (e.g., sold-out issues → [FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md](FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md))

### Keeping Documentation Updated

When making changes to the plugin:
1. Update relevant doc files with changes
2. Add new sections to PRODUCTION-READY.md changelog
3. Update file modification lists
4. Create new doc file for major features
5. Update this INDEX.md if adding new docs

---

## ✅ Documentation Coverage

Current documentation covers:

- ✅ Production deployment checklist
- ✅ Intelligent content change detection system
- ✅ Sold-out status and custom categories fixes
- ✅ PWA scope restriction
- ✅ Testing procedures
- ✅ CSS `!important` analysis
- ✅ Console.log removal audit

**Missing documentation** (future additions):
- Square OAuth authentication flow
- Webhook signature verification
- Admin menu editor drag-and-drop
- Help/Bill request system
- Table number management

---

## 📧 Contributing to Documentation

If you add new features or fix bugs:

1. **Create a new doc file** for significant features
2. **Update existing docs** for enhancements to existing features
3. **Follow the template**:
   ```markdown
   # Feature/Fix Name

   **Date**: YYYY-MM-DD
   **Version**: X.X
   **Issue**: Brief description

   ## Problem
   [What was wrong]

   ## Root Cause
   [Why it was wrong]

   ## Solution
   [How you fixed it]

   ## Testing
   [How to verify]

   ## Files Modified
   [List with line numbers]
   ```

4. **Update this INDEX.md** to include your new documentation

---

**Last Updated**: 2025-10-21
**Documentation Version**: 3.1
