<?php

function admin_get_monthly_sales_data(int $year): array
{
    // Initialize array with 0 for all 12 months
    $monthlyData = array_fill(1, 12, 0.0);
    
    try {
        $db = get_db_connection();
        // Check if created_at exists (it should)
        // Check if order_status exists, if not try status
        $statusCol = 'order_status';
        try {
            $check = $db->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
            if ($check->rowCount() == 0) {
                $statusCol = 'status';
            }
        } catch (Exception $e) {
            $statusCol = 'status';
        }

        $sql = "SELECT MONTH(created_at) as month, SUM(total_amount) as total 
                FROM orders 
                WHERE YEAR(created_at) = ? AND ($statusCol = 'completed' OR $statusCol = 'delivered' OR $statusCol = 'shipped')
                GROUP BY MONTH(created_at)";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$year]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $monthlyData[(int)$row['month']] = (float)$row['total'];
        }
    } catch (PDOException $e) {
        // Fallback for missing columns or table issues
        error_log("Dashboard Error (Sales): " . $e->getMessage());
    }
    
    return array_values($monthlyData);
}

function admin_get_order_status_counts(): array
{
    try {
        $db = get_db_connection();
        $statusCol = 'order_status';
        try {
            $check = $db->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
            if ($check->rowCount() == 0) {
                $statusCol = 'status';
            }
        } catch (Exception $e) {
            $statusCol = 'status';
        }

        $stmt = $db->query("SELECT $statusCol as status, COUNT(*) as count FROM orders GROUP BY $statusCol");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = [];
        foreach ($results as $row) {
            // Normalize status key to lowercase
            $status = strtolower($row['status'] ?? 'unknown');
            $counts[$status] = (int)$row['count'];
        }
        return $counts;
    } catch (PDOException $e) {
        error_log("Dashboard Error (Status): " . $e->getMessage());
        return [];
    }
}

function admin_get_top_selling_products(int $limit = 5): array
{
    try {
        $db = get_db_connection();
        
        // Check for order_status vs status
        $statusCol = 'order_status';
        try {
            $check = $db->query("SHOW COLUMNS FROM orders LIKE 'order_status'");
            if ($check->rowCount() == 0) {
                $statusCol = 'status';
            }
        } catch (Exception $e) {
            $statusCol = 'status';
        }

        $sql = "SELECT p.id, p.name, p.image, p.price, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as total_revenue
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            JOIN orders o ON o.id = oi.order_id
            WHERE o.$statusCol != 'cancelled'
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT " . (int)$limit;
            
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Dashboard Error (Top Products): " . $e->getMessage());
        return [];
    }
}

function admin_get_low_stock_products(int $threshold = 10, int $limit = 5): array
{
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM products WHERE stock <= ? ORDER BY stock ASC LIMIT " . (int)$limit);
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Dashboard Error (Low Stock): " . $e->getMessage());
        return [];
    }
}
