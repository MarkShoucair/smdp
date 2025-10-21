# Mixed Mapping Style Fix

**Date**: 2025-10-21
**Version**: 3.1
**Issue**: "Undefined array key 'item_id'" errors in menu editor

---

## Problem

After fixing category ID validation in the sync manager, the menu editor started throwing PHP warnings:

```
WARNING Undefined array key "item_id" in class-admin-pages.php on line 1471
WARNING Undefined array key "item_id" in class-admin-pages.php on line 1524
```

Items were disappearing from the menu editor entirely.

---

## Root Cause

The plugin supports **two mapping styles**:

### Old-Style Mapping (from sync)
```php
[
    'ITEM_ID_123' => [
        'category' => 'CAT_ID',
        'order' => 0,
        'hide_image' => 0
    ]
]
```
- Key = Square item ID
- No `instance_id` or `item_id` fields

### New-Style Mapping (from custom categories)
```php
[
    'INSTANCE_ID_456' => [
        'item_id' => 'ITEM_ID_123',
        'instance_id' => 'INSTANCE_ID_456',
        'category' => 'CUSTOM_CAT',
        'order' => 0,
        'hide_image' => 0
    ]
]
```
- Key = Instance ID (allows same item in multiple categories)
- Has both `instance_id` and `item_id` fields

### The Mismatch

The menu editor (lines 1460-1466) detects mapping style by checking if **ANY** entry has `instance_id`:

```php
$is_new_style = false;
foreach ($mapping as $key => $data) {
    if (isset($data['instance_id'])) {
        $is_new_style = true;
        break;
    }
}
```

If it finds even ONE new-style entry (from custom categories), it treats **ALL** entries as new-style. This caused errors when trying to access `$map_data['item_id']` on old-style entries from the sync.

---

## Solution

Modified the menu editor to handle **mixed mapping styles** by checking each entry individually instead of treating all entries uniformly.

### Change 1: Lines 1468-1480

**Before**:
```php
if ($is_new_style) {
    foreach ( $mapping as $instance_id => $map_data ) {
        $item_id = $map_data['item_id'];  // ❌ Crashes on old-style entries
        if (!isset($items_by_id[$item_id])) continue;
```

**After**:
```php
if ($is_new_style) {
    // Note: Can contain mixed old-style and new-style entries
    foreach ( $mapping as $instance_id => $map_data ) {
        // Check if this specific entry is new-style or old-style
        if (isset($map_data['item_id'])) {
            // New-style entry: has both instance_id (key) and item_id (field)
            $item_id = $map_data['item_id'];
        } else {
            // Old-style entry mixed in: key IS the item_id
            $item_id = $instance_id;
        }
        if (!isset($items_by_id[$item_id])) continue;
```

### Change 2: Lines 1528-1538

**Before**:
```php
foreach ( $items_by_id as $item_id => $item_obj ) {
    $found = false;
    foreach ($mapping as $map_data) {
        if ($map_data['item_id'] === $item_id) {  // ❌ Crashes on old-style entries
            $found = true;
            break;
        }
    }
```

**After**:
```php
foreach ( $items_by_id as $item_id => $item_obj ) {
    $found = false;
    foreach ($mapping as $key => $map_data) {
        // Check both new-style (item_id field) and old-style (key is item_id)
        $mapped_item_id = isset($map_data['item_id']) ? $map_data['item_id'] : $key;
        if ($mapped_item_id === $item_id) {
            $found = true;
            break;
        }
    }
```

---

## How It Works

1. **Menu editor detects if mapping contains ANY new-style entries** (line 1460-1466)
2. **If new-style entries exist, it processes ALL entries in mixed-mode** (line 1468+)
3. **For each mapping entry, it checks individually**:
   - Has `item_id` field? → It's new-style, use `$map_data['item_id']`
   - No `item_id` field? → It's old-style, use array key as item_id
4. **When searching for unmapped items**, it checks both styles (line 1531-1537)

This allows the mapping array to contain:
- Old-style entries from automatic sync
- New-style entries from custom categories
- Both coexisting peacefully

---

## Testing

### Test Scenario 1: Items from Sync Only
1. Fresh install with no custom categories
2. Run Square sync
3. Check menu editor - all items should appear in their Square categories
4. **Expected**: No errors, all items visible

### Test Scenario 2: Items in Custom Categories
1. Create a custom category
2. Add items to it
3. Check menu editor
4. **Expected**: Items appear in both original category AND custom category (as instances)

### Test Scenario 3: Mixed Mapping
1. Have items from sync (old-style)
2. Create custom category and add items (new-style)
3. Check menu editor
4. **Expected**: All items visible, no "undefined array key" errors

### Test Scenario 4: Category Validation
1. Run sync with items that have:
   - Valid `categories[]` array
   - Valid `reporting_category`
   - Invalid category IDs
   - No categories at all
2. Check menu editor
3. **Expected**: Items assigned to first valid category from `categories[]` array, falling back to `reporting_category`, then "unassigned"

---

## Files Modified

### `includes/class-admin-pages.php`

**Lines 1468-1480**: Added mixed mapping detection
```php
// Check if this specific entry is new-style or old-style
if (isset($map_data['item_id'])) {
    // New-style entry
    $item_id = $map_data['item_id'];
} else {
    // Old-style entry mixed in
    $item_id = $instance_id;
}
```

**Lines 1528-1538**: Added mixed mapping search
```php
// Check both new-style (item_id field) and old-style (key is item_id)
$mapped_item_id = isset($map_data['item_id']) ? $map_data['item_id'] : $key;
if ($mapped_item_id === $item_id) {
    $found = true;
    break;
}
```

---

## Why This Architecture?

### Why Not Convert All to New-Style?

We could modify the sync to create new-style mapping, but that would:
1. Require migrating existing mappings
2. Use more storage (duplicate `item_id` in key and field)
3. Create instance IDs for items that don't need them

### Why Not Keep All Old-Style?

Custom categories **require** new-style mapping because:
- Same item can appear in multiple custom categories
- Each instance needs separate `order` and `hide_image` settings
- Old-style can't support multiple instances (one item_id = one mapping entry)

### Mixed-Style is Optimal

- Sync creates old-style for standard items (efficient)
- Custom categories create new-style for multi-instance items (flexible)
- Menu editor handles both gracefully

---

## Impact

✅ **Fixed**: "Undefined array key 'item_id'" errors
✅ **Fixed**: Items disappearing from menu editor
✅ **Maintained**: Backward compatibility with old-style mapping
✅ **Maintained**: Custom category functionality with new-style mapping
✅ **No database migration required**

---

## Related Documentation

- [CATEGORY-MAPPING-FIX.md](CATEGORY-MAPPING-FIX.md) - Category ID validation
- [FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md](FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md) - New-style vs old-style mapping details
- [PRODUCTION-READY.md](PRODUCTION-READY.md) - Complete changelog

---

**Note**: All technical documentation is AI-generated for reference and development purposes. While comprehensive and technically accurate, please verify critical implementation details against the actual codebase.
