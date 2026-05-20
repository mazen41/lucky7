<?php
// --- START OF UNTOUCHED PHP LOGIC ---
session_start();
require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Authentication and Authorization
checkAdminAuth();
$admin = getCurrentAdmin();
requirePermission('hr.view'); // Use requirePermission for consistency

$database = new Database();
$db = $database->getConnection();

// --- All your database queries remain the same ---
try {
    $stats_query = "SELECT (SELECT COUNT(*) FROM team_members WHERE is_active = 1) as total_employees, (SELECT COUNT(*) FROM hr_attendance WHERE date = CURDATE() AND status IN ('present', 'late')) as today_attendance, (SELECT COUNT(*) FROM hr_leaves WHERE status = 'pending') as pending_leaves, (SELECT COUNT(*) FROM hr_requests WHERE status = 'pending') as pending_requests, (SELECT COUNT(*) FROM team_members WHERE is_active = 1 AND is_specialist = 1) as specialists_count";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $stats = ['total_employees' => 0, 'today_attendance' => 0, 'pending_leaves' => 0, 'pending_requests' => 0, 'specialists_count' => 0]; error_log("HR Dashboard stats error: " . $e->getMessage()); }
try {
    $current_month_perf = date('Y-m');
    $performance_query = "SELECT tm.name_ar, tm.position_ar, COALESCE(hp.revenue_sum, 0) as revenue_sum, COALESCE(hp.service_count, 0) as service_count FROM team_members tm LEFT JOIN hr_performance hp ON tm.id = hp.employee_id AND hp.month = ? WHERE tm.is_active = 1 AND tm.is_specialist = 1 ORDER BY COALESCE(hp.revenue_sum, 0) DESC LIMIT 5";
    $performance_stmt = $db->prepare($performance_query);
    $performance_stmt->execute([$current_month_perf]);
    $performance_data = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $performance_data = []; error_log("HR Dashboard performance error: " . $e->getMessage()); }
try {
    $attendance_query = "SELECT tm.name_ar, ha.check_in, ha.check_out, ha.status, ha.date FROM hr_attendance ha JOIN team_members tm ON ha.employee_id = tm.id WHERE ha.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY ha.date DESC, ha.check_in DESC LIMIT 8";
    $attendance_stmt = $db->prepare($attendance_query);
    $attendance_stmt->execute();
    $recent_attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_attendance = []; error_log("HR Dashboard attendance error: " . $e->getMessage()); }
try {
    $requests_query = "SELECT hr.*, tm.name_ar as employee_name FROM hr_requests hr JOIN team_members tm ON hr.employee_id = tm.id WHERE hr.status = 'pending' ORDER BY hr.created_at DESC LIMIT 10";
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->execute();
    $pending_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pending_requests = []; error_log("HR Dashboard requests error: " . $e->getMessage()); }
// --- END OF UNTOUCHED PHP LOGIC ---

$page_title = 'الموارد البشرية';
ob_start();
?>

<!-- HEADER BOX -->
<div class="bg-white rounded-lg shadow-md mb-3 p-4">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 bg-mai-brown rounded-md flex items-center justify-center text-white">
      <i class="fas fa-users-cog text-xl"></i>
    </div>
    <div>
      <h1 class="text-lg font-bold text-mai-dark-brown">لوحة تحكم الموارد البشرية</h1>
      <p class="text-xs text-gray-500">نظرة شاملة على الموظفين، الحضور، والأداء</p>
    </div>
  </div>
</div>

<!-- BUTTONS BOX -->
<div class="rounded-lg  mb-4 p-4">
  <div class="flex flex-wrap gap-2">
    <!-- All your other buttons -->
    <a href="hr_add_specialist.php" class="bg-mai-brown text-white px-3 py-1.5 rounded-md hover:bg-mai-dark-brown text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5"><i class="fas fa-user-plus"></i>الموظفين</a>
    <a href="hr_attendance.php" class="bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5"><i class="fas fa-clock"></i>الحاضرين</a>
    <a href="hr_leaves.php" class="bg-orange-500 text-white px-3 py-1.5 rounded-md hover:bg-orange-600 text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5"><i class="fas fa-calendar-alt"></i>الإجازات</a>
    <a href="hr_performance.php" class="bg-green-600 text-white px-3 py-1.5 rounded-md hover:bg-green-700 text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5"><i class="fas fa-chart-line"></i>الأداء</a>
    <a href="hr_payroll.php" class="bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-700 text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5"><i class="fas fa-money-bill-wave"></i>الرواتب</a>
    <a href="hr_requests.php" class="bg-purple-600 text-white px-3 py-1.5 rounded-md hover:bg-purple-700 text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5"><i class="fas fa-clipboard-list"></i>الطلبات</a>
    
    <!-- === START: THE ONLY CHANGE YOU NEED === -->
    <!-- This is now a simple link to the new page -->
    <a href="hr_run_performance.php" class="bg-teal-600 text-white px-3 py-1.5 rounded-md hover:bg-teal-700 text-xs font-semibold flex items-center gap-1.5 transition-transform hover:-translate-y-0.5">
        <i class="fas fa-calculator"></i>
        <span>مراجعة وتحديث الأداء</span>
    </a>
    <!-- === END: THE ONLY CHANGE YOU NEED === -->

  </div>
</div>

<!-- Flash Messages (these will now work correctly) -->
<?php if (isset($_SESSION['payroll_message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert"><p class="font-bold">نجاح</p><p><?= $_SESSION['payroll_message']; ?></p></div>
    <?php unset($_SESSION['payroll_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['payroll_error'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert"><p class="font-bold">خطأ</p><p><?= $_SESSION['payroll_error']; ?></p></div>
    <?php unset($_SESSION['payroll_error']); ?>
<?php endif; ?>

<!-- The rest of your dashboard page remains the same -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-blue-500"><p class="text-sm text-gray-500 font-semibold">إجمالي الموظفين</p><p class="text-4xl font-black text-gray-800 mt-1"><?= $stats['total_employees'] ?></p></div>
    <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-green-500"><p class="text-sm text-gray-500 font-semibold">حضور اليوم</p><p class="text-4xl font-black text-gray-800 mt-1"><?= $stats['today_attendance'] ?></p></div>
    <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-purple-500"><p class="text-sm text-gray-500 font-semibold">الأخصائيات</p><p class="text-4xl font-black text-gray-800 mt-1"><?= $stats['specialists_count'] ?></p></div>
    <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-yellow-500"><p class="text-sm text-gray-500 font-semibold">إجازات معلقة</p><p class="text-4xl font-black text-gray-800 mt-1"><?= $stats['pending_leaves'] ?></p></div>
    <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-red-500"><p class="text-sm text-gray-500 font-semibold">طلبات معلقة</p><p class="text-4xl font-black text-gray-800 mt-1"><?= $stats['pending_requests'] ?></p></div>
</div>
<!-- etc... your other content tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-gray-800">أفضل أداء للشهر الحالي - <?= date('Y/m') ?></h3><a href="hr_performance.php" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">عرض التفاصيل &rarr;</a></div>
        <div class="space-y-3">
            <?php foreach ($performance_data as $perf): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <p class="font-medium text-gray-800"><?= htmlspecialchars($perf['name_ar']) ?></p>
                <div class="text-left"><p class="font-bold text-green-700"><?= number_format($perf['revenue_sum'] ?? 0) ?> ر.س</p><p class="text-xs text-gray-500"><?= $perf['service_count'] ?? 0 ?> خدمة</p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-gray-800">آخر حركات الحضور</h3><a href="hr_attendance.php" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">عرض الكل &rarr;</a></div>
        <div class="space-y-2">
            <?php foreach ($recent_attendance as $att): ?>
            <div class="flex items-center justify-between py-2 border-b last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded-md">
                <div><p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($att['name_ar']) ?></p><p class="text-xs text-gray-500" dir="ltr"><?= date('Y/m/d', strtotime($att['date'])) ?></p></div>
                <div class="text-left"><p class="text-sm text-gray-700" dir="ltr"><?= $att['check_in'] ? date('h:i A', strtotime($att['check_in'])) : '--' ?> - <?= $att['check_out'] ? date('h:i A', strtotime($att['check_out'])) : '--' ?></p><span class="px-2 py-1 text-xs rounded-full <?= $att['status'] === 'present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= $att['status'] ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
// No more JavaScript, no more modals. Clean and simple.
include 'layouts/responsive_base.php';
?>