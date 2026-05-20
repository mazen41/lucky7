<?php

require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php'; 

checkAdminAuth();

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
            error_log("Permission check failed in reviews.php: " . $e->getMessage());
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
            case 'approve':
                $query = "UPDATE reviews SET is_approved = 1 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['id']]);
                $success = "تم الموافقة على التقييم بنجاح";
                break;
                
            case 'reject':
                $query = "UPDATE reviews SET is_approved = 0 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['id']]);
                $success = "تم رفض التقييم بنجاح";
                break;
                
            case 'delete':
                $query = "DELETE FROM reviews WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['id']]);
                $success = "تم حذف التقييم بنجاح";
                break;
                
            case 'add':
                $query = "INSERT INTO reviews (customer_name, review_text, rating, service_id, is_approved) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_POST['customer_name'],
                    $_POST['review_text'],
                    $_POST['rating'],
                    $_POST['service_id'] ?: null,
                    isset($_POST['is_approved']) ? 1 : 0
                ]);
                $success = "تم إضافة التقييم بنجاح";
                break;
        }
    }
}

// Get reviews with service names
 $query = "SELECT r.*, s.title_ar as service_name 
          FROM reviews r 
          LEFT JOIN services s ON r.service_id = s.id 
          ORDER BY r.created_at DESC";
 $stmt = $db->prepare($query);
 $stmt->execute();
 $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get services for dropdown
 $services_query = "SELECT id, title_ar FROM services WHERE is_active = 1";
 $services_stmt = $db->prepare($services_query);
 $services_stmt->execute();
 $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
 $page_title = 'التقييمات';
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
                <div>
                    <h1 class="text-lg font-bold text-mai-dark-brown">التقييمات</h1>
                    <p class="text-xs text-gray-500">عرض وإدارة تقييمات العملاء</p>
                </div>
            </div>
            
            <!-- START: ADDED BUTTON -->
            <a href="settings.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm flex items-center gap-2 transition-colors">
                <i class="fas fa-arrow-left"></i>
                <span>العودة للإعدادات</span>
            </a>
            <!-- END: ADDED BUTTON -->
            
        </div>
    </div>

    <!-- Main Content -->
    <div class="p-6">
            <!-- This h1 and p are a bit redundant with the header, consider removing them if you like -->
            <div class="mb-8 hidden">
                <h1 class="text-3xl font-bold text-mai-dark-brown">إدارة التقييمات</h1>
                <p class="text-gray-600 mt-2">عرض وإدارة تقييمات العملاء</p>
            </div>

            <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- Add New Review -->
            <div class="bg-white rounded-xl shadow-lg mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-mai-dark-brown">إضافة تقييم جديد</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم العميل</label>
                            <input type="text" name="customer_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">التقييم</label>
                            <select name="rating" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                                <option value="">اختر التقييم</option>
                                <option value="5">5 نجوم - ممتاز</option>
                                <option value="4">4 نجوم - جيد جداً</option>
                                <option value="3">3 نجوم - جيد</option>
                                <option value="2">2 نجوم - مقبول</option>
                                <option value="1">1 نجمة - ضعيف</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الخدمة (اختياري)</label>
                            <select name="service_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                                <option value="">بدون خدمة محددة</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['title_ar']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_approved" id="is_approved" checked class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                            <label for="is_approved" class="mr-2 text-sm text-gray-700">موافق عليه</label>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">نص التقييم</label>
                            <textarea name="review_text" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                                <i class="fas fa-plus ml-2"></i>
                                إضافة التقييم
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reviews List -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-mai-dark-brown">قائمة التقييمات</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($reviews)): ?>
                        <p class="text-gray-500 text-center py-8">لا توجد تقييمات حتى الآن</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($reviews as $review): ?>
                            <div class="border border-gray-200 rounded-lg p-4 <?php echo $review['is_approved'] ? 'bg-green-50' : 'bg-yellow-50'; ?>">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-mai-cream rounded-full flex items-center justify-center ml-3">
                                            <i class="fas fa-user text-mai-brown"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-mai-dark-brown text-sm"><?php echo htmlspecialchars($review['customer_name']); ?></h3>
                                            <div class="text-mai-gold">
                                                <?php for($i = 0; $i < $review['rating']; $i++): ?>
                                                    <i class="fas fa-star text-xs"></i>
                                                <?php endfor; ?>
                                                <?php for($i = $review['rating']; $i < 5; $i++): ?>
                                                    <i class="far fa-star text-xs"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <span class="px-2 py-1 rounded-full text-xs <?php echo $review['is_approved'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $review['is_approved'] ? 'موافق عليه' : 'في الانتظار'; ?>
                                    </span>
                                </div>
                                
                                <p class="text-gray-700 text-sm mb-3"><?php echo htmlspecialchars($review['review_text']); ?></p>
                                
                                <?php if ($review['service_name']): ?>
                                <p class="text-mai-brown text-xs mb-3">الخدمة: <?php echo htmlspecialchars($review['service_name']); ?></p>
                                <?php endif; ?>
                                
                                <p class="text-gray-500 text-xs mb-3"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></p>
                                
                                <div class="flex justify-center space-x-2 space-x-reverse">
                                    <?php if (!$review['is_approved']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?php echo $review['id']; ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm">
                                            <i class="fas fa-check"></i> موافقة
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?php echo $review['id']; ?>">
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-800 text-sm">
                                            <i class="fas fa-times"></i> إلغاء الموافقة
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا التقييم؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $review['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>