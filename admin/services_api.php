<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php'; // Included for requirePermission

header('Content-Type: application/json');
checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // --- NEW CASE TO GET A SERVICE WITH ITS RECIPE ---
        case 'get_with_recipe':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid product ID');
            }

            // Fetch the service details
            $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$service) {
                throw new Exception('Product not found');
            }

            // Fetch the associated recipe
            $recipeStmt = $db->prepare("SELECT * FROM service_recipes WHERE service_id = ?");
            $recipeStmt->execute([$id]);
            $recipe = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);

            $service['recipe'] = $recipe; // Attach recipe to the service object

            echo json_encode(['success' => true, 'service' => $service]);
            break;

        // --- (other cases like get_all, get_active, etc. are unchanged) ---
        case 'get_all':
            $stmt = $db->prepare("
                SELECT s.*, c.name_ar as category_name_ar, c.name_en as category_name_en, c.color as category_color
                FROM services s
                LEFT JOIN service_categories c ON s.category_id = c.id
                ORDER BY c.sort_order, s.sort_order, s.name_ar
            ");
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'services' => $services]);
            break;

        case 'get_active':
            $stmt = $db->prepare("
                SELECT s.*, c.name_ar as category_name_ar, c.name_en as category_name_en, c.color as category_color
                FROM services s
                LEFT JOIN service_categories c ON s.category_id = c.id
                WHERE s.is_active = 1 AND (c.is_active = 1 OR c.is_active IS NULL)
                ORDER BY c.sort_order, s.sort_order, s.name_ar
            ");
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'services' => $services]);
            break;

        case 'get_by_category':
            $categoryId = (int) ($_GET['category_id'] ?? 0);
            $stmt = $db->prepare("
                SELECT s.*, c.name_ar as category_name_ar, c.name_en as category_name_en, c.color as category_color
                FROM services s
                LEFT JOIN service_categories c ON s.category_id = c.id
                WHERE s.category_id = ? AND s.is_active = 1
                ORDER BY s.sort_order, s.name_ar
            ");
            $stmt->execute([$categoryId]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'services' => $services]);
            break;


        // --- MODIFIED CREATE CASE ---
        case 'create':
            requirePermission('services.create');
            $db->beginTransaction();

            try {
                // (Validation remains the same)
                $category_id = (int) ($_POST['category_id'] ?? 0);
                $name_ar = trim($_POST['name_ar'] ?? '');
                //... (all other variable declarations)
                $name_en = trim($_POST['name_en'] ?? '');
                $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
                $offer_price = !empty($_POST['offer_price']) ? (float)$_POST['offer_price'] : null;
                $sort_order = (int) ($_POST['sort_order'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                if (empty($name_ar) || $price === null || $price <= 0) {
                     throw new Exception('Name and a valid price are required.');
                }

                // Insert into services table (as before)
                $stmt = $db->prepare("
                    INSERT INTO services 
                    (category_id, name_ar, name_en, price, offer_price, sort_order, is_active, price_type, duration) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'fixed', 0)
                ");
                $stmt->execute([$category_id ?: null, $name_ar, $name_en, $price, $offer_price, $sort_order, $is_active]);
                $serviceId = $db->lastInsertId();

                // Handle the recipe
                $ingredients = $_POST['ingredients'] ?? [];
                $quantities = $_POST['quantities'] ?? [];

                if (count($ingredients) === count($quantities)) {
                    $recipeStmt = $db->prepare("
                        INSERT INTO service_recipes (service_id, ingredient_product_id, quantity_consumed) 
                        VALUES (?, ?, ?)
                    ");
                    for ($i = 0; $i < count($ingredients); $i++) {
                        $ingId = (int) $ingredients[$i];
                        $qty = (float) $quantities[$i];
                        if ($ingId > 0 && $qty > 0) {
                            $recipeStmt->execute([$serviceId, $ingId, $qty]);
                        }
                    }
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Product created successfully', 'id' => $serviceId]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e; // Re-throw the exception to be caught by the outer try-catch
            }
            break;

        // --- MODIFIED UPDATE CASE ---
        case 'update':
            requirePermission('services.edit');
            $db->beginTransaction();

            try {
                $id = (int) ($_POST['id'] ?? 0);
                // (Validation remains the same)
                 $category_id = (int) ($_POST['category_id'] ?? 0);
                $name_ar = trim($_POST['name_ar'] ?? '');
                $name_en = trim($_POST['name_en'] ?? '');
                $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
                $offer_price = !empty($_POST['offer_price']) ? (float)$_POST['offer_price'] : null;
                $sort_order = (int) ($_POST['sort_order'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if ($id <= 0 || empty($name_ar) || $price === null || $price <= 0) {
                    throw new Exception('Invalid data provided for update.');
                }
                
                // Update services table (as before)
                $stmt = $db->prepare("
                    UPDATE services 
                    SET category_id = ?, name_ar = ?, name_en = ?, price = ?, offer_price = ?, 
                        sort_order = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$category_id ?: null, $name_ar, $name_en, $price, $offer_price, $sort_order, $is_active, $id]);

                // --- UPDATE RECIPE ---
                // 1. Delete the old recipe
                $deleteRecipeStmt = $db->prepare("DELETE FROM service_recipes WHERE service_id = ?");
                $deleteRecipeStmt->execute([$id]);

                // 2. Insert the new recipe
                $ingredients = $_POST['ingredients'] ?? [];
                $quantities = $_POST['quantities'] ?? [];

                if (count($ingredients) === count($quantities)) {
                    $recipeStmt = $db->prepare("
                        INSERT INTO service_recipes (service_id, ingredient_product_id, quantity_consumed) 
                        VALUES (?, ?, ?)
                    ");
                    for ($i = 0; $i < count($ingredients); $i++) {
                        $ingId = (int) $ingredients[$i];
                        $qty = (float) $quantities[$i];
                        if ($ingId > 0 && $qty > 0) {
                            $recipeStmt->execute([$id, $ingId, $qty]);
                        }
                    }
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        // --- MODIFIED DELETE CASE ---
        case 'delete':
            requirePermission('services.delete');
            $db->beginTransaction();

            try {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid product ID');
                
                // Check for dependencies (e.g., in appointments or orders)
                // This check should be modified to match your actual order/sales tables
                $checkStmt = $db->prepare("SELECT COUNT(*) as order_count FROM order_items WHERE service_id = ?");
                $checkStmt->execute([$id]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('لا يمكن حذف المنتج لوجود طلبات سابقة مرتبطة به.');
                }

                // 1. Delete associated recipe
                $deleteRecipeStmt = $db->prepare("DELETE FROM service_recipes WHERE service_id = ?");
                $deleteRecipeStmt->execute([$id]);

                // 2. Delete the service itself
                $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
                $stmt->execute([$id]);

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        // --- (Other cases like toggle_status, get_by_id, etc. are unchanged) ---
        case 'toggle_status':
            requirePermission('services.edit');
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid product ID');
            }

            $stmt = $db->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Product status updated successfully']);
            break;

        case 'get_by_id':
            $id = (int) ($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid product ID');
            }

            $stmt = $db->prepare("
                SELECT s.*, c.name_ar as category_name_ar, c.name_en as category_name_en, c.color as category_color
                FROM services s
                LEFT JOIN service_categories c ON s.category_id = c.id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$service) {
                throw new Exception('Product not found');
            }

            echo json_encode(['success' => true, 'service' => $service]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Services API Error: " . $e->getMessage());
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>