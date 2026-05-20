<?php
/**
 * Return to Admin Session
 * This script ends the user impersonation and restores the original admin's session.
 */
session_start();
require_once '../config/database.php';

// Security check: Must have an original admin ID in the session.
if (!isset($_SESSION['original_admin_id'])) {
    header('Location: login.php'); // Redirect to login if session is invalid
    exit;
}

$original_admin_id = $_SESSION['original_admin_id'];

// Clear the specialist's session data
unset($_SESSION['user_logged_in'], $_SESSION['user_id'], $_SESSION['user_name']);

// Unset the impersonation flag
unset($_SESSION['original_admin_id']);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch the original admin's data to restore the session completely
    $stmt = $db->prepare("SELECT id, name, role_id FROM users WHERE id = ?");
    $stmt->execute([$original_admin_id]);
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin_user) {
        // Restore the admin session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin_user['id'];
        $_SESSION['admin_username'] = $admin_user['name'];
        $_SESSION['admin_role_id'] = $admin_user['role_id'];

        // Redirect back to the specialist management page
        header('Location: hr_add_specialist.php');
        exit;
    } else {
        // Failsafe: if admin user not found, destroy session and force a new login
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    // Handle any database errors
    error_log("Return to admin failed: " . $e->getMessage());
    session_destroy();
    header('Location: login.php');
    exit;
}