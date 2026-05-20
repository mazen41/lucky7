<?php
/**
 * Marketing Campaign API - MAI Salon
 * RESTful API for campaign management and tracking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once 'marketing_segmentation.php';
require_once 'marketing_messaging.php';

// Simple API authentication
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
if (!$api_key || $api_key !== 'mai_salon_marketing_2024') {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$segmentation = new MarketingSegmentation($db);
$messaging = new MarketingMessaging($db);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Get the endpoint from path
$endpoint = end($path_parts);

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($endpoint, $db, $segmentation, $messaging);
            break;
            
        case 'POST':
            handlePostRequest($endpoint, $db, $segmentation, $messaging);
            break;
            
        case 'PUT':
            handlePutRequest($endpoint, $db, $segmentation, $messaging);
            break;
            
        case 'DELETE':
            handleDeleteRequest($endpoint, $db, $segmentation, $messaging);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($endpoint, $db, $segmentation, $messaging) {
    switch ($endpoint) {
        case 'campaigns':
            getCampaigns($db);
            break;
            
        case 'segments':
            getSegments($db, $segmentation);
            break;
            
        case 'campaign':
            $id = $_GET['id'] ?? null;
            if ($id) {
                getCampaign($db, $id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Campaign ID required']);
            }
            break;
            
        case 'segment-customers':
            $segment_id = $_GET['segment_id'] ?? null;
            if ($segment_id) {
                getSegmentCustomers($db, $segmentation, $segment_id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Segment ID required']);
            }
            break;
            
        case 'campaign-stats':
            $campaign_id = $_GET['campaign_id'] ?? null;
            if ($campaign_id) {
                getCampaignStats($db, $messaging, $campaign_id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Campaign ID required']);
            }
            break;
            
        case 'delivery-report':
            $campaign_id = $_GET['campaign_id'] ?? null;
            if ($campaign_id) {
                getDeliveryReport($messaging, $campaign_id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Campaign ID required']);
            }
            break;
            
        case 'customer-messages':
            $customer_id = $_GET['customer_id'] ?? null;
            if ($customer_id) {
                getCustomerMessages($messaging, $customer_id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Customer ID required']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePostRequest($endpoint, $db, $segmentation, $messaging) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'campaign':
            createCampaign($db, $input);
            break;
            
        case 'send-campaign':
            sendCampaign($db, $segmentation, $messaging, $input);
            break;
            
        case 'segment':
            createSegment($db, $input);
            break;
            
        case 'message':
            sendMessage($messaging, $input);
            break;
            
        case 'mark-read':
            markMessageRead($messaging, $input);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePutRequest($endpoint, $db, $segmentation, $messaging) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'campaign':
            updateCampaign($db, $input);
            break;
            
        case 'segment':
            updateSegment($db, $input);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handleDeleteRequest($endpoint, $db, $segmentation, $messaging) {
    switch ($endpoint) {
        case 'campaign':
            $id = $_GET['id'] ?? null;
            if ($id) {
                deleteCampaign($db, $id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Campaign ID required']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

// API Functions

function getCampaigns($db) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? '';
    
    $where_clause = '';
    $params = [];
    
    if ($status) {
        $where_clause = 'WHERE status = ?';
        $params[] = $status;
    }
    
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM campaign_audience WHERE campaign_id = c.id) as audience_count,
               (SELECT COUNT(*) FROM customer_messages WHERE campaign_id = c.id AND status = 'read') as read_count
        FROM campaigns c
        {$where_clause}
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM campaigns {$where_clause}");
    $count_params = $status ? [$status] : [];
    $count_stmt->execute($count_params);
    $total = $count_stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => $campaigns,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getCampaign($db, $id) {
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM campaign_audience WHERE campaign_id = c.id) as audience_count,
               (SELECT COUNT(*) FROM customer_messages WHERE campaign_id = c.id AND status = 'read') as read_count
        FROM campaigns c
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($campaign) {
        echo json_encode(['success' => true, 'data' => $campaign]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
    }
}

function createCampaign($db, $input) {
    $required = ['name', 'message_title', 'message_content', 'campaign_type'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field {$field} is required"]);
            return;
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO campaigns (name, description, message_title, message_content, campaign_type, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $success = $stmt->execute([
        $input['name'],
        $input['description'] ?? '',
        $input['message_title'],
        $input['message_content'],
        $input['campaign_type'],
        $input['created_by'] ?? 1
    ]);
    
    if ($success) {
        $campaign_id = $db->lastInsertId();
        echo json_encode(['success' => true, 'campaign_id' => $campaign_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create campaign']);
    }
}

function sendCampaign($db, $segmentation, $messaging, $input) {
    $campaign_id = $input['campaign_id'] ?? null;
    $segment_id = $input['segment_id'] ?? null;
    
    if (!$campaign_id || !$segment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID and Segment ID are required']);
        return;
    }
    
    // Get campaign details
    $campaign_stmt = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
    $campaign_stmt->execute([$campaign_id]);
    $campaign = $campaign_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        return;
    }
    
    // Get segment customers
    $segment_stmt = $db->prepare("SELECT rules FROM customer_segments WHERE id = ?");
    $segment_stmt->execute([$segment_id]);
    $rules = $segment_stmt->fetchColumn();
    
    if (!$rules) {
        http_response_code(404);
        echo json_encode(['error' => 'Segment not found']);
        return;
    }
    
    $customers = $segmentation->applySegmentRules($rules);
    
    if (empty($customers)) {
        echo json_encode(['success' => true, 'message' => 'No customers match the segment criteria', 'sent_count' => 0]);
        return;
    }
    
    // Send messages
    $result = $messaging->sendCampaignMessages(
        $campaign_id,
        $customers,
        $campaign['message_title'],
        $campaign['message_content'],
        $input['sender_id'] ?? 1
    );
    
    if ($result['success']) {
        // Update campaign status
        $update_stmt = $db->prepare("
            UPDATE campaigns 
            SET status = 'sent', sent_at = NOW(), total_recipients = ?
            WHERE id = ?
        ");
        $update_stmt->execute([$result['sent_count'], $campaign_id]);
        
        echo json_encode([
            'success' => true,
            'sent_count' => $result['sent_count'],
            'failed_count' => $result['failed_count'],
            'total_count' => $result['total_count']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $result['error']]);
    }
}

function getSegments($db, $segmentation) {
    $stmt = $db->query("
        SELECT *, 
               CASE 
                   WHEN last_updated IS NULL OR last_updated < DATE_SUB(NOW(), INTERVAL 1 HOUR)
                   THEN 'outdated'
                   ELSE 'current'
               END as count_status
        FROM customer_segments 
        WHERE is_active = 1 
        ORDER BY name
    ");
    
    $segments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update outdated counts
    foreach ($segments as &$segment) {
        if ($segment['count_status'] === 'outdated') {
            $segment['customer_count'] = $segmentation->updateSegmentCount($segment['id']);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $segments]);
}

function getSegmentCustomers($db, $segmentation, $segment_id) {
    $segment_stmt = $db->prepare("SELECT rules FROM customer_segments WHERE id = ?");
    $segment_stmt->execute([$segment_id]);
    $rules = $segment_stmt->fetchColumn();
    
    if (!$rules) {
        http_response_code(404);
        echo json_encode(['error' => 'Segment not found']);
        return;
    }
    
    $customers = $segmentation->applySegmentRules($rules);
    echo json_encode(['success' => true, 'data' => $customers]);
}

function getCampaignStats($db, $messaging, $campaign_id) {
    $stats = $messaging->getMessageStats($campaign_id);
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getDeliveryReport($messaging, $campaign_id) {
    $report = $messaging->getDeliveryReport($campaign_id);
    if (isset($report['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $report['error']]);
    } else {
        echo json_encode(['success' => true, 'data' => $report]);
    }
}

function sendMessage($messaging, $input) {
    $required = ['customer_id', 'title', 'content'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field {$field} is required"]);
            return;
        }
    }
    
    $result = $messaging->sendMessage(
        $input['customer_id'],
        $input['title'],
        $input['content'],
        $input['type'] ?? 'general',
        $input['sender_id'] ?? null
    );
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}

function getCustomerMessages($messaging, $customer_id) {
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    $unread_only = filter_var($_GET['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $messages = $messaging->getCustomerMessages($customer_id, $limit, $offset, $unread_only);
    echo json_encode(['success' => true, 'data' => $messages]);
}

function markMessageRead($messaging, $input) {
    $message_id = $input['message_id'] ?? null;
    $customer_id = $input['customer_id'] ?? null;
    
    if (!$message_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Message ID is required']);
        return;
    }
    
    $result = $messaging->markAsRead($message_id, $customer_id);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
}

function updateCampaign($db, $input) {
    $id = $input['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID is required']);
        return;
    }
    
    $fields = [];
    $params = [];
    
    $allowed_fields = ['name', 'description', 'message_title', 'message_content', 'campaign_type', 'status'];
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            $fields[] = "{$field} = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    $params[] = $id;
    
    $stmt = $db->prepare("UPDATE campaigns SET " . implode(', ', $fields) . " WHERE id = ?");
    $success = $stmt->execute($params);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found or no changes made']);
    }
}

function createSegment($db, $input) {
    $required = ['name', 'segment_type', 'rules'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field {$field} is required"]);
            return;
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO customer_segments (name, description, segment_type, rules, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $success = $stmt->execute([
        $input['name'],
        $input['description'] ?? '',
        $input['segment_type'],
        is_array($input['rules']) ? json_encode($input['rules']) : $input['rules'],
        $input['created_by'] ?? 1
    ]);
    
    if ($success) {
        $segment_id = $db->lastInsertId();
        echo json_encode(['success' => true, 'segment_id' => $segment_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create segment']);
    }
}

function updateSegment($db, $input) {
    $id = $input['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Segment ID is required']);
        return;
    }
    
    $fields = [];
    $params = [];
    
    $allowed_fields = ['name', 'description', 'segment_type', 'rules', 'is_active'];
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            if ($field === 'rules' && is_array($input[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = json_encode($input[$field]);
            } else {
                $fields[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    $params[] = $id;
    
    $stmt = $db->prepare("UPDATE customer_segments SET " . implode(', ', $fields) . " WHERE id = ?");
    $success = $stmt->execute($params);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Segment not found or no changes made']);
    }
}

function deleteCampaign($db, $id) {
    // Check if campaign has been sent
    $check_stmt = $db->prepare("SELECT status FROM campaigns WHERE id = ?");
    $check_stmt->execute([$id]);
    $status = $check_stmt->fetchColumn();
    
    if (!$status) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        return;
    }
    
    if ($status === 'sent') {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete sent campaigns']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM campaigns WHERE id = ?");
    $success = $stmt->execute([$id]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete campaign']);
    }
}
?>
