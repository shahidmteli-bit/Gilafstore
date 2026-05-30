# CRM Debug Tool - Quick Start Guide

## 🚀 5-Minute Setup

### Step 1: Upload Files
Upload these files to your server:
- `admin/crm_debug.php` (NEW)
- `wacrm/src/app/api/debug/route.ts` (NEW)
- `admin/crm_integration.php` (UPDATED)

### Step 2: Deploy WACRM
Push changes to GitHub and trigger Render redeploy:
```bash
git add .
git commit -m "Add debug endpoints"
git push
```

### Step 3: Access Debug Panel
```
https://yourdomain.com/admin/crm_debug.php
```

### Step 4: Fetch Debug Info
Click **"🌐 Fetch WACRM Debug Info"** button

### Step 5: Review Results
Look for ✅ green badges = everything working!

---

## 🎯 What to Look For

### ✅ All Green = Integration Working
```
✅ Database: Connected
✅ cURL: Enabled
✅ OpenSSL: Enabled
✅ CRM Enabled: Yes
✅ API Key: Exists
✅ WACRM Environment: production
✅ Supabase: Connected
✅ Integration Keys: Found
✅ Routes: Available
```

### ⚠️ Yellow = Warning (Not Critical)
```
⚠️ API Key: No → Generate one in API Keys tab
⚠️ CRM Enabled: No → Toggle to enable
```

### ❌ Red = Error (Fix Required)
```
❌ Database: Error → Check database connection
❌ cURL: Disabled → Enable cURL in PHP
❌ Supabase: Error → Check environment variables
```

---

## 🔧 Common Fixes

### "Database: Error"
1. Check `includes/db_connect.php`
2. Verify database credentials
3. Restart MySQL/MariaDB

### "cURL: Disabled"
1. Enable cURL in `php.ini`
2. Uncomment: `extension=curl`
3. Restart web server

### "Supabase: Error"
1. Go to Render Dashboard
2. Select WACRM service
3. Check Environment Variables
4. Verify Supabase credentials

### "No API Key"
1. Go to CRM Integration
2. Click "API Keys" tab
3. Click "Generate New API Key"
4. Copy the key
5. Add to Supabase `integration_keys` table

---

## 📊 Debug Panel Layout

```
┌─────────────────────────────────────────────────────┐
│  🔧 CRM Integration Debug Panel                     │
│  Comprehensive diagnostic information               │
├─────────────────────────────────────────────────────┤
│  [🌐 Fetch WACRM Debug Info] [📋 Copy All] [🔄 Refresh]
├─────────────────────────────────────────────────────┤
│                                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  │ 💻 Server    │  │ 🗄️ Database  │  │ ⚙️ CRM       │
│  │ PHP 8.2.0    │  │ Connected ✅ │  │ Enabled ✅   │
│  │ Windows 10   │  │ PDO Driver   │  │ URL: ...     │
│  │ Apache 2.4   │  │              │  │ API Key: ✅  │
│  └──────────────┘  └──────────────┘  └──────────────┘
│
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  │ 📦 Extensions│  │ 💾 Memory    │  │ 🌍 WACRM Env │
│  │ cURL: ✅     │  │ Current: 2MB │  │ Prod: ✅     │
│  │ OpenSSL: ✅  │  │ Peak: 5MB    │  │ Keys: ✅     │
│  │              │  │ Limit: 128MB │  │              │
│  └──────────────┘  └──────────────┘  └──────────────┘
│
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  │ 🔗 Supabase  │  │ 🔑 API Keys  │  │ ⚡ Performance
│  │ Connected ✅ │  │ Found: 2 ✅  │  │ Response: 145ms
│  │ Contacts: 1K │  │ Active: 1    │  │ Uptime: 3600s
│  │              │  │ Inactive: 1  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘
│
│  ┌──────────────────────────────────────────────────┐
│  │ 🛣️ Available Routes                             │
│  │ /api/health                                      │
│  │ /api/integration/health                          │
│  │ /api/debug                                       │
│  │ /api/whatsapp/webhook                            │
│  │ /api/integration/webhook                         │
│  └──────────────────────────────────────────────────┘
│
│  ┌──────────────────────────────────────────────────┐
│  │ 📄 Raw JSON Response                             │
│  │ {                                                │
│  │   "success": true,                               │
│  │   "status": "debug",                             │
│  │   "service": "wacrm",                            │
│  │   "debug": { ... }                               │
│  │ }                                                │
│  └──────────────────────────────────────────────────┘
│
└─────────────────────────────────────────────────────┘
```

---

## 🎯 Troubleshooting Flow

```
Start
  ↓
Open Debug Panel
  ↓
Click "Fetch WACRM Debug Info"
  ↓
All Green ✅?
  ├─ YES → Integration Working! ✅
  │
  └─ NO → Check which is Red/Yellow
      ↓
      Database Error?
      ├─ YES → Fix database connection
      │
      cURL/OpenSSL Disabled?
      ├─ YES → Enable in PHP
      │
      Supabase Error?
      ├─ YES → Check environment variables
      │
      No API Key?
      ├─ YES → Generate and add to Supabase
      │
      Routes Missing?
      └─ YES → Check WACRM deployment
```

---

## 📋 Copy & Paste Commands

### Test WACRM Health Endpoint
```bash
curl https://wacrm-wyjo.onrender.com/api/health
```

### Test WACRM Debug Endpoint
```bash
curl https://wacrm-wyjo.onrender.com/api/debug
```

### Test Integration Health
```bash
curl https://wacrm-wyjo.onrender.com/api/integration/health
```

---

## 🔐 Security Notes

✅ **Safe to Use:**
- Admin-only access (GilafStore)
- No passwords exposed
- No API secrets shown
- Production-ready
- Error messages are safe

---

## 📞 When to Share Debug Info

Share debug info when:
1. ❌ Connection test fails
2. ❌ Settings won't save
3. ❌ Webhooks not working
4. ❌ Customers not syncing
5. ❌ Any integration error

**How to Share:**
1. Open Debug Panel
2. Click "Copy All Debug Info"
3. Paste in support ticket
4. Include description of issue

---

## ✨ Key Features

| Feature | Benefit |
|---------|---------|
| **Real-time Diagnostics** | See all info instantly |
| **Color-Coded Status** | Easy to spot problems |
| **One-Click Copy** | Share debug info easily |
| **No Manual Logging** | Automatic data collection |
| **Mobile-Responsive** | Works on all devices |
| **Admin-Only Access** | Secure by default |
| **Production-Ready** | Safe to use live |
| **Beautiful UI** | Easy to understand |

---

## 🚀 Next Steps

1. ✅ Upload files
2. ✅ Deploy WACRM
3. ✅ Open Debug Panel
4. ✅ Fetch WACRM info
5. ✅ Verify all ✅
6. ✅ Test connection
7. ✅ Confirm integration

---

## 📞 Support

**Issue:** Debug panel shows errors
**Solution:** 
1. Take screenshot
2. Copy all debug info
3. Contact support with:
   - Screenshot
   - Debug info
   - Description of issue

---

**Version:** 1.0.0
**Status:** ✅ Production Ready
**Last Updated:** May 30, 2026
