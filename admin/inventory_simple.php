<?php
require_once 'auth.php';
require_once 'users_functions.php';

checkAdminAuth();

if (!hasPermission('inventory.manage')) {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$admin = getCurrentAdmin();

// Handle form submissions
$success = '';
$error = '';

if ($_POST && isset($_POST['add_product'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $name_ar = $_POST['name_ar'];
        $name_en = $_POST['name_en'] ?? '';
        $sku = $_POST['sku'];
        $qty = (int)($_POST['qty'] ?? 0);
        $min_qty = (int)($_POST['min_qty'] ?? 0);
        $unit = $_POST['unit'] ?? 'قطعة';
        $purchase_price = $_POST['purchase_price'] ?: null;
        $sale_price = $_POST['sale_price'] ?: null;
        $is_service = isset($_POST['is_service']) ? 1 : 0;
        
        $stmt = $db->prepare("INSERT INTO products (sku, name, name_ar, qty, min_qty, unit, purchase_price, sale_price, type, track_stock, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        
        $type = $is_service ? 'service' : 'product';
        $track_stock = $is_service ? 0 : 1;
        
        $stmt->execute([$sku, $name_en, $name_ar, $qty, $min_qty, $unit, $purchase_price, $sale_price, $type, $track_stock, $admin['id']]);
        
        $success = 'تم إضافة المنتج بنجاح';
    } catch (Exception $e) {
        $error = 'خطأ في إضافة المنتج: ' . $e->getMessage();
    }
}

// Get data
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get categories
    $categories = [];
    try {
        $stmt = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name_ar");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Categories table might not exist yet
    }
    
    // Get products
    $products = [];
    try {
        $stmt = $db->query("SELECT p.*, COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar 
                           FROM products p 
                           LEFT JOIN product_categories pc ON p.category_id = pc.id 
                           WHERE p.is_active = 1 
                           ORDER BY p.created_at DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Products table might not exist yet
    }
    
    // Get low stock products
    $low_stock_products = [];
    try {
        $stmt = $db->query("SELECT p.*, COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar 
                           FROM products p 
                           LEFT JOIN product_categories pc ON p.category_id = pc.id 
                           WHERE p.is_active = 1 AND p.track_stock = 1 AND p.qty <= p.min_qty 
                           ORDER BY p.qty ASC");
        $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle error gracefully
    }
    
} catch (Exception $e) {
    $error = 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage();
    $categories = [];
    $products = [];
    $low_stock_products = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المخزون والمنتجات - صالون MAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'arabic': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        'mai-brown': '#8B4513',
                        'mai-gold': '#D4AF37',
                        'mai-cream': '#F5E6D3',
                        'mai-dark-brown': '#654321',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Tajawal', sans-serif; }
    </style>
</head>
<body class="font-arabic bg-gray-100">
    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 p-8 overflow-x-auto">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-mai-dark-brown">المخزون والمنتجات</h1>
                    <p class="text-gray-600 mt-2">إدارة المنتجات والمخزون وتتبع الحركات</p>
                </div>

                <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?php echo $error; ?>
                    <div class="mt-2">
                        <a href="fix_database_issues.php" class="text-red-800 underline">إصلاح مشاكل قاعدة البيانات</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Low Stock Alerts -->
                <?php if (!empty($low_stock_products)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold text-yellow-800 mb-4">
                        <i class="fas fa-exclamation-triangle ml-2"></i>
                        تنبيهات المخزون المنخفض (<?php echo count($low_stock_products); ?>)
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($low_stock_products as $product): ?>
                        <div class="bg-white border border-yellow-300 rounded-lg p-4">
                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($product['name_ar']); ?></h3>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($product['category_name_ar']); ?></p>
                            <div class="mt-2">
                                <span class="text-red-600 font-bold"><?php echo $product['qty']; ?></span>
                                <span class="text-gray-500">/ <?php echo $product['min_qty']; ?></span>
                                <span class="text-sm text-gray-500 mr-2"><?php echo $product['unit']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-4 mb-6">
                    <button onclick="openAddProductModal()" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-plus ml-2"></i>
                        إضافة منتج جديد
                    </button>
                    <a href="fix_database_issues.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-wrench ml-2"></i>
                        إصلاح قاعدة البيانات
                    </a>
                </div>

                <!-- Products Table -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-mai-dark-brown">قائمة المنتجات (<?php echo count($products); ?>)</h2>
                    </div>
                    
                    <?php if (empty($products)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-boxes text-gray-300 text-6xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">لا توجد منتجات</h3>
                        <p class="text-gray-500 mb-4">ابدأ بإضافة منتج جديد أو إصلاح قاعدة البيانات</p>
                        <button onclick="openAddProductModal()" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                            إضافة منتج جديد
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right py-3 px-4 font-medium text-gray-700">المنتج</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-700">رمز المنتج</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-700">المخزون</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-700">سعر البيع</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-700">النوع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name_ar']); ?></p>
                                            <?php if ($product['name']): ?>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($product['name']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">
                                        <?php echo htmlspecialchars($product['sku']); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($product['type'] == 'service'): ?>
                                            <span class="text-blue-600">خدمة</span>
                                        <?php else: ?>
                                            <span class="<?php echo $product['qty'] <= $product['min_qty'] ? 'text-red-600 font-bold' : 'text-gray-900'; ?>">
                                                <?php echo $product['qty']; ?>
                                            </span>
                                            <span class="text-sm text-gray-500 mr-1"><?php echo $product['unit']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-gray-900 font-medium">
                                        <?php echo $product['sale_price'] ? number_format($product['sale_price'], 2) . ' ريال' : '-'; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($product['type'] == 'service'): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">خدمة</span>
                                        <?php else: ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">منتج</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-mai-dark-brown">إضافة منتج جديد</h3>
                </div>
                <form method="POST" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم المنتج (عربي) *</label>
                            <input type="text" name="name_ar" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم المنتج (إنجليزي)</label>
                            <input type="text" name="name_en" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">رمز المنتج (SKU) *</label>
                            <input type="text" name="sku" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">وحدة القياس</label>
                            <select name="unit" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="قطعة">قطعة</option>
                                <option value="زجاجة">زجاجة</option>
                                <option value="أنبوب">أنبوب</option>
                                <option value="برطمان">برطمان</option>
                                <option value="مل">مل</option>
                                <option value="جرام">جرام</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">سعر الشراء</label>
                            <input type="number" step="0.01" name="purchase_price" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">سعر البيع</label>
                            <input type="number" step="0.01" name="sale_price" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الكمية الابتدائية</label>
                            <input type="number" name="qty" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الحد الأدنى للمخزون</label>
                            <input type="number" name="min_qty" value="5" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_service" class="ml-2">
                                <span class="text-sm text-gray-700">هذا العنصر خدمة (لا يؤثر على المخزون)</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4 space-x-reverse mt-6">
                        <button type="button" onclick="closeAddProductModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                            إلغاء
                        </button>
                        <button type="submit" name="add_product" class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                            إضافة المنتج
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddProductModal() {
            document.getElementById('addProductModal').classList.remove('hidden');
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').classList.add('hidden');
        }
    </script>
</body>
</html>
