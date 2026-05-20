<?php
/**
 * إدارة الموارد البشرية - الطلبات العامة (إصدار مخصص بالعمليات المباشرة والفلاتر والترقيم)
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['admin_id'];

// --- معالجة العمليات ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // العملية: الموافقة على الطلب
        if ($_POST['action'] === 'approve_request' && isset($_POST['request_id'])) {
            $stmt = $db->prepare(
                "UPDATE hr_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$admin_id, $_POST['request_id']]);
            $_SESSION['message'] = "تمت الموافقة على الطلب بنجاح.";
        }
        
        // العملية: رفض الطلب
        if ($_POST['action'] === 'reject_request' && isset($_POST['request_id'])) {
            $stmt = $db->prepare(
                "UPDATE hr_requests SET status = 'rejected', approved_by = ?, manager_notes = ? WHERE id = ?"
            );
            $stmt->execute([$admin_id, $_POST['manager_notes'], $_POST['request_id']]);
            $_SESSION['message'] = "تم رفض الطلب.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET)); // إعادة تحميل الصفحة بنفس الفلاتر
    exit;
}

// --- إعدادات الترقيم (Pagination) ---
$items_per_page = 5; // عدد الطلبات في كل صفحة
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// --- الفلاتر وجلب البيانات ---
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// بناء الاستعلام الأساسي والجمل الشرطية
$base_query = "
    FROM 
        hr_requests r
    LEFT JOIN 
        team_members tm ON r.employee_id = tm.id
";

$where_clauses = [];
$params = [];

if ($filter_status) {
    $where_clauses[] = "r.status = ?";
    $params[] = $filter_status;
}
if ($filter_type) {
    $where_clauses[] = "r.type = ?";
    $params[] = $filter_type;
}
if ($filter_date_from) {
    $where_clauses[] = "r.created_at >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where_clauses[] = "r.created_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

try {
    // 1. حساب العدد الإجمالي للطلبات (لأغراض الترقيم)
    $count_query = "SELECT COUNT(r.id) " . $base_query . $where_sql;
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    $offset = ($current_page - 1) * $items_per_page;

    // 2. جلب الطلبات للصفحة الحالية فقط
    $query = "
        SELECT 
            r.*, 
            tm.name_ar AS employee_name
        " . $base_query . $where_sql . "
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    // إضافة متغيرات الترقيم إلى مصفوفة المعلمات
    $query_params = array_merge($params, [$items_per_page, $offset]);
    
    $stmt = $db->prepare($query);
    // يجب تمرير الأنواع الصحيحة لـ LIMIT و OFFSET
    foreach ($query_params as $key => $val) {
        if (is_int($val)) {
            $stmt->bindValue($key + 1, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key + 1, $val, PDO::PARAM_STR);
        }
    }
    
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $requests = [];
    $total_items = 0;
    $total_pages = 0;
    $page_error = "حدث خطأ أثناء جلب الطلبات: " . $e->getMessage();
}


// ألوان الحالات
function getStatusBadge($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'in_progress' => 'bg-blue-100 text-blue-800',
        'completed' => 'bg-purple-100 text-purple-800',
    ];
    $style = $colors[$status] ?? 'bg-gray-100 text-gray-800';
    $names = [
        'pending' => 'قيد المراجعة',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
    ];
    return "<span class='px-3 py-1 text-xs font-semibold rounded-full $style'>" . htmlspecialchars($names[$status] ?? $status) . "</span>";
}

$page_title = 'إدارة طلبات الموظفين';
ob_start();
?>
<div class="bg-gray-50 p-4 sm:p-6 lg:p-8 min-h-screen" dir="rtl">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800"><?= $page_title ?></h1>
            <a href="/admin/hr_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 font-semibold">
                العودة إلى لوحة التحكم
            </a>
        </div>

        <!-- رسائل النظام -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert"><p><?= $_SESSION['message']; ?></p></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert"><p><?= $_SESSION['error']; ?></p></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- نموذج الفلاتر -->
        <div class="bg-white p-4 rounded-xl shadow mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">الحالة</label>
                    <select name="status" id="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">الكل</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>قيد المراجعة</option>
                        <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>تمت الموافقة</option>
                        <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                        <option value="in_progress" <?= $filter_status == 'in_progress' ? 'selected' : '' ?>>قيد التنفيذ</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>مكتمل</option>
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">النوع</label>
                    <select name="type" id="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">الكل</option>
                        <option value="salary_advance" <?= $filter_type == 'salary_advance' ? 'selected' : '' ?>>سلفة مالية</option>
                        <option value="schedule_change" <?= $filter_type == 'schedule_change' ? 'selected' : '' ?>>تعديل جدول</option>
                        <option value="equipment" <?= $filter_type == 'equipment' ? 'selected' : '' ?>>معدات</option>
                        <option value="training" <?= $filter_type == 'training' ? 'selected' : '' ?>>تدريب</option>
                        <option value="transfer" <?= $filter_type == 'transfer' ? 'selected' : '' ?>>نقل</option>
                        <option value="other" <?= $filter_type == 'other' ? 'selected' : '' ?>>أخرى</option>
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">من تاريخ</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">إلى تاريخ</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex space-x-2 rtl:space-x-reverse">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold">تصفية</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="w-full text-center bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 font-semibold">إعادة ضبط</a>
                </div>
            </form>
        </div>

        <!-- قائمة الطلبات -->
        <div class="space-y-4">
            <?php if (!empty($requests)): ?>
                <?php foreach ($requests as $request): ?>
                <div class="bg-white rounded-xl shadow-md p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($request['title']) ?></p>
                            <p class="text-sm text-gray-500 mt-1">
                                الموظف: <strong class="font-medium text-gray-700"><?= htmlspecialchars($request['employee_name'] ?? 'غير متوفر') ?></strong> |
                                التاريخ: <?= date('Y/m/d', strtotime($request['created_at'])) ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <?= getStatusBadge($request['status']) ?>
                        </div>
                    </div>
                    <p class="mt-4 text-gray-700"><?= nl2br(htmlspecialchars($request['description'])) ?></p>
                    
                    <?php if ($request['manager_notes']): ?>
                    <div class="mt-4 pt-3 border-t border-gray-200">
                        <p class="text-sm font-semibold text-gray-600">ملاحظات المدير:</p>
                        <p class="text-sm text-gray-700 bg-yellow-50 p-2 rounded-md"><?= nl2br(htmlspecialchars($request['manager_notes'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'pending'): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end items-center gap-3">
                        <button onclick="openRejectModal(<?= $request['id'] ?>)" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 font-semibold text-sm">
                            رفض
                        </button>
                        <form method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد أنك تريد الموافقة على هذا الطلب؟');">
                            <input type="hidden" name="action" value="approve_request">
                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-semibold text-sm">
                                موافقة
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-16 bg-white rounded-xl shadow-md">
                    <p class="text-gray-500 text-lg">لا توجد طلبات مطابقة للفلاتر المحددة.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==== EDITED PART: PAGINATION START ==== -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-8 bg-white rounded-xl shadow p-4 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
            <!-- Page number search -->
            <div class="w-full md:w-auto">
                <form method="GET" class="flex items-center space-x-2 rtl:space-x-reverse">
                    <!-- Hidden fields to preserve filters -->
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                    
                    <input 
                        type="number" 
                        name="page" 
                        min="1" 
                        max="<?= $total_pages ?>" 
                        value="<?= $current_page ?>" 
                        class="w-20 border-gray-300 rounded-md shadow-sm text-center"
                        aria-label="اذهب إلى صفحة"
                    >
                    <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 font-semibold text-sm">اذهب</button>
                </form>
            </div>
            
            <!-- Pagination Links -->
            <nav aria-label="Pagination">
                <ul class="inline-flex items-center -space-x-px rtl:space-x-reverse">
                    <?php
                        // Function to build page link
                        function page_link($page, $text, $is_disabled = false, $is_current = false) {
                            $base_classes = "flex items-center justify-center px-4 h-10 leading-tight border border-gray-300";
                            $current_classes = "z-10 bg-blue-600 text-white border-blue-600";
                            $disabled_classes = "bg-gray-100 text-gray-400 cursor-not-allowed";
                            $default_classes = "bg-white text-gray-600 hover:bg-gray-100 hover:text-gray-700";
                            
                            $classes = $base_classes;
                            if ($is_current) $classes .= " " . $current_classes;
                            elseif ($is_disabled) $classes .= " " . $disabled_classes;
                            else $classes .= " " . $default_classes;
                            
                            $query_params = $_GET;
                            $query_params['page'] = $page;
                            $href = $is_disabled ? '#' : '?' . http_build_query($query_params);
                            
                            return "<li><a href='{$href}' class='{$classes}'>{$text}</a></li>";
                        }

                        // Previous button
                        echo page_link($current_page - 1, 'السابق', $current_page <= 1);

                        // Page numbers logic
                        $window = 2; // How many pages to show around the current page
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == 1 || $i == $total_pages || ($i >= $current_page - $window && $i <= $current_page + $window)) {
                                echo page_link($i, $i, false, $i == $current_page);
                            } elseif ($i == 2 || $i == $total_pages - 1) {
                                echo "<li><span class='flex items-center justify-center px-4 h-10 leading-tight text-gray-600 border border-gray-300 bg-white'>...</span></li>";
                            }
                        }

                        // Next button
                        echo page_link($current_page + 1, 'التالي', $current_page >= $total_pages);
                    ?>
                </ul>
            </nav>

            <!-- Page info -->
            <div class="text-sm text-gray-600 w-full md:w-auto text-center">
                صفحة <span class="font-bold"><?= $current_page ?></span> من <span class="font-bold"><?= $total_pages ?></span>
                (إجمالي <?= $total_items ?> طلب)
            </div>
        </div>
        <?php endif; ?>
        <!-- ==== EDITED PART: PAGINATION END ==== -->
        
    </div>
</div>

<!-- نافذة رفض الطلب -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 m-4">
        <h3 class="text-xl font-bold mb-4">سبب الرفض</h3>
        <p class="text-sm text-gray-600 mb-4">يرجى كتابة السبب، سيتم عرضه للموظف.</p>
        <form method="POST">
            <input type="hidden" name="action" value="reject_request">
            <input type="hidden" name="request_id" id="modal_reject_request_id">
            <div>
                <label for="modal_manager_notes" class="sr-only">ملاحظات المدير</label>
                <textarea name="manager_notes" id="modal_manager_notes" rows="4" required class="w-full border-gray-300 rounded-md focus:border-red-500 focus:ring-red-500" placeholder="أدخل السبب هنا..."></textarea>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-5 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">إلغاء</button>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">تأكيد الرفض</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(requestId) {
    document.getElementById('modal_reject_request_id').value = requestId;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>