<?php
// Comprehensive error handling and debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering
header('Content-Type: application/json');
ob_start();

try {
    // Include required files
    require_once 'auth.php';
    require_once 'users_functions.php';
    require_once '../app/Core/UserManager.php';
    
    // Check authentication
    checkAdminAuth();
    $admin = getCurrentAdmin();
    
    // Validate admin
    if (!$admin || !isset($admin['id'])) {
        throw new Exception('معلومات المشرف غير متوفرة');
    }
    
    // Initialize UserManager
    $userManager = new UserManager();
    
    if (!$userManager) {
        throw new Exception('نظام إدارة المستخدمين غير متاح');
    }
    
    // Test essential methods
    if (!method_exists($userManager, 'createUser')) {
        throw new Exception('وظيفة إنشاء المستخدم غير متوفرة');
    }
    
} catch (Exception $e) {
    $initError = $e->getMessage();
    error_log('API Initialization Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
} catch (Error $e) {
    $initError = 'خطأ خطير في التهيئة: ' . $e->getMessage();
    error_log('API Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
} catch (Throwable $e) {
    $initError = 'خطأ غير متوقع في التهيئة: ' . $e->getMessage();
    error_log('API Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}

// If initialization failed, return error immediately
if ($initError) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $initError,
        'debug' => 'Initialization failed'
    ]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير مدعومة');
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            // Check permission
            if (!hasPermission('users.create')) {
                throw new Exception('ليس لديك صلاحية لإنشاء المستخدمين');
            }
            
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $roleId = (int)($_POST['role_id'] ?? 1); // Default to role 1 if not provided
            
            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            // If no role_id provided, default to basic role (ID: 1)
            if (!$roleId) {
                $roleId = 1;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('البريد الإلكتروني غير صحيح');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            }
            
            // Double-check variables (should already be validated)
            if (!$admin || !isset($admin['id'])) {
                error_log('Admin validation failed in create action');
                throw new Exception('معلومات المشرف غير متوفرة');
            }
            
            if (!$userManager) {
                error_log('UserManager validation failed in create action');
                throw new Exception('نظام إدارة المستخدمين غير متاح');
            }
            
            // Check permission function exists
            if (!function_exists('hasPermission')) {
                error_log('hasPermission function not found');
                throw new Exception('وظيفة فحص الصلاحيات غير متوفرة');
            }
            
            try {
                $userId = $userManager->createUser($name, $email, $password, $roleId, $admin['id']);
                
                // Ensure clean output
                ob_clean();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم إنشاء المستخدم بنجاح',
                    'user_id' => $userId
                ]);
                
            } catch (Exception $createError) {
                error_log('User creation error: ' . $createError->getMessage());
                throw new Exception('فشل في إنشاء المستخدم: ' . $createError->getMessage());
            }
            break;
            
        case 'update':
            // Check permission
            if (!hasPermission('users.edit')) {
                throw new Exception('ليس لديك صلاحية لتعديل المستخدمين');
            }
            
            // Validate admin and userManager
            if (!$admin || !isset($admin['id'])) {
                throw new Exception('معلومات المشرف غير متوفرة');
            }
            
            if (!$userManager) {
                throw new Exception('نظام إدارة المستخدمين غير متاح');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 1);
            
            if (!$userId || empty($name) || empty($email) || !$roleId) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('البريد الإلكتروني غير صحيح');
            }
            
            try {
                $userManager->updateUser($userId, $name, $email, $roleId, $isActive, $admin['id']);
                
                // Ensure clean output
                ob_clean();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث المستخدم بنجاح'
                ]);
                
            } catch (Exception $updateError) {
                error_log('User update error: ' . $updateError->getMessage());
                throw new Exception('فشل في تحديث المستخدم: ' . $updateError->getMessage());
            }
            break;
            
        case 'delete':
            // Check permission
            if (!hasPermission('users.delete')) {
                throw new Exception('ليس لديك صلاحية لحذف المستخدمين');
            }
            
            // Validate admin and userManager
            if (!$admin || !isset($admin['id'])) {
                throw new Exception('معلومات المشرف غير متوفرة');
            }
            
            if (!$userManager) {
                throw new Exception('نظام إدارة المستخدمين غير متاح');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if (!$userId) {
                throw new Exception('معرف المستخدم مطلوب');
            }
            
            if ($userId == $admin['id']) {
                throw new Exception('لا يمكنك حذف حسابك الخاص');
            }
            
            try {
                $userManager->deleteUser($userId, $admin['id']);
                
                // Ensure clean output
                ob_clean();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم حذف المستخدم بنجاح'
                ]);
                
            } catch (Exception $deleteError) {
                error_log('User delete error: ' . $deleteError->getMessage());
                throw new Exception('فشل في حذف المستخدم: ' . $deleteError->getMessage());
            }
            break;
            
        case 'change_password':
            // Check permission
            if (!hasPermission('users.edit')) {
                throw new Exception('ليس لديك صلاحية لتغيير كلمات المرور');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            
            if (!$userId || empty($newPassword)) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            if (strlen($newPassword) < 6) {
                throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            }
            
            $userManager->changePassword($userId, $newPassword, $admin['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح'
            ]);
            break;
            
        case 'get_user_permissions':
            // Check permission
            if (!hasPermission('users.manage_permissions')) {
                throw new Exception('ليس لديك صلاحية لعرض الصلاحيات');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if (!$userId) {
                throw new Exception('معرف المستخدم مطلوب');
            }
            
            $user = $userManager->getUserById($userId);
            $rolePermissions = $userManager->getRolePermissions($user['role_id']);
            $userPermissions = $userManager->getUserSpecificPermissions($userId);
            $allPermissions = $userManager->getPermissions();
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'role_permissions' => $rolePermissions,
                'user_permissions' => $userPermissions,
                'all_permissions' => $allPermissions
            ]);
            break;
            
        case 'grant_permission':
            // Check permission
            if (!hasPermission('users.manage_permissions')) {
                throw new Exception('ليس لديك صلاحية لتعيين الصلاحيات');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $permissionId = (int)($_POST['permission_id'] ?? 0);
            
            if (!$userId || !$permissionId) {
                throw new Exception('معرف المستخدم والصلاحية مطلوبان');
            }
            
            $userManager->grantUserPermission($userId, $permissionId, $admin['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'تم منح الصلاحية بنجاح'
            ]);
            break;
            
        case 'revoke_permission':
            // Check permission
            if (!hasPermission('users.manage_permissions')) {
                throw new Exception('ليس لديك صلاحية لإلغاء الصلاحيات');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $permissionId = (int)($_POST['permission_id'] ?? 0);
            
            if (!$userId || !$permissionId) {
                throw new Exception('معرف المستخدم والصلاحية مطلوبان');
            }
            
            $userManager->revokeUserPermission($userId, $permissionId, $admin['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إلغاء الصلاحية بنجاح'
            ]);
            break;
            
        case 'toggle_user_permission':
            // Check permission
            if (!hasPermission('users.manage_permissions')) {
                throw new Exception('ليس لديك صلاحية لإدارة الصلاحيات');
            }
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $permissionId = (int)($_POST['permission_id'] ?? 0);
            $isGranted = (int)($_POST['is_granted'] ?? 0);
            
            if (!$userId || !$permissionId) {
                throw new Exception('معرف المستخدم ومعرف الصلاحية مطلوبان');
            }
            
            if ($isGranted) {
                $userManager->grantUserPermission($userId, $permissionId, $admin['id']);
            } else {
                $userManager->revokeUserPermission($userId, $permissionId, $admin['id']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'تم تحديث الصلاحية بنجاح'
            ]);
            break;
            
        case 'get_roles':
            // Check permission
            if (!hasPermission('users.manage_roles')) {
                throw new Exception('ليس لديك صلاحية لعرض الأدوار');
            }
            
            $roles = $userManager->getRoles();
            
            echo json_encode([
                'success' => true,
                'roles' => $roles
            ]);
            break;
            
        case 'get_role_permissions':
            // Check permission
            if (!hasPermission('users.view')) {
                throw new Exception('ليس لديك صلاحية لعرض الصلاحيات');
            }
            
            $roleId = (int)($_POST['role_id'] ?? 0);
            
            if (!$roleId) {
                throw new Exception('معرف الدور مطلوب');
            }
            
            $permissions = $userManager->getRolePermissions($roleId);
            $role = null;
            
            // Get role info
            $roles = $userManager->getRoles();
            foreach ($roles as $r) {
                if ($r['id'] == $roleId) {
                    $role = $r;
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'permissions' => $permissions,
                'role_name' => $role['name'] ?? $role['role_name_ar'] ?? 'دور غير محدد'
            ]);
            break;
            
        default:
            throw new Exception('إجراء غير مدعوم');
    }
    
} catch (Exception $e) {
    // Clean any output buffer
    ob_clean();
    
    // Log the error for debugging
    error_log('API Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} catch (Error $e) {
    // Clean any output buffer
    ob_clean();
    
    // Log the fatal error
    error_log('API Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ خطير في الخادم',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} catch (Throwable $e) {
    // Clean any output buffer
    ob_clean();
    
    // Log any other throwable
    error_log('API Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} finally {
    // Ensure output buffer is ended
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>
