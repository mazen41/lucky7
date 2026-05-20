<?php
/**
 * User Management System
 * Handles user authentication, role-based access control, and permissions
 */

require_once __DIR__ . '/../../config/database.php';

class UserManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Authenticate user with email and password
     */
    public function authenticate($email, $password) {
        $query = "SELECT u.*, r.name as role_name, r.description as role_description 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE u.email = :email AND u.is_active = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Create session
                $this->createUserSession($user['id']);
                
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($userId, $permissionKey) {
        // Check user-specific permissions first (overrides)
        $query = "SELECT up.is_granted 
                  FROM user_permissions up 
                  JOIN permissions p ON up.permission_id = p.id 
                  WHERE up.user_id = :user_id AND p.permission_key = :permission_key";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':permission_key', $permissionKey);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (bool)$result['is_granted'];
        }
        
        // Check role-based permissions
        $query = "SELECT COUNT(*) as count 
                  FROM role_permissions rp 
                  JOIN permissions p ON rp.permission_id = p.id 
                  JOIN users u ON u.role_id = rp.role_id 
                  WHERE u.id = :user_id AND p.permission_key = :permission_key";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':permission_key', $permissionKey);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId) {
        $permissions = [];
        
        // Get role-based permissions
        $query = "SELECT DISTINCT p.permission_key, p.permission_name_ar, p.module, p.action 
                  FROM role_permissions rp 
                  JOIN permissions p ON rp.permission_id = p.id 
                  JOIN users u ON u.role_id = rp.role_id 
                  WHERE u.id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['permission_key']] = $row;
        }
        
        // Apply user-specific overrides
        $query = "SELECT p.permission_key, p.permission_name_ar, p.module, p.action, up.is_granted 
                  FROM user_permissions up 
                  JOIN permissions p ON up.permission_id = p.id 
                  WHERE up.user_id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['is_granted']) {
                $permissions[$row['permission_key']] = $row;
            } else {
                unset($permissions[$row['permission_key']]);
            }
        }
        
        return $permissions;
    }
    
    /**
     * Create new user
     */
    public function createUser($name, $email, $password, $roleId, $createdBy = null) {
        // Check if email already exists
        if ($this->emailExists($email)) {
            throw new Exception('البريد الإلكتروني مستخدم بالفعل');
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Simple insert without created_by to avoid column errors
        $query = "INSERT INTO users (name, email, password, role_id) VALUES (:name, :email, :password, :role_id)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role_id', $roleId);
        
        if ($stmt->execute()) {
            $userId = $this->db->lastInsertId();
            
            // Log the action if audit table exists
            try {
                $this->logAuditAction($createdBy, 'create_user', $userId, null, null, json_encode(['name' => $name, 'email' => $email, 'role_id' => $roleId]));
            } catch (Exception $e) {
                // Ignore audit logging errors
                error_log("Audit logging failed: " . $e->getMessage());
            }
            
            return $userId;
        }
        
        throw new Exception('فشل في إنشاء المستخدم');
    }
    
    /**
     * Check if a column exists in a table
     */
    private function columnExists($table, $column) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Update user
     */
    public function updateUser($userId, $name, $email, $roleId, $isActive, $updatedBy = null) {
        // Get old values for audit
        $oldUser = $this->getUserById($userId);
        
        $query = "UPDATE users SET name = :name, email = :email, role_id = :role_id, is_active = :is_active 
                  WHERE id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role_id', $roleId);
        $stmt->bindParam(':is_active', $isActive);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            // Log the action
            $this->logAuditAction($updatedBy, 'update_user', $userId, null, json_encode($oldUser), json_encode(['name' => $name, 'email' => $email, 'role_id' => $roleId, 'is_active' => $isActive]));
            
            return true;
        }
        
        throw new Exception('فشل في تحديث المستخدم');
    }
    
    /**
     * Delete user (soft delete by deactivating)
     */
    public function deleteUser($userId, $deletedBy = null) {
        $oldUser = $this->getUserById($userId);
        
        $query = "UPDATE users SET is_active = 0 WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            // Log the action
            $this->logAuditAction($deletedBy, 'delete_user', $userId, null, json_encode($oldUser), null);
            
            return true;
        }
        
        throw new Exception('فشل في حذف المستخدم');
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $query = "SELECT u.*, r.name as role_name, r.description as role_description 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE u.id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers($page = 1, $limit = 20, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE (u.name LIKE :search OR u.email LIKE :search OR r.name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        $query = "SELECT u.*, r.name as role_name, r.description as role_description 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  $whereClause
                  ORDER BY u.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total users count
     */
    public function getTotalUsersCount($search = '') {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE (u.name LIKE :search OR u.email LIKE :search OR r.name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        $query = "SELECT COUNT(*) as count 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  $whereClause";
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'];
    }
    
    /**
     * Get all roles
     */
    public function getRoles() {
        $query = "SELECT * FROM roles ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all permissions grouped by module
     */
    public function getPermissions() {
        $query = "SELECT * FROM permissions ORDER BY module, action";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by module
        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['module']][] = $permission;
        }
        
        return $grouped;
    }
    
    /**
     * Grant permission to user
     */
    public function grantUserPermission($userId, $permissionId, $grantedBy = null) {
        $query = "INSERT INTO user_permissions (user_id, permission_id, is_granted, granted_by) 
                  VALUES (:user_id, :permission_id, 1, :granted_by) 
                  ON DUPLICATE KEY UPDATE is_granted = 1, granted_by = :granted_by";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':permission_id', $permissionId);
        $stmt->bindParam(':granted_by', $grantedBy);
        
        if ($stmt->execute()) {
            $this->logAuditAction($grantedBy, 'grant', $userId, null, $permissionId, null);
            return true;
        }
        
        return false;
    }
    
    /**
     * Revoke permission from user
     */
    public function revokeUserPermission($userId, $permissionId, $revokedBy = null) {
        $query = "INSERT INTO user_permissions (user_id, permission_id, is_granted, granted_by) 
                  VALUES (:user_id, :permission_id, 0, :revoked_by) 
                  ON DUPLICATE KEY UPDATE is_granted = 0, granted_by = :revoked_by";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':permission_id', $permissionId);
        $stmt->bindParam(':revoked_by', $revokedBy);
        
        if ($stmt->execute()) {
            $this->logAuditAction($revokedBy, 'revoke', $userId, null, $permissionId, null);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get role permissions
     */
    public function getRolePermissions($roleId) {
        $query = "SELECT p.* FROM role_permissions rp 
                  JOIN permissions p ON rp.permission_id = p.id 
                  WHERE rp.role_id = :role_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':role_id', $roleId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clear all permissions for a role
     */
    public function clearRolePermissions($roleId) {
        $query = "DELETE FROM role_permissions WHERE role_id = :role_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':role_id', $roleId);
        return $stmt->execute();
    }
    
    /**
     * Grant permission to role
     */
    public function grantRolePermission($roleId, $permissionId, $grantedBy = null) {
        $query = "INSERT INTO role_permissions (role_id, permission_id, granted_by) 
                  VALUES (:role_id, :permission_id, :granted_by)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':role_id', $roleId);
        $stmt->bindParam(':permission_id', $permissionId);
        $stmt->bindParam(':granted_by', $grantedBy);
        
        return $stmt->execute();
    }
    
    /**
     * Create new role
     */
    public function createRole($roleName, $roleNameAr, $description = null, $createdBy = null) {
        // Simple insert without created_by to avoid column errors
        $query = "INSERT INTO roles (role_name, name, description) VALUES (:role_name, :name, :description)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':role_name', $roleName);
        $stmt->bindParam(':name', $roleNameAr);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        throw new Exception('فشل في إنشاء الدور');
    }
    
    /**
     * Get user specific permissions (overrides)
     */
    public function getUserSpecificPermissions($userId) {
        $query = "SELECT p.*, up.is_granted FROM user_permissions up 
                  JOIN permissions p ON up.permission_id = p.id 
                  WHERE up.user_id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email, $excludeUserId = null) {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
        $params = [':email' => $email];
        
        if ($excludeUserId) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeUserId;
        }
        
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
    }
    
    /**
     * Create user session
     */
    private function createUserSession($userId) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $query = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                  VALUES (:user_id, :session_token, :ip_address, :user_agent, :expires_at)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':session_token', $sessionToken);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $stmt->bindParam(':expires_at', $expiresAt);
        $stmt->execute();
        
        return $sessionToken;
    }
    
    /**
     * Log audit action
     */
    private function logAuditAction($userId, $action, $targetUserId = null, $targetRoleId = null, $permissionId = null, $details = null) {
        try {
            // Check if audit table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'permission_audit_log'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Audit table doesn't exist, skip logging
                return true;
            }
            
            $query = "INSERT INTO permission_audit_log (user_id, target_user_id, target_role_id, permission_id, action, new_value, ip_address, user_agent) 
                      VALUES (:user_id, :target_user_id, :target_role_id, :permission_id, :action, :new_value, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':target_user_id', $targetUserId);
            $stmt->bindParam(':target_role_id', $targetRoleId);
            $stmt->bindParam(':permission_id', $permissionId);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':new_value', $details);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Silently fail audit logging
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $newPassword, $changedBy = null) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = :password WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            $this->logAuditAction($changedBy, 'update_user', $userId, null, null, 'password_changed');
            return true;
        }
        
        return false;
    }
}
?>
