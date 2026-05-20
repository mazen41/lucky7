<?php
/**
 * MAI Salon - Responsive Loyalty System
 * Fully responsive loyalty program with mobile support
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
$page_title = 'برنامج الولاء والنقاط';
ob_start();
?>

<!-- Loyalty System Container -->
<div class="loyalty-container">
    <!-- Header with Stats -->
    <?php include 'includes/loyalty_header.php'; ?>
    
    <!-- Loyalty Tiers -->
    <?php include 'includes/loyalty_tiers.php'; ?>
    
    <!-- Loyalty Members -->
    <?php include 'includes/loyalty_members.php'; ?>
</div>

<!-- New Tier Modal (hidden by default) -->
<div id="new-tier-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة شريحة جديدة</h3>
            <button id="close-tier-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-tier-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الشريحة*</label>
                            <input type="text" name="tier_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المستوى*</label>
                            <input type="number" name="tier_level" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الحد الأدنى للنقاط*</label>
                            <input type="number" name="min_points" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الحد الأقصى للنقاط</label>
                            <input type="number" name="max_points" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <p class="text-xs text-gray-500 mt-1">اتركه فارغاً إذا لم يكن هناك حد أقصى</p>
                        </div>
                    </div>
                </div>
                
                <!-- Benefits -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المزايا والخصومات</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نسبة الخصم*</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="discount_percentage" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">مضاعف النقاط</label>
                            <input type="number" step="0.1" name="points_multiplier" value="1.0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">المزايا الإضافية</label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="benefit-birthday" name="benefit_birthday" class="ml-2">
                                <label for="benefit-birthday" class="text-sm">هدية عيد الميلاد</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="benefit-priority" name="benefit_priority" class="ml-2">
                                <label for="benefit-priority" class="text-sm">أولوية الحجز</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="benefit-free-service" name="benefit_free_service" class="ml-2">
                                <label for="benefit-free-service" class="text-sm">خدمة مجانية شهرياً</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="benefit-vip" name="benefit_vip" class="ml-2">
                                <label for="benefit-vip" class="text-sm">خدمة VIP</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Appearance -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المظهر</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اللون</label>
                            <div class="flex flex-wrap gap-2">
                                <div class="w-8 h-8 bg-amber-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="amber"></div>
                                <div class="w-8 h-8 bg-gray-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="gray"></div>
                                <div class="w-8 h-8 bg-yellow-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="yellow"></div>
                                <div class="w-8 h-8 bg-purple-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="purple"></div>
                                <div class="w-8 h-8 bg-blue-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="blue"></div>
                                <div class="w-8 h-8 bg-green-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="green"></div>
                                <div class="w-8 h-8 bg-red-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="red"></div>
                                <div class="w-8 h-8 bg-pink-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="pink"></div>
                            </div>
                            <input type="hidden" name="tier_color" value="amber">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الأيقونة</label>
                            <div class="grid grid-cols-4 gap-2">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-award">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-medal">
                                    <i class="fas fa-medal"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-crown">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-gem">
                                    <i class="fas fa-gem"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-star">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-trophy">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-certificate">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center cursor-pointer border-2 border-transparent hover:border-gray-400" data-icon="fa-shield-alt">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                            </div>
                            <input type="hidden" name="tier_icon" value="fa-award">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-tier-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-tier-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ الشريحة
            </button>
        </div>
    </div>
</div>

<!-- Adjust Points Modal (hidden by default) -->
<div id="adjust-points-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-mai-dark-brown">تعديل نقاط العميل</h3>
            <button id="close-points-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="adjust-points-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">اختر العميل*</label>
                    <select name="customer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <option value="">اختر العميل...</option>
                        <option value="1">سارة عبدالله القحطاني (5,250 نقطة)</option>
                        <option value="2">نورا محمد العتيبي (3,750 نقطة)</option>
                        <option value="3">فاطمة أحمد السالم (1,850 نقطة)</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع العملية*</label>
                    <div class="flex">
                        <label class="inline-flex items-center ml-4">
                            <input type="radio" name="operation_type" value="add" checked class="ml-1">
                            <span>إضافة</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="operation_type" value="subtract" class="ml-1">
                            <span>خصم</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">عدد النقاط*</label>
                    <input type="number" name="points_amount" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">سبب التعديل*</label>
                    <select name="reason" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <option value="">اختر السبب...</option>
                        <option value="purchase">مشتريات</option>
                        <option value="service">خدمة</option>
                        <option value="redemption">استبدال نقاط</option>
                        <option value="referral">إحالة صديق</option>
                        <option value="birthday">هدية عيد ميلاد</option>
                        <option value="adjustment">تعديل يدوي</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end">
            <button id="cancel-points-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-points-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ التعديل
            </button>
        </div>
    </div>
</div>

<!-- Loyalty System JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // New tier modal
    const newTierBtn = document.getElementById('new-tier-btn');
    const newTierModal = document.getElementById('new-tier-modal');
    const closeTierModal = document.getElementById('close-tier-modal');
    const cancelTierBtn = document.getElementById('cancel-tier-btn');
    
    newTierBtn.addEventListener('click', function() {
        newTierModal.classList.remove('hidden');
    });
    
    function closeTierModal() {
        newTierModal.classList.add('hidden');
    }
    
    closeTierModal.addEventListener('click', closeTierModal);
    cancelTierBtn.addEventListener('click', closeTierModal);
    
    // Close modal when clicking outside
    newTierModal.addEventListener('click', function(event) {
        if (event.target === newTierModal) {
            closeTierModal();
        }
    });
    
    // Adjust points modal
    const adjustPointsBtn = document.getElementById('adjust-points-btn');
    const adjustPointsModal = document.getElementById('adjust-points-modal');
    const closePointsModal = document.getElementById('close-points-modal');
    const cancelPointsBtn = document.getElementById('cancel-points-btn');
    
    adjustPointsBtn.addEventListener('click', function() {
        adjustPointsModal.classList.remove('hidden');
    });
    
    function closePointsModal() {
        adjustPointsModal.classList.add('hidden');
    }
    
    closePointsModal.addEventListener('click', closePointsModal);
    cancelPointsBtn.addEventListener('click', closePointsModal);
    
    // Close modal when clicking outside
    adjustPointsModal.addEventListener('click', function(event) {
        if (event.target === adjustPointsModal) {
            closePointsModal();
        }
    });
    
    // Color picker for tier
    const colorPickers = document.querySelectorAll('[data-color]');
    const tierColorInput = document.querySelector('input[name="tier_color"]');
    
    colorPickers.forEach(picker => {
        picker.addEventListener('click', function() {
            // Remove selected border from all pickers
            colorPickers.forEach(p => p.classList.remove('border-gray-800', 'border-4'));
            // Add selected border to clicked picker
            this.classList.add('border-gray-800', 'border-4');
            // Update hidden input
            tierColorInput.value = this.dataset.color;
        });
    });
    
    // Icon picker for tier
    const iconPickers = document.querySelectorAll('[data-icon]');
    const tierIconInput = document.querySelector('input[name="tier_icon"]');
    
    iconPickers.forEach(picker => {
        picker.addEventListener('click', function() {
            // Remove selected border from all pickers
            iconPickers.forEach(p => p.classList.remove('border-gray-800', 'border-4'));
            // Add selected border to clicked picker
            this.classList.add('border-gray-800', 'border-4');
            // Update hidden input
            tierIconInput.value = this.dataset.icon;
        });
    });
    
    // Save tier form
    const saveTierBtn = document.getElementById('save-tier-btn');
    const newTierForm = document.getElementById('new-tier-form');
    
    saveTierBtn.addEventListener('click', function() {
        if (newTierForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ الشريحة بنجاح');
            closeTierModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newTierForm.querySelector(':invalid');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
    
    // Save points form
    const savePointsBtn = document.getElementById('save-points-btn');
    const adjustPointsForm = document.getElementById('adjust-points-form');
    
    savePointsBtn.addEventListener('click', function() {
        if (adjustPointsForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم تعديل النقاط بنجاح');
            closePointsModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = adjustPointsForm.querySelector(':invalid');
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
