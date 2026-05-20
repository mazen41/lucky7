<?php
/**
 * users_functions.php
 *
 * Helper functions for the user management system.
 * This revised version centralizes object creation for efficiency and implements
 * a "fail-closed" security model to prevent unauthorized access during database errors.
 */

// Start session only if it hasn't been started yet.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Centralized Object Instantiation ---
// Create shared instances of the Database and UserManager to avoid redundancy and
// multiple connections on a single request.

if (!class_exists('Database')) {
    // Correctly resolve the path from the current file's directory
    require_once __DIR__ . '/../config/database.php';
}
if (!class_exists('UserManager')) {
    // Correctly resolve the path from the current file's directory
    require_once __DIR__ . '/../app/Core/UserManager.php';
}

/**
 * @var UserManager|null Singleton instance for the UserManager.
 */
$user_manager_instance = null;

/**
 * @var PDO|null Singleton instance for the Database connection.
 */
$db_instance = null;

try {
    // Establish a single database connection for this request.
    $database = new Database();
    $db_instance = $database->getConnection();
    
    // Create a single UserManager instance using the shared connection.
    // The UserManager's constructor should accept the PDO connection.
    $user_manager_instance = new UserManager($db_instance);
} catch (Exception $e) {
    // If the database or core classes cannot be loaded, log the critical error.
    // The permission functions below will handle this state by denying access.
    error_log("Critical Error: Failed to initialize database or UserManager. " . $e->getMessage());
    
    // Optionally, you can display a generic error page in a production environment
    // die("A critical error occurred. Please contact support.");
}

// --- Permission Checking Functions ---

/**
 * Checks if the current logged-in user has a specific permission.
 * This is the core checking function.
 *
 * @param string $permissionKey The permission key to check (e.g., 'users.create').
 * @return bool True if the user has permission, false otherwise.
 */
function hasPermission($permissionKey) {
    global $user_manager_instance;

    // 1. Fail-Closed Pre-check: If core components failed to load, deny permission.
    if (!$user_manager_instance) {
        error_log("Permission denied for '$permissionKey': UserManager is not available.");
        return false;
    }
    
    // 2. Session Check: Ensure a user is logged in.
    if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_id'])) {
        return false;
    }

    try {
        // 3. Defer to UserManager: The core logic for checking permissions resides here.
        return $user_manager_instance->hasPermission($_SESSION['admin_id'], $permissionKey);
    } catch (Exception $e) {
        // 4. Fail-Closed on Error: If any error occurs during the check, log it and deny permission.
        error_log("Permission check failed for key '$permissionKey' for User ID {$_SESSION['admin_id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Requires a permission to access a page. If the user lacks the permission,
 * it sets an error message and redirects them.
 *
 * @param string $permissionKey The required permission key.
 * @param string $redirectUrl The URL to redirect to on failure.
 * @return void This function exits on failure.
 */
function requirePermission($permissionKey, $redirectUrl = 'dashboard_r.php') {
    // First, check if the user is logged in at all.
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
    
    // Use the core hasPermission function to check.
    if (!hasPermission($permissionKey)) {
        // Log the unauthorized attempt for security auditing.
        error_log("Unauthorized Access Attempt: User ID {$_SESSION['admin_id']} was blocked from a resource requiring '$permissionKey'.");
        
        // Set a user-friendly error message (using the consistent session key) and redirect.
        $_SESSION['error'] = 'ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة.';
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Gets all permission keys for the current logged-in user.
 *
 * @return array An array of permission key strings. Returns an empty array on failure.
 */
function getCurrentUserPermissions() {
    global $user_manager_instance;

    // Fail-Closed Pre-check
    if (!$user_manager_instance) {
        return [];
    }
    
    // Session Check
    if (!isset($_SESSION['admin_id'])) {
        return [];
    }

    try {
        return $user_manager_instance->getUserPermissions($_SESSION['admin_id']);
    } catch (Exception $e) {
        error_log("Failed to get permissions for User ID {$_SESSION['admin_id']}: " . $e->getMessage());
        return []; // Return an empty array on failure.
    }
}

/**
 * Safely gets the current logged-in user's data from the session.
 * This is assumed to be defined in your auth.php, but is added here for completeness,
 * as it was called in your original users.php file.
 *
 * @return array|null The user data array or null if not logged in.
 */
// function getCurrentAdmin() {
//     if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_data'])) {
//         return $_SESSION['admin_data'];
//     }
//     // Fallback if 'admin_data' is not set, but 'admin_id' is.
//     elseif (isset($_SESSION['admin_id'])) {
//         return [
//             'id' => $_SESSION['admin_id'],
//             // Add other user details here if needed, or fetch from DB
//         ];
//     }
//     return null;
// }