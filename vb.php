<?php
/**
 * Short URL redirect for batch verification QR codes
 * vb.php?c=BATCH_CODE → verify-batch.php?code=BATCH_CODE
 * Keeps QR codes compact for product packaging
 */
$code = $_GET['c'] ?? $_GET['code'] ?? '';
if ($code) {
    header('Location: verify-batch.php?code=' . urlencode($code));
} else {
    header('Location: verify-batch.php');
}
exit;
