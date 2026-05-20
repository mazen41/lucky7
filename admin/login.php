<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// If an admin is already logged in, send them to the admin dashboard.
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard_r.php'); // Admin dashboard
    exit();
}
// If a specialist is already logged in, send them to their dashboard.
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: dashboard_r.php'); // Specialist dashboard
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Query is good, gets both IDs needed
            $query = "SELECT u.id as user_id, u.name, u.email, u.password, u.role_id, r.role_name, tm.id as team_member_id 
                      FROM users u
                      LEFT JOIN team_members tm ON u.id = tm.user_id
                      JOIN roles r ON u.role_id = r.id
                      WHERE u.email = :username AND u.is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    
                    // Update last login
                    $updateLogin = "UPDATE users SET last_login = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateLogin);
                    $updateStmt->bindParam(':id', $user['user_id']);
                    $updateStmt->execute();

                    if (isset($user['role_name']) && $user['role_name'] === 'specialist') {
                        // --- THIS IS A SPECIALIST ---
                        $_SESSION['user_logged_in'] = true;
                        
                        // ==========================================================
                        // THE FIX: ALWAYS use the ID from the 'users' table.
                        // ==========================================================
                        $_SESSION['user_id'] = $user['user_id']; // THIS IS THE CORRECTED LINE
                        // ==========================================================

                        $_SESSION['user_name'] = $user['name'];
                        header('Location: dashboard_r.php'); 
                        exit();

                    } else {
                        // --- THIS IS AN ADMIN OR OTHER STAFF ROLE ---
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $user['user_id'];
                        $_SESSION['admin_username'] = $user['name'];
                        $_SESSION['admin_role_id'] = $user['role_id'];
                        header('Location: dashboard_r.php'); // Redirect to ADMIN dashboard
                        exit();
                    }

                } else {
                    $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
                }
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى.';
            error_log("Login error: " . $e->getMessage());
        }
    } else {
        $error = 'يرجى إدخال جميع البيانات المطلوبة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - Lu7cky</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'arabic': ['Tajawal', 'sans-serif'], },
                    colors: {
                        'lucky-offwhite': '#FAF3EB',
                        'lucky-teal': '#38C296',
                        'lucky-purple': '#480335',
                        'lucky-brown-medium': '#A87B4F',
                        'lucky-brown-dark': '#5F3813',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .form-input { transition: all 0.3s ease; }
        .form-input:focus-within { border-color: #38C296; box-shadow: 0 0 0 3px rgba(56, 194, 150, 0.2); }
        .brand-bg { background-image: url('https://images.unsplash.com/photo-1559925393-8be0ec4767c8?q=80&w=1887&auto=format&fit=crop'); background-size: cover; background-position: center; }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>
<body class="font-arabic bg-lucky-offwhite">
    <div class="flex min-h-screen">
        <!-- Form Section -->
        <div class="flex flex-1 flex-col justify-center items-center px-6 py-12 lg:flex-none lg:px-20 xl:px-24 bg-white">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <!-- Header -->
                <div>
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <div class="w-12 h-12 bg-gradient-to-br from-lucky-brown-dark to-lucky-brown-medium rounded-full flex items-center justify-center shadow-md">
                            <span class="text-white font-bold text-xl">L7</span>
                        </div>
                        <span class="text-4xl font-extrabold text-lucky-purple">Lu7cky</span>
                    </div>
                    <h2 class="mt-8 text-3xl font-bold leading-9 tracking-tight text-lucky-purple">لوحة التحكم</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-500">مرحباً بعودتك! قم بتسجيل الدخول لإدارة الكافيه.</p>
                </div>

                <!-- Form -->
                <div class="mt-10">
                    <div>
                        <form method="POST" class="space-y-6" onsubmit="handleFormSubmit(this)">
                            <!-- Error Message -->
                            <?php if (!empty($error)): ?>
                            <div class="bg-red-50 border-r-4 border-red-500 text-red-700 px-4 py-3 rounded-lg" role="alert">
                                <p class="font-bold">خطأ في الدخول</p>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Email Input -->
                            <div>
                                <label for="username" class="block text-sm font-medium leading-6 text-gray-900">البريد الإلكتروني</label>
                                <div class="mt-2 relative form-input border border-gray-300 rounded-md">
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="text" id="username" name="username" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" placeholder="manager@lu7cky.com">
                                </div>
                            </div>

                            <!-- Password Input -->
                            <div>
                                <label for="password" class="block text-sm font-medium leading-6 text-gray-900">كلمة المرور</label>
                                <div class="mt-2 relative form-input border border-gray-300 rounded-md">
                                     <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" id="password" name="password" required class="w-full pr-10 pl-4 py-3 bg-transparent focus:outline-none" placeholder="••••••••••••">
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div>
                                <button type="submit" class="btn-submit flex w-full justify-center items-center rounded-md bg-gradient-to-r from-lucky-purple to-lucky-teal px-3 py-3 text-sm font-semibold leading-6 text-white shadow-sm hover:opacity-90 transition-all duration-300 transform hover:scale-105">
                                    <span class="button-text">تسجيل الدخول</span>
                                    <i class="fas fa-spinner fa-spin button-loader hidden ml-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-8 text-center text-sm text-gray-500">
                        <a href="../index.php" class="font-semibold text-lucky-brown-medium hover:text-lucky-brown-dark">
                            <i class="fas fa-arrow-right ml-1"></i>
                            العودة إلى الموقع الرئيسي
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Branding Section -->
        <div class="relative hidden w-0 flex-1 lg:block">
            <div class="absolute inset-0 h-full w-full object-cover brand-bg">
                <div class="absolute inset-0 bg-lucky-purple opacity-60"></div>
            </div>
            <div class="relative z-10 flex flex-col justify-end h-full p-12 text-white">
                <h2 class="text-4xl font-bold">بوابتك لعالم القهوة المختصة</h2>
                <p class="mt-4 text-lg opacity-80">تحكم كامل في منتجاتك، طلباتك، وعملائك من مكان واحد.</p>
            </div>
        </div>
    </div>

    <script>
        function handleFormSubmit(form) {
            const button = form.querySelector('.btn-submit');
            const buttonText = button.querySelector('.button-text');
            const buttonLoader = button.querySelector('.button-loader');
            button.disabled = true;
            buttonText.classList.add('hidden');
            buttonLoader.classList.remove('hidden');
        }
    </script>
</body>
</html>