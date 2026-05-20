<?php
session_start();

// --- LANGUAGE SWITCHER ---
$available_langs = ['ar', 'en'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: payroll.php"); // Redirect to clean the URL
    exit;
}
$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');

// --- TRANSLATIONS ---
$t = [
    'en' => [
        'title' => 'Payslips',
        'header' => 'Payslips',
        'subheader' => 'A detailed view of your monthly salary history.',
        'month' => 'Month',
        'base_salary' => 'Base Salary',
        'bonus' => 'Bonus',
        'overtime' => 'Overtime Pay',
        'deductions' => 'Deductions',
        'net_salary' => 'Net Salary',
        'status' => 'Status',
        'no_records' => 'No payroll records yet.',
        'paid' => 'Paid',
        'processing' => 'Processing',
        'draft' => 'Draft', // Added translation for draft status
        'currency' => 'SAR'
    ],
    'ar' => [
        'title' => 'كشوف الرواتب',
        'header' => 'كشوف الرواتب',
        'subheader' => 'عرض تفصيلي لسجل رواتبك الشهرية.',
        'month' => 'الشهر',
        'base_salary' => 'الراتب الأساسي',
        'bonus' => 'المكافأة',
        'overtime' => 'أجر إضافي',
        'deductions' => 'الخصومات',
        'net_salary' => 'صافي الراتب',
        'status' => 'الحالة',
        'no_records' => 'لا توجد سجلات رواتب حتى الآن.',
        'paid' => 'مدفوع',
        'processing' => 'قيد المعالجة',
        'draft' => 'مسودة', // Added translation for draft status
        'currency' => 'ر.س'
    ]
];
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
$user_id_from_session = $_SESSION['user_id'];

// =========================================================================
// --- FIX: GET THE CORRECT employee_id FROM THE team_members TABLE ---
// =========================================================================
$employee_id = null;
$stmt_employee = $db->prepare("SELECT id FROM team_members WHERE user_id = ?");
$stmt_employee->execute([$user_id_from_session]);
$employee_record = $stmt_employee->fetch(PDO::FETCH_ASSOC);

if ($employee_record) {
    $employee_id = $employee_record['id'];
}
// =========================================================================


// --- FETCH PAYROLL HISTORY (Now using the correct employee_id) ---
$payroll_history = []; // Default to an empty array

if ($employee_id) {
    $stmt = $db->prepare("SELECT * FROM hr_payroll WHERE employee_id = ? ORDER BY month DESC");
    $stmt->execute([$employee_id]); // Use the correct ID
    $payroll_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Updated status map to include 'draft'
$status_map = [
    'paid' => ['text' => $t[$lang]['paid'], 'class' => 'bg-green-100 text-green-800'],
    'processing' => ['text' => $t[$lang]['processing'], 'class' => 'bg-yellow-100 text-yellow-800'],
    'draft' => ['text' => $t[$lang]['draft'], 'class' => 'bg-gray-100 text-gray-800']
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
        @media (max-width: 767px) {
            .sidebar { transform: translateX(<?= $is_rtl ? '100%' : '-100%' ?>); }
            .sidebar.active { transform: translateX(0); }
        }
        @media (min-width: 768px) {
            .main-content { margin-<?= $is_rtl ? 'right' : 'left' ?>: 16rem; }
        }
        .animated-card { opacity: 0; animation: slide-up 0.6s ease-out forwards; }
        @keyframes slide-up { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .table-row { opacity: 0; animation: fade-in-up 0.5s ease-out forwards; }
        @keyframes fade-in-up { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100">

    <div class="flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar bg-gray-800 text-white w-64 min-h-screen p-4 fixed <?= $is_rtl ? 'right-0' : 'left-0' ?> top-0 z-40 md:block">
            <div class="flex justify-between items-center mb-8">
                 <div class="text-2xl font-bold"><?= $t_sidebar[$lang]['employee_portal'] ?></div>
                 <a href="?lang=<?= $is_rtl ? 'en' : 'ar' ?>" class="text-sm p-2 bg-gray-700 rounded-md hover:bg-gray-600"><?= $is_rtl ? 'EN' : 'AR' ?></a>
            </div>
            <nav>
                <a href="specialist_dashboard.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-home <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['home'] ?></a>
                <a href="profile.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-user <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['my_profile'] ?></a>
                <a href="leaves.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-calendar-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['leaves'] ?></a>
                <a href="performance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-chart-line <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['performance'] ?></a>
                <a href="payroll.php" class="flex items-center p-3 my-2 bg-gray-900 rounded-lg font-bold"><i class="fas fa-file-invoice-dollar <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['payroll'] ?></a>
                <a href="requests.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-clipboard-list <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['requests'] ?></a>
            </nav>
            <div class="absolute bottom-4 right-4 left-4">
                 <a href="logouttwo.php" class="flex items-center p-3 my-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors justify-center"><i class="fas fa-sign-out-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['logout'] ?></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-1 p-6 md:p-8">
            <button id="menu-toggle" class="md:hidden fixed top-4 <?= $is_rtl ? 'right-4' : 'left-4' ?> bg-gray-800 text-white p-2 rounded-md z-50">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="text-4xl font-bold text-gray-800 mb-2"><?= $t[$lang]['header'] ?></h1>
            <p class="text-gray-500 mb-8"><?= $t[$lang]['subheader'] ?></p>

            <div class="animated-card bg-white p-6 rounded-xl shadow-lg border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm <?= $is_rtl ? 'text-right' : 'text-left' ?> text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="p-4"><?= $t[$lang]['month'] ?></th>
                                <th class="p-4"><?= $t[$lang]['base_salary'] ?></th>
                                <th class="p-4"><?= $t[$lang]['bonus'] ?></th>
                                <th class="p-4"><?= $t[$lang]['overtime'] ?></th>
                                <th class="p-4"><?= $t[$lang]['deductions'] ?></th>
                                <th class="p-4 text-green-700"><?= $t[$lang]['net_salary'] ?></th>
                                <th class="p-4"><?= $t[$lang]['status'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payroll_history)): ?>
                                <tr><td colspan="7" class="text-center py-12 text-gray-500"><?= $t[$lang]['no_records'] ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($payroll_history as $index => $payroll): ?>
                                <tr class="table-row border-b hover:bg-gray-50" style="animation-delay: <?= $index * 0.1 ?>s">
                                    <td class="p-4 font-semibold text-gray-900"><?= htmlspecialchars(date('F Y', strtotime($payroll['month'].'-01'))) ?></td>
                                    <td class="p-4"><?= number_format($payroll['base_salary'], 2) ?> <?= $t[$lang]['currency'] ?></td>
                                    <td class="p-4"><?= number_format($payroll['bonus'], 2) ?> <?= $t[$lang]['currency'] ?></td>
                                    <td class="p-4"><?= number_format($payroll['overtime_pay'], 2) ?> <?= $t[$lang]['currency'] ?></td>
                                    <td class="p-4 text-red-600 font-medium"><?= number_format($payroll['deductions'], 2) ?> <?= $t[$lang]['currency'] ?></td>
                                    <td class="p-4 font-bold text-lg text-green-700"><?= number_format($payroll['net_salary'], 2) ?> <?= $t[$lang]['currency'] ?></td>
                                    <td class="p-4">
                                        <?php $status_info = $status_map[$payroll['status']] ?? ['text' => htmlspecialchars($payroll['status']), 'class' => 'bg-gray-200 text-gray-800']; ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $status_info['class'] ?>">
                                            <?= $status_info['text'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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