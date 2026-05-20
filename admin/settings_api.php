<?php
require_once 'auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');
checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Ensure settings table exists (idempotent)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(191) PRIMARY KEY,
        `value` TEXT NULL,
        `type` VARCHAR(50) NOT NULL DEFAULT 'string'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // ignore here; handled later in actions
}

try {
    switch ($action) {
        case 'get_all':
            $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings");
            $stmt->execute();
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['value'];
                switch ($row['type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'decimal':
                        $value = (float) $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                $settings[$row['key']] = $value;
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'update_single':
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';
            $type = $_POST['type'] ?? 'string';
            
            if (empty($key)) {
                throw new Exception('Setting key is required');
            }
            
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)");
            $stmt->execute([$key, $value, $type]);
            
            echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
            break;

        case 'update_multiple':
            $settings = json_decode($_POST['settings'] ?? '{}', true);
            
            if (empty($settings)) {
                throw new Exception('No settings provided');
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)");
            
            foreach ($settings as $key => $setting) {
                $value = $setting['value'];
                $type = $setting['type'] ?? 'string';
                
                // Convert boolean values
                if ($type === 'boolean') {
                    $value = $value ? '1' : '0';
                }
                
                $stmt->execute([$key, $value, $type]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
            break;

        case 'get_business_info':
            $keys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_twitter', 'company_instagram'];
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            
            $info = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $info[$row['key']] = $row['value'];
            }
            echo json_encode(['success' => true, 'info' => $info]);
            break;

        case 'update_business_info':
            $info = [
                'company_name' => $_POST['company_name'] ?? '',
                'company_address' => $_POST['company_address'] ?? '',
                'company_phone' => $_POST['company_phone'] ?? '',
                'company_email' => $_POST['company_email'] ?? '',
                'company_twitter' => $_POST['company_twitter'] ?? '',
                'company_instagram' => $_POST['company_instagram'] ?? ''
            ];
            
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, 'string') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            
            foreach ($info as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Business information updated successfully']);
            break;

        case 'get_tax_settings':
            $keys = ['tax_enabled', 'tax_inclusive', 'tax_rate', 'tax_name'];
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['value'];
                if ($row['type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($row['type'] === 'decimal') {
                    $value = (float) $value;
                }
                $settings[$row['key']] = $value;
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'update_tax_settings':
            // Map radio group tax_type => tax_inclusive boolean
            $tax_type = $_POST['tax_type'] ?? 'inclusive';
            $tax_inclusive = $tax_type === 'inclusive' ? '1' : '0';
            $tax_rate = isset($_POST['tax_rate']) ? number_format((float)$_POST['tax_rate'], 2, '.', '') : '15.00';
            $tax_name = trim($_POST['tax_name'] ?? 'ضريبة القيمة المضافة');
            $settings = [
                'tax_enabled' => ['value' => isset($_POST['tax_enabled']) ? '1' : '0', 'type' => 'boolean'],
                'tax_inclusive' => ['value' => $tax_inclusive, 'type' => 'boolean'],
                'tax_rate' => ['value' => $tax_rate, 'type' => 'decimal'],
                'tax_name' => ['value' => $tax_name, 'type' => 'string']
            ];
            
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)");
            
            foreach ($settings as $key => $setting) {
                $stmt->execute([$key, $setting['value'], $setting['type']]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Tax settings updated successfully']);
            break;

        case 'get_pos_settings':
            $keys = ['pos_require_open_shift_for_cash', 'pos_auto_print_receipt', 'pos_default_payment_method'];
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['value'];
                if ($row['type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                $settings[$row['key']] = $value;
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'update_pos_settings':
            $settings = [
                'pos_require_open_shift_for_cash' => ['value' => isset($_POST['pos_require_open_shift_for_cash']) ? '1' : '0', 'type' => 'boolean'],
                'pos_auto_print_receipt' => ['value' => isset($_POST['pos_auto_print_receipt']) ? '1' : '0', 'type' => 'boolean'],
                'pos_default_payment_method' => ['value' => $_POST['pos_default_payment_method'] ?? 'cash', 'type' => 'string']
            ];
            
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)");
            
            foreach ($settings as $key => $setting) {
                $stmt->execute([$key, $setting['value'], $setting['type']]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'POS settings updated successfully']);
            break;

        case 'get_loyalty_settings':
            $keys = ['loyalty_enabled', 'loyalty_discount_priority', 'loyalty_points_per_riyal', 'loyalty_riyal_per_point'];
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['value'];
                if ($row['type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($row['type'] === 'integer') {
                    $value = (int) $value;
                } elseif ($row['type'] === 'decimal') {
                    $value = (float) $value;
                }
                $settings[$row['key']] = $value;
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'update_loyalty_settings':
            $settings = [
                'loyalty_enabled' => ['value' => isset($_POST['loyalty_enabled']) ? '1' : '0', 'type' => 'boolean'],
                'loyalty_discount_priority' => ['value' => $_POST['loyalty_discount_priority'] ?? 'tier_first', 'type' => 'string'],
                'loyalty_points_per_riyal' => ['value' => $_POST['loyalty_points_per_riyal'] ?? '1', 'type' => 'integer'],
                'loyalty_riyal_per_point' => ['value' => $_POST['loyalty_riyal_per_point'] ?? '0.01', 'type' => 'decimal']
            ];
            
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)");
            
            foreach ($settings as $key => $setting) {
                $stmt->execute([$key, $setting['value'], $setting['type']]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Loyalty settings updated successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
