<?php
/**
 * Smart Document Compressor API
 * Compresses uploaded PDF/image files server-side
 * Target: ~30-45 KB while maintaining readability
 */

// Don't set content-type until we know the result
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Method not allowed']));
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'No file uploaded or upload error']));
}

$file = $_FILES['document'];
$targetKB = isset($_POST['target_kb']) ? (int)$_POST['target_kb'] : 45;
$targetBytes = $targetKB * 1024;
$tmpPath = $file['tmp_name'];
$mimeType = @mime_content_type($tmpPath) ?: 'application/octet-stream';
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file type
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
$allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

if (!in_array($mimeType, $allowedTypes) && !in_array($extension, $allowedExts)) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Unsupported file type: ' . $mimeType]));
}

// Max upload size: 10 MB
if ($file['size'] > 10 * 1024 * 1024) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'File too large (max 10 MB)']));
}

try {
    $compressedData = false;

    if ($extension === 'pdf' || $mimeType === 'application/pdf') {
        $compressedData = compressPDF($tmpPath, $targetBytes);
    } else {
        $compressedData = compressImageFile($tmpPath, $mimeType, $targetBytes);
    }

    if ($compressedData === false || strlen($compressedData) === 0) {
        $compressedData = file_get_contents($tmpPath);
    }

    $outMime = ($extension === 'pdf') ? 'application/pdf' : 'image/jpeg';

    ob_end_clean();
    header('Content-Type: ' . $outMime);
    header('Content-Length: ' . strlen($compressedData));
    header('X-Original-Size: ' . $file['size']);
    header('X-Compressed-Size: ' . strlen($compressedData));
    echo $compressedData;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Compression failed: ' . $e->getMessage()]);
} catch (Error $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

exit;

// =============================================
// IMAGE COMPRESSION (using GD library)
// =============================================
function compressImageFile($path, $mimeType, $targetBytes) {
    if (!extension_loaded('gd')) {
        throw new Exception('GD library not available');
    }

    // Load image based on type
    switch (true) {
        case strpos($mimeType, 'jpeg') !== false:
        case strpos($mimeType, 'jpg') !== false:
            $img = @imagecreatefromjpeg($path);
            break;
        case strpos($mimeType, 'png') !== false:
            $img = @imagecreatefrompng($path);
            break;
        case strpos($mimeType, 'webp') !== false:
            $img = @imagecreatefromwebp($path);
            break;
        default:
            $img = @imagecreatefromstring(file_get_contents($path));
    }

    if (!$img) {
        throw new Exception('Failed to load image');
    }

    $w = imagesx($img);
    $h = imagesy($img);

    // Smart resize if too large
    $maxDim = 1400;
    if ($w > $maxDim || $h > $maxDim) {
        if ($w > $h) {
            $newW = $maxDim;
            $newH = (int)round($h * $maxDim / $w);
        } else {
            $newH = $maxDim;
            $newW = (int)round($w * $maxDim / $h);
        }
        $resized = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
        $w = $newW;
        $h = $newH;
    }

    // Binary search for optimal JPEG quality
    $lo = 5;
    $hi = 90;
    $bestData = null;

    for ($i = 0; $i < 12; $i++) {
        $mid = (int)(($lo + $hi) / 2);
        ob_start();
        imagejpeg($img, null, $mid);
        $data = ob_get_clean();

        if (strlen($data) > $targetBytes) {
            $hi = $mid;
        } else {
            $lo = $mid;
            $bestData = $data;
        }
    }

    // If still too large, resize further
    if (!$bestData || strlen($bestData) > $targetBytes * 1.5) {
        $newW = (int)round($w * 0.6);
        $newH = (int)round($h * 0.6);
        $smaller = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($smaller, 255, 255, 255);
        imagefill($smaller, 0, 0, $white);
        imagecopyresampled($smaller, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $smaller;

        $lo = 5;
        $hi = 85;
        for ($i = 0; $i < 10; $i++) {
            $mid = (int)(($lo + $hi) / 2);
            ob_start();
            imagejpeg($img, null, $mid);
            $data = ob_get_clean();

            if (strlen($data) > $targetBytes) {
                $hi = $mid;
            } else {
                $lo = $mid;
                $bestData = $data;
            }
        }
    }

    imagedestroy($img);

    if (!$bestData) {
        // Last resort: very low quality
        $img = @imagecreatefromjpeg($path) ?: imagecreatefromstring(file_get_contents($path));
        ob_start();
        imagejpeg($img, null, 10);
        $bestData = ob_get_clean();
        imagedestroy($img);
    }

    return $bestData;
}

// =============================================
// PDF COMPRESSION
// =============================================
function compressPDF($path, $targetBytes) {
    $currentSize = filesize($path);

    // If already small enough, return as-is
    if ($currentSize <= $targetBytes) {
        return file_get_contents($path);
    }

    // Try Ghostscript if available (best PDF compression)
    $gsPath = findGhostscript();
    if ($gsPath) {
        $tmpOut = tempnam(sys_get_temp_dir(), 'gs_pdf_');

        // Try screen quality first (smallest), then ebook
        $qualities = ['screen', 'ebook'];
        foreach ($qualities as $quality) {
            $cmd = escapeshellarg($gsPath) . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4'
                . ' -dPDFSETTINGS=/' . $quality
                . ' -dNOPAUSE -dQUIET -dBATCH'
                . ' -dColorImageResolution=100 -dGrayImageResolution=100'
                . ' -sOutputFile=' . escapeshellarg($tmpOut)
                . ' ' . escapeshellarg($path);

            @exec($cmd, $output, $retval);

            if ($retval === 0 && file_exists($tmpOut) && filesize($tmpOut) > 0) {
                $data = file_get_contents($tmpOut);
                @unlink($tmpOut);
                if (strlen($data) <= $targetBytes * 1.5) {
                    return $data;
                }
            }
        }
        @unlink($tmpOut);
    }

    // Try Imagick if available — convert PDF pages to compressed images then reassemble
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->setResolution(120, 120);
            $imagick->readImage($path);

            $pageCount = $imagick->getNumberImages();
            $qualityPerPage = max(20, min(70, (int)($targetBytes / max(1, $pageCount) / 500)));

            $imagick->setImageCompressionQuality($qualityPerPage);

            // Write compressed PDF
            $imagick->setImageFormat('pdf');
            $data = $imagick->getImagesBlob();
            $imagick->destroy();

            if (strlen($data) > 0) {
                return $data;
            }
        } catch (Exception $e) {
            // Imagick failed, continue to fallback
        }
    }

    // Fallback: return original PDF (can't compress without gs/imagick)
    return file_get_contents($path);
}

function findGhostscript() {
    if (PHP_OS_FAMILY === 'Windows') {
        $paths = [
            'C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe',
        ];
        foreach ($paths as $pattern) {
            $matches = glob($pattern);
            if (!empty($matches)) {
                return $matches[0];
            }
        }
        // Try just the command
        exec('gswin64c --version 2>&1', $out, $ret);
        if ($ret === 0) return 'gswin64c';
        exec('gswin32c --version 2>&1', $out, $ret);
        if ($ret === 0) return 'gswin32c';
    } else {
        exec('which gs 2>/dev/null', $out, $ret);
        if ($ret === 0 && !empty($out[0])) return trim($out[0]);
        // Common Linux paths
        foreach (['/usr/bin/gs', '/usr/local/bin/gs'] as $p) {
            if (file_exists($p)) return $p;
        }
    }
    return null;
}
