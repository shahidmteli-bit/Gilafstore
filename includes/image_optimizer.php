<?php
/**
 * Smart Image Optimizer — Centralized image processing for all admin uploads
 * 
 * Features:
 *  - Auto WebP conversion (JPG/PNG/JPEG → WebP)
 *  - Auto compression with quality stepping
 *  - Auto resize to preset dimensions
 *  - Size limit enforcement
 *  - Optimization report (original vs final)
 * 
 * Usage:
 *   require_once __DIR__ . '/image_optimizer.php';
 *   $result = SmartImageOptimizer::optimize($_FILES['image'], 'product');
 *   // $result = ['success'=>true, 'filename'=>'xxx.webp', 'report'=>[...]]
 */

class SmartImageOptimizer
{
    // ── Presets ────────────────────────────────────────────────────────────
    // Each preset defines: max dimensions, max file size, quality steps,
    // and the upload directory relative to project root.
    private static array $presets = [
        'product' => [
            'maxWidth'    => 1200,
            'maxHeight'   => 1200,
            'maxFileSize' => 307200,   // 300 KB — high quality for desktop
            'qualities'   => [92, 85, 78],
            'uploadDir'   => 'assets/images/products/',
            'prefix'      => 'product_',
        ],
        'variant' => [
            'maxWidth'    => 1200,
            'maxHeight'   => 1200,
            'maxFileSize' => 307200,   // 300 KB — high quality for desktop
            'qualities'   => [92, 85, 78],
            'uploadDir'   => 'assets/images/products/',
            'prefix'      => 'variant_',
        ],
        'hero' => [
            'maxWidth'    => 1920,
            'maxHeight'   => 600,
            'maxFileSize' => 204800,   // 200 KB — wider banner needs higher budget
            'qualities'   => [82, 72, 58],
            'uploadDir'   => 'assets/uploads/hero_banner/',
            'prefix'      => 'hero_',
            'minWidth'    => 1200,
            'minHeight'   => 400,
        ],
        'hero_mobile' => [
            'maxWidth'    => 780,      // 2× display width (390px viewport)
            'maxHeight'   => 840,      // 2× display height (420px min-height) — ratio 13:14
            'maxFileSize' => 153600,   // 150 KB — mobile banner
            'qualities'   => [82, 72, 58],
            'uploadDir'   => 'assets/uploads/hero_banner/',
            'prefix'      => 'hero_m_',
            'minWidth'    => 390,      // 1× minimum display width
            'minHeight'   => 420,      // 1× minimum display height
        ],
        'advertisement' => [
            'maxWidth'    => 800,
            'maxHeight'   => 600,
            'maxFileSize' => 122880,   // 120 KB
            'qualities'   => [80, 65, 50],
            'uploadDir'   => 'assets/uploads/advertisements/',
            'prefix'      => 'img_',
        ],
        'category' => [
            'maxWidth'    => 600,
            'maxHeight'   => 600,
            'maxFileSize' => 102400,   // 100 KB — small thumbnails
            'qualities'   => [85, 72, 58],
            'uploadDir'   => 'assets/uploads/categories/',
            'prefix'      => 'cat_',
        ],
    ];

    // Maximum upload size before processing (5 MB hard limit)
    private const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;

    // Allowed MIME types
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Main entry point — optimize an uploaded file.
     *
     * @param  array  $file    $_FILES['field_name']
     * @param  string $preset  One of: product, variant, hero, advertisement
     * @return array  ['success'=>bool, 'filename'=>string|null, 'report'=>array, 'error'=>string|null]
     */
    public static function optimize(array $file, string $preset = 'product'): array
    {
        $report = [
            'preset'        => $preset,
            'originalName'  => $file['name'] ?? '',
            'originalSize'  => $file['size'] ?? 0,
            'originalSizeKB'=> round(($file['size'] ?? 0) / 1024, 1),
            'finalSize'     => 0,
            'finalSizeKB'   => 0,
            'savedKB'       => 0,
            'savedPercent'  => 0,
            'dimensions'    => '',
            'format'        => 'webp',
            'wasResized'    => false,
            'wasConverted'  => false,
            'qualityUsed'   => 0,
        ];

        // ── Validate preset ──
        if (!isset(self::$presets[$preset])) {
            return self::fail('Invalid optimization preset: ' . $preset, $report);
        }
        $cfg = self::$presets[$preset];

        // ── Validate upload ──
        if (empty($file['name'])) {
            return self::fail('No file provided', $report);
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return self::fail('Upload error code: ' . $file['error'], $report);
        }

        // ── Validate size (2 MB hard limit) ──
        if ($file['size'] > self::MAX_UPLOAD_SIZE) {
            $sizeMB = round($file['size'] / (1024 * 1024), 1);
            return self::fail("File too large ({$sizeMB} MB). Maximum allowed is 2 MB.", $report);
        }

        // ── Validate MIME type ──
        $mime = $file['type'] ?? '';
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            return self::fail('Unsupported format (' . $mime . '). Allowed: JPG, PNG, WEBP.', $report);
        }

        // ── Ensure upload directory exists ──
        $projectRoot = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR;
        $uploadDir = $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $cfg['uploadDir']);
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return self::fail('Cannot create upload directory', $report);
            }
        }

        // ── Load source image via GD ──
        $srcImage = self::loadImage($file['tmp_name'], $mime);
        if (!$srcImage) {
            // GD failed — fallback: save original file as-is
            $ext = self::ALLOWED_MIMES[$mime];
            $filename = $cfg['prefix'] . uniqid('', true) . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                return self::fail('Unable to save uploaded file (GD unavailable)', $report);
            }
            chmod($dest, 0644);
            $report['finalSize']    = filesize($dest);
            $report['finalSizeKB']  = round($report['finalSize'] / 1024, 1);
            $report['format']       = $ext;
            $report['qualityUsed']  = 'original';
            return ['success' => true, 'filename' => $filename, 'report' => $report, 'error' => null];
        }

        // ── Validate minimum dimensions (if preset requires it) ──
        $origW = imagesx($srcImage);
        $origH = imagesy($srcImage);
        if (!empty($cfg['minWidth']) && $origW < $cfg['minWidth']) {
            imagedestroy($srcImage);
            return self::fail("Image too narrow ({$origW}px). Minimum width: {$cfg['minWidth']}px.", $report);
        }
        if (!empty($cfg['minHeight']) && $origH < $cfg['minHeight']) {
            imagedestroy($srcImage);
            return self::fail("Image too short ({$origH}px). Minimum height: {$cfg['minHeight']}px.", $report);
        }

        // ── Auto-resize ──
        $maxW  = $cfg['maxWidth'];
        $maxH  = $cfg['maxHeight'];

        if ($origW > $maxW || $origH > $maxH) {
            $ratio = min($maxW / $origW, $maxH / $origH);
            $newW  = intval($origW * $ratio);
            $newH  = intval($origH * $ratio);
            $report['wasResized'] = true;
        } else {
            $newW = $origW;
            $newH = $origH;
        }

        // ── Create resized canvas ──
        $resized = imagecreatetruecolor($newW, $newH);
        // Preserve transparency for PNG sources
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
        imagealphablending($resized, true);
        imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($srcImage);

        // ── Save as WebP with progressive quality reduction ──
        $filename = $cfg['prefix'] . uniqid('', true) . '.webp';
        $dest = $uploadDir . $filename;
        $report['wasConverted'] = ($mime !== 'image/webp');
        $report['dimensions'] = $newW . '×' . $newH;

        $finalQuality = 0;
        foreach ($cfg['qualities'] as $quality) {
            imagewebp($resized, $dest, $quality);
            $finalQuality = $quality;
            if (filesize($dest) <= $cfg['maxFileSize']) {
                break;
            }
        }

        imagedestroy($resized);
        chmod($dest, 0644);

        // ── Build report ──
        $report['finalSize']    = filesize($dest);
        $report['finalSizeKB']  = round($report['finalSize'] / 1024, 1);
        $report['savedKB']      = round(($report['originalSize'] - $report['finalSize']) / 1024, 1);
        $report['savedPercent'] = $report['originalSize'] > 0
            ? round((1 - $report['finalSize'] / $report['originalSize']) * 100, 1)
            : 0;
        $report['qualityUsed']  = $finalQuality;
        $report['format']       = 'webp';

        return [
            'success'  => true,
            'filename' => $filename,
            'report'   => $report,
            'error'    => null,
        ];
    }

    /**
     * Batch optimize multiple files (e.g., product images 1-4).
     *
     * @param  array  $files   Array of $_FILES entries
     * @param  string $preset  Optimization preset
     * @return array  Array of results (same structure as optimize())
     */
    public static function optimizeBatch(array $files, string $preset = 'product'): array
    {
        $results = [];
        foreach ($files as $key => $file) {
            if (!empty($file['name'])) {
                $results[$key] = self::optimize($file, $preset);
            }
        }
        return $results;
    }

    /**
     * Get the preset configuration (for display in admin UI).
     */
    public static function getPresetConfig(string $preset): ?array
    {
        return self::$presets[$preset] ?? null;
    }

    /**
     * Get all preset names.
     */
    public static function getPresetNames(): array
    {
        return array_keys(self::$presets);
    }

    /**
     * Generate an HTML optimization report card.
     */
    public static function renderReportHTML(array $report): string
    {
        $color = ($report['savedPercent'] > 50) ? '#28a745' : (($report['savedPercent'] > 20) ? '#ffc107' : '#6c757d');
        $badge = $report['wasConverted'] ? '<span style="background:#C5A059;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">Converted to WebP</span>' : '';
        $resizeBadge = $report['wasResized'] ? '<span style="background:#17a2b8;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">Auto-resized</span>' : '';

        return '
        <div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:12px;margin:6px 0;font-size:13px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <strong>' . htmlspecialchars($report['originalName']) . '</strong>
                <span style="background:' . $color . ';color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                    −' . $report['savedPercent'] . '%
                </span>
            </div>
            <div style="display:flex;gap:20px;color:#555;">
                <span>Original: <strong>' . $report['originalSizeKB'] . ' KB</strong></span>
                <span>→</span>
                <span>Optimized: <strong>' . $report['finalSizeKB'] . ' KB</strong></span>
                <span>Saved: <strong>' . $report['savedKB'] . ' KB</strong></span>
            </div>
            <div style="margin-top:6px;display:flex;gap:8px;">
                ' . $badge . ' ' . $resizeBadge . '
                <span style="background:#e9ecef;padding:2px 8px;border-radius:4px;font-size:11px;">' . $report['dimensions'] . '</span>
                <span style="background:#e9ecef;padding:2px 8px;border-radius:4px;font-size:11px;">Q' . $report['qualityUsed'] . '</span>
            </div>
        </div>';
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private static function loadImage(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($path);
            case 'image/png':  return @imagecreatefrompng($path);
            case 'image/webp': return @imagecreatefromwebp($path);
            default:           return null;
        }
    }

    private static function fail(string $error, array $report): array
    {
        return [
            'success'  => false,
            'filename' => null,
            'report'   => $report,
            'error'    => $error,
        ];
    }
}
