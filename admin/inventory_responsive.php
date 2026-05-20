<?php
/**
 * MAI Salon - Responsive Inventory Management System
 * Fully responsive inventory system with mobile support
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'auth.php';

// Check admin authentication
checkAdminAuth();

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get inventory statistics (placeholder - replace with actual logic)
$stats = [
    'total_products' => 35,
    'low_stock' => 8,
    'out_of_stock' => 3,
    'inventory_value' => '12,450.00'
];

// Set page title and start output buffering for the responsive layout
$page_title = 'المخزون والمنتجات';
ob_start();
?>

<!-- Inventory Management Container -->
<div class="inventory-container">
    <!-- Header with Stats -->
    <?php include 'includes/inventory_header.php'; ?>
    
    <!-- Search and Filters -->
    <?php include 'includes/inventory_search.php'; ?>
    
    <!-- Inventory Table -->
    <?php include 'includes/inventory_table.php'; ?>
</div>

<!-- New Product Modal (hidden by default) -->
<div id="new-product-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة منتج جديد</h3>
            <button id="close-product-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-product-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم المنتج*</label>
                            <input type="text" name="product_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رمز المنتج*</label>
                            <input type="text" name="product_code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الباركود</label>
                            <input type="text" name="barcode" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">التصنيف*</label>
                            <select name="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر التصنيف...</option>
                                <option value="hair">منتجات الشعر</option>
                                <option value="skin">منتجات البشرة</option>
                                <option value="makeup">مكياج</option>
                                <option value="nails">منتجات الأظافر</option>
                                <option value="tools">أدوات وأجهزة</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing and Stock -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">التسعير والمخزون</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">سعر التكلفة*</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="cost_price" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">ر.س</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">سعر البيع*</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="selling_price" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">ر.س</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نسبة الضريبة</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="tax_rate" value="15" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المخزون الحالي*</label>
                            <input type="number" name="current_stock" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الحد الأدنى للمخزون</label>
                            <input type="number" name="min_stock" value="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">وحدة القياس</label>
                            <select name="unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="piece">قطعة</option>
                                <option value="box">صندوق</option>
                                <option value="bottle">زجاجة</option>
                                <option value="tube">أنبوب</option>
                                <option value="ml">مل</option>
                                <option value="gram">جرام</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Product Details -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">تفاصيل المنتج</h4>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">وصف المنتج</label>
                            <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الماركة / المصنع</label>
                            <input type="text" name="brand" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">صورة المنتج</label>
                            <div class="border border-dashed border-gray-300 rounded-lg p-4 text-center">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                <p class="text-sm text-gray-500">اسحب الصورة هنا أو انقر للتصفح</p>
                                <input type="file" name="product_image" class="hidden" id="product-image-upload">
                                <button type="button" id="upload-image-btn" class="mt-2 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors text-sm">
                                    اختر صورة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-product-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-product-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ المنتج
            </button>
        </div>
    </div>
</div>

<!-- New Category Modal (hidden by default) -->
<div id="new-category-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة تصنيف جديد</h3>
            <button id="close-category-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-category-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم التصنيف*</label>
                    <input type="text" name="category_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                    <textarea name="category_description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">اللون</label>
                    <div class="flex flex-wrap gap-2">
                        <div class="w-8 h-8 bg-blue-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="blue"></div>
                        <div class="w-8 h-8 bg-green-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="green"></div>
                        <div class="w-8 h-8 bg-red-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="red"></div>
                        <div class="w-8 h-8 bg-yellow-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="yellow"></div>
                        <div class="w-8 h-8 bg-purple-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="purple"></div>
                        <div class="w-8 h-8 bg-pink-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="pink"></div>
                        <div class="w-8 h-8 bg-indigo-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="indigo"></div>
                        <div class="w-8 h-8 bg-gray-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="gray"></div>
                    </div>
                    <input type="hidden" name="category_color" value="blue">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الأيقونة</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-bottle-water">
                            <i class="fas fa-bottle-water"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-spray-can">
                            <i class="fas fa-spray-can"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-cut">
                            <i class="fas fa-cut"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-paint-brush">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-pump-soap">
                            <i class="fas fa-pump-soap"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-magic">
                            <i class="fas fa-magic"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-box">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-tag">
                            <i class="fas fa-tag"></i>
                        </div>
                    </div>
                    <input type="hidden" name="category_icon" value="fa-bottle-water">
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end">
            <button id="cancel-category-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-category-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ التصنيف
            </button>
        </div>
    </div>
</div>

<!-- Inventory Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize statistics
    document.getElementById('total-products').textContent = '<?php echo $stats['total_products']; ?>';
    document.getElementById('low-stock').textContent = '<?php echo $stats['low_stock']; ?>';
    document.getElementById('out-of-stock').textContent = '<?php echo $stats['out_of_stock']; ?>';
    document.getElementById('inventory-value').textContent = '<?php echo $stats['inventory_value']; ?> ر.س';
    document.getElementById('total-results').textContent = '<?php echo $stats['total_products']; ?>';
    
    // New product modal
    const newProductBtn = document.getElementById('new-product-btn');
    const newProductModal = document.getElementById('new-product-modal');
    const closeProductModal = document.getElementById('close-product-modal');
    const cancelProductBtn = document.getElementById('cancel-product-btn');
    
    newProductBtn.addEventListener('click', function() {
        newProductModal.classList.remove('hidden');
    });
    
    function closeProductModal() {
        newProductModal.classList.add('hidden');
    }
    
    closeProductModal.addEventListener('click', closeProductModal);
    cancelProductBtn.addEventListener('click', closeProductModal);
    
    // Close modal when clicking outside
    newProductModal.addEventListener('click', function(event) {
        if (event.target === newProductModal) {
            closeProductModal();
        }
    });
    
    // New category modal
    const newCategoryBtn = document.getElementById('new-category-btn');
    const newCategoryModal = document.getElementById('new-category-modal');
    const closeCategoryModal = document.getElementById('close-category-modal');
    const cancelCategoryBtn = document.getElementById('cancel-category-btn');
    
    newCategoryBtn.addEventListener('click', function() {
        newCategoryModal.classList.remove('hidden');
    });
    
    function closeCategoryModal() {
        newCategoryModal.classList.add('hidden');
    }
    
    closeCategoryModal.addEventListener('click', closeCategoryModal);
    cancelCategoryBtn.addEventListener('click', closeCategoryModal);
    
    // Close modal when clicking outside
    newCategoryModal.addEventListener('click', function(event) {
        if (event.target === newCategoryModal) {
            closeCategoryModal();
        }
    });
    
    // Color picker for category
    const colorPickers = document.querySelectorAll('[data-color]');
    const categoryColorInput = document.querySelector('input[name="category_color"]');
    
    colorPickers.forEach(picker => {
        picker.addEventListener('click', function() {
            // Remove selected border from all pickers
            colorPickers.forEach(p => p.classList.remove('border-gray-800', 'border-4'));
            // Add selected border to clicked picker
            this.classList.add('border-gray-800', 'border-4');
            // Update hidden input
            categoryColorInput.value = this.dataset.color;
        });
    });
    
    // Icon picker for category
    const iconPickers = document.querySelectorAll('[data-icon]');
    const categoryIconInput = document.querySelector('input[name="category_icon"]');
    
    iconPickers.forEach(picker => {
        picker.addEventListener('click', function() {
            // Remove selected border from all pickers
            iconPickers.forEach(p => p.classList.remove('border-gray-800', 'border-4'));
            // Add selected border to clicked picker
            this.classList.add('border-gray-800', 'border-4');
            // Update hidden input
            categoryIconInput.value = this.dataset.icon;
        });
    });
    
    // Image upload button
    const uploadImageBtn = document.getElementById('upload-image-btn');
    const productImageUpload = document.getElementById('product-image-upload');
    
    uploadImageBtn.addEventListener('click', function() {
        productImageUpload.click();
    });
    
    // Search and filters
    const resetFiltersBtn = document.getElementById('reset-filters-btn');
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    
    resetFiltersBtn.addEventListener('click', function() {
        document.getElementById('product-search').value = '';
        document.getElementById('category-filter').selectedIndex = 0;
        document.getElementById('stock-filter').selectedIndex = 0;
        document.getElementById('sort-by').selectedIndex = 0;
    });
    
    applyFiltersBtn.addEventListener('click', function() {
        // This would typically trigger an AJAX request to filter products
        // For demo purposes, we'll just show an alert
        alert('تم تطبيق الفلاتر');
    });
    
    // Save product form
    const saveProductBtn = document.getElementById('save-product-btn');
    const newProductForm = document.getElementById('new-product-form');
    
    saveProductBtn.addEventListener('click', function() {
        if (newProductForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ المنتج بنجاح');
            closeProductModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newProductForm.querySelector(':invalid');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
    
    // Save category form
    const saveCategoryBtn = document.getElementById('save-category-btn');
    const newCategoryForm = document.getElementById('new-category-form');
    
    saveCategoryBtn.addEventListener('click', function() {
        if (newCategoryForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ التصنيف بنجاح');
            closeCategoryModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newCategoryForm.querySelector(':invalid');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
