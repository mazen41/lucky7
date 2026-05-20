<?php

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();

// Define hasPermission function for this page
if (!function_exists('hasPermission')) {
    function hasPermission($permissionKey) {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }
        
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if RBAC tables exist
            $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return true; // Allow if no RBAC tables
            }
            
            require_once __DIR__ . '/../app/Core/UserManager.php';
            $userManager = new UserManager();
            return $userManager->hasPermission($_SESSION['admin_id'], $permissionKey);
            
        } catch (Exception $e) {
            error_log("Permission check failed in team.php: " . $e->getMessage());
            return true; // Default to allow
        }
    }
}

$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $query = "INSERT INTO team_members (name_ar, position_ar, bio_ar, experience_years, is_active) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['name_ar'],
                    $_POST['position_ar'],
                    $_POST['bio_ar'],
                    $_POST['experience_years'],
                    isset($_POST['is_active']) ? 1 : 0
                ]);
                $success = "تم إضافة عضو الفريق بنجاح";
                break;
                
            case 'edit':
                $query = "UPDATE team_members SET name_ar = ?, position_ar = ?, bio_ar = ?, experience_years = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['name_ar'],
                    $_POST['position_ar'],
                    $_POST['bio_ar'],
                    $_POST['experience_years'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['id']
                ]);
                $success = "تم تحديث عضو الفريق بنجاح";
                break;
                
            case 'delete':
                $query = "DELETE FROM team_members WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['id']]);
                $success = "تم حذف عضو الفريق بنجاح";
                break;
        }
    }
}

// Get team members
$query = "SELECT * FROM team_members ORDER BY sort_order ASC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'الفريق';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>


    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold text-mai-brown">MAI</span>
                        <span class="text-sm text-gray-600 mr-2">لوحة التحكم</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                    <a href="../index.php" target="_blank" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-external-link-alt ml-1"></i>
                        عرض الموقع
                    </a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-sign-out-alt ml-1"></i>
                        تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-mai-dark-brown">إدارة الفريق</h1>
                <p class="text-gray-600 mt-2">إضافة وتعديل أعضاء فريق العمل</p>
            </div>

            <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- Add New Team Member -->
            <div class="bg-white rounded-xl shadow-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-mai-dark-brown">إضافة عضو جديد</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الاسم (عربي)</label>
                            <input type="text" name="name_ar" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">المنصب (عربي)</label>
                            <input type="text" name="position_ar" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">النبذة الشخصية (عربي)</label>
                            <textarea name="bio_ar" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">سنوات الخبرة</label>
                            <input type="number" name="experience_years" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" checked class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                            <label for="is_active" class="mr-2 text-sm text-gray-700">نشط</label>
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                                <i class="fas fa-plus ml-2"></i>
                                إضافة عضو الفريق
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Team Members List -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-mai-dark-brown">أعضاء الفريق</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($team_members)): ?>
                        <p class="text-gray-500 text-center py-8">لا يوجد أعضاء فريق حتى الآن</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($team_members as $member): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-center mb-4">
                                    <div class="w-20 h-20 bg-mai-cream rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-user text-mai-brown text-2xl"></i>
                                    </div>
                                    <h3 class="font-bold text-mai-dark-brown"><?php echo htmlspecialchars($member['name_ar']); ?></h3>
                                    <p class="text-mai-brown text-sm"><?php echo htmlspecialchars($member['position_ar']); ?></p>
                                    <p class="text-gray-600 text-xs mt-1"><?php echo $member['experience_years']; ?> سنوات خبرة</p>
                                </div>
                                
                                <div class="text-center space-x-2 space-x-reverse">
                                    <button onclick="editMember(<?php echo htmlspecialchars(json_encode($member)); ?>)" 
                                            class="text-mai-brown hover:text-mai-dark-brown">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا العضو؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="mt-3">
                                    <span class="px-2 py-1 rounded-full text-xs <?php echo $member['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $member['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-mai-dark-brown">تعديل عضو الفريق</h3>
                    <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" id="editForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الاسم (عربي)</label>
                        <input type="text" name="name_ar" id="edit_name_ar" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">المنصب (عربي)</label>
                        <input type="text" name="position_ar" id="edit_position_ar" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">النبذة الشخصية (عربي)</label>
                        <textarea name="bio_ar" id="edit_bio_ar" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">سنوات الخبرة</label>
                        <input type="number" name="experience_years" id="edit_experience_years" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                        <label for="edit_is_active" class="mr-2 text-sm text-gray-700">نشط</label>
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end space-x-4 space-x-reverse">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            إلغاء
                        </button>
                        <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                            <i class="fas fa-save ml-2"></i>
                            حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editMember(member) {
            document.getElementById('edit_id').value = member.id;
            document.getElementById('edit_name_ar').value = member.name_ar;
            document.getElementById('edit_position_ar').value = member.position_ar;
            document.getElementById('edit_bio_ar').value = member.bio_ar;
            document.getElementById('edit_experience_years').value = member.experience_years;
            document.getElementById('edit_is_active').checked = member.is_active == 1;
            
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>