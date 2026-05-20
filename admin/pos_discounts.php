<?php

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_discount') {
            $code = strtoupper(trim($_POST['code']));
            $name_ar = trim($_POST['name_ar']);
            $type = $_POST['type'];
            $value = floatval($_POST['value']);
            $applicable_to = $_POST['applicable_to'];
            $min_amount = floatval($_POST['min_amount'] ?? 0);
            $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
            $start_date = $_POST['start_date'] ?: null;
            $end_date = $_POST['end_date'] ?: null;
            
            // Validate inputs
            if (empty($code) || empty($name_ar) || $value <= 0) {
                throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
            }
            
            if ($type === 'percentage' && $value > 100) {
                throw new Exception('نسبة الخصم لا يمكن أن تزيد عن 100%');
            }
            
            // Check if code already exists
            $check_query = "SELECT id FROM discount_codes WHERE code = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$code]);
            
            if ($check_stmt->fetch()) {
                throw new Exception('كود الخصم موجود بالفعل');
            }
            
            // Insert new discount code
            $insert_query = "INSERT INTO discount_codes (
                code, name_ar, type, value, applicable_to, min_amount, 
                usage_limit, start_date, end_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->execute([
                $code, $name_ar, $type, $value, $applicable_to, 
                $min_amount, $usage_limit, $start_date, $end_date, $admin['id']
            ]);
            
            $success_message = 'تم إنشاء كود الخصم بنجاح';
            
        } elseif ($action === 'toggle_status') {
            $discount_id = intval($_POST['discount_id']);
            $new_status = intval($_POST['new_status']);
            
            $update_query = "UPDATE discount_codes SET is_active = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_status, $discount_id]);
            
            $success_message = $new_status ? 'تم تفعيل كود الخصم' : 'تم إلغاء تفعيل كود الخصم';
            
        } elseif ($action === 'delete_discount') {
            $discount_id = intval($_POST['discount_id']);
            
            // Check if code has been used
            $usage_query = "SELECT COUNT(*) as count FROM invoices WHERE discount_code_id = ?";
            $usage_stmt = $db->prepare($usage_query);
            $usage_stmt->execute([$discount_id]);
            $usage_result = $usage_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usage_result['count'] > 0) {
                throw new Exception('لا يمكن حذف كود خصم تم استخدامه في فواتير');
            }
            
            $delete_query = "DELETE FROM discount_codes WHERE id = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->execute([$discount_id]);
            
            $success_message = 'تم حذف كود الخصم بنجاح';
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all discount codes
$discounts_query = "SELECT dc.*, au.username as created_by_name,
                   (SELECT COUNT(*) FROM invoices WHERE discount_code_id = dc.id) as usage_count
                   FROM discount_codes dc
                   LEFT JOIN admin_users au ON dc.created_by = au.id
                   ORDER BY dc.created_at DESC";
$discounts_stmt = $db->prepare($discounts_query);
$discounts_stmt->execute();
$discount_codes = $discounts_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'خصومات نقاط البيع';
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
                        <span class="text-sm text-gray-600 mr-2">إدارة أكواد الخصم</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                    <a href="pos.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة لنقطة البيع
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Messages -->
        <?php if (isset($success_message)): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 ml-2"></i>
                <span class="text-green-800"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 ml-2"></i>
                <span class="text-red-800"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Create New Discount Code -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-mai-dark-brown mb-4">إنشاء كود خصم جديد</h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_discount">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">كود الخصم *</label>
                        <input type="text" name="code" required maxlength="50" 
                               placeholder="مثال: WELCOME10"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown uppercase">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم الخصم *</label>
                        <input type="text" name="name_ar" required maxlength="255"
                               placeholder="خصم الترحيب"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع الخصم *</label>
                        <select name="type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            <option value="percentage">نسبة مئوية</option>
                            <option value="fixed">مبلغ ثابت</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">قيمة الخصم *</label>
                        <input type="number" name="value" step="0.01" min="0.01" required
                               placeholder="10"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ينطبق على</label>
                        <select name="applicable_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            <option value="all">الكل</option>
                            <option value="services">الخدمات فقط</option>
                            <option value="products">المنتجات فقط</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الحد الأدنى للمبلغ</label>
                        <input type="number" name="min_amount" step="0.01" min="0" value="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">حد الاستخدام</label>
                        <input type="number" name="usage_limit" min="1"
                               placeholder="اتركه فارغاً للاستخدام غير المحدود"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ البداية</label>
                            <input type="date" name="start_date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الانتهاء</label>
                            <input type="date" name="end_date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-mai-brown text-white py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-plus ml-2"></i>
                        إنشاء كود الخصم
                    </button>
                </form>
            </div>

            <!-- Existing Discount Codes -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-mai-dark-brown">أكواد الخصم الموجودة</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($discount_codes)): ?>
                        <p class="text-gray-500 text-center py-8">لا توجد أكواد خصم</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($discount_codes as $discount): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 space-x-reverse">
                                            <span class="font-bold text-lg text-mai-brown"><?php echo htmlspecialchars($discount['code']); ?></span>
                                            <span class="px-2 py-1 rounded-full text-xs <?php echo ($discount['is_active'] ?? 1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ($discount['is_active'] ?? 1) ? 'نشط' : 'غير نشط'; ?>
                                            </span>
                                            <?php if (($discount['end_date'] ?? null) && strtotime($discount['end_date']) < time()): ?>
                                            <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">منتهي الصلاحية</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($discount['name_ar'] ?? 'كود خصم'); ?></div>
                                            <div class="text-sm text-gray-600">
                                                الخصم: <?php echo ($discount['type'] ?? 'percentage') === 'percentage' ? ($discount['value'] ?? 0) . '%' : number_format($discount['value'] ?? 0, 2) . ' ر.س'; ?>
                                                <?php if (($discount['min_amount'] ?? 0) > 0): ?>
                                                | الحد الأدنى: <?php echo number_format($discount['min_amount'] ?? 0, 2); ?> ر.س
                                                <?php endif; ?>
                                                <?php if ($discount['usage_limit'] ?? null): ?>
                                                | الحد الأقصى: <?php echo $discount['usage_limit'] ?? 0; ?> استخدام
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                تم الاستخدام: <?php echo $discount['usage_count'] ?? 0; ?> مرة
                                                <?php if (($discount['start_date'] ?? null) || ($discount['end_date'] ?? null)): ?>
                                                | الفترة: 
                                                <?php echo ($discount['start_date'] ?? null) ? date('Y-m-d', strtotime($discount['start_date'])) : 'غير محدد'; ?>
                                                إلى
                                                <?php echo ($discount['end_date'] ?? null) ? date('Y-m-d', strtotime($discount['end_date'])) : 'غير محدد'; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 space-x-reverse">
                                        <!-- Toggle Status -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo ($discount['is_active'] ?? 1) ? 0 : 1; ?>">
                                            <button type="submit" class="text-sm px-3 py-1 rounded <?php echo ($discount['is_active'] ?? 1) ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?>">
                                                <?php echo ($discount['is_active'] ?? 1) ? 'إلغاء التفعيل' : 'تفعيل'; ?>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete -->
                                        <?php if (($discount['usage_count'] ?? 0) == 0): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الكود؟')">
                                            <input type="hidden" name="action" value="delete_discount">
                                            <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function discountManager() {
            return {
                // Add any JavaScript functionality here if needed
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>