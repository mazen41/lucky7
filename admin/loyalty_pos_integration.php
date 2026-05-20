<?php
/**
 * Loyalty POS Integration - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

require_once '../config/database.php';
require_once 'loyalty_engine.php';

class LoyaltyPOSIntegration {
    private $db;
    private $loyalty;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->loyalty = new LoyaltyEngine($this->db);
    }
    
    /**
     * Process invoice for loyalty points and discounts
     */
    public function processInvoiceLoyalty($invoice_id, $customer_id, $invoice_total, $apply_discount = true) {
        $this->db->beginTransaction();
        
        try {
            $result = [
                'discount_applied' => 0,
                'points_earned' => 0,
                'new_tier' => null,
                'success' => false
            ];
            
            // 1. Apply loyalty discount if enabled
            if ($apply_discount && $customer_id) {
                $discount = $this->loyalty->applyLoyaltyDiscount($customer_id, $invoice_total);
                if ($discount > 0) {
                    $result['discount_applied'] = $discount;
                    
                    // Update invoice with discount
                    $stmt = $this->db->prepare("
                        UPDATE invoices 
                        SET loyalty_discount_applied = ?, 
                            total_amount = total_amount - ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$discount, $discount, $invoice_id]);
                }
            }
            
            // 2. Award purchase points (on final amount after discount)
            if ($customer_id) {
                $final_amount = $invoice_total - $result['discount_applied'];
                $points = $this->loyalty->awardPurchasePoints($customer_id, $final_amount, $invoice_id);
                $result['points_earned'] = $points;
                
                // Get updated customer tier
                $customer_info = $this->loyalty->getCustomerLoyaltyInfo($customer_id);
                $result['new_tier'] = $customer_info['tier_name'] ?? null;
            }
            
            $this->db->commit();
            $result['success'] = true;
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Loyalty POS integration error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get customer loyalty preview for POS
     */
    public function getCustomerLoyaltyPreview($customer_id, $invoice_total) {
        $customer_info = $this->loyalty->getCustomerLoyaltyInfo($customer_id);
        if (!$customer_info) {
            return null;
        }
        
        $discount = $this->loyalty->applyLoyaltyDiscount($customer_id, $invoice_total);
        $potential_points = floor($invoice_total * 1); // Assuming 1 point per SAR
        
        return [
            'customer_name' => $customer_info['customer_name'],
            'current_points' => $customer_info['points'],
            'current_tier' => $customer_info['tier_name'],
            'tier_color' => $customer_info['tier_color'],
            'discount_percent' => $customer_info['discount_percent'],
            'discount_amount' => $discount,
            'points_to_earn' => $potential_points,
            'final_total' => $invoice_total - $discount
        ];
    }
    
    /**
     * Manual loyalty discount application (for POS interface)
     */
    public function applyManualLoyaltyDiscount($customer_id, $invoice_total) {
        return $this->loyalty->applyLoyaltyDiscount($customer_id, $invoice_total);
    }
}

// API endpoint for POS integration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    header('Content-Type: application/json');
    
    $pos_loyalty = new LoyaltyPOSIntegration();
    
    switch ($_POST['api_action']) {
        case 'get_customer_loyalty':
            $customer_id = intval($_POST['customer_id']);
            $invoice_total = floatval($_POST['invoice_total']);
            
            $preview = $pos_loyalty->getCustomerLoyaltyPreview($customer_id, $invoice_total);
            echo json_encode(['success' => true, 'data' => $preview]);
            break;
            
        case 'apply_loyalty_discount':
            $customer_id = intval($_POST['customer_id']);
            $invoice_total = floatval($_POST['invoice_total']);
            
            $discount = $pos_loyalty->applyManualLoyaltyDiscount($customer_id, $invoice_total);
            echo json_encode(['success' => true, 'discount' => $discount]);
            break;
            
        case 'process_invoice_loyalty':
            $invoice_id = intval($_POST['invoice_id']);
            $customer_id = intval($_POST['customer_id']);
            $invoice_total = floatval($_POST['invoice_total']);
            $apply_discount = isset($_POST['apply_discount']) && $_POST['apply_discount'] === 'true';
            
            $result = $pos_loyalty->processInvoiceLoyalty($invoice_id, $customer_id, $invoice_total, $apply_discount);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}
?>
