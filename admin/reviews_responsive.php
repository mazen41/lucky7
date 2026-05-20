<?php
/**
 * MAI Salon - Responsive Reviews Management System
 * Fully responsive reviews management system with mobile support
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
$page_title = 'إدارة التقييمات';
ob_start();
?>

<!-- Reviews Management Container -->
<div class="reviews-container">
    <!-- Header with Stats -->
    <?php include 'includes/reviews_header.php'; ?>
    
    <!-- Reviews Table -->
    <?php include 'includes/reviews_table.php'; ?>
</div>

<!-- New Review Modal (hidden by default) -->
<div id="new-review-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة تقييم جديد</h3>
            <button id="close-review-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-review-form">
                <!-- Customer Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">معلومات العميل</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اختر العميل*</label>
                            <select name="customer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر العميل...</option>
                                <option value="1">سارة عبدالله القحطاني</option>
                                <option value="2">نورا محمد العتيبي</option>
                                <option value="3">منى طارق الحربي</option>
                                <option value="4">ريم خالد الشهري</option>
                                <option value="5">فاطمة أحمد السالم</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">أو أدخل اسم العميل*</label>
                            <input type="text" name="customer_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <p class="text-xs text-gray-500 mt-1">إذا كان العميل غير مسجل في النظام</p>
                        </div>
                    </div>
                </div>
                
                <!-- Review Details -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">تفاصيل التقييم</h4>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">التقييم*</label>
                        <div class="flex text-gray-400 text-2xl">
                            <i class="far fa-star cursor-pointer hover:text-yellow-400 ml-1" data-rating="1"></i>
                            <i class="far fa-star cursor-pointer hover:text-yellow-400 ml-1" data-rating="2"></i>
                            <i class="far fa-star cursor-pointer hover:text-yellow-400 ml-1" data-rating="3"></i>
                            <i class="far fa-star cursor-pointer hover:text-yellow-400 ml-1" data-rating="4"></i>
                            <i class="far fa-star cursor-pointer hover:text-yellow-400" data-rating="5"></i>
                        </div>
                        <input type="hidden" name="rating" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">الخدمات*</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="service-haircut" name="services[]" value="haircut" class="ml-2">
                                <label for="service-haircut" class="text-sm">قص شعر</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-coloring" name="services[]" value="coloring" class="ml-2">
                                <label for="service-coloring" class="text-sm">صبغة شعر</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-styling" name="services[]" value="styling" class="ml-2">
                                <label for="service-styling" class="text-sm">تصفيف شعر</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-skincare" name="services[]" value="skincare" class="ml-2">
                                <label for="service-skincare" class="text-sm">عناية بالبشرة</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-makeup" name="services[]" value="makeup" class="ml-2">
                                <label for="service-makeup" class="text-sm">مكياج</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-nails" name="services[]" value="nails" class="ml-2">
                                <label for="service-nails" class="text-sm">أظافر</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-spa" name="services[]" value="spa" class="ml-2">
                                <label for="service-spa" class="text-sm">سبا</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="service-massage" name="services[]" value="massage" class="ml-2">
                                <label for="service-massage" class="text-sm">مساج</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">نص التقييم*</label>
                        <textarea name="review_text" rows="4" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التقييم*</label>
                        <input type="date" name="review_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    </div>
                </div>
                
                <!-- Review Status -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">حالة التقييم</h4>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="radio" id="status-published" name="status" value="published" checked class="ml-2">
                            <label for="status-published" class="text-sm">منشور</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="status-pending" name="status" value="pending" class="ml-2">
                            <label for="status-pending" class="text-sm">معلق</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="status-rejected" name="status" value="rejected" class="ml-2">
                            <label for="status-rejected" class="text-sm">مرفوض</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-review-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-review-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ التقييم
            </button>
        </div>
    </div>
</div>

<!-- Reviews Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add cache-busting query parameter to prevent browser caching issues
    const cacheBuster = '?v=' + new Date().getTime();
    
    // New review modal
    const newReviewBtn = document.getElementById('new-review-btn');
    const newReviewModal = document.getElementById('new-review-modal');
    const closeReviewModal = document.getElementById('close-review-modal');
    const cancelReviewBtn = document.getElementById('cancel-review-btn');
    
    newReviewBtn.addEventListener('click', function() {
        newReviewModal.classList.remove('hidden');
    });
    
    function closeReviewModal() {
        newReviewModal.classList.add('hidden');
    }
    
    closeReviewModal.addEventListener('click', closeReviewModal);
    cancelReviewBtn.addEventListener('click', closeReviewModal);
    
    // Close modal when clicking outside
    newReviewModal.addEventListener('click', function(event) {
        if (event.target === newReviewModal) {
            closeReviewModal();
        }
    });
    
    // Star rating functionality
    const stars = document.querySelectorAll('[data-rating]');
    const ratingInput = document.querySelector('input[name="rating"]');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingInput.value = rating;
            
            // Update stars appearance
            stars.forEach(s => {
                const starRating = parseInt(s.dataset.rating);
                if (starRating <= rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.classList.add('text-yellow-400');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                    s.classList.remove('text-yellow-400');
                }
            });
        });
        
        // Hover effect
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            
            stars.forEach(s => {
                const starRating = parseInt(s.dataset.rating);
                if (starRating <= rating) {
                    s.classList.add('text-yellow-400');
                }
            });
        });
        
        star.addEventListener('mouseleave', function() {
            // Reset to selected rating
            const selectedRating = parseInt(ratingInput.value) || 0;
            
            stars.forEach(s => {
                const starRating = parseInt(s.dataset.rating);
                if (starRating <= selectedRating) {
                    s.classList.add('text-yellow-400');
                } else {
                    s.classList.remove('text-yellow-400');
                }
            });
        });
    });
    
    // Export and settings buttons
    const exportReviewsBtn = document.getElementById('export-reviews-btn');
    const settingsBtn = document.getElementById('settings-btn');
    
    exportReviewsBtn.addEventListener('click', function() {
        // This would typically trigger an export function
        // For demo purposes, we'll just show an alert
        alert('جاري تصدير التقييمات...');
    });
    
    settingsBtn.addEventListener('click', function() {
        window.location.href = 'review_settings.php' + cacheBuster;
    });
    
    // Save review form
    const saveReviewBtn = document.getElementById('save-review-btn');
    const newReviewForm = document.getElementById('new-review-form');
    
    saveReviewBtn.addEventListener('click', function() {
        // Check if either customer_id or customer_name is filled
        const customerId = document.querySelector('select[name="customer_id"]').value;
        const customerName = document.querySelector('input[name="customer_name"]').value;
        
        if (!customerId && !customerName) {
            alert('يرجى اختيار عميل أو إدخال اسم العميل');
            return;
        }
        
        // Check if rating is selected
        if (!ratingInput.value) {
            alert('يرجى اختيار تقييم النجوم');
            return;
        }
        
        // Check if at least one service is selected
        const services = document.querySelectorAll('input[name="services[]"]:checked');
        if (services.length === 0) {
            alert('يرجى اختيار خدمة واحدة على الأقل');
            return;
        }
        
        if (newReviewForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ التقييم بنجاح');
            closeReviewModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newReviewForm.querySelector(':invalid');
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
