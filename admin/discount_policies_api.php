<?php
require_once 'auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all_roles':
            // Fetches all roles for a dropdown menu, excluding super_admin.
            $stmt = $db->prepare("SELECT role_name, role_name_ar FROM roles ORDER BY id");
            $stmt->execute();
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'roles' => $roles]);
            break;

        case 'get_all':
            // Uses a LEFT JOIN to get all roles and their corresponding policies.
            // This ensures all roles are listed, even if no policy is set for them yet.
            $query = "
                SELECT
                    r.role_name,
                    r.role_name_ar,
                    p.id,
                    p.max_discount_percent,
                    p.max_discount_amount,
                    p.reason_required,
                    p.is_active
                FROM
                    roles r
                LEFT JOIN
                    role_discount_policies p ON r.role_name = p.role_name
                ORDER BY
                    r.id
            ";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $policies_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'policies' => $policies_data]);
            break;

        case 'get_policy_for_role':
            $role = $_GET['role'] ?? '';
            if (empty($role)) {
                throw new Exception('Role is required');
            }
            
            // First, try to get the existing policy.
            $stmt = $db->prepare("SELECT * FROM role_discount_policies WHERE role_name = ?");
            $stmt->execute([$role]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$policy) {
                // If no policy exists, get role details from the main roles table.
                $stmt_role = $db->prepare("SELECT role_name_ar FROM roles WHERE role_name = ?");
                $stmt_role->execute([$role]);
                $role_details = $stmt_role->fetch(PDO::FETCH_ASSOC);
                $role_name_ar = $role_details ? $role_details['role_name_ar'] : $role;

                // Return a default structure for a NEW policy for the frontend.
                $policy = [
                    'id' => null, // No ID yet
                    'role_name' => $role,
                    'role_name_ar' => $role_name_ar,
                    'max_discount_percent' => '', // Empty to not suggest a value
                    'max_discount_amount' => '',
                    'reason_required' => 0,
                    'is_active' => 1
                ];
            }
            
            echo json_encode(['success' => true, 'policy' => $policy]);
            break;

        case 'update':
            $role_name = $_POST['role_name'] ?? '';
            $role_name_ar = $_POST['role_name_ar'] ?? '';
            // Handle potentially empty string for amounts, saving 0 or NULL appropriately.
            $max_discount_percent = !empty($_POST['max_discount_percent']) ? (float)$_POST['max_discount_percent'] : 0;
            $max_discount_amount = !empty($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : null;
            $reason_required = isset($_POST['reason_required']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($role_name)) throw new Exception('Role name is required');
            if ($max_discount_percent < 0 || $max_discount_percent > 100) throw new Exception('Discount percentage must be between 0 and 100');
            if ($max_discount_amount !== null && $max_discount_amount < 0) throw new Exception('Discount amount must be positive');
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE to create or update the policy.
            $stmt = $db->prepare("
                INSERT INTO role_discount_policies 
                (role_name, role_name_ar, max_discount_percent, max_discount_amount, reason_required, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                role_name_ar = VALUES(role_name_ar),
                max_discount_percent = VALUES(max_discount_percent),
                max_discount_amount = VALUES(max_discount_amount),
                reason_required = VALUES(reason_required),
                is_active = VALUES(is_active)
            ");
            
            $stmt->execute([$role_name, $role_name_ar, $max_discount_percent, $max_discount_amount, $reason_required, $is_active]);
            
            echo json_encode(['success' => true, 'message' => 'Discount policy updated successfully']);
            break;

        case 'validate_discount':
            $role = $_POST['role'] ?? '';
            $discount_percent = (float) ($_POST['discount_percent'] ?? 0);
            $discount_amount = (float) ($_POST['discount_amount'] ?? 0);
            $subtotal = (float) ($_POST['subtotal'] ?? 0);
            
            if (empty($role)) {
                throw new Exception('Role is required');
            }
            
            // Get the active policy for the given role.
            $stmt = $db->prepare("SELECT * FROM role_discount_policies WHERE role_name = ? AND is_active = 1");
            $stmt->execute([$role]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$policy) {
                throw new Exception('No active discount policy found for this role');
            }
            
            $validation = [
                'valid' => true,
                'errors' => [],
                'max_percent' => $policy['max_discount_percent'],
                'max_amount' => $policy['max_discount_amount'],
                'reason_required' => (bool) $policy['reason_required']
            ];
            
            // Calculate actual discount amount if only percentage is given.
            if ($discount_percent > 0 && $subtotal > 0) {
                $calculated_amount = ($subtotal * $discount_percent) / 100;
                if ($discount_amount == 0) {
                    $discount_amount = $calculated_amount;
                }
            }
            
            // Validate percentage against the policy.
            if ($discount_percent > $policy['max_discount_percent']) {
                $validation['valid'] = false;
                $validation['errors'][] = "Maximum allowed discount is {$policy['max_discount_percent']}%";
            }
            
            // Validate amount against the policy.
            if ($policy['max_discount_amount'] !== null && $discount_amount > $policy['max_discount_amount']) {
                $validation['valid'] = false;
                $validation['errors'][] = "Maximum allowed discount amount is {$policy['max_discount_amount']} SAR";
            }
            
            echo json_encode(['success' => true, 'validation' => $validation]);
            break;

        case 'delete':
            $role_name = $_POST['role_name'] ?? '';
            if (empty($role_name)) {
                throw new Exception('Role name is required');
            }
            
            // Protect default roles from deletion.
            $protected_roles = ['receptionist', 'specialist', 'manager', 'partner'];
            if (in_array($role_name, $protected_roles)) {
                throw new Exception('Cannot delete default role policies');
            }
            
            $stmt = $db->prepare("DELETE FROM role_discount_policies WHERE role_name = ?");
            $stmt->execute([$role_name]);
            
            echo json_encode(['success' => true, 'message' => 'Discount policy deleted successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Set a proper HTTP status code for errors.
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>