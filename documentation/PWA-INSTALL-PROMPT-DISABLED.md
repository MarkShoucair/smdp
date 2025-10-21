# PWA Install Prompt Disabled

**Date**: 2025-10-21
**Version**: 3.1
**Issue**: Install prompt modal was too intrusive for users

---

## Change

Disabled the automatic PWA install banner/modal that appeared after setting a table number.

### Reason

The install prompt, while well-designed, was considered too intrusive by users. It would pop up automatically and could interrupt the browsing experience.

---

## What Was Disabled

1. **Install Banner** - The floating purple banner with "Install Now" button
2. **Automatic Diagnostics** - Console logging that ran automatically on page load

---

## What Still Works

Users can still install the PWA through:
1. **Browser's native install button** (appears in address bar on Chrome/Edge)
2. **Browser menu** → "Install app" or "Add to home screen"
3. **Manual trigger** (if needed): `window.smdpPWAInstall.show()`

The PWA functionality itself is completely intact:
- ✅ Service worker still registers
- ✅ Manifest still loads
- ✅ Offline capabilities still work
- ✅ App can be installed via browser UI
- ✅ Standalone mode detection still works

---

## Implementation

### File Modified: `assets/js/pwa-install.js`

**Change 1: Disabled showInstallBanner() (Lines 92-94)**
```javascript
function showInstallBanner() {
  // Install prompt disabled - users can still install via browser menu
  return;

  // ... rest of function (unreachable)
}
```

**Change 2: Disabled auto-diagnostics (Lines 344-353)**
```javascript
// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    init();
    // Diagnostics can be run manually via window.smdpPWAInstall.diagnose()
  });
} else {
  init();
  // Diagnostics can be run manually via window.smdpPWAInstall.diagnose()
}
```

---

## Re-enabling (If Needed)

To re-enable the install prompt in the future:

1. **Remove the early return** in `showInstallBanner()`:
   ```javascript
   function showInstallBanner() {
     // Remove this line:
     return;

     // Rest of function will execute
   ```

2. **Re-enable auto-diagnostics** (optional):
   ```javascript
   init();
   setTimeout(diagnose, 1000);
   ```

---

## Testing

### Verify Install Prompt is Disabled
1. Open menu app in browser
2. Set a table number
3. **Expected**: NO install banner appears
4. **Expected**: Console is clean (no diagnostic output)

### Verify PWA Still Works
1. Open menu app in Chrome/Edge
2. Look for install icon in address bar
3. Click "Install" from browser UI
4. **Expected**: App installs successfully
5. **Expected**: Standalone mode works correctly

### Verify Manual Trigger Still Works
1. Open browser console
2. Run: `window.smdpPWAInstall.show()`
3. **Expected**: Install banner appears (if prompt available)
4. Run: `window.smdpPWAInstall.diagnose()`
5. **Expected**: Diagnostic info logs to console

---

## Public API (Still Available)

```javascript
// Check if install prompt is available
window.smdpPWAInstall.isAvailable()

// Manually show install banner (if you need it)
window.smdpPWAInstall.show()

// Hide install banner
window.smdpPWAInstall.hide()

// Check if running as installed PWA
window.smdpPWAInstall.isPWA()

// Run diagnostics
window.smdpPWAInstall.diagnose()
```

---

## Alternative: Less Intrusive Approach

If you want to re-enable install prompts but make them less annoying, consider:

1. **Show only once per session** (not on every table set)
2. **Smaller, dismissible toast** instead of banner
3. **Bottom-right corner** instead of top-center
4. **Delay by 30+ seconds** after page load
5. **Only show after user interaction** (scroll, click category, etc.)

Example modification:
```javascript
function showInstallBanner() {
  // Only show once per session
  if (sessionStorage.getItem('smdp_pwa_prompt_shown')) {
    return;
  }
  sessionStorage.setItem('smdp_pwa_prompt_shown', 'true');

  // Delay 30 seconds
  setTimeout(function() {
    // ... show banner code
  }, 30000);
}
```

---

## Related Documentation

- [PWA-SCOPE-FIX.md](PWA-SCOPE-FIX.md) - PWA scope restriction to menu app pages only

---

**Note**: All technical documentation is AI-generated for reference and development purposes.
