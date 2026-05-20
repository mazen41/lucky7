<?php
/**
 * Fixed ProductManager Class
 * Handles product and inventory management operations with proper column mapping
 * 
 * @author MAI Salon Development Team
 * @version 2.0
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
     * Create a new product with proper column mapping
     */
    public function createProduct($data, $createdBy) {
        try {
            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateSKU($data['name_ar'] ?? 'PROD');
            }
            
            // Validate SKU uniqueness
            if ($this->skuExists($data['sku'])) {
                throw new Exception('رمز المنتج (SKU) مستخدم بالفعل');
            }
            
            $query = "INSERT INTO products (
                sku, name_ar, name_en, category_id, type, 
                stock_quantity, min_stock_level, unit_of_measure, 
                purchase_price, sale_price, cost_price, is_active
            ) VALUES (
                :sku, :name_ar, :name_en, :category_id, :type,
                :stock_quantity, :min_stock_level, :unit_of_measure,
                :purchase_price, :sale_price, :cost_price, 1
            )";
            
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':sku', $data['sku']);
            $stmt->bindParam(':name_ar', $data['name_ar']);
            $stmt->bindParam(':name_en', $data['name'] ?? null);
            $stmt->bindParam(':category_id', $data['category_id']);
            $stmt->bindParam(':type', $data['type'] ?? 'product');
            $stmt->bindParam(':stock_quantity', $data['qty'] ?? 0);
            $stmt->bindParam(':min_stock_level', $data['min_qty'] ?? 5);
            $stmt->bindParam(':unit_of_measure', $data['unit'] ?? 'قطعة');
            $stmt->bindParam(':purchase_price', $data['purchase_price']);
            $stmt->bindParam(':sale_price', $data['sale_price']);
            $stmt->bindParam(':cost_price', $data['cost_price']);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            
            throw new Exception('فشل في إنشاء المنتج');
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في قاعدة البيانات: ' . $e->getMessage());
        }
    }
    
    /**
     * Get products with proper array key handling
     */
    public function getProducts($filters = [], $limit = 50, $offset = 0) {
        try {
            $query = "SELECT 
                p.id,
                p.sku,
                p.name_en as name,
                p.name_ar,
                p.type,
                p.stock_quantity,
                p.stock_quantity as qty,
                p.min_stock_level,
                p.min_stock_level as min_qty,
                p.unit_of_measure,
                p.unit_of_measure as unit,
                p.purchase_price,
                p.sale_price,
                p.cost_price,
                p.is_active,
                COALESCE(pc.name_ar, 'غير مصنف') as category_name,
                COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE 1=1";
            
            $params = [];
            
            if (isset($filters['is_active'])) {
                $query .= " AND p.is_active = :is_active";
                $params[':is_active'] = $filters['is_active'] ? 1 : 0;
            }
            
            if (isset($filters['type'])) {
                $query .= " AND p.type = :type";
                $params[':type'] = $filters['type'];
            }
            
            $query .= " ORDER BY p.name_ar LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرجاع المنتجات: ' . $e->getMessage());
        }
    }
    
    /**
     * Get low stock products with proper array key handling
     */
    public function getLowStockProducts($limit = 20) {
        try {
            $query = "SELECT 
                p.id,
                p.sku,
                p.name_en as name,
                p.name_ar,
                p.stock_quantity,
                p.stock_quantity as qty,
                p.min_stock_level,
                p.min_stock_level as min_qty,
                p.unit_of_measure,
                p.unit_of_measure as unit,
                p.sale_price,
                COALESCE(pc.name_ar, 'غير مصنف') as category_name,
                COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE p.is_active = 1 
            AND p.type = 'product'
            AND p.stock_quantity <= p.min_stock_level
            ORDER BY p.stock_quantity ASC
            LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرجاع المنتجات منخفضة المخزون: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if SKU exists
     */
    private function skuExists($sku) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE sku = :sku");
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Generate SKU
     */
    private function generateSKU($name) {
        $prefix = strtoupper(substr($name, 0, 3));
        $timestamp = time();
        return $prefix . $timestamp;
    }
    
    /**
     * Get product by ID
     */
    public function getProductById($id) {
        try {
            $query = "SELECT 
                p.*,
                COALESCE(pc.name_ar, 'غير مصنف') as category_name
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE p.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception('خطأ في استرجاع المنتج: ' . $e->getMessage());
        }
    }
}
?>
