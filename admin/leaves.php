<?php
session_start();

$available_langs = ['ar', 'en'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');

$t = [
    'en' => [
        'title' => 'Leave Requests',
        'header' => 'Leaves',
        'subheader' => 'Submit a new leave request or view your past requests history.',
        'request_success' => 'Leave request submitted successfully and is now pending review.',
        'request_error' => 'An error occurred while submitting the request: ',
        'date_error' => 'Please ensure the end date is after the start date.',
        'new_request_title' => 'Submit a New Request',
        'leave_type' => 'Leave Type',
        'annual' => 'Annual',
        'sick' => 'Sick',
        'unpaid' => 'Unpaid',
        'emergency' => 'Emergency',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'reason' => 'Reason',
        'submit_button' => 'Submit Request',
        'history_title' => 'Leave History',
        'table_type' => 'Type',
        'table_period' => 'Period',
        'table_to' => 'to',
        'table_days' => 'Days',
        'table_status' => 'Status',
        'no_records' => 'No leave records to display.',
        'approved' => 'Approved',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
    ],
    'ar' => [
        'title' => 'طلبات الإجازة',
        'header' => 'الإجازات',
        'subheader' => 'قم بتقديم طلب إجازة جديد أو عرض سجل طلباتك السابقة.',
        'request_success' => 'تم إرسال طلب الإجازة بنجاح وهو الآن قيد المراجعة.',
        'request_error' => 'حدث خطأ أثناء إرسال الطلب: ',
        'date_error' => 'يرجى التأكد من أن تاريخ النهاية بعد تاريخ البداية.',
        'new_request_title' => 'تقديم طلب جديد',
        'leave_type' => 'نوع الإجازة',
        'annual' => 'سنوية',
        'sick' => 'مرضية',
        'unpaid' => 'بدون راتب',
        'emergency' => 'طارئة',
        'from_date' => 'من تاريخ',
        'to_date' => 'إلى تاريخ',
        'reason' => 'السبب',
        'submit_button' => 'إرسال الطلب',
        'history_title' => 'سجل الإجازات',
        'table_type' => 'النوع',
        'table_period' => 'الفترة',
        'table_to' => 'إلى',
        'table_days' => 'الأيام',
        'table_status' => 'الحالة',
        'no_records' => 'لا يوجد سجل إجازات لعرضه.',
        'approved' => 'موافق عليه',
        'pending' => 'معلق', // <<< FIX: Changed from 'قيد الانتظار' to 'معلق'
        'rejected' => 'مرفوض',
    ]
];
// --- Common sidebar translations ---
$t_sidebar = [
    'en' => [ 'employee_portal' => 'Employee Portal', 'home' => 'Home', 'my_profile' => 'My Profile', 'attendance' => 'Attendance', 'leaves' => 'Leaves', 'performance' => 'Performance', 'payroll' => 'Payroll', 'requests' => 'Requests', 'logout' => 'Logout' ],
    'ar' => [ 'employee_portal' => 'بوابة الموظفين', 'home' => 'الرئيسية', 'my_profile' => 'ملفي الشخصي', 'attendance' => 'الحاضر', 'leaves' => 'الإجازات', 'performance' => 'الأداء', 'payroll' => 'الرواتب', 'requests' => 'الطلبات', 'logout' => 'تسجيل الخروج' ]
];


if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// --- HANDLE NEW LEAVE REQUEST SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_leave') {
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];

    if ($from_date && $to_date && $to_date >= $from_date) {
        try {
            $from = new DateTime($from_date);
            $to = new DateTime($to_date);
            $days_count = $from->diff($to)->days + 1;

            $stmt = $db->prepare(
                "INSERT INTO hr_leaves (employee_id, type, from_date, to_date, days_count, reason, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                $user_id,
                $_POST['type'],
                $from_date,
                $to_date,
                $days_count,
                $_POST['reason']
            ]);
            $_SESSION['message'] = $t[$lang]['request_success'];
        } catch (Exception $e) {
            $_SESSION['error'] = $t[$lang]['request_error'] . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = $t[$lang]['date_error'];
    }
    header('Location: leaves.php');
    exit;
}

// --- FETCH LEAVE HISTORY ---
$stmt = $db->prepare("SELECT * FROM hr_leaves WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$leave_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mappings for translation
$type_map = [
    'annual' => $t[$lang]['annual'], 'sick' => $t[$lang]['sick'], 'unpaid' => $t[$lang]['unpaid'], 'emergency' => $t[$lang]['emergency']
];
$status_map = [
    'approved' => $t[$lang]['approved'], 'pending' => $t[$lang]['pending'], 'rejected' => $t[$lang]['rejected']
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
        .table-row { opacity: 0; animation: fade-in 0.5s ease-out forwards; }
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
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
                <a href="leaves.php" class="flex items-center p-3 my-2 bg-gray-900 rounded-lg font-bold"><i class="fas fa-calendar-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['leaves'] ?></a>
                <a href="performance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-chart-line <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['performance'] ?></a>
                <a href="payroll.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-money-bill-wave <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['payroll'] ?></a>
                <a href="requests.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-paper-plane <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['requests'] ?></a>
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
            
            <h1 class="text-4xl font-bold text-gray-800 mb-2"><?= $t[$lang]['header'] ?></h1>
            <p class="text-gray-500 mb-8"><?= $t[$lang]['subheader'] ?></p>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-green-100 border-<?= $is_rtl ? 'r' : 'l' ?>-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md" role="alert"><p><?= $_SESSION['message']; ?></p></div>
                <?php unset($_SESSION['message']); endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-<?= $is_rtl ? 'r' : 'l' ?>-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md" role="alert"><p><?= $_SESSION['error']; ?></p></div>
                <?php unset($_SESSION['error']); endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- New Leave Request Form -->
                <div class="lg:col-span-1 animated-card bg-white p-6 rounded-xl shadow-lg border">
                    <h2 class="text-2xl font-bold mb-5 text-gray-700 flex items-center"><i class="fas fa-paper-plane text-indigo-500 <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['new_request_title'] ?></h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="request_leave">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700"><?= $t[$lang]['leave_type'] ?></label>
                            <select id="type" name="type" required class="mt-1 block w-full p-2.5 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="annual"><?= $t[$lang]['annual'] ?></option>
                                <option value="sick"><?= $t[$lang]['sick'] ?></option>
                                <option value="unpaid"><?= $t[$lang]['unpaid'] ?></option>
                                <option value="emergency"><?= $t[$lang]['emergency'] ?></option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="from_date" class="block text-sm font-medium"><?= $t[$lang]['from_date'] ?></label>
                                <input type="date" id="from_date" name="from_date" required class="mt-1 w-full p-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="to_date" class="block text-sm font-medium"><?= $t[$lang]['to_date'] ?></label>
                                <input type="date" id="to_date" name="to_date" required class="mt-1 w-full p-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div>
                            <label for="reason" class="block text-sm font-medium"><?= $t[$lang]['reason'] ?></label>
                            <textarea id="reason" name="reason" rows="4" required class="mt-1 w-full p-2.5 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
                        </div>
                        <div>
                            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition transform hover:scale-105 shadow-lg flex items-center justify-center gap-2">
                                <i class="fas fa-check"></i> <?= $t[$lang]['submit_button'] ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Leave History Table -->
                <div class="lg:col-span-2 animated-card bg-white p-6 rounded-xl shadow-lg border" style="animation-delay: 0.2s;">
                    <h2 class="text-2xl font-bold mb-5 text-gray-700 flex items-center"><i class="fas fa-history text-indigo-500 <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['history_title'] ?></h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm <?= $is_rtl ? 'text-right' : 'text-left' ?> text-gray-500">
                             <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3"><?= $t[$lang]['table_type'] ?></th>
                                    <th class="px-4 py-3"><?= $t[$lang]['table_period'] ?></th>
                                    <th class="px-4 py-3"><?= $t[$lang]['table_days'] ?></th>
                                    <th class="px-4 py-3"><?= $t[$lang]['table_status'] ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leave_history)): ?>
                                    <tr><td colspan="4" class="text-center py-10 text-gray-500"><?= $t[$lang]['no_records'] ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($leave_history as $index => $leave): ?>
                                    <tr class="table-row border-b hover:bg-gray-50" style="animation-delay: <?= $index * 0.1 ?>s">
                                        <td class="px-4 py-4 font-medium text-gray-900"><?= $type_map[$leave['type']] ?? htmlspecialchars($leave['type']) ?></td>
                                        <td class="px-4 py-4 text-xs"><?= htmlspecialchars($leave['from_date']) ?> <br><?= $t[$lang]['table_to'] ?> <?= htmlspecialchars($leave['to_date']) ?></td>
                                        <td class="px-4 py-4 font-semibold text-indigo-600"><?= htmlspecialchars($leave['days_count']) ?></td>
                                        <td class="px-4 py-4">
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                                <?= $leave['status'] == 'approved' ? 'bg-green-100 text-green-800' :
                                                  ($leave['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                <?= $status_map[$leave['status']] ?? htmlspecialchars($leave['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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