# Sold-Out Status Initial Load Fix

**Date**: 2025-10-21
**Version**: 3.1
**Issue**: Sold-out badges not showing on first page load until user clicks on category

---

## Problem

When users loaded the menu app, sold-out status only appeared on the currently visible category. Other categories would not show sold-out badges until the user clicked on them, even though the server had the updated sold-out information.

### User Experience Impact

1. Customer loads menu app
2. First category shows sold-out badges correctly
3. Customer clicks on "Bowls" category
4. **Problem**: No sold-out badges visible (even though items are sold-out on server)
5. Customer might try to order a sold-out item
6. After a few seconds, badges appear (from background refresh)

This created confusion and potential ordering errors.

---

## Root Cause

The refresh logic (lines 527-536) was only updating sold-out status for **visible containers**:

```javascript
// OLD CODE - Only refreshed visible section
var $visibleSection = $menuApp.find('.smdp-app-section').filter(function() {
  return $(this).css('display') !== 'none';
});
if ($visibleSection.length) {
  var $container = $visibleSection.find('.smdp-menu-container');
  if ($container.length) {
    refreshSoldOutStatus($container, true); // Only 1 category
  }
}
```

**Why this happened**:
- Menu app has multiple sections (one per category)
- Only one section is visible at page load
- Hidden sections don't get sold-out status updated
- When user clicks category, the section becomes visible but still has stale badges
- Background polling eventually updates it, but there's a delay

---

## Solution

Updated the initial load logic to refresh **ALL containers** (both visible and hidden), with staggered requests to prevent server overload.

### Implementation

**File**: `assets/js/refresh.js`
**Lines**: 520-543

**Before**:
```javascript
// Menu app - refresh only the visible section
var $visibleSection = $menuApp.find('.smdp-app-section').filter(function() {
  return $(this).css('display') !== 'none';
});
if ($visibleSection.length) {
  var $container = $visibleSection.find('.smdp-menu-container');
  if ($container.length) {
    refreshSoldOutStatus($container, true);
  }
}
```

**After**:
```javascript
// Menu app - refresh ALL sections (not just visible) so sold-out status is ready when user switches tabs
var $allContainers = $menuApp.find('.smdp-menu-container');
log('Found ' + $allContainers.length + ' menu containers to refresh');
$allContainers.each(function(index) {
  var $container = $(this);
  // Stagger requests by 100ms to avoid server overload
  setTimeout(function() {
    refreshSoldOutStatus($container, true); // Silent on initial load
  }, index * 100);
});
```

---

## How It Works

### Initial Page Load Flow

1. **Page loads at T+0ms**
2. **T+2000ms**: Initial sold-out refresh starts
3. **Finds all menu containers** (e.g., 5 categories = 5 containers)
4. **Staggers AJAX requests**:
   - Container 1: Refreshes at T+2000ms
   - Container 2: Refreshes at T+2100ms
   - Container 3: Refreshes at T+2200ms
   - Container 4: Refreshes at T+2300ms
   - Container 5: Refreshes at T+2400ms
5. **All sold-out status updated** within ~2.5 seconds of page load
6. **User can click any category** and see correct badges immediately

### Staggered Requests

The 100ms stagger prevents:
- ❌ Server overload from simultaneous requests
- ❌ Browser connection limit issues
- ❌ Database query congestion

With 10 categories, requests span 1 second (2000ms to 3000ms), which is acceptable on page load.

---

## Performance Impact

### Before Fix
- **1 AJAX request** on page load (visible category only)
- **N-1 additional requests** when user clicks categories (lazy loading)
- **Total**: N requests spread over user session

### After Fix
- **N AJAX requests** on page load (all categories)
- **0 additional requests** when user clicks categories (already loaded)
- **Total**: N requests (same total, but all upfront)

### Network Impact
For a typical menu with 5 categories:
- **Requests**: 5 × 100ms stagger = 500ms total duration
- **Payload**: ~500 bytes per request × 5 = ~2.5KB total
- **Total time**: 2.5 seconds from page load to all badges ready

This is **negligible** on modern connections and provides much better UX.

---

## Benefits

### User Experience
- ✅ Sold-out badges appear immediately on ALL categories
- ✅ No confusion when switching tabs
- ✅ Prevents customers from attempting to order sold-out items
- ✅ No visible "loading" or "flashing" of badges

### Technical
- ✅ Leverages existing lightweight sold-out refresh (no full container reload)
- ✅ Staggered requests prevent server overload
- ✅ Silent refresh (no loading indicators)
- ✅ Content change detection still works (auto-triggers full refresh if needed)

### Operational
- ✅ Tablets show accurate sold-out status immediately after page load
- ✅ No need to manually refresh or click categories
- ✅ Server sold-out updates reflected instantly across all tablets

---

## Testing

### Test Scenario 1: Fresh Page Load
1. Mark items as sold-out in Square POS
2. Open menu app on tablet (fresh page load)
3. Wait 3 seconds
4. **Expected**: First visible category shows sold-out badges
5. Click on another category
6. **Expected**: Sold-out badges already visible (no delay)

### Test Scenario 2: Multiple Categories
1. Have 5+ categories with sold-out items in each
2. Load menu app
3. Open browser DevTools → Network tab
4. **Expected**: See 5 staggered AJAX requests to `smdp_get_sold_out_status`
5. **Expected**: Requests 100ms apart
6. Click through all categories
7. **Expected**: All badges correct, no additional AJAX calls

### Test Scenario 3: Server Update
1. Load menu app on multiple tablets
2. Mark item as sold-out in Square POS
3. Wait for sync to complete
4. Refresh one tablet
5. **Expected**: Sold-out badge appears on ALL categories within 3 seconds
6. Switch between categories
7. **Expected**: Badges remain correct

### Test Scenario 4: Offline Mode
1. Load menu app online
2. Go offline (airplane mode)
3. Refresh page (loads from cache)
4. **Expected**: AJAX requests fail gracefully
5. **Expected**: Last cached sold-out status still displays
6. **Expected**: No errors in console

---

## Debug Logging

With debug mode enabled, you'll see:
```
[SMDP Refresh] 📋 Performing initial sold-out status check for ALL containers...
[SMDP Refresh] Found 5 menu containers to refresh
[SMDP Refresh] 🔄 Refreshing sold-out status for: menu-123-cat-drinks
[SMDP Refresh] 🔄 Refreshing sold-out status for: menu-123-cat-bowls
[SMDP Refresh] 🔄 Refreshing sold-out status for: menu-123-cat-sides
[SMDP Refresh] 🔄 Refreshing sold-out status for: menu-123-cat-desserts
[SMDP Refresh] 🔄 Refreshing sold-out status for: menu-123-cat-specials
[SMDP Refresh] ✅ Updated sold-out badges: 2 items in menu-123-cat-drinks
[SMDP Refresh] ✅ Updated sold-out badges: 1 item in menu-123-cat-bowls
...
```

---

## Alternative Approaches Considered

### Approach 1: Refresh on Category Click (OLD)
- ❌ Delay when switching categories
- ❌ User sees badges "pop in"
- ❌ Poor UX

### Approach 2: Refresh Only Visible + Background Polling
- ❌ Still has delay on first click
- ❌ Wastes resources with continuous polling
- ❌ Doesn't solve initial load problem

### Approach 3: Refresh All on Load (CHOSEN)
- ✅ Instant badges on all categories
- ✅ One-time cost on page load
- ✅ Excellent UX
- ✅ No ongoing polling needed

### Approach 4: Include in HTML Server-Side
- ❌ Would require caching entire HTML for all categories
- ❌ Much larger cache size
- ❌ Loses benefit of lightweight sold-out-only refresh
- ❌ Content change detection wouldn't work

---

## Related Documentation

- [CONTENT-CHANGE-DETECTION.md](CONTENT-CHANGE-DETECTION.md) - How content changes trigger full refresh
- [FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md](FIXES-SOLD-OUT-CUSTOM-CATEGORIES.md) - Sold-out badge styling and logic

---

**Note**: All technical documentation is AI-generated for reference and development purposes.
