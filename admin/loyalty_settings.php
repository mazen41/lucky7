<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
 $admin = getCurrentAdmin();

 $database = new Database();
 $db = $database->getConnection();

// ===================================================================
// == START: DATABASE ACTIONS (FORM SUBMISSIONS)
// ===================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db->beginTransaction();
    
    try {
        switch ($action) {
            // --- UPDATE GENERAL LOYALTY RULES ---
            case 'update_rules':
                $rules_to_update = $_POST['rules'] ?? [];
                $active_rules = $_POST['active_rules'] ?? [];

                $stmt = $db->prepare(
                    "UPDATE loyalty_rules SET rule_value = :value, is_active = :active WHERE rule_key = :key"
                );

                $all_keys_stmt = $db->query("SELECT rule_key FROM loyalty_rules");
                $all_keys = $all_keys_stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($all_keys as $key) {
                    $value = $rules_to_update[$key] ?? null;
                    $is_active = isset($active_rules[$key]) ? 1 : 0;

                    if ($value !== null) {
                        $stmt->execute([
                            ':value'  => $value,
                            ':active' => $is_active,
                            ':key'    => $key
                        ]);
                    }
                }
                $_SESSION['success_message'] = 'تم تحديث قواعد نظام الولاء بنجاح.';
                break;

            // --- ADD NEW LOYALTY TIER ---
            case 'add_tier':
                $stmt = $db->prepare(
                    "INSERT INTO loyalty_tiers 
                        (name_ar, name_en, min_points, discount_percent, tier_color, tier_icon, benefits_ar, is_active, sort_order) 
                     VALUES 
                        (:name_ar, :name_en, :min_points, :discount_percent, :tier_color, :tier_icon, :benefits_ar, :is_active, :sort_order)"
                );
                $stmt->execute([
                    ':name_ar'         => $_POST['tier_name_ar'],
                    ':name_en'         => $_POST['tier_name_en'],
                    ':min_points'      => $_POST['min_points'],
                    ':discount_percent'=> $_POST['discount_percent'],
                    ':tier_color'      => $_POST['color'],
                    ':tier_icon'       => $_POST['tier_icon'],
                    ':benefits_ar'     => $_POST['benefits_ar'],
                    ':is_active'       => isset($_POST['is_active']) ? 1 : 0,
                    ':sort_order'      => $_POST['sort_order'] ?? 0
                ]);
                $_SESSION['success_message'] = 'تم إضافة مستوى ولاء جديد بنجاح.';
                break;

            // --- UPDATE EXISTING TIER ---
            case 'update_tier':
                $stmt = $db->prepare(
                    "UPDATE loyalty_tiers 
                     SET name_ar = :name_ar, name_en = :name_en, min_points = :min_points, discount_percent = :discount_percent, 
                         tier_color = :tier_color, tier_icon = :tier_icon, benefits_ar = :benefits_ar, is_active = :is_active, sort_order = :sort_order
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':id'              => $_POST['tier_id'],
                    ':name_ar'         => $_POST['tier_name_ar'],
                    ':name_en'         => $_POST['tier_name_en'],
                    ':min_points'      => $_POST['min_points'],
                    ':discount_percent'=> $_POST['discount_percent'],
                    ':tier_color'      => $_POST['color'],
                    ':tier_icon'       => $_POST['tier_icon'],
                    ':benefits_ar'     => $_POST['benefits_ar'],
                    ':is_active'       => isset($_POST['is_active']) ? 1 : 0,
                    ':sort_order'      => $_POST['sort_order'] ?? 0
                ]);
                $_SESSION['success_message'] = 'تم تحديث مستوى الولاء بنجاح.';
                break;
            
            // --- DELETE TIER ---
            case 'delete_tier':
                $stmt = $db->prepare("DELETE FROM loyalty_tiers WHERE id = ?");
                $stmt->execute([$_POST['tier_id']]);
                $_SESSION['success_message'] = 'تم حذف مستوى الولاء بنجاح.';
                break;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error_message'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    header('Location: loyalty_settings.php');
    exit;
}

// ===================================================================
// == START: DATA FETCHING FOR PAGE DISPLAY
// ===================================================================

 $rules_stmt = $db->query("SELECT rule_key, rule_value, description_ar, is_active FROM loyalty_rules");
 $loyalty_rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);

 $tiers_stmt = $db->query("SELECT * FROM loyalty_tiers ORDER BY min_points ASC");
 $loyalty_tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);

 $page_title = 'نظام الولاء';
 $page_subtitle = 'إدارة قواعد ومستويات الولاء';
ob_start();
?>

<!-- ================= PAGE LAYOUT (kept the same design) ================= -->

<div class="bg-white rounded-xl shadow mb-6 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-gray-800">إعدادات نظام الولاء</h1>
                <p class="text-sm text-gray-500">إدارة القواعد العامة ومستويات العملاء</p>
            </div>
        </div>
        <a href="settings.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm"><i class="fas fa-arrow-right ml-2"></i>العودة للإعدادات</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Loyalty Rules -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-4">قواعد نظام الولاء</h2>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="update_rules">
                <?php foreach ($loyalty_rules as $rule): ?>
                <div class="p-4 border rounded-lg bg-gray-50">
                    <label for="rule_<?= htmlspecialchars($rule['rule_key']) ?>" class="block text-sm font-medium text-gray-800 mb-2"><?= htmlspecialchars($rule['description_ar']) ?></label>
                    <div class="flex items-center gap-3">
                        <input type="number" step="any" name="rules[<?= htmlspecialchars($rule['rule_key']) ?>]" id="rule_<?= htmlspecialchars($rule['rule_key']) ?>" 
                               value="<?= htmlspecialchars($rule['rule_value']) ?>" 
                               class="w-full p-2 border border-gray-300 rounded-lg">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="active_rules[<?= htmlspecialchars($rule['rule_key']) ?>]" <?= $rule['is_active'] ? 'checked' : '' ?> class="h-5 w-5 rounded border-gray-300 text-green-500 focus:ring-green-500">
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition mt-4">
                    <i class="fas fa-save mr-2"></i>حفظ قواعد الولاء
                </button>
            </form>
        </div>
    </div>

    <!-- Loyalty Tiers -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-xl font-bold text-gray-800">إدارة مستويات الولاء</h2>
                <button onclick="openTierModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>إضافة مستوى جديد</button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium">الاسم (عربي)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium">الاسم (إنجليزي)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium">الحد الأدنى للنقاط</th>
                            <th class="px-4 py-3 text-right text-xs font-medium">الخصم (%)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium">مزايا</th>
                            <th class="px-4 py-3 text-center text-xs font-medium">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($loyalty_tiers as $tier): ?>
                        <tr>
                            <td class="px-4 py-4 font-medium"><?= htmlspecialchars($tier['name_ar']) ?></td>
                            <td class="px-4 py-4"><?= htmlspecialchars($tier['name_en']) ?></td>
                            <td class="px-4 py-4"><?= htmlspecialchars($tier['min_points']) ?></td>
                            <td class="px-4 py-4"><?= htmlspecialchars($tier['discount_percent']) ?>%</td>
                            <td class="px-4 py-4"><?= htmlspecialchars($tier['benefits_ar']) ?></td>
                            <td class="px-4 py-4 text-center">
                                <button onclick='openTierModal(<?= json_encode($tier) ?>)' class="text-indigo-600 hover:text-indigo-900">تعديل</button>
                                <form method="POST" class="inline-block ml-4" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                    <input type="hidden" name="action" value="delete_tier">
                                    <input type="hidden" name="tier_id" value="<?= $tier['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">حذف</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($loyalty_tiers)): ?>
                            <tr><td colspan="6" class="text-center py-6 text-gray-500">لا توجد مستويات ولاء.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tier Modal -->
<div id="tierModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <form id="tierForm" method="POST">
            <input type="hidden" name="action" id="modal_action">
            <input type="hidden" name="tier_id" id="modal_tier_id">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 id="modal_title" class="text-xl font-bold text-gray-800"></h3>
                <button type="button" onclick="closeTierModal()" class="text-gray-400 hover:text-red-600">&times;</button>
            </div>
            <div class="p-6 grid grid-cols-2 gap-x-6 gap-y-4">
                <div><label class="block text-sm">الاسم (عربي)</label><input type="text" name="tier_name_ar" id="modal_tier_name_ar" required class="mt-1 w-full p-2 border rounded-lg"></div>
                <div><label class="block text-sm">الاسم (إنجليزي)</label><input type="text" name="tier_name_en" id="modal_tier_name_en" required class="mt-1 w-full p-2 border rounded-lg"></div>
                <div><label class="block text-sm">النقاط المطلوبة</label><input type="number" name="min_points" id="modal_min_points" required class="mt-1 w-full p-2 border rounded-lg"></div>
                <div><label class="block text-sm">نسبة الخصم (%)</label><input type="number" name="discount_percent" id="modal_discount_percent" step="0.1" min="0" class="mt-1 w-full p-2 border rounded-lg"></div>
                <div class="col-span-2"><label class="block text-sm">المزايا (عربي)</label><textarea name="benefits_ar" id="modal_benefits_ar" class="mt-1 w-full p-2 border rounded-lg"></textarea></div>
                <div><label class="block text-sm">اللون</label><input type="color" name="color" id="modal_color" class="mt-1 w-full h-10 p-1 border rounded-lg"></div>
                <div><label class="block text-sm">الأيقونة</label><input type="text" name="tier_icon" id="modal_tier_icon" class="mt-1 w-full p-2 border rounded-lg"></div>
                <div><label class="block text-sm">الترتيب</label><input type="number" name="sort_order" id="modal_sort_order" class="mt-1 w-full p-2 border rounded-lg"></div>
                <div class="flex items-center"><input type="checkbox" name="is_active" id="modal_is_active" class="mr-2"><label>مفعل</label></div>
            </div>
            <div class="p-5 bg-gray-50 border-t flex justify-end gap-3">
                <button type="button" onclick="closeTierModal()" class="px-5 py-2 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg">حفظ</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tierModal = document.getElementById('tierModal');
    const tierForm = document.getElementById('tierForm');
    
    window.openTierModal = function(tierData = null) {
        tierForm.reset();
        if (tierData) {
            document.getElementById('modal_title').innerText = 'تعديل مستوى';
            document.getElementById('modal_action').value = 'update_tier';
            document.getElementById('modal_tier_id').value = tierData.id;
            document.getElementById('modal_tier_name_ar').value = tierData.name_ar;
            document.getElementById('modal_tier_name_en').value = tierData.name_en;
            document.getElementById('modal_min_points').value = tierData.min_points;
            document.getElementById('modal_discount_percent').value = tierData.discount_percent;
            document.getElementById('modal_color').value = tierData.tier_color;
            document.getElementById('modal_tier_icon').value = tierData.tier_icon;
            document.getElementById('modal_benefits_ar').value = tierData.benefits_ar;
            document.getElementById('modal_sort_order').value = tierData.sort_order;
            document.getElementById('modal_is_active').checked = tierData.is_active == 1;
        } else {
            document.getElementById('modal_title').innerText = 'إضافة مستوى جديد';
            document.getElementById('modal_action').value = 'add_tier';
        }
        tierModal.classList.remove('hidden');
        tierModal.classList.add('flex');
    }

    window.closeTierModal = function() {
        tierModal.classList.add('hidden');
        tierModal.classList.remove('flex');
    }
});
</script>

<?php
if (isset($_SESSION['error_message'])): ?>
    <div class="fixed top-5 right-5 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-lg"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="fixed top-5 right-5 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <script>setTimeout(()=>{document.querySelector('.fixed.top-5.right-5')?.remove();},3000);</script>
<?php endif; ?>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>