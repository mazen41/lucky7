<?php
/**
 * MAI Salon - Responsive Client Management System
 * Fully responsive CRM system with mobile support
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

// Get client statistics (placeholder - replace with actual logic)
$stats = [
    'total_customers' => 22,
    'active_customers' => 16,
    'new_today' => 2,
    'active_month' => 12
];

// Set page title and start output buffering for the responsive layout
$page_title = 'إدارة العملاء (CRM)';
ob_start();
?>

<!-- Client Management Container -->
<div class="clients-container">
    <!-- Header with Stats -->
    <?php include 'includes/clients_header.php'; ?>
    
    <!-- Search and Filters -->
    <?php include 'includes/clients_search.php'; ?>
    
    <!-- Clients Table -->
    <?php include 'includes/clients_table.php'; ?>
</div>

<!-- New Client Modal (hidden by default) -->
<div id="new-client-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة عميل جديد</h3>
            <button id="close-client-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-client-form">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف*</label>
                            <input type="tel" name="phone" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                            <input type="email" name="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
                            <input type="date" name="birthdate" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس</label>
                            <select name="gender" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر...</option>
                                <option value="female">أنثى</option>
                                <option value="male">ذكر</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">معلومات الاتصال</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المدينة</label>
                            <select name="city" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر...</option>
                                <option value="riyadh">الرياض</option>
                                <option value="jeddah">جدة</option>
                                <option value="dammam">الدمام</option>
                                <option value="mecca">مكة المكرمة</option>
                                <option value="medina">المدينة المنورة</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                            <input type="text" name="address" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم هاتف إضافي</label>
                            <input type="tel" name="alt_phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الاتصال المفضلة</label>
                            <select name="contact_preference" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر...</option>
                                <option value="phone">الهاتف</option>
                                <option value="sms">رسائل نصية</option>
                                <option value="email">البريد الإلكتروني</option>
                                <option value="whatsapp">واتساب</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Beauty Profile -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">الملف الجمالي</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع البشرة</label>
                            <select name="skin_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر...</option>
                                <option value="normal">عادية</option>
                                <option value="dry">جافة</option>
                                <option value="oily">دهنية</option>
                                <option value="combination">مختلطة</option>
                                <option value="sensitive">حساسة</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع الشعر</label>
                            <select name="hair_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر...</option>
                                <option value="straight">مستقيم</option>
                                <option value="wavy">متموج</option>
                                <option value="curly">مجعد</option>
                                <option value="coily">شديد التجعيد</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الحساسية</label>
                            <input type="text" name="allergies" placeholder="مثال: اللاتكس، العطور..." class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تفضيلات الخدمة</label>
                            <input type="text" name="service_preferences" placeholder="مثال: صبغات طبيعية فقط..." class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">معلومات إضافية</h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                        <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                    </div>
                </div>
                
                <!-- Marketing Preferences -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">تفضيلات التسويق</h4>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="marketing-sms" name="marketing_sms" class="ml-2">
                            <label for="marketing-sms" class="text-sm">الموافقة على استلام رسائل نصية تسويقية</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="marketing-email" name="marketing_email" class="ml-2">
                            <label for="marketing-email" class="text-sm">الموافقة على استلام بريد إلكتروني تسويقي</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="marketing-whatsapp" name="marketing_whatsapp" class="ml-2">
                            <label for="marketing-whatsapp" class="text-sm">الموافقة على استلام رسائل واتساب تسويقية</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-client-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-client-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ العميل
            </button>
        </div>
    </div>
</div>

<!-- Client Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize statistics
    document.getElementById('total-customers').textContent = '<?php echo $stats['total_customers']; ?>';
    document.getElementById('active-customers').textContent = '<?php echo $stats['active_customers']; ?>';
    document.getElementById('new-today').textContent = '<?php echo $stats['new_today']; ?>';
    document.getElementById('active-month').textContent = '<?php echo $stats['active_month']; ?>';
    document.getElementById('total-results').textContent = '<?php echo $stats['total_customers']; ?>';
    
    // Segments dropdown toggle
    const segmentsDropdownBtn = document.getElementById('segments-dropdown-btn');
    const segmentsDropdownMenu = document.getElementById('segments-dropdown-menu');
    
    segmentsDropdownBtn.addEventListener('click', function() {
        segmentsDropdownMenu.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!segmentsDropdownBtn.contains(event.target) && !segmentsDropdownMenu.contains(event.target)) {
            segmentsDropdownMenu.classList.add('hidden');
        }
    });
    
    // New client modal
    const newClientBtn = document.getElementById('new-client-btn');
    const newClientModal = document.getElementById('new-client-modal');
    const closeClientModal = document.getElementById('close-client-modal');
    const cancelClientBtn = document.getElementById('cancel-client-btn');
    
    newClientBtn.addEventListener('click', function() {
        newClientModal.classList.remove('hidden');
    });
    
    function closeModal() {
        newClientModal.classList.add('hidden');
    }
    
    closeClientModal.addEventListener('click', closeModal);
    cancelClientBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    newClientModal.addEventListener('click', function(event) {
        if (event.target === newClientModal) {
            closeModal();
        }
    });
    
    // Search and filters
    const clientSearch = document.getElementById('client-search');
    const segmentFilter = document.getElementById('segment-filter');
    const statusFilter = document.getElementById('status-filter');
    const cityFilter = document.getElementById('city-filter');
    const resetFiltersBtn = document.getElementById('reset-filters-btn');
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    
    resetFiltersBtn.addEventListener('click', function() {
        clientSearch.value = '';
        segmentFilter.selectedIndex = 0;
        statusFilter.selectedIndex = 0;
        cityFilter.selectedIndex = 0;
    });
    
    applyFiltersBtn.addEventListener('click', function() {
        // This would typically trigger an AJAX request to filter clients
        // For demo purposes, we'll just show an alert
        alert('تم تطبيق الفلاتر');
    });
    
    // Save client form
    const saveClientBtn = document.getElementById('save-client-btn');
    const newClientForm = document.getElementById('new-client-form');
    
    saveClientBtn.addEventListener('click', function() {
        if (newClientForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ العميل بنجاح');
            closeModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newClientForm.querySelector(':invalid');
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
