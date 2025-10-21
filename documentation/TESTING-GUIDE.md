# Testing Guide - Intelligent Content Change Detection

**Plugin**: Square Menu Display Premium Deluxe Pro v3.1
**Feature**: Intelligent content change detection with hybrid refresh system
**Date**: 2025-10-21

---

## Prerequisites

1. ✅ Plugin installed and activated
2. ✅ Square access token configured
3. ✅ At least one menu shortcode on a page
4. ✅ Browser developer console open (F12)

---

## Test Suite

### Test 1: Verify Lightweight Sold-Out Updates ⚡

**Goal**: Confirm that sold-out status changes trigger badge-only updates (no flicker)

**Steps**:
1. Open frontend page with menu
2. Open browser console (F12)
3. Note initial items and sold-out badges
4. In Square Dashboard:
   - Mark an item as "Sold Out" (using inventory or location overrides)
5. In WordPress Admin:
   - Go to Settings > Sync
   - Click "Sync Now" (or wait for cron)
6. On frontend:
   - Switch to another category and back
   - OR wait for auto-refresh (30 seconds default)

**Expected Result**:
```
Console logs:
🔄 Refreshing sold-out status for: menu_123
✅ Sold-out status received: menu_123
✅ Content unchanged - updating badges only
✅ Updated 1 sold-out badges
```

**Visual Result**:
- ✅ Sold-out badge appears instantly
- ✅ No page flicker or reload effect
- ✅ Item stays in same position

**If this fails**: Check that `get_sold_out_status` AJAX endpoint returns `content_hash`

---

### Test 2: Verify Full Refresh on Price Change 🔄

**Goal**: Confirm that price changes trigger full HTML refresh

**Steps**:
1. Open frontend page with menu
2. Open browser console (F12)
3. Note current price of an item
4. In Square Dashboard:
   - Change the price of that item
5. In WordPress Admin:
   - Go to Settings > Sync
   - Click "Sync Now"
6. On frontend:
   - Switch to another category and back

**Expected Result**:
```
Console logs:
🔄 Refreshing sold-out status for: menu_123
✅ Sold-out status received: menu_123
🔄 Menu content changed (hash mismatch) - triggering full refresh
   Old hash: a1b2c3d4e5f6...
   New hash: f6e5d4c3b2a1...
🔄 Full refresh triggered for: menu_123
✅ Menu refreshed successfully: menu_123
```

**Visual Result**:
- ✅ New price displayed
- ✅ Brief reload effect (acceptable for content changes)
- ✅ All item data updated

**If this fails**: Check that variations are included in content hash

---

### Test 3: Verify Full Refresh on New Item 🆕

**Goal**: Confirm that adding items triggers full refresh (count mismatch)

**Steps**:
1. Open frontend page with menu
2. Open browser console (F12)
3. Count current items in a category
4. In Square Dashboard:
   - Add a new item to that category
5. In WordPress Admin:
   - Go to Settings > Sync
   - Click "Sync Now"
6. On frontend:
   - Switch to that category

**Expected Result**:
```
Console logs:
🔄 Refreshing sold-out status for: menu_123
✅ Sold-out status received: menu_123
🔄 Item count changed (5 → 6) - triggering full refresh
🔄 Full refresh triggered for: menu_123
✅ Menu refreshed successfully: menu_123
```

**Visual Result**:
- ✅ New item appears in menu
- ✅ Item count increased
- ✅ All formatting intact

**If this fails**: Check that `item_count` is returned in AJAX response

---

### Test 4: Verify Description Change Detection 📝

**Goal**: Confirm that description updates trigger full refresh

**Steps**:
1. Open frontend page with menu
2. Open browser console (F12)
3. Note description of an item
4. In Square Dashboard:
   - Update the description
5. In WordPress Admin:
   - Go to Settings > Sync
   - Click "Sync Now"
6. On frontend:
   - Switch categories to trigger refresh

**Expected Result**:
```
Console logs:
🔄 Menu content changed (hash mismatch) - triggering full refresh
   Old hash: abc123...
   New hash: def456...
```

**Visual Result**:
- ✅ New description displayed
- ✅ Full refresh occurred

---

### Test 5: Verify Item Reordering 🔢

**Goal**: Confirm that changing item order triggers full refresh

**Steps**:
1. Open frontend page with menu
2. Open browser console (F12)
3. In WordPress Admin:
   - Go to Menu Editor
   - Reorder items in a category (drag and drop)
   - Click "Save Order"
4. On frontend:
   - Switch to that category

**Expected Result**:
```
Console logs:
🔄 Menu content changed (hash mismatch) - triggering full refresh
```

**Visual Result**:
- ✅ Items display in new order
- ✅ Full refresh occurred

---

### Test 6: Verify Offline Graceful Degradation 📴

**Goal**: Confirm menu works when AJAX fails (offline mode)

**Steps**:
1. Open frontend page with menu
2. Open browser DevTools > Network tab
3. Set throttling to "Offline"
4. Switch categories

**Expected Result**:
```
Console logs:
⚠️ Could not update sold-out status (offline?): menu_123
```

**Visual Result**:
- ✅ Menu still displays (cached data)
- ✅ Category switching works
- ✅ No error messages to user
- ✅ Sold-out badges show last known state

---

### Test 7: Verify No Double-Refresh Effect ✨

**Goal**: Confirm the original issue is fixed (no flicker on category switch)

**Steps**:
1. Open frontend page with menu
2. Click through multiple categories rapidly
3. Watch for any visual flicker/reload effect

**Expected Result**:
- ✅ Smooth category transitions
- ✅ No visible reload/flicker
- ✅ Only badge updates occur (lightweight)

**If you see flicker**:
- Check if `refreshSingleMenu()` is being called (should rarely happen)
- Verify content hash is stable when only sold-out changes

---

## Performance Verification

### Check Refresh Ratio

Open console and run:
```javascript
// Monitor refresh types
var refreshCount = { lightweight: 0, full: 0 };

// Patch the functions to count calls
var originalRefresh = refreshSoldOutStatus;
refreshSoldOutStatus = function() {
    refreshCount.lightweight++;
    return originalRefresh.apply(this, arguments);
};

var originalFull = refreshSingleMenu;
refreshSingleMenu = function() {
    refreshCount.full++;
    return originalFull.apply(this, arguments);
};

// After 10 category switches, check ratio
console.log('Lightweight refreshes:', refreshCount.lightweight);
console.log('Full refreshes:', refreshCount.full);
console.log('Ratio:', (refreshCount.lightweight / refreshCount.full).toFixed(2));
```

**Expected**: 70-80% lightweight, 20-30% full (during normal usage with occasional content updates)

---

## Network Traffic Comparison

### Before Implementation
**Per category switch**: ~50KB HTML

### After Implementation
**Sold-out only**: ~200 bytes JSON (250x reduction)
**Content change**: ~50KB HTML (same as before)

**To measure**:
1. Open DevTools > Network tab
2. Filter by "smdp"
3. Switch categories
4. Check request size for `smdp_get_sold_out_status`

---

## Debug Log Reference

| Log Message | Meaning | Action |
|-------------|---------|--------|
| `✅ Content unchanged - updating badges only` | Hash matched, lightweight refresh | ✅ Expected |
| `🔄 Menu content changed (hash mismatch)` | Content updated, full refresh | ✅ Expected when items change |
| `🔄 Item count changed (X → Y)` | Items added/removed | ✅ Expected for new items |
| `⚠️ Could not update sold-out status (offline?)` | AJAX failed | ✅ Expected when offline |
| `❌ Sold-out status update failed` | Server error | ❌ Investigate server logs |

---

## Common Issues

### Issue: Always triggers full refresh
**Symptoms**: Every category switch shows "hash mismatch"
**Cause**: Hash not being cached properly
**Solution**: Clear browser cache, check `contentHashes` object in console:
```javascript
console.log(contentHashes);
```

### Issue: Never triggers full refresh
**Symptoms**: Price changes don't show up
**Cause**: Hash not including price data
**Solution**: Verify `variations` are in content hash (class-ajax-handler.php:535)

### Issue: Random hash mismatches
**Symptoms**: Full refresh when nothing changed
**Cause**: Order instability in hash generation
**Solution**: Check that item ordering is consistent in backend query

---

## Rollback Procedure

If issues occur, revert to simple refresh:

1. Edit `assets/js/refresh.js`
2. Change line ~500:
```javascript
// From:
window.smdpRefreshMenu = refreshSoldOutStatus; // Lightweight

// To:
window.smdpRefreshMenu = refreshSingleMenu; // Always full refresh
```

This disables intelligent detection and reverts to full refresh (stable fallback).

---

## Success Criteria

✅ Test 1 passes: Sold-out changes = badge-only update
✅ Test 2 passes: Price changes = full refresh
✅ Test 3 passes: New items = full refresh
✅ Test 4 passes: Description changes = full refresh
✅ Test 5 passes: Order changes = full refresh
✅ Test 6 passes: Offline = graceful degradation
✅ Test 7 passes: No double-refresh effect
✅ Performance: 70%+ lightweight refreshes
✅ Network: ~200 bytes for sold-out updates

---

## Reporting Issues

If any test fails, provide:

1. **Console logs**: Copy full debug output
2. **Network tab**: Screenshot of AJAX requests
3. **Test scenario**: Which test failed
4. **Expected vs Actual**: What you expected vs what happened
5. **Browser**: Chrome/Firefox/Safari version

---

## Next Steps After Testing

Once all tests pass:

1. ✅ Monitor production logs for 24-48 hours
2. ✅ Check performance metrics (refresh ratio)
3. ✅ Verify no PHP errors in WordPress debug.log
4. ✅ Confirm user experience improvements (no flicker)
5. ✅ Document any edge cases discovered

---

## Contact

For support or questions about this feature:
- See [CONTENT-CHANGE-DETECTION.md](CONTENT-CHANGE-DETECTION.md) for technical details
- See [PRODUCTION-READY.md](PRODUCTION-READY.md) for deployment checklist
- Review debug logs in browser console with prefix `[SMDP Refresh]`
