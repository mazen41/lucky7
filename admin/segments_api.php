<?php
/**
 * Customer Segments API - MAI Salon CRM
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
// Start session and basic auth check
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مخول للوصول']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';


try {
    if ($method === 'POST') {
        switch ($action) {
            case 'auto_segment':
                runAutomaticSegmentation($db);
                break;
            case 'create_segment':
            default:
                createSegment($db);
                break;
        }
    } elseif ($method === 'GET') {
         if ($action === 'customers') {
            getSegmentCustomers($db);
        } else {
            getSegments($db);
        }
    } elseif ($method === 'PUT') {
        updateSegment($db);
    } elseif ($method === 'DELETE') {
        deleteSegment($db);
    } else {
        throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


function createSegment($db) {
    $name_ar = $_POST['name_ar'] ?? '';
    $description_ar = $_POST['description_ar'] ?? '';
    $color = $_POST['color'] ?? '#3B82F6';
    $is_automatic = isset($_POST['is_automatic']) ? 1 : 0;

    if (empty($name_ar)) {
        throw new Exception('اسم الشريحة مطلوب');
    }

    $stmt = $db->prepare("
        INSERT INTO customer_segments (name_ar, description_ar, color, is_automatic, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $name_ar,
        $description_ar,
        $color,
        $is_automatic,
        $_SESSION['admin_id'] ?? 1
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء الشريحة بنجاح',
        'segment_id' => $db->lastInsertId()
    ]);
}


function getSegments($db) {
    $stmt = $db->prepare("
        SELECT cs.*,
               COUNT(csa.customer_id) as customer_count
        FROM customer_segments cs
        LEFT JOIN customer_segment_assignments csa ON cs.id = csa.segment_id
        WHERE cs.is_active = 1
        GROUP BY cs.id
        ORDER BY cs.name_ar
    ");

    $stmt->execute();
    $segments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $segments
    ]);
}

function getSegmentCustomers($db) {
    $segment_id = $_GET['segment_id'] ?? 0;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    if (!$segment_id) {
        throw new Exception('معرف الشريحة مطلوب');
    }

    // Get customers in segment
    $stmt = $db->prepare("
        SELECT c.*, csa.assigned_at
        FROM customers c
        INNER JOIN customer_segment_assignments csa ON c.id = csa.customer_id
        WHERE csa.segment_id = ? AND c.status = 'active'
        ORDER BY c.name_ar
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$segment_id, $limit, $offset]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $count_stmt = $db->prepare("
        SELECT COUNT(*)
        FROM customers c
        INNER JOIN customer_segment_assignments csa ON c.id = csa.customer_id
        WHERE csa.segment_id = ? AND c.status = 'active'
    ");
    $count_stmt->execute([$segment_id]);
    $total = $count_stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $customers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function runAutomaticSegmentation($db) {
    // Read thresholds from POST (with defaults)
    $vip_min_spent = isset($_POST['vip_min_spent']) ? floatval($_POST['vip_min_spent']) : 5000.0;
    $regular_min_visits = isset($_POST['regular_min_visits']) ? intval($_POST['regular_min_visits']) : 5;
    $new_days = isset($_POST['new_days']) ? intval($_POST['new_days']) : 30;
    $inactive_days = isset($_POST['inactive_days']) ? intval($_POST['inactive_days']) : 90;

    // Clear existing automatic assignments
    $db->exec("DELETE FROM customer_segment_assignments WHERE is_automatic = 1");

    $diagnostics = [];
    $metrics = [];
    // Strategy 1: invoices + invoice_items
    try {
        $metrics_sql = "
            SELECT c.id AS customer_id,
                   COALESCE(SUM(ii.total_price), 0) AS total_spent,
                   COUNT(DISTINCT i.id) AS visit_count,
                   GREATEST(COALESCE(MAX(i.created_at), '1970-01-01'), COALESCE(MAX(a.start_at), '1970-01-01')) AS last_activity_date,
                   COALESCE(c.created_at, '1970-01-01') AS created_at
            FROM customers c
            LEFT JOIN invoices i ON c.id = i.customer_id AND i.status = 'paid'
            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
            LEFT JOIN appointments a ON c.id = a.customer_id
            WHERE c.status = 'active'
            GROUP BY c.id
        ";
        $metrics = $db->query($metrics_sql)->fetchAll(PDO::FETCH_ASSOC);
        $diagnostics[] = 'strategy: invoices+items';
    } catch (Throwable $e) {
        $metrics = $db->query("SELECT id AS customer_id, 0 AS total_spent, 0 AS visit_count, created_at AS last_activity_date, created_at FROM customers WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
        $diagnostics[] = 'strategy: fallback, no invoice/appointment data';
    }


    // Map segment names to IDs
    $segStmt = $db->query("SELECT id, name_ar FROM customer_segments WHERE is_active = 1 AND is_automatic = 1");
    $segRows = $segStmt->fetchAll(PDO::FETCH_ASSOC);
    $segmentIds = [
        'vip' => null,
        'regular' => null,
        'new' => null,
        'inactive' => null,
    ];
    foreach ($segRows as $s) {
        $name = (string)$s['name_ar'];
        $lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if (strpos(strtolower($name), 'vip') !== false) $segmentIds['vip'] = $s['id'];
        elseif (strpos($lower, 'منتظم') !== false || strpos(strtolower($name), 'regular') !== false) $segmentIds['regular'] = $s['id'];
        elseif (strpos($lower, 'جد') !== false || strpos(strtolower($name), 'new') !== false) $segmentIds['new'] = $s['id'];
        elseif (strpos($lower, 'غير') !== false || strpos(strtolower($name), 'inactive') !== false) $segmentIds['inactive'] = $s['id'];
    }

    $assign = $db->prepare("INSERT IGNORE INTO customer_segment_assignments (customer_id, segment_id, is_automatic, assigned_by) VALUES (?, ?, 1, ?)");
    $assigned = 0;
    $adminId = $_SESSION['admin_id'] ?? 1;

    $now = new DateTime();
    foreach ($metrics as $m) {
        $cid = (int)$m['customer_id'];
        $spent = (float)$m['total_spent'];
        $visits = (int)$m['visit_count'];
        $lastActivity = new DateTime($m['last_activity_date'] ?? '1970-01-01');
        $createdAt = new DateTime($m['created_at'] ?? '1970-01-01');

        // VIP
        if ($segmentIds['vip'] && $spent >= $vip_min_spent) {
            $assign->execute([$cid, $segmentIds['vip'], $adminId]);
            if($assign->rowCount() > 0) $assigned++;
        }
        // Regular
        if ($segmentIds['regular'] && $visits >= $regular_min_visits) {
            $assign->execute([$cid, $segmentIds['regular'], $adminId]);
            if($assign->rowCount() > 0) $assigned++;
        }
        // New
        $diffNew = $now->diff($createdAt)->days;
        if ($segmentIds['new'] && $diffNew !== false && $diffNew <= $new_days) {
            $assign->execute([$cid, $segmentIds['new'], $adminId]);
            if($assign->rowCount() > 0) $assigned++;
        }
        // Inactive
        if ($segmentIds['inactive'] && $lastActivity < (clone $now)->modify("-{$inactive_days} days")) {
            $assign->execute([$cid, $segmentIds['inactive'], $adminId]);
            if($assign->rowCount() > 0) $assigned++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'تم تشغيل التصنيف التلقائي بنجاح',
        'assignments_made' => $assigned,
        'diagnostics' => $diagnostics,
        'thresholds' => [
            'vip_min_spent' => $vip_min_spent,
            'regular_min_visits' => $regular_min_visits,
            'new_days' => $new_days,
            'inactive_days' => $inactive_days
        ]
    ]);
}

function updateSegment($db) {
    parse_str(file_get_contents('php://input'), $data);

    $id = $data['id'] ?? 0;
    $name_ar = $data['name_ar'] ?? '';
    $description_ar = $data['description_ar'] ?? '';
    $color = $data['color'] ?? '#3B82F6';

    if (!$id || empty($name_ar)) {
        throw new Exception('البيانات المطلوبة غير مكتملة');
    }

    $stmt = $db->prepare("
        UPDATE customer_segments
        SET name_ar = ?, description_ar = ?, color = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([$name_ar, $description_ar, $color, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث الشريحة بنجاح'
    ]);
}

function deleteSegment($db) {
    parse_str(file_get_contents('php://input'), $data);
    $id = $data['id'] ?? 0;

    if (!$id) {
        throw new Exception('معرف الشريحة مطلوب');
    }

    // Check if segment has customers
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM customer_segment_assignments WHERE segment_id = ?");
    $count_stmt->execute([$id]);
    $customer_count = $count_stmt->fetchColumn();

    if ($customer_count > 0) {
        throw new Exception('لا يمكن حذف شريحة تحتوي على عملاء');
    }

    $stmt = $db->prepare("UPDATE customer_segments SET is_active = 0, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف الشريحة بنجاح'
    ]);
}
?>