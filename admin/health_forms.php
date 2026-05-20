<?php

/**
 * Health Forms Management - Dynamic Form Builder Interface
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_template':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                
                $stmt = $db->prepare("INSERT INTO health_form_templates (title, description, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$title, $description, $admin['id']]);
                $success = "تم إنشاء القالب بنجاح";
                break;
                
            case 'toggle_template':
                $template_id = intval($_POST['template_id']);
                $is_active = intval($_POST['is_active']);
                
                $stmt = $db->prepare("UPDATE health_form_templates SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active, $template_id]);
                $success = $is_active ? "تم تفعيل القالب" : "تم إلغاء تفعيل القالب";
                break;
        }
    }
}

// Get templates
$templates_query = "SELECT * FROM health_form_templates ORDER BY created_at DESC";
$templates_stmt = $db->prepare($templates_query);
$templates_stmt->execute();
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get template statistics
$stats_query = "SELECT 
    t.id,
    COUNT(DISTINCT q.id) as question_count,
    COUNT(DISTINCT r.id) as response_count,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_count
FROM health_form_templates t
LEFT JOIN health_form_questions q ON t.id = q.template_id
LEFT JOIN health_form_responses r ON t.id = r.template_id
GROUP BY t.id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = [];
while ($row = $stats_stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['id']] = $row;
}


// Layout configuration
$page_title = 'النماذج الصحية';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>
    <!-- Page Header (content-level) -->
    <div class="bg-white rounded-xl shadow mb-4 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-mai-brown rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-mai-dark-brown">النماذج الصحية</h1>
                    <p class="text-xs text-gray-500">إنشاء وإدارة قوالب النماذج الصحية</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="p-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-mai-dark-brown">إدارة النماذج الصحية</h1>
                <p class="text-gray-600 mt-2">إنشاء وإدارة نماذج التاريخ الصحي للعملاء</p>
            </div>

            <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="mb-6 flex space-x-4 space-x-reverse">
                <button onclick="openCreateTemplateModal()" class="bg-mai-brown text-white px-6 py-3 rounded-lg hover:bg-mai-dark-brown">
                    <i class="fas fa-plus ml-2"></i>إنشاء قالب جديد
                </button>
                <button onclick="openFormBuilderModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-tools ml-2"></i>منشئ النماذج
                </button>
                <button onclick="location.href='health_form_responses.php'" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700">
                    <i class="fas fa-file-alt ml-2"></i>عرض الردود
                </button>
            </div>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($templates as $template): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xl font-bold text-mai-dark-brown"><?php echo htmlspecialchars($template['title']); ?></h3>
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <span class="text-sm text-gray-500">v<?php echo $template['version']; ?></span>
                                <?php if ($template['is_active']): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">نشط</span>
                                <?php else: ?>
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs">غير نشط</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($template['description'] ?? 'لا يوجد وصف'); ?></p>
                        
                        <!-- Statistics -->
                        <div class="grid grid-cols-3 gap-4 mb-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-mai-brown"><?php echo $stats[$template['id']]['question_count'] ?? 0; ?></div>
                                <div class="text-xs text-gray-500">أسئلة</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-blue-600"><?php echo $stats[$template['id']]['response_count'] ?? 0; ?></div>
                                <div class="text-xs text-gray-500">ردود</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600"><?php echo $stats[$template['id']]['completed_count'] ?? 0; ?></div>
                                <div class="text-xs text-gray-500">مكتملة</div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex space-x-2 space-x-reverse">
                            <button onclick="editTemplate(<?php echo $template['id']; ?>)" class="flex-1 bg-mai-brown text-white px-3 py-2 rounded text-sm hover:bg-mai-dark-brown">
                                <i class="fas fa-edit ml-1"></i>تعديل
                            </button>
                            <button onclick="previewTemplate(<?php echo $template['id']; ?>)" class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700">
                                <i class="fas fa-eye ml-1"></i>معاينة
                            </button>
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="action" value="toggle_template">
                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $template['is_active'] ? 0 : 1; ?>">
                                <button type="submit" class="w-full <?php echo $template['is_active'] ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-3 py-2 rounded text-sm">
                                    <i class="fas fa-<?php echo $template['is_active'] ? 'pause' : 'play'; ?> ml-1"></i>
                                    <?php echo $template['is_active'] ? 'إلغاء' : 'تفعيل'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($templates)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-file-medical text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-500 mb-2">لا توجد قوالب</h3>
                    <p class="text-gray-400 mb-4">ابدأ بإنشاء أول قالب نموذج صحي</p>
                    <button onclick="openCreateTemplateModal()" class="bg-mai-brown text-white px-6 py-3 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-plus ml-2"></i>إنشاء قالب جديد
                    </button>
                </div>
                <?php endif; ?>
            </div>
    </div>

    <!-- Create Template Modal -->
    <div id="createTemplateModal" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">إنشاء قالب جديد</h3>
                <button onclick="closeCreateTemplateModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_template">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">عنوان القالب *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">وصف القالب</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" onclick="closeCreateTemplateModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-save ml-2"></i>إنشاء القالب
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form Builder Modal -->
    <div id="formBuilderModal" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">منشئ النماذج</h3>
                <button onclick="closeFormBuilderModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="text-center py-8">
                <i class="fas fa-tools text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-500 mb-2">منشئ النماذج</h3>
                <p class="text-gray-400 mb-4">سيتم تطوير واجهة منشئ النماذج التفاعلية قريباً</p>
                <p class="text-sm text-gray-500">ستتضمن: سحب وإفلات الأسئلة، معاينة مباشرة، إعدادات متقدمة</p>
            </div>
        </div>
    </div>

    <script>
        function openCreateTemplateModal() {
            document.getElementById('createTemplateModal').style.display = 'flex';
        }

        function closeCreateTemplateModal() {
            document.getElementById('createTemplateModal').style.display = 'none';
        }

        function openFormBuilderModal() {
            document.getElementById('formBuilderModal').style.display = 'flex';
        }

        function closeFormBuilderModal() {
            document.getElementById('formBuilderModal').style.display = 'none';
        }

        function editTemplate(templateId) {
            window.location.href = `health_form_builder.php?template_id=${templateId}`;
        }

        function previewTemplate(templateId) {
            window.open(`health_form_preview.php?template_id=${templateId}`, '_blank');
        }

        // Close modals when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('createTemplateModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCreateTemplateModal();
                }
            });

            document.getElementById('formBuilderModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeFormBuilderModal();
                }
            });
        });
    </script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>