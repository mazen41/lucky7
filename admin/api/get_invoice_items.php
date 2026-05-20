<?php
// File: admin/api/get_invoice_items.php
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$invoice_id = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);
if (!$invoice_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid invoice ID.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, item_name, quantity, unit_price, item_type, item_id FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}