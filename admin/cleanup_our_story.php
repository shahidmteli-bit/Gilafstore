<?php
/**
 * Cleanup Script - Remove "Our Story" and ensure "Our Philosophy" exists
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    die('Access denied');
}

echo "<h2>Cleaning up Our Story content...</h2>";

// Delete any existing "our_story" entries
$deleted = db_query("DELETE FROM page_content WHERE page_key = 'our_story'");
echo "<p>✓ Removed 'our_story' entries from database</p>";

// Check if our_philosophy exists
$philosophy = db_fetch("SELECT * FROM page_content WHERE page_key = 'our_philosophy'");

if (!$philosophy) {
    // Create our_philosophy entry
    $content = '<h2 style="color: #C9A961; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">OUR PHILOSOPHY</h2><h1 style="color: #C9A961; font-size: 3rem; font-weight: 700; margin-bottom: 30px;">Preserving the Art of Taste</h1><p style="font-size: 1.1rem; line-height: 1.8; margin-bottom: 20px;">At Gilaf Foods & Spices, we believe that food is not just sustenance—it is memory. Founded by Shahid Mohammad & Muneera Shahid, our mission is to bring the unadulterated taste of Kashmir to your table.</p><p style="font-size: 1.1rem; line-height: 1.8;">We work directly with local farmers, ensuring that every strand of saffron and every drop of honey retains the purity of the mountains.</p>';
    
    db_query("INSERT INTO page_content (page_key, page_title, content, meta_description, is_active) VALUES (?, ?, ?, ?, 1)",
        ['our_philosophy', 'Our Philosophy', $content, 'Discover our philosophy of preserving authentic Kashmiri taste']);
    
    echo "<p>✓ Created 'our_philosophy' entry</p>";
} else {
    echo "<p>✓ 'our_philosophy' already exists</p>";
}

echo "<h3 style='color: green;'>Cleanup Complete!</h3>";
echo "<p><a href='manage_content.php'>Go to Content Management</a></p>";
echo "<p><a href='../about-us.php' target='_blank'>View About Us Page</a></p>";
?>
