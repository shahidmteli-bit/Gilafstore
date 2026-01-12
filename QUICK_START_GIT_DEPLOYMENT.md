# ⚡ QUICK START - Git Deployment in 10 Minutes

## 🎯 Goal
Set up automated deployment: Local → GitHub → Hostinger (Live Site)

---

## ✅ PRE-FLIGHT CHECK

Your current setup:
- ✅ Git initialized: `C:\xampp\htdocs\Gilaf Ecommerce website\.git`
- ✅ GitHub connected: `https://github.com/shahidmteli-bit/gilaf-website.git`
- ✅ `.gitignore` created (protects sensitive files)
- ⏳ Hostinger: Needs Git deployment setup

---

## 🚀 10-MINUTE SETUP

### Step 1: Push Your Code to GitHub (2 minutes)

Open PowerShell in Windsurf and run:

```powershell
cd "C:\xampp\htdocs\Gilaf Ecommerce website"

# Stage all files
git add .

# Commit
git commit -m "Production ready - initial deployment setup"

# Push to GitHub
git push origin main
```

**Verify:** Visit `https://github.com/shahidmteli-bit/gilaf-website` - you should see all your files!

---

### Step 2: Setup Hostinger Git Deployment (5 minutes)

**A. Login to Hostinger**
1. Go to: `https://hpanel.hostinger.com`
2. Select your website: `gilafstore.com`

**B. Find Git Section**
- Look for **"Git"** in the sidebar
- Or: **Advanced** → **Git Version Control**
- Or: Search for **"Git"**

**C. Create Deployment**

Click **"Create"** or **"Deploy from Git"** and fill in:

```
Repository URL: https://github.com/shahidmteli-bit/gilaf-website.git
Branch: main
Deploy to: /public_html/
Auto-deploy: ✅ ON (Enable)
```

**D. Authorize GitHub**
- Click **"Authorize Hostinger"**
- Login to GitHub if prompted
- Grant access

**E. First Deployment**
- Click **"Deploy Now"** or **"Pull Changes"**
- ⚠️ This will replace files in `/public_html/`
- Wait 1-2 minutes for completion

---

### Step 3: Recreate Sensitive Files (2 minutes)

Git doesn't include `db_connect.php` (passwords). You need to recreate it:

**A. Get Your Database Credentials**
- Hostinger Panel → **Databases** → **MySQL**
- Note down:
  - Database Host
  - Database Username
  - Database Password
  - Database Name

**B. Create db_connect.php**
- Hostinger Panel → **File Manager**
- Navigate to: `/public_html/includes/`
- Create new file: `db_connect.php`
- Add this code (with YOUR credentials):

```php
<?php
define('DB_HOST', 'localhost');           // Your DB host
define('DB_USER', 'your_username');       // Your DB username
define('DB_PASS', 'your_password');       // Your DB password
define('DB_NAME', 'your_database_name');  // Your DB name

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
```

---

### Step 4: Set Folder Permissions (1 minute)

**Via Hostinger File Manager:**
1. Right-click on `uploads/` → Permissions → `777`
2. Right-click on `cache/` → Permissions → `777`
3. Right-click on `logs/` → Permissions → `777`

**Or via SSH (if enabled):**
```bash
cd /public_html
chmod -R 777 uploads/ cache/ logs/
```

---

### Step 5: Test Everything! (2 minutes)

**A. Test Live Site**
- Visit: `https://gilafstore.com`
- Check homepage loads
- Check products display
- Test admin login

**B. Test Auto-Deployment**

In Windsurf, make a small change:

```powershell
# Edit index.php - add a comment at the top
# Then:
git add index.php
git commit -m "Test auto-deployment"
git push origin main

# Wait 30-60 seconds
# Visit https://gilafstore.com
# View page source - your comment should appear!
```

---

## 🎉 SUCCESS!

If the test worked, you're done! From now on:

```
1. Edit files in Windsurf
2. git add . && git commit -m "message"
3. git push origin main
4. Wait 30 seconds
5. Changes are LIVE!
```

---

## 📋 DAILY WORKFLOW

```powershell
# Check what changed
git status

# Add all changes
git add .

# Commit with message
git commit -m "Added new feature"

# Push (auto-deploys to live site!)
git push origin main
```

---

## ⚠️ IMPORTANT REMINDERS

### ✅ DO Track in Git:
- All `.php` files (except `db_connect.php`)
- All `.css`, `.js`, `.html` files
- `.htaccess` files
- Database schema files

### ❌ DON'T Track in Git:
- `db_connect.php` (already in `.gitignore`)
- User uploads (`uploads/` folder)
- Cache files
- Log files

### 🔒 Sensitive Files (Create Manually on Server):
- `/public_html/includes/db_connect.php`
- Any API keys or secrets

---

## 🆘 TROUBLESHOOTING

**Problem: "Changes not appearing on live site"**
```powershell
# Check if push was successful
git push origin main

# Check Hostinger deployment logs
# Hostinger Panel → Git → View Logs
```

**Problem: "Database connection error"**
- Check: `db_connect.php` exists in `/public_html/includes/`
- Check: Database credentials are correct
- Check: Hostinger database is running

**Problem: "Permission denied errors"**
- Set folder permissions to 777:
  - `uploads/`
  - `cache/`
  - `logs/`

---

## 📚 FULL DOCUMENTATION

For detailed explanations:
- **Complete Guide:** `HOSTINGER_GIT_DEPLOYMENT_GUIDE.md`
- **Visual Roadmap:** `GIT_DEPLOYMENT_ROADMAP.md`

---

## 🎯 NEXT STEPS

After setup is complete:
1. ✅ Backup your Hostinger database regularly
2. ✅ Document your database credentials securely
3. ✅ Test deployment with small changes first
4. ✅ Learn Git branching for advanced workflows

---

**Total Setup Time:** ~10 minutes  
**Daily Deployment Time:** ~30 seconds  
**Manual FTP Uploads:** Never again! 🎉

---

Last Updated: January 11, 2026
