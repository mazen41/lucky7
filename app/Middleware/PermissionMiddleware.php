<?php
/**
 * Permission Middleware
 * Handles route protection based on user permissions
 */

require_once __DIR__ . '/../Core/UserManager.php';

class PermissionMiddleware {
    private $userManager;
    
    public function __construct() {
        $this->userManager = new UserManager();
    }
    
    /**
     * Check if current user has required permission
     */
    public function requirePermission($permissionKey, $redirectUrl = 'dashboard.php') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: login.php');
            exit();
        }
        
        $userId = $_SESSION['admin_id'];
        
        // Check permission
        if (!$this->userManager->hasPermission($userId, $permissionKey)) {
            // Log unauthorized access attempt
            error_log("Unauthorized access attempt: User ID $userId tried to access $permissionKey");
            
            // Redirect with error message
            $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
            header("Location: $redirectUrl");
            exit();
        }
        
        return true;
    }
    
    /**
     * Check multiple permissions (user must have at least one)
     */
    public function requireAnyPermission($permissions, $redirectUrl = 'dashboard.php') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: login.php');
            exit();
        }
        
        $userId = $_SESSION['admin_id'];
        
        foreach ($permissions as $permission) {
            if ($this->userManager->hasPermission($userId, $permission)) {
                return true;
            }
        }
        
        // No permissions found
        error_log("Unauthorized access attempt: User ID $userId tried to access one of: " . implode(', ', $permissions));
        $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
        header("Location: $redirectUrl");
        exit();
    }
    
    /**
     * Check if user has all specified permissions
     */
    public function requireAllPermissions($permissions, $redirectUrl = 'dashboard.php') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: login.php');
            exit();
        }
        
        $userId = $_SESSION['admin_id'];
        
        foreach ($permissions as $permission) {
            if (!$this->userManager->hasPermission($userId, $permission)) {
                error_log("Unauthorized access attempt: User ID $userId missing permission: $permission");
                $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
                header("Location: $redirectUrl");
                exit();
            }
        }
        
        return true;
    }
    
    /**
     * Get current user permissions for frontend use
     */
    public function getCurrentUserPermissions() {
        session_start();
        
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return [];
        }
        
        $userId = $_SESSION['admin_id'];
        return $this->userManager->getUserPermissions($userId);
    }
    
    /**
     * Check permission without redirect (for AJAX calls)
     */
    public function hasPermission($permissionKey) {
        session_start();
        
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }
        
        $userId = $_SESSION['admin_id'];
        return $this->userManager->hasPermission($userId, $permissionKey);
    }
    
    /**
     * Generate permission-based navigation menu
     */
    public function getNavigationMenu() {
        $permissions = $this->getCurrentUserPermissions();
        $menu = [];
        
        // Dashboard
        if (isset($permissions['dashboard.view'])) {
            $menu[] = [
                'title' => 'لوحة التحكم',
                'url' => 'dashboard.php',
                'icon' => 'fas fa-tachometer-alt',
                'active' => basename($_SERVER['PHP_SELF']) === 'dashboard.php'
            ];
        }
        
        // POS
        if (isset($permissions['pos.view'])) {
            $menu[] = [
                'title' => 'نقطة البيع (POS)',
                'url' => 'pos.php',
                'icon' => 'fas fa-cash-register',
                'active' => basename($_SERVER['PHP_SELF']) === 'pos.php'
            ];
        }
        
        // Bookings
        if (isset($permissions['bookings.view'])) {
            $menu[] = [
                'title' => 'التقويم والحجوزات',
                'url' => 'bookings.php',
                'icon' => 'fas fa-calendar-alt',
                'active' => basename($_SERVER['PHP_SELF']) === 'bookings.php'
            ];
        }
        
        // Clients
        if (isset($permissions['clients.view'])) {
            $menu[] = [
                'title' => 'إدارة العملاء (CRM)',
                'url' => 'clients.php',
                'icon' => 'fas fa-users',
                'active' => basename($_SERVER['PHP_SELF']) === 'clients.php'
            ];
        }
        
        // Inventory
        if (isset($permissions['inventory.view'])) {
            $menu[] = [
                'title' => 'المخزون والمنتجات',
                'url' => 'inventory.php',
                'icon' => 'fas fa-boxes',
                'active' => basename($_SERVER['PHP_SELF']) === 'inventory.php'
            ];
        }
        
        // HR
        if (isset($permissions['hr.view'])) {
            $menu[] = [
                'title' => 'الموارد البشرية (HR)',
                'url' => 'hr.php',
                'icon' => 'fas fa-user-tie',
                'active' => basename($_SERVER['PHP_SELF']) === 'hr.php'
            ];
        }
        
        // Loyalty
        if (isset($permissions['loyalty.view'])) {
            $menu[] = [
                'title' => 'نظام الولاء (Loyalty)',
                'url' => 'loyalty.php',
                'icon' => 'fas fa-award',
                'active' => basename($_SERVER['PHP_SELF']) === 'loyalty.php'
            ];
        }
        
        // Marketing
        if (isset($permissions['marketing.view'])) {
            $menu[] = [
                'title' => 'الحملات التسويقية',
                'url' => 'marketing.php',
                'icon' => 'fas fa-bullhorn',
                'active' => basename($_SERVER['PHP_SELF']) === 'marketing.php'
            ];
        }
        
        // Reports
        if (isset($permissions['reports.view'])) {
            $menu[] = [
                'title' => 'التقارير (Reports)',
                'url' => 'reports.php',
                'icon' => 'fas fa-chart-bar',
                'active' => basename($_SERVER['PHP_SELF']) === 'reports.php'
            ];
        }
        
        // Settings
        if (isset($permissions['settings.view'])) {
            $menu[] = [
                'title' => 'الإعدادات (Settings)',
                'url' => 'settings.php',
                'icon' => 'fas fa-cog',
                'active' => basename($_SERVER['PHP_SELF']) === 'settings.php'
            ];
        }
        
        // User Management
        if (isset($permissions['users.view'])) {
            $menu[] = [
                'title' => 'إدارة المستخدمين والصلاحيات',
                'url' => 'users.php',
                'icon' => 'fas fa-user-shield',
                'active' => basename($_SERVER['PHP_SELF']) === 'users.php'
            ];
        }
        
        return $menu;
    }
    
    /**
     * Filter dashboard statistics based on permissions
     */
    public function filterDashboardStats($stats, $permissions) {
        $filteredStats = [];
        
        if (isset($permissions['dashboard.view_statistics'])) {
            // User can see all statistics
            return $stats;
        }
        
        // Filter based on specific permissions
        if (isset($permissions['bookings.view'])) {
            $filteredStats['bookings'] = $stats['bookings'] ?? 0;
            $filteredStats['total_bookings'] = $stats['total_bookings'] ?? 0;
        }
        
        if (isset($permissions['content.services'])) {
            $filteredStats['services'] = $stats['services'] ?? 0;
        }
        
        if (isset($permissions['content.team'])) {
            $filteredStats['team_members'] = $stats['team_members'] ?? 0;
        }
        
        if (isset($permissions['content.reviews'])) {
            $filteredStats['reviews'] = $stats['reviews'] ?? 0;
        }
        
        return $filteredStats;
    }
}

// Helper functions for easy use in templates
function requirePermission($permission, $redirectUrl = 'dashboard.php') {
    $middleware = new PermissionMiddleware();
    return $middleware->requirePermission($permission, $redirectUrl);
}

function hasPermission($permission) {
    $middleware = new PermissionMiddleware();
    return $middleware->hasPermission($permission);
}

function getCurrentUserPermissions() {
    $middleware = new PermissionMiddleware();
    return $middleware->getCurrentUserPermissions();
}
?>
