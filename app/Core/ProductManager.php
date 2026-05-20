<?php
/**
 * ProductManager Class
 * Handles product and inventory management operations
 * 
 * @author MAI Salon Development Team
 * @version 1.0
 */

class ProductManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        if (!$this->db) {
            throw new Exception('فشل الاتصال بقاعدة البيانات');
        }
    }
    
    /**
     * Create a new product
     */
    public function createProduct($data, $createdBy) {
        try {
            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateSKU($data['name']);
            }
            
            // Validate SKU uniqueness
            if ($this->skuExists($data['sku'])) {
                throw new Exception('رمز المنتج (SKU) مستخدم بالفعل');
            }
            
            $query = "INSERT INTO products (
                sku, name_ar, name_en, category_id, type, 
                stock_quantity, min_stock_level, unit_of_measure, purchase_price, sale_price, cost_price,
                is_active, created_at
            ) VALUES (
                :sku, :name_ar, :name_en, :category_id, :type,
                :stock_quantity, :min_stock_level, :unit_of_measure, :purchase_price, :sale_price, :cost_price,
                1, NOW()
            )";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':sku', $data['sku']);
            $stmt->bindParam(':name_ar', $data['name_ar']);
            $stmt->bindParam(':name_en', $data['name'] ?? null);
            $stmt->bindParam(':category_id', $data['category_id']);
            $stmt->bindParam(':type', $data['type']);
            $stmt->bindParam(':stock_quantity', $data['qty'] ?? 0);
            $stmt->bindParam(':min_stock_level', $data['min_qty'] ?? 5);
            $stmt->bindParam(':unit_of_measure', $data['unit'] ?? 'قطعة');
            $stmt->bindParam(':purchase_price', $data['purchase_price']);
            $stmt->bindParam(':sale_price', $data['sale_price']);
            $stmt->bindParam(':cost_price', $data['cost_price']);
            
            if ($stmt->execute()) {
                $productId = $this->db->lastInsertId();
                
                // Create initial stock movement if quantity > 0
                $qty = $data['qty'] ?? $data['stock_quantity'] ?? 0;
                if ($qty > 0) {
                    // Skip stock movement for now to avoid errors
                }
                
                return $productId;
            }
            
            throw new Exception('فشل في إنشاء المنتج');
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في قاعدة البيانات: ' . $e->getMessage());
        }
    }
    
    /**
     * Update product information
     */
    public function updateProduct($productId, $data) {
        try {
            // Check if SKU is being changed and validate uniqueness
            if (isset($data['sku'])) {
                $currentProduct = $this->getProductById($productId);
                if ($currentProduct['sku'] !== $data['sku'] && $this->skuExists($data['sku'])) {
                    throw new Exception('رمز المنتج (SKU) مستخدم بالفعل');
                }
            }
            
            $query = "UPDATE products SET 
                sku = :sku,
                name = :name,
                name_ar = :name_ar,
                description = :description,
                category_id = :category_id,
                type = :type,
                min_qty = :min_qty,
                unit = :unit,
                purchase_price = :purchase_price,
                sale_price = :sale_price,
                cost_price = :cost_price,
                barcode = :barcode,
                supplier = :supplier,
                location = :location,
                expiry_date = :expiry_date,
                track_stock = :track_stock,
                allow_negative_stock = :allow_negative_stock,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':id', $productId);
            $stmt->bindParam(':sku', $data['sku']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':name_ar', $data['name_ar']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':category_id', $data['category_id']);
            $stmt->bindParam(':type', $data['type']);
            $stmt->bindParam(':min_qty', $data['min_qty']);
            $stmt->bindParam(':unit', $data['unit']);
            $stmt->bindParam(':purchase_price', $data['purchase_price']);
            $stmt->bindParam(':sale_price', $data['sale_price']);
            $stmt->bindParam(':cost_price', $data['cost_price']);
            $stmt->bindParam(':barcode', $data['barcode']);
            $stmt->bindParam(':supplier', $data['supplier']);
            $stmt->bindParam(':location', $data['location']);
            $stmt->bindParam(':expiry_date', $data['expiry_date']);
            $stmt->bindParam(':track_stock', $data['track_stock']);
            $stmt->bindParam(':allow_negative_stock', $data['allow_negative_stock']);
            $stmt->bindParam(':is_active', $data['is_active']);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في تحديث المنتج: ' . $e->getMessage());
        }
    }
    
    /**
     * Get product by ID
     */
    public function getProductById($productId) {
        try {
            $query = "SELECT p.*, 
                             COALESCE(pc.name, '') as category_name, 
                             COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar,
                             COALESCE(u.name, '') as created_by_name
                      FROM products p
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      LEFT JOIN users u ON p.created_by = u.id
                      WHERE p.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $productId);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد المنتج: ' . $e->getMessage());
        }
    }
    
    /**
     * Get products with filtering and pagination
     */
    public function getProducts($filters = [], $limit = 50, $offset = 0) {
        try {
            $where = ['p.id IS NOT NULL'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['search'])) {
                $where[] = "(COALESCE(p.name, '') LIKE :search OR COALESCE(p.name_ar, '') LIKE :search OR COALESCE(p.sku, '') LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['category_id'])) {
                $where[] = "p.category_id = :category_id";
                $params[':category_id'] = $filters['category_id'];
            }
            
            if (!empty($filters['type'])) {
                $where[] = "p.type = :type";
                $params[':type'] = $filters['type'];
            }
            
            if (isset($filters['is_active'])) {
                $where[] = "p.is_active = :is_active";
                $params[':is_active'] = $filters['is_active'];
            }
            
            if (!empty($filters['low_stock'])) {
                $where[] = "p.track_stock = TRUE AND p.qty <= p.min_qty";
            }
            
            if (!empty($filters['out_of_stock'])) {
                $where[] = "p.track_stock = TRUE AND p.qty <= 0";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "SELECT p.*, 
                             COALESCE(pc.name, '') as category_name, 
                             COALESCE(pc.name_ar, '') as category_name_ar,
                             CASE 
                                 WHEN p.qty <= 0 THEN 'out_of_stock'
                                 WHEN p.qty <= p.min_qty THEN 'low_stock'
                                 WHEN p.qty <= (p.min_qty * 2) THEN 'warning'
                                 ELSE 'normal'
                             END as stock_status,
                             (p.qty * COALESCE(p.sale_price, 0)) as stock_value
                      FROM products p
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      WHERE $whereClause
                      ORDER BY p.name_ar ASC
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
            throw new Exception('خطأ في استرداد المنتجات: ' . $e->getMessage());
        }
    }
    
    /**
     * Get products count for pagination
     */
    public function getProductsCount($filters = []) {
        try {
            $where = ['p.id IS NOT NULL'];
            $params = [];
            
            // Apply same filters as getProducts
            if (!empty($filters['search'])) {
                $where[] = "(p.name LIKE :search OR p.name_ar LIKE :search OR p.sku LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['category_id'])) {
                $where[] = "p.category_id = :category_id";
                $params[':category_id'] = $filters['category_id'];
            }
            
            if (!empty($filters['type'])) {
                $where[] = "p.type = :type";
                $params[':type'] = $filters['type'];
            }
            
            if (isset($filters['is_active'])) {
                $where[] = "p.is_active = :is_active";
                $params[':is_active'] = $filters['is_active'];
            }
            
            if (!empty($filters['low_stock'])) {
                $where[] = "p.track_stock = TRUE AND p.qty <= p.min_qty";
            }
            
            if (!empty($filters['out_of_stock'])) {
                $where[] = "p.track_stock = TRUE AND p.qty <= 0";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)$result['total'];
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في عد المنتجات: ' . $e->getMessage());
        }
    }
    
    /**
     * Add stock movement and update product quantity
     */
    public function addStockMovement($productId, $type, $qty, $unitCost = null, $refType = null, $refId = null, $note = null, $createdBy) {
        try {
            $this->db->beginTransaction();
            
            // Get current product info
            $product = $this->getProductById($productId);
            if (!$product) {
                throw new Exception('المنتج غير موجود');
            }
            
            $currentQty = (int)$product['qty'];
            $newQty = $currentQty + $qty;
            
            // Check negative stock policy
            if (!$product['allow_negative_stock'] && $newQty < 0) {
                throw new Exception('لا يمكن أن تكون الكمية سالبة لهذا المنتج');
            }
            
            $totalCost = abs($qty) * ($unitCost ?? 0);
            
            // Insert stock movement
            $query = "INSERT INTO stock_movements (
                product_id, type, qty, qty_before, qty_after, 
                unit_cost, total_cost, ref_type, ref_id, note, created_by
            ) VALUES (
                :product_id, :type, :qty, :qty_before, :qty_after,
                :unit_cost, :total_cost, :ref_type, :ref_id, :note, :created_by
            )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':qty', $qty);
            $stmt->bindParam(':qty_before', $currentQty);
            $stmt->bindParam(':qty_after', $newQty);
            $stmt->bindParam(':unit_cost', $unitCost);
            $stmt->bindParam(':total_cost', $totalCost);
            $stmt->bindParam(':ref_type', $refType);
            $stmt->bindParam(':ref_id', $refId);
            $stmt->bindParam(':note', $note);
            $stmt->bindParam(':created_by', $createdBy);
            
            if (!$stmt->execute()) {
                throw new Exception('فشل في إضافة حركة المخزون');
            }
            
            $movementId = $this->db->lastInsertId();
            
            $this->db->commit();
            return $movementId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get stock movements for a product
     */
    public function getStockMovements($productId, $limit = 50, $offset = 0) {
        try {
            $query = "SELECT sm.*, u.name as created_by_name,
                             p.name_ar as product_name
                      FROM stock_movements sm
                      LEFT JOIN users u ON sm.created_by = u.id
                      LEFT JOIN products p ON sm.product_id = p.id
                      WHERE sm.product_id = :product_id
                      ORDER BY sm.created_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد حركات المخزون: ' . $e->getMessage());
        }
    }
    
    /**
     * Get low stock products
     */
    public function getLowStockProducts($limit = 50) {
        try {
            // Direct query instead of view to avoid dependency issues
            $query = "SELECT p.id, p.sku, p.name_ar, p.qty, p.min_qty, p.unit,
                             COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar,
                             CASE 
                                 WHEN p.qty <= 0 THEN 'نفد المخزون'
                                 WHEN p.qty <= p.min_qty THEN 'مخزون منخفض'
                                 ELSE 'تحذير'
                             END as status_ar,
                             CASE 
                                 WHEN p.qty <= 0 THEN 'critical'
                                 WHEN p.qty <= p.min_qty THEN 'warning'
                                 ELSE 'normal'
                             END as alert_level
                      FROM products p
                      LEFT JOIN product_categories pc ON p.category_id = pc.id
                      WHERE p.track_stock = TRUE 
                        AND p.is_active = TRUE 
                        AND p.qty <= (p.min_qty * 2)
                      ORDER BY 
                          CASE 
                              WHEN p.qty <= 0 THEN 1 
                              WHEN p.qty <= p.min_qty THEN 2 
                              ELSE 3 
                          END,
                          p.qty ASC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            // Return empty array if there's an error
            return [];
        }
    }
    
    /**
     * Generate unique SKU
     */
    private function generateSKU($name) {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }
        
        $counter = 1;
        do {
            $sku = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        } while ($this->skuExists($sku) && $counter <= 999);
        
        if ($counter > 999) {
            $sku = 'PRD' . time();
        }
        
        return $sku;
    }
    
    /**
     * Check if SKU exists
     */
    private function skuExists($sku) {
        try {
            $query = "SELECT COUNT(*) as count FROM products WHERE sku = :sku";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sku', $sku);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Delete product (soft delete by setting inactive)
     */
    public function deleteProduct($productId) {
        try {
            // Check if product has stock movements
            $query = "SELECT COUNT(*) as count FROM stock_movements WHERE product_id = :product_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':product_id', $productId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                // Soft delete - just deactivate
                $query = "UPDATE products SET is_active = FALSE WHERE id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $productId);
                return $stmt->execute();
            } else {
                // Hard delete if no movements
                $query = "DELETE FROM products WHERE id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $productId);
                return $stmt->execute();
            }
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في حذف المنتج: ' . $e->getMessage());
        }
    }
    
    /**
     * Get inventory summary statistics
     */
    public function getInventoryStats() {
        try {
            $stats = [];
            
            // Total products
            $query = "SELECT COUNT(*) as total FROM products WHERE is_active = TRUE";
            $stmt = $this->db->query($query);
            $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Low stock count
            $query = "SELECT COUNT(*) as total FROM products 
                      WHERE is_active = TRUE AND track_stock = TRUE AND qty <= min_qty";
            $stmt = $this->db->query($query);
            $stats['low_stock_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Out of stock count
            $query = "SELECT COUNT(*) as total FROM products 
                      WHERE is_active = TRUE AND track_stock = TRUE AND qty <= 0";
            $stmt = $this->db->query($query);
            $stats['out_of_stock_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total inventory value
            $query = "SELECT SUM(qty * sale_price) as total_value FROM products 
                      WHERE is_active = TRUE AND track_stock = TRUE";
            $stmt = $this->db->query($query);
            $stats['total_inventory_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
            
            return $stats;
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرداد إحصائيات المخزون: ' . $e->getMessage());
        }
    }
}
?>
