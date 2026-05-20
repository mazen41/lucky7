<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_GET['invoice_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف الفاتورة مطلوب']);
    exit;
}

$invoice_id = intval($_GET['invoice_id']);

try {
    // Get invoice items
    $items_query = "SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id";
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$invoice_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل عناصر الفاتورة: ' . $e->getMessage()
    ]);
}
?>
