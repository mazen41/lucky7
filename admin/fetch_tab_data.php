// <?php
// // This file provides data on-demand for the client profile page pagination.
// require_once 'auth.php';
// require_once '../config/database.php';

// // Ensure an admin is logged in
// checkAdminAuth();

// header('Content-Type: application/json');

// // --- INPUT VALIDATION ---
// $customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
// $tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_STRING);
// $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

// if (!$customer_id || !$tab) {
//     echo json_encode(['error' => 'Missing required parameters.']);
//     exit;
// }

// $database = new Database();
// $db = $database->getConnection();

// $limit = 5; // Records per page
// $offset = ($page - 1) * $limit;
// $response = [];

// // --- DATA FETCHING LOGIC ---
// try {
//     switch ($tab) {
//         case 'appointments':
//             $count_stmt = $db->prepare("SELECT COUNT(id) FROM appointments WHERE customer_id = ?");
//             $count_stmt->execute([$customer_id]);
//             $total_records = $count_stmt->fetchColumn();

//             $data_stmt = $db->prepare("SELECT * FROM appointments WHERE customer_id = ? ORDER BY start_at DESC LIMIT ? OFFSET ?");
//             $data_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
//             $data_stmt->bindParam(2, $limit, PDO::PARAM_INT);
//             $data_stmt->bindParam(3, $offset, PDO::PARAM_INT);
//             $data_stmt->execute();
//             $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
//             break;

//         case 'invoices':
//             $count_stmt = $db->prepare("SELECT COUNT(id) FROM invoices WHERE customer_id = ?");
//             $count_stmt->execute([$customer_id]);
//             $total_records = $count_stmt->fetchColumn();

//             $data_stmt = $db->prepare("SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
//             $data_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
//             $data_stmt->bindParam(2, $limit, PDO::PARAM_INT);
//             $data_stmt->bindParam(3, $offset, PDO::PARAM_INT);
//             $data_stmt->execute();
//             $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
//             break;

//         case 'health_forms':
//             $count_stmt = $db->prepare("SELECT COUNT(cf.id) FROM client_forms cf WHERE cf.customer_id = ?");
//             $count_stmt->execute([$customer_id]);
//             $total_records = $count_stmt->fetchColumn();

//             $data_stmt = $db->prepare("SELECT cf.*, hf.name as form_name FROM client_forms cf JOIN health_forms hf ON cf.form_id = hf.id WHERE cf.customer_id = ? ORDER BY cf.created_at DESC LIMIT ? OFFSET ?");
//             $data_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
//             $data_stmt->bindParam(2, $limit, PDO::PARAM_INT);
//             $data_stmt->bindParam(3, $offset, PDO::PARAM_INT);
//             $data_stmt->execute();
//             $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
//             break;
        
//         case 'services':
//             // This query finds all unique services from paid invoices for the customer
//             $count_query = "SELECT COUNT(DISTINCT ii.item_id) FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id WHERE i.customer_id = ? AND i.status = 'paid'";
//             $count_stmt = $db->prepare($count_query);
//             $count_stmt->execute([$customer_id]);
//             $total_records = $count_stmt->fetchColumn();

//             $data_query = "
//                 SELECT 
//                     s.name_ar as service_name,
//                     COUNT(ii.id) as times_done,
//                     MAX(i.created_at) as last_done_date
//                 FROM invoice_items ii
//                 JOIN invoices i ON ii.invoice_id = i.id
//                 JOIN services s ON ii.service_id = s.id
//                 WHERE i.customer_id = ? AND i.status = 'paid'
//                 GROUP BY s.id, s.name_ar
//                 ORDER BY last_done_date DESC
//                 LIMIT ? OFFSET ?
//             ";
//             $data_stmt = $db->prepare($data_query);
//             $data_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
//             $data_stmt->bindParam(2, $limit, PDO::PARAM_INT);
//             $data_stmt->bindParam(3, $offset, PDO::PARAM_INT);
//             $data_stmt->execute();
//             $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
//             break;

//         default:
//             throw new Exception("Invalid tab specified.");
//     }

//     // --- PREPARE RESPONSE ---
//     $response = [
//         'success' => true,
//         'data' => $data,
//         'pagination' => [
//             'total_records' => (int)$total_records,
//             'current_page' => $page,
//             'records_per_page' => $limit,
//             'total_pages' => ceil($total_records / $limit)
//         ]
//     ];

// } catch (Exception $e) {
//     $response = ['success' => false, 'error' => $e->getMessage()];
// }

// echo json_encode($response, JSON_UNESCAPED_UNICODE);
// exit;