# Gilaf Sales Portal - Android APK Builder

## Overview
This builds an Android APK that wraps the Gilaf Sales Portal website into a native Android app using Capacitor.

The app loads your live sales portal website inside a native WebView, giving your sales team:
- **Home screen icon** — opens like a real app
- **GPS/Location access** — for attendance check-in
- **Camera access** — for future receipt uploads
- **Push notifications** — can be added later
- **Full screen** — no browser address bar

---

## Prerequisites

1. **Node.js** (v16+) — Download from https://nodejs.org/
2. **Android Studio** — Download from https://developer.android.com/studio
3. **Java JDK 17** — Usually bundled with Android Studio
4. **Your sales portal must be hosted on a live server** (with a domain/IP accessible from phones)

---

## Step-by-Step: Build the APK

### Step 1: Update Server URL

Open `capacitor.config.json` and replace `YOUR_DOMAIN.com` with your actual server:

```json
"server": {
    "url": "https://yourdomain.com/sales-portal/",
```

If you're testing locally on the same WiFi, use your computer's IP:
```json
"url": "http://192.168.1.100/Gilaf%20Ecommerce%20website/sales-portal/"
```

### Step 2: Install Dependencies

Open a terminal in this `apk-builder` folder:

```bash
npm install
```

### Step 3: Initialize Capacitor

```bash
npx cap init "Gilaf Sales" "com.gilafstore.sales" --web-dir www
```

If it asks to overwrite, say yes.

### Step 4: Build the web directory

```bash
node build.js
```

### Step 5: Add Android Platform

```bash
npx cap add android
```

### Step 6: Copy web files to Android

```bash
npx cap copy android
```

### Step 7: Open in Android Studio

```bash
npx cap open android
```

This opens Android Studio. Wait for Gradle sync to complete.

### Step 8: Build APK

**Option A: From Android Studio**
- Go to `Build` → `Build Bundle(s) / APK(s)` → `Build APK(s)`
- APK will be at: `android/app/build/outputs/apk/debug/app-debug.apk`

**Option B: From command line**
```bash
cd android
gradlew assembleDebug
```

### Step 9: Install on Phone

Transfer `app-debug.apk` to your sales persons' phones via:
- WhatsApp
- Email
- Google Drive
- USB cable

They need to enable "Install from unknown sources" in their phone settings.

---

## App Icon

Before building, replace the default icons in:
```
android/app/src/main/res/mipmap-*/
```

You can generate icons at: https://icon.kitchen/ or https://romannurik.github.io/AndroidAssetStudio/

---

## For Production (Signed APK / Play Store)

To create a signed release APK:

1. Generate a keystore:
```bash
keytool -genkey -v -keystore gilaf-sales.keystore -alias gilaf -keyalg RSA -keysize 2048 -validity 10000
```

2. In Android Studio: `Build` → `Generate Signed Bundle / APK`
3. Select your keystore file and build

---

## Updating the App

Since the app loads from your live server, **you don't need to rebuild the APK** when you update the website. Changes are instant!

Only rebuild the APK if you need to:
- Change the app name or icon
- Update Android permissions
- Change the server URL

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| White screen | Check server URL in capacitor.config.json |
| GPS not working | Ensure location permission is granted |
| Can't install APK | Enable "Unknown sources" in phone settings |
| Slow loading | Check internet connection, server must be accessible |
