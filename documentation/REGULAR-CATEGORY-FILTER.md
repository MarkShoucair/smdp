# Regular Category Filter Fix

**Date**: 2025-10-21
**Version**: 3.1
**Issue**: Items showing as unassigned or in wrong categories due to MENU_CATEGORY vs REGULAR_CATEGORY types

---

## Problem

After implementing category ID validation, items were still showing as "Unassigned" or appearing in incorrect categories. Investigation revealed that Square has **two types of categories**:

1. **`REGULAR_CATEGORY`** - Customer-facing menu display categories
2. **`MENU_CATEGORY`** - POS-only organizational categories (internal use)

The plugin was treating both types equally, which caused:
- Items assigned to POS-only categories appearing as "Unassigned"
- Incorrect category mappings using MENU_CATEGORY IDs
- Categories list cluttered with internal POS organization structure

---

## Square Category Types

### REGULAR_CATEGORY
- **Purpose**: Menu display and customer-facing applications
- **Visibility**: Shown to customers in online ordering, kiosks, etc.
- **Example**: "Bowls", "Drinks", "Desserts"
- **Should be synced**: ✅ YES

### MENU_CATEGORY
- **Purpose**: POS internal organization only
- **Visibility**: Only visible to staff in Square POS
- **Example**: "Register 1 Items", "Seasonal Specials Setup"
- **Should be synced**: ❌ NO

### How Items Reference Categories

Items can have both types:
```json
{
  "item_data": {
    "name": "Olympia Power Bowl",
    "categories": [
      {"id": "CAT123"},  // Could be REGULAR or MENU type
      {"id": "CAT456"}   // Could be REGULAR or MENU type
    ],
    "reporting_category": {
      "id": "CAT789"  // Could be REGULAR or MENU type
    }
  }
}
```

The plugin must check each category's `category_type` field to determine if it should be used for menu display.

---

## Solution

Updated both `update_item_mappings()` and `process_categories()` functions to filter for **REGULAR_CATEGORY only**.

### Change 1: Item Mapping Filter (Lines 296-314)

**Before**:
```php
foreach ( $objects as $obj ) {
    if ( $obj['type'] === 'CATEGORY' ) {
        $valid_category_ids[] = $obj['id'];
        $category_names[$obj['id']] = $obj['category_data']['name'] ?? 'Unknown';
    }
}
```

**After**:
```php
$skipped_menu_cats = 0;
foreach ( $objects as $obj ) {
    if ( $obj['type'] === 'CATEGORY' ) {
        // Check if this is a REGULAR_CATEGORY (for menu display) vs MENU_CATEGORY (POS only)
        $category_type = $obj['category_data']['category_type'] ?? 'REGULAR_CATEGORY';

        if ( $category_type === 'REGULAR_CATEGORY' ) {
            $valid_category_ids[] = $obj['id'];
            $category_names[$obj['id']] = $obj['category_data']['name'] ?? 'Unknown';
        } else {
            $skipped_menu_cats++;
        }
    }
}
error_log('[SMDP Sync] Found ' . count($valid_category_ids) . ' REGULAR categories (skipped ' . $skipped_menu_cats . ' MENU categories)');
```

### Change 2: Category Storage Filter (Lines 395-404)

**Before**:
```php
foreach ( $objects as $obj ) {
    if ( isset( $obj['type'] ) && $obj['type'] === 'CATEGORY' ) {
        $cd   = $obj['category_data'] ?? array();
        $name = $cd['name'] ?? 'Unnamed Category';
        $slug = sanitize_title( $name );
        $id   = $obj['id'];
        // ... rest of processing
```

**After**:
```php
foreach ( $objects as $obj ) {
    if ( isset( $obj['type'] ) && $obj['type'] === 'CATEGORY' ) {
        $cd = $obj['category_data'] ?? array();

        // Only process REGULAR_CATEGORY types (not MENU_CATEGORY which is POS-only)
        $category_type = $cd['category_type'] ?? 'REGULAR_CATEGORY';
        if ( $category_type !== 'REGULAR_CATEGORY' ) {
            continue; // Skip MENU_CATEGORY types
        }

        $name = $cd['name'] ?? 'Unnamed Category';
        $slug = sanitize_title( $name );
        $id   = $obj['id'];
        // ... rest of processing
```

---

## How It Works

### Sync Flow

1. **Fetch all catalog objects** from Square API
2. **First Pass - Build valid category list**:
   - Loop through all CATEGORY objects
   - Check `category_type` field
   - If `REGULAR_CATEGORY` → Add to `$valid_category_ids`
   - If `MENU_CATEGORY` → Skip and increment counter
   - Log how many of each type were found

3. **Second Pass - Assign items to categories**:
   - Loop through all ITEM objects
   - Check item's `categories[]` array
   - For each category ID, verify it's in `$valid_category_ids`
   - Only assign if category is REGULAR_CATEGORY type
   - Fall back to `reporting_category` if valid
   - Otherwise mark as "unassigned"

4. **Category Storage**:
   - Only store REGULAR_CATEGORY types
   - Skip MENU_CATEGORY types entirely
   - Preserve custom categories (created manually)

### Default Value

```php
$category_type = $obj['category_data']['category_type'] ?? 'REGULAR_CATEGORY';
```

We default to `REGULAR_CATEGORY` if the field is missing, since older Square API responses may not include this field, and all categories were effectively "regular" in older versions.

---

## Impact

### Before Fix
- ❌ Items assigned to MENU_CATEGORY showed as "Unassigned"
- ❌ Category list cluttered with POS-only categories
- ❌ "Olympia Power Bowls" in wrong category
- ❌ Some items not visible in menu editor

### After Fix
- ✅ Only REGULAR_CATEGORY types are used
- ✅ Items correctly assigned to customer-facing categories
- ✅ Clean category list without POS clutter
- ✅ All items visible in correct categories
- ✅ Debug log shows "X REGULAR categories (skipped Y MENU categories)"

---

## Testing

### Test Scenario 1: Items with MENU_CATEGORY Only
1. Create item in Square POS assigned to MENU_CATEGORY
2. Run sync
3. Check menu editor
4. **Expected**: Item shows as "Unassigned" (correct behavior)

### Test Scenario 2: Items with REGULAR_CATEGORY Only
1. Create item in Square assigned to REGULAR_CATEGORY
2. Run sync
3. Check menu editor
4. **Expected**: Item shows in correct category

### Test Scenario 3: Items with Both Types
1. Create item in Square with:
   - `categories[]`: `[MENU_CAT_ID, REGULAR_CAT_ID]`
2. Run sync
3. Check menu editor
4. **Expected**: Item uses REGULAR_CAT_ID, skips MENU_CAT_ID

### Test Scenario 4: Debug Log Validation
1. Run sync
2. Check debug.log
3. **Expected**: Log shows:
   ```
   [SMDP Sync] Found 12 REGULAR categories (skipped 8 MENU categories)
   ```

---

## Files Modified

### `includes/class-sync-manager.php`

**Lines 296-314**: Filter item mapping validation
```php
// Check if this is a REGULAR_CATEGORY (for menu display) vs MENU_CATEGORY (POS only)
$category_type = $obj['category_data']['category_type'] ?? 'REGULAR_CATEGORY';

if ( $category_type === 'REGULAR_CATEGORY' ) {
    $valid_category_ids[] = $obj['id'];
    $category_names[$obj['id']] = $obj['category_data']['name'] ?? 'Unknown';
} else {
    $skipped_menu_cats++;
}
```

**Lines 395-404**: Filter category storage
```php
// Only process REGULAR_CATEGORY types (not MENU_CATEGORY which is POS-only)
$category_type = $cd['category_type'] ?? 'REGULAR_CATEGORY';
if ( $category_type !== 'REGULAR_CATEGORY' ) {
    continue; // Skip MENU_CATEGORY types
}
```

---

## Why This Matters

### User Experience
- Customers see only relevant, customer-facing categories
- No confusion from POS-only organizational structure
- Menu display matches Square's intended public-facing setup

### Data Integrity
- Items correctly mapped to display categories
- No "ghost" assignments to internal POS categories
- Accurate category counts and filtering

### Performance
- Fewer categories stored in database
- Faster category lookups
- Cleaner admin interface

---

## Related Square API Documentation

From Square Catalog API:
```
category_type: Indicates the type of category
- REGULAR_CATEGORY: A standard category
- MENU_CATEGORY: A category used for menu organization in Square Point of Sale
```

**Source**: Square Catalog API - CatalogCategory object reference

---

## Related Documentation

- [CATEGORY-MAPPING-FIX.md](CATEGORY-MAPPING-FIX.md) - Category ID validation from `categories[]` array
- [MIXED-MAPPING-FIX.md](MIXED-MAPPING-FIX.md) - Handling mixed old-style and new-style mapping
- [PRODUCTION-READY.md](PRODUCTION-READY.md) - Complete changelog

---

**Note**: All technical documentation is AI-generated for reference and development purposes. While comprehensive and technically accurate, please verify critical implementation details against the actual codebase.
