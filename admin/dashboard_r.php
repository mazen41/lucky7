<?php
/**
 * Role-Based Dashboard Redirector - MAI Salon (FIXED TO MATCH NEW LOGIN LOGIC)
 *
 * This script is now simplified. Since `login.php` has been corrected to always store
 * the `users.id` in the session, we no longer need complex checks.
 *
 * 1. It gets the user ID from the session (either `user_id` or `admin_id`).
 * 2. It checks the role directly using the `users.id`.
 * 3. It redirects to the correct final dashboard.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// --- UNIFIED USER ID CHECK ---
// Get the user ID, which is always from the 'users' table now.
$userId = null;
if (isset($_SESSION['user_id'])) {
    // This session is for specialists.
    $userId = $_SESSION['user_id'];
} elseif (isset($_SESSION['admin_id'])) {
    // This session is for admins and other staff.
    $userId = $_SESSION['admin_id'];
} else {
    // If no one is logged in, send them to the login page.
    header('Location: login.php');
    exit();
}

// Set the default dashboard URL for admins and other staff roles.
// This matches the redirect in login.php.
$redirectUrl = 'dashboard.php'; 

try {
    $database = new Database();
    $db = $database->getConnection();

    // --- SIMPLIFIED, UNIFIED QUERY ---
    // Since we always have the users.id, we only need one query.
    $query = "SELECT r.role_name 
              FROM users u
              JOIN roles r ON u.role_id = r.id
              WHERE u.id = ?
              LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If we found the user and their role is 'specialist'...
    if ($user && $user['role_name'] === 'specialist') {
        // ...change the redirect URL to the specialist's final destination.
        // NOTE: Make sure 'specialist_dashboard.php' is the correct final page.
        $redirectUrl = 'specialist_dashboard.php';
    }
    
} catch (Exception $e) {
    // If there is any database error, log it for debugging purposes.
    // The script will proceed to the default admin dashboard as a failsafe.
    error_log("Dashboard redirect failed for user ID $userId: " . $e->getMessage());
}

// Perform the final redirect to the correct dashboard.
header('Location: ' . $redirectUrl);
exit();