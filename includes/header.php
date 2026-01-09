<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Gilaf Store | Taste • Culture • Craft';
$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
$userName = $_SESSION['user']['name'] ?? null;
$isLoggedIn = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- New Design CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/new-design.css'); ?>">
    <link rel="stylesheet" href="<?= asset_url('css/login-premium.css'); ?>">
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div style="display: flex; gap: 15px; align-items: center; justify-content: center;">
                <span><i class="fas fa-certificate"></i> Certified Organic</span>
                <span class="hidden-mobile">|</span>
                <span class="hidden-mobile">Ships to 15+ Countries</span>
            </div>
            <div style="display: flex; gap: 20px; align-items: center; justify-content: center;">
                <div class="region-trigger" onclick="openRegionModal()">
                    <span>Change Region</span>
                    <img id="current-flag" src="https://flagcdn.com/in.svg" width="20" alt="IN" style="border-radius: 2px;">
                    <span id="current-currency" style="font-weight: 600;">INR (₹)</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.6rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header" id="header">
        <div class="container nav-container">
            <div class="menu-toggle"><i class="fas fa-bars"></i></div>
            <div class="logo">
                <a href="<?= base_url('index.php'); ?>">
                    <h1>GILAF STORE</h1>
                    <span>Taste • Culture • Craft</span>
                </a>
            </div>
            <nav class="nav-links">
                <a href="<?= base_url('index.php'); ?>">HOME</a>
                <a href="<?= base_url('shop.php'); ?>">SHOP</a>
                <div class="dropdown">
                    <span class="dropbtn">SHOP BY CATEGORY <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <?php
                        $categories = get_categories();
                        foreach ($categories as $cat):
                        ?>
                            <a href="<?= base_url('shop.php?category=' . $cat['id']); ?>"><?= htmlspecialchars($cat['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="dropdown">
                    <span class="dropbtn">TRACK <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <a href="#" onclick="openTrackingModal(); return false;">Track Order</a>
                        <a href="#locator">Track Stores</a>
                        <a href="<?= base_url('verify-batch.php'); ?>">Verify Product Authenticity</a>
                    </div>
                </div>
                <div class="dropdown">
                    <span class="dropbtn">OUR STORY <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <a href="#story">About Us</a>
                        <a href="#">Our Values</a>
                        <a href="#">Blogs</a>
                    </div>
                </div>
            </nav>
            <div class="user-actions">
                <i class="fas fa-search" onclick="toggleSearchBar()" style="cursor: pointer;" title="Search"></i>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= base_url('user/profile.php'); ?>" title="<?= htmlspecialchars($userName); ?>">
                        <i class="fas fa-user"></i>
                    </a>
                <?php else: ?>
                    <i class="fas fa-user" onclick="openLoginModal()" title="Login"></i>
                <?php endif; ?>
                <a href="<?= base_url('cart.php'); ?>" style="position: relative;">
                    <i class="fas fa-shopping-bag"></i>
                    <?php if ($cartCount > 0): ?>
                        <span style="position: absolute; top: -5px; right: -8px; background: var(--color-gold); color: white; font-size: 10px; padding: 2px 5px; border-radius: 50%;"><?= $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <!-- Search Bar (Hidden by default) -->
        <div id="searchBar" style="display: none; background: white; padding: 20px 0; border-top: 1px solid #eee; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
            <div class="container">
                <form action="<?= base_url('search.php'); ?>" method="get" style="display: flex; gap: 10px; max-width: 600px; margin: 0 auto;">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Search for products..." 
                        style="flex: 1; padding: 12px 20px; border: 1px solid #ddd; border-radius: 50px; font-size: 0.95rem; outline: none;"
                        required
                        autofocus
                    >
                    <button type="submit" class="btn btn-primary" style="border-radius: 50px; padding: 12px 30px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Search Bar Script -->
    <script>
        function toggleSearchBar() {
            const searchBar = document.getElementById('searchBar');
            if (searchBar.style.display === 'none') {
                searchBar.style.display = 'block';
                searchBar.querySelector('input').focus();
            } else {
                searchBar.style.display = 'none';
            }
        }
    </script>

    <?php if (function_exists('display_flash')) { display_flash(); } ?>
    <main>
