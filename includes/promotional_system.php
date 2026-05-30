<?php
/**
 * ADVANCED PROMOTIONAL DISPLAY SYSTEM
 * Handles promotional banners, exit intent, signup incentives, and CRO optimization
 */

/**
 * Get active promotions for specific page/context
 */
function get_active_promotions($page = 'all', $productId = null, $categoryId = null) {
    try {
        $db = get_db_connection();
        $now = date('Y-m-d H:i:s');
        
        $sql = "SELECT * FROM promotions 
                WHERE is_active = 1 
                AND (start_date IS NULL OR start_date <= ?)
                AND (end_date IS NULL OR end_date >= ?)";
        
        $params = [$now, $now];
        
        // Filter by page
        switch($page) {
            case 'homepage':
                $sql .= " AND show_on_homepage = 1";
                break;
            case 'product':
                $sql .= " AND show_on_product_page = 1";
                break;
            case 'cart':
                $sql .= " AND show_on_cart = 1";
                break;
            case 'checkout':
                $sql .= " AND show_on_checkout = 1";
                break;
        }
        
        $sql .= " ORDER BY priority DESC, id DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter by targeting
        if ($productId || $categoryId) {
            $promotions = array_filter($promotions, function($promo) use ($productId, $categoryId) {
                if ($promo['target_type'] === 'all') return true;
                
                $targetIds = !empty($promo['target_ids']) ? json_decode($promo['target_ids'], true) : [];
                
                if ($promo['target_type'] === 'product' && $productId) {
                    return in_array($productId, $targetIds);
                }
                
                if ($promo['target_type'] === 'category' && $categoryId) {
                    return in_array($categoryId, $targetIds);
                }
                
                return false;
            });
        }
        
        return array_values($promotions);
    } catch (Exception $e) {
        error_log("Error getting promotions: " . $e->getMessage());
        return [];
    }
}

/**
 * Render promotional banner
 */
function render_promo_banner($promo, $context = 'default') {
    $bannerColor = htmlspecialchars($promo['banner_color'] ?? '#FF6B6B');
    $textColor = htmlspecialchars($promo['text_color'] ?? '#FFFFFF');
    $message = htmlspecialchars($promo['promo_message']);
    $badge = !empty($promo['promo_badge']) ? htmlspecialchars($promo['promo_badge']) : '';
    $icon = htmlspecialchars($promo['icon'] ?? 'tag');
    
    $urgencyHtml = '';
    if ($promo['show_countdown'] && !empty($promo['end_date'])) {
        $endTime = strtotime($promo['end_date']);
        $urgencyHtml = '<span class="promo-countdown" data-end="' . $endTime . '"></span>';
    } elseif (!empty($promo['urgency_message'])) {
        $urgencyHtml = '<span class="promo-urgency">' . htmlspecialchars($promo['urgency_message']) . '</span>';
    }
    
    $html = '<div class="promo-banner promo-' . $context . '" style="background: ' . $bannerColor . '; color: ' . $textColor . ';" data-promo-id="' . $promo['id'] . '">';
    $html .= '<div class="promo-content">';
    
    if ($badge) {
        $html .= '<span class="promo-badge">' . $badge . '</span>';
    }
    
    $html .= '<span class="promo-icon"><i class="fas fa-' . $icon . '"></i></span>';
    $html .= '<span class="promo-message">' . $message . '</span>';
    
    if ($urgencyHtml) {
        $html .= $urgencyHtml;
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get active exit intent popup
 */
function get_exit_intent_popup() {
    try {
        $db = get_db_connection();
        
        // Check if user has already seen popup in this session
        $sessionId = session_id();
        
        $sql = "SELECT eip.* FROM exit_intent_popups eip
                WHERE eip.is_active = 1
                ORDER BY eip.priority DESC, eip.id DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $popup = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$popup) return null;
        
        // Check if already shown
        if ($popup['show_once_per_session']) {
            $checkSql = "SELECT id FROM user_promo_interactions 
                        WHERE user_identifier = ? 
                        AND promo_id = ? 
                        AND promo_type = 'exit_intent'";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([$sessionId, $popup['id']]);
            
            if ($checkStmt->fetch()) {
                return null; // Already shown
            }
        }
        
        return $popup;
    } catch (Exception $e) {
        error_log("Error getting exit intent popup: " . $e->getMessage());
        return null;
    }
}

/**
 * Render exit intent popup
 */
function render_exit_intent_popup($popup) {
    $overlayColor = htmlspecialchars($popup['overlay_color'] ?? 'rgba(0,0,0,0.7)');
    $bgColor = htmlspecialchars($popup['background_color'] ?? '#FFFFFF');
    $headline = htmlspecialchars($popup['headline']);
    $subheadline = htmlspecialchars($popup['subheadline'] ?? '');
    $offerText = htmlspecialchars($popup['offer_text']);
    $ctaText = htmlspecialchars($popup['cta_text'] ?? 'Claim Offer');
    $ctaLink = htmlspecialchars($popup['cta_link'] ?? '#');
    $imageUrl = !empty($popup['image_url']) ? htmlspecialchars($popup['image_url']) : '';
    
    $html = '<div id="exitIntentPopup" class="exit-intent-overlay" style="background: ' . $overlayColor . ';" data-popup-id="' . $popup['id'] . '">';
    $html .= '<div class="exit-intent-modal" style="background: ' . $bgColor . ';">';
    $html .= '<button class="exit-intent-close" onclick="closeExitIntent()">&times;</button>';
    
    if ($imageUrl) {
        $html .= '<div class="exit-intent-image"><img src="' . $imageUrl . '" alt="Special Offer"></div>';
    }
    
    $html .= '<div class="exit-intent-content">';
    $html .= '<h2 class="exit-intent-headline">' . $headline . '</h2>';
    
    if ($subheadline) {
        $html .= '<p class="exit-intent-subheadline">' . $subheadline . '</p>';
    }
    
    $html .= '<div class="exit-intent-offer">' . $offerText . '</div>';
    
    if ($popup['auto_apply_coupon']) {
        $html .= '<div class="exit-intent-coupon">';
        $html .= '<span class="coupon-code">' . htmlspecialchars($popup['auto_apply_coupon']) . '</span>';
        $html .= '<button class="copy-coupon" onclick="copyCouponCode(\'' . htmlspecialchars($popup['auto_apply_coupon']) . '\')">Copy Code</button>';
        $html .= '</div>';
    }
    
    $html .= '<a href="' . $ctaLink . '" class="exit-intent-cta" onclick="trackExitIntentConversion(' . $popup['id'] . ')">' . $ctaText . '</a>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get active signup incentives
 */
function get_signup_incentives($page = 'register') {
    try {
        $db = get_db_connection();
        
        $sql = "SELECT * FROM signup_incentives WHERE is_active = 1";
        
        if ($page === 'register') {
            $sql .= " AND show_on_register_page = 1";
        } elseif ($page === 'checkout') {
            $sql .= " AND show_on_checkout = 1";
        }
        
        $sql .= " ORDER BY priority DESC, id DESC LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting signup incentives: " . $e->getMessage());
        return null;
    }
}

/**
 * Render signup incentive banner
 */
function render_signup_incentive($incentive) {
    $headline = htmlspecialchars($incentive['headline']);
    $description = htmlspecialchars($incentive['description'] ?? '');
    $discountValue = $incentive['discount_value'];
    $discountType = $incentive['discount_type'];
    
    $discountText = $discountType === 'percentage' ? $discountValue . '% OFF' : '₹' . $discountValue . ' OFF';
    
    $html = '<div class="signup-incentive-banner" data-incentive-id="' . $incentive['id'] . '">';
    $html .= '<div class="incentive-icon"><i class="fas fa-gift"></i></div>';
    $html .= '<div class="incentive-content">';
    $html .= '<h3 class="incentive-headline">' . $headline . '</h3>';
    
    if ($description) {
        $html .= '<p class="incentive-description">' . $description . '</p>';
    }
    
    $html .= '<div class="incentive-badge">' . $discountText . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get homepage banners
 */
function get_homepage_banners($position = null) {
    try {
        $db = get_db_connection();
        $now = date('Y-m-d H:i:s');
        
        $sql = "SELECT * FROM homepage_banners 
                WHERE is_active = 1
                AND (start_date IS NULL OR start_date <= ?)
                AND (end_date IS NULL OR end_date >= ?)";
        
        $params = [$now, $now];
        
        if ($position) {
            $sql .= " AND position = ?";
            $params[] = $position;
        }
        
        $sql .= " ORDER BY sort_order ASC, id DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting homepage banners: " . $e->getMessage());
        return [];
    }
}

/**
 * Render homepage banner
 */
function render_homepage_banner($banner) {
    $type = $banner['banner_type'];
    $headline = htmlspecialchars($banner['headline']);
    $subheadline = htmlspecialchars($banner['subheadline'] ?? '');
    $ctaText = htmlspecialchars($banner['cta_text'] ?? '');
    $ctaLink = htmlspecialchars($banner['cta_link'] ?? '#');
    $bgColor = htmlspecialchars($banner['background_color'] ?? '#FF6B6B');
    $textColor = htmlspecialchars($banner['text_color'] ?? '#FFFFFF');
    $bgImage = !empty($banner['background_image']) ? htmlspecialchars($banner['background_image']) : '';
    
    $style = 'background: ' . $bgColor . '; color: ' . $textColor . ';';
    if ($bgImage) {
        $style .= ' background-image: url(' . $bgImage . '); background-size: cover; background-position: center;';
    }
    
    $html = '<div class="homepage-banner banner-' . $type . '" style="' . $style . '" data-banner-id="' . $banner['id'] . '">';
    $html .= '<div class="banner-content">';
    $html .= '<h2 class="banner-headline">' . $headline . '</h2>';
    
    if ($subheadline) {
        $html .= '<p class="banner-subheadline">' . $subheadline . '</p>';
    }
    
    if ($banner['show_countdown'] && !empty($banner['end_date'])) {
        $endTime = strtotime($banner['end_date']);
        $html .= '<div class="banner-countdown" data-end="' . $endTime . '"></div>';
    }
    
    if ($ctaText) {
        $html .= '<a href="' . $ctaLink . '" class="banner-cta" onclick="trackBannerClick(' . $banner['id'] . ')">' . $ctaText . '</a>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Track promo view
 */
function track_promo_view($promoId, $promoType) {
    try {
        $db = get_db_connection();
        
        // Update view count
        $table = $promoType === 'promotion' ? 'promotions' : 
                ($promoType === 'exit_intent' ? 'exit_intent_popups' : 
                ($promoType === 'signup_incentive' ? 'signup_incentives' : 'homepage_banners'));
        
        if ($promoType === 'promotion') {
            $sql = "UPDATE {$table} SET views = views + 1 WHERE id = ?";
        } else {
            $sql = "UPDATE {$table} SET impressions = impressions + 1 WHERE id = ?";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$promoId]);
        
        // Log analytics
        $userId = $_SESSION['user']['id'] ?? null;
        $sessionId = session_id();
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $sql = "INSERT INTO promo_analytics (promo_id, promo_type, event_type, user_id, session_id, page_url, ip_address, user_agent)
                VALUES (?, ?, 'view', ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$promoId, $promoType, $userId, $sessionId, $pageUrl, $ipAddress, $userAgent]);
        
    } catch (Exception $e) {
        error_log("Error tracking promo view: " . $e->getMessage());
    }
}

/**
 * Track promo click
 */
function track_promo_click($promoId, $promoType) {
    try {
        $db = get_db_connection();
        
        // Update click count
        $table = $promoType === 'promotion' ? 'promotions' : 'homepage_banners';
        $sql = "UPDATE {$table} SET clicks = clicks + 1 WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$promoId]);
        
        // Log analytics
        $userId = $_SESSION['user']['id'] ?? null;
        $sessionId = session_id();
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $sql = "INSERT INTO promo_analytics (promo_id, promo_type, event_type, user_id, session_id, page_url, ip_address, user_agent)
                VALUES (?, ?, 'click', ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$promoId, $promoType, $userId, $sessionId, $pageUrl, $ipAddress, $userAgent]);
        
    } catch (Exception $e) {
        error_log("Error tracking promo click: " . $e->getMessage());
    }
}

/**
 * Mark exit intent as shown
 */
function mark_exit_intent_shown($popupId) {
    try {
        $db = get_db_connection();
        $sessionId = session_id();
        
        $sql = "INSERT INTO user_promo_interactions (user_identifier, promo_id, promo_type, interaction_type)
                VALUES (?, ?, 'exit_intent', 'viewed')
                ON DUPLICATE KEY UPDATE interaction_type = 'viewed'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sessionId, $popupId]);
        
    } catch (Exception $e) {
        error_log("Error marking exit intent shown: " . $e->getMessage());
    }
}

/**
 * Check if free shipping threshold is met
 */
function check_free_shipping_threshold($cartTotal) {
    $promotions = get_active_promotions('cart');
    
    foreach ($promotions as $promo) {
        if ($promo['promo_type'] === 'free_shipping' && $cartTotal >= $promo['min_order_value']) {
            return [
                'eligible' => true,
                'threshold' => $promo['min_order_value'],
                'message' => $promo['promo_message']
            ];
        } elseif ($promo['promo_type'] === 'free_shipping') {
            $remaining = $promo['min_order_value'] - $cartTotal;
            return [
                'eligible' => false,
                'threshold' => $promo['min_order_value'],
                'remaining' => $remaining,
                'message' => 'Add ₹' . number_format($remaining, 2) . ' more for free shipping!'
            ];
        }
    }
    
    return null;
}
