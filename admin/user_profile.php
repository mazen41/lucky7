<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Check if a user (specialist) or admin is logged in. If not, redirect to login.
if (!isset($_SESSION['user_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Determine user type and get their ID from the correct session variable
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$user_id_in_users_table = null;
$team_member_id = null;
$user_name = '';
$user_email = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($is_admin) {
        $user_id_in_users_table = $_SESSION['admin_id'];
    } else {
        // For specialists, we have team_member_id in session. We need to find their user_id.
        $team_member_id = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT user_id FROM team_members WHERE id = :team_member_id");
        $stmt->bindParam(':team_member_id', $team_member_id);
        $stmt->execute();
        $user_id_in_users_table = $stmt->fetchColumn();
    }

    if (!$user_id_in_users_table) {
        // If we can't find the user, log them out for safety.
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Fetch current user data to display in the form
    $stmt = $db->prepare("SELECT name, email FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id_in_users_table);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_name = $user['name'];
        $user_email = $user['email'];
    }

} catch (Exception $e) {
    $error = 'حدث خطأ في الاتصال بقاعدة البيانات.';
    error_log("Profile Page DB Error: " . $e->getMessage());
}


// --- FORM PROCESSING LOGIC ---
$profile_success_message = '';
$profile_error_message = '';
$password_success_message = '';
$password_error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($db)) {

    // --- Handle Profile Information Update ---
    if (isset($_POST['update_profile'])) {
        $newName = trim($_POST['name']);
        $newEmail = trim($_POST['email']);

        if (empty($newName) || empty($newEmail)) {
            $profile_error_message = 'الاسم والبريد الإلكتروني حقول مطلوبة.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $profile_error_message = 'صيغة البريد الإلكتروني غير صحيحة.';
        } else {
            try {
                // Check if the new email already exists for another user
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
                $stmt->bindParam(':email', $newEmail);
                $stmt->bindParam(':user_id', $user_id_in_users_table);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $profile_error_message = 'هذا البريد الإلكتروني مستخدم بالفعل.';
                } else {
                    $updateStmt = $db->prepare("UPDATE users SET name = :name, email = :email WHERE id = :user_id");
                    $updateStmt->bindParam(':name', $newName);
                    $updateStmt->bindParam(':email', $newEmail);
                    $updateStmt->bindParam(':user_id', $user_id_in_users_table);
                    
                    if ($updateStmt->execute()) {
                        $profile_success_message = 'تم تحديث بيانات الملف الشخصي بنجاح.';
                        // Update session name if it exists
                        if(isset($_SESSION['user_name'])) $_SESSION['user_name'] = $newName;
                        if(isset($_SESSION['admin_username'])) $_SESSION['admin_username'] = $newName;
                        // Refresh the variables for the form
                        $user_name = $newName;
                        $user_email = $newEmail;
                    } else {
                        $profile_error_message = 'حدث خطأ أثناء تحديث البيانات.';
                    }
                }
            } catch (Exception $e) {
                $profile_error_message = 'حدث خطأ في النظام.';
                error_log("Profile Update Error: " . $e->getMessage());
            }
        }
    }

    // --- Handle Password Change ---
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $password_error_message = 'يرجى ملء جميع حقول كلمة المرور.';
        } elseif ($newPassword !== $confirmPassword) {
            $password_error_message = 'كلمتا المرور الجديدتان غير متطابقتين.';
        } elseif (strlen($newPassword) < 8) {
            $password_error_message = 'يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل.';
        } else {
            try {
                // First, get the current hashed password from the DB
                $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id");
                $stmt->bindParam(':user_id', $user_id_in_users_table);
                $stmt->execute();
                $stored_hash = $stmt->fetchColumn();

                // Verify the current password
                if (password_verify($currentPassword, $stored_hash)) {
                    // Hash the new password
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update the password in the database
                    $updateStmt = $db->prepare("UPDATE users SET password = :new_password WHERE id = :user_id");
                    $updateStmt->bindParam(':new_password', $newPasswordHash);
                    $updateStmt->bindParam(':user_id', $user_id_in_users_table);

                    if ($updateStmt->execute()) {
                        $password_success_message = 'تم تغيير كلمة المرور بنجاح.';
                    } else {
                        $password_error_message = 'حدث خطأ أثناء تغيير كلمة المرور.';
                    }
                } else {
                    $password_error_message = 'كلمة المرور الحالية غير صحيحة.';
                }
            } catch (Exception $e) {
                $password_error_message = 'حدث خطأ في النظام.';
                error_log("Password Change Error: " . $e->getMessage());
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - لوحة التحكم</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'arabic': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        'mai-brown': '#8B4513',
                        'mai-gold': '#D4AF37',
                        'mai-cream': '#FDF8F0',
                        'mai-dark': '#4a2c0f',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .form-input-container {
            transition: all 0.3s ease;
        }
        .form-input-container:focus-within {
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="font-arabic bg-mai-cream">
    <div class="flex items-center justify-center min-h-screen p-6">
        <div class="w-full max-w-4xl mx-auto">
            
            <!-- Header -->
            <div class="text-center mb-10">
                 <div class="flex items-center justify-center space-x-2 space-x-reverse mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-mai-brown to-mai-gold rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-user text-white text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-4xl font-bold text-mai-dark">ملفي الشخصي</h1>
                <p class="text-gray-500 mt-2">إدارة معلومات حسابك وتفاصيل تسجيل الدخول.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Profile Information Form -->
                <div class="bg-white p-8 rounded-2xl shadow-lg">
                    <h2 class="text-2xl font-bold text-mai-dark mb-6 border-b pb-4">تعديل البيانات الشخصية</h2>
                    
                    <form method="POST" class="space-y-6">
                        <!-- Profile Messages -->
                        <?php if ($profile_success_message): ?>
                            <div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 rounded-lg" role="alert">
                                <p><?php echo htmlspecialchars($profile_success_message); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($profile_error_message): ?>
                            <div class="bg-red-50 border-r-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                                <p><?php echo htmlspecialchars($profile_error_message); ?></p>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="name" class="block text-sm font-medium leading-6 text-gray-900">الاسم الكامل</label>
                            <div class="mt-2 relative form-input-container border border-gray-300 rounded-md">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-user"></i></span>
                                <input type="text" id="name" name="name" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" value="<?php echo htmlspecialchars($user_name); ?>">
                            </div>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium leading-6 text-gray-900">البريد الإلكتروني</label>
                            <div class="mt-2 relative form-input-container border border-gray-300 rounded-md">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" id="email" name="email" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" value="<?php echo htmlspecialchars($user_email); ?>">
                            </div>
                        </div>
                        <div>
                            <button type="submit" name="update_profile" class="btn-submit flex w-full justify-center items-center rounded-md bg-gradient-to-r from-mai-brown to-mai-gold px-3 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90 transition-all duration-300 transform hover:scale-105">
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="bg-white p-8 rounded-2xl shadow-lg">
                     <h2 class="text-2xl font-bold text-mai-dark mb-6 border-b pb-4">تغيير كلمة المرور</h2>
                    <form method="POST" class="space-y-6">
                         <!-- Password Messages -->
                        <?php if ($password_success_message): ?>
                            <div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 rounded-lg" role="alert">
                                <p><?php echo htmlspecialchars($password_success_message); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($password_error_message): ?>
                            <div class="bg-red-50 border-r-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                                <p><?php echo htmlspecialchars($password_error_message); ?></p>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="current_password" class="block text-sm font-medium leading-6 text-gray-900">كلمة المرور الحالية</label>
                            <div class="mt-2 relative form-input-container border border-gray-300 rounded-md">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" id="current_password" name="current_password" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" placeholder="••••••••">
                            </div>
                        </div>
                        <div>
                            <label for="new_password" class="block text-sm font-medium leading-6 text-gray-900">كلمة المرور الجديدة</label>
                            <div class="mt-2 relative form-input-container border border-gray-300 rounded-md">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-key"></i></span>
                                <input type="password" id="new_password" name="new_password" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" placeholder="••••••••">
                            </div>
                        </div>
                         <div>
                            <label for="confirm_password" class="block text-sm font-medium leading-6 text-gray-900">تأكيد كلمة المرور الجديدة</label>
                            <div class="mt-2 relative form-input-container border border-gray-300 rounded-md">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-key"></i></span>
                                <input type="password" id="confirm_password" name="confirm_password" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" placeholder="••••••••">
                            </div>
                        </div>
                        <div>
                            <button type="submit" name="change_password" class="btn-submit flex w-full justify-center items-center rounded-md bg-mai-dark px-3 py-3 text-sm font-semibold text-white shadow-sm hover:bg-opacity-90 transition-all duration-300">
                                تغيير كلمة المرور
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- Action Buttons -->
            <div class="mt-10 text-center flex flex-col sm:flex-row items-center justify-center gap-4">
                 <a href="dashboard.php" class="w-full sm:w-auto inline-block bg-mai-brown text-white px-8 py-3 rounded-lg font-semibold hover:bg-mai-dark transition-colors duration-300">
                    <i class="fas fa-tachometer-alt ml-2"></i>
                    العودة إلى لوحة التحكم
                </a>
                 <a href="user_attendance.php" class="w-full sm:w-auto inline-block bg-white text-mai-brown border border-mai-brown px-8 py-3 rounded-lg font-semibold hover:bg-mai-brown hover:text-white transition-colors duration-300">
                    <i class="fas fa-clipboard-user ml-2"></i>
                    سجل الحضور والانصراف
                </a>
                <a href="logout.php" class="w-full sm:w-auto inline-block text-gray-600 hover:text-mai-dark font-semibold transition-colors duration-300">
                    <i class="fas fa-arrow-right-from-bracket ml-2"></i>
                    تسجيل الخروج
                </a>
            </div>

        </div>
    </div>
</body>
</html>