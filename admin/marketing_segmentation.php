<?php
/**
 * Marketing Customer Segmentation Engine - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

class MarketingSegmentation {
    private $db;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
    }
    
    /**
     * Get customers by spending criteria
     */
    public function getCustomersBySpending($min_amount = 0, $max_amount = null, $time_period = 'all_time') {
        $where_conditions = ["c.total_spent >= ?"];
        $params = [$min_amount];
        
        if ($max_amount !== null) {
            $where_conditions[] = "c.total_spent <= ?";
            $params[] = $max_amount;
        }
        
        if ($time_period !== 'all_time') {
            $months = intval($time_period);
            $where_conditions[] = "c.last_visit_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)";
            $params[] = $months;
        }
        
        $query = "
            SELECT c.*, lw.points, lw.tier_id, lt.name_ar as tier_name
            FROM customers c
            LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
            LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE " . implode(' AND ', $where_conditions) . "
            AND c.status = 'active'
            ORDER BY c.total_spent DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customers by loyalty tier
     */
    public function getCustomersByLoyalty($tier_name = null, $min_points = 0) {
        $where_conditions = ["lw.points >= ?"];
        $params = [$min_points];
        
        if ($tier_name) {
            $where_conditions[] = "lt.name_en = ?";
            $params[] = $tier_name;
        }
        
        $query = "
            SELECT c.*, lw.points, lw.tier_id, lt.name_ar as tier_name, lt.name_en as tier_en
            FROM customers c
            JOIN loyalty_wallets lw ON c.id = lw.customer_id
            JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE " . implode(' AND ', $where_conditions) . "
            AND c.status = 'active'
            ORDER BY lw.points DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get inactive customers
     */
    public function getInactiveCustomers($months = 3, $min_previous_visits = 1) {
        $query = "
            SELECT c.*, lw.points, lt.name_ar as tier_name,
                   DATEDIFF(NOW(), c.last_visit_date) as days_since_visit
            FROM customers c
            LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
            LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE c.last_visit_date < DATE_SUB(NOW(), INTERVAL ? MONTH)
            AND c.visit_count >= ?
            AND c.status = 'active'
            ORDER BY c.last_visit_date ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$months, $min_previous_visits]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customers by service preferences
     */
    public function getCustomersByServices($service_categories = [], $min_bookings = 1, $time_period_months = 12) {
        if (empty($service_categories)) {
            return [];
        }
        
        $category_placeholders = str_repeat('?,', count($service_categories) - 1) . '?';
        $params = array_merge($service_categories, [$time_period_months, $min_bookings]);
        
        $query = "
            SELECT c.*, COUNT(DISTINCT a.id) as service_bookings,
                   lw.points, lt.name_ar as tier_name
            FROM customers c
            LEFT JOIN appointments a ON c.id = a.customer_id
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
            LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE s.category IN ({$category_placeholders})
            AND a.start_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            AND c.status = 'active'
            GROUP BY c.id
            HAVING service_bookings >= ?
            ORDER BY service_bookings DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get new customers
     */
    public function getNewCustomers($registration_days = 30, $max_visits = 2) {
        $query = "
            SELECT c.*, lw.points, lt.name_ar as tier_name,
                   DATEDIFF(NOW(), c.created_at) as days_since_registration
            FROM customers c
            LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
            LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND c.visit_count <= ?
            AND c.status = 'active'
            ORDER BY c.created_at DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$registration_days, $max_visits]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customers with upcoming birthdays
     */
    public function getUpcomingBirthdays($days_ahead = 30) {
        $query = "
            SELECT c.*, lw.points, lt.name_ar as tier_name,
                   c.date_of_birth as birthday
            FROM customers c
            LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
            LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE c.date_of_birth IS NOT NULL
            AND (
                (DAYOFYEAR(c.date_of_birth) >= DAYOFYEAR(NOW()) 
                 AND DAYOFYEAR(c.date_of_birth) <= DAYOFYEAR(DATE_ADD(NOW(), INTERVAL ? DAY)))
                OR
                (DAYOFYEAR(c.date_of_birth) <= DAYOFYEAR(DATE_ADD(NOW(), INTERVAL ? DAY)) - 365)
            )
            AND c.status = 'active'
            ORDER BY 
                CASE 
                    WHEN DAYOFYEAR(c.date_of_birth) >= DAYOFYEAR(NOW()) 
                    THEN DAYOFYEAR(c.date_of_birth) 
                    ELSE DAYOFYEAR(c.date_of_birth) + 365 
                END
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$days_ahead, $days_ahead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Apply segment rules and get customers
     */
    public function applySegmentRules($rules) {
        $rules_array = is_string($rules) ? json_decode($rules, true) : $rules;
        
        if (!$rules_array) {
            return [];
        }
        
        // Spending-based segmentation
        if (isset($rules_array['min_total_spent'])) {
            return $this->getCustomersBySpending(
                $rules_array['min_total_spent'],
                $rules_array['max_total_spent'] ?? null,
                $rules_array['time_period'] ?? 'all_time'
            );
        }
        
        // Loyalty-based segmentation
        if (isset($rules_array['loyalty_tier']) || isset($rules_array['min_points'])) {
            return $this->getCustomersByLoyalty(
                $rules_array['loyalty_tier'] ?? null,
                $rules_array['min_points'] ?? 0
            );
        }
        
        // Inactivity-based segmentation
        if (isset($rules_array['last_visit_months'])) {
            return $this->getInactiveCustomers(
                $rules_array['last_visit_months'],
                $rules_array['min_previous_visits'] ?? 1
            );
        }
        
        // Service-based segmentation
        if (isset($rules_array['service_categories'])) {
            return $this->getCustomersByServices(
                $rules_array['service_categories'],
                $rules_array['min_bookings'] ?? 1,
                $rules_array['time_period'] ?? 12
            );
        }
        
        // New customers
        if (isset($rules_array['registration_days'])) {
            return $this->getNewCustomers(
                $rules_array['registration_days'],
                $rules_array['max_visits'] ?? 2
            );
        }
        
        // Birthday customers
        if (isset($rules_array['upcoming_birthdays'])) {
            return $this->getUpcomingBirthdays($rules_array['days_ahead'] ?? 30);
        }
        
        return [];
    }
    
    /**
     * Update segment customer count
     */
    public function updateSegmentCount($segment_id) {
        // Get segment rules
        $stmt = $this->db->prepare("SELECT rules FROM customer_segments WHERE id = ?");
        $stmt->execute([$segment_id]);
        $rules = $stmt->fetchColumn();
        
        if (!$rules) {
            return 0;
        }
        
        // Apply rules and count customers
        $customers = $this->applySegmentRules($rules);
        $count = count($customers);
        
        // We don't store the count in the database anymore
        // Just return the count for informational purposes
        return $count;
    }
    
    /**
     * Get segment statistics
     */
    public function getSegmentStats() {
        $stats = [];
        
        // Total customers
        $stmt = $this->db->query("SELECT COUNT(*) FROM customers WHERE status = 'active'");
        $stats['total_customers'] = $stmt->fetchColumn();
        
        // Segments breakdown
        $stmt = $this->db->query("
            SELECT COUNT(*) as count
            FROM customer_segments 
            WHERE is_active = 1
        ");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activity
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM campaigns 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['recent_campaigns'] = $stmt->fetchColumn();
        
        return $stats;
    }
}
?>
