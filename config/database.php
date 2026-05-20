<?php
class Database {
   private $host = 'sql302.infinityfree.com';
  private $db_name = 'if0_40841777_luckyseven';
  private $username = 'if0_40841777';
    private $password = 'fe9CATfUYM';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        // --- EDITED: Set PHP's default timezone here. This is safe. ---
        date_default_timezone_set('Asia/Riyadh');

        // --- EDITED: Added time_zone to the initial command for MySQL ---
$init_command = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+03:00'";

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => $init_command // Use the variable here
                )
            );
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            
            // Try to create database if it doesn't exist
            if ($exception->getCode() == 1049) {
                try {
                    $tempConn = new PDO(
                        "mysql:host=" . $this->host . ";charset=utf8mb4",
                        $this->username,
                        $this->password,
                        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                    );
                    
                    $tempConn->exec("CREATE DATABASE IF NOT EXISTS `" . $this->db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    // Try connecting again, using the same robust options
                    $this->conn = new PDO(
                        "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                        $this->username,
                        $this->password,
                        array(
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::MYSQL_ATTR_INIT_COMMAND => $init_command // Use the variable here too
                        )
                    );
                    
                    // Create basic tables
                    $this->createBasicTables();
                    
                } catch(PDOException $e) {
                    error_log("Failed to create database: " . $e->getMessage());
                    return null;
                }
            } else {
                return null;
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Create basic tables if they don't exist
     */
    private function createBasicTables() {
        try {
            // Create users table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `email` varchar(150) NOT NULL UNIQUE,
                    `password` varchar(255) NOT NULL,
                    `role_id` int(11) DEFAULT 1,
                    `is_active` tinyint(1) DEFAULT 1,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `last_login` timestamp NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_email` (`email`),
                    KEY `idx_role_id` (`role_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Create roles table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `roles` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `role_name` varchar(50) NOT NULL UNIQUE,
                    `name` varchar(100) NOT NULL,
                    `description` text NULL,
                    `is_system_role` tinyint(1) DEFAULT 0,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_role_name` (`role_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Insert default roles if table is empty
            $stmt = $this->conn->query("SELECT COUNT(*) FROM roles");
            if ($stmt->fetchColumn() == 0) {
                $this->conn->exec("
                    INSERT INTO `roles` (`role_name`, `name`, `description`, `is_system_role`) VALUES
                    ('super_admin', 'مدير عام', 'صلاحيات كاملة على النظام', 1),
                    ('admin', 'مدير', 'صلاحيات إدارية عامة', 1),
                    ('manager', 'مدير فرع', 'إدارة العمليات اليومية والتقارير', 1),
                    ('employee', 'موظف', 'صلاحيات محدودة للموظفين', 1)
                ");
            }
            
            // Create admin_users table for backward compatibility
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `admin_users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) UNIQUE NOT NULL,
                    `password` varchar(255) NOT NULL,
                    `email` varchar(100) NOT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Insert default admin if admin_users table is empty
            $stmt = $this->conn->query("SELECT COUNT(*) FROM admin_users");
            if ($stmt->fetchColumn() == 0) {
                $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
                $stmt->execute(['admin', $hashedPassword, 'admin@maisalon.com']);
            }
            
            // Create site_settings table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `site_settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_key` varchar(100) UNIQUE NOT NULL,
                    `setting_value` text,
                    `setting_type` enum('text','textarea','image','json') DEFAULT 'text',
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
        } catch (PDOException $e) {
            error_log("Error creating tables: " . $e->getMessage());
        }
    }
}

// Helper function to get site settings (guarded to prevent redeclare)
if (!function_exists('getSiteSetting')) {
    function getSiteSetting($key, $default = '') {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                return $default;
            }
            
            $query = "SELECT setting_value FROM site_settings WHERE setting_key = :key";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Error getting site setting: " . $e->getMessage());
        }
        
        return $default;
    }
}

// Helper function to update site settings (guarded to prevent redeclare)
if (!function_exists('updateSiteSetting')) {
    function updateSiteSetting($key, $value, $type = 'text') {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                return false;
            }
            
            $query = "INSERT INTO site_settings (setting_key, setting_value, setting_type) 
                      VALUES (:key, :value, :type) 
                      ON DUPLICATE KEY UPDATE setting_value = :value, setting_type = :type";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':type', $type);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error updating site setting: " . $e->getMessage());
            return false;
        }
    }
}
?>