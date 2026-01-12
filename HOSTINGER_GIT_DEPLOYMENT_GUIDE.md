# 🚀 HOSTINGER GIT DEPLOYMENT GUIDE
## Complete Setup for Gilaf Ecommerce Website

---

## 📋 TABLE OF CONTENTS
1. [Current Situation](#current-situation)
2. [Understanding the Workflow](#understanding-the-workflow)
3. [Step-by-Step Setup](#step-by-step-setup)
4. [Daily Development Workflow](#daily-development-workflow)
5. [Folder Structure Explained](#folder-structure-explained)
6. [Troubleshooting](#troubleshooting)

---

## 🎯 CURRENT SITUATION

### What You Have Now:
- **Local Development:** `C:\xampp\htdocs\Gilaf Ecommerce website\`
- **GitHub Repository:** `https://github.com/shahidmteli-bit/gilaf-website.git`
- **Hostinger Server:** Files manually uploaded to `/public_html/`
- **Windsurf IDE:** Editing files in local folder

### The Problem:
- You manually uploaded files to Hostinger
- Now you want to use Git for future updates
- Need to sync existing files with Git without breaking anything

---

## 🔄 UNDERSTANDING THE WORKFLOW

### The Complete Flow:

```
┌─────────────────────────────────────────────────────────────┐
│                    YOUR DEVELOPMENT FLOW                     │
└─────────────────────────────────────────────────────────────┘

1️⃣ LOCAL DEVELOPMENT (Your Computer)
   📁 C:\xampp\htdocs\Gilaf Ecommerce website\
   │
   │ You edit files using Windsurf IDE
   │ Test on localhost (http://localhost/Gilaf Ecommerce website/)
   │
   ↓

2️⃣ GIT COMMIT (Save Changes)
   💾 git add .
   💾 git commit -m "Added new feature"
   │
   ↓

3️⃣ PUSH TO GITHUB (Cloud Storage)
   ☁️ git push origin main
   │
   │ Files go to: github.com/shahidmteli-bit/gilaf-website
   │
   ↓

4️⃣ HOSTINGER PULLS FROM GITHUB (Auto-Deploy)
   🌐 Hostinger server pulls latest code
   📁 Updates: /public_html/
   │
   ↓

5️⃣ LIVE WEBSITE UPDATED
   ✅ https://gilafstore.com (LIVE!)
```

---

## 🛠️ STEP-BY-STEP SETUP

### PHASE 1: Prepare Your Local Repository (Already Done! ✅)

Your Git is already initialized. Let's verify:

```bash
# Check Git status
cd "C:\xampp\htdocs\Gilaf Ecommerce website"
git status
git remote -v
```

**Expected Output:**
```
origin  https://github.com/shahidmteli-bit/gilaf-website.git (fetch)
origin  https://github.com/shahidmteli-bit/gilaf-website.git (push)
```

---

### PHASE 2: Clean Up Your Local Files

**Step 1: Add .gitignore (Already Created! ✅)**

The `.gitignore` file tells Git which files NOT to upload:
- ❌ Database passwords (`db_connect.php`)
- ❌ User uploads (images, PDFs)
- ❌ Cache files
- ❌ Log files
- ❌ Debug/test files

**Step 2: Commit Current State**

```bash
# Add all files (respecting .gitignore)
git add .

# Commit with a message
git commit -m "Initial commit - Current production state"

# Push to GitHub
git push origin main
```

---

### PHASE 3: Setup Hostinger Git Deployment

#### Option A: Using Hostinger's Built-in Git Deployment (RECOMMENDED)

**Step 1: Login to Hostinger Control Panel**
1. Go to: https://hpanel.hostinger.com
2. Select your website: `gilafstore.com`

**Step 2: Navigate to Git Section**
1. Click on **"Advanced"** → **"Git"**
2. Or search for **"Git Version Control"**

**Step 3: Create New Git Deployment**
1. Click **"Create New Repository"** or **"Deploy from Git"**
2. Choose **"Deploy from GitHub"**

**Step 4: Connect GitHub Repository**
1. **Repository URL:** `https://github.com/shahidmteli-bit/gilaf-website.git`
2. **Branch:** `main`
3. **Deploy Path:** `/public_html/`
4. **Important:** Check "Delete existing files" (since you manually uploaded before)

**Step 5: Authenticate GitHub**
1. Hostinger will ask you to authenticate with GitHub
2. Click **"Authorize Hostinger"**
3. Grant access to your repository

**Step 6: Configure Deployment**
```
Repository: shahidmteli-bit/gilaf-website
Branch: main
Target Directory: /public_html/
Auto-deploy: ✅ Enabled (Deploy on every push)
```

**Step 7: First Deployment**
1. Click **"Deploy Now"** or **"Pull Changes"**
2. Hostinger will:
   - Clear `/public_html/` (backup first if needed!)
   - Clone your GitHub repository
   - Set up auto-deployment

---

#### Option B: Manual SSH Git Setup (Advanced)

If Hostinger's Git feature isn't available, use SSH:

**Step 1: Enable SSH Access**
1. Hostinger Panel → **Advanced** → **SSH Access**
2. Enable SSH
3. Note your SSH credentials

**Step 2: Connect via SSH**
```bash
ssh u123456789@your-server-ip
```

**Step 3: Setup Git on Server**
```bash
# Navigate to public_html
cd /public_html

# Backup existing files (IMPORTANT!)
cd ..
mv public_html public_html_backup_$(date +%Y%m%d)

# Create fresh directory
mkdir public_html
cd public_html

# Clone your repository
git clone https://github.com/shahidmteli-bit/gilaf-website.git .

# Set up auto-pull (optional - create a webhook)
```

---

### PHASE 4: Create Important Server Files

**Step 1: Create .gitkeep files for empty folders**

These ensure Git tracks empty folders:

```bash
# In your local folder
cd "C:\xampp\htdocs\Gilaf Ecommerce website"

# Create .gitkeep files
echo. > uploads\.gitkeep
echo. > cache\.gitkeep
echo. > logs\.gitkeep
echo. > assets\Images\products\.gitkeep
```

**Step 2: Ensure .htaccess files are tracked**

```bash
# Check if .htaccess exists
dir .htaccess
dir uploads\.htaccess
dir cache\.htaccess
```

**Step 3: Commit these changes**

```bash
git add .
git commit -m "Added .gitkeep files for folder structure"
git push origin main
```

---

### PHASE 5: Configure Database Connection for Production

**Important:** Your database credentials are different on Hostinger!

**Step 1: Create a template file (Local)**

Create: `includes/db_connect.example.php`

```php
<?php
// Database Configuration Template
// Copy this to db_connect.php and update with your credentials

define('DB_HOST', 'localhost');        // Usually 'localhost'
define('DB_USER', 'your_db_username'); // Your database username
define('DB_PASS', 'your_db_password'); // Your database password
define('DB_NAME', 'your_db_name');     // Your database name

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>
```

**Step 2: On Hostinger Server (via File Manager or SSH)**

1. Navigate to: `/public_html/includes/`
2. Create `db_connect.php` with YOUR actual Hostinger database credentials
3. **Never commit this file to Git!** (Already in .gitignore)

---

## 🔄 DAILY DEVELOPMENT WORKFLOW

### Making Changes and Deploying:

```bash
# 1. Make changes in Windsurf IDE
# Edit files in: C:\xampp\htdocs\Gilaf Ecommerce website\

# 2. Test locally
# Open: http://localhost/Gilaf Ecommerce website/

# 3. Check what changed
git status

# 4. Stage your changes
git add .
# Or add specific files:
# git add admin/new_feature.php
# git add assets/css/new-style.css

# 5. Commit with descriptive message
git commit -m "Added product filter feature"

# 6. Push to GitHub
git push origin main

# 7. Hostinger auto-deploys (if configured)
# Your changes are now LIVE at https://gilafstore.com
```

### Example: Adding a New Feature

```bash
# Scenario: You added a new discount system

# 1. Check changes
git status

# Output:
# modified:   admin/manage_discounts.php
# new file:   admin/add_discount.php
# modified:   includes/discount_functions.php

# 2. Add all changes
git add .

# 3. Commit
git commit -m "Added discount management system for admin panel"

# 4. Push
git push origin main

# 5. Wait 30-60 seconds for auto-deploy
# 6. Visit https://gilafstore.com/admin/manage_discounts.php
```

---

## 📁 FOLDER STRUCTURE EXPLAINED

### Local Development:
```
C:\xampp\htdocs\Gilaf Ecommerce website\
├── .git/                    # Git tracking (hidden)
├── .gitignore              # Files to ignore
├── admin/                  # Admin panel files
├── api/                    # API endpoints
├── assets/                 # CSS, JS, Images
├── includes/               # PHP includes
│   └── db_connect.php     # ❌ NOT in Git (sensitive)
├── uploads/                # ❌ NOT in Git (user files)
├── cache/                  # ❌ NOT in Git (temporary)
├── logs/                   # ❌ NOT in Git (log files)
├── index.php               # ✅ Tracked in Git
└── README.md               # ✅ Tracked in Git
```

### GitHub Repository:
```
github.com/shahidmteli-bit/gilaf-website
├── admin/                  # ✅ All PHP files
├── api/                    # ✅ All API files
├── assets/                 # ✅ CSS, JS (no user uploads)
├── includes/               # ✅ PHP files (except db_connect.php)
│   └── db_connect.example.php  # ✅ Template only
├── uploads/.gitkeep        # ✅ Empty folder marker
├── cache/.gitkeep          # ✅ Empty folder marker
├── index.php               # ✅ Main file
└── .gitignore              # ✅ Ignore rules
```

### Hostinger Server:
```
/public_html/
├── admin/                  # ✅ Pulled from GitHub
├── api/                    # ✅ Pulled from GitHub
├── assets/                 # ✅ Pulled from GitHub
├── includes/
│   ├── db_connect.php     # ⚠️ Created manually (with real credentials)
│   └── other files        # ✅ Pulled from GitHub
├── uploads/                # ⚠️ User uploaded files (NOT from Git)
├── cache/                  # ⚠️ Generated by PHP
├── logs/                   # ⚠️ Generated by server
└── index.php               # ✅ Pulled from GitHub
```

---

## 🎯 IMPORTANT RULES

### ✅ DO Track in Git:
- All `.php` files (except sensitive configs)
- All `.css`, `.js` files
- All `.html` files
- Database schema files (`.sql`)
- `.htaccess` files
- Documentation (`.md` files)
- Static images (logos, icons)

### ❌ DON'T Track in Git:
- `db_connect.php` (database passwords)
- User uploaded files (`uploads/`)
- Cache files (`cache/`)
- Log files (`logs/`)
- Debug/test files
- Backup files (`.bak`, `.old`)
- IDE settings (`.vscode/`, `.idea/`)

---

## 🔧 TROUBLESHOOTING

### Problem 1: "Files not updating on live site"

**Solution:**
```bash
# Check if push was successful
git push origin main

# On Hostinger, manually pull (if auto-deploy failed)
# SSH into server:
ssh u123456789@server-ip
cd /public_html
git pull origin main
```

---

### Problem 2: "Database connection error on live site"

**Cause:** `db_connect.php` is not on server (it's in .gitignore)

**Solution:**
1. Login to Hostinger File Manager
2. Navigate to `/public_html/includes/`
3. Create `db_connect.php` with Hostinger database credentials
4. Get credentials from: Hostinger Panel → Databases → MySQL

---

### Problem 3: "Uploads folder is empty on server"

**Cause:** User uploads are not tracked in Git (by design)

**Solution:**
- User uploads should be uploaded directly to server
- Or use FTP to upload `/uploads/` folder separately
- Git is only for CODE, not user data

---

### Problem 4: "Permission denied errors"

**Solution:**
```bash
# On Hostinger server (via SSH)
cd /public_html
chmod -R 755 .
chmod -R 777 uploads/
chmod -R 777 cache/
chmod -R 777 logs/
```

---

### Problem 5: "Want to rollback to previous version"

**Solution:**
```bash
# See commit history
git log --oneline

# Rollback to specific commit
git reset --hard abc1234

# Force push to GitHub
git push origin main --force

# Hostinger will auto-deploy the old version
```

---

## 🚨 FIRST-TIME TRANSITION CHECKLIST

Since you already uploaded files manually, here's how to transition safely:

### ✅ Pre-Deployment Checklist:

- [ ] **Backup current Hostinger files**
  - Download entire `/public_html/` folder via FTP
  - Or use Hostinger's backup feature

- [ ] **Verify .gitignore is correct**
  - Check: `cat .gitignore`
  - Ensure sensitive files are excluded

- [ ] **Commit all local changes**
  ```bash
  git add .
  git commit -m "Pre-deployment commit - all current files"
  git push origin main
  ```

- [ ] **Save database credentials**
  - Copy your Hostinger database credentials
  - You'll need to recreate `db_connect.php` after deployment

- [ ] **Test locally first**
  - Ensure everything works on localhost
  - Check all features

### 🚀 Deployment Steps:

1. **Enable Git on Hostinger**
   - Hostinger Panel → Git → Deploy from GitHub

2. **Configure deployment**
   - Repository: `shahidmteli-bit/gilaf-website`
   - Branch: `main`
   - Path: `/public_html/`
   - ✅ Enable auto-deploy

3. **First deployment**
   - Click "Deploy Now"
   - Wait 2-3 minutes

4. **Recreate sensitive files**
   - Create `/public_html/includes/db_connect.php`
   - Add your Hostinger database credentials

5. **Set folder permissions**
   ```bash
   chmod 777 uploads/
   chmod 777 cache/
   chmod 777 logs/
   ```

6. **Test live site**
   - Visit: https://gilafstore.com
   - Test all features
   - Check admin panel

---

## 📞 QUICK REFERENCE COMMANDS

### Daily Git Commands:
```bash
# Check status
git status

# Add all changes
git add .

# Commit
git commit -m "Your message here"

# Push to GitHub (triggers auto-deploy)
git push origin main

# Pull latest changes
git pull origin main

# View history
git log --oneline

# Discard local changes
git checkout -- filename.php
```

### Hostinger Commands (via SSH):
```bash
# Connect
ssh u123456789@server-ip

# Navigate to site
cd /public_html

# Pull latest code
git pull origin main

# Check Git status
git status

# View current branch
git branch

# Set permissions
chmod -R 755 .
chmod -R 777 uploads/ cache/ logs/
```

---

## 🎓 LEARNING RESOURCES

### Understanding Git:
- **What is Git?** Version control system (tracks code changes)
- **What is GitHub?** Cloud storage for Git repositories
- **What is a commit?** A saved snapshot of your code
- **What is a push?** Uploading commits to GitHub
- **What is a pull?** Downloading latest code from GitHub

### Git Workflow Analogy:
```
Git = Save button for your code
GitHub = Google Drive for code
Hostinger = Your live website (downloads from GitHub)
```

---

## ✅ SUCCESS INDICATORS

You'll know it's working when:

1. ✅ You edit a file in Windsurf
2. ✅ You commit and push to GitHub
3. ✅ Within 1-2 minutes, changes appear on https://gilafstore.com
4. ✅ No manual FTP uploads needed
5. ✅ You can rollback changes easily

---

## 🎯 NEXT STEPS

1. **Complete the first-time setup** (follow Phase 3)
2. **Test with a small change** (edit a comment in index.php)
3. **Verify auto-deployment works**
4. **Document your Hostinger credentials** (keep them safe!)
5. **Create a backup schedule**

---

## 📧 SUPPORT

If you encounter issues:
1. Check this guide's Troubleshooting section
2. Check Hostinger's Git documentation
3. Check GitHub repository settings
4. Contact Hostinger support (they can help with Git setup)

---

**Last Updated:** January 11, 2026  
**Version:** 1.0  
**Author:** Windsurf AI Assistant
