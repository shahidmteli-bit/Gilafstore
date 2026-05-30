<?php
/**
 * Gilaf Rewards Engine — Core wallet/earning/redemption/expiry logic
 * Include this file wherever wallet operations are needed.
 */

if (!function_exists('rw_get_rule')) {

    // ── Rule helper ──────────────────────────────────────────────────────────
    // ── Rule helper (string values) ──────────────────────────────────────
    function rw_get_rule_str(string $key, string $default = ''): string {
        static $scache = [];
        if (!isset($scache[$key])) {
            $db = get_db_connection();
            try {
                $st = $db->prepare("SELECT value_str FROM rewards_rules WHERE rule_key = ? LIMIT 1");
                $st->execute([$key]);
                $row = $st->fetch();
                $scache[$key] = $row ? (string)($row['value_str'] ?? $default) : $default;
            } catch (Exception $e) { $scache[$key] = $default; }
        }
        return $scache[$key];
    }

    function rw_get_rule(string $key, float $default = 0.0): float {
        static $cache = [];
        if (!isset($cache[$key])) {
            $db = get_db_connection();
            try {
                $st = $db->prepare("SELECT value FROM rewards_rules WHERE rule_key = ? AND is_active = 1 LIMIT 1");
                $st->execute([$key]);
                $row = $st->fetch();
                $cache[$key] = $row ? (float)$row['value'] : $default;
            } catch (Exception $e) { $cache[$key] = $default; }
        }
        return $cache[$key];
    }

    // ── Wallet: get or create ────────────────────────────────────────────────
    function rw_get_wallet(int $userId): array {
        $db = get_db_connection();
        $st = $db->prepare("SELECT * FROM rewards_wallets WHERE user_id = ?");
        $st->execute([$userId]);
        $wallet = $st->fetch();
        if (!$wallet) {
            $code = strtoupper(substr(md5($userId . time()), 0, 8));
            $db->prepare("INSERT INTO rewards_wallets (user_id, referral_code) VALUES (?, ?)")->execute([$userId, $code]);
            $st->execute([$userId]);
            $wallet = $st->fetch();
        }
        return $wallet ?: [];
    }

    // ── Wallet: credit ───────────────────────────────────────────────────────
    function rw_credit(int $userId, float $amount, string $source, string $description = '', ?int $orderId = null, ?string $expiresAt = null): bool {
        if ($amount <= 0) return false;
        $db = get_db_connection();
        $wallet = rw_get_wallet($userId);
        if (!$wallet || $wallet['is_frozen'] || $wallet['is_disabled']) return false;

        $newBalance = $wallet['balance'] + $amount;
        $newLifetime = $wallet['lifetime_earned'] + $amount;

        $db->prepare("UPDATE rewards_wallets SET balance = ?, lifetime_earned = ?, updated_at = NOW() WHERE user_id = ?")->execute([$newBalance, $newLifetime, $userId]);
        $db->prepare("INSERT INTO rewards_transactions (user_id, type, amount, balance_after, source, order_id, description, expires_at) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$userId, 'credit', $amount, $newBalance, $source, $orderId, $description, $expiresAt]);

        // Notify
        rw_notify($userId, 'earned', '₹' . number_format($amount, 0) . ' Rewards Earned!',
            'You earned ₹' . number_format($amount, 0) . ' in Gilaf Rewards Wallet. ' . $description);

        // Check tier upgrade
        rw_check_tier_upgrade($userId);

        return true;
    }

    // ── Wallet: debit (redemption) ───────────────────────────────────────────
    function rw_debit(int $userId, float $amount, string $description = '', ?int $orderId = null): bool {
        if ($amount <= 0) return false;
        $db = get_db_connection();
        $wallet = rw_get_wallet($userId);
        if (!$wallet || $wallet['is_frozen'] || $wallet['is_disabled']) return false;
        if ($wallet['balance'] < $amount) return false;

        $newBalance = $wallet['balance'] - $amount;
        $newRedeemed = $wallet['lifetime_redeemed'] + $amount;

        $db->prepare("UPDATE rewards_wallets SET balance = ?, lifetime_redeemed = ?, updated_at = NOW() WHERE user_id = ?")->execute([$newBalance, $newRedeemed, $userId]);
        $db->prepare("INSERT INTO rewards_transactions (user_id, type, amount, balance_after, source, order_id, description) VALUES (?,?,?,?,?,?,?)")
           ->execute([$userId, 'debit', $amount, $newBalance, 'redemption', $orderId, $description]);

        rw_notify($userId, 'redeemed', '₹' . number_format($amount, 0) . ' Rewards Used',
            'You redeemed ₹' . number_format($amount, 0) . ' from your Gilaf Rewards Wallet.');
        return true;
    }

    // ── Locked rewards: add ──────────────────────────────────────────────────
    function rw_add_locked(int $userId, float $amount, string $condition, float $unlockValue, string $source, string $description = ''): bool {
        if ($amount <= 0) return false;
        $db = get_db_connection();
        $wallet = rw_get_wallet($userId);
        if (!$wallet) return false;

        $expiry = date('Y-m-d H:i:s', strtotime('+90 days'));
        $db->prepare("INSERT INTO rewards_locked_rewards (user_id, amount, unlock_condition, unlock_value, source, description, expires_at) VALUES (?,?,?,?,?,?,?)")
           ->execute([$userId, $amount, $condition, $unlockValue, $source, $description, $expiry]);

        $newLocked = $wallet['locked_balance'] + $amount;
        $db->prepare("UPDATE rewards_wallets SET locked_balance = ?, updated_at = NOW() WHERE user_id = ?")->execute([$newLocked, $userId]);
        return true;
    }

    // ── Locked rewards: attempt unlock ──────────────────────────────────────
    function rw_try_unlock_locked(int $userId, string $event, float $eventValue = 0): int {
        $db = get_db_connection();
        $conditionMap = [
            'first_order'     => 'first_order',
            'spend_threshold' => 'spend_threshold',
            'referral'        => 'referral',
            'orders_count'    => 'orders_count',
        ];
        $condition = $conditionMap[$event] ?? null;
        if (!$condition) return 0;

        $rows = $db->prepare("SELECT * FROM rewards_locked_rewards WHERE user_id = ? AND status = 'locked' AND unlock_condition = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $rows->execute([$userId, $condition]);
        $locked = $rows->fetchAll();
        $unlocked = 0;

        foreach ($locked as $row) {
            $eligible = false;
            if ($condition === 'first_order' || $condition === 'referral') $eligible = true;
            elseif ($condition === 'spend_threshold' && $eventValue >= $row['unlock_value']) $eligible = true;
            elseif ($condition === 'orders_count' && $eventValue >= $row['unlock_value']) $eligible = true;

            if ($eligible) {
                $db->prepare("UPDATE rewards_locked_rewards SET status='unlocked', unlocked_at=NOW() WHERE id=?")->execute([$row['id']]);
                rw_credit($userId, (float)$row['amount'], 'locked_unlock', 'Locked reward unlocked: ' . $row['description']);
                $db->prepare("UPDATE rewards_wallets SET locked_balance = GREATEST(0, locked_balance - ?) WHERE user_id = ?")->execute([$row['amount'], $userId]);

                rw_notify($userId, 'unlocked', '🔓 ₹' . number_format($row['amount'], 0) . ' Rewards Unlocked!',
                    'Your locked rewards of ₹' . number_format($row['amount'], 0) . ' have been added to your wallet.');
                $unlocked++;
            }
        }
        return $unlocked;
    }

    // ── Earn on purchase ─────────────────────────────────────────────────────
    function rw_earn_on_purchase(int $userId, float $orderTotal, int $orderId): void {
        $perHundred = rw_get_rule('purchase_per_100', 5.0);
        if ($perHundred <= 0) return;

        // Apply tier multiplier
        $wallet = rw_get_wallet($userId);
        $tier = rw_get_tier($wallet['tier'] ?? 'Silver');
        $multiplier = $tier ? (float)$tier['multiplier'] : 1.0;

        // Apply campaign multiplier
        $campaignMult = rw_get_active_campaign_multiplier($userId, $orderId);

        $earned = floor($orderTotal / 100) * $perHundred * $multiplier * $campaignMult;
        if ($earned <= 0) return;

        $expiry = date('Y-m-d H:i:s', strtotime('+' . (int)rw_get_rule('reward_expiry_days', 90) . ' days'));
        rw_credit($userId, $earned, 'purchase', 'Cashback on Order #' . $orderId, $orderId, $expiry);

        // Try unlocking spend-based locked rewards
        $db = get_db_connection();
        $totalSpent = (float)$db->prepare("SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE user_id = ? AND source='purchase' AND type='credit'")->execute([$userId]) ? 0 : 0;
        $st = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM rewards_transactions WHERE user_id = ? AND source='purchase' AND type='credit'");
        $st->execute([$userId]);
        $totalSpent = (float)($st->fetchColumn() ?: 0);
        rw_try_unlock_locked($userId, 'spend_threshold', $totalSpent);

        // Count orders for orders_count unlock
        $st2 = $db->prepare("SELECT COUNT(DISTINCT order_id) FROM rewards_transactions WHERE user_id = ? AND source='purchase' AND type='credit' AND order_id IS NOT NULL");
        $st2->execute([$userId]);
        $orderCount = (int)($st2->fetchColumn() ?: 0);
        rw_try_unlock_locked($userId, 'orders_count', $orderCount);
    }

    // ── First order bonus ────────────────────────────────────────────────────
    function rw_handle_first_order(int $userId, int $orderId): void {
        $bonus = rw_get_rule('first_order_reward', 50.0);
        $db = get_db_connection();
        // Check no prior purchase credit
        $st = $db->prepare("SELECT COUNT(*) FROM rewards_transactions WHERE user_id = ? AND source = 'first_order'");
        $st->execute([$userId]);
        if ((int)$st->fetchColumn() === 0 && $bonus > 0) {
            rw_credit($userId, $bonus, 'first_order', 'First Order Bonus!', $orderId);
        }
        rw_try_unlock_locked($userId, 'first_order');
    }

    // ── Signup rewards ───────────────────────────────────────────────────────
    function rw_handle_signup(int $userId, ?string $referralCode = null): void {
        $instant = rw_get_rule('signup_reward', 100.0);
        $locked1  = rw_get_rule('signup_locked_1', 300.0);
        $locked2  = rw_get_rule('signup_locked_2', 600.0);
        $locked2Threshold = 1000.0;

        $db = get_db_connection();
        $st = $db->prepare("SELECT value2 FROM rewards_rules WHERE rule_key = 'signup_locked_2' LIMIT 1");
        $st->execute();
        $row = $st->fetch();
        if ($row) $locked2Threshold = (float)$row['value2'] ?: 1000.0;

        rw_get_wallet($userId); // ensure wallet exists

        if ($instant > 0) {
            $expiry = date('Y-m-d H:i:s', strtotime('+' . (int)rw_get_rule('reward_expiry_days', 90) . ' days'));
            rw_credit($userId, $instant, 'signup', '🎁 Welcome to Gilaf! Instant cashback reward.', null, $expiry);
        }
        if ($locked1 > 0) {
            rw_add_locked($userId, $locked1, 'first_order', 0, 'signup', 'Unlock after your first order');
        }
        if ($locked2 > 0) {
            rw_add_locked($userId, $locked2, 'spend_threshold', $locked2Threshold, 'signup', "Unlock after spending ₹{$locked2Threshold}");
        }

        // Handle referral
        if ($referralCode) {
            rw_handle_referral_signup($userId, $referralCode);
        }
    }

    // ── Referral ─────────────────────────────────────────────────────────────
    function rw_handle_referral_signup(int $referredId, string $referralCode): void {
        $db = get_db_connection();
        $st = $db->prepare("SELECT user_id FROM rewards_wallets WHERE referral_code = ? LIMIT 1");
        $st->execute([$referralCode]);
        $row = $st->fetch();
        if (!$row) return;
        $referrerId = (int)$row['user_id'];
        if ($referrerId === $referredId) return;

        $referrerReward = rw_get_rule('referral_referrer', 150.0);
        $referredReward = rw_get_rule('referral_referred', 100.0);

        // Log referral
        $db->prepare("INSERT IGNORE INTO rewards_referrals (referrer_id, referred_id, referral_code, status, referrer_reward, referred_reward) VALUES (?,?,?,?,?,?)")
           ->execute([$referrerId, $referredId, $referralCode, 'pending', $referrerReward, $referredReward]);

        // Give referred user reward
        if ($referredReward > 0) {
            $expiry = date('Y-m-d H:i:s', strtotime('+' . (int)rw_get_rule('reward_expiry_days', 90) . ' days'));
            rw_credit($referredId, $referredReward, 'referral', 'Welcome referral reward from a friend!', null, $expiry);
        }
    }

    // ── Referral: complete (when referred user places first order) ────────────
    function rw_complete_referral(int $referredId, int $orderId): void {
        $db = get_db_connection();
        $st = $db->prepare("SELECT * FROM rewards_referrals WHERE referred_id = ? AND status = 'pending' LIMIT 1");
        $st->execute([$referredId]);
        $ref = $st->fetch();
        if (!$ref) return;

        $db->prepare("UPDATE rewards_referrals SET status='rewarded', order_id=? WHERE id=?")->execute([$orderId, $ref['id']]);

        if ((float)$ref['referrer_reward'] > 0) {
            rw_credit((int)$ref['referrer_id'], (float)$ref['referrer_reward'], 'referral_bonus',
                'Your referral placed their first order! 🎉', $orderId);
        }
        rw_try_unlock_locked((int)$ref['referrer_id'], 'referral');
    }

    // ── Review reward ─────────────────────────────────────────────────────────
    function rw_handle_review(int $userId, int $productId): void {
        $reward = rw_get_rule('review_reward', 25.0);
        if ($reward <= 0) return;
        $db = get_db_connection();
        // One review reward per product per user
        $st = $db->prepare("SELECT COUNT(*) FROM rewards_transactions WHERE user_id = ? AND source = 'review' AND description LIKE ?");
        $st->execute([$userId, '%product:' . $productId . '%']);
        if ((int)$st->fetchColumn() === 0) {
            rw_credit($userId, $reward, 'review', 'Product review reward — product:' . $productId);
        }
    }

    // ── Redemption: calculate max allowed ────────────────────────────────────
    function rw_get_max_redeem(int $userId, float $cartTotal, array $opts = []): array {
        $wallet = rw_get_wallet($userId);
        if (!$wallet || $wallet['is_frozen'] || $wallet['is_disabled']) {
            return ['allowed' => false, 'max' => 0, 'balance' => 0, 'reason' => 'Wallet unavailable'];
        }

        // COD restriction
        $paymentMethod = $opts['payment_method'] ?? '';
        if ($paymentMethod === 'cod' && (int)rw_get_rule('allow_wallet_on_cod', 1) === 0) {
            return ['allowed' => false, 'max' => 0, 'balance' => (float)$wallet['balance'], 'reason' => 'Wallet cannot be used with Cash on Delivery'];
        }

        // Coupon + wallet combo restriction
        $hasCoupon = (bool)($opts['has_coupon'] ?? false);
        if ($hasCoupon && (int)rw_get_rule('allow_wallet_with_coupon', 1) === 0) {
            return ['allowed' => false, 'max' => 0, 'balance' => (float)$wallet['balance'], 'reason' => 'Wallet cannot be combined with a promo code'];
        }

        $minCart    = rw_get_rule('min_cart_redeem', 200.0);
        $maxPct     = rw_get_rule('max_redeem_pct', 10.0);
        $maxFixed   = rw_get_rule('max_redeem_fixed', 0.0); // 0 = no fixed cap
        $minAmount  = rw_get_rule('min_redeem_amount', 10.0);
        $balance    = (float)$wallet['balance'];

        if ($cartTotal < $minCart) {
            return ['allowed' => false, 'max' => 0, 'balance' => $balance,
                    'reason' => 'Add ₹' . number_format($minCart - $cartTotal, 0) . ' more to use rewards'];
        }
        if ($balance < $minAmount) {
            return ['allowed' => false, 'max' => 0, 'balance' => $balance,
                    'reason' => 'Minimum ₹' . $minAmount . ' wallet balance required'];
        }

        // Tier bonus
        $tier = rw_get_tier($wallet['tier'] ?? 'Silver');
        $bonusPct = $tier ? (float)$tier['redeem_bonus_pct'] : 0;
        $effectivePct = min($maxPct + $bonusPct, 30); // hard cap 30%

        $maxByPct = floor($cartTotal * $effectivePct / 100 * 100) / 100;
        $max = min($balance, $maxByPct);

        // Apply fixed cap if configured
        if ($maxFixed > 0) {
            $max = min($max, $maxFixed);
        }

        return [
            'allowed'      => $max >= $minAmount,
            'max'          => round($max, 2),
            'balance'      => $balance,
            'min_cart'     => $minCart,
            'cart_total'   => $cartTotal,
            'reason'       => $max >= $minAmount ? '' : 'Wallet amount too low for this cart',
        ];
    }

    // ── Tier helpers ──────────────────────────────────────────────────────────
    function rw_get_tier(string $tierName): ?array {
        $db = get_db_connection();
        $st = $db->prepare("SELECT * FROM rewards_tiers WHERE tier_name = ? LIMIT 1");
        $st->execute([$tierName]);
        return $st->fetch() ?: null;
    }

    function rw_get_all_tiers(): array {
        $db = get_db_connection();
        try {
            return $db->query("SELECT * FROM rewards_tiers WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
        } catch (Exception $e) { return []; }
    }

    function rw_check_tier_upgrade(int $userId): void {
        $db = get_db_connection();
        $st = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM rewards_transactions WHERE user_id = ? AND source = 'purchase' AND type = 'credit'");
        $st->execute([$userId]);
        $totalSpent = (float)($st->fetchColumn() ?: 0);

        $tiers = rw_get_all_tiers();
        $newTier = 'Silver';
        foreach ($tiers as $t) {
            if ($totalSpent >= (float)$t['min_spent']) $newTier = $t['tier_name'];
        }

        $wallet = rw_get_wallet($userId);
        if ($wallet && $wallet['tier'] !== $newTier) {
            $db->prepare("UPDATE rewards_wallets SET tier = ? WHERE user_id = ?")->execute([$newTier, $userId]);
            rw_notify($userId, 'vip_upgrade', '🏆 VIP Upgrade — ' . $newTier . '!',
                "Congratulations! You've been upgraded to {$newTier} tier. Enjoy exclusive rewards benefits!");
        }
    }

    // ── Campaign multiplier ───────────────────────────────────────────────────
    function rw_get_active_campaign_multiplier(int $userId, ?int $orderId = null): float {
        $db = get_db_connection();
        try {
            $st = $db->query("SELECT MAX(multiplier) FROM rewards_campaigns WHERE is_active = 1 AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW()) AND campaign_type IN ('multiplier','festival')");
            $mult = (float)($st->fetchColumn() ?: 1.0);
            return max(1.0, $mult);
        } catch (Exception $e) { return 1.0; }
    }

    // ── Expiry engine ─────────────────────────────────────────────────────────
    function rw_run_expiry(): int {
        $db = get_db_connection();
        $expired = 0;
        try {
            $rows = $db->query("SELECT DISTINCT user_id, SUM(amount) as expiring FROM rewards_transactions
                WHERE type = 'credit' AND expires_at IS NOT NULL AND expires_at < NOW()
                AND id NOT IN (SELECT COALESCE(order_id,0) FROM rewards_transactions WHERE type = 'expire')
                GROUP BY user_id")->fetchAll();

            foreach ($rows as $row) {
                // Mark transactions as expired
                $db->prepare("UPDATE rewards_transactions SET type = 'expire' WHERE user_id = ? AND type = 'credit' AND expires_at IS NOT NULL AND expires_at < NOW()")->execute([$row['user_id']]);
                $amount = (float)$row['expiring'];
                if ($amount <= 0) continue;

                // Deduct balance
                $wallet = rw_get_wallet((int)$row['user_id']);
                $newBal = max(0, (float)$wallet['balance'] - $amount);
                $db->prepare("UPDATE rewards_wallets SET balance = ? WHERE user_id = ?")->execute([$newBal, $row['user_id']]);
                $db->prepare("INSERT INTO rewards_transactions (user_id, type, amount, balance_after, source, description) VALUES (?,?,?,?,?,?)")
                   ->execute([$row['user_id'], 'expire', $amount, $newBal, 'expiry', 'Rewards expired']);

                rw_notify((int)$row['user_id'], 'expiring', '⏰ Rewards Expired',
                    '₹' . number_format($amount, 0) . ' in rewards have expired from your wallet.');
                $expired++;
            }
        } catch (Exception $e) {}
        return $expired;
    }

    // ── Expiry warning (call via cron or on wallet view) ─────────────────────
    function rw_get_expiring_soon(int $userId, int $days = 7): float {
        $db = get_db_connection();
        try {
            $st = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE user_id = ? AND type = 'credit' AND expires_at IS NOT NULL AND expires_at > NOW() AND expires_at < DATE_ADD(NOW(), INTERVAL ? DAY)");
            $st->execute([$userId, $days]);
            return (float)($st->fetchColumn() ?: 0);
        } catch (Exception $e) { return 0; }
    }

    // ── Notification ──────────────────────────────────────────────────────────
    function rw_notify(int $userId, string $type, string $title, string $message): void {
        $db = get_db_connection();
        try {
            $db->prepare("INSERT INTO rewards_notifications (user_id, type, title, message) VALUES (?,?,?,?)")
               ->execute([$userId, $type, $title, $message]);
        } catch (Exception $e) {}
    }

    // ── Unread notification count ─────────────────────────────────────────────
    function rw_unread_notifications(int $userId): int {
        $db = get_db_connection();
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM rewards_notifications WHERE user_id = ? AND is_read = 0");
            $st->execute([$userId]);
            return (int)($st->fetchColumn() ?: 0);
        } catch (Exception $e) { return 0; }
    }

    // ── Earn per ₹100: label for display ─────────────────────────────────────
    function rw_earn_per_100_label(float $price, string $tierName = 'Silver'): string {
        $per100 = rw_get_rule('purchase_per_100', 5.0);
        if ($per100 <= 0) return '';
        $tier = rw_get_tier($tierName);
        $mult = $tier ? (float)$tier['multiplier'] : 1.0;
        $earn = floor($price / 100) * $per100 * $mult;
        if ($earn <= 0) return '';
        return '₹' . number_format($earn, 0);
    }

    // ── Spin Wheel: spin ─────────────────────────────────────────────────────
    function rw_spin_wheel(int $userId): array {
        $enabled = rw_get_rule('spin_wheel_enabled', 0);
        if (!$enabled) return ['error' => 'Spin wheel is disabled'];

        $db = get_db_connection();
        $everyDays = max(1, (int)rw_get_rule('spin_wheel_per_days', 1));

        $st = $db->prepare("SELECT MAX(spun_at) FROM rewards_spin_history WHERE user_id = ?");
        $st->execute([$userId]);
        $lastSpin = $st->fetchColumn();
        if ($lastSpin && strtotime($lastSpin) > strtotime("-{$everyDays} days")) {
            $nextSpin = date('M d, Y', strtotime($lastSpin . " +{$everyDays} days"));
            return ['error' => "Next spin available on {$nextSpin}"];
        }

        $segments = $db->query("SELECT * FROM rewards_spinwheel WHERE is_active = 1")->fetchAll();
        if (!$segments) return ['error' => 'No segments configured'];

        // Weighted random
        $totalWeight = array_sum(array_column($segments, 'probability'));
        $rand = mt_rand(1, $totalWeight);
        $chosen = null;
        $cumulative = 0;
        foreach ($segments as $seg) {
            $cumulative += $seg['probability'];
            if ($rand <= $cumulative) { $chosen = $seg; break; }
        }
        if (!$chosen) $chosen = $segments[0];

        // Log
        $db->prepare("INSERT INTO rewards_spin_history (user_id, spinwheel_id, reward_type, reward_value, ip_address) VALUES (?,?,?,?,?)")
           ->execute([$userId, $chosen['id'], $chosen['reward_type'], $chosen['reward_value'], $_SERVER['REMOTE_ADDR'] ?? null]);

        // Credit reward
        $rewardMsg = '';
        if ($chosen['reward_type'] === 'cashback' && $chosen['reward_value'] > 0) {
            rw_credit($userId, (float)$chosen['reward_value'], 'spin_wheel', '🎡 Spin Wheel — ' . $chosen['label']);
            $rewardMsg = 'You won ₹' . number_format($chosen['reward_value'], 0) . ' cashback!';
        } elseif ($chosen['reward_type'] === 'bonus_rewards' && $chosen['reward_value'] > 0) {
            rw_credit($userId, (float)$chosen['reward_value'], 'spin_wheel', '🎡 Spin Wheel — ' . $chosen['label']);
            $rewardMsg = 'You won ₹' . number_format($chosen['reward_value'], 0) . ' bonus rewards!';
        } elseif ($chosen['reward_type'] === 'unlock_reward' && $chosen['reward_value'] > 0) {
            rw_credit($userId, (float)$chosen['reward_value'], 'spin_wheel', '🎡 Spin Wheel — Unlocked ' . $chosen['label']);
            $rewardMsg = 'You unlocked ₹' . number_format($chosen['reward_value'], 0) . ' in rewards!';
        } elseif ($chosen['reward_type'] === 'free_shipping') {
            $_SESSION['rw_free_shipping'] = true;
            $rewardMsg = 'You won Free Shipping on your next order!';
        } else {
            $rewardMsg = 'Better luck next time! 😊';
        }

        return ['success' => true, 'segment' => $chosen, 'message' => $rewardMsg];
    }
}
