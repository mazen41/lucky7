<?php
/**
 * MAI Salon - Responsive Services Management System
 * Fully responsive services management system with mobile support
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

// Set page title and start output buffering for the responsive layout
$page_title = 'إدارة الخدمات';
ob_start();
?>

<!-- Services Management Container -->
<div class="services-container">
    <!-- Header with Stats -->
    <?php include 'includes/services_header.php'; ?>
    
    <!-- Services Table -->
    <?php include 'includes/services_table.php'; ?>
</div>

<!-- New Service Modal (hidden by default) -->
<div id="new-service-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة خدمة جديدة</h3>
            <button id="close-service-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-service-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الخدمة*</label>
                            <input type="text" name="service_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رمز الخدمة*</label>
                            <input type="text" name="service_code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">التصنيف*</label>
                            <select name="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر التصنيف...</option>
                                <option value="hair">خدمات الشعر</option>
                                <option value="skin">خدمات البشرة</option>
                                <option value="makeup">المكياج</option>
                                <option value="nails">الأظافر</option>
                                <option value="spa">السبا</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الأيقونة</label>
                            <select name="icon" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="fa-cut">مقص (قص شعر)</option>
                                <option value="fa-paint-brush">فرشاة (صبغة)</option>
                                <option value="fa-spa">سبا</option>
                                <option value="fa-magic">سحر (مكياج)</option>
                                <option value="fa-hand-sparkles">أظافر</option>
                                <option value="fa-pump-soap">منتجات العناية</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Duration and Price -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المدة والسعر</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المدة (بالدقائق)*</label>
                            <input type="number" name="duration" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">وقت الانتظار (بالدقائق)</label>
                            <input type="number" name="buffer_time" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">السعر*</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="price" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">ر.س</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع السعر</label>
                            <select name="price_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="fixed">ثابت</option>
                                <option value="range">نطاق (يحدد في الحجز)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Description and Details -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">الوصف والتفاصيل</h4>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">وصف الخدمة</label>
                        <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">صورة الخدمة</label>
                            <div class="border border-dashed border-gray-300 rounded-lg p-4 text-center">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                <p class="text-sm text-gray-500">اسحب الصورة هنا أو انقر للتصفح</p>
                                <input type="file" name="service_image" class="hidden" id="service-image-upload">
                                <button type="button" id="upload-image-btn" class="mt-2 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors text-sm">
                                    اختر صورة
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الخيارات المتقدمة</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="is-bookable" name="is_bookable" checked class="ml-2">
                                    <label for="is-bookable" class="text-sm">متاح للحجز</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="is-featured" name="is_featured" class="ml-2">
                                    <label for="is-featured" class="text-sm">خدمة مميزة</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="is-active" name="is_active" checked class="ml-2">
                                    <label for="is-active" class="text-sm">نشط</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-service-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-service-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ الخدمة
            </button>
        </div>
    </div>
</div>

<!-- Services Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add cache-busting query parameter to prevent browser caching issues
    const cacheBuster = '?v=' + new Date().getTime();
    
    // New service modal
    const newServiceBtn = document.getElementById('new-service-btn');
    const newServiceModal = document.getElementById('new-service-modal');
    const closeServiceModal = document.getElementById('close-service-modal');
    const cancelServiceBtn = document.getElementById('cancel-service-btn');
    
    newServiceBtn.addEventListener('click', function() {
        newServiceModal.classList.remove('hidden');
    });
    
    function closeServiceModal() {
        newServiceModal.classList.add('hidden');
    }
    
    closeServiceModal.addEventListener('click', closeServiceModal);
    cancelServiceBtn.addEventListener('click', closeServiceModal);
    
    // Close modal when clicking outside
    newServiceModal.addEventListener('click', function(event) {
        if (event.target === newServiceModal) {
            closeServiceModal();
        }
    });
    
    // Categories and pricing buttons
    const categoriesBtn = document.getElementById('categories-btn');
    const pricingBtn = document.getElementById('pricing-btn');
    
    categoriesBtn.addEventListener('click', function() {
        window.location.href = 'service_categories.php' + cacheBuster;
    });
    
    pricingBtn.addEventListener('click', function() {
        window.location.href = 'service_pricing.php' + cacheBuster;
    });
    
    // Image upload button
    const uploadImageBtn = document.getElementById('upload-image-btn');
    const serviceImageUpload = document.getElementById('service-image-upload');
    
    uploadImageBtn.addEventListener('click', function() {
        serviceImageUpload.click();
    });
    
    // Save service form
    const saveServiceBtn = document.getElementById('save-service-btn');
    const newServiceForm = document.getElementById('new-service-form');
    
    saveServiceBtn.addEventListener('click', function() {
        if (newServiceForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ الخدمة بنجاح');
            closeServiceModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newServiceForm.querySelector(':invalid');
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
