<?php
/**
 * Loyalty Points Engine - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

class LoyaltyEngine {
    private $db;
    private $rules;
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
        $this->loadRules();
    }
    
    /**
     * Load loyalty rules from database
     */
    private function loadRules() {
        $stmt = $this->db->prepare("SELECT rule_key, rule_value FROM loyalty_rules WHERE is_active = 1");
        $stmt->execute();
        $rules = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $this->rules = [
            'points_per_sar' => intval($rules['points_per_sar'] ?? 1),
            'referral_points' => intval($rules['referral_points'] ?? 100),
            'google_review_points' => intval($rules['google_review_points'] ?? 50),
            'birthday_points' => intval($rules['birthday_points'] ?? 200),
            'min_purchase_for_points' => intval($rules['min_purchase_for_points'] ?? 50),
            'points_expiry_months' => intval($rules['points_expiry_months'] ?? 12),
            'tier_downgrade_months' => intval($rules['tier_downgrade_months'] ?? 3),
            'loyalty_discount_priority' => intval($rules['loyalty_discount_priority'] ?? 1),
            'birthday_auto_confirm' => intval($rules['birthday_auto_confirm'] ?? 0),
            'max_referral_per_month' => intval($rules['max_referral_per_month'] ?? 5)
        ];
    }
    
    /**
     * Award points for purchase
     */
    public function awardPurchasePoints($customer_id, $invoice_amount, $invoice_id) {
        if ($invoice_amount < $this->rules['min_purchase_for_points']) {
            return 0;
        }
        
        $points = floor($invoice_amount * $this->rules['points_per_sar']);
        
        if ($points > 0) {
            $this->addPoints($customer_id, $points, 'earn', 'purchase', $invoice_id, 
                "نقاط الشراء - فاتورة #{$invoice_id}");
            
            // Update invoice with earned points
            $stmt = $this->db->prepare("UPDATE invoices SET loyalty_points_earned = ? WHERE id = ?");
            $stmt->execute([$points, $invoice_id]);
        }
        
        return $points;
    }
    
    /**
     * Award referral points
     */
    public function awardReferralPoints($referrer_customer_id, $referred_customer_id) {
        // Check monthly referral limit
        $monthly_referrals = $this->getMonthlyReferrals($referrer_customer_id);
        if ($monthly_referrals >= $this->rules['max_referral_per_month']) {
            return false;
        }
        
        $points = $this->rules['referral_points'];
        $this->addPoints($referrer_customer_id, $points, 'earn', 'referral', $referred_customer_id,
            "نقاط الإحالة - عميل جديد");
        
        return $points;
    }
    
    /**
     * Award Google review points
     */
    public function awardGoogleReviewPoints($customer_id, $review_rating) {
        if ($review_rating < 4) {
            return 0; // Only award points for 4+ star reviews
        }
        
        $points = $this->rules['google_review_points'];
        $this->addPoints($customer_id, $points, 'earn', 'google_review', null,
            "نقاط تقييم Google - {$review_rating} نجوم");
        
        return $points;
    }
    
    /**
     * Award birthday points
     */
    public function awardBirthdayPoints($customer_id, $auto_confirmed = false) {
        // Check if birthday points already awarded this year
        $current_year = date('Y');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM loyalty_transactions 
            WHERE customer_id = ? AND earning_type = 'birthday' 
            AND YEAR(created_at) = ?
        ");
        $stmt->execute([$customer_id, $current_year]);
        
        if ($stmt->fetchColumn() > 0) {
            return 0; // Already awarded this year
        }
        
        $points = $this->rules['birthday_points'];
        $description = $auto_confirmed ? "نقاط عيد الميلاد - تلقائي" : "نقاط عيد الميلاد - مؤكد";
        
        $this->addPoints($customer_id, $points, 'earn', 'birthday', null, $description);
        
        // Mark birthday as confirmed
        $stmt = $this->db->prepare("UPDATE loyalty_wallets SET birthday_confirmed = 1 WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        
        return $points;
    }
    
    /**
     * Add points to customer wallet
     */
    private function addPoints($customer_id, $points, $transaction_type, $earning_type = null, $reference_id = null, $description = null) {
        $this->db->beginTransaction();
        
        try {
            // Create transaction record
            $stmt = $this->db->prepare("
                INSERT INTO loyalty_transactions 
                (customer_id, points, transaction_type, earning_type, reference_id, description_ar, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$customer_id, $points, $transaction_type, $earning_type, $reference_id, $description]);
            
            // Update wallet
            if ($transaction_type === 'earn') {
                $stmt = $this->db->prepare("
                    UPDATE loyalty_wallets 
                    SET points = points + ?, 
                        lifetime_points = lifetime_points + ?,
                        last_activity_at = NOW()
                    WHERE customer_id = ?
                ");
                $stmt->execute([$points, $points, $customer_id]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE loyalty_wallets 
                    SET points = GREATEST(0, points - ?),
                        last_activity_at = NOW()
                    WHERE customer_id = ?
                ");
                $stmt->execute([$points, $customer_id]);
            }
            
            // Update tier
            $this->updateCustomerTier($customer_id);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Loyalty points error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update customer tier based on points
     */
    public function updateCustomerTier($customer_id) {
        $stmt = $this->db->prepare("SELECT points FROM loyalty_wallets WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $current_points = $stmt->fetchColumn();
        
        // Get appropriate tier
        $stmt = $this->db->prepare("
            SELECT id FROM loyalty_tiers 
            WHERE min_points <= ? AND is_active = 1
            ORDER BY min_points DESC LIMIT 1
        ");
        $stmt->execute([$current_points]);
        $new_tier_id = $stmt->fetchColumn();
        
        if ($new_tier_id) {
            $stmt = $this->db->prepare("UPDATE loyalty_wallets SET tier_id = ? WHERE customer_id = ?");
            $stmt->execute([$new_tier_id, $customer_id]);
        }
    }
    
    /**
     * Apply loyalty discount to invoice
     */
    public function applyLoyaltyDiscount($customer_id, $invoice_total) {
        $stmt = $this->db->prepare("
            SELECT lw.points, lt.discount_percent 
            FROM loyalty_wallets lw
            JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE lw.customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $loyalty_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loyalty_data || $loyalty_data['discount_percent'] <= 0) {
            return 0;
        }
        
        $discount_amount = ($invoice_total * $loyalty_data['discount_percent']) / 100;
        return round($discount_amount, 2);
    }
    
    /**
     * Get customer loyalty info
     */
    public function getCustomerLoyaltyInfo($customer_id) {
        $stmt = $this->db->prepare("
            SELECT lw.*, lt.name_ar as tier_name, lt.discount_percent, lt.tier_color,
                   c.name as customer_name, c.phone as customer_phone
            FROM loyalty_wallets lw
            JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            JOIN customers c ON lw.customer_id = c.id
            WHERE lw.customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly referrals count
     */
    private function getMonthlyReferrals($customer_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM loyalty_transactions 
            WHERE customer_id = ? AND earning_type = 'referral' 
            AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
        ");
        $stmt->execute([$customer_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Process tier downgrades for inactive customers
     */
    public function processInactivityDowngrades() {
        $months = $this->rules['tier_downgrade_months'];
        
        $stmt = $this->db->prepare("
            UPDATE loyalty_wallets lw
            JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            SET lw.tier_id = 1
            WHERE lw.last_activity_at < DATE_SUB(NOW(), INTERVAL ? MONTH)
            AND lw.tier_id > 1
        ");
        $stmt->execute([$months]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Get loyalty statistics
     */
    public function getLoyaltyStats() {
        $stats = [];
        
        // Total active customers
        $stmt = $this->db->query("SELECT COUNT(*) FROM loyalty_wallets");
        $stats['total_customers'] = $stmt->fetchColumn();
        
        // Customers by tier
        $stmt = $this->db->query("
            SELECT lt.name_ar, COUNT(*) as count
            FROM loyalty_wallets lw
            JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            GROUP BY lt.id, lt.name_ar
            ORDER BY lt.sort_order
        ");
        $stats['by_tier'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Points distributed this month
        $stmt = $this->db->query("
            SELECT SUM(points) FROM loyalty_transactions 
            WHERE transaction_type = 'earn' 
            AND MONTH(created_at) = MONTH(NOW()) 
            AND YEAR(created_at) = YEAR(NOW())
        ");
        $stats['monthly_points'] = $stmt->fetchColumn() ?? 0;
        
        return $stats;
    }
}
?>
