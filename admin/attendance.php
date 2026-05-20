<?php
session_start();
date_default_timezone_set('Asia/Riyadh');

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
        'title' => 'Attendance Log',
        'page_header' => 'Attendance & Clock-In/Out',
        'page_subheader' => 'Here you can view your attendance history and manage your time.',
        'success' => 'Success!',
        'error' => 'Error!',
        'check_in_success' => 'Checked in successfully at ',
        'check_in_error' => 'You have already checked in for today.',
        'check_out_success' => 'Checked out successfully at ',
        'check_out_error' => 'You must check in first before checking out.',
        'generic_error' => 'An error occurred: ',
        'table_date' => 'Date',
        'table_check_in' => 'Check-In Time',
        'table_check_out' => 'Check-Out Time',
        'table_hours' => 'Work Hours',
        'table_status' => 'Status',
        'no_records' => 'No attendance records to display.',
        'present' => 'Present',
        'late' => 'Late',
        'absent' => 'Absent',
    ],
    'ar' => [
        'title' => 'سجل الحضور',
        'page_header' => 'سجل الحضور والإنصراف',
        'page_subheader' => 'هنا يمكنك عرض تاريخ الحضور الخاص بك.',
        'success' => 'نجاح!',
        'error' => 'خطأ!',
        'check_in_success' => 'تم تسجيل الحضور بنجاح الساعة ',
        'check_in_error' => 'لقد قمت بتسجيل الحضور بالفعل لهذا اليوم.',
        'check_out_success' => 'تم تسجيل الانصراف بنجاح الساعة ',
        'check_out_error' => 'يجب تسجيل الحضور أولاً قبل تسجيل الانصراف.',
        'generic_error' => 'حدث خطأ: ',
        'table_date' => 'التاريخ',
        'table_check_in' => 'وقت الحضور',
        'table_check_out' => 'وقت الانصراف',
        'table_hours' => 'ساعات العمل',
        'table_status' => 'الحالة',
        'no_records' => 'لا يوجد سجلات حضور لعرضها.',
        'present' => 'حاضر',
        'late' => 'متأخر',
        'absent' => 'غائب',
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
$today = date('Y-m-d');

// --- HANDLE CHECK-IN/CHECK-OUT ACTIONS ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $now_time = date('Y-m-d H:i:s');
    $now_time_only = date('H:i:s');

    try {
        $stmt = $db->prepare("SELECT id, check_in, break_minutes FROM hr_attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$user_id, $today]);
        $today_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($action == 'check_in') {
            if (!$today_record) {
                $stmt = $db->prepare("INSERT INTO hr_attendance (employee_id, date, check_in, status) VALUES (?, ?, ?, 'present')");
                $stmt->execute([$user_id, $today, $now_time_only]);
                $_SESSION['message'] = $t[$lang]['check_in_success'] . date('h:i A');
            } else {
                $_SESSION['error'] = $t[$lang]['check_in_error'];
            }
        } elseif ($action == 'check_out') {
            if ($today_record && $today_record['check_in']) {
                $check_in_dt = new DateTime($today . ' ' . $today_record['check_in']);
                $check_out_dt = new DateTime($now_time);
                $interval = $check_in_dt->diff($check_out_dt);
                $minutes_worked = ($interval->h * 60) + $interval->i;
                $hours_worked = ($minutes_worked - ($today_record['break_minutes'] ?? 0)) / 60;

                $stmt = $db->prepare("UPDATE hr_attendance SET check_out = ?, hours = ? WHERE id = ?");
                $stmt->execute([$now_time_only, $hours_worked, $today_record['id']]);
                $_SESSION['message'] = $t[$lang]['check_out_success'] . date('h:i A');
            } else {
                $_SESSION['error'] = $t[$lang]['check_out_error'];
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $t[$lang]['generic_error'] . $e->getMessage();
    }
    header('Location: attendance.php');
    exit;
}

// --- FETCH ATTENDANCE HISTORY ---
$stmt = $db->prepare("SELECT * FROM hr_attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 30");
$stmt->execute([$user_id]);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map status for translation
$status_map = [
    'present' => $t[$lang]['present'],
    'late' => $t[$lang]['late'],
    'absent' => $t[$lang]['absent']
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
        .table-row { opacity: 0; animation: slide-up 0.5s ease-out forwards; }
        @keyframes slide-up { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100">

    <div class="flex">
        <!-- (FIXED) Sidebar now includes the 'hidden' class to hide it on mobile by default -->
        <aside id="sidebar" class="sidebar bg-gray-800 text-white w-64 min-h-screen p-4 fixed <?= $is_rtl ? 'right-0' : 'left-0' ?> top-0 z-50 hidden md:block">
            <div class="text-2xl font-bold mb-8"><?= $t_sidebar[$lang]['employee_portal'] ?></div>
            <nav>
                <a href="dashboard.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-home <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['home'] ?></a>
                <a href="profile.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-user <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['my_profile'] ?></a>
                <a href="attendance.php" class="flex items-center p-3 my-2 bg-gray-900 rounded-lg font-bold"><i class="fas fa-clock <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['attendance'] ?></a>
                <a href="leaves.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-calendar-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['leaves'] ?></a>
                <a href="performance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-chart-line <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['performance'] ?></a>
                <a href="payroll.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-money-bill-wave <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['payroll'] ?></a>
                <a href="requests.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-paper-plane <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['requests'] ?></a>
            </nav>
            <div class="absolute bottom-4 right-4 left-4">
                <a href="logouttwo.php" class="flex items-center p-3 my-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors justify-center"><i class="fas fa-sign-out-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t_sidebar[$lang]['logout'] ?></a>
            </div>
        </aside>

        <!-- Hamburger Menu button -->
        <button id="menu-toggle" class="md:hidden fixed top-4 <?= $is_rtl ? 'right-4' : 'left-4' ?> bg-gray-800 text-white p-2 rounded-md z-50">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Main Content -->
        <main class="main-content flex-1 p-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2"><?= $t[$lang]['page_header'] ?></h1>
            <p class="text-gray-500 mb-8"><?= $t[$lang]['page_subheader'] ?></p>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-green-100 border-<?= $is_rtl ? 'r' : 'l' ?>-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md flex items-center" role="alert">
                    <i class="fas fa-check-circle text-2xl <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i>
                    <div><p class="font-bold"><?= $t[$lang]['success'] ?></p><p><?= $_SESSION['message']; ?></p></div>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                 <div class="bg-red-100 border-<?= $is_rtl ? 'r' : 'l' ?>-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md flex items-center" role="alert">
                    <i class="fas fa-exclamation-circle text-2xl <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i>
                    <div><p class="font-bold"><?= $t[$lang]['error'] ?></p><p><?= $_SESSION['error']; ?></p></div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm <?= $is_rtl ? 'text-right' : 'text-left' ?> text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3"><?= $t[$lang]['table_date'] ?></th>
                                <th scope="col" class="px-6 py-3"><?= $t[$lang]['table_check_in'] ?></th>
                                <th scope="col" class="px-6 py-3"><?= $t[$lang]['table_check_out'] ?></th>
                                <th scope="col" class="px-6 py-3"><?= $t[$lang]['table_hours'] ?></th>
                                <th scope="col" class="px-6 py-3"><?= $t[$lang]['table_status'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance_history)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10 text-gray-500"><?= $t[$lang]['no_records'] ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_history as $index => $record): ?>
                                <tr class="table-row bg-white border-b hover:bg-gray-50" style="animation-delay: <?= $index * 0.05 ?>s">
                                    <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap"><?= htmlspecialchars($record['date']) ?></td>
                                    <td class="px-6 py-4"><?= $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '---' ?></td>
                                    <td class="px-6 py-4"><?= $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '---' ?></td>
                                    <td class="px-6 py-4 font-semibold text-indigo-600"><?= $record['hours'] ? number_format($record['hours'], 2) : '0.00' ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $record['status'] == 'present' ? 'bg-green-100 text-green-800' : ($record['status'] == 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $status_map[$record['status']] ?? htmlspecialchars($record['status']) ?>
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
            const sidebar = document.getElementById('sidebar');

            // If the sidebar is currently active (visible), we want to hide it.
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                // Wait for the animation to finish, then add 'hidden' to remove it from the layout.
                setTimeout(() => {
                    sidebar.classList.add('hidden');
                }, 300); // 300ms matches the CSS transition duration
            } else {
                // If the sidebar is hidden, we want to show it.
                sidebar.classList.remove('hidden');
                // Use a tiny timeout to ensure the 'display' property is 'block' before the transform starts.
                setTimeout(() => {
                    sidebar.classList.add('active');
                }, 10);
            }
        });
    </script>
</body>
</html>