<?php
// Set the content type to JSON and specify UTF-8 encoding
header('Content-Type: application/json; charset=utf-8');

// Include the database configuration file
require_once '../config/database.php';

// --- Error Handling ---
// A simple function to send a JSON error response and stop the script
function send_error($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// --- Input Validation ---
// Check if the category_id is provided in the request
if (!isset($_GET['category_id']) || !is_numeric($_GET['category_id'])) {
    send_error('A valid Category ID is required.');
}

// Sanitize the input to ensure it's an integer
$categoryId = intval($_GET['category_id']);

// --- Database Interaction ---
try {
    // Establish a new database connection
    $database = new Database();
    $db = $database->getConnection();

    // ================= START: EDITED SQL QUERY =================
    // Prepare the SQL query to fetch all necessary service details, including all price types
    $query = "SELECT 
                id, 
                name_ar, 
                description_ar, 
                duration,
                price_type,
                price,
                offer_price,
                min_price,
                max_price
              FROM 
                services 
              WHERE 
                category_id = :category_id 
                AND is_active = 1 
              ORDER BY 
                sort_order ASC, name_ar ASC";
    // ================== END: EDITED SQL QUERY ==================
    
    // Prepare the statement to prevent SQL injection
    $stmt = $db->prepare($query);

    // Bind the category ID parameter
    $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);

    // Execute the query
    $stmt->execute();

    // Fetch all the matching services as an associative array
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Success Response ---
    // If the query was successful, encode the results in JSON and send them
    // Use JSON_UNESCAPED_UNICODE to prevent Arabic characters from being converted to \uXXXX
    echo json_encode($services, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    // --- Database Error Handling ---
    // Log the error for debugging purposes (don't show detailed errors to the user)
    error_log("Database Error: " . $e->getMessage());
    
    // Send a generic server error message
    send_error('Could not connect to the database or query failed.', 500);
}
?>