<?php
// Minimal API for testing - isolate the exact error
header('Content-Type: application/json');
ob_start();

try {
    // Basic validation
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $action = $_POST['action'] ?? '';
    if ($action !== 'create') {
        throw new Exception('Action not supported');
    }
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 1);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        throw new Exception('All fields required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email');
    }
    
    // Try to include required files step by step
    if (!file_exists('auth.php')) {
        throw new Exception('auth.php not found');
    }
    require_once 'auth.php';
    
    if (!file_exists('users_functions.php')) {
        throw new Exception('users_functions.php not found');
    }
    require_once 'users_functions.php';
    
    // Check authentication
    if (!function_exists('checkAdminAuth')) {
        throw new Exception('checkAdminAuth function not found');
    }
    checkAdminAuth();
    
    if (!function_exists('getCurrentAdmin')) {
        throw new Exception('getCurrentAdmin function not found');
    }
    $admin = getCurrentAdmin();
    
    if (!$admin || !isset($admin['id'])) {
        throw new Exception('Admin data invalid');
    }
    
    // Try UserManager
    if (!file_exists('../app/Core/UserManager.php')) {
        throw new Exception('UserManager.php not found');
    }
    require_once '../app/Core/UserManager.php';
    
    if (!class_exists('UserManager')) {
        throw new Exception('UserManager class not found');
    }
    
    $userManager = new UserManager();
    
    if (!method_exists($userManager, 'createUser')) {
        throw new Exception('createUser method not found');
    }
    
    // Try to create user
    $userId = $userManager->createUser($name, $email, $password, $roleId, $admin['id']);
    
    // Clean output and return success
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Critical error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

if (ob_get_level()) {
    ob_end_flush();
}
?>
