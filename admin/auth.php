<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
function checkAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit();
    }
}

// Get current admin info
function getCurrentAdmin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    
    // If using legacy admin_id = 1, map to existing user
    $admin_id = intval($_SESSION['admin_id']);
    if ($admin_id == 1) {
        $admin_id = 3; // Map to existing admin user in users table
    }
    
    return [
        'id' => $admin_id,
        'username' => $_SESSION['admin_username'] ?? 'admin',
        'email' => $_SESSION['admin_email'] ?? 'admin@maisalon.com'
    ];
}

function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_id']) || isset($_SESSION['user_id']);
}

// Logout function
function adminLogout() {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
