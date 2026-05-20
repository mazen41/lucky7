<?php
/**
 * HR Management - Performance & Targets
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$current_month = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month;

// Handle target updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_target') {
    try {
        $stmt = $db->prepare(
            "INSERT INTO hr_targets (employee_id, month, service_target, revenue_target, bonus_percentage, bonus_threshold)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             service_target = VALUES(service_target), 
             revenue_target = VALUES(revenue_target),
             bonus_percentage = VALUES(bonus_percentage),
             bonus_threshold = VALUES(bonus_threshold)"
        );
        $stmt->execute([
            $_POST['employee_id'],
            $selected_month,
            $_POST['service_target'],
            $_POST['revenue_target'],
            $_POST['bonus_percentage'],
            $_POST['bonus_threshold']
        ]);
        $_SESSION['message'] = "تم تحديث الهدف بنجاح.";
    } catch (Exception $e) {
        $_SESSION['error'] = "خطأ في تحديث الهدف: " . $e->getMessage();
    }
    header('Location: hr_performance.php?month=' . $selected_month);
    exit;
}

// Fetch performance data with targets
try {
    $perf_query = "
        SELECT 
            tm.id as employee_id, tm.name_ar, tm.position_ar,
            p.month,
            COALESCE(p.service_count, 0) as service_count,
            COALESCE(p.revenue_sum, 0) as revenue_sum,
            COALESCE(t.service_target, 0) as target_service_count,
            COALESCE(t.revenue_target, 0) as target_revenue,
            p.service_achievement, p.revenue_achievement, p.bonus_earned,
            t.bonus_percentage, t.bonus_threshold
        FROM team_members tm
        LEFT JOIN hr_performance p ON tm.id = p.employee_id AND p.month = :month
        LEFT JOIN hr_targets t ON tm.id = t.employee_id AND t.month = :month
        WHERE tm.is_active = 1 AND tm.is_specialist = 1
        ORDER BY tm.name_ar ASC
    ";
    $stmt = $db->prepare($perf_query);
    $stmt->bindParam(':month', $selected_month);
    $stmt->execute();
    $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $performance_data = [];
    error_log("HR Performance page error: " . $e->getMessage());
}

$page_title = 'أداء الأخصائيات والمستهدفات';
ob_start();
?>
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
        <h1 class="text-xl font-bold"><?= $page_title ?></h1>
        <div class="flex items-center gap-3">
            <a href="hr_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm whitespace-nowrap">العودة للوحة التحكم</a>
            <form method="GET">
                <input type="month" name="month" value="<?= $selected_month ?>" onchange="this.form.submit()" class="border rounded p-2">
            </form>
        </div>
    </div>

    <!-- Performance and Targets Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full border">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-3 text-right">الأخصائية</th>
                    <th class="p-3 text-right">الأداء الفعلي (خدمات/إيراد)</th>
                    <th class="p-3 text-right">المستهدف (خدمات/إيراد)</th>
                    <th class="p-3 text-right">نسبة الإنجاز (خدمات/إيراد)</th>
                    <th class="p-3 text-right">المكافأة المكتسبة</th>
                    <th class="p-3 text-center">تعديل الهدف</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($performance_data as $perf): ?>
                <tr class="border-b">
                    <td class="p-3"><?= htmlspecialchars($perf['name_ar']) ?></td>
                    <td class="p-3"><?= $perf['service_count'] ?> / <?= number_format($perf['revenue_sum'], 2) ?> ر.س</td>
                    <td class="p-3"><?= $perf['target_service_count'] ?> / <?= number_format($perf['target_revenue'], 2) ?> ر.س</td>
                    <td class="p-3"><?= number_format($perf['service_achievement'] ?? 0, 1) ?>% / <?= number_format($perf['revenue_achievement'] ?? 0, 1) ?>%</td>
                    <td class="p-3 font-semibold text-green-700"><?= number_format($perf['bonus_earned'] ?? 0, 2) ?> ر.س</td>
                    <td class="p-3 text-center">
                        <button onclick="openTargetModal(<?= htmlspecialchars(json_encode($perf)) ?>)" class="text-blue-600">تحديد الهدف</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                 <?php if (empty($performance_data)): ?>
                    <tr><td colspan="6" class="text-center py-10 text-gray-500">لا توجد بيانات أداء لهذا الشهر.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Target Setting Modal -->
<div id="targetModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg">
        <h2 class="text-xl font-bold mb-4">تحديد الهدف لـ <span id="modalEmployeeName"></span> لشهر <?= $selected_month ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="set_target">
            <input type="hidden" name="employee_id" id="modalEmployeeId">
            <div class="grid grid-cols-2 gap-4">
                <div><label>مستهدف الخدمات</label><input type="number" name="service_target" id="modalServiceTarget" class="mt-1 w-full border rounded p-2"></div>
                <div><label>مستهدف الإيرادات</label><input type="number" step="0.01" name="revenue_target" id="modalRevenueTarget" class="mt-1 w-full border rounded p-2"></div>
                <div><label>نسبة المكافأة (%)</label><input type="number" step="0.1" name="bonus_percentage" id="modalBonusPercentage" class="mt-1 w-full border rounded p-2"></div>
                <div><label>عتبة تحقيق المكافأة (%)</label><input type="number" step="1" name="bonus_threshold" id="modalBonusThreshold" class="mt-1 w-full border rounded p-2" placeholder="e.g., 100"></div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeTargetModal()" class="px-4 py-2 border rounded">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-mai-brown text-white rounded">حفظ الهدف</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTargetModal(data) {
    document.getElementById('modalEmployeeName').innerText = data.name_ar;
    document.getElementById('modalEmployeeId').value = data.employee_id;
    document.getElementById('modalServiceTarget').value = data.target_service_count;
    document.getElementById('modalRevenueTarget').value = data.target_revenue;
    document.getElementById('modalBonusPercentage').value = data.bonus_percentage;
    document.getElementById('modalBonusThreshold').value = data.bonus_threshold;
    document.getElementById('targetModal').classList.remove('hidden');
}

function closeTargetModal() {
    document.getElementById('targetModal').classList.add('hidden');
}
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>