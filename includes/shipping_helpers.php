<?php
/**
 * Shared Shipping Helper Functions
 * Used by admin/ajax/shipping_api.php and admin_actions.php
 */

// ─── Encryption helpers (AES-256-CBC) ───
if (!function_exists('sp_get_key')) {
    function sp_get_key() {
        $keyFile = dirname(__DIR__) . '/.gilaf_security_key';
        if (file_exists($keyFile)) return trim(file_get_contents($keyFile));
        return hash('sha256', 'gilaf_shipping_' . DB_NAME . '_secret_2026', true);
    }
}
if (!function_exists('sp_decrypt')) {
    function sp_decrypt($enc) {
        if (empty($enc)) return '';
        $key = sp_get_key(); $data = base64_decode($enc);
        $parts = explode('::', $data, 2);
        if (count($parts) !== 2) return '';
        return openssl_decrypt($parts[1], 'AES-256-CBC', $key, 0, $parts[0]);
    }
}
if (!function_exists('sp_encrypt')) {
    function sp_encrypt($plain) {
        if (empty($plain)) return '';
        $key = sp_get_key(); $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $cipher);
    }
}

// ─── Normalize Indian phone to 10 digits ───
if (!function_exists('sr_normalize_phone')) {
    function sr_normalize_phone($phone) {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        // Remove +91 or 91 prefix
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') $digits = substr($digits, 2);
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') $digits = substr($digits, 1);
        // Must be 10 digits
        if (strlen($digits) !== 10) return '0000000000';
        return $digits;
    }
}

// ─── Shiprocket cURL Helper ───
if (!function_exists('shiprocket_request')) {
    function shiprocket_request($baseUrl, $endpoint, $token, $data = null, $method = 'POST') {
        $url = rtrim($baseUrl, '/') . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => json_decode($response, true), 'raw' => $response, 'error' => $error];
    }
}

// ─── Get Shiprocket auth token (stored or fresh login) ───
if (!function_exists('shiprocket_get_token')) {
    function shiprocket_get_token($db, $partner) {
        $baseUrl = $partner['is_sandbox'] ? ($partner['sandbox_url'] ?: $partner['base_url']) : ($partner['base_url'] ?: $partner['sandbox_url']);
        $email = sp_decrypt($partner['api_key_enc']);
        $password = sp_decrypt($partner['api_secret_enc']);

        if (empty($email) || empty($password)) return ['token' => null, 'error' => 'Email or password not configured'];

        // Try stored token first
        $extra = json_decode($partner['extra_fields'] ?? '{}', true);
        if (!empty($extra['auth_token'])) {
            $storedToken = sp_decrypt($extra['auth_token']);
            if (!empty($storedToken)) {
                $check = shiprocket_request($baseUrl, '/v1/external/settings/company', $storedToken, null, 'GET');
                if ($check['code'] === 200) {
                    return ['token' => $storedToken, 'base_url' => $baseUrl];
                }
            }
        }

        // Fresh login
        $url = rtrim($baseUrl, '/') . '/v1/external/auth/login';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
            CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);

        if ($code === 200 && !empty($data['token'])) {
            try {
                $tokenEnc = sp_encrypt($data['token']);
                $db->prepare("UPDATE shipping_partners SET extra_fields = JSON_SET(COALESCE(extra_fields, '{}'), '$.auth_token', ?) WHERE id = ?")->execute([$tokenEnc, $partner['id']]);
            } catch (Exception $e) { /* non-critical */ }
            return ['token' => $data['token'], 'base_url' => $baseUrl];
        }

        return ['token' => null, 'error' => 'Auth failed (HTTP ' . $code . '): ' . ($data['message'] ?? $resp)];
    }
}

// ─── Full Shiprocket shipment creation (create order → AWB → pickup → label) ───
if (!function_exists('shiprocket_create_full_shipment')) {
    function shiprocket_create_full_shipment($db, $partner, $orderId, $adminId = null) {
        $result = ['success' => false, 'message' => '', 'awb_code' => '', 'courier_name' => '', 'sr_order_id' => '', 'shipment_id' => '', 'label_url' => ''];

        // Step 1: Auth
        $auth = shiprocket_get_token($db, $partner);
        if (empty($auth['token'])) {
            $result['message'] = 'Shiprocket auth failed: ' . ($auth['error'] ?? 'Unknown');
            return $result;
        }
        $token = $auth['token'];
        $baseUrl = $auth['base_url'];

        // Get order
        $stmt = $db->prepare("SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) { $result['message'] = 'Order not found'; return $result; }

        // Get items
        $itemStmt = $db->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse shipping address
        $addr = ['name' => '', 'address' => '', 'city' => '', 'state' => '', 'pincode' => '', 'phone' => '', 'email' => ''];
        if (!empty($order['shipping_address'])) {
            $ad = json_decode($order['shipping_address'], true);
            if (is_array($ad)) {
                $addr = [
                    'name' => $ad['name'] ?? $ad['full_name'] ?? $order['customer_name'],
                    'address' => trim(($ad['address_line1'] ?? '') . ' ' . ($ad['address_line2'] ?? '')),
                    'city' => $ad['city'] ?? '',
                    'state' => $ad['state'] ?? '',
                    'pincode' => $ad['pincode'] ?? ($ad['zip_code'] ?? ''),
                    'phone' => $ad['phone'] ?? $order['customer_phone'],
                    'email' => $order['customer_email'] ?? '',
                ];
            }
        }
        // Fallback to user_addresses
        if (empty($addr['address'])) {
            $addrStmt = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1");
            $addrStmt->execute([$order['user_id']]);
            $ua = $addrStmt->fetch(PDO::FETCH_ASSOC);
            if ($ua) {
                $addr = [
                    'name' => $order['customer_name'] ?? 'Customer',
                    'address' => implode(', ', array_filter([$ua['flat_number'] ?? '', $ua['address_line1'] ?? '', $ua['address_line2'] ?? ''])),
                    'city' => $ua['city'] ?? '',
                    'state' => $ua['state'] ?? '',
                    'pincode' => $ua['zip_code'] ?? ($ua['pincode'] ?? ''),
                    'phone' => $ua['phone'] ?? $order['customer_phone'],
                    'email' => $order['customer_email'] ?? '',
                ];
            }
        }
        // Final fallback: use customer data from users table
        if (empty($addr['name']))  $addr['name']  = $order['customer_name'] ?? 'Customer';
        if (empty($addr['phone'])) $addr['phone'] = $order['customer_phone'] ?? '';
        if (empty($addr['email'])) $addr['email'] = $order['customer_email'] ?? '';

        // Build order items + calculate package dimensions from product data
        $srItems = [];
        $maxL = 20; $maxW = 15; $maxH = 10; $totalWeight = 0;
        foreach ($items as $item) {
            // Fetch product shipping data
            $prodStmt = $db->prepare("SELECT hsn_code, shipping_length, shipping_width, shipping_height, shipping_weight FROM products WHERE id = ?");
            $prodStmt->execute([$item['product_id']]);
            $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);

            $srItems[] = [
                'name' => $item['product_name'] ?? ('Item #' . $item['product_id']),
                'sku' => $item['sku'] ?? ('SKU-' . ($item['product_id'] ?? '0')),
                'units' => (int)$item['quantity'],
                'selling_price' => (float)$item['price'],
                'discount' => '',
                'tax' => '',
                'hsn' => $prod['hsn_code'] ?? '',
            ];

            // Aggregate dimensions: max of each, sum weight
            if (!empty($prod['shipping_length']) && $prod['shipping_length'] > $maxL) $maxL = (float)$prod['shipping_length'];
            if (!empty($prod['shipping_width'])  && $prod['shipping_width']  > $maxW) $maxW = (float)$prod['shipping_width'];
            if (!empty($prod['shipping_height']) && $prod['shipping_height'] > $maxH) $maxH = (float)$prod['shipping_height'];
            $totalWeight += ((float)($prod['shipping_weight'] ?? 0.5)) * (int)$item['quantity'];
        }
        if ($totalWeight < 0.5) $totalWeight = 0.5;

        $paymentMethod = strtolower($order['payment_method'] ?? '');
        $isCOD = (strpos($paymentMethod, 'cod') !== false || strpos($paymentMethod, 'cash') !== false);

        $nameParts = explode(' ', trim($addr['name']), 2);
        $firstName = $nameParts[0] ?: 'Customer';
        $lastName = $nameParts[1] ?? '';

        // Step 2: Fetch pickup location from Shiprocket
        $pickupLocName = 'Primary'; // default fallback
        $pickupResp = shiprocket_request($baseUrl, '/v1/external/settings/company/pickup', $token, null, 'GET');
        if ($pickupResp['code'] === 200 && !empty($pickupResp['body']['data']['shipping_address'])) {
            $pickupLocName = $pickupResp['body']['data']['shipping_address'][0]['pickup_location'] ?? 'Primary';
        }
        error_log("SHIPROCKET PICKUP LOCATION [Order #{$orderId}]: '{$pickupLocName}'");

        // Create order on Shiprocket
        $orderPayload = [
            'order_id' => 'GILAF-' . $orderId,
            'order_date' => date('Y-m-d H:i', strtotime($order['created_at'])),
            'pickup_location' => $pickupLocName,
            'billing_customer_name' => $firstName,
            'billing_last_name' => $lastName,
            'billing_address' => $addr['address'] ?: 'NA',
            'billing_address_2' => '',
            'billing_city' => $addr['city'] ?: 'NA',
            'billing_pincode' => $addr['pincode'] ?: '000000',
            'billing_state' => $addr['state'] ?: 'NA',
            'billing_country' => 'India',
            'billing_email' => $addr['email'] ?: 'customer@example.com',
            'billing_phone' => sr_normalize_phone($addr['phone'] ?: '0000000000'),
            'shipping_is_billing' => true,
            'order_items' => $srItems,
            'payment_method' => $isCOD ? 'COD' : 'Prepaid',
            'sub_total' => (float)$order['total_amount'],
            'length' => $maxL,
            'breadth' => $maxW,
            'height' => $maxH,
            'weight' => round($totalWeight, 2),
        ];

        $createResp = shiprocket_request($baseUrl, '/v1/external/orders/create/adhoc', $token, $orderPayload);
        error_log("SHIPROCKET CREATE ORDER [Order #{$orderId}] HTTP {$createResp['code']}: " . substr($createResp['raw'] ?? '', 0, 2000));

        if ($createResp['code'] < 200 || $createResp['code'] >= 300 || empty($createResp['body']['shipment_id'])) {
            $errMsg = $createResp['body']['message'] ?? ($createResp['body']['errors'] ?? $createResp['raw']);
            if (is_array($errMsg)) $errMsg = json_encode($errMsg);
            $result['message'] = 'Shiprocket create order failed (HTTP ' . $createResp['code'] . '): ' . $errMsg;
            return $result;
        }

        $srOrderId = $createResp['body']['order_id'] ?? '';
        $srShipmentId = $createResp['body']['shipment_id'] ?? '';
        error_log("SHIPROCKET ORDER CREATED [Order #{$orderId}] SR Order: {$srOrderId}, Shipment: {$srShipmentId}");

        // Step 3: Assign AWB
        $awbResp = shiprocket_request($baseUrl, '/v1/external/courier/assign/awb', $token, [
            'shipment_id' => (int)$srShipmentId,
        ]);
        error_log("SHIPROCKET ASSIGN AWB [Order #{$orderId}] HTTP {$awbResp['code']}: " . substr($awbResp['raw'] ?? '', 0, 2000));

        $awbCode = '';
        $courierName = '';
        if ($awbResp['code'] >= 200 && $awbResp['code'] < 300) {
            $body = $awbResp['body'] ?? [];
            // Shiprocket nests AWB data in different paths depending on response
            $awbData = $body['response']['data'] ?? $body['data'] ?? $body ?? [];
            $awbCode = $awbData['awb_code'] ?? ($body['awb_code'] ?? '');
            $courierName = $awbData['courier_name'] ?? ($body['courier_name'] ?? '');
            // Also check awb_assign_status
            $assignStatus = $body['awb_assign_status'] ?? ($body['response']['data']['awb_assign_status'] ?? null);
            error_log("SHIPROCKET AWB PARSED [Order #{$orderId}] AWB: '{$awbCode}', Courier: '{$courierName}', assign_status: " . json_encode($assignStatus));
        } else {
            error_log("SHIPROCKET AWB FAILED [Order #{$orderId}] HTTP {$awbResp['code']}: " . ($awbResp['raw'] ?? ''));
        }

        // Step 4: Request pickup
        shiprocket_request($baseUrl, '/v1/external/courier/generate/pickup', $token, [
            'shipment_id' => [$srShipmentId],
        ]);

        // Step 5: Get label URL
        $labelUrl = '';
        $labelResp = shiprocket_request($baseUrl, '/v1/external/courier/generate/label', $token, [
            'shipment_id' => [$srShipmentId],
        ]);
        if ($labelResp['code'] >= 200 && $labelResp['code'] < 300) {
            $labelUrl = $labelResp['body']['label_url'] ?? '';
        }

        // Save to order_shipments
        try {
            $stmt = $db->prepare("INSERT INTO order_shipments (order_id, shipping_type, shipping_partner, shipping_partner_code, api_shipment_id, api_order_id, awb_or_tracking, shipping_status, label_url, created_by_admin_id, notes) VALUES (?, 'api', ?, ?, ?, ?, ?, 'shipped', ?, ?, ?)");
            $stmt->execute([
                $orderId,
                $courierName ?: $partner['partner_name'],
                $partner['partner_code'],
                (string)$srShipmentId,
                (string)$srOrderId,
                $awbCode ?: null,
                $labelUrl ?: null,
                $adminId,
                "Shiprocket Order #{$srOrderId}, Shipment #{$srShipmentId}" . ($awbCode ? ", AWB: {$awbCode}" : ''),
            ]);
        } catch (Exception $e) {
            error_log("WARNING: Failed to save order_shipment for order #{$orderId}: " . $e->getMessage());
        }

        // Update orders table with tracking info
        if (!empty($awbCode)) {
            $db->prepare("UPDATE orders SET courier_company = ?, tracking_id = ? WHERE id = ?")
               ->execute([$courierName ?: 'ShipRocket', $awbCode, $orderId]);
        }

        $result['success'] = true;
        $result['message'] = 'Shiprocket shipment created successfully!';
        $result['awb_code'] = $awbCode;
        $result['courier_name'] = $courierName ?: $partner['partner_name'];
        $result['sr_order_id'] = $srOrderId;
        $result['shipment_id'] = $srShipmentId;
        $result['label_url'] = $labelUrl;
        return $result;
    }
}
