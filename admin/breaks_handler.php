<?php
session_start();
require_once '../config/database.php'; // Adjust path as needed

// Set the content type to JSON for all responses
header('Content-Type: application/json');

// Prepare a default response array
$response = [
    'success' => false,
    'message' => 'An invalid action was requested.'
];

// --- Authentication & Authorization ---
// if (!isset($_SESSION['user_id'])) {
//     http_response_code(403);
//     $response['message'] = 'Access Denied. You do not have permission to perform this action.';
//     echo json_encode($response);
//     exit;
// }

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            // --- CREATE (ADD) A NEW BREAK ---
            case 'add':
                $stmt = $db->prepare(
                    "INSERT INTO hr_break_rules (break_name_en, break_name_ar, duration_minutes, is_paid) VALUES (:bne, :bna, :duration, :paid)"
                );
                $stmt->bindParam(':bne', $_POST['break_name_en']);
                $stmt->bindParam(':bna', $_POST['break_name_ar']);
                $stmt->bindParam(':duration', $_POST['duration_minutes'], PDO::PARAM_INT);
                $stmt->bindParam(':paid', $_POST['is_paid'], PDO::PARAM_INT); // Use PARAM_INT for boolean 0/1
                $stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Break rule added successfully.';
                break;

            // --- UPDATE AN EXISTING BREAK ---
            case 'update':
                $stmt = $db->prepare(
                    "UPDATE hr_break_rules SET break_name_en = :bne, break_name_ar = :bna, duration_minutes = :duration, is_paid = :paid WHERE id = :id"
                );
                $stmt->bindParam(':bne', $_POST['break_name_en']);
                $stmt->bindParam(':bna', $_POST['break_name_ar']);
                $stmt->bindParam(':duration', $_POST['duration_minutes'], PDO::PARAM_INT);
                $stmt->bindParam(':paid', $_POST['is_paid'], PDO::PARAM_INT); // Use PARAM_INT for boolean 0/1
                $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT); // The JS sends 'id', not 'break_id'
                $stmt->execute();

                $response['success'] = true;
                $response['message'] = 'Break rule updated successfully.';
                break;

            // --- DELETE A BREAK ---
            case 'delete':
                $stmt = $db->prepare("DELETE FROM hr_break_rules WHERE id = :id");
                $stmt->bindParam(':id', $_POST['break_id'], PDO::PARAM_INT);
                $stmt->execute();
                
                // For delete, we redirect from the form itself, but if called via JS, this would be the response
                $_SESSION['flash_success'] = 'Break rule deleted successfully.';
                header('Location: manage_breaks.php');
                exit; // Exit after redirect header
        }
    } catch (PDOException $e) {
        // If any database error occurs, catch it and send it back as a JSON message
        http_response_code(500);
        $response['message'] = "Database Error: " . $e->getMessage();
    }
}

// Echo the final JSON response and exit.
echo json_encode($response);
exit;
?>