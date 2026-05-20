<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if users table exists
    $checkTable = "SHOW TABLES LIKE 'users'";
    $stmt = $db->prepare($checkTable);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "❌ Users table doesn't exist. Please run the RBAC setup first.\n";
        exit;
    }
    
    // Check if default admin user exists
    $checkUser = "SELECT id FROM users WHERE email = 'admin@maisalon.com'";
    $stmt = $db->prepare($checkUser);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Default admin user already exists.\n";
        echo "Email: admin@maisalon.com\n";
        echo "Password: admin123\n";
    } else {
        // Create default admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        $insertUser = "INSERT INTO users (name, email, password, role_id, is_active, created_at) 
                       VALUES ('Super Admin', 'admin@maisalon.com', :password, 1, 1, NOW())";
        
        $stmt = $db->prepare($insertUser);
        $stmt->bindParam(':password', $hashedPassword);
        
        if ($stmt->execute()) {
            echo "✅ Default admin user created successfully!\n";
            echo "Email: admin@maisalon.com\n";
            echo "Password: admin123\n";
        } else {
            echo "❌ Failed to create default admin user.\n";
        }
    }
    
    // List all users in the system
    echo "\n📋 All users in the system:\n";
    $listUsers = "SELECT id, name, email, role_id, is_active FROM users ORDER BY id";
    $stmt = $db->prepare($listUsers);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "No users found in the database.\n";
    } else {
        foreach ($users as $user) {
            $status = $user['is_active'] ? 'Active' : 'Inactive';
            echo "ID: {$user['id']} | Name: {$user['name']} | Email: {$user['email']} | Role: {$user['role_id']} | Status: {$status}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
