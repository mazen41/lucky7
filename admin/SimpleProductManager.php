<?php

require_once __DIR__ . '/accounting_functions.php';

class SimpleProductManager
{
    private $db;
    private $acc_settings = null; 

    public function __construct()
    {
        $this->db = (new Database())->getConnection();
    }
    
    /**
     * Converts a given quantity from a larger unit (KG, Liter) to its base unit (Gram, Milliliter).
     * Assumes Gram, Milliliter, and Piece are the base units.
     *
     * @param float $quantity The quantity to convert.
     * @param string $unit The unit of the quantity (e.g., 'KILOGRAM', 'LITER').
     * @return float The quantity in its base unit.
     */
    private function convertToBaseUnits($quantity, $unit) {
        $quantity = (float)$quantity;
        switch (strtoupper($unit)) {
            case 'KILOGRAM':
                return $quantity * 1000; // Convert KG to Grams
            case 'LITER':
                return $quantity * 1000; // Convert Liters to Milliliters
            default:
                return $quantity; // Gram, Milliliter, Piece are already base units
        }
    }

    /**
     * Fetches a single product by its ID, including its recipe if it's a sellable item.
     *
     * @param int $id The product ID.
     * @return array|false The product data or false if not found.
     */
    public function getProductById($id)
    {
        $stmt = $this->db->prepare("SELECT p.*, pc.name_ar as category_name_ar FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id WHERE p.id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        // If the product is a sellable item, get its recipe
        if ($product && $product['product_usage'] === 'direct_sale') {
            $product['recipe'] = $this->getProductRecipe($id);
        } else {
            $product['recipe'] = []; // Ensure recipe key exists
        }

        return $product;
    }
    
    /**
     * Fetches only products marked as "Internal Use" to be used as ingredients.
     *
     * @return array A list of internal use products.
     */
    public function getInternalUseProducts()
    {
        $stmt = $this->db->prepare("SELECT id, name_ar, unit FROM products WHERE product_usage = 'internal_use' AND is_active = 1 ORDER BY name_ar");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches the recipe (list of ingredients) for a given sellable product.
     *
     * @param int $sellable_product_id The ID of the main sellable product.
     * @return array A list of ingredients for the product.
     */
    public function getProductRecipe($sellable_product_id)
    {
        $stmt = $this->db->prepare("
            SELECT 
                pr.ingredient_product_id, 
                pr.quantity_consumed,
                p.name_ar as ingredient_name,
                p.unit as ingredient_unit
            FROM product_recipes pr
            JOIN products p ON pr.ingredient_product_id = p.id
            WHERE pr.sellable_product_id = ?
        ");
        $stmt->execute([$sellable_product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retrieves a paginated list of active products.
     *
     * @param array $filters Filtering options (currently unused).
     * @param int $limit The number of products to return.
     * @param int $offset The starting point for the product list.
     * @return array A list of products.
     */
    public function getProducts($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $query = "SELECT 
                p.id, p.sku, p.name_ar, p.name_en, p.type, p.product_usage,
                p.stock_quantity, p.min_stock_level,
                p.unit, p.purchase_price, p.selling_price, p.cost_price, p.is_active,
                COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE p.is_active = 1
            ORDER BY p.created_at DESC 
            LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Creates a new product, saves its recipe, and logs the initial stock.
     * The initial stock quantity is converted to its base unit before being stored.
     *
     * @param array $data The product data from the form.
     * @param int $adminId The ID of the administrator creating the product.
     * @return int The ID of the newly created product.
     * @throws Exception on failure.
     */
    public function createProduct($data, $adminId)
    {
        $this->db->beginTransaction();
        try {
            // Convert the initial quantity to its base unit before insertion
            $stockInBaseUnits = $this->convertToBaseUnits($data['qty'], $data['unit']);

            $sql = "INSERT INTO products (sku, name_en, name_ar, category_id, type, product_usage, stock_quantity, min_stock_level, unit, purchase_price, selling_price, cost_price, created_by) 
                    VALUES (:sku, :name_en, :name_ar, :category_id, :type, :product_usage, :qty, :min_qty, :unit, :purchase_price, :sale_price, :cost_price, :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sku' => $data['sku'],
                ':name_en' => $data['name_en'],
                ':name_ar' => $data['name_ar'],
                ':category_id' => $data['category_id'],
                ':type' => 'product',
                ':product_usage' => $data['product_usage'],
                ':qty' => $stockInBaseUnits, // <-- SAVE THE CONVERTED VALUE
                ':min_qty' => $data['min_qty'], // Min level is kept in the user-defined unit
                ':unit' => $data['unit'],
                ':purchase_price' => $data['purchase_price'],
                ':sale_price' => $data['sale_price'],
                ':cost_price' => $data['cost_price'],
                ':created_by' => $adminId
            ]);
            $productId = $this->db->lastInsertId();

            if ($stockInBaseUnits > 0) {
                 $this->logStockMovement($productId, $stockInBaseUnits, $stockInBaseUnits, 'purchase', null, null, $adminId, 'Initial stock');
            }
            
            // Save Recipe if applicable
            if ($data['product_usage'] === 'direct_sale' && !empty($data['ingredients'])) {
                $recipeSql = "INSERT INTO product_recipes (sellable_product_id, ingredient_product_id, quantity_consumed) VALUES (?, ?, ?)";
                $recipeStmt = $this->db->prepare($recipeSql);
                foreach ($data['ingredients'] as $index => $ingredientId) {
                    $quantity = $data['quantities'][$index] ?? 0;
                    if ($ingredientId && $quantity > 0) {
                        $recipeStmt->execute([$productId, $ingredientId, $quantity]);
                    }
                }
            }

            $this->db->commit();
            return $productId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Updates a product's details and its recipe.
     * Note: This function does not handle stock adjustments.
     *
     * @param int $id The ID of the product to update.
     * @param array $data The new data for the product.
     * @param int $adminId The ID of the administrator making the update.
     * @throws Exception on failure.
     */
    public function updateProduct($id, $data, $adminId)
    {
        $this->db->beginTransaction();
        try {
            $sql = "UPDATE products SET name_ar = :name_ar, name_en = :name_en, category_id = :category_id, sku = :sku, selling_price = :selling_price, purchase_price = :purchase_price, cost_price = :cost_price, min_stock_level = :min_stock_level, unit = :unit, product_usage = :product_usage WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':name_ar' => $data['name_ar'],
                ':name_en' => $data['name_en'],
                ':category_id' => $data['category_id'],
                ':sku' => $data['sku'],
                ':selling_price' => $data['selling_price'],
                ':purchase_price' => $data['purchase_price'],
                ':cost_price' => $data['cost_price'],
                ':min_stock_level' => $data['min_stock_level'],
                ':unit' => $data['unit'],
                ':product_usage' => $data['product_usage']
            ]);
            
            // Update Recipe: Easiest way is to delete old and insert new
            $deleteStmt = $this->db->prepare("DELETE FROM product_recipes WHERE sellable_product_id = ?");
            $deleteStmt->execute([$id]);

            if ($data['product_usage'] === 'direct_sale' && !empty($data['ingredients'])) {
                $recipeSql = "INSERT INTO product_recipes (sellable_product_id, ingredient_product_id, quantity_consumed) VALUES (?, ?, ?)";
                $recipeStmt = $this->db->prepare($recipeSql);
                foreach ($data['ingredients'] as $index => $ingredientId) {
                    $quantity = $data['quantities'][$index] ?? 0;
                    if ($ingredientId && $quantity > 0) {
                        $recipeStmt->execute([$id, $ingredientId, $quantity]);
                    }
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Adjusts the stock of a product. It converts the incoming quantity change to base units
     * based on the product's registered unit before applying the change.
     *
     * @param int $productId The product ID.
     * @param float $quantityChange The amount to add (positive) or remove (negative), in the product's registered unit.
     * @param string $movementType The type of movement (e.g., 'purchase', 'sale', 'waste').
     * @param int $adminId The ID of the user performing the action.
     * @param int|null $refId An optional reference ID (e.g., order ID).
     * @param string|null $refType An optional reference type (e.g., 'order').
     * @param string $notes Optional notes for the movement.
     * @return float|null The new stock quantity in BASE units, or null on failure.
     * @throws Exception on failure.
     */
    public function adjustStock($productId, $quantityChange, $movementType, $adminId, $refId = null, $refType = null, $notes = '')
    {
        if ($this->acc_settings === null) {
            $this->acc_settings = getAccountingSettings($this->db);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT stock_quantity, purchase_price, unit FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                error_log("Stock Adjustment Warning: Product with ID {$productId} not found.");
                $this->db->rollBack();
                return null;
            }
            
            // Convert the incoming quantity change to base units, based on the product's own unit type
            $quantityChangeInBaseUnits = $this->convertToBaseUnits($quantityChange, $product['unit']);

            // All subsequent operations are on base units
            $currentStock = $product['stock_quantity'];
            $newStock = $currentStock + $quantityChangeInBaseUnits;

            $stmt = $this->db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$newStock, $productId]);
            
            $this->logStockMovement($productId, $quantityChangeInBaseUnits, $newStock, $movementType, $refId, $refType, $adminId, $notes);
            
            // Accounting logic for purchases
            if ($movementType === 'purchase' && $quantityChangeInBaseUnits > 0) {
                $purchase_cost = $quantityChange * (floatval($product['purchase_price']) ?? 0); // Cost is based on original quantity and price
                if ($purchase_cost > 0) {
                    $journal_items = [
                        ['account_id' => $this->acc_settings['default_inventory_account_id'], 'type' => 'debit', 'amount' => $purchase_cost],
                        ['account_id' => $this->acc_settings['default_cash_account_id'], 'type' => 'credit', 'amount' => $purchase_cost],
                    ];
                    createJournalEntry($this->db, date('Y-m-d'), "شراء مخزون للمنتج ID: {$productId}", $journal_items, $refId, 'purchase', $adminId);
                }
            }

            $this->db->commit();
            return $newStock; // Returns the new stock in BASE units
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * A dedicated function for the POS to deduct stock where the quantity is already in the base unit.
     * This avoids repeated conversions and is more efficient for sales processing.
     *
     * @param int $productId The product ID.
     * @param float $baseUnitQuantityChange The amount to change stock by (usually negative), already in the base unit.
     * @param string $movementType The type of stock movement.
     * @param int $adminId The user ID.
     * @param int|null $refId Optional reference ID.
     * @param string|null $refType Optional reference type.
     * @param string $notes Optional notes.
     * @throws Exception on failure.
     */
    public function deductStockByBaseUnits($productId, $baseUnitQuantityChange, $movementType, $adminId, $refId = null, $refType = null, $notes = '') {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                 error_log("Stock Deduction Warning: Product with ID {$productId} not found.");
                 $this->db->rollBack();
                 return;
            }

            // The quantity change is ALREADY in the base unit, so we just add/subtract
            $newStock = (float)$product['stock_quantity'] + (float)$baseUnitQuantityChange;

            $update_stmt = $this->db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $update_stmt->execute([$newStock, $productId]);
            
            $this->logStockMovement($productId, $baseUnitQuantityChange, $newStock, $movementType, $refId, $refType, $adminId, $notes);

            $this->db->commit();
        } catch(Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Logs a stock movement into the database for auditing purposes.
     */
    public function logStockMovement($productId, $change, $newQty, $type, $refId, $refType, $userId, $notes)
    {
        $sql = "INSERT INTO product_stock_movements (product_id, quantity_change, new_quantity, movement_type, reference_id, reference_type, user_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId, $change, $newQty, $type, $refId, $refType, $userId, $notes]);
    }
    
    /**
     * Retrieves products that are at or below their minimum stock level.
     *
     * @param string $usageType The product usage type ('internal_use' or 'direct_sale').
     * @return array A list of low-stock products.
     */
    public function getLowStockProducts($usageType)
    {
        $stmt = $this->db->prepare("
            SELECT p.*, pc.name_ar as category_name_ar
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE p.is_active = 1 AND p.type = 'product' AND p.product_usage = ? AND p.stock_quantity <= p.min_stock_level
            ORDER BY p.name_ar
        ");
        $stmt->execute([$usageType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deactivates a product, effectively deleting it from active lists.
     *
     * @param int $id The ID of the product to delete.
     * @return int The number of rows affected.
     * @throws Exception on failure.
     */
    public function deleteProduct($id)
    {
        try {
            $query = "UPDATE products SET is_active = 0 WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('خطأ في حذف المنتج: ' . $e->getMessage());
        }
    }
}