<?php
/**
 * HR Management - Leave Management
 */
session_start();
require_once 'auth.php'; // For hasPermission()
require_once '../config/database.php';
require_once 'users_functions.php'; // For checkAdminAuth() if it's there

checkAdminAuth();

// --- PERMISSION CHECK ---
if (!hasPermission('hr.leaves.manage')) {
    $_SESSION['error'] = 'ليس لديك الصلاحية لإدارة الإجازات.';
    header('Location: hr_dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['admin_id'];

// --- LOGIC FOR APPROVING/REJECTING LEAVES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    
    try {
        if ($_POST['action'] === 'approve') {
            $stmt = $db->prepare("UPDATE hr_leaves SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$admin_id, $leave_id]);
            $_SESSION['message'] = "تمت الموافقة على طلب الإجازة بنجاح.";
        }
        elseif ($_POST['action'] === 'reject') {
            $notes = trim($_POST['manager_notes'] ?? '');
            if (empty($notes)) {
                throw new Exception("سبب الرفض مطلوب.");
            }
            $stmt = $db->prepare("UPDATE hr_leaves SET status = 'rejected', approved_by = ?, approved_at = NOW(), manager_notes = ? WHERE id = ?");
            $stmt->execute([$admin_id, $notes, $leave_id]);
            $_SESSION['message'] = "تم رفض طلب الإجازة.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
    header('Location: hr_leaves.php');
    exit;
}

// --- DATA FETCHING ---
$filter_status = $_GET['status'] ?? '';
$where_sql = '';
$params = [];
if ($filter_status && in_array($filter_status, ['pending', 'approved', 'rejected'])) {
    $where_sql = "WHERE hl.status = ?";
    $params[] = $filter_status;
}

try {
    // ==== FIX: Corrected the JOIN condition to match hr_leaves.employee_id with team_members.user_id ====
    $query = "
        SELECT 
            hl.*, 
            tm.name_ar 
        FROM 
            hr_leaves hl 
        LEFT JOIN 
            team_members tm ON hl.employee_id = tm.user_id 
        $where_sql 
        ORDER BY 
            hl.created_at DESC";
        
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $leave_requests = [];
    $page_error = "خطأ في جلب البيانات: " . $e->getMessage();
}

$page_title = 'إدارة الإجازات';
ob_start();
?>
<!-- #################### ALPINE.JS COMPONENT #################### -->
<div x-data="{ 
    isModalOpen: false, 
    action: '', 
    leaveId: null, 
    employeeName: '',
    openModal(actionType, id, name) {
        this.action = actionType;
        this.leaveId = id;
        this.employeeName = name;
        this.isModalOpen = true;
    } 
}">

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-mai-dark-brown"><?= $page_title ?></h1>
            <div class="flex items-center gap-4">
                 <a href="hr_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm">العودة للوحة التحكم</a>
                <a href="?status=pending" class="px-4 py-2 rounded-lg text-sm <?= $filter_status=='pending'?'bg-yellow-500 text-white':'bg-gray-200' ?>">قيد المراجعة</a>
                <a href="?status=approved" class="px-4 py-2 rounded-lg text-sm <?= $filter_status=='approved'?'bg-green-500 text-white':'bg-gray-200' ?>">تمت الموافقة</a>
                <a href="?status=rejected" class="px-4 py-2 rounded-lg text-sm <?= $filter_status=='rejected'?'bg-red-500 text-white':'bg-gray-200' ?>">مرفوض</a>
                <a href="?" class="text-sm text-blue-600">عرض الكل</a>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">الموظف</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">نوع الإجازة</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">من تاريخ</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">إلى تاريخ</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">عدد الأيام</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">الحالة</th>
                        <th class="px-5 py-3 text-center text-xs font-medium text-gray-500">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($leave_requests as $request): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4"><?= htmlspecialchars($request['name_ar'] ?? 'غير معروف') ?></td>
                        <td class="px-5 py-4"><?= translateLeaveType($request['type']) ?></td>
                        <td class="px-5 py-4"><?= date('Y/m/d', strtotime($request['from_date'])) ?></td>
                        <td class="px-5 py-4"><?= date('Y/m/d', strtotime($request['to_date'])) ?></td>
                        <td class="px-5 py-4"><?= $request['days_count'] ?></td>
                        <td class="px-5 py-4"><span class="px-2 py-1 text-xs rounded-full <?= getStatusClass($request['status']) ?>"><?= translateStatus($request['status']) ?></span></td>
                        <td class="px-5 py-4 text-center">
                            <?php if ($request['status'] === 'pending'): ?>
                            <button @click="openModal('approve', <?= $request['id'] ?>, '<?= htmlspecialchars($request['name_ar'] ?? 'Unknown') ?>')" type="button" class="text-green-600 hover:text-green-900 font-semibold">موافقة</button>
                            <span class="mx-2 text-gray-300">|</span>
                            <button @click="openModal('reject', <?= $request['id'] ?>, '<?= htmlspecialchars($request['name_ar'] ?? 'Unknown') ?>')" type="button" class="text-red-600 hover:text-red-900 font-semibold">رفض</button>
                            <?php else: ?>
                                <span class="text-sm text-gray-400">تمت المعالجة</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                     <?php if (empty($leave_requests)): ?>
                        <tr><td colspan="7" class="text-center py-10 text-gray-500">لا توجد طلبات إجازة.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- #################### ACTION MODAL #################### -->
    <div x-show="isModalOpen" x-trap.inert.noscroll="isModalOpen" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" x-cloak>
        <form method="POST" @submit.prevent="if (action === 'reject' && $refs.notes.value.trim() === '') { alert('سبب الرفض مطلوب'); return; } $el.submit()">
            <div @click.outside="isModalOpen = false" class="bg-white rounded-xl w-full max-w-lg shadow-xl" x-show="isModalOpen" x-transition>
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold" :class="action === 'approve' ? 'text-green-600' : 'text-red-600'" x-text="action === 'approve' ? 'تأكيد الموافقة' : 'تأكيد الرفض'"></h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-gray-600">هل أنت متأكد من <strong x-text="action === 'approve' ? 'الموافقة' : 'رفض'"></strong> طلب الإجازة للموظف <strong x-text="employeeName"></strong>؟</p>
                    
                    <input type="hidden" name="action" :value="action">
                    <input type="hidden" name="leave_id" :value="leaveId">
                    
                    <div x-show="action === 'reject'" x-transition>
                        <label for="manager_notes" class="block text-sm font-medium text-gray-700">سبب الرفض (مطلوب)</label>
                        <textarea x-ref="notes" name="manager_notes" id="manager_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-xl">
                    <button type="button" @click="isModalOpen = false" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 font-semibold">إلغاء</button>
                    <button type="submit" class="text-white px-6 py-2 rounded-lg font-bold" :class="action === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">
                        <span x-text="action === 'approve' ? 'نعم، موافقة' : 'نعم، رفض'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>

</div> <!-- End of Alpine Component -->

<?php
function getStatusClass($status) {
    switch ($status) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function translateStatus($status) {
    $map = [
        'approved' => 'تمت الموافقة',
        'pending'  => 'قيد المراجعة',
        'rejected' => 'مرفوض'
    ];
    return $map[$status] ?? $status;
}
function translateLeaveType($type) {
    $map = ['annual' => 'سنوية', 'sick' => 'مرضية', 'unpaid' => 'بدون راتب', 'emergency' => 'طارئة'];
    return $map[$type] ?? $type;
}

$page_content = ob_get_clean();
// Add AlpineJS for modal functionality
$page_specific_footer_scripts = '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>';
include 'layouts/responsive_base.php';
?>