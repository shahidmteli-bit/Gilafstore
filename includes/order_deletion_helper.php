<?php
/**
 * Order Deletion Helper
 * Handles complete cascade deletion of orders with all linked financial records
 */

/**
 * Delete order with complete cascade removal of all linked records
 * 
 * @param int $orderId Order ID to delete
 * @return array Result with success status and message
 */
function delete_order_cascade(int $orderId): array
{
    $db = get_db_connection();
    
    // Fetch order details before deletion
    $order = db_fetch('SELECT * FROM sales_orders WHERE id = ?', [$orderId]);
    
    if (!$order) {
        return [
            'success' => false,
            'message' => 'Order not found.'
        ];
    }
    
    $partyId = (int)$order['party_id'];
    $orderNumber = $order['order_number'];
    
    try {
        $db->beginTransaction();
        
        // Step 1: Delete all payment history entries linked to this order
        $paymentHistoryCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_payment_history WHERE order_id = ?', [$orderId]);
        db_query('DELETE FROM sales_payment_history WHERE order_id = ?', [$orderId]);
        
        // Step 2: Delete order items
        db_query('DELETE FROM sales_order_items WHERE order_id = ?', [$orderId]);
        
        // Step 3: Delete the order itself
        db_query('DELETE FROM sales_orders WHERE id = ?', [$orderId]);
        
        $db->commit();
        
        // Step 5: Recalculate party outstanding in real-time
        recalculate_party_outstanding($partyId);
        
        return [
            'success' => true,
            'message' => "Order {$orderNumber} deleted successfully. Removed {$paymentHistoryCount['cnt']} payment record(s).",
            'party_id' => $partyId,
            'deleted_payments' => (int)$paymentHistoryCount['cnt']
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [
            'success' => false,
            'message' => 'Failed to delete order: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete payment collection with complete cascade removal
 * Can be called before or after admin confirmation
 * 
 * @param int $collectionId Collection ID to delete
 * @return array Result with success status and message
 */
function delete_collection_cascade(int $collectionId): array
{
    $db = get_db_connection();
    
    // Fetch collection details before deletion
    $collection = db_fetch('SELECT * FROM sales_collections WHERE id = ?', [$collectionId]);
    
    if (!$collection) {
        return [
            'success' => false,
            'message' => 'Collection not found.'
        ];
    }
    
    $partyId = (int)$collection['party_id'];
    $collectionNumber = $collection['collection_number'];
    $amount = (float)$collection['amount'];
    $status = $collection['status'];
    
    try {
        $db->beginTransaction();
        
        // Step 1: If collection was confirmed, we need to reverse the payment allocation
        if ($status === 'confirmed') {
            // Find all payment history entries created by this collection
            $paymentEntries = db_fetch_all(
                'SELECT * FROM sales_payment_history 
                 WHERE reference_number LIKE ? OR notes LIKE ?',
                ['%' . $collectionNumber . '%', '%' . $collectionNumber . '%']
            );
            
            // Reverse the payment allocation on each affected order
            foreach ($paymentEntries as $entry) {
                if ($entry['order_id']) {
                    $order = db_fetch('SELECT * FROM sales_orders WHERE id = ?', [$entry['order_id']]);
                    if ($order) {
                        $newPaymentAmount = max(0, (float)$order['payment_amount'] - (float)$entry['amount']);
                        $newPaymentStatus = $newPaymentAmount >= (float)$order['total_amount'] ? 'received' : 
                                          ($newPaymentAmount > 0 ? 'partial' : 'pending');
                        
                        db_query(
                            'UPDATE sales_orders SET payment_amount = ?, payment_status = ? WHERE id = ?',
                            [$newPaymentAmount, $newPaymentStatus, $entry['order_id']]
                        );
                    }
                }
            }
            
            // Delete all payment history entries created by this collection
            db_query(
                'DELETE FROM sales_payment_history 
                 WHERE reference_number LIKE ? OR notes LIKE ?',
                ['%' . $collectionNumber . '%', '%' . $collectionNumber . '%']
            );
        }
        
        // Step 2: Delete the collection record itself
        db_query('DELETE FROM sales_collections WHERE id = ?', [$collectionId]);
        
        $db->commit();
        
        // Step 3: Recalculate party outstanding in real-time
        recalculate_party_outstanding($partyId);
        
        return [
            'success' => true,
            'message' => "Collection {$collectionNumber} (₹" . number_format($amount, 2) . ") deleted successfully. All payment allocations reversed.",
            'party_id' => $partyId,
            'amount' => $amount,
            'was_confirmed' => $status === 'confirmed'
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [
            'success' => false,
            'message' => 'Failed to delete collection: ' . $e->getMessage()
        ];
    }
}

/**
 * Recalculate party outstanding with precision handling
 * Fixes rounding issues like ₹1 residual balances
 * 
 * @param int $partyId Party ID
 * @return float Calculated outstanding amount
 */
function recalculate_party_outstanding_precise(int $partyId): float
{
    // Use DECIMAL precision in SQL to avoid floating point errors
    $orderRow = db_fetch(
        'SELECT CAST(COALESCE(SUM(total_amount - COALESCE(payment_amount, 0)), 0) AS DECIMAL(10,2)) as outstanding 
         FROM sales_orders
         WHERE party_id = ? 
         AND status IN ("approved","dispatched","delivered")
         AND order_type = "new_order" 
         AND payment_status != "received"',
        [$partyId]
    );
    
    $outstanding = (float)($orderRow['outstanding'] ?? 0);
    
    // Round to 2 decimal places and treat values < 0.01 as zero
    $outstanding = round($outstanding, 2);
    if (abs($outstanding) < 0.01) {
        $outstanding = 0;
    }
    
    db_query('UPDATE sales_parties SET outstanding_amount = ? WHERE id = ?', [$outstanding, $partyId]);
    return $outstanding;
}
