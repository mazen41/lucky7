<?php
/**
 * MAI Salon - Responsive Team Management System
 * Fully responsive team management system with mobile support
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
$page_title = 'إدارة فريق العمل';
ob_start();
?>

<!-- Team Management Container -->
<div class="team-container">
    <!-- Header with Stats -->
    <?php include 'includes/team_header.php'; ?>
    
    <!-- Team Members Table -->
    <?php include 'includes/team_table.php'; ?>
</div>

<!-- New Team Member Modal (hidden by default) -->
<div id="new-member-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة عضو جديد</h3>
            <button id="close-member-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-member-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الأول*</label>
                            <input type="text" name="first_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم العائلة*</label>
                            <input type="text" name="last_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المسمى الوظيفي*</label>
                            <input type="text" name="job_title" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                            <input type="email" name="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                            <input type="tel" name="phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التعيين</label>
                            <input type="date" name="hire_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                    </div>
                </div>
                
                <!-- Specialties and Services -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">التخصصات والخدمات</h4>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">التخصص الرئيسي*</label>
                        <select name="specialty" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <option value="">اختر التخصص...</option>
                            <option value="hair">الشعر</option>
                            <option value="skin">البشرة</option>
                            <option value="makeup">المكياج</option>
                            <option value="nails">الأظافر</option>
                            <option value="spa">السبا</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الخدمات</label>
                        <div class="grid grid-cols-2 gap-2">
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
                        </div>
                    </div>
                </div>
                
                <!-- Profile and Schedule -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">الملف الشخصي والجدول</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">صورة الملف الشخصي</label>
                            <div class="border border-dashed border-gray-300 rounded-lg p-4 text-center">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                <p class="text-sm text-gray-500">اسحب الصورة هنا أو انقر للتصفح</p>
                                <input type="file" name="profile_image" class="hidden" id="profile-image-upload">
                                <button type="button" id="upload-image-btn" class="mt-2 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors text-sm">
                                    اختر صورة
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نبذة مختصرة</label>
                            <textarea name="bio" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center mb-2">
                            <input type="checkbox" id="is-specialist" name="is_specialist" checked class="ml-2">
                            <label for="is-specialist" class="text-sm font-medium">متخصص (يمكن حجز مواعيد معه)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="is-active" name="is_active" checked class="ml-2">
                            <label for="is-active" class="text-sm font-medium">نشط</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-member-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-member-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ العضو
            </button>
        </div>
    </div>
</div>

<!-- Team Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add cache-busting query parameter to prevent browser caching issues
    const cacheBuster = '?v=' + new Date().getTime();
    
    // New team member modal
    const newMemberBtn = document.getElementById('new-member-btn');
    const newMemberModal = document.getElementById('new-member-modal');
    const closeMemberModal = document.getElementById('close-member-modal');
    const cancelMemberBtn = document.getElementById('cancel-member-btn');
    
    newMemberBtn.addEventListener('click', function() {
        newMemberModal.classList.remove('hidden');
    });
    
    function closeMemberModal() {
        newMemberModal.classList.add('hidden');
    }
    
    closeMemberModal.addEventListener('click', closeMemberModal);
    cancelMemberBtn.addEventListener('click', closeMemberModal);
    
    // Close modal when clicking outside
    newMemberModal.addEventListener('click', function(event) {
        if (event.target === newMemberModal) {
            closeMemberModal();
        }
    });
    
    // Schedule and specialties buttons
    const scheduleBtn = document.getElementById('schedule-btn');
    const specialtiesBtn = document.getElementById('specialties-btn');
    
    scheduleBtn.addEventListener('click', function() {
        window.location.href = 'team_schedule.php' + cacheBuster;
    });
    
    specialtiesBtn.addEventListener('click', function() {
        window.location.href = 'team_specialties.php' + cacheBuster;
    });
    
    // Image upload button
    const uploadImageBtn = document.getElementById('upload-image-btn');
    const profileImageUpload = document.getElementById('profile-image-upload');
    
    uploadImageBtn.addEventListener('click', function() {
        profileImageUpload.click();
    });
    
    // Save team member form
    const saveMemberBtn = document.getElementById('save-member-btn');
    const newMemberForm = document.getElementById('new-member-form');
    
    saveMemberBtn.addEventListener('click', function() {
        if (newMemberForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ العضو بنجاح');
            closeMemberModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newMemberForm.querySelector(':invalid');
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
