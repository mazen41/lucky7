<?php
require_once 'auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');
checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            $stmt = $db->prepare("SELECT * FROM service_categories ORDER BY sort_order, name_ar");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'get_active':
            $stmt = $db->prepare("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name_ar");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'create':
            $name_ar = $_POST['name_ar'] ?? '';
            $name_en = $_POST['name_en'] ?? '';
            $description_ar = $_POST['description_ar'] ?? $_POST['description'] ?? '';
            $description_en = $_POST['description_en'] ?? '';
            $icon = $_POST['icon'] ?? 'fas fa-star';
            $color = $_POST['color'] ?? '#8B4513';
            $sort_order = (int) ($_POST['sort_order'] ?? 0);
            
            if (empty($name_ar)) {
                throw new Exception('Arabic name is required');
            }
            
            $stmt = $db->prepare("
                INSERT INTO service_categories 
                (name_ar, name_en, description, icon, color, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name_ar, 
                $name_en, 
                $description_ar, 
                $icon, 
                $color, 
                $sort_order
            ]);
            
            $categoryId = $db->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Category created successfully', 'id' => $categoryId]);
            break;

        case 'update':
            $id = (int) ($_POST['id'] ?? 0);
            $name_ar = $_POST['name_ar'] ?? '';
            $name_en = $_POST['name_en'] ?? '';
            $description_ar = $_POST['description_ar'] ?? $_POST['description'] ?? '';
            $description_en = $_POST['description_en'] ?? '';
            $icon = $_POST['icon'] ?? 'fas fa-star';
            $color = $_POST['color'] ?? '#8B4513';
            $sort_order = (int) ($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            if (empty($name_ar)) {
                throw new Exception('Arabic name is required');
            }
            
            $stmt = $db->prepare("
                UPDATE service_categories 
                SET name_ar = ?, name_en = ?, description = ?, 
                    icon = ?, color = ?, sort_order = ?, is_active = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name_ar, 
                $name_en, 
                $description_ar, 
                $icon, 
                $color, 
                $sort_order, 
                $is_active, 
                $id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            // Check if category has services
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM services_new WHERE category_id = ?");
            $stmt->execute([$id]);
            $serviceCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($serviceCount > 0) {
                throw new Exception("Cannot delete category with {$serviceCount} services. Please move or delete services first.");
            }
            
            $stmt = $db->prepare("DELETE FROM service_categories WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
            break;

        case 'toggle_status':
            $id = (int) ($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            $stmt = $db->prepare("UPDATE service_categories SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Category status updated successfully']);
            break;

        case 'reorder':
            $categories = json_decode($_POST['categories'] ?? '[]', true);
            
            if (empty($categories)) {
                throw new Exception('No categories provided for reordering');
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE service_categories SET sort_order = ? WHERE id = ?");
            
            foreach ($categories as $index => $categoryId) {
                $stmt->execute([$index + 1, $categoryId]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Categories reordered successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
