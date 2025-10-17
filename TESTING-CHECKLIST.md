# Security Fixes Testing Checklist

Use this checklist to verify all security fixes are working correctly.

---

## ✅ PRE-DEPLOYMENT TESTS

### 1. Webhook Security Test

- [ ] **Test Invalid Signature**
  - Send webhook with wrong signature
  - Expected: HTTP 403 response
  - Check logs for "SECURITY: Webhook signature verification FAILED!"

- [ ] **Test Missing Signature Key**
  - Clear webhook signature key from database
  - Send webhook request
  - Expected: HTTP 500 response with "Webhook not configured"

- [ ] **Test Valid Webhook**
  - Trigger real Square webhook (update an item)
  - Expected: HTTP 200, sync completes successfully
  - Check logs for "Webhook signature verified successfully"

**Pass Criteria:** Invalid webhooks rejected, valid webhooks accepted

---

### 2. Rate Limiting Test

- [ ] **Test Help Request Rate Limit**
  - Click "Request Help" button rapidly 10 times
  - Expected: First 5 succeed, remaining 5 show error
  - Wait 60 seconds
  - Try again - should work

- [ ] **Test Bill Request Rate Limit**
  - Same test as above for "Request Bill" button
  - Expected: Same behavior (5 per minute limit)

- [ ] **Test Get Bill Rate Limit**
  - Click "View Bill" button rapidly 15 times
  - Expected: First 10 succeed, remaining 5 blocked
  - Check logs for rate limit messages

- [ ] **Test Different User Agent**
  - Change browser User-Agent
  - Verify rate limit is separate (should reset counter)

**Pass Criteria:** Rate limits properly enforced across all endpoints

---

### 3. Input Validation Test

- [ ] **Test Invalid Access Token**
  - Go to Settings page
  - Enter token with special characters: `test@#$%token`
  - Click Save Settings
  - Expected: Error message "Invalid access token format"

- [ ] **Test Short Access Token**
  - Enter token less than 50 chars
  - Expected: Validation error shown

- [ ] **Test Invalid Location ID**
  - Go to Help & Bill settings
  - Enter location ID with invalid format: `LOC-123`
  - Expected: Error "Invalid Location ID format"

- [ ] **Test Invalid Table Number**
  - Try table number with special chars: `Table@1`
  - Expected: Request rejected with "Invalid table number"

- [ ] **Test SQL Injection Attempt** (Should fail safely)
  - Try table number: `1' OR '1'='1`
  - Expected: Rejected as invalid format

**Pass Criteria:** All invalid inputs rejected with clear error messages

---

### 4. Debug Logging Test

- [ ] **Test Production Logging** (WP_DEBUG = false)
  - Disable WP_DEBUG in wp-config.php
  - Perform OAuth connection
  - Check debug.log
  - Expected: Only critical errors logged, no token previews

- [ ] **Test Debug Mode Logging** (WP_DEBUG = true)
  - Enable WP_DEBUG
  - Perform OAuth connection
  - Check debug.log
  - Expected: More detailed logs appear

**Pass Criteria:** Sensitive data not logged in production mode

---

### 5. Admin Error Notifications Test

- [ ] **Test No Token Error**
  - Clear access token
  - Click "Sync Now"
  - Expected: Red admin notice appears on dashboard
  - Message: "No Square access token configured"
  - Buttons: "Check Settings" and "View API Log"

- [ ] **Test Invalid Token Error**
  - Enter invalid/expired token
  - Click "Sync Now"
  - Expected: Admin notice with API error details

- [ ] **Test Successful Sync**
  - Enter valid token
  - Click "Sync Now"
  - Expected: Success message, no error notices

**Pass Criteria:** Clear error messages displayed when sync fails

---

## 🔍 MANUAL CODE REVIEW

### 6. Code Quality Checks

- [ ] All files have `if ( ! defined( 'ABSPATH' ) ) exit;`
- [ ] All AJAX handlers have `check_ajax_referer()`
- [ ] All admin pages check `current_user_can('manage_options')`
- [ ] All output uses `esc_html()`, `esc_attr()`, or `esc_url()`
- [ ] No raw `$_SERVER` variables without sanitization
- [ ] No `eval()` or `exec()` calls
- [ ] No `wp_remote_get()` without timeout parameter

**Pass Criteria:** All checks pass

---

## 🌐 FUNCTIONAL TESTING

### 7. Core Functionality Test

- [ ] **Menu Display**
  - Frontend menu displays correctly
  - Images load properly
  - Sold-out items show banner
  - Categories filter correctly

- [ ] **Admin Settings**
  - Token can be saved
  - Sync interval can be changed
  - Manual sync works
  - Debug mode toggles properly

- [ ] **Webhooks**
  - Activate webhook button works
  - Refresh webhook button works
  - Subscription appears in list

- [ ] **Help & Bill Features**
  - Help request creates order in Square
  - Bill request creates order in Square
  - View Bill displays correct order

**Pass Criteria:** All features work as expected

---

## 🔐 SECURITY PENETRATION TESTING

### 8. Attack Simulation (Safe to test)

- [ ] **CSRF Attack Test**
  - Create forged POST request without nonce
  - Expected: Request rejected with "nonce verification failed"

- [ ] **XSS Attack Test**
  - Enter `<script>alert('XSS')</script>` in category name
  - Save and view category page
  - Expected: Script tags escaped, not executed

- [ ] **Path Traversal Test**
  - Try accessing: `/wp-content/plugins/smdp/../../../wp-config.php`
  - Expected: 404 or access denied

- [ ] **Brute Force Test**
  - Attempt 20+ rapid AJAX requests
  - Expected: Rate limiting kicks in, blocks requests

**Pass Criteria:** All attack attempts properly blocked

---

## 📊 PERFORMANCE TESTING

### 9. Load Testing

- [ ] **Sync Performance**
  - Time a full catalog sync
  - Check for timeouts (should complete < 30 seconds)
  - Verify pagination works with large catalogs

- [ ] **Rate Limit Performance**
  - Verify transients don't accumulate excessively
  - Check database for transient buildup after 1 hour

**Pass Criteria:** No performance degradation

---

## 🚀 DEPLOYMENT READINESS

### 10. Pre-Deploy Checklist

- [ ] All tests above pass
- [ ] Database backup created
- [ ] Staging environment tested
- [ ] WP_DEBUG disabled for production
- [ ] SSL certificate valid
- [ ] Square webhook URL uses HTTPS
- [ ] Access token has correct permissions
- [ ] Documentation updated

**Pass Criteria:** All items checked

---

## 📝 TEST RESULTS

**Date Tested:** _______________

**Tested By:** _______________

**Environment:**
- WordPress Version: _______________
- PHP Version: _______________
- Plugin Version: 3.0 (Security Hardened)

### Test Results Summary

| Test Category | Status | Notes |
|---------------|--------|-------|
| 1. Webhook Security | ⬜ Pass ⬜ Fail | |
| 2. Rate Limiting | ⬜ Pass ⬜ Fail | |
| 3. Input Validation | ⬜ Pass ⬜ Fail | |
| 4. Debug Logging | ⬜ Pass ⬜ Fail | |
| 5. Admin Notices | ⬜ Pass ⬜ Fail | |
| 6. Code Quality | ⬜ Pass ⬜ Fail | |
| 7. Core Functions | ⬜ Pass ⬜ Fail | |
| 8. Security Tests | ⬜ Pass ⬜ Fail | |
| 9. Performance | ⬜ Pass ⬜ Fail | |
| 10. Deploy Ready | ⬜ Pass ⬜ Fail | |

**Overall Result:** ⬜ READY FOR PRODUCTION ⬜ NEEDS FIXES

---

## 🐛 ISSUES FOUND

Document any issues discovered during testing:

1. **Issue:** _______________________________________________
   **Severity:** ⬜ Critical ⬜ High ⬜ Medium ⬜ Low
   **Status:** ⬜ Fixed ⬜ In Progress ⬜ Pending

2. **Issue:** _______________________________________________
   **Severity:** ⬜ Critical ⬜ High ⬜ Medium ⬜ Low
   **Status:** ⬜ Fixed ⬜ In Progress ⬜ Pending

3. **Issue:** _______________________________________________
   **Severity:** ⬜ Critical ⬜ High ⬜ Medium ⬜ Low
   **Status:** ⬜ Fixed ⬜ In Progress ⬜ Pending

---

## ✅ SIGN-OFF

**Developer:** _______________ **Date:** _______________

**QA Tester:** _______________ **Date:** _______________

**Approval:** _______________ **Date:** _______________

---

**END OF TESTING CHECKLIST**
