<?php
/**
 * Promo Code Functions
 * Handles validation, application, and tracking of promotional discount codes
 * Enhanced with intelligent user eligibility detection and analytics
 */

/**
 * Get user order history and profile
 * @param string|null $email User email
 * @param string|null $phone User phone
 * @param int|null $userId User ID
 * @return array User profile with order history
 */
function get_user_profile($email = null, $phone = null, $userId = null) {
    try {
        // Build query to find user by email, phone, or ID
        $conditions = [];
        $params = [];
        
        if ($userId) {
            $conditions[] = 'u.id = ?';
            $params[] = $userId;
        }
        if ($email) {
            $conditions[] = 'u.email = ?';
            $params[] = $email;
        }
        if ($phone) {
            $conditions[] = 'u.phone = ?';
            $params[] = $phone;
        }
        
        if (empty($conditions)) {
            return ['exists' => false, 'order_count' => 0, 'last_order_date' => null];
        }
        
        $whereClause = implode(' OR ', $conditions);
        
        // Get user and order count
        $sql = "SELECT u.id, u.email, u.phone, u.created_at,
                       COUNT(DISTINCT o.id) as order_count,
                       MAX(o.created_at) as last_order_date,
                       MIN(o.created_at) as first_order_date
                FROM users u
                LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled' AND o.status != 'failed'
                WHERE {$whereClause}
                GROUP BY u.id
                LIMIT 1";
        
        $user = db_fetch($sql, $params);
        
        if (!$user) {
            return ['exists' => false, 'order_count' => 0, 'last_order_date' => null];
        }
        
        return [
            'exists' => true,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'order_count' => (int)$user['order_count'],
            'last_order_date' => $user['last_order_date'],
            'first_order_date' => $user['first_order_date'],
            'created_at' => $user['created_at'],
            'days_since_last_order' => $user['last_order_date'] ? 
                floor((time() - strtotime($user['last_order_date'])) / 86400) : null
        ];
    } catch (Exception $e) {
        error_log("Error getting user profile: " . $e->getMessage());
        return ['exists' => false, 'order_count' => 0, 'last_order_date' => null];
    }
}

/**
 * Check if user is eligible for promo code based on eligibility type
 * @param array $promo Promo code data
 * @param array $userProfile User profile from get_user_profile()
 * @return array Result with 'eligible' boolean and 'reason' message
 */
function check_user_eligibility($promo, $userProfile) {
    $eligibilityType = $promo['eligibility_type'] ?? 'all_users';
    $orderCount = $userProfile['order_count'] ?? 0;
    $exists = $userProfile['exists'] ?? false;
    
    switch ($eligibilityType) {
        case 'new_users':
            if ($exists) {
                return ['eligible' => false, 'reason' => 'This promo code is only for new users'];
            }
            return ['eligible' => true, 'reason' => 'New user eligible'];
            
        case 'first_time':
            if (!$exists || $orderCount !== 0) {
                return ['eligible' => false, 'reason' => 'This promo code is only for first-time buyers'];
            }
            return ['eligible' => true, 'reason' => 'First-time buyer eligible'];
            
        case 'second_time':
            if ($orderCount !== 1) {
                return ['eligible' => false, 'reason' => 'This promo code is only for second-time buyers'];
            }
            return ['eligible' => true, 'reason' => 'Second-time buyer eligible'];
            
        case 'first_second_time':
            if ($orderCount > 1) {
                return ['eligible' => false, 'reason' => 'This promo code is only for first or second-time buyers'];
            }
            return ['eligible' => true, 'reason' => 'First/second-time buyer eligible'];
            
        case 'third_time':
            if ($orderCount !== 2) {
                return ['eligible' => false, 'reason' => 'This promo code is only for third-time buyers'];
            }
            return ['eligible' => true, 'reason' => 'Third-time buyer eligible'];
            
        case 'repeat_users':
            if ($orderCount < 4) {
                return ['eligible' => false, 'reason' => 'This promo code is only for repeat customers (4+ orders)'];
            }
            return ['eligible' => true, 'reason' => 'Repeat customer eligible'];
            
        case 'returning_inactive':
            $inactiveDays = $promo['inactive_days'] ?? 30;
            $daysSinceLastOrder = $userProfile['days_since_last_order'] ?? 0;
            
            if (!$exists || $orderCount === 0) {
                return ['eligible' => false, 'reason' => 'This promo code is only for returning customers'];
            }
            if ($daysSinceLastOrder < $inactiveDays) {
                return ['eligible' => false, 'reason' => "This promo code is only for customers inactive for {$inactiveDays}+ days"];
            }
            return ['eligible' => true, 'reason' => 'Returning inactive customer eligible'];
            
        case 'all_existing':
            if (!$exists) {
                return ['eligible' => false, 'reason' => 'This promo code is only for existing customers'];
            }
            return ['eligible' => true, 'reason' => 'Existing customer eligible'];
            
        case 'all_users':
            return ['eligible' => true, 'reason' => 'All users eligible'];
            
        case 'custom':
            // Custom rule evaluation would go here
            // For now, default to eligible
            return ['eligible' => true, 'reason' => 'Custom rule eligible'];
            
        default:
            return ['eligible' => true, 'reason' => 'Default eligible'];
    }
}

/**
 * Get user type label based on order count
 * @param int $orderCount Number of orders
 * @return string User type label
 */
function get_user_type_label($orderCount) {
    if ($orderCount === 0) return 'First-Time Buyer';
    if ($orderCount === 1) return 'Second-Time Buyer';
    if ($orderCount === 2) return 'Third-Time Buyer';
    if ($orderCount >= 4) return 'Repeat Customer';
    return 'Customer';
}

/**
 * Validate a promo code
 * @param string $code The promo code to validate
 * @param float $cartTotal The current cart total
 * @param int|null $userId The user ID (optional)
 * @return array Result with 'valid' boolean and 'message' or 'promo' data
 */
function validate_promo_code($code, $cartTotal, $userId = null, $userEmail = null, $userPhone = null) {
    try {
        $code = strtoupper(trim($code));
        
        if (empty($code)) {
            return ['valid' => false, 'message' => 'Please enter a promo code'];
        }
        
        // Get promo code from database
        $sql = "SELECT * FROM promo_codes WHERE code = ?";
        $promo = db_fetch($sql, [$code]);
        
        if (!$promo) {
            return ['valid' => false, 'message' => 'Invalid promo code'];
        }
        
        // Check if active
        if (!$promo['is_active']) {
            return ['valid' => false, 'message' => 'This promo code is currently inactive'];
        }
        
        // Check validity period
        $now = time();
        $validFrom = strtotime($promo['valid_from']);
        $validUntil = strtotime($promo['valid_until']);
        
        if ($now < $validFrom) {
            return ['valid' => false, 'message' => 'This promo code is not yet active'];
        }
        
        if ($now > $validUntil) {
            return ['valid' => false, 'message' => 'This promo code has expired'];
        }
        
        // Check minimum order value
        if ($cartTotal < $promo['min_order_value']) {
            return [
                'valid' => false, 
                'message' => 'Minimum order value of ₹' . number_format($promo['min_order_value'], 2) . ' required'
            ];
        }
        
        // Check usage limit
        if ($promo['usage_limit'] && $promo['used_count'] >= $promo['usage_limit']) {
            return ['valid' => false, 'message' => 'This promo code has reached its usage limit'];
        }
        
        // INTELLIGENT USER ELIGIBILITY CHECK
        $userProfile = get_user_profile($userEmail, $userPhone, $userId);
        $eligibilityCheck = check_user_eligibility($promo, $userProfile);
        
        if (!$eligibilityCheck['eligible']) {
            return ['valid' => false, 'message' => $eligibilityCheck['reason']];
        }
        
        // Calculate discount
        $discount = calculate_promo_discount($promo, $cartTotal);
        
        return [
            'valid' => true,
            'promo' => $promo,
            'discount' => $discount,
            'user_profile' => $userProfile
        ];
        
    } catch (Exception $e) {
        error_log("Promo code validation error: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Error validating promo code'];
    }
}

/**
 * Calculate discount amount for a promo code
 * @param array $promo The promo code data
 * @param float $cartTotal The cart total
 * @return float The discount amount
 */
function calculate_promo_discount($promo, $cartTotal) {
    if ($promo['discount_type'] === 'percentage') {
        $discount = ($cartTotal * $promo['discount_value']) / 100;
        
        // Apply max discount cap if set
        if ($promo['max_discount'] && $discount > $promo['max_discount']) {
            $discount = $promo['max_discount'];
        }
    } else {
        // Fixed amount discount
        $discount = $promo['discount_value'];
        
        // Don't exceed cart total
        if ($discount > $cartTotal) {
            $discount = $cartTotal;
        }
    }
    
    return round($discount, 2);
}

/**
 * Apply promo code to session
 * @param string $code The promo code
 * @param float $cartTotal The cart total
 * @return array Result with success status and message
 */
function apply_promo_code($code, $cartTotal, $userEmail = null, $userPhone = null) {
    $userId = $_SESSION['user']['id'] ?? null;
    $userEmail = $userEmail ?? ($_SESSION['user']['email'] ?? null);
    $userPhone = $userPhone ?? ($_SESSION['user']['phone'] ?? null);
    
    $validation = validate_promo_code($code, $cartTotal, $userId, $userEmail, $userPhone);
    
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }
    
    // Store in session
    $_SESSION['promo_code'] = [
        'id' => $validation['promo']['id'],
        'code' => $validation['promo']['code'],
        'discount_type' => $validation['promo']['discount_type'],
        'discount_value' => $validation['promo']['discount_value'],
        'discount_amount' => $validation['discount'],
        'applied_at' => time()
    ];
    
    return [
        'success' => true,
        'message' => 'Promo code applied successfully!',
        'discount' => $validation['discount']
    ];
}

/**
 * Remove promo code from session
 */
function remove_promo_code() {
    unset($_SESSION['promo_code']);
}

/**
 * Get applied promo code from session
 * @return array|null The promo code data or null
 */
function get_applied_promo_code() {
    return $_SESSION['promo_code'] ?? null;
}

/**
 * Recalculate promo discount based on new cart total
 * @param float $newCartTotal The new cart total
 * @return float The updated discount amount
 */
function recalculate_promo_discount($newCartTotal) {
    $appliedPromo = get_applied_promo_code();
    
    if (!$appliedPromo) {
        return 0;
    }
    
    // Re-validate the promo code with new total
    $validation = validate_promo_code($appliedPromo['code'], $newCartTotal);
    
    if (!$validation['valid']) {
        // Promo is no longer valid, remove it
        remove_promo_code();
        return 0;
    }
    
    // Update discount amount in session
    $_SESSION['promo_code']['discount_amount'] = $validation['discount'];
    
    return $validation['discount'];
}

/**
 * Record promo code usage after successful order
 * @param int $promoId The promo code ID
 * @param int|null $userId The user ID
 * @param int|null $orderId The order ID
 * @param float $discountAmount The discount amount applied
 * @return bool Success status
 */
function record_promo_usage($promoId, $userId, $orderId, $discountAmount, $userEmail = null, $userPhone = null, $orderCount = 0) {
    try {
        // Get user profile for tracking
        $userProfile = get_user_profile($userEmail, $userPhone, $userId);
        $userType = get_user_type_label($orderCount);
        
        // Insert usage record with enhanced tracking
        $sql = "INSERT INTO promo_code_usage (promo_code_id, user_id, order_id, discount_amount, user_email, user_phone, order_count_at_use, user_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        db_query($sql, [$promoId, $userId, $orderId, $discountAmount, $userEmail, $userPhone, $orderCount, $userType]);
        
        // Increment used count
        $sql = "UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?";
        db_query($sql, [$promoId]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording promo usage: " . $e->getMessage());
        return false;
    }
}

/**
 * Get promo code discount summary for display
 * @param float $cartTotal The cart total
 * @return array|null Discount summary or null
 */
function get_promo_discount_summary($cartTotal) {
    $appliedPromo = get_applied_promo_code();
    
    if (!$appliedPromo) {
        return null;
    }
    
    // Recalculate to ensure accuracy
    $discount = recalculate_promo_discount($cartTotal);
    
    if ($discount <= 0) {
        return null;
    }
    
    return [
        'code' => $appliedPromo['code'],
        'discount_amount' => $discount,
        'discount_type' => $appliedPromo['discount_type'],
        'discount_value' => $appliedPromo['discount_value']
    ];
}

/**
 * Check if user has already used a promo code
 * @param int $userId The user ID
 * @param int $promoId The promo code ID
 * @return bool True if already used
 */
function has_user_used_promo($userId, $promoId) {
    try {
        $sql = "SELECT COUNT(*) as count FROM promo_code_usage 
                WHERE user_id = ? AND promo_code_id = ?";
        $result = db_fetch($sql, [$userId, $promoId]);
        return $result['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all active promo codes (for display/marketing)
 * @return array List of active promo codes
 */
function get_active_promo_codes($forHeader = false, $userProfile = null) {
    try {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT code, description, promo_message, discount_type, discount_value, min_order_value, valid_until, eligibility_type, display_in_header 
                FROM promo_codes 
                WHERE is_active = 1 
                AND valid_from <= ? 
                AND valid_until >= ?
                AND (usage_limit IS NULL OR used_count < usage_limit)";
        
        if ($forHeader) {
            $sql .= " AND display_in_header = 1";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $promoCodes = db_fetch_all($sql, [$now, $now]);
        
        // Filter by user eligibility if user profile provided
        if ($userProfile && $forHeader) {
            $promoCodes = array_filter($promoCodes, function($promo) use ($userProfile) {
                $eligibility = check_user_eligibility($promo, $userProfile);
                return $eligibility['eligible'];
            });
        }
        
        return array_values($promoCodes);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get detailed promo code usage statistics with filters
 * @param int|null $promoId Filter by promo code ID
 * @param string|null $dateFrom Filter from date
 * @param string|null $dateTo Filter to date
 * @param string|null $userType Filter by user type
 * @return array Usage statistics
 */
function get_promo_usage_analytics($promoId = null, $dateFrom = null, $dateTo = null, $userType = null) {
    try {
        $sql = "SELECT 
                    pcu.*,
                    pc.code,
                    pc.description as promo_description,
                    o.total_amount as order_value,
                    o.created_at as order_date,
                    o.status as order_status
                FROM promo_code_usage pcu
                JOIN promo_codes pc ON pcu.promo_code_id = pc.id
                LEFT JOIN orders o ON pcu.order_id = o.id
                WHERE 1=1";
        
        $params = [];
        
        if ($promoId) {
            $sql .= " AND pcu.promo_code_id = ?";
            $params[] = $promoId;
        }
        
        if ($dateFrom) {
            $sql .= " AND pcu.used_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND pcu.used_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        if ($userType) {
            $sql .= " AND pcu.user_type = ?";
            $params[] = $userType;
        }
        
        // Only include successful orders
        $sql .= " AND (o.status IS NULL OR o.status NOT IN ('cancelled', 'failed'))";
        $sql .= " ORDER BY pcu.used_at DESC";
        
        return db_fetch_all($sql, $params);
    } catch (Exception $e) {
        error_log("Error getting promo analytics: " . $e->getMessage());
        return [];
    }
}
