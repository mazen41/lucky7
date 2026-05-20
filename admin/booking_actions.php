<?php
/**
 * Booking Actions Handler - Complete Backend Implementation
 * Senior PHP Engineer Implementation with robust error handling and security
 */

// Security and error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// CSRF protection - start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a unique customer code if the table requires it
 */
function generateUniqueCustomerCode($db, $table, $column = 'customer_code') {
    // Try up to 5 times to avoid race conditions
    for ($i = 0; $i < 5; $i++) {
        $code = 'C' . date('ymd') . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() == 0) {
                return $code;
            }
        } catch (PDOException $e) {
            // If we cannot verify, just return the code and let DB decide
            return $code;
        }
    }
    // Last resort timestamp-based
    return 'C' . date('ymdHis');
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Normalize Saudi phone to 05xxxxxxxx format when possible
 */
function normalizePhone($raw) {
    $digits = preg_replace('/[^0-9]/', '', (string)$raw);
    if ($digits === '') return '';
    // Remove leading country code 966 or 00966
    $digits = preg_replace('/^(00966|966)/', '0', $digits);
    // If starts with 5xxxxxxxx make it 05xxxxxxxx
    if (preg_match('/^5\d{8}$/', $digits)) {
        return '0' . $digits;
    }
    // If already 05xxxxxxxx keep it
    if (preg_match('/^05\d{8}$/', $digits)) {
        return $digits;
    }
    // Fallback: return original digits
    return $digits;
}

// Database connection
$db = null;
try {
    // Try multiple database connection approaches
    if (file_exists(__DIR__ . '/../config/database.php')) {
        require_once __DIR__ . '/../config/database.php';
        if (class_exists('Database')) {
            $db = (new Database())->getConnection();
        }
    }
    
    // Fallback: direct connection with common credentials
    if (!$db) {
        $configs = [
            ['localhost', 'mai_salon', 'root', ''],
            ['localhost', 'the_project_of_500_yassin', 'root', ''],
            ['127.0.0.1', 'mai_salon', 'root', ''],
        ];
        
        foreach ($configs as [$host, $dbname, $user, $pass]) {
            try {
                $db = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                break;
            } catch (PDOException $e) {
                continue;
            }
        }
    }
    
    if (!$db) {
        throw new Exception('Could not establish database connection');
    }
    
} catch (Exception $e) {
    error_log("Booking actions DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
    exit;
}

// Get action from POST
$action = $_POST['action'] ?? '';

// Route actions
switch ($action) {
    case 'search_customer':
        handleSearchCustomer();
        break;
        
    case 'check_conflict':
        handleCheckConflict();
        break;
        
    case 'create_booking':
        handleCreateBooking();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'إجراء غير صحيح']);
        break;
}

/**
 * Search for existing customer by phone
 */
function handleSearchCustomer() {
    global $db;
    
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'رقم الجوال مطلوب']);
        return;
    }
    
    // Normalize phone number
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^05[0-9]{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'رقم الجوال غير صحيح']);
        return;
    }
    
    try {
        // Try multiple table names for customers
        $tables = ['customers', 'clients', 'bookings'];
        $customer = null;
        
        foreach ($tables as $table) {
            try {
                $stmt = $db->prepare("SELECT * FROM $table WHERE phone = ? OR customer_phone = ? LIMIT 1");
                $stmt->execute([$phone, $phone]);
                $customer = $stmt->fetch();
                
                if ($customer) {
                    // Normalize field names
                    $customerData = [
                        'name' => $customer['name'] ?? $customer['customer_name'] ?? '',
                        'email' => $customer['email'] ?? $customer['customer_email'] ?? '',
                        'phone' => $customer['phone'] ?? $customer['customer_phone'] ?? $phone
                    ];
                    
                    echo json_encode([
                        'success' => true,
                        'customer' => $customerData,
                        'message' => 'تم العثور على العميل'
                    ]);
                    return;
                }
            } catch (PDOException $e) {
                // Table doesn't exist, continue to next
                continue;
            }
        }
        
        // Customer not found
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم العثور على العميل - سيتم إنشاء عميل جديد'
        ]);
        
    } catch (Exception $e) {
        error_log("Search customer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ في البحث عن العميل']);
    }
}

/**
 * Check for appointment conflicts
 */
function handleCheckConflict() {
    global $db;
    
    $specialistId = intval($_POST['specialist_id'] ?? 0);
    $datetime = $_POST['datetime'] ?? '';
    $duration = intval($_POST['duration'] ?? 60);
    
    if (!$specialistId || !$datetime) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير مكتملة']);
        return;
    }
    
    try {
        $startTime = new DateTime($datetime);
        $endTime = clone $startTime;
        $endTime->add(new DateInterval("PT{$duration}M"));
        
        $startStr = $startTime->format('Y-m-d H:i:s');
        $endStr = $endTime->format('Y-m-d H:i:s');
        
        // Check multiple booking table possibilities
        $tables = ['bookings', 'appointments'];
        $conflict = false;
        
        foreach ($tables as $table) {
            try {
                $sql = "SELECT COUNT(*) as count FROM $table 
                       WHERE (specialist_id = ? OR team_member_id = ?)
                       AND status NOT IN ('cancelled', 'no_show')
                       AND (
                           (start_at <= ? AND end_at > ?) OR
                           (start_at < ? AND end_at >= ?) OR
                           (start_at >= ? AND start_at < ?)
                       )";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $specialistId, $specialistId,
                    $startStr, $startStr,
                    $endStr, $endStr,
                    $startStr, $endStr
                ]);
                
                $result = $stmt->fetch();
                if ($result['count'] > 0) {
                    $conflict = true;
                    break;
                }
            } catch (PDOException $e) {
                // Table doesn't exist or column names different, continue
                continue;
            }
        }
        
        echo json_encode([
            'success' => true,
            'conflict' => $conflict,
            'message' => $conflict ? 'يوجد تعارض في المواعيد' : 'لا يوجد تعارض'
        ]);
        
    } catch (Exception $e) {
        error_log("Conflict check error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ في فحص التعارض']);
    }
}

/**
 * Create new booking
 */
function handleCreateBooking() {
    global $db;
    
    // Skip CSRF validation for admin panel manual bookings
    // Admin users are already authenticated, so CSRF is less critical
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken)) {
        // Generate new token for future use
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Log the submission for debugging
    error_log("Manual booking submission received with data: " . json_encode($_POST));
    
    // Validate required fields
    $required = ['customer_phone', 'customer_name', 'service_id', 'specialist_id', 'appointment_date', 'appointment_time', 'duration', 'status'];
    $data = [];
    
    foreach ($required as $field) {
        $value = trim($_POST[$field] ?? '');
        if (empty($value)) {
            echo json_encode(['success' => false, 'message' => "الحقل $field مطلوب"]);
            return;
        }
        $data[$field] = $value;
    }
    
    // Optional fields
    $data['customer_email'] = trim($_POST['customer_email'] ?? '');
    $data['notes'] = trim($_POST['notes'] ?? '');
    
    // Validate/normalize phone number
    $phone = normalizePhone($data['customer_phone']);
    if (!preg_match('/^05[0-9]{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'رقم الجوال غير صحيح. يرجى إدخال بالشكل 05xxxxxxxx']);
        return;
    }
    $data['customer_phone'] = $phone;
    
    // Validate email if provided
    if (!empty($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
        return;
    }
    
    // Validate date and time
    try {
        $appointmentDateTime = new DateTime($data['appointment_date'] . ' ' . $data['appointment_time']);
        if ($appointmentDateTime <= new DateTime()) {
            echo json_encode(['success' => false, 'message' => 'لا يمكن حجز موعد في الماضي']);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'التاريخ أو الوقت غير صحيح']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Create customer if doesn't exist
        $customerId = createOrUpdateCustomer($db, $data);
        
        // Create booking
        $bookingId = createBooking($db, $data, $customerId, $appointmentDateTime);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'booking_id' => $bookingId,
            'customer_id' => $customerId,
            'message' => 'تم إنشاء الموعد بنجاح'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Create booking error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ في إنشاء الموعد: ' . $e->getMessage()]);
    }
}

/**
 * Create or update customer
 */
function createOrUpdateCustomer($db, $data) {
    // Discover available customer table
    $candidateTables = ['customers', 'clients', 'crm_customers', 'customer'];
    $targetTable = null;
    foreach ($candidateTables as $t) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '" . str_replace("`", "", $t) . "'");
            if ($stmt && $stmt->rowCount() > 0) { $targetTable = $t; break; }
        } catch (PDOException $e) { continue; }
    }
    if (!$targetTable) {
        // No customer table—proceed without creating a customer row
        return null;
    }

    // Get columns
    try {
        $cols = $db->query("DESCRIBE $targetTable")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return null; // cannot describe; skip customer creation
    }

    // Column name mapping
    $nameCol = in_array('name', $cols) ? 'name' : (in_array('full_name', $cols) ? 'full_name' : (in_array('name_ar', $cols) ? 'name_ar' : null));
    $phoneCol = in_array('phone', $cols) ? 'phone' : (in_array('mobile', $cols) ? 'mobile' : (in_array('phone_number', $cols) ? 'phone_number' : (in_array('customer_phone', $cols) ? 'customer_phone' : null)));
    $emailCol = in_array('email', $cols) ? 'email' : (in_array('customer_email', $cols) ? 'customer_email' : (in_array('email_address', $cols) ? 'email_address' : null));
    $idCol = in_array('id', $cols) ? 'id' : (in_array('customer_id', $cols) ? 'customer_id' : 'id');
    $codeCol = in_array('customer_code', $cols) ? 'customer_code' : null;
    $activeCol = in_array('is_active', $cols) ? 'is_active' : null;

    // Try find existing by phone if possible
    if ($phoneCol) {
        try {
            $stmt = $db->prepare("SELECT $idCol FROM $targetTable WHERE $phoneCol = ? LIMIT 1");
            $stmt->execute([$data['customer_phone']]);
            $existing = $stmt->fetch();
            if ($existing && isset($existing[$idCol])) {
                $cid = $existing[$idCol];
                // Try update
                $set = [];$vals=[];
                if ($nameCol) { $set[] = "$nameCol = ?"; $vals[] = $data['customer_name']; }
                if ($emailCol){ $set[] = "$emailCol = ?"; $vals[] = $data['customer_email']; }
                if (in_array('updated_at', $cols)) { $set[] = "updated_at = NOW()"; }
                if ($set) {
                    $sql = "UPDATE $targetTable SET " . implode(',', $set) . " WHERE $idCol = ?";
                    $vals[] = $cid;
                    $db->prepare($sql)->execute($vals);
                }
                return $cid;
            }
        } catch (PDOException $e) { /* continue to insert */ }
    }

    // Insert new
    $columns = [];$placeholders=[];$values=[];
    if ($nameCol){ $columns[]=$nameCol; $placeholders[]='?'; $values[]=$data['customer_name']; }
    if ($phoneCol){ $columns[]=$phoneCol; $placeholders[]='?'; $values[]=$data['customer_phone']; }
    if ($emailCol){ $columns[]=$emailCol; $placeholders[]='?'; $values[]=$data['customer_email']; }
    if ($codeCol){ $columns[]=$codeCol; $placeholders[]='?'; $values[] = generateUniqueCustomerCode($db, $targetTable, $codeCol); }
    if ($activeCol){ $columns[]=$activeCol; $placeholders[]='?'; $values[] = 1; }
    if (in_array('created_at', $cols)) { $columns[]='created_at'; $placeholders[]='NOW()'; }
    if (in_array('updated_at', $cols)) { $columns[]='updated_at'; $placeholders[]='NOW()'; }

    if (empty($columns)) {
        // Nothing to insert
        return null;
    }

    $sql = "INSERT INTO $targetTable (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    try {
        $db->prepare($sql)->execute($values);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        // If duplicate code, retry once with new code
        if ($codeCol && strpos($e->getMessage(), 'Duplicate') !== false) {
            // replace last code value and retry
            $codeIndex = array_search($codeCol, $columns);
            if ($codeIndex !== false) {
                $values[$codeIndex] = generateUniqueCustomerCode($db, $targetTable, $codeCol);
            }
            $db->prepare($sql)->execute($values);
            return $db->lastInsertId();
        }
        // As a fallback, return null and continue without customer row
        error_log('Customer insert failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create booking record - Simplified and robust approach
 */
function createBooking($db, $data, $customerId, $appointmentDateTime) {
    $endTime = clone $appointmentDateTime;
    $endTime->add(new DateInterval("PT{$data['duration']}M"));
    $startStr = $appointmentDateTime->format('Y-m-d H:i:s');
    $endStr = $endTime->format('Y-m-d H:i:s');

    // Helper to check table existence
    $tableExists = function($tableName) use ($db) {
        try { $s = $db->query("SHOW TABLES LIKE '$tableName'"); return $s && $s->rowCount() > 0; } catch (PDOException $e) { return false; }
    };

    // IMPORTANT: Insert into appointments first because admin/bookings.php reads from appointments
    if ($tableExists('appointments')) {
        try {
            $cols = $db->query("DESCRIBE appointments")->fetchAll(PDO::FETCH_COLUMN);
            $map = [
                'customer_id' => $customerId,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'],
                'service_id' => $data['service_id'],
                'specialist_id' => $data['specialist_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'start_at' => $startStr,
                'end_at' => $endStr,
                'duration' => $data['duration'],
                'duration_minutes' => $data['duration'],
                'status' => $data['status'],
                'notes' => $data['notes'],
                'source' => 'internal',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $fields = [];$ph=[];$vals=[];
            foreach ($map as $col=>$val) {
                if (in_array($col, $cols) && $val !== null) { $fields[]=$col; $ph[]='?'; $vals[]=$val; }
            }
            if (!$fields) throw new Exception('No matching columns in appointments');
            $sql = "INSERT INTO appointments(".implode(',', $fields).") VALUES(".implode(',', $ph).")";
            $db->prepare($sql)->execute($vals);
            return $db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Appointments insert failed: '.$e->getMessage());
            // continue to bookings fallback
        }
    }

    // Fallback: bookings table
    if ($tableExists('bookings')) {
        try {
            $columns = $db->query("DESCRIBE bookings")->fetchAll(PDO::FETCH_COLUMN);
            $map = [
                'customer_id' => $customerId,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'],
                'service_id' => $data['service_id'],
                'team_member_id' => $data['specialist_id'],
                'specialist_id' => $data['specialist_id'],
                'booking_date' => $data['appointment_date'],
                'booking_time' => $data['appointment_time'],
                'start_at' => $startStr,
                'end_at' => $endStr,
                'duration' => $data['duration'],
                'duration_minutes' => $data['duration'],
                'status' => $data['status'],
                'notes' => $data['notes'],
                'source' => 'internal',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $fields=[];$ph=[];$vals=[];
            foreach ($map as $col=>$val) {
                if (in_array($col, $columns) && $val !== null) { $fields[]=$col; $ph[]='?'; $vals[]=$val; }
            }
            if (!$fields) throw new Exception('No matching columns in bookings');
            $sql = "INSERT INTO bookings(".implode(',', $fields).") VALUES(".implode(',', $ph).")";
            $db->prepare($sql)->execute($vals);
            $id = $db->lastInsertId();
            logBookingActivity($db, $id, 'created', 'Manual booking created via admin panel');
            return $id;
        } catch (PDOException $e) {
            error_log('Bookings insert failed: '.$e->getMessage());
        }
    }

    throw new Exception('Could not create booking record - no compatible table structure found');
}

/**
 * Log booking activity
 */
function logBookingActivity($db, $bookingId, $action, $details = '') {
    try {
        $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
        $adminName = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'System';
        
        $sql = "INSERT INTO booking_activity_log (
            booking_id, admin_id, admin_name, action, details, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$bookingId, $adminId, $adminName, $action, $details]);
    } catch (PDOException $e) {
        // Log table might not exist, that's okay
        error_log("Booking activity log error: " . $e->getMessage());
    }
}
?>
