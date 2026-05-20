<?php
/**
 * HR Management - Manage Employees & Specialists (ARABIC VERSION)
 * REVISED & ENHANCED VERSION
 * Combines CRUD, Service Assignments, Working Hours, and Deletion with Appointment Reassignment.
 * Includes UI improvements, a show/hide password feature, and logic to create missing user accounts on edit.
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// --- AJAX ACTION: Check for Specialist's Appointments ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_appointments') {
    header('Content-Type: application/json');
    $specialist_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$specialist_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Specialist ID']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE specialist_id = ? AND start_at > NOW()");
        $stmt->execute([$specialist_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $appointment_count = $result['count'] ?? 0;

        if ($appointment_count > 0) {
            $stmt = $db->prepare("SELECT id, name_ar FROM team_members WHERE is_specialist = 1 AND is_active = 1 AND id != ? ORDER BY name_ar");
            $stmt->execute([$specialist_id]);
            $other_specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'has_appointments' => true,
                'appointment_count' => $appointment_count,
                'other_specialists' => $other_specialists
            ]);
        } else {
            echo json_encode(['has_appointments' => false]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// --- Helper function to handle working hours submission ---
function handle_working_hours($db, $specialist_id, $working_hours_data) {
    $delete_stmt = $db->prepare("DELETE FROM specialist_working_hours WHERE specialist_id = ?");
    $delete_stmt->execute([$specialist_id]);

    if (empty($working_hours_data)) {
        return;
    }

    $insert_stmt = $db->prepare(
        "INSERT INTO specialist_working_hours (specialist_id, day_of_week, open_time, close_time, is_working) 
         VALUES (?, ?, ?, ?, ?)"
    );

    for ($i = 0; $i < 7; $i++) {
        $day_data = $working_hours_data[$i] ?? null;
        $is_working = isset($day_data['is_working']) ? 1 : 0;
        
        $open_time = !empty($day_data['open_time']) ? $day_data['open_time'] : null;
        $close_time = !empty($day_data['close_time']) ? $day_data['close_time'] : null;

        if ($is_working && $open_time && $close_time) {
            $insert_stmt->execute([$specialist_id, $i, $open_time, $close_time, 1]);
        } else {
            $insert_stmt->execute([$specialist_id, $i, null, null, 0]);
        }
    }
}

// --- Helper function to handle image uploads ---
function handle_image_upload($file_input, $current_path = 'uploads/default.png') {
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input];
        $filename = uniqid() . '-' . basename($file['name']);
        $destination = UPLOAD_PATH . $filename;
        if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Optionally delete the old image if it's not the default one
            if ($current_path && $current_path !== 'uploads/default.png' && file_exists(__DIR__ . '/../' . $current_path)) {
                @unlink(__DIR__ . '/../' . $current_path);
            }
            return 'uploads/' . $filename;
        } else {
            throw new Exception('فشل رفع الملف.');
        }
    }
    return $current_path; // Return the existing path if no new file is uploaded
}

// --- CRUD ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login_as') {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            $_SESSION['original_admin_id'] = $_SESSION['admin_id'];
            unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role_id']);
            
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $_POST['specialist_tm_id'];
            $_SESSION['user_name'] = $_POST['specialist_name'];

            header('Location: specialist_dashboard.php');
            exit;
        }
    }

    $db->beginTransaction();
    try {
        switch ($action) {
            case 'add':
                if (empty($_POST['password'])) throw new Exception('كلمة المرور مطلوبة عند إضافة مستخدم جديد.');
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) throw new Exception('البريد الإلكتروني مستخدم بالفعل.');

                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['name_ar'], $_POST['email'], $hashed_password, $_POST['role_id'], $_POST['is_active']]);
                $new_user_id = $db->lastInsertId();

                $image_path = handle_image_upload('image');

                $stmt = $db->prepare("INSERT INTO team_members (user_id, name_ar, position_ar, salary, is_specialist, is_active, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$new_user_id, $_POST['name_ar'], $_POST['position_ar'], $_POST['salary'] ?: 0.00, $_POST['is_specialist'], $_POST['is_active'], $image_path]);
                $new_team_member_id = $db->lastInsertId();

                if ($_POST['is_specialist'] == 1) {
                    if (!empty($_POST['assigned_services'])) {
                        $assign_stmt = $db->prepare("INSERT INTO specialist_services (specialist_id, service_id) VALUES (?, ?)");
                        foreach ($_POST['assigned_services'] as $service_id) {
                            $assign_stmt->execute([$new_team_member_id, $service_id]);
                        }
                    }
                    if (!empty($_POST['working_hours'])) {
                        handle_working_hours($db, $new_team_member_id, $_POST['working_hours']);
                    }
                }
                $_SESSION['message'] = 'تم إضافة الموظف بنجاح.';
                break;

            case 'update':
                $team_member_id = $_POST['id'];
                $user_id = empty($_POST['user_id']) ? null : $_POST['user_id'];

                // Logic for creating a user account if it doesn't exist for the team member
                if (is_null($user_id)) {
                    if (empty($_POST['email'])) throw new Exception('البريد الإلكتروني مطلوب لإنشاء حساب للموظف.');
                    if (empty($_POST['password'])) throw new Exception('كلمة المرور مطلوبة لإنشاء حساب للموظف.');
                    
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) throw new Exception('البريد الإلكتروني مستخدم بالفعل.');

                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt_create_user = $db->prepare("INSERT INTO users (name, email, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
                    $stmt_create_user->execute([$_POST['name_ar'], $_POST['email'], $hashed_password, $_POST['role_id'], $_POST['is_active']]);
                    $user_id = $db->lastInsertId();
                    
                    $stmt_link_user = $db->prepare("UPDATE team_members SET user_id = ? WHERE id = ?");
                    $stmt_link_user->execute([$user_id, $team_member_id]);
                } else {
                    // Standard user update for an existing user account
                    // First, check if the email is being changed and if the new one is already taken by someone else
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$_POST['email'], $user_id]);
                    if ($stmt->fetch()) {
                        throw new Exception('البريد الإلكتروني مستخدم بالفعل من قبل مستخدم آخر.');
                    }

                    // Build the UPDATE query for the 'users' table dynamically
                    $user_update_sql = "UPDATE users SET name = ?, email = ?, role_id = ?, is_active = ?";
                    $user_params = [
                        $_POST['name_ar'],
                        $_POST['email'],
                        $_POST['role_id'],
                        $_POST['is_active']
                    ];

                    // Add password to the query ONLY if a new one is provided
                    if (!empty($_POST['password'])) {
                        $user_update_sql .= ", password = ?";
                        $user_params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }

                    $user_update_sql .= " WHERE id = ?";
                    $user_params[] = $user_id;

                    $stmt = $db->prepare($user_update_sql);
                    $stmt->execute($user_params);
                }
                
                // Update team_members table
                $image_path = handle_image_upload('image', $_POST['current_image']);
                $stmt = $db->prepare("UPDATE team_members SET name_ar = ?, position_ar = ?, salary = ?, is_specialist = ?, is_active = ?, image = ? WHERE id = ?");
                $stmt->execute([$_POST['name_ar'], $_POST['position_ar'], $_POST['salary'] ?: 0.00, $_POST['is_specialist'], $_POST['is_active'], $image_path, $team_member_id]);

                // Update specialist-specific info
                $db->prepare("DELETE FROM specialist_services WHERE specialist_id = ?")->execute([$team_member_id]);
                
                if ($_POST['is_specialist'] == 1) {
                    if (!empty($_POST['assigned_services'])) {
                        $assign_stmt = $db->prepare("INSERT INTO specialist_services (specialist_id, service_id) VALUES (?, ?)");
                        foreach ($_POST['assigned_services'] as $service_id) {
                            $assign_stmt->execute([$team_member_id, $service_id]);
                        }
                    }
                    handle_working_hours($db, $team_member_id, $_POST['working_hours'] ?? []);
                } else {
                    // If they are no longer a specialist, clear their working hours
                    $db->prepare("DELETE FROM specialist_working_hours WHERE specialist_id = ?")->execute([$team_member_id]);
                }
                $_SESSION['message'] = 'تم تحديث بيانات الموظف بنجاح.';
                break;
            
            case 'delete':
                $team_member_id = $_POST['id'];
                $reassign_to_id = !empty($_POST['reassign_to']) ? $_POST['reassign_to'] : null;

                $stmt_user = $db->prepare("SELECT user_id FROM team_members WHERE id = ?");
                $stmt_user->execute([$team_member_id]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
                if (!$user) throw new Exception("لم يتم العثور على الموظف.");
                $user_id_to_delete = $user['user_id'];

                if ($reassign_to_id) {
                    $stmt_new_name = $db->prepare("SELECT name_ar FROM team_members WHERE id = ?");
                    $stmt_new_name->execute([$reassign_to_id]);
                    $new_specialist = $stmt_new_name->fetch(PDO::FETCH_ASSOC);
                    if (!$new_specialist) throw new Exception("الأخصائي البديل غير موجود.");
                    
                    $stmt_update = $db->prepare("UPDATE appointments SET specialist_id = ?, specialist_name = ? WHERE specialist_id = ? AND start_at > NOW()");
                    $stmt_update->execute([$reassign_to_id, $new_specialist['name_ar'], $team_member_id]);
                } else {
                    $stmt_delete_app = $db->prepare("DELETE FROM appointments WHERE specialist_id = ? AND start_at > NOW()");
                    $stmt_delete_app->execute([$team_member_id]);
                }
                
                $db->prepare("DELETE FROM specialist_services WHERE specialist_id = ?")->execute([$team_member_id]);
                $db->prepare("DELETE FROM specialist_working_hours WHERE specialist_id = ?")->execute([$team_member_id]);
                
                $db->prepare("DELETE FROM team_members WHERE id = ?")->execute([$team_member_id]);
                if ($user_id_to_delete) {
                    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id_to_delete]);
                }

                $_SESSION['message'] = 'تم حذف الموظف بنجاح.';
                break;
        }
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }
    header('Location: hr_add_specialist.php');
    exit;
}

// --- DATA FETCHING ---
try {
    $stmt = $db->prepare("
        SELECT 
            tm.*, u.email, u.role_id, r.role_name_ar, u.id as user_id,
            GROUP_CONCAT(DISTINCT ss.service_id) as assigned_services_ids,
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'day_of_week', swh.day_of_week, 
                        'open_time', swh.open_time, 
                        'close_time', swh.close_time, 
                        'open_time_12', DATE_FORMAT(swh.open_time, '%l:%i %p'),
                        'close_time_12', DATE_FORMAT(swh.close_time, '%l:%i %p'),
                        'is_working', swh.is_working
                    ) ORDER BY swh.day_of_week ASC
                ) 
                FROM specialist_working_hours swh 
                WHERE swh.specialist_id = tm.id
            ) as working_hours_json
        FROM team_members tm
        LEFT JOIN users u ON tm.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN specialist_services ss ON tm.id = ss.specialist_id
        GROUP BY tm.id
        ORDER BY tm.created_at DESC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $roles = $db->query("SELECT id, role_name_ar FROM roles ORDER BY role_name_ar ASC")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $db->query("SELECT id, name_ar FROM service_categories ORDER BY name_ar ASC")->fetchAll(PDO::FETCH_ASSOC);
    $services = $db->query("SELECT id, name_ar, category_id FROM services WHERE is_active = 1 ORDER BY name_ar ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $employees = $roles = $categories = $services = [];
    $page_error = "خطأ في جلب البيانات: " . $e->getMessage();
}

$page_title = 'إدارة الموظفين';
ob_start();
?>
<div class="container mx-auto p-4 lg:p-6">

    <!-- Messages & Errors -->
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md" role="alert"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <div x-data="employeeManager(<?= htmlspecialchars(json_encode($employees)); ?>, <?= htmlspecialchars(json_encode($roles)); ?>, <?= htmlspecialchars(json_encode($services)); ?>, <?= htmlspecialchars(json_encode($categories)); ?>)">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm mb-6 p-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
            <div class="flex items-center gap-2">
                <a href="hr_dashboard.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 font-semibold text-sm">العودة</a>
                <button @click="openAddModal()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown font-semibold text-sm">إضافة موظف</button>
            </div>
        </div>

        <!-- Employee Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <template x-for="employee in employees" :key="employee.id">
                <div class="bg-white rounded-xl shadow-md border p-5 flex flex-col text-center items-center transition-all duration-300 hover:shadow-xl hover:border-mai-brown">
                    <img :src="employee.image ? '/' + employee.image : '/uploads/default.png'" alt="صورة الموظف" class="w-28 h-28 rounded-full object-cover mb-4 border-4 border-gray-100">
                    <h3 class="font-bold text-lg text-gray-900" x-text="employee.name_ar"></h3>
                    <p class="text-sm text-gray-500 mb-4" x-text="employee.position_ar"></p>
                    
                    <div class="text-sm w-full mt-2 space-y-2 text-right">
                        <div class="flex justify-between items-center py-1 border-b"><span>الحالة:</span><span class="px-2 py-1 rounded-full text-xs font-semibold" :class="employee.is_active == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" x-text="employee.is_active == 1 ? 'نشط' : 'غير نشط'"></span></div>
                        <div class="flex justify-between items-center py-1 border-b"><span>أخصائية:</span><span class="font-bold" :class="employee.is_specialist == 1 ? 'text-mai-brown' : 'text-gray-500'" x-text="employee.is_specialist == 1 ? 'نعم' : 'لا'"></span></div>
                        <div class="flex justify-between items-center py-1"><span>الراتب:</span><span class="font-semibold text-gray-700" x-text="`${parseFloat(employee.salary).toFixed(2)} SAR`"></span></div>
                    </div>

                    <template x-if="employee.is_specialist == 1 && employee.working_hours_json">
                        <div class="mt-4 pt-3 border-t w-full text-right text-xs space-y-1">
                            <h4 class="font-semibold text-gray-700 mb-2">أوقات الدوام:</h4>
                            <template x-for="day in JSON.parse(employee.working_hours_json)">
                                <div class="flex justify-between items-center text-gray-600" :class="!day.is_working && 'opacity-40'">
                                    <span class="font-semibold" x-text="daysOfWeek[day.day_of_week]"></span>
                                    <span class="font-mono tracking-wider" x-text="day.is_working ? `${day.open_time_12} - ${day.close_time_12}` : 'إجازة'"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                    
                    <div class="mt-auto pt-4 border-t w-full flex justify-center items-center gap-2">
                        <button @click="openEditModal(employee)" class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-md hover:bg-blue-600 text-sm font-semibold">تعديل</button>
                        <button @click="confirmDelete(employee)" class="flex-1 bg-red-500 text-white px-3 py-2 rounded-md hover:bg-red-600 text-sm font-semibold">حذف</button>
                        <template x-if="employee.is_specialist == 1 && employee.user_id">
                            <form method="POST" action="hr_add_specialist.php" class="inline-block flex-1">
                                <input type="hidden" name="action" value="login_as">
                                <input type="hidden" name="specialist_tm_id" :value="employee.id">
                                <input type="hidden" name="specialist_name" :value="employee.name_ar">
                                <button type="submit" class="w-full bg-green-500 text-white px-3 py-2 rounded-md hover:bg-green-600 text-sm font-semibold">دخول</button>
                            </form>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Add/Edit Modal -->
        <div x-show="isModalOpen" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4" x-cloak>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col" @click.outside="isModalOpen = false">
                <div class="p-5 border-b flex justify-between items-center"><h2 class="text-xl font-bold text-gray-800" x-text="formMethod === 'add' ? 'إضافة موظف جديد' : 'تعديل بيانات الموظف'"></h2><button @click="isModalOpen = false" class="text-gray-500 hover:text-gray-800">&times;</button></div>
                <form :action="formAction" method="POST" enctype="multipart/form-data" class="flex-grow overflow-y-auto">
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-5">
                        <!-- Left Column: Basic Info -->
                        <div class="space-y-4">
                            <input type="hidden" name="action" :value="formMethod"><input type="hidden" name="id" x-model="formData.id"><input type="hidden" name="user_id" x-model="formData.user_id"><input type="hidden" name="current_image" x-model="formData.image">
                            <div><label class="font-medium text-sm text-gray-700">الاسم الكامل</label><input type="text" name="name_ar" x-model="formData.name_ar" required class="mt-1 w-full p-2 border rounded-md focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"></div>
                            <div><label class="font-medium text-sm text-gray-700">المسمى الوظيفي</label><input type="text" name="position_ar" x-model="formData.position_ar" required class="mt-1 w-full p-2 border rounded-md focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"></div>
                            <!-- === EDITED: REMOVED :disabled ATTRIBUTE === -->
                            <div><label class="font-medium text-sm text-gray-700">البريد الإلكتروني (للدخول)</label><input type="email" name="email" x-model="formData.email" required class="mt-1 w-full p-2 border rounded-md focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"></div>
                            
                            <div>
                                <label class="font-medium text-sm text-gray-700">كلمة المرور <span class="text-xs text-gray-500" x-show="formMethod === 'update'">(اتركه فارغاً لعدم التغيير)</span></label>
                                <div class="relative mt-1">
                                    <input :type="showPassword ? 'text' : 'password'" name="password" id="password" class="w-full p-2 border rounded-md focus:ring-2 focus:ring-mai-brown focus:border-mai-brown pr-10">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500">
                                        <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 1.274-4.057 5.064-7 9.542-7 .847 0 1.673.123 2.468.35M21 12a9.753 9.753 0 01-4.125 6.175M18 18l-3-3m0 0l-3-3m3 3l-3 3m3-3l3-3" /></svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div><label class="font-medium text-sm text-gray-700">الراتب</label><input type="number" step="0.01" name="salary" x-model="formData.salary" class="mt-1 w-full p-2 border rounded-md focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"></div>
                            <div><label class="font-medium text-sm text-gray-700">الدور والصلاحيات</label><select name="role_id" x-model="formData.role_id" required class="mt-1 w-full p-2 border rounded-md bg-white focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"><template x-for="role in roles"><option :value="role.id" x-text="role.role_name_ar"></option></template></select></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="font-medium text-sm text-gray-700">أخصائية؟</label><select name="is_specialist" x-model="formData.is_specialist" class="mt-1 w-full p-2 border rounded-md bg-white focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"><option value="1">نعم</option><option value="0">لا</option></select></div>
                                <div><label class="font-medium text-sm text-gray-700">حالة الحساب</label><select name="is_active" x-model="formData.is_active" class="mt-1 w-full p-2 border rounded-md bg-white focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"><option value="1">نشط</option><option value="0">غير نشط</option></select></div>
                            </div>
                            <div><label class="font-medium text-sm text-gray-700">صورة الموظف</label><input type="file" name="image" class="mt-1 w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-mai-brown-light file:text-mai-brown hover:file:bg-mai-brown/20"></div>
                        </div>

                        <!-- Right Column: Specialist Info -->
                        <div class="space-y-6 lg:border-r lg:pr-8" x-show="formData.is_specialist == 1" x-transition>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-3">الخدمات المسندة</h3>
                                <div class="border rounded-md p-3 mt-2 max-h-48 overflow-y-auto space-y-2 bg-gray-50">
                                    <template x-for="service in services" :key="service.id">
                                        <label class="flex items-center space-x-3 space-x-reverse p-2 rounded-md hover:bg-gray-100 cursor-pointer"><input type="checkbox" name="assigned_services[]" :value="service.id" x-model="formData.assigned_services" class="rounded text-mai-brown focus:ring-mai-brown"><span class="text-sm" x-text="service.name_ar"></span></label>
                                    </template>
                                </div>
                            </div>
                            <div class="space-y-3 pt-4 border-t">
                                <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-3">أوقات الدوام</h3>
                                <div class="space-y-2">
                                    <template x-for="(day, index) in formData.working_hours" :key="index">
                                        <div class="grid grid-cols-4 items-center gap-3 p-2 rounded-md" :class="day.is_working ? 'bg-green-50' : 'bg-gray-100'">
                                            <div class="flex items-center col-span-1"><input type="checkbox" :name="`working_hours[${index}][is_working]`" x-model="day.is_working" class="ml-2 rounded text-mai-brown focus:ring-mai-brown"><label class="font-semibold text-sm" x-text="daysOfWeek[index]"></label></div>
                                            <div class="col-span-3 grid grid-cols-2 gap-2">
                                                <input type="time" :name="`working_hours[${index}][open_time]`" x-model="day.open_time" :disabled="!day.is_working" class="w-full text-sm p-1 border rounded-md disabled:bg-gray-200 disabled:cursor-not-allowed">
                                                <input type="time" :name="`working_hours[${index}][close_time]`" x-model="day.close_time" :disabled="!day.is_working" class="w-full text-sm p-1 border rounded-md disabled:bg-gray-200 disabled:cursor-not-allowed">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-gray-50 border-t flex justify-end gap-3">
                        <button type="button" @click="isModalOpen = false" class="px-5 py-2 border rounded-lg bg-white hover:bg-gray-100 font-semibold">إلغاء</button>
                        <button type="submit" class="bg-mai-brown text-white px-8 py-2 rounded-lg hover:bg-mai-dark-brown font-semibold">حفظ</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Delete Confirmation Modal -->
        <div x-show="isDeleteModalOpen" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4" x-cloak>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg" @click.outside="isDeleteModalOpen = false">
                <div class="p-6 text-center">
                    <div class="w-16 h-16 rounded-full bg-red-100 p-4 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">هل أنت متأكد؟</h3>

                    <template x-if="!deleteModal.hasAppointments">
                        <p class="text-gray-600 mt-2">سيتم حذف الموظف <strong x-text="specialistToDelete ? specialistToDelete.name_ar : ''"></strong> بشكل نهائي. هذا الإجراء لا يمكن التراجع عنه.</p>
                    </template>
                    <template x-if="deleteModal.hasAppointments">
                        <div class="mt-4 text-sm bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p class="font-semibold text-yellow-800">تحذير: هذا الأخصائي لديه <strong x-text="deleteModal.appointmentCount"></strong> مواعيد قادمة.</p>
                            <p class="mt-2 text-yellow-700">لحذف الأخصائي، يمكنك إعادة تعيين هذه المواعيد لأخصائي آخر. إذا لم تقم بإعادة التعيين، سيتم حذف جميع مواعيده القادمة.</p>
                            <div class="mt-4">
                                <label for="reassignSpecialist" class="block text-sm font-medium text-gray-700 text-right mb-1">إعادة التعيين إلى:</label>
                                <select id="reassignSpecialist" x-model="deleteModal.reassignTo" class="w-full p-2 border rounded-md bg-white focus:ring-2 focus:ring-mai-brown"><option value="">-- عدم إعادة التعيين (سيتم حذف المواعيد) --</option><template x-for="specialist in deleteModal.otherSpecialists" :key="specialist.id"><option :value="specialist.id" x-text="specialist.name_ar"></option></template></select>
                            </div>
                        </div>
                    </template>
                </div>
                <form method="POST" action="hr_add_specialist.php">
                    <div class="px-6 py-4 bg-gray-50 border-t flex justify-center gap-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" :value="specialistToDelete ? specialistToDelete.id : ''">
                        <input type="hidden" name="reassign_to" x-model="deleteModal.reassignTo">
                        <button type="button" @click="isDeleteModalOpen = false" class="px-6 py-2 border rounded-lg bg-white hover:bg-gray-100 font-semibold">إلغاء</button>
                        <button type="submit" class="bg-red-600 text-white px-8 py-2 rounded-lg hover:bg-red-700 font-semibold">تأكيد الحذف</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    function employeeManager(employeesData, rolesData, servicesData, categoriesData) {
        return {
            employees: employeesData,
            roles: rolesData,
            services: servicesData,
            categories: categoriesData,
            daysOfWeek: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
            isModalOpen: false,
            isDeleteModalOpen: false,
            showPassword: false,
            specialistToDelete: null,
            deleteModal: {
                hasAppointments: false,
                appointmentCount: 0,
                otherSpecialists: [],
                reassignTo: ''
            },
            formAction: 'hr_add_specialist.php',
            formMethod: 'add',
            formData: {},
            
            init() { this.resetForm(); },

            openAddModal() {
                this.resetForm();
                this.formMethod = 'add';
                this.isModalOpen = true;
                this.showPassword = false;
            },

            openEditModal(employee) {
                this.resetForm();
                this.formMethod = 'update';
                this.formData = JSON.parse(JSON.stringify(employee));
                this.formData.assigned_services = this.formData.assigned_services_ids ? this.formData.assigned_services_ids.split(',').map(Number) : [];
                
                if (this.formData.working_hours_json) {
                    const savedHours = JSON.parse(this.formData.working_hours_json);
                    let formHours = this.createDefaultWorkingHours();
                    savedHours.forEach(day => {
                        formHours[day.day_of_week] = {
                            is_working: day.is_working == 1,
                            open_time: day.open_time ? day.open_time.substring(0, 5) : '09:00',
                            close_time: day.close_time ? day.close_time.substring(0, 5) : '17:00'
                        };
                    });
                    this.formData.working_hours = formHours;
                } else {
                     this.formData.working_hours = this.createDefaultWorkingHours();
                }
                
                if (!this.formData.user_id) {
                    this.formData.email = '';
                }

                this.isModalOpen = true;
                this.showPassword = false;
            },
            
            resetForm() {
                this.formData = { 
                    id: null, user_id: null, name_ar: '', position_ar: '', salary: 0.00, 
                    is_active: 1, is_specialist: 0, email: '', role_id: this.roles.length > 0 ? this.roles[0].id : '', 
                    image: '', assigned_services: [], working_hours: this.createDefaultWorkingHours()
                };
            },

            createDefaultWorkingHours() {
                let hours = [];
                for (let i = 0; i < 7; i++) {
                    hours.push({
                        is_working: (i !== 5), // Friday off by default
                        open_time: '10:00',
                        close_time: '22:00'
                    });
                }
                return hours;
            },

            async confirmDelete(employee) {
                this.specialistToDelete = employee;
                this.deleteModal = { hasAppointments: false, appointmentCount: 0, otherSpecialists: [], reassignTo: '' };

                if (employee.is_specialist != 1) {
                    this.isDeleteModalOpen = true;
                    return;
                }

                try {
                    const response = await fetch(`hr_add_specialist.php?action=check_appointments&id=${employee.id}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();
                    if (data.error) throw new Error('Server error: ' + data.error);

                    if (data.has_appointments) {
                        this.deleteModal.hasAppointments = true;
                        this.deleteModal.appointmentCount = data.appointment_count;
                        this.deleteModal.otherSpecialists = data.other_specialists;
                    }
                } catch (error) {
                    console.error('Failed to check appointments:', error);
                    this.deleteModal.hasAppointments = false; 
                } finally {
                    this.isDeleteModalOpen = true;
                }
            }
        }
    }
</script>
<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>