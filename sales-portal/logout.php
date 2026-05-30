<?php
require_once __DIR__ . '/includes/auth.php';
sales_logout();
// Clear localStorage token via JS for WebView
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><script>';
echo 'localStorage.removeItem("sales_auth_token");';
echo 'window.location.href="' . sales_base_url('login.php') . '";';
echo '</script></body></html>';
exit;
