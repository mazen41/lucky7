<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Standard Translations and Language Setup ---
$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');
$t = [
    'en' => ['title' => 'My Profile', 'header' => 'My Profile', 'update_success' => 'Profile updated successfully.', 'update_error' => 'Error updating profile: ', 'profile_not_found' => 'Your profile could not be found. Please contact administration.', 'employee_id' => 'Employee ID', 'base_salary' => 'Base Salary', 'name_english' => 'Name (English)', 'email' => 'Email Address', 'save_changes' => 'Save Changes', 'currency' => 'SAR'],
    'ar' => ['title' => 'ملفي الشخصي', 'header' => 'ملفي الشخصي', 'update_success' => 'تم تحديث الملف الشخصي بنجاح.', 'update_error' => 'حدث خطأ أثناء التحديث: ', 'profile_not_found' => 'لا يمكن العثور على ملفك الشخصي. يرجى التواصل مع الإدارة.', 'employee_id' => 'رقم الموظف', 'base_salary' => 'الراتب الأساسي', 'name_english' => 'الاسم (إنجليزي)', 'email' => 'البريد الإلكتروني', 'save_changes' => 'حفظ التعديلات', 'currency' => 'ر.س']
];
$t_sidebar = [
    'en' => ['employee_portal' => 'Employee Portal', 'home' => 'Home', 'my_profile' => 'My Profile', 'attendance' => 'Attendance', 'leaves' => 'Leaves', 'performance' => 'Performance', 'payroll' => 'Payroll', 'requests' => 'Requests', 'logout' => 'Logout'],
    'ar' => ['employee_portal' => 'بوابة الموظفين', 'home' => 'الرئيسية', 'my_profile' => 'ملفي الشخصي', 'attendance' => 'الحضور', 'leaves' => 'الإجازات', 'performance' => 'الأداء', 'payroll' => 'الرواتب', 'requests' => 'الطلبات', 'logout' => 'تسجيل الخروج']
];
// ---------------------------------------------------

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 1. GET THE ID FROM THE SESSION
$user_id_from_session = $_SESSION['user_id'];

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// --- UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        $stmt_users = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt_users->execute([$_POST['email'], $user_id_from_session]);
        $stmt_tm = $db->prepare("UPDATE team_members SET name_en = ? WHERE user_id = ?");
        $stmt_tm->execute([$_POST['name_en'], $user_id_from_session]);
        $db->commit();
        $_SESSION['message'] = $t[$lang]['update_success'];
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $t[$lang]['update_error'] . $e->getMessage();
    }
    header('Location: profile.php');
    exit;
}

// --- DATA FETCHING LOGIC ---
$stmt_user = $db->prepare("SELECT id, email, name FROM users WHERE id = ?");
$stmt_user->execute([$user_id_from_session]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    session_destroy();
    header('Location: login.php?error=user_not_found');
    exit;
}

$stmt_tm = $db->prepare("SELECT name_en, position_ar, salary, image FROM team_members WHERE user_id = ?");
$stmt_tm->execute([$user_id_from_session]);
$team_member_data = $stmt_tm->fetch(PDO::FETCH_ASSOC);

$profile = array_merge($user_data, $team_member_data ?: []);
$display_name = ($lang === 'ar' && !empty($profile['name'])) ? $profile['name'] : ($profile['name_en'] ?? $profile['name'] ?? 'Employee');

// --- AVATAR LOGIC ---
$profile_image = isset($profile['image']) && !empty($profile['image']) ? $profile['image'] : null;
$profile_initial = '';
if (!$profile_image && !empty($display_name)) {
    // Safely get the first character of a multi-byte string (like Arabic)
    $profile_initial = mb_strtoupper(mb_substr($display_name, 0, 1, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $is_rtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t[$lang]['title'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: '<?= $is_rtl ? 'Cairo' : 'Poppins' ?>', sans-serif; background-color: #f3f4f6; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(<?= $is_rtl ? '100%' : '-100%' ?>); } .sidebar.active { transform: translateX(0); } }
        @media (min-width: 768px) { .main-content { margin-<?= $is_rtl ? 'right' : 'left' ?>: 16rem; } }
        
        .form-input { transition: all 0.3s ease; }
        .form-input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); outline: none; }
        
        .fade-in-up { opacity: 0; transform: translateY(20px); animation: fadeInUp 0.6s ease-out forwards; }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="antialiased">

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar bg-gray-800 text-gray-200 w-64 p-4 fixed <?= $is_rtl ? 'right-0' : 'left-0' ?> top-0 z-50 md:translate-x-0 h-full flex flex-col shadow-lg">
            <div class="text-center mb-10">
                <a href="dashboard_r.php" class="text-2xl font-bold text-white tracking-wider"><?= $t_sidebar[$lang]['employee_portal'] ?></a>
            </div>
            <nav class="flex-grow">
                 <a href="dashboard_r.php" class="flex items-center p-3 my-1 hover:bg-gray-700 hover:text-white rounded-lg transition-colors"><i class="fas fa-home fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['home'] ?></a>
                <a href="profile.php" class="flex items-center p-3 my-1 bg-gray-900 text-white rounded-lg font-bold"><i class="fas fa-user fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['my_profile'] ?></a>
                <a href="attendance.php" class="flex items-center p-3 my-1 hover:bg-gray-700 hover:text-white rounded-lg transition-colors"><i class="fas fa-clock fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['attendance'] ?></a>
                <a href="leaves.php" class="flex items-center p-3 my-1 hover:bg-gray-700 hover:text-white rounded-lg transition-colors"><i class="fas fa-calendar-alt fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['leaves'] ?></a>
                <a href="performance.php" class="flex items-center p-3 my-1 hover:bg-gray-700 hover:text-white rounded-lg transition-colors"><i class="fas fa-chart-line fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['performance'] ?></a>
                <a href="payroll.php" class="flex items-center p-3 my-1 hover:bg-gray-700 hover:text-white rounded-lg transition-colors"><i class="fas fa-money-bill-wave fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['payroll'] ?></a>
                <a href="requests.php" class="flex items-center p-3 my-1 hover:bg-gray-700 hover:text-white rounded-lg transition-colors"><i class="fas fa-paper-plane fa-fw <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['requests'] ?></a>
            </nav>
            <div class="mt-auto">
                 <a href="logout.php" class="flex items-center p-3 my-2 bg-red-600 hover:bg-red-700 text-white rounded-lg justify-center transition-colors"><i class="fas fa-sign-out-alt fa-fw <?= $is_rtl ? 'ml-2' : 'mr-2' ?>"></i> <?= $t_sidebar[$lang]['logout'] ?></a>
            </div>
        </aside>
        
        <button id="menu-toggle" class="md:hidden fixed top-4 <?= $is_rtl ? 'right-4' : 'left-4' ?> bg-gray-800 text-white p-2 rounded-md z-50 shadow-lg"><i class="fas fa-bars"></i></button>

        <!-- Main Content -->
        <main class="main-content flex-1 p-6 md:p-10">
            <div class="max-w-4xl mx-auto">
                <header class="fade-in-up mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800"><?= $t[$lang]['header'] ?></h1>
                </header>
                
                <!-- Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="fade-in-up bg-green-100 border-l-4 border-green-500 text-green-800 p-4 mb-6 rounded-lg shadow-md" role="alert"><p><?= $_SESSION['message']; ?></p></div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                     <div class="fade-in-up bg-red-100 border-l-4 border-red-500 text-red-800 p-4 mb-6 rounded-lg shadow-md" role="alert"><p><?= $_SESSION['error']; ?></p></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Profile Card -->
                <div class="fade-in-up bg-white p-8 md:p-10 rounded-2xl shadow-xl transition-shadow hover:shadow-2xl">
                    <div class="flex flex-col md:flex-row items-center md:items-start md:gap-10">
                        <!-- Avatar Section -->
                        <div class="flex-shrink-0 mb-6 md:mb-0 text-center">
                            <?php if ($profile_image): ?>
                                <!-- EDITED: Added a forward slash '/' to the beginning of the src path -->
                                <img src="/<?= htmlspecialchars($profile_image) ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover mx-auto border-4 border-gray-100 shadow-md">
                            <?php else: ?>
                                <div class="w-32 h-32 rounded-full bg-indigo-100 flex items-center justify-center mx-auto border-4 border-gray-100 shadow-md">
                                    <span class="text-5xl font-bold text-indigo-600"><?= htmlspecialchars($profile_initial) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Profile Info & Form -->
                        <div class="flex-grow w-full">
                            <div class="text-center <?= $is_rtl ? 'md:text-right' : 'md:text-left' ?>">
                                 <h2 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($display_name) ?></h2>
                                 <p class="text-lg text-gray-500 mt-1"><?= htmlspecialchars($profile['position_ar'] ?? 'N/A') ?></p>
                                 <p class="text-sm text-gray-400 mt-2 font-mono"><?= $t[$lang]['employee_id'] ?>: #<?= htmlspecialchars($profile['id']) ?></p>
                            </div>
                            
                            <hr class="my-8 border-gray-200">
                            
                            <form method="POST" action="profile.php">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Base Salary -->
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <label class="block text-sm font-medium text-gray-500"><?= $t[$lang]['base_salary'] ?></label>
                                        <p class="mt-1 text-xl font-semibold text-green-600"><?= number_format($profile['salary'] ?? 0, 2) ?> <span class="text-sm text-gray-500"><?= $t[$lang]['currency'] ?></span></p>
                                    </div>
                                    <div class="md:col-span-2"></div>

                                    <!-- English Name -->
                                    <div>
                                        <label for="name_en" class="block text-sm font-medium text-gray-700 mb-2"><?= $t[$lang]['name_english'] ?></label>
                                        <input type="text" name="name_en" id="name_en" value="<?= htmlspecialchars($profile['name_en'] ?? '') ?>" class="form-input w-full border-gray-300 rounded-lg shadow-sm p-3">
                                    </div>
                                    
                                    <!-- Email -->
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2"><?= $t[$lang]['email'] ?></label>
                                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required class="form-input w-full border-gray-300 rounded-lg shadow-sm p-3">
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end">
                                    <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 transition transform hover:scale-105 shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <i class="fas fa-save <?= $is_rtl ? 'ml-2' : 'mr-2' ?>"></i> <?= $t[$lang]['save_changes'] ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() { 
            document.getElementById('sidebar').classList.toggle('active'); 
        });
    </script>
</body>
</html>