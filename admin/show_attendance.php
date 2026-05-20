<?php
session_start();
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

// --- Authentication & Permission Check ---
checkAdminAuth();
requirePermission('attendance.view');

$database = new Database();
$db = $database->getConnection();

// --- Data Fetching Logic ---
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'employee_id' => $_GET['employee_id'] ?? '' // This is actually user_id
];

$where_conditions = [];
$params = [];

if (!empty($filters['date_from'])) {
    $where_conditions[] = "DATE(log.event_time) >= ?";
    $params[] = $filters['date_from'];
}
if (!empty($filters['date_to'])) {
    $where_conditions[] = "DATE(log.event_time) <= ?";
    $params[] = $filters['date_to'];
}
if (!empty($filters['employee_id'])) {
    // Corrected condition to filter by user ID
    $where_conditions[] = "log.user_id = ?";
    $params[] = $filters['employee_id'];
}

$where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// === CORRECTED QUERY: JOIN with the 'users' table ===
$query = "
    SELECT
        log.event_time,
        u.name AS employee_name, -- Get name from the 'users' table
        log.event_type,
        log.latitude,
        log.longitude,
        br.break_name_ar
    FROM
        user_attendance_log AS log
    JOIN
        users AS u ON log.user_id = u.id -- JOIN with 'users' on user_id
    LEFT JOIN
        hr_break_rules AS br ON log.break_rule_id = br.id
    {$where_clause}
    ORDER BY
        log.event_time DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === CORRECTED: Fetch users for the filter dropdown ===
$users_stmt = $db->query("SELECT id, name FROM users ORDER BY name ASC");
$users_list = $users_stmt->fetchAll(PDO::FETCH_ASSOC);


$page_title = 'سجل الحضور والانصراف';
ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md mb-6 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center text-xl"><i class="fas fa-user-clock"></i></div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800"><?= $page_title ?></h1>
                <p class="text-sm text-gray-500">عرض وتصفية سجلات حضور وانصراف المستخدمين.</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="bg-white rounded-xl shadow-md mb-6 p-6">
    <form method="GET" action="">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700">من تاريخ</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2">
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700">إلى تاريخ</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2">
            </div>
            <div>
                <label for="employee_id" class="block text-sm font-medium text-gray-700">المستخدم</label>
                <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2">
                    <option value="">جميع المستخدمين</option>
                    <?php // === CORRECTED: Loop through the '$users_list' variable === ?>
                    <?php foreach ($users_list as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= ($filters['employee_id'] == $user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="w-full bg-mai-brown text-white py-2 px-4 rounded-md hover:bg-opacity-90 flex items-center justify-center gap-2">
                    <i class="fas fa-filter"></i> تصفية
                </button>
                 <a href="show_attendance.php" class="w-full bg-gray-300 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-400 flex items-center justify-center gap-2">
                    <i class="fas fa-times"></i> مسح
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Attendance Log Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-right text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3">المستخدم</th>
                    <th scope="col" class="px-6 py-3">الوقت والتاريخ</th>
                    <th scope="col" class="px-6 py-3 text-center">الإجراء</th>
                    <th scope="col" class="px-6 py-3 text-center">الموقع</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance_logs)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-10 text-gray-500">
                            <i class="fas fa-info-circle fa-2x mb-2"></i>
                            <p>لا توجد سجلات تطابق البحث.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendance_logs as $log): ?>
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                <?= htmlspecialchars($log['employee_name']) ?>
                            </td>
                            <td class="px-6 py-4" dir="ltr" style="text-align: right;">
                                <?= date('Y-m-d h:i A', strtotime($log['event_time'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php
                                    $badge_class = '';
                                    $badge_text = '';

                                    switch ($log['event_type']) {
                                        case 'in':
                                            $badge_class = 'bg-green-100 text-green-800';
                                            $badge_text = 'حضور';
                                            break;
                                        case 'out':
                                            $badge_class = 'bg-red-100 text-red-800';
                                            $badge_text = 'انصراف';
                                            break;
                                        case 'break_start':
                                            $badge_class = 'bg-yellow-100 text-yellow-800';
                                            $badge_text = 'بدء راحة';
                                            if (!empty($log['break_name_ar'])) {
                                                $badge_text .= ' (' . htmlspecialchars($log['break_name_ar']) . ')';
                                            }
                                            break;
                                        case 'break_end':
                                            $badge_class = 'bg-blue-100 text-blue-800';
                                            $badge_text = 'انتهاء راحة';
                                            break;
                                        default:
                                            $badge_class = 'bg-gray-100 text-gray-800';
                                            $badge_text = htmlspecialchars($log['event_type']);
                                            break;
                                    }
                                ?>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $badge_class ?>">
                                    <?= $badge_text ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!empty($log['latitude']) && !empty($log['longitude'])): ?>
                                    <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="text-blue-500 hover:underline">
                                        <i class="fas fa-map-marker-alt"></i> عرض على الخريطة
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>