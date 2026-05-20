// <?php
// // login_as_user.php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// session_start();

// require_once 'auth.php'; // Contains checkAdminAuth()
// require_once '../config/database.php';
// require_once 'users_functions.php';

// checkAdminAuth();

// // 1. Check if the admin has permission
// if (!hasPermission('users.login_as')) {
//     $_SESSION['error'] = 'ليس لديك الصلاحية لتنفيذ هذا الإجراء.';
//     header('Location: hr_add_specialist.php');
//     exit;
// }

// // 2. Get the team member ID from the URL
// $target_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
// if (!$target_id) {
//     $_SESSION['error'] = 'معرف الموظف الهدف غير صالح.';
//     header('Location: hr_add_specialist.php');
//     exit;
// }

// try {
//     $database = new Database();
//     $db = $database->getConnection();

//     // 3. Get employee info by ID
//     $stmt = $db->prepare("SELECT id, name_ar FROM team_members WHERE id = ?");
//     $stmt->execute([$target_id]);
//     $employee = $stmt->fetch(PDO::FETCH_ASSOC);

//     if (!$employee) {
//         throw new Exception("الموظف المستهدف غير موجود.");
//     }

//     // 4. Store admin info for later return
//     $_SESSION['original_admin_id'] = $_SESSION['admin_id'] ?? null;
//     $_SESSION['original_admin_username'] = $_SESSION['admin_username'] ?? null;

//     // 5. Clear admin session
//     unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_logged_in'], $_SESSION['admin_permissions'], $_SESSION['admin_role']);

//     // 6. Set employee session
//     $_SESSION['user_id'] = $employee['id'];       // This is team_members.id now
//     $_SESSION['user_name'] = $employee['name_ar'];
//     $_SESSION['user_logged_in'] = true;

//     // Save session and redirect
//     session_write_close();

//     header('Location: dashboard.php');
//     exit;

// } catch (Exception $e) {
//     $_SESSION['error'] = 'حدث خطأ أثناء محاولة الدخول: ' . $e->getMessage();
//     header('Location: hr_add_specialist.php');
//     exit;
// }
