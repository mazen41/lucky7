<?php
require_once 'auth.php';
require_once '../config/database.php';

// This function likely exists in your included files, but defining it here for clarity if needed.
if (!function_exists('updateSiteSetting')) {
    function updateSiteSetting($key, $value, $type = 'text') {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = :value WHERE setting_key = :key");
            $stmt->execute([':value' => $value, ':key' => $key]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error in updateSiteSetting: " . $e->getMessage());
            return false;
        }
    }
}


checkAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['image'];
    $uploadType = $_POST['upload_type'] ?? '';
    $itemId = $_POST['item_id'] ?? null;

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
    }

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    // Define the web-root relative directory
    $webRootRelativeDir = 'assets/images/';
    // Define the server-path directory (from this file's location)
    $uploadDir = '../' . $webRootRelativeDir;
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    // Generate unique filename and paths
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid($uploadType . '_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    $db_path = $webRootRelativeDir . $filename; // Path to store in DB (e.g., 'assets/images/file.jpg')

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Update database based on upload type
    $database = new Database();
    $db = $database->getConnection();

    switch ($uploadType) {
        case 'hero':
            updateSiteSetting('hero_image', $db_path, 'image');
            break;
        case 'about':
            updateSiteSetting('about_image', $db_path, 'image');
            break;
        case 'logo':
            updateSiteSetting('site_logo', $db_path, 'image');
            break;
        
        // --- START: NEW LOGIC FOR CATEGORY IMAGE UPLOAD ---
        case 'category':
            if (empty($itemId)) {
                throw new Exception('Category ID is required');
            }
            $query = "UPDATE service_categories SET image_url = :image_url WHERE id = :id";
            $stmt = $db->prepare($query);

            // ---- START FIX ----
            // Added a check to ensure the statement was prepared successfully before using it.
            // This prevents a fatal error if the database connection fails or the query is invalid.
            if ($stmt === false) {
                throw new Exception('Failed to prepare the database statement for category update.');
            }
            // ---- END FIX ----

            $stmt->bindParam(':image_url', $db_path);
            $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception('Database update failed for category. Error: ' . ($errorInfo[2] ?? 'Unknown error'));
            }
            break;
        // --- END: NEW LOGIC ---

        case 'service':
            if (empty($itemId)) {
                throw new Exception('Service ID is required');
            }
            $query = "UPDATE services SET image = :image WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':image', $db_path);
            $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);
            $stmt->execute();
            break;
        case 'team':
            if (empty($itemId)) {
                throw new Exception('Team member ID is required');
            }
            $query = "UPDATE team_members SET image = :image WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':image', $db_path);
            $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);
            $stmt->execute();
            break;
        default:
            // Delete the uploaded file if the type is invalid to avoid clutter
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            throw new Exception('Invalid upload type');
    }

    $response = [
        'success' => true,
        'message' => 'Image uploaded successfully',
        'path' => $db_path
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>