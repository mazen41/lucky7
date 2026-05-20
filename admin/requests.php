<?php
session_start();

// --- LANGUAGE SWITCHER ---
$available_langs = ['ar', 'en'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');

// --- TRANSLATIONS ---
$t = [
    'en' => [
        'title' => 'General Requests',
        'header' => 'General Requests',
        'submit_success' => 'Your request has been submitted successfully.',
        'submit_error' => 'An error occurred: ',
        'new_request_header' => 'Submit a New Request',
        'request_title' => 'Request Title',
        'request_type' => 'Request Type',
        'salary_advance' => 'Salary Advance',
        'equipment_request' => 'Equipment Request',
        'training_request' => 'Training Request',
        'other' => 'Other',
        'amount_field' => 'Advance Amount (SAR)',
        'priority' => 'Priority',
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'details' => 'Details',
        'submit_button' => 'Submit Request',
        'history_header' => 'Requests History',
        'no_records' => 'No requests to display yet.',
        'tag_type' => 'Type',
        'tag_priority' => 'Priority',
        'tag_amount' => 'Amount',
        'manager_notes' => 'Manager Notes:',
        'approved' => 'Approved',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'in_progress' => 'In Progress'
    ],
    'ar' => [
        'title' => 'الطلبات العامة',
        'header' => 'الطلبات العامة',
        'submit_success' => 'تم إرسال طلبك بنجاح.',
        'submit_error' => 'حدث خطأ: ',
        'new_request_header' => 'تقديم طلب جديد',
        'request_title' => 'عنوان الطلب',
        'request_type' => 'نوع الطلب',
        'salary_advance' => 'سلفة راتب',
        'equipment_request' => 'طلب معدات',
        'training_request' => 'طلب تدريب',
        'other' => 'أخرى',
        'amount_field' => 'مبلغ السلفة (ر.س)',
        'priority' => 'الأولوية',
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'details' => 'التفاصيل',
        'submit_button' => 'إرسال الطلب',
        'history_header' => 'سجل الطلبات',
        'no_records' => 'لا توجد طلبات لعرضها بعد.',
        'tag_type' => '',
        'tag_priority' => '',
        'tag_amount' => '',
        'manager_notes' => 'ملاحظات الإدارة:',
        'approved' => 'موافق عليه',
        'pending' => 'قيد الانتظار',
        'rejected' => 'مرفوض',
        'completed' => 'مكتمل',
        'in_progress' => 'قيد التنفيذ'
    ]
];
// --- Common sidebar translations ---
$t_sidebar = [
    'en' => [ 'employee_portal' => 'Employee Portal', 'home' => 'Home', 'my_profile' => 'My Profile', 'attendance' => 'Attendance', 'leaves' => 'Leaves', 'performance' => 'Performance', 'payroll' => 'Payroll', 'requests' => 'Requests', 'logout' => 'Logout' ],
    'ar' => [ 'employee_portal' => 'بوابة الموظفين', 'home' => 'الرئيسية', 'my_profile' => 'ملفي الشخصي', 'attendance' => 'الحضور', 'leaves' => 'الإجازات', 'performance' => 'الأداء', 'payroll' => 'الرواتب', 'requests' => 'الطلبات', 'logout' => 'تسجيل الخروج' ]
];


if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// --- HANDLE NEW REQUEST SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    try {
        $type = $_POST['type'];
        $amount = ($type === 'salary_advance' && isset($_POST['amount'])) ? $_POST['amount'] : null;

        $stmt = $db->prepare(
            "INSERT INTO hr_requests (employee_id, type, title, description, priority, amount, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([
            $user_id, $type, $_POST['title'], $_POST['description'], $_POST['priority'], $amount
        ]);
        $_SESSION['message'] = $t[$lang]['submit_success'];
    } catch (Exception $e) {
        $_SESSION['error'] = $t[$lang]['submit_error'] . $e->getMessage();
    }
    header('Location: requests.php');
    exit;
}

// --- FETCH REQUESTS HISTORY ---
$stmt = $db->prepare("SELECT * FROM hr_requests WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$requests_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- TRANSLATION MAPS ---
$type_map = [
    'salary_advance' => $t[$lang]['salary_advance'], 'equipment' => $t[$lang]['equipment_request'],
    'training' => $t[$lang]['training_request'], 'other' => $t[$lang]['other']
];
$priority_map = [
    'low' => $t[$lang]['low'], 'medium' => $t[$lang]['medium'], 'high' => $t[$lang]['high']
];
$status_map = [
    'completed' => $t[$lang]['completed'], 'approved' => $t[$lang]['approved'], 'rejected' => $t[$lang]['rejected'],
    'pending' => $t[$lang]['pending'], 'in_progress' => $t[$lang]['in_progress']
];

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $is_rtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t[$lang]['title'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: '<?= $is_rtl ? 'Cairo' : 'Poppins' ?>', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .main-content { transition: margin-<?= $is_rtl ? 'right' : 'left' ?> 0.3s ease-in-out; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(<?= $is_rtl ? '100%' : '-100%' ?>); }
            .sidebar.active { transform: translateX(0); }
        }
        @media (min-width: 768px) {
            .main-content { margin-<?= $is_rtl ? 'right' : 'left' ?>: 16rem; }
        }
        .animated-card { opacity: 0; animation: slide-up 0.6s ease-out forwards; }
        @keyframes slide-up { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100">

    <div class="flex">
        <!-- Sidebar Navigation -->
        <aside id="sidebar" class="sidebar bg-gray-800 text-white w-64 min-h-screen p-4 fixed <?= $is_rtl ? 'right-0' : 'left-0' ?> top-0 z-50 md:block">
            <div class="text-2xl font-bold mb-8"><?= $t_sidebar[$lang]['employee_portal'] ?></div>
            <nav>
                <a href="dashboard.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-home <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['home'] ?></a>
                <a href="profile.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-user <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['my_profile'] ?></a>
                <a href="attendance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-clock <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['attendance'] ?></a>
                <a href="leaves.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-calendar-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['leaves'] ?></a>
                <a href="performance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-chart-line <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['performance'] ?></a>
                <a href="payroll.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-money-bill-wave <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['payroll'] ?></a>
                <a href="requests.php" class="flex items-center p-3 my-2 bg-gray-900 rounded-lg font-bold"><i class="fas fa-paper-plane <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['requests'] ?></a>
            </nav>
            <div class="absolute bottom-4 right-4 left-4">
                 <a href="logouttwo.php" class="flex items-center p-3 my-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors justify-center"><i class="fas fa-sign-out-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['logout'] ?></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-1 p-8">
            <button id="menu-toggle" class="md:hidden fixed top-4 <?= $is_rtl ? 'right-4' : 'left-4' ?> bg-gray-800 text-white p-2 rounded-md z-50">
                <i class="fas fa-bars"></i>
            </button>
            
            <h1 class="text-4xl font-bold text-gray-800 mb-6"><?= $t[$lang]['header'] ?></h1>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-green-100 border-<?= $is_rtl ? 'r' : 'l' ?>-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert"><?= $_SESSION['message']; ?></div>
                <?php unset($_SESSION['message']); endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-<?= $is_rtl ? 'r' : 'l' ?>-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert"><?= $_SESSION['error']; ?></div>
                <?php unset($_SESSION['error']); endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- New Request Form -->
                <div class="lg:col-span-1 animated-card bg-white p-6 rounded-xl shadow-lg border">
                    <h2 class="text-2xl font-bold mb-5 text-gray-700"><?= $t[$lang]['new_request_header'] ?></h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="submit_request">
                        <div>
                            <label for="title" class="block text-sm font-medium"><?= $t[$lang]['request_title'] ?></label>
                            <input type="text" id="title" name="title" required class="mt-1 w-full p-2.5 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label for="type" class="block text-sm font-medium"><?= $t[$lang]['request_type'] ?></label>
                            <select id="type" name="type" required class="mt-1 w-full p-2.5 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="salary_advance"><?= $t[$lang]['salary_advance'] ?></option>
                                <option value="equipment"><?= $t[$lang]['equipment_request'] ?></option>
                                <option value="training"><?= $t[$lang]['training_request'] ?></option>
                                <option value="other"><?= $t[$lang]['other'] ?></option>
                            </select>
                        </div>
                        <div id="amount-field" class="hidden">
                            <label for="amount" class="block text-sm font-medium"><?= $t[$lang]['amount_field'] ?></label>
                            <input type="number" id="amount" name="amount" step="0.01" class="mt-1 w-full p-2.5 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="priority" class="block text-sm font-medium"><?= $t[$lang]['priority'] ?></label>
                            <select id="priority" name="priority" required class="mt-1 w-full p-2.5 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="low"><?= $t[$lang]['low'] ?></option>
                                <option value="medium" selected><?= $t[$lang]['medium'] ?></option>
                                <option value="high"><?= $t[$lang]['high'] ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium"><?= $t[$lang]['details'] ?></label>
                            <textarea id="description" name="description" rows="4" required class="mt-1 w-full p-2.5 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition transform hover:scale-105 shadow-lg flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane"></i> <?= $t[$lang]['submit_button'] ?>
                        </button>
                    </form>
                </div>

                <!-- Requests History -->
                <div class="lg:col-span-2 animated-card" style="animation-delay: 0.2s;">
                    <h2 class="text-2xl font-bold mb-5 text-gray-700"><?= $t[$lang]['history_header'] ?></h2>
                    <div class="space-y-4">
                        <?php if (empty($requests_history)): ?>
                            <div class="bg-white rounded-xl shadow-lg border p-12 text-center text-gray-500"><?= $t[$lang]['no_records'] ?></div>
                        <?php else: ?>
                            <?php foreach($requests_history as $request): ?>
                            <div class="bg-white border rounded-xl shadow-lg p-5 transition hover:shadow-2xl hover:-translate-y-1">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($request['title']) ?></h3>
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                                        <?php switch($request['status']) {
                                            case 'completed': case 'approved': echo 'bg-green-100 text-green-800'; break;
                                            case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-yellow-100 text-yellow-800'; } ?>">
                                        <?= $status_map[$request['status']] ?? htmlspecialchars($request['status']) ?>
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 mt-1 items-center">
                                    <span><i class="fas fa-tag <?= $is_rtl ? 'ml-1' : 'mr-1' ?>"></i> <?= $type_map[$request['type']] ?? htmlspecialchars($request['type']) ?></span>
                                    <span><i class="fas fa-flag <?= $is_rtl ? 'ml-1' : 'mr-1' ?>"></i> <?= $priority_map[$request['priority']] ?? htmlspecialchars($request['priority']) ?></span>
                                    <?php if ($request['type'] === 'salary_advance' && !empty($request['amount'])): ?>
                                        <span class="font-bold text-green-600"><i class="fas fa-money-bill-wave <?= $is_rtl ? 'ml-1' : 'mr-1' ?>"></i> <?= number_format($request['amount'], 2) ?> <?= $t[$lang]['currency'] ?? '' ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-3 text-gray-700 text-sm"><?= nl2br(htmlspecialchars($request['description'])) ?></p>
                                <?php if (!empty($request['manager_notes'])): ?>
                                <div class="mt-4 pt-3 border-t bg-gray-50 p-3 rounded-md">
                                    <p class="text-sm font-semibold text-gray-800"><?= $t[$lang]['manager_notes'] ?></p>
                                    <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($request['manager_notes'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Script for the request form (amount field)
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const amountField = document.getElementById('amount-field');
            const amountInput = document.getElementById('amount');
            function toggleAmountField() {
                if (typeSelect.value === 'salary_advance') {
                    amountField.classList.remove('hidden');
                    amountInput.required = true;
                } else {
                    amountField.classList.add('hidden');
                    amountInput.required = false;
                    amountInput.value = '';
                }
            }
            typeSelect.addEventListener('change', toggleAmountField);
            toggleAmountField(); // Run on page load
        });

        // Script for the responsive sidebar
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>