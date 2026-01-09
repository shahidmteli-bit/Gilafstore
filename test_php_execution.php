<?php
// Simple test to verify PHP is executing
echo "<!DOCTYPE html><html><head><title>PHP Execution Test</title></head><body>";
echo "<h1>PHP Execution Test</h1>";
echo "<p><strong>PHP is working:</strong> " . (phpversion() ? "✓ YES" : "✗ NO") . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Test base_url
require_once __DIR__ . '/includes/functions.php';
$testUrl = base_url('cart.php');
echo "<p><strong>base_url('cart.php') returns:</strong> <code>$testUrl</code></p>";

// Test actual link rendering
echo "<h2>Test Link Rendering:</h2>";
echo '<a href="' . base_url('cart.php') . '" style="display: inline-block; padding: 15px 30px; background: #1a3c34; color: white; text-decoration: none; border-radius: 8px;">Click to go to Cart</a>';

echo "<h2>View Page Source:</h2>";
echo "<p>Right-click this page and select 'View Page Source'. Check if the link href shows:</p>";
echo "<ul>";
echo "<li>✓ GOOD: <code>href=\"http://localhost/Gilaf%20Ecommerce%20website/cart.php\"</code></li>";
echo "<li>✗ BAD: <code>href=\"&lt;?= base_url('cart.php'); ?&gt;\"</code></li>";
echo "</ul>";

echo "<h2>Inspect Element Test:</h2>";
echo "<p>Right-click the link above and select 'Inspect'. Check the href attribute in the Elements tab.</p>";

echo "</body></html>";
?>
