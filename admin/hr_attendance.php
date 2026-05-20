<?php
/**
 * HR Management - Attendance Management
 * Final version following the requested logic:
 * 1. SELECT from hr_attendance first.
 * 2. Get names in a separate step. No initial JOIN.
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// --- POST logic for editing remains the same ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'edit') {
            $stmt_user = $db->prepare("SELECT user_id FROM team_members WHERE id = ? LIMIT 1");
            $stmt_user->execute([$_POST['employee_id']]);
            $employee_user_id = $stmt_user->fetchColumn();

            if (!$employee_user_id) {
                throw new Exception("Error: Could not find the User ID for the selected employee.");
            }

            $total_hours_worked = 0;
            if (!empty($_POST['check_in']) && !empty($_POST['check_out'])) {
                $check_in_dt = new DateTime($_POST['check_in']);
                $check_out_dt = new DateTime($_POST['check_out']);
                if ($check_out_dt > $check_in_dt) {
                    $interval = $check_in_dt->diff($check_out_dt);
                    $work_seconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                    $break_seconds = ($_POST['break_minutes'] ?? 0) * 60;
                    $net_seconds = $work_seconds - $break_seconds;
                    $total_hours_worked = $net_seconds > 0 ? $net_seconds / 3600 : 0;
                }
            }
            
            $sql = "
                INSERT INTO hr_attendance (employee_id, date, check_in, check_out, break_minutes, total_hours_worked, status)
                VALUES (:employee_id, :date, :check_in, :check_out, :break_minutes, :total_hours_worked, :status)
                ON DUPLICATE KEY UPDATE
                    check_in = VALUES(check_in), check_out = VALUES(check_out),
                    break_minutes = VALUES(break_minutes), total_hours_worked = VALUES(total_hours_worked), status = VALUES(status)
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':employee_id' => $employee_user_id,
                ':date' => $_POST['date'],
                ':check_in' => empty($_POST['check_in']) ? null : $_POST['check_in'],
                ':check_out' => empty($_POST['check_out']) ? null : $_POST['check_out'],
                ':break_minutes' => $_POST['break_minutes'] ?? 0,
                ':total_hours_worked' => number_format($total_hours_worked, 2, '.', ''),
                ':status' => $_POST['status']
            ]);

            $_SESSION['message'] = "تم تحديث سجل الحضور بنجاح.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "خطأ في تحديث السجل: " . $e->getMessage();
    }
    header('Location: hr_attendance.php?date=' . $_POST['date'] . '&employee_id=' . ($_POST['filter_employee_id'] ?? ''));
    exit;
}

// --- DATA FETCHING (AS PER YOUR INSTRUCTIONS) ---
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_employee_id = $_GET['employee_id'] ?? ''; // This is team_members.id

$final_records = [];

try {
    // Step 1: Select all active team members and LEFT JOIN attendance for the selected date
    $query = "
        SELECT t.id AS team_member_id, t.user_id, t.name_ar, a.check_in, a.check_out, a.break_minutes, a.total_hours_worked, a.status, a.date
        FROM team_members t
        LEFT JOIN hr_attendance a ON a.employee_id = t.user_id AND a.date = :filter_date
        WHERE t.is_active = 1
    ";
    $params = [':filter_date' => $filter_date];

    // Filter by employee if provided
    if ($filter_employee_id) {
        $query .= " AND t.id = :team_member_id";
        $params[':team_member_id'] = $filter_employee_id;
    }

    $query .= " ORDER BY t.name_ar ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare final records with default status = absent if NULL
    foreach ($records as $record) {
        if ($record['status'] === null) {
            $record['status'] = 'absent';
            $record['check_in'] = null;
            $record['check_out'] = null;
            $record['break_minutes'] = 0;
            $record['total_hours_worked'] = 0;
            $record['date'] = $filter_date;
        }
        $final_records[] = $record;
    }

    // Fetch active employees for the dropdown filter.
    $employees_stmt = $db->query("SELECT id, name_ar FROM team_members WHERE is_active = 1 ORDER BY name_ar ASC");
    $employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $final_records = [];
    $employees = [];
    $page_error = "خطأ في جلب البيانات: " . $e->getMessage();
}

$page_title = 'إدارة الحضور والإنصراف';
ob_start();
?>

<!-- HTML to display the data -->
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-2xl font-bold text-mai-dark-brown"><?= $page_title ?></h1>
        <div class="flex items-center gap-3">
            <form method="GET" class="flex items-center gap-3">
                <select name="employee_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm">
                    <option value="">كل الموظفين</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($filter_employee_id == $emp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($emp['name_ar']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm">
                <a href="hr_attendance.php" class="text-sm text-blue-600 hover:underline">إعادة تعيين</a>
            </form>
        </div>
    </div>
    <div class="flex items-center gap-4">
                 <a href="hr_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm">العودة للوحة التحكم</a>
           
            </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">الموظف</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">التاريخ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">تسجيل الدخول</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">تسجيل الخروج</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">مدة العمل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">الحالة</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($final_records)): ?>
                    <tr><td colspan="7" class="text-center py-10 text-gray-500">لا يوجد سجلات حضور لهذا اليوم.</td></tr>
                <?php else: ?>
                    <?php foreach ($final_records as $record): ?>
                    <?php 
                        $display_status = $record['status'] ?? 'absent';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4"><?= htmlspecialchars($record['name_ar']) ?></td>
                        <td class="px-6 py-4"><?= date('Y/m/d', strtotime($record['date'])) ?></td>
                        <td class="px-6 py-4"><?= $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '---' ?></td>
                        <td class="px-6 py-4"><?= $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '---' ?></td>
                        <td class="px-6 py-4"><?= !empty($record['total_hours_worked']) ? number_format($record['total_hours_worked'], 2) . ' ساعة' : '---' ?></td>
                        <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full <?= getStatusClass($display_status) ?>"><?= translateStatus($display_status) ?></span></td>
                        <td class="px-6 py-4"><button onclick='openEditModal(<?= json_encode($record) ?>, "<?= $filter_date ?>")' class="text-indigo-600 hover:text-indigo-900">تعديل</button></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <form method="POST" class="p-6">
            <h3 class="text-xl font-bold mb-4">تعديل سجل الحضور</h3>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="employee_id" id="modal_employee_id"> <!-- This will hold team_member_id -->
            <input type="hidden" name="date" id="modal_date">
            <input type="hidden" name="filter_employee_id" value="<?= htmlspecialchars($filter_employee_id) ?>">

            <div class="mb-4">
                <label class="block text-sm font-bold">الموظف:</label>
                <p id="modal_employee_name" class="text-lg"></p>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label>وقت الدخول</label><input type="time" name="check_in" id="modal_check_in" class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div><label>وقت الخروج</label><input type="time" name="check_out" id="modal_check_out" class="mt-1 w-full border-gray-300 rounded-md"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                     <div><label>مدة الراحة (دقائق)</label><input type="number" name="break_minutes" id="modal_break_minutes" value="0" class="mt-1 w-full border-gray-300 rounded-md"></div>
                     <div><label>الحالة</label>
                        <select name="status" id="modal_status" required class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="present">حاضر</option>
                            <option value="absent">غائب</option>
                            <option value="late">متأخر</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-5 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="px-6 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(record, filterDate) {
    // The form needs the primary ID of the team_members table
    document.getElementById('modal_employee_id').value = record.team_member_id;
    document.getElementById('modal_employee_name').innerText = record.name_ar;
    document.getElementById('modal_date').value = record.date;
    document.getElementById('modal_check_in').value = record.check_in || '';
    document.getElementById('modal_check_out').value = record.check_out || '';
    document.getElementById('modal_break_minutes').value = record.break_minutes || 0;
    document.getElementById('modal_status').value = record.status || 'present';
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php
function getStatusClass($status) {
    switch ($status) {
        case 'present': return 'bg-green-100 text-green-800';
        case 'late': return 'bg-yellow-100 text-yellow-800';
        case 'absent': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
function translateStatus($status) {
    $map = [
        'present' => 'حاضر', 'late' => 'متأخر',
        'absent' => 'غائب'
    ];
    return $map[$status] ?? $status;
}

$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>
