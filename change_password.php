<?php
require_once './config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // The target email and new password
    $email = 'admin@maisalon.com';
    $newPassword = 'password'; // 🔒 change this to your desired password

    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Prepare the update statement
    $query = "UPDATE users SET password = :password WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':email', $email);

    if ($stmt->execute()) {
        echo "✅ Password updated successfully for {$email}.";
    } else {
        echo "❌ Failed to update password.";
    }
} catch (Exception $e) {
    echo "⚠️ Error: " . $e->getMessage();
}
