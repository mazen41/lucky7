<?php
// shared_functions.php
// This file holds functions needed by both the POS page and its actions handler.

if (!function_exists('findOrCreateCustomerByPhone')) {
    /**
     * Finds a customer by phone number or creates a new one if not found.
     *
     * @param PDO $db The database connection object.
     * @param string $name The customer's name.
     * @param string $phone The customer's phone number.
     * @param string|null $email The customer's email (optional).
     * @return string|null The customer's ID.
     */
    function findOrCreateCustomerByPhone($db, $name, $phone, $email = null) {
        if (empty($phone)) {
            return null;
        }
        $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        $customerId = $stmt->fetchColumn();

        if ($customerId) {
            return $customerId;
        } else {
            $stmt = $db->prepare("INSERT INTO customers (name, phone, email, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
            $stmt->execute([$name, $phone, $email]);
            return $db->lastInsertId();
        }
    }
}

if (!function_exists('processLoyaltyPoints')) {
    /**
     * Processes and awards loyalty points for a purchase.
     *
     * @param PDO $db The database connection object.
     * @param int $customerId The customer's ID.
     * @param float $totalAmount The total amount of the purchase.
     * @param int $referenceId The ID of the invoice or transaction.
     * @param string $referenceType The type of reference (e.g., 'invoice').
     * @return void
     */
    function processLoyaltyPoints($db, $customerId, $totalAmount, $referenceId, $referenceType = 'invoice') {
        if (!$customerId || $totalAmount <= 0) {
            return;
        }
        try {
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

            $trans_stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, points, transaction_type, earning_type, reference_id, reference_type, description_ar, created_at) VALUES (?, ?, 'earn', 'purchase', ?, ?, 'نقاط من عملية شراء', NOW())");
            $trans_stmt->execute([$customerId, $earned_points, $referenceId, $referenceType]);

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
            // Do not re-throw, just log the error to avoid breaking the main transaction.
        }
    }
}

?>