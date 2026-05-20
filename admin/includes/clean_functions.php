<?php
/**
 * Clean permission functions - no duplicate includes
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global hasPermission function
if (!function_exists('hasPermission')) {
    function hasPermission($permissionKey) {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }
        
        try {
            // Use existing database connection if available
            global $db;
            if (!isset($db)) {
                if (!class_exists('Database')) {
                    require_once __DIR__ . '/../../config/database.php';
                }
                $database = new Database();
                $db = $database->getConnection();
            }
            
            // Check if RBAC tables exist
            $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return true; // Allow if no RBAC tables
            }
            
            // Include UserManager if available
            if (!class_exists('UserManager')) {
                $userManagerPath = __DIR__ . '/../../app/Core/UserManager.php';
                if (file_exists($userManagerPath)) {
                    require_once $userManagerPath;
                } else {
                    return true; // Fallback if UserManager not found
                }
            }
            
            $userManager = new UserManager();
            return $userManager->hasPermission($_SESSION['admin_id'], $permissionKey);
            
        } catch (Exception $e) {
            error_log("Permission check failed: " . $e->getMessage());
            return true;
        }
    }
}
?>