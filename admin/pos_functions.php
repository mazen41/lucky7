<?php
require_once '../config/database.php';

if (!function_exists('findOrCreateCustomerByName')) {
    /**
     * Finds a customer by name, or creates a new one if not found.
     * Name is not a reliable unique key, so this is for basic tracking.
     *
     * @param PDO $db The database connection object.
     * @param string $name The customer's name.
     * @param int $created_by The ID of the admin creating the customer.
     * @return string|null The customer's ID or null if name is empty.
     */
    function findOrCreateCustomerByName($db, $name, $created_by) {
        // If name is empty, it's a guest/walk-in customer, so we don't create a record.
        if (empty(trim($name))) {
            return null;
        }

        // Try to find an existing customer with the same name.
        // This is a simple match and may not be unique.
        $stmt = $db->prepare("SELECT id FROM customers WHERE name_ar = :name OR name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $customerId = $stmt->fetchColumn();

        if ($customerId) {
            return $customerId;
        } else {
            // Create a new customer record. Phone number is omitted.
            $customer_code = 'CUS' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO customers (customer_code, name_ar, status, created_at, created_by) VALUES (?, ?, 'active', NOW(), ?)");
            $stmt->execute([$customer_code, $name, $created_by]);
            return $db->lastInsertId();
        }
    }
}


if (!function_exists('processLoyaltyPoints')) {
    /**
     * Processes loyalty points for a customer after a purchase.
     *
     * @param PDO $db The database connection object.
     * @param int $customerId The ID of the customer.
     * @param float $totalAmount The total amount of the transaction.
     * @param int $referenceId The ID of the invoice or reference transaction.
     * @param string $referenceType The type of the reference (e.g., 'invoice').
     * @return void
     */
    function processLoyaltyPoints($db, $customerId, $totalAmount, $referenceId, $referenceType = 'invoice') {
        if (!$customerId || $totalAmount <= 0) {
            return;
        }
        try {
            // Get current loyalty rules
            $rules_stmt = $db->query("SELECT * FROM loyalty_rules ORDER BY id DESC LIMIT 1");
            $rules = $rules_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rules) return;

            $points_per_sar = $rules['points_per_sar'] ?? 1;
            $min_purchase_for_points = $rules['min_purchase_for_points'] ?? 0;

            if ($totalAmount < $min_purchase_for_points) {
                return;
            }

            $earned_points = floor($totalAmount * $points_per_sar);
            if ($earned_points <= 0) {
                return;
            }

            // Create loyalty transaction
            $trans_stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, points, transaction_type, earning_type, reference_id, reference_type, description_ar, created_at) VALUES (?, ?, 'earn', 'purchase', ?, ?, 'نقاط من عملية شراء', NOW())");
            $trans_stmt->execute([$customerId, $earned_points, $referenceId, $referenceType]);

            // Update or create loyalty wallet
            $wallet_stmt = $db->prepare("SELECT * FROM loyalty_wallets WHERE customer_id = ?");
            $wallet_stmt->execute([$customerId]);
            $wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);

            if ($wallet) {
                $update_wallet_stmt = $db->prepare("UPDATE loyalty_wallets SET points = points + ?, lifetime_points = lifetime_points + ?, last_activity_at = NOW() WHERE customer_id = ?");
                $update_wallet_stmt->execute([$earned_points, $earned_points, $customerId]);
            } else {
                $create_wallet_stmt = $db->prepare("INSERT INTO loyalty_wallets (customer_id, points, lifetime_points, last_activity_at) VALUES (?, ?, ?, NOW())");
                $create_wallet_stmt->execute([$customerId, $earned_points, $earned_points]);
            }

            // Check for tier upgrade
            $current_lifetime_points = ($wallet['lifetime_points'] ?? 0) + $earned_points;
            $tiers_stmt = $db->query("SELECT id, min_points FROM loyalty_tiers ORDER BY min_points DESC");
            $tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);
            $new_tier_id = $wallet['tier_id'] ?? null;
            
            foreach ($tiers as $tier) {
                if ($current_lifetime_points >= $tier['min_points']) {
                    $new_tier_id = $tier['id'];
                    break;
                }
            }
            
            if ($new_tier_id && $new_tier_id != ($wallet['tier_id'] ?? null)) {
                $update_tier_stmt = $db->prepare("UPDATE loyalty_wallets SET tier_id = ? WHERE customer_id = ?");
                $update_tier_stmt->execute([$new_tier_id, $customerId]);
            }

        } catch (Exception $e) {
            error_log("Loyalty Points Error: " . $e->getMessage());
            // Do not re-throw the exception to avoid breaking the main transaction
        }
    }
}

// --- RETAINED FROM ORIGINAL FILE ---
if (!function_exists('updateSpecialistPerformance')) {
    /**
     * Calculates and updates the performance record for a single specialist for a given month.
     * This is designed to be called in real-time after an invoice is created.
     *
     * @param PDO $db The database connection object.
     * @param int $specialist_id The ID of the specialist to update.
     * @param string $month The month to calculate for (format: 'YYYY-MM').
     * @return void
     */
    function updateSpecialistPerformance($db, $specialist_id, $month) {
        if (!$specialist_id || !$month) {
            return;
        }

        try {
            // Get specialist's base salary
            $spec_stmt = $db->prepare("SELECT salary FROM team_members WHERE id = ?");
            $spec_stmt->execute([$specialist_id]);
            $base_salary = $spec_stmt->fetchColumn() ?? 0.00;

            // Calculate total individual revenue for the month
            $revenue_query = "
                SELECT COALESCE(SUM(ii.unit_price * ii.quantity), 0) as revenue_sum 
                FROM invoice_items ii 
                JOIN invoices i ON ii.invoice_id = i.id 
                WHERE ii.specialist_id = ? 
                AND DATE_FORMAT(i.created_at, '%Y-%m') = ? 
                AND i.status = 'paid'
            ";
            $rev_stmt = $db->prepare($revenue_query);
            $rev_stmt->execute([$specialist_id, $month]);
            $revenue_sum = $rev_stmt->fetchColumn();
            
            // Specialist Bonus Calculation (5x Salary rule)
            $bonus_earned = 0.00;
            $revenue_threshold = $base_salary * 5;
            if ($base_salary > 0 && $revenue_sum > $revenue_threshold) {
                $surplus_revenue = $revenue_sum - $revenue_threshold;
                $bonus_earned = $surplus_revenue * 0.05; // 5% of the surplus
            }

            // Also get the total service count for the month
            $sc_stmt = $db->prepare("
                SELECT COUNT(ii.id) 
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                WHERE ii.specialist_id = ? 
                AND DATE_FORMAT(i.created_at, '%Y-%m') = ?
                AND i.status = 'paid'
            ");
            $sc_stmt->execute([$specialist_id, $month]);
            $service_count = $sc_stmt->fetchColumn();

            // Insert or update the performance record
            // Note: Attendance fields are not updated here; they are handled by the main calculator script.
            $upsert_query = "
                INSERT INTO hr_performance (employee_id, month, service_count, revenue_sum, bonus_earned, calculated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    service_count = VALUES(service_count),
                    revenue_sum = VALUES(revenue_sum),
                    bonus_earned = VALUES(bonus_earned),
                    calculated_at = NOW()
            ";
            
            $upsert_stmt = $db->prepare($upsert_query);
            $upsert_stmt->execute([$specialist_id, $month, $service_count, $revenue_sum, $bonus_earned]);

        } catch (Exception $e) {
            // Log the error but don't break the main POS transaction
            error_log("Real-time performance update failed for specialist {$specialist_id}: " . $e->getMessage());
        }
    }
}
?>