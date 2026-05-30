# CRM Integration Debug Tool - Complete Guide

## 🔧 Overview

A comprehensive debugging system has been created to diagnose and troubleshoot WACRM integration issues. The debug tool provides real-time diagnostic information from both GilafStore and WACRM servers.

---

## 📍 Access Points

### 1. **GilafStore Admin Panel**
- **URL:** `https://yourdomain.com/admin/crm_debug.php`
- **Access:** Admin users only
- **Location:** CRM Integration → Debug Panel tab

### 2. **WACRM API Endpoint**
- **URL:** `https://wacrm-wyjo.onrender.com/api/debug`
- **Method:** GET
- **Auth:** Not required
- **Response:** JSON with detailed system information

---

## 🎯 What the Debug Tool Shows

### Local GilafStore Diagnostics

#### Server Information
- PHP Version
- Operating System
- Server Software
- Current Timestamp
- Memory Usage (Current, Peak, Limit)

#### Database Status
- Connection Status
- Database Driver
- Connection Health

#### CRM Settings
- CRM Enabled/Disabled Status
- WACRM Base URL
- API Key Existence
- Configuration Status

#### Extensions & Dependencies
- cURL Status
- OpenSSL Status
- Required PHP Extensions

---

### Remote WACRM Diagnostics

#### Environment Variables
- Node.js Environment (dev/production)
- Supabase URL Configuration
- API Keys Status
- Encryption Key Status
- Meta WhatsApp Credentials

#### Supabase Connectivity
- Connection Status
- Database Health
- Error Messages (if any)
- Contacts Count

#### Integration Keys
- Number of API Keys
- Key Names
- Active/Inactive Status
- Key Details

#### Available Routes
- `/api/health` - Simple health check
- `/api/integration/health` - Integration health check
- `/api/debug` - Debug endpoint
- `/api/whatsapp/webhook` - WhatsApp webhook
- `/api/integration/webhook` - Integration webhook

#### Performance Metrics
- Response Time (ms)
- Server Uptime (seconds)

---

## 🚀 How to Use

### Step 1: Open Debug Panel

**Option A: From GilafStore Admin**
1. Go to Admin Dashboard
2. Click "CRM Integration"
3. Click "Debug Panel" tab
4. Click "Fetch WACRM Debug Info" button

**Option B: Direct URL**
```
https://yourdomain.com/admin/crm_debug.php
```

### Step 2: Fetch Remote Debug Info

Click the **"🌐 Fetch WACRM Debug Info"** button to retrieve real-time diagnostics from WACRM server.

**What happens:**
1. Panel sends request to WACRM `/api/debug` endpoint
2. WACRM collects system information
3. Results displayed in organized cards
4. All data shown in raw JSON format at bottom

### Step 3: Review Diagnostics

Look for:
- ✅ Green badges = Working correctly
- ⚠️ Yellow badges = Warning/Not configured
- ❌ Red badges = Error/Missing

### Step 4: Copy Debug Info

Click **"📋 Copy All Debug Info"** to copy all diagnostic data to clipboard for:
- Sharing with support team
- Pasting in bug reports
- Archiving for troubleshooting history

---

## 🔍 Common Issues & Solutions

### Issue: "Connection Failed — Unknown error"

**Check in Debug Panel:**
1. **GilafStore Side:**
   - Is Database connected? ✅
   - Is cURL enabled? ✅
   - Is OpenSSL enabled? ✅

2. **WACRM Side:**
   - Is Supabase connected? ✅
   - Are environment variables set? ✅
   - Is the server running? ✅

**Solution:**
- If GilafStore shows ❌ cURL: Enable cURL in PHP
- If WACRM shows ❌ Supabase: Check environment variables in Render dashboard

---

### Issue: "No API key configured"

**Check in Debug Panel:**
1. Go to "API Keys" tab in CRM Integration
2. Click "Generate New API Key"
3. Copy the generated key
4. Add to Supabase `integration_keys` table

**Verify:**
- Debug Panel should show: "API Key: Yes" ✅

---

### Issue: "Supabase connection failed"

**Check in Debug Panel:**
1. **WACRM Debug Info** → Supabase Connection
2. Look for error message
3. Check environment variables:
   - `NEXT_PUBLIC_SUPABASE_URL` - Set? ✅
   - `SUPABASE_SERVICE_ROLE_KEY` - Set? ✅

**Solution:**
- Go to Render Dashboard
- Select WACRM service
- Check Environment Variables
- Verify Supabase credentials are correct

---

## 📊 Debug Panel Sections

### 1. Server Information Card
```
PHP Version: 8.2.0
OS: Windows NT 10.0
Server: Apache/2.4.54
Timestamp: 2026-05-30T01:25:00Z
```

### 2. Database Card
```
Connected: Yes ✅
Driver: PDO
```

### 3. CRM Settings Card
```
Enabled: Yes ✅
Base URL: https://wacrm-wyjo.onrender.com
API Key: Yes ✅
```

### 4. Extensions Card
```
cURL: Yes ✅
OpenSSL: Yes ✅
```

### 5. Memory Usage Card
```
Current: 2.50 MB
Peak: 5.75 MB
Limit: 128 MB
```

### 6. WACRM Environment Card (Remote)
```
NODE_ENV: production ✅
NEXT_PUBLIC_SUPABASE_URL: ✓ Set ✅
SUPABASE_SERVICE_ROLE_KEY: ✓ Set ✅
```

### 7. Supabase Connection Card (Remote)
```
Status: Connected ✅
Error: None
Contacts Count: 1,234
```

### 8. Integration Keys Card (Remote)
```
Status: Connected ✅
Keys Found: 2
Key 1: GilafStore (Active) ✅
Key 2: Test Key (Inactive) ⚠️
```

### 9. Available Routes Card (Remote)
```
health: /api/health
integrationHealth: /api/integration/health
debug: /api/debug
whatsappWebhook: /api/whatsapp/webhook
integrationWebhook: /api/integration/webhook
```

### 10. Performance Card (Remote)
```
Response Time: 145ms
Server Uptime: 3600s
```

### 11. Raw JSON Response
Complete JSON response for advanced debugging

---

## 🛠️ Technical Details

### GilafStore Debug Endpoint
**File:** `admin/crm_debug.php`

**Features:**
- Local server diagnostics
- Database connection check
- CRM settings verification
- PHP extension validation
- Memory usage monitoring
- AJAX request to WACRM `/api/debug`

**Security:**
- Admin-only access via `require_admin()`
- No sensitive data exposed
- Safe error handling

### WACRM Debug Endpoint
**File:** `wacrm/src/app/api/debug/route.ts`

**Features:**
- Environment variable status
- Supabase connectivity test
- Integration keys verification
- Available routes listing
- Performance metrics
- Error handling and logging

**Security:**
- No authentication required (safe info only)
- No sensitive credentials exposed
- Error messages don't leak system details
- Production-safe logging

---

## 📝 Debug Workflow

### For Support Team
1. Ask user to open Debug Panel
2. Click "Fetch WACRM Debug Info"
3. Click "Copy All Debug Info"
4. Paste in support ticket
5. Review diagnostics to identify issue

### For Developers
1. Open Debug Panel
2. Check each card for errors
3. Review Raw JSON for detailed info
4. Check WACRM logs via Render dashboard
5. Check GilafStore error logs via PHP error_log

### For Deployment Verification
1. Deploy WACRM to Render
2. Open Debug Panel
3. Click "Fetch WACRM Debug Info"
4. Verify all green badges ✅
5. Confirm connection test passes

---

## 🔐 Security Notes

- ✅ Debug panel is admin-only
- ✅ No passwords or secrets exposed
- ✅ API keys shown only as masked values
- ✅ Error messages are user-friendly
- ✅ No stack traces in production
- ✅ WACRM `/api/debug` is safe (no auth required)

---

## 📞 Support

If debug panel shows errors:

1. **Take a screenshot** of the debug panel
2. **Copy all debug info** using the button
3. **Contact support** with:
   - Screenshot of debug panel
   - Copied debug info
   - Description of the issue
   - Steps to reproduce

---

## 🎯 Quick Checklist

Before contacting support, verify:

- [ ] GilafStore Database: Connected ✅
- [ ] GilafStore cURL: Enabled ✅
- [ ] GilafStore OpenSSL: Enabled ✅
- [ ] CRM Enabled: Yes ✅
- [ ] API Key: Exists ✅
- [ ] WACRM Environment: production ✅
- [ ] WACRM Supabase: Connected ✅
- [ ] WACRM Integration Keys: Found ✅
- [ ] Connection Test: Passes ✅

If all checks pass ✅, the integration is working correctly!

---

**Last Updated:** May 30, 2026
**Version:** 1.0.0
**Status:** Production Ready ✅
