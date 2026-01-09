<?php
/**
 * Direct database insert test to identify the exact issue
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "=== Direct Database Insert Test ===\n\n";

// Test 1: Direct INSERT without AnalyticsTracker
echo "Test 1: Direct INSERT query...\n";

$testData = [
    'visitor_id' => 'test_visitor_' . time(),
    'user_id' => null,
    'product_id' => 1,
    'event_type' => 'click',
    'event_source' => 'direct_test',
    'category_id' => null,
    'product_price' => 100.00,
    'quantity' => 1,
    'session_id' => 'test_session_' . time(),
    'page_url' => '/test'
];

$query = "INSERT INTO analytics_product_events 
         (visitor_id, user_id, product_id, event_type, event_source, 
          category_id, product_price, quantity, session_id, page_url, event_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo "❌ Prepare failed: " . $conn->error . "\n";
    exit;
}

$stmt->bind_param('siissidiss',
    $testData['visitor_id'],
    $testData['user_id'],
    $testData['product_id'],
    $testData['event_type'],
    $testData['event_source'],
    $testData['category_id'],
    $testData['product_price'],
    $testData['quantity'],
    $testData['session_id'],
    $testData['page_url']
);

if ($stmt->execute()) {
    echo "✅ Direct INSERT successful!\n";
    echo "Inserted ID: " . $stmt->insert_id . "\n";
} else {
    echo "❌ Execute failed: " . $stmt->error . "\n";
}
$stmt->close();

// Test 2: Verify the insert
echo "\nTest 2: Verifying insert...\n";
$check = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_source = 'direct_test'");
if ($check) {
    $result = $check->fetch_assoc();
    echo "Records with event_source='direct_test': {$result['count']}\n";
}

// Test 3: Using AnalyticsTracker class
echo "\nTest 3: Using AnalyticsTracker class...\n";
try {
    require_once __DIR__ . '/includes/analytics_tracker.php';
    
    // Create tracker instance
    $tracker = new AnalyticsTracker($conn);
    echo "✅ AnalyticsTracker instantiated\n";
    echo "Visitor ID: " . $tracker->getVisitorId() . "\n";
    echo "Session ID: " . $tracker->getSessionId() . "\n";
    
    // Try to track an event
    $result = $tracker->trackProductEvent(2, 'click', 'class_test', null, 200.00, 1);
    if ($result) {
        echo "✅ trackProductEvent returned true\n";
    } else {
        echo "❌ trackProductEvent returned false\n";
    }
    
    // Verify
    $check2 = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_source = 'class_test'");
    if ($check2) {
        $result2 = $check2->fetch_assoc();
        echo "Records with event_source='class_test': {$result2['count']}\n";
        if ($result2['count'] > 0) {
            echo "✅ Class method IS working!\n";
        } else {
            echo "❌ Class method NOT inserting data\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

// Test 4: Check error log
echo "\nTest 4: Checking PHP error log...\n";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    echo "Error log location: $errorLog\n";
    $lastLines = array_slice(file($errorLog), -10);
    echo "Last 10 lines:\n";
    echo implode("", $lastLines);
} else {
    echo "Error log not found or not configured\n";
}

$conn->close();
?>
