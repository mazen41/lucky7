<?php
// Prevent browser caching
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * Customer Segments Management - MAI Salon CRM
 * CORRECTED: Code now fully matches the provided database schema,
 * fixing create, edit, and auto-segmentation logic.
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// --- START: PHP LOGIC FOR CRUD & AJAX ACTIONS ---

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            
            // ACTION: CREATE a new manual segment
            case 'create_segment':
                $query = "INSERT INTO customer_segments 
                            (name, name_ar, description, color, segment_type, rules, is_automatic, created_by) 
                          VALUES (?, ?, ?, ?, 'custom', '{}', 0, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['name_ar'], // Using name_ar for both name and name_ar for simplicity
                    $_POST['name_ar'],
                    $_POST['description'],
                    $_POST['color'],
                    $admin['id']
                ]);
                $_SESSION['message'] = "تم إنشاء الشريحة الجديدة بنجاح.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;

            // ACTION: UPDATE an existing segment
            case 'edit_segment':
                $query = "UPDATE customer_segments SET name = ?, name_ar = ?, description = ?, color = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['name_ar'],
                    $_POST['name_ar'],
                    $_POST['description'],
                    $_POST['color'],
                    $_POST['segment_id']
                ]);
                $_SESSION['message'] = "تم تحديث الشريحة بنجاح.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;

            // ACTION: DELETE a segment
            case 'delete_segment':
                $segment_id = $_POST['segment_id'];
                $db->prepare("DELETE FROM customer_segment_assignments WHERE segment_id = ?")->execute([$segment_id]);
                $db->prepare("DELETE FROM customer_segments WHERE id = ?")->execute([$segment_id]);
                $_SESSION['message'] = "تم حذف الشريحة بنجاح.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            
            // ACTION: RUN AUTO-SEGMENTATION (AJAX)
            case 'run_auto_segment':
                header('Content-Type: application/json');
                $db->beginTransaction();

                // 1. Define and store the rules for automatic segments in the database
                $auto_segments_rules = [
                    'عملاء VIP' => [
                        'type' => 'spending',
                        'color' => '#FFD700',
                        'desc' => 'العملاء الأكثر إنفاقًا بناءً على إجمالي المبيعات',
                        'rules' => json_encode(['min_spent' => (float)$_POST['vip_min_spent']])
                    ],
                    'عملاء منتظمون' => [
                        'type' => 'loyalty',
                        'color' => '#10B981',
                        'desc' => 'العملاء الأكثر زيارة بناءً على عدد الفواتير',
                        'rules' => json_encode(['min_visits' => (int)$_POST['regular_min_visits']])
                    ],
                    'عملاء جدد' => [
                        'type' => 'custom',
                        'color' => '#3B82F6',
                        'desc' => 'العملاء الذين تم تسجيلهم خلال فترة محددة',
                        'rules' => json_encode(['new_within_days' => (int)$_POST['new_days']])
                    ],
                    'عملاء غير نشطين' => [
                        'type' => 'inactivity',
                        'color' => '#6B7280',
                        'desc' => 'العملاء الذين لم يقوموا بأي عملية شراء منذ فترة',
                        'rules' => json_encode(['inactive_for_days' => (int)$_POST['inactive_days']])
                    ]
                ];
                
                $segment_ids = [];
                $stmt = $db->prepare("
                    INSERT INTO customer_segments (name, name_ar, description, segment_type, rules, color, is_automatic, created_by) 
                    VALUES (:name, :name_ar, :desc, :type, :rules, :color, 1, :admin_id)
                    ON DUPLICATE KEY UPDATE 
                        rules = VALUES(rules), description = VALUES(description), color = VALUES(color), id = LAST_INSERT_ID(id)
                ");
                $id_stmt = $db->prepare("SELECT id FROM customer_segments WHERE name = ? AND is_automatic = 1");

                foreach ($auto_segments_rules as $name => $details) {
                    $stmt->execute([
                        ':name' => $name,
                        ':name_ar' => $name,
                        ':desc' => $details['desc'],
                        ':type' => $details['type'],
                        ':rules' => $details['rules'],
                        ':color' => $details['color'],
                        ':admin_id' => $admin['id']
                    ]);
                    $id_stmt->execute([$name]);
                    $segment_ids[$name] = $id_stmt->fetchColumn();
                }

                // 2. Clear previous *automatic* assignments
                $db->exec("DELETE csa FROM customer_segment_assignments csa JOIN customer_segments cs ON csa.segment_id = cs.id WHERE cs.is_automatic = 1");

                $assignments_made = 0;
                $assign_stmt = $db->prepare("INSERT IGNORE INTO customer_segment_assignments (customer_id, segment_id) VALUES (?, ?)");

                // 3. Apply rules based on POST data - CORRECTED QUERIES
                
                // VIP Customers: Based on total_spent
                $vip_query = $db->prepare("SELECT id FROM customers WHERE total_spent >= ? AND status = 'active'");
                $vip_query->execute([$_POST['vip_min_spent']]);
                while ($customer_id = $vip_query->fetchColumn()) {
                    if ($assign_stmt->execute([$customer_id, $segment_ids['عملاء VIP']])) $assignments_made++;
                }
                
                // Regular Customers: Based on visit_count
                $regular_query = $db->prepare("SELECT id FROM customers WHERE visit_count >= ? AND status = 'active'");
                $regular_query->execute([$_POST['regular_min_visits']]);
                while ($customer_id = $regular_query->fetchColumn()) {
                    if ($assign_stmt->execute([$customer_id, $segment_ids['عملاء منتظمون']])) $assignments_made++;
                }

                // New Customers: Based on created_at
                $new_query = $db->prepare("SELECT id FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND status = 'active'");
                $new_query->execute([$_POST['new_days']]);
                while ($customer_id = $new_query->fetchColumn()) {
                    if ($assign_stmt->execute([$customer_id, $segment_ids['عملاء جدد']])) $assignments_made++;
                }

                // Inactive Customers: Based on last_visit_date
                $inactive_query = $db->prepare("SELECT id FROM customers WHERE (last_visit_date IS NULL OR last_visit_date < DATE_SUB(NOW(), INTERVAL ? DAY)) AND status = 'active'");
                $inactive_query->execute([$_POST['inactive_days']]);
                while ($customer_id = $inactive_query->fetchColumn()) {
                    if ($assign_stmt->execute([$customer_id, $segment_ids['عملاء غير نشطين']])) $assignments_made++;
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'تم التصنيف بنجاح', 'assignments_made' => $assignments_made]);
                exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        
        if (!empty($_POST['action']) && $_POST['action'] === 'run_auto_segment') {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } else {
            $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
        }
        exit;
    }
}

// --- END PHP LOGIC ---

// Data fetching for display
$segments_query = "
    SELECT cs.*, COUNT(csa.customer_id) as actual_count
    FROM customer_segments cs
    LEFT JOIN customer_segment_assignments csa ON cs.id = csa.segment_id
    WHERE cs.is_active = 1
    GROUP BY cs.id
    ORDER BY cs.name_ar ASC
";
$segments_stmt = $db->prepare($segments_query);
$segments_stmt->execute();
$segments = $segments_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_customers_stmt = $db->query("SELECT COUNT(*) FROM customers WHERE status = 'active'");
$total_customers = $total_customers_stmt->fetchColumn();

$stats_query = "
    SELECT
        COUNT(*) as total_segments,
        SUM(CASE WHEN is_automatic = 1 THEN 1 ELSE 0 END) as automatic_segments,
        SUM(CASE WHEN is_automatic = 0 THEN 1 ELSE 0 END) as manual_segments
    FROM customer_segments
    WHERE is_active = 1
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'شرائح العملاء';
$page_subtitle = 'إدارة وعرض البيانات';
ob_start();
?>
<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-mai-brown rounded-lg flex items-center justify-center text-white">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-mai-dark-brown">شرائح العملاء</h1>
                <p class="text-xs text-gray-500">إدارة وتصنيف العملاء حسب الشرائح المختلفة</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="openAutoSegModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                <i class="fas fa-robot"></i>
                تشغيل التصنيف التلقائي
            </button>
            <button onclick="openCreateSegmentModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                <i class="fas fa-plus"></i>
                إضافة شريحة جديدة
            </button>
        </div>
    </div>
</div>

<!-- Auto Segmentation Modal -->
<div id="autoSegModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="min-h-screen w-full flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">إعدادات التصنيف التلقائي</h3>
                <button onclick="closeAutoSegModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form id="autoSegForm" onsubmit="submitAutoSeg(event)">
                <input type="hidden" name="action" value="run_auto_segment">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">حد الإنفاق لعملاء VIP (ر.س)</label><input type="number" step="0.01" name="vip_min_spent" value="5000" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">عدد الزيارات للمنتظمين</label><input type="number" name="regular_min_visits" value="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">عميل جديد خلال (يوم)</label><input type="number" name="new_days" value="30" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">عميل غير نشط بعد (يوم)</label><input type="number" name="inactive_days" value="90" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></div>
                </div>
                <div class="flex gap-3 mt-6"><button type="button" onclick="closeAutoSegModal()" class="flex-1 px-4 py-2 border rounded-lg">إلغاء</button><button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg">تشغيل الآن</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="p-6">
    <?php if (isset($_SESSION['message'])): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p><?= $_SESSION['message'] ?></p></div><?php unset($_SESSION['message']); endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><p><?= $_SESSION['error'] ?></p></div><?php unset($_SESSION['error']); endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">إجمالي الشرائح</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $stats['total_segments'] ?? 0 ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-layer-group text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">شرائح تلقائية</p>
                    <p class="text-2xl font-bold text-green-600"><?= $stats['automatic_segments'] ?? 0 ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-robot text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">شرائح يدوية</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $stats['manual_segments'] ?? 0 ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">إجمالي العملاء</p>
                    <p class="text-2xl font-bold text-orange-600"><?= $total_customers ?></p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-friends text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($segments)): ?>
        <div class="text-center py-12"><i class="fas fa-layer-group text-6xl text-gray-300 mb-4"></i><h3 class="text-xl font-medium text-gray-600 mb-2">لا توجد شرائح عملاء</h3><button onclick="openCreateSegmentModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg"><i class="fas fa-plus ml-2"></i>إنشاء شريحة جديدة</button></div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($segments as $segment): ?>
        <div class="bg-white rounded-xl shadow-sm border flex flex-col">
            <div class="p-6 border-b flex-grow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background-color: <?= htmlspecialchars($segment['color']) ?>20;"><i class="fas fa-users text-xl" style="color: <?= htmlspecialchars($segment['color']) ?>;"></i></div>
                    <?php if ($segment['is_automatic']): ?><span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">تلقائي</span><?php else: ?><span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">يدوي</span><?php endif; ?>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2"><?= htmlspecialchars($segment['name_ar']) ?></h3>
                <p class="text-sm text-gray-600 mb-4 h-12 overflow-hidden"><?= htmlspecialchars($segment['description']) ?></p>
                <div class="flex items-center justify-between">
                    <div class="text-center"><p class="text-2xl font-bold" style="color: <?= htmlspecialchars($segment['color']) ?>;"><?= $segment['actual_count'] ?></p><p class="text-xs text-gray-500">عميل</p></div>
                    <div class="text-center"><p class="text-sm font-medium text-gray-600"><?= $total_customers > 0 ? round(($segment['actual_count'] / $total_customers) * 100, 1) : 0 ?>%</p><p class="text-xs text-gray-500">من الإجمالي</p></div>
                </div>
            </div>
            <div class="p-4 bg-gray-50">
                <div class="flex gap-2">
                    <button onclick="viewSegmentCustomers(<?= $segment['id'] ?>)" class="w-full bg-white text-gray-700 px-3 py-2 rounded-lg text-sm border"><i class="fas fa-eye ml-1"></i>عرض</button>
                    <button onclick='openEditSegmentModal(<?= htmlspecialchars(json_encode($segment)) ?>)' class="w-full bg-blue-600 text-white px-3 py-2 rounded-lg text-sm"><i class="fas fa-edit ml-1"></i>تعديل</button>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد؟');" class="w-full">
                        <input type="hidden" name="action" value="delete_segment"><input type="hidden" name="segment_id" value="<?= $segment['id'] ?>"><button type="submit" class="w-full bg-red-600 text-white px-3 py-2 rounded-lg text-sm"><i class="fas fa-trash ml-1"></i>حذف</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Create Segment Modal -->
<div id="createSegmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold">إضافة شريحة جديدة</h3><button onclick="closeCreateSegmentModal()" class="text-gray-400"><i class="fas fa-times text-xl"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_segment">
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-2">اسم الشريحة (بالعربية)</label><input type="text" name="name_ar" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="block text-sm font-medium mb-2">الوصف</label><textarea name="description" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
                <div><label class="block text-sm font-medium mb-2">اللون</label><input type="color" name="color" value="#3B82F6" class="w-full h-10 border rounded-lg"></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeCreateSegmentModal()" class="flex-1 px-4 py-2 border rounded-lg">إلغاء</button><button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg">إضافة</button></div>
        </form>
    </div>
</div>

<!-- Edit Segment Modal -->
<div id="editSegmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold">تعديل الشريحة</h3><button onclick="closeEditSegmentModal()" class="text-gray-400"><i class="fas fa-times text-xl"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_segment"><input type="hidden" name="segment_id" id="edit_segment_id">
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-2">اسم الشريحة (بالعربية)</label><input type="text" name="name_ar" id="edit_name_ar" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="block text-sm font-medium mb-2">الوصف</label><textarea name="description" id="edit_description" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
                <div><label class="block text-sm font-medium mb-2">اللون</label><input type="color" name="color" id="edit_color" class="w-full h-10 border rounded-lg"></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeEditSegmentModal()" class="flex-1 px-4 py-2 border rounded-lg">إلغاء</button><button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg">حفظ</button></div>
        </form>
    </div>
</div>

<script>
function openCreateSegmentModal() { document.getElementById('createSegmentModal').classList.remove('hidden'); }
function closeCreateSegmentModal() { document.getElementById('createSegmentModal').classList.add('hidden'); }
function openEditSegmentModal(segment) {
    document.getElementById('edit_segment_id').value = segment.id;
    document.getElementById('edit_name_ar').value = segment.name_ar;
    document.getElementById('edit_description').value = segment.description;
    document.getElementById('edit_color').value = segment.color;
    document.getElementById('editSegmentModal').classList.remove('hidden');
}
function closeEditSegmentModal() { document.getElementById('editSegmentModal').classList.add('hidden'); }
function viewSegmentCustomers(segmentId) { window.location.href = `clients.php?segment=${segmentId}`; }
function openAutoSegModal() { document.getElementById('autoSegModal').classList.remove('hidden'); }
function closeAutoSegModal() { document.getElementById('autoSegModal').classList.add('hidden'); }

function submitAutoSeg(event) {
    event.preventDefault();
    const form = document.getElementById('autoSegForm');
    const formData = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري التشغيل...';
    btn.disabled = true;

    fetch('', { method: 'POST', body: formData })
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => { throw new Error(err.message || 'Server error') });
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
            alert('تم تشغيل التصنيف التلقائي بنجاح. تم إجراء ' + (data.assignments_made || 0) + ' عملية تعيين.');
            closeAutoSegModal();
            location.reload();
        } else {
            alert('خطأ في التصنيف التلقائي: ' + (data.message || 'غير معروف'));
        }
      })
      .catch(err => {
        console.error(err);
        alert('حدث خطأ فادح: ' + err.message);
      })
      .finally(() => { 
          btn.innerHTML = original; 
          btn.disabled = false; 
      });
}
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>