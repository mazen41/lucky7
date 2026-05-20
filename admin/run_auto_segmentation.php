<?php
/**
 * Run Automatic Customer Segmentation
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "🤖 Running automatic customer segmentation...\n\n";
    
    // Clear existing automatic assignments
    $db->exec("DELETE FROM customer_segment_assignments WHERE is_automatic = 1");
    echo "🗑️ Cleared existing automatic assignments\n";
    
    $assignments_made = 0;
    
    // Get all automatic segments
    $segments_stmt = $db->prepare("SELECT * FROM customer_segments WHERE is_automatic = 1 AND is_active = 1");
    $segments_stmt->execute();
    $segments = $segments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Found " . count($segments) . " automatic segments\n\n";
    
    foreach ($segments as $segment) {
        echo "Processing: " . $segment['name_ar'] . "\n";
        
        $criteria = json_decode($segment['criteria'], true);
        if (!$criteria) {
            echo "⚠️ No criteria found for segment\n\n";
            continue;
        }
        
        $where_conditions = [];
        $params = [];
        
        foreach ($criteria as $field => $condition) {
            switch ($field) {
                case 'total_spent':
                    if ($condition[0] === '>=') {
                        $where_conditions[] = "c.total_spent >= ?";
                        $params[] = $condition[1];
                        echo "  - Total spent >= " . $condition[1] . "\n";
                    }
                    break;
                    
                case 'visit_count':
                    if ($condition[0] === '>=') {
                        $where_conditions[] = "c.visit_count >= ?";
                        $params[] = $condition[1];
                        echo "  - Visit count >= " . $condition[1] . "\n";
                    } elseif ($condition[0] === '<=') {
                        $where_conditions[] = "c.visit_count <= ?";
                        $params[] = $condition[1];
                        echo "  - Visit count <= " . $condition[1] . "\n";
                    }
                    break;
                    
                case 'skin_type':
                    if ($condition[0] === '=') {
                        $where_conditions[] = "c.skin_type = ?";
                        $params[] = $condition[1];
                        echo "  - Skin type = " . $condition[1] . "\n";
                    }
                    break;
                    
                case 'city':
                    if ($condition[0] === '=') {
                        $where_conditions[] = "c.city = ?";
                        $params[] = $condition[1];
                        echo "  - City = " . $condition[1] . "\n";
                    }
                    break;
                    
                case 'created_at':
                    if ($condition[0] === '>=' && $condition[1] === '30 days ago') {
                        $where_conditions[] = "c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                        echo "  - Created within last 30 days\n";
                    }
                    break;
                    
                case 'last_visit_date':
                    if ($condition[0] === '>=' && $condition[1] === '60 days ago') {
                        $where_conditions[] = "c.last_visit_date >= DATE_SUB(NOW(), INTERVAL 60 DAY)";
                        echo "  - Last visit within 60 days\n";
                    } elseif ($condition[0] === '<' && $condition[1] === '90 days ago') {
                        $where_conditions[] = "(c.last_visit_date < DATE_SUB(NOW(), INTERVAL 90 DAY) OR c.last_visit_date IS NULL)";
                        echo "  - Last visit more than 90 days ago or never\n";
                    }
                    break;
                    
                case 'date_of_birth':
                    if ($condition[0] === 'month' && $condition[1] === 'current') {
                        $where_conditions[] = "MONTH(c.date_of_birth) = MONTH(NOW())";
                        echo "  - Birthday this month\n";
                    }
                    break;
            }
        }
        
        if (!empty($where_conditions)) {
            $where_clause = implode(' AND ', $where_conditions);
            $query = "SELECT c.id, c.name FROM customers c WHERE c.status = 'active' AND ($where_clause)";
            
            $customers_stmt = $db->prepare($query);
            $customers_stmt->execute($params);
            $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "  Found " . count($customers) . " matching customers\n";
            
            // Assign customers to segment
            if (!empty($customers)) {
                $assign_stmt = $db->prepare("
                    INSERT IGNORE INTO customer_segment_assignments (customer_id, segment_id, is_automatic, assigned_by) 
                    VALUES (?, ?, 1, 1)
                ");
                
                foreach ($customers as $customer) {
                    $assign_stmt->execute([$customer['id'], $segment['id']]);
                    $assignments_made++;
                    echo "    ✅ Assigned: " . $customer['name'] . "\n";
                }
            }
        }
        echo "\n";
    }
    
    // Update segment customer counts
    $db->exec("
        UPDATE customer_segments cs 
        SET customer_count = (
            SELECT COUNT(*) 
            FROM customer_segment_assignments csa 
            WHERE csa.segment_id = cs.id
        )
    ");
    
    echo "📈 Updated segment customer counts\n";
    echo "🎉 Automatic segmentation completed!\n";
    echo "📊 Total assignments made: $assignments_made\n";
    echo "🔗 View results at: /admin/customer_segments.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
