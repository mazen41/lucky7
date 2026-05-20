<?php
/**
 * InventoryManager Class
 * Handles stock counts, movements tracking, and inventory operations
 * 
 * @author MAI Salon Development Team
 * @version 1.0
 */

class InventoryManager {
    private $db;
    private $productManager;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        if (!$this->db) {
            throw new Exception('فشل الاتصال بقاعدة البيانات');
        }
        
        require_once 'ProductManager.php';
        $this->productManager = new ProductManager();
    }
    
    /**
     * Create new stock count session
     */
    public function createStockCount($title, $description = null, $location = null, $countDate = null, $createdBy) {
        try {
            $this->db->beginTransaction();
            
            // Generate reference number
            $reference = $this->generateStockCountReference();
            
            if (!$countDate) {
                $countDate = date('Y-m-d');
            }
            
            $query = "INSERT INTO stock_counts (
                reference, title, description, location, count_date, created_by
            ) VALUES (
                :reference, :title, :description, :location, :count_date, :created_by
            )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':reference', $reference);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':count_date', $countDate);
            $stmt->bindParam(':created_by', $createdBy);
            
            if (!$stmt->execute()) {
                throw new Exception('فشل في إنشاء جلسة الجرد');
            }
            
            $countId = $this->db->lastInsertId();
            
            // Add all active products to count items
            $this->addProductsToStockCount($countId, $location);
            
            $this->db->commit();
            return $countId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Add products to stock count
     */
    private function addProductsToStockCount($countId, $location = null) {
        $whereClause = "WHERE p.is_active = TRUE AND p.track_stock = TRUE";
        $params = [':count_id' => $countId];
        
        if ($location) {
            $whereClause .= " AND p.location = :location";
            $params[':location'] = $location;
        }
        
        $query = "INSERT INTO stock_count_items (count_id, product_id, system_qty, unit_cost)
                  SELECT :count_id, p.id, p.qty, p.cost_price
                  FROM products p
                  $whereClause";
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('فشل في إضافة المنتجات لجلسة الجرد');
        }
        
        // Update total items count
        $updateQuery = "UPDATE stock_counts 
                        SET total_items = (
                            SELECT COUNT(*) FROM stock_count_items WHERE count_id = :count_id
                        )
                        WHERE id = :count_id";
        
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->bindParam(':count_id', $countId);
        $updateStmt->execute();
    }
    
    /**
     * Start stock count (change status to in_progress)
     */
    public function startStockCount($countId, $userId) {
        try {
            $query = "UPDATE stock_counts 
                      SET status = 'in_progress', started_at = CURRENT_TIMESTAMP
                      WHERE id = :id AND status = 'draft'";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $countId);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في بدء الجرد: ' . $e->getMessage());
        }
    }
    
    /**
     * Update counted quantity for a product in stock count
     */
    public function updateCountedQuantity($countId, $productId, $countedQty, $note = null, $countedBy) {
        try {
            // Get stock count info
            $countInfo = $this->getStockCountById($countId);
            if (!$countInfo || $countInfo['status'] !== 'in_progress') {
                throw new Exception('جلسة الجرد غير متاحة للتحديث');
            }
            
            // Get current item info
            $query = "SELECT * FROM stock_count_items 
                      WHERE count_id = :count_id AND product_id = :product_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':count_id', $countId);
            $stmt->bindParam(':product_id', $productId);
            $stmt->execute();
            
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new Exception('المنتج غير موجود في جلسة الجرد');
            }
            
            // Calculate variance value
            $varianceQty = $countedQty - $item['system_qty'];
            $varianceValue = $varianceQty * ($item['unit_cost'] ?? 0);
            
            // Update the count item
            $updateQuery = "UPDATE stock_count_items 
                           SET counted_qty = :counted_qty,
                               variance_value = :variance_value,
                               note = :note,
                               counted_at = CURRENT_TIMESTAMP,
                               counted_by = :counted_by
                           WHERE count_id = :count_id AND product_id = :product_id";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':counted_qty', $countedQty);
            $updateStmt->bindParam(':variance_value', $varianceValue);
            $updateStmt->bindParam(':note', $note);
            $updateStmt->bindParam(':counted_by', $countedBy);
            $updateStmt->bindParam(':count_id', $countId);
            $updateStmt->bindParam(':product_id', $productId);
            
            if (!$updateStmt->execute()) {
                throw new Exception('فشل في تحديث الكمية المعدودة');
            }
            
            // Update stock count summary
            $this->updateStockCountSummary($countId);
            
            return true;
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في تحديث الكمية: ' . $e->getMessage());
        }
    }
    
    /**
     * Complete stock count and apply adjustments
     */
    public function completeStockCount($countId, $completedBy, $applyAdjustments = true) {
        try {
            $this->db->beginTransaction();
            
            // Get stock count info
            $countInfo = $this->getStockCountById($countId);
            if (!$countInfo || $countInfo['status'] !== 'in_progress') {
                throw new Exception('جلسة الجرد غير متاحة للإكمال');
            }
            
            // Get all count items with variances
            $query = "SELECT sci.*, p.name_ar as product_name
                      FROM stock_count_items sci
                      JOIN products p ON sci.product_id = p.id
                      WHERE sci.count_id = :count_id 
                        AND sci.counted_qty IS NOT NULL
                        AND sci.variance_qty != 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':count_id', $countId);
            $stmt->execute();
            
            $varianceItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($applyAdjustments) {
                // Apply stock adjustments for variances
                foreach ($varianceItems as $item) {
                    $adjustmentNote = "تسوية جرد - {$countInfo['reference']} - {$item['product_name']}";
                    if ($item['note']) {
                        $adjustmentNote .= " - " . $item['note'];
                    }
                    
                    $this->productManager->addStockMovement(
                        $item['product_id'],
                        'stock_count',
                        $item['variance_qty'],
                        $item['unit_cost'],
                        'stock_count',
                        $countId,
                        $adjustmentNote,
                        $completedBy
                    );
                }
            }
            
            // Update stock count status
            $updateQuery = "UPDATE stock_counts 
                           SET status = 'completed',
                               completed_at = CURRENT_TIMESTAMP,
                               completed_by = :completed_by
                           WHERE id = :id";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':completed_by', $completedBy);
            $updateStmt->bindParam(':id', $countId);
            
            if (!$updateStmt->execute()) {
                throw new Exception('فشل في إكمال الجرد');
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get stock count by ID
     */
    public function getStockCountById($countId) {
        try {
            $query = "SELECT sc.*, 
                             u1.name as created_by_name,
                             u2.name as completed_by_name
                      FROM stock_counts sc
                      LEFT JOIN users u1 ON sc.created_by = u1.id
                      LEFT JOIN users u2 ON sc.completed_by = u2.id
                      WHERE sc.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $countId);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد بيانات الجرد: ' . $e->getMessage());
        }
    }
    
    /**
     * Get stock count items
     */
    public function getStockCountItems($countId, $filters = []) {
        try {
            $where = ['sci.count_id = :count_id'];
            $params = [':count_id' => $countId];
            
            if (!empty($filters['search'])) {
                $where[] = "(p.name LIKE :search OR p.name_ar LIKE :search OR p.sku LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['variance_only'])) {
                $where[] = "sci.variance_qty != 0";
            }
            
            if (!empty($filters['uncounted_only'])) {
                $where[] = "sci.counted_qty IS NULL";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "SELECT sci.*, 
                             p.sku, p.name, p.name_ar, p.unit,
                             pc.name_ar as category_name_ar,
                             u.name as counted_by_name
                      FROM stock_count_items sci
                      JOIN products p ON sci.product_id = p.id
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      LEFT JOIN users u ON sci.counted_by = u.id
                      WHERE $whereClause
                      ORDER BY p.name_ar ASC";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد عناصر الجرد: ' . $e->getMessage());
        }
    }
    
    /**
     * Get stock counts list
     */
    public function getStockCounts($filters = [], $limit = 50, $offset = 0) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['status'])) {
                $where[] = "sc.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['location'])) {
                $where[] = "sc.location = :location";
                $params[':location'] = $filters['location'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "sc.count_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "sc.count_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "SELECT sc.*, 
                             u1.name as created_by_name,
                             u2.name as completed_by_name
                      FROM stock_counts sc
                      LEFT JOIN users u1 ON sc.created_by = u1.id
                      LEFT JOIN users u2 ON sc.completed_by = u2.id
                      WHERE $whereClause
                      ORDER BY sc.created_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد قائمة الجرد: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate low stock alerts
     */
    public function generateLowStockAlerts() {
        try {
            // Clear existing alerts
            $this->db->exec("DELETE FROM low_stock_alerts WHERE is_acknowledged = FALSE");
            
            // Generate new alerts
            $query = "INSERT INTO low_stock_alerts (product_id, current_qty, min_qty, alert_level)
                      SELECT 
                          p.id,
                          p.qty,
                          p.min_qty,
                          CASE 
                              WHEN p.qty <= 0 THEN 'out_of_stock'
                              WHEN p.qty <= p.min_qty THEN 'critical'
                              WHEN p.qty <= (p.min_qty * 1.5) THEN 'warning'
                          END as alert_level
                      FROM products p
                      WHERE p.is_active = TRUE 
                        AND p.track_stock = TRUE
                        AND p.qty <= (p.min_qty * 1.5)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في توليد تنبيهات المخزون: ' . $e->getMessage());
        }
    }
    
    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts($acknowledged = false) {
        try {
            $query = "SELECT lsa.*, 
                             p.sku, p.name_ar, p.unit,
                             pc.name_ar as category_name_ar,
                             u.name as acknowledged_by_name
                      FROM low_stock_alerts lsa
                      JOIN products p ON lsa.product_id = p.id
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      LEFT JOIN users u ON lsa.acknowledged_by = u.id
                      WHERE lsa.is_acknowledged = :acknowledged
                      ORDER BY 
                          CASE lsa.alert_level 
                              WHEN 'out_of_stock' THEN 1 
                              WHEN 'critical' THEN 2 
                              WHEN 'warning' THEN 3 
                          END,
                          lsa.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':acknowledged', $acknowledged, PDO::PARAM_BOOL);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد تنبيهات المخزون: ' . $e->getMessage());
        }
    }
    
    /**
     * Acknowledge low stock alert
     */
    public function acknowledgeLowStockAlert($alertId, $userId) {
        try {
            $query = "UPDATE low_stock_alerts 
                      SET is_acknowledged = TRUE,
                          acknowledged_by = :user_id,
                          acknowledged_at = CURRENT_TIMESTAMP
                      WHERE id = :alert_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':alert_id', $alertId);
            $stmt->bindParam(':user_id', $userId);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في تأكيد التنبيه: ' . $e->getMessage());
        }
    }
    
    /**
     * Update stock count summary statistics
     */
    private function updateStockCountSummary($countId) {
        $query = "UPDATE stock_counts sc
                  SET items_counted = (
                      SELECT COUNT(*) FROM stock_count_items 
                      WHERE count_id = sc.id AND counted_qty IS NOT NULL
                  ),
                  total_variance = (
                      SELECT COALESCE(SUM(variance_value), 0) FROM stock_count_items 
                      WHERE count_id = sc.id AND counted_qty IS NOT NULL
                  )
                  WHERE sc.id = :count_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':count_id', $countId);
        $stmt->execute();
    }
    
    /**
     * Generate stock count reference number
     */
    private function generateStockCountReference() {
        $year = date('Y');
        $month = date('m');
        
        // Get next sequence number for this month
        $query = "SELECT COUNT(*) + 1 as next_seq 
                  FROM stock_counts 
                  WHERE YEAR(created_at) = :year AND MONTH(created_at) = :month";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = str_pad($result['next_seq'], 3, '0', STR_PAD_LEFT);
        
        return "SC-{$year}{$month}-{$sequence}";
    }
    
    /**
     * Cancel stock count
     */
    public function cancelStockCount($countId, $reason = null) {
        try {
            $query = "UPDATE stock_counts 
                      SET status = 'cancelled',
                          description = CONCAT(COALESCE(description, ''), ' - ملغي: ', COALESCE(:reason, 'بدون سبب'))
                      WHERE id = :id AND status IN ('draft', 'in_progress')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $countId);
            $stmt->bindParam(':reason', $reason);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في إلغاء الجرد: ' . $e->getMessage());
        }
    }
    
    /**
     * Get inventory movement report
     */
    public function getMovementReport($dateFrom, $dateTo, $productId = null, $type = null) {
        try {
            $where = ['DATE(sm.created_at) BETWEEN :date_from AND :date_to'];
            $params = [
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo
            ];
            
            if ($productId) {
                $where[] = 'sm.product_id = :product_id';
                $params[':product_id'] = $productId;
            }
            
            if ($type) {
                $where[] = 'sm.type = :type';
                $params[':type'] = $type;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "SELECT sm.*, 
                             p.sku, p.name_ar as product_name,
                             u.name as created_by_name
                      FROM stock_movements sm
                      JOIN products p ON sm.product_id = p.id
                      LEFT JOIN users u ON sm.created_by = u.id
                      WHERE $whereClause
                      ORDER BY sm.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في تقرير حركات المخزون: ' . $e->getMessage());
        }
    }
}
?>
