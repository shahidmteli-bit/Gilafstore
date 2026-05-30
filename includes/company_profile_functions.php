<?php
/**
 * Company Profile Functions
 * Get/save company profile, logo upload with smart optimization
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Get company profile (single row, id=1)
 * Returns array of all profile fields or defaults
 */
function get_company_profile(): array
{
    $defaults = [
        'id' => 1,
        'company_name' => '',
        'brand_name' => '',
        'tagline' => '',
        'logo_web' => '',
        'logo_print' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
        'country' => 'India',
        'phone' => '',
        'email' => '',
        'website' => '',
        'fssai_number' => '',
        'gstin' => '',
        'gstin_2' => '',
        'pan_number' => '',
        'return_address' => '',
        'return_city' => '',
        'return_state' => '',
        'return_pincode' => '',
        'return_phone' => '',
        'default_courier' => '',
        'timezone' => 'Asia/Kolkata',
        'show_gst_on_invoice' => 1,
        'show_pan_on_invoice' => 0,
        'show_phone_on_label' => 1,
        'show_email_on_invoice' => 1,
        'show_return_address' => 1,
        // Footer-specific fields
        'footer_description'   => 'A premium D2C brand by Gilaf Foods & Spices.',
        'footer_reg_address'   => '',
        'footer_reg_city'      => '',
        'footer_reg_state'     => '',
        'footer_reg_pincode'   => '',
        'footer_reg_country'   => 'India',
        'footer_support_email' => '',
        'footer_phone_display' => '',
        'footer_whatsapp'      => '',
    ];

    try {
        $row = db_fetch('SELECT * FROM company_profile WHERE id = 1');
        if ($row) {
            return array_merge($defaults, $row);
        }
    } catch (PDOException $e) {
        // Table may not exist yet
    }

    return $defaults;
}

/**
 * Save company profile (upsert id=1)
 */
function save_company_profile(array $data): bool
{
    try {
        $db = get_db_connection();

        // Auto-add missing columns if they don't exist yet
        try {
            $colCheck = $db->query("SHOW COLUMNS FROM company_profile LIKE 'fssai_number'");
            if ($colCheck->rowCount() === 0) {
                $db->exec("ALTER TABLE company_profile ADD COLUMN fssai_number VARCHAR(20) DEFAULT '' AFTER website");
            }
            $colCheck2 = $db->query("SHOW COLUMNS FROM company_profile LIKE 'gstin_2'");
            if ($colCheck2->rowCount() === 0) {
                $db->exec("ALTER TABLE company_profile ADD COLUMN gstin_2 VARCHAR(20) DEFAULT '' AFTER gstin");
            }
            // Footer-specific columns
            $footerColDefs = [
                'footer_description'   => "VARCHAR(500) NOT NULL DEFAULT ''",
                'footer_reg_address'   => "VARCHAR(500) NOT NULL DEFAULT ''",
                'footer_reg_city'      => "VARCHAR(100) NOT NULL DEFAULT ''",
                'footer_reg_state'     => "VARCHAR(100) NOT NULL DEFAULT ''",
                'footer_reg_pincode'   => "VARCHAR(10)  NOT NULL DEFAULT ''",
                'footer_reg_country'   => "VARCHAR(100) NOT NULL DEFAULT 'India'",
                'footer_support_email' => "VARCHAR(150) NOT NULL DEFAULT ''",
                'footer_phone_display' => "VARCHAR(30)  NOT NULL DEFAULT ''",
                'footer_whatsapp'      => "VARCHAR(30)  NOT NULL DEFAULT ''",
            ];
            foreach ($footerColDefs as $fc => $fdef) {
                $chk = $db->query("SHOW COLUMNS FROM company_profile LIKE '{$fc}'")->rowCount();
                if ($chk === 0) {
                    $db->exec("ALTER TABLE company_profile ADD COLUMN `{$fc}` {$fdef}");
                }
            }
        } catch (PDOException $ignore) {}

        $fields = [
            'company_name', 'brand_name', 'tagline',
            'address', 'city', 'state', 'pincode', 'country',
            'phone', 'email', 'website', 'fssai_number', 'gstin', 'gstin_2', 'pan_number',
            'return_address', 'return_city', 'return_state', 'return_pincode', 'return_phone',
            'default_courier', 'timezone',
            'show_gst_on_invoice', 'show_pan_on_invoice',
            'show_phone_on_label', 'show_email_on_invoice', 'show_return_address',
            // Footer fields
            'footer_description', 'footer_reg_address', 'footer_reg_city',
            'footer_reg_state', 'footer_reg_pincode', 'footer_reg_country',
            'footer_support_email', 'footer_phone_display', 'footer_whatsapp',
        ];

        // Check if row exists
        $exists = db_fetch('SELECT id FROM company_profile WHERE id = 1');

        if ($exists) {
            $sets = [];
            $params = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "`{$f}` = ?";
                    $params[] = $data[$f];
                }
            }
            if (empty($sets)) return true;
            $params[] = 1;
            $sql = "UPDATE company_profile SET " . implode(', ', $sets) . " WHERE id = ?";
            $db->prepare($sql)->execute($params);
        } else {
            $insertFields = ['id'];
            $insertValues = [1];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $insertFields[] = "`{$f}`";
                    $insertValues[] = $data[$f];
                }
            }
            $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));
            $sql = "INSERT INTO company_profile (" . implode(', ', $insertFields) . ") VALUES ({$placeholders})";
            $db->prepare($sql)->execute($insertValues);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Company profile save error: " . $e->getMessage());
        return false;
    }
}

/**
 * Upload and optimize company logo
 * Returns ['web' => 'path', 'print' => 'path'] or false
 */
function upload_company_logo(array $file): array|false
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) {
        return false;
    }

    $uploadDir = realpath(__DIR__ . '/../assets/images') . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $timestamp = date('YmdHis');

    // SVG: just copy as-is (vector, no optimization needed)
    if ($mime === 'image/svg+xml') {
        $webName = "company-logo-{$timestamp}.svg";
        $printName = $webName;
        move_uploaded_file($file['tmp_name'], $uploadDir . $webName);

        update_logo_paths($webName, $printName);
        return ['web' => $webName, 'print' => $printName];
    }

    // Raster image: optimize
    $srcImage = null;
    switch ($mime) {
        case 'image/png':
            $srcImage = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/jpeg':
        case 'image/jpg':
            $srcImage = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/webp':
            $srcImage = imagecreatefromwebp($file['tmp_name']);
            break;
    }

    if (!$srcImage) {
        return false;
    }

    $origW = imagesx($srcImage);
    $origH = imagesy($srcImage);

    // --- PRINT version: high-res, max 1200px wide, PNG for quality ---
    $printMaxW = 1200;
    $printW = $origW;
    $printH = $origH;
    if ($origW > $printMaxW) {
        $printW = $printMaxW;
        $printH = (int)round($origH * ($printMaxW / $origW));
    }
    $printImg = imagecreatetruecolor($printW, $printH);
    imagealphablending($printImg, false);
    imagesavealpha($printImg, true);
    $transparent = imagecolorallocatealpha($printImg, 255, 255, 255, 127);
    imagefill($printImg, 0, 0, $transparent);
    imagecopyresampled($printImg, $srcImage, 0, 0, 0, 0, $printW, $printH, $origW, $origH);

    $printName = "company-logo-print-{$timestamp}.png";
    imagepng($printImg, $uploadDir . $printName, 1); // low compression = high quality
    imagedestroy($printImg);

    // --- WEB version: optimized, max 400px wide, WebP ---
    $webMaxW = 400;
    $webW = min($origW, $webMaxW);
    $webH = (int)round($origH * ($webW / $origW));
    $webImg = imagecreatetruecolor($webW, $webH);
    imagealphablending($webImg, false);
    imagesavealpha($webImg, true);
    $transparent2 = imagecolorallocatealpha($webImg, 255, 255, 255, 127);
    imagefill($webImg, 0, 0, $transparent2);
    imagecopyresampled($webImg, $srcImage, 0, 0, 0, 0, $webW, $webH, $origW, $origH);

    if (function_exists('imagewebp')) {
        $webName = "company-logo-web-{$timestamp}.webp";
        imagewebp($webImg, $uploadDir . $webName, 88);
    } else {
        $webName = "company-logo-web-{$timestamp}.png";
        imagepng($webImg, $uploadDir . $webName, 6);
    }
    imagedestroy($webImg);
    imagedestroy($srcImage);

    update_logo_paths($webName, $printName);

    // Auto-regenerate PWA icons from the new logo
    regenerate_pwa_icons_from_logo($uploadDir . $printName);

    return ['web' => $webName, 'print' => $printName];
}

/**
 * Regenerate all PWA icon sizes from a source logo image
 */
function regenerate_pwa_icons_from_logo(string $logoPath): void
{
    if (!file_exists($logoPath)) return;

    $mime = mime_content_type($logoPath);
    $src = null;
    switch ($mime) {
        case 'image/png':  $src = @imagecreatefrompng($logoPath); break;
        case 'image/jpeg': $src = @imagecreatefromjpeg($logoPath); break;
        case 'image/webp': $src = @imagecreatefromwebp($logoPath); break;
    }
    if (!$src) return;

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $iconsDir = realpath(__DIR__ . '/../assets/icons');
    if (!$iconsDir) return;

    $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
    foreach ($sizes as $size) {
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        // Zoom logo to fill with minimal padding
        $pad = max(1, intval($size * 0.02));
        $availW = $size - ($pad * 2);
        $availH = $size - ($pad * 2);
        $scale = max($availW / $srcW, $availH / $srcH);
        $drawW = intval($srcW * $scale);
        $drawH = intval($srcH * $scale);
        $drawX = intval(($size - $drawW) / 2);
        $drawY = intval(($size - $drawH) / 2);

        imagealphablending($img, true);
        imagecopyresampled($img, $src, $drawX, $drawY, 0, 0, $drawW, $drawH, $srcW, $srcH);
        imagepng($img, $iconsDir . "/icon-{$size}x{$size}.png", 2);
        imagedestroy($img);
    }
    imagedestroy($src);
}

/**
 * Update logo paths in DB
 */
function update_logo_paths(string $webPath, string $printPath): void
{
    try {
        $db = get_db_connection();
        $db->prepare("UPDATE company_profile SET logo_web = ?, logo_print = ? WHERE id = 1")
           ->execute([$webPath, $printPath]);
    } catch (PDOException $e) {
        error_log("Logo path update error: " . $e->getMessage());
    }
}

/**
 * Get company logo URL for web display
 */
function get_company_logo_url(): string
{
    $profile = get_company_profile();
    if (!empty($profile['logo_web'])) {
        return base_url('assets/images/' . $profile['logo_web']);
    }
    return base_url('assets/icons/icon-192x192.png');
}

/**
 * Get company logo URL for print (high-res)
 */
function get_company_logo_print_url(): string
{
    $profile = get_company_profile();
    if (!empty($profile['logo_print'])) {
        return base_url('assets/images/' . $profile['logo_print']);
    }
    if (!empty($profile['logo_web'])) {
        return base_url('assets/images/' . $profile['logo_web']);
    }
    return base_url('assets/icons/icon-192x192.png');
}

/**
 * Get all social media platforms ordered by sort_order.
 * Lazily creates the table if missing (graceful degradation).
 */
function get_footer_social_platforms(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $cache = db_fetch_all(
            "SELECT * FROM footer_social_media ORDER BY sort_order ASC, id ASC"
        );
    } catch (PDOException $e) {
        $cache = [];
    }
    return $cache;
}

/**
 * Save all social media platform records from the admin form.
 * $data = array of [ id => [...fields] ]
 */
function save_footer_social_platforms(array $data): bool
{
    try {
        $db = get_db_connection();
        $stmt = $db->prepare(
            "UPDATE footer_social_media
             SET social_url = ?, is_enabled = ?, open_new_tab = ?, sort_order = ?
             WHERE id = ?"
        );
        foreach ($data as $id => $row) {
            $url        = trim($row['social_url'] ?? '');
            $enabled    = empty($url) ? 0 : (int)($row['is_enabled'] ?? 0);
            $newTab     = (int)($row['open_new_tab'] ?? 1);
            $sortOrder  = (int)($row['sort_order'] ?? 0);
            $stmt->execute([$url, $enabled, $newTab, $sortOrder, (int)$id]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("save_footer_social_platforms error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get company details for invoices/labels (replaces hardcoded version)
 * Respects visibility toggles
 */
function get_company_profile_for_documents(): array
{
    $p = get_company_profile();

    $details = [
        'company_name' => $p['company_name'],
        'brand_name' => $p['brand_name'],
        'name' => $p['brand_name'] ?: $p['company_name'],
        'tagline' => $p['tagline'],
        'logo' => get_company_logo_print_url(),
        'logo_web' => get_company_logo_url(),
        'address' => $p['address'],
        'city' => $p['city'],
        'state' => $p['state'],
        'pincode' => $p['pincode'],
        'country' => $p['country'],
        'website' => $p['website'],
        'default_courier' => $p['default_courier'],
    ];

    // Conditional fields based on visibility toggles
    $details['phone'] = $p['show_phone_on_label'] ? $p['phone'] : '';
    $details['email'] = $p['show_email_on_invoice'] ? $p['email'] : '';
    $details['fssai_number'] = $p['fssai_number'] ?? '';
    $details['gstin'] = $p['show_gst_on_invoice'] ? $p['gstin'] : '';
    $details['gstin_2'] = $p['show_gst_on_invoice'] ? ($p['gstin_2'] ?? '') : '';
    $details['pan'] = $p['show_pan_on_invoice'] ? $p['pan_number'] : '';

    // Return address
    if ($p['show_return_address']) {
        $details['return_address'] = $p['return_address'] ?: $p['address'];
        $details['return_city'] = $p['return_city'] ?: $p['city'];
        $details['return_state'] = $p['return_state'] ?: $p['state'];
        $details['return_pincode'] = $p['return_pincode'] ?: $p['pincode'];
        $details['return_phone'] = $p['return_phone'] ?: $p['phone'];
    } else {
        $details['return_address'] = '';
        $details['return_city'] = '';
        $details['return_state'] = '';
        $details['return_pincode'] = '';
        $details['return_phone'] = '';
    }

    return $details;
}
