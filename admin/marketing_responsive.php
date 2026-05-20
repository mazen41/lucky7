<?php
/**
 * MAI Salon - Responsive Marketing Campaigns System
 * Fully responsive marketing system with mobile support
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
$page_title = 'الحملات التسويقية';
ob_start();
?>

<!-- Marketing System Container -->
<div class="marketing-container">
    <!-- Header with Stats -->
    <?php include 'includes/marketing_header.php'; ?>
    
    <!-- Marketing Campaigns -->
    <?php include 'includes/marketing_campaigns.php'; ?>
    
    <!-- Marketing Analytics -->
    <?php include 'includes/marketing_analytics.php'; ?>
</div>

<!-- New Campaign Modal (hidden by default) -->
<div id="new-campaign-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إنشاء حملة جديدة</h3>
            <button id="close-campaign-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-campaign-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الحملة*</label>
                            <input type="text" name="campaign_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">لون الحملة</label>
                            <div class="flex flex-wrap gap-2">
                                <div class="w-8 h-8 bg-green-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="green"></div>
                                <div class="w-8 h-8 bg-blue-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="blue"></div>
                                <div class="w-8 h-8 bg-purple-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="purple"></div>
                                <div class="w-8 h-8 bg-yellow-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="yellow"></div>
                                <div class="w-8 h-8 bg-red-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="red"></div>
                                <div class="w-8 h-8 bg-pink-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="pink"></div>
                                <div class="w-8 h-8 bg-indigo-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="indigo"></div>
                                <div class="w-8 h-8 bg-gray-500 rounded-full cursor-pointer border-2 border-transparent hover:border-gray-400" data-color="gray"></div>
                            </div>
                            <input type="hidden" name="campaign_color" value="green">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ البدء*</label>
                            <input type="date" name="start_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الانتهاء</label>
                            <input type="date" name="end_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <p class="text-xs text-gray-500 mt-1">اتركه فارغاً للحملات المستمرة</p>
                        </div>
                    </div>
                </div>
                
                <!-- Target Audience -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">الجمهور المستهدف</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اختيار الشريحة*</label>
                            <select name="segment" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="">اختر الشريحة...</option>
                                <option value="all">جميع العملاء</option>
                                <option value="vip">عملاء VIP</option>
                                <option value="new">عملاء جدد</option>
                                <option value="regular">عملاء منتظمون</option>
                                <option value="inactive">عملاء غير نشطين</option>
                                <option value="sensitive">بشرة حساسة</option>
                                <option value="high_spenders">كثيرو الإنفاق</option>
                                <option value="birthday">عيد ميلاد هذا الشهر</option>
                                <option value="local">عملاء محليون</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">العدد المتوقع</label>
                            <input type="text" name="estimated_recipients" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" readonly>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">فلاتر إضافية</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">المدينة</label>
                                <select name="city_filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    <option value="">الكل</option>
                                    <option value="riyadh">الرياض</option>
                                    <option value="jeddah">جدة</option>
                                    <option value="dammam">الدمام</option>
                                    <option value="mecca">مكة المكرمة</option>
                                    <option value="medina">المدينة المنورة</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">الجنس</label>
                                <select name="gender_filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    <option value="">الكل</option>
                                    <option value="female">أنثى</option>
                                    <option value="male">ذكر</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">آخر زيارة</label>
                                <select name="last_visit_filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    <option value="">الكل</option>
                                    <option value="30">آخر 30 يوم</option>
                                    <option value="90">آخر 90 يوم</option>
                                    <option value="180">آخر 180 يوم</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Campaign Content -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">محتوى الحملة</h4>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">قنوات الاتصال*</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="channel-sms" name="channel_sms" class="ml-2">
                                    <label for="channel-sms" class="text-sm">رسائل نصية (SMS)</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="channel-email" name="channel_email" class="ml-2">
                                    <label for="channel-email" class="text-sm">بريد إلكتروني</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="channel-whatsapp" name="channel_whatsapp" class="ml-2">
                                    <label for="channel-whatsapp" class="text-sm">واتساب</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SMS Content -->
                        <div id="sms-content" class="border border-gray-200 rounded-lg p-4">
                            <h5 class="font-medium text-gray-700 mb-2">محتوى الرسالة النصية</h5>
                            <div>
                                <textarea name="sms_content" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                                <p class="text-xs text-gray-500 mt-1">الحد الأقصى: 160 حرف</p>
                            </div>
                        </div>
                        
                        <!-- Email Content -->
                        <div id="email-content" class="border border-gray-200 rounded-lg p-4">
                            <h5 class="font-medium text-gray-700 mb-2">محتوى البريد الإلكتروني</h5>
                            <div class="mb-3">
                                <label class="block text-sm text-gray-600 mb-1">عنوان البريد</label>
                                <input type="text" name="email_subject" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">محتوى البريد</label>
                                <textarea name="email_content" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                            </div>
                        </div>
                        
                        <!-- WhatsApp Content -->
                        <div id="whatsapp-content" class="border border-gray-200 rounded-lg p-4">
                            <h5 class="font-medium text-gray-700 mb-2">محتوى رسالة الواتساب</h5>
                            <div>
                                <textarea name="whatsapp_content" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Campaign Settings -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">إعدادات الحملة</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">حالة الحملة</label>
                            <select name="campaign_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="active">نشطة</option>
                                <option value="scheduled">مجدولة</option>
                                <option value="draft">مسودة</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">وقت الإرسال</label>
                            <select name="send_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                <option value="now">الآن</option>
                                <option value="morning">صباحاً (9:00)</option>
                                <option value="afternoon">ظهراً (12:00)</option>
                                <option value="evening">مساءً (18:00)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="track-campaign" name="track_campaign" class="ml-2" checked>
                            <label for="track-campaign" class="text-sm">تتبع أداء الحملة</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-campaign-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-draft-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg ml-2">
                حفظ كمسودة
            </button>
            <button id="save-campaign-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                إنشاء الحملة
            </button>
        </div>
    </div>
</div>

<!-- Marketing System JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // New campaign modal
    const newCampaignBtn = document.getElementById('new-campaign-btn');
    const newCampaignModal = document.getElementById('new-campaign-modal');
    const closeCampaignModal = document.getElementById('close-campaign-modal');
    const cancelCampaignBtn = document.getElementById('cancel-campaign-btn');
    
    newCampaignBtn.addEventListener('click', function() {
        newCampaignModal.classList.remove('hidden');
    });
    
    function closeCampaignModal() {
        newCampaignModal.classList.add('hidden');
    }
    
    closeCampaignModal.addEventListener('click', closeCampaignModal);
    cancelCampaignBtn.addEventListener('click', closeCampaignModal);
    
    // Close modal when clicking outside
    newCampaignModal.addEventListener('click', function(event) {
        if (event.target === newCampaignModal) {
            closeCampaignModal();
        }
    });
    
    // Color picker for campaign
    const colorPickers = document.querySelectorAll('[data-color]');
    const campaignColorInput = document.querySelector('input[name="campaign_color"]');
    
    colorPickers.forEach(picker => {
        picker.addEventListener('click', function() {
            // Remove selected border from all pickers
            colorPickers.forEach(p => p.classList.remove('border-gray-800', 'border-4'));
            // Add selected border to clicked picker
            this.classList.add('border-gray-800', 'border-4');
            // Update hidden input
            campaignColorInput.value = this.dataset.color;
        });
    });
    
    // Channel selection toggle
    const channelSms = document.getElementById('channel-sms');
    const channelEmail = document.getElementById('channel-email');
    const channelWhatsapp = document.getElementById('channel-whatsapp');
    const smsContent = document.getElementById('sms-content');
    const emailContent = document.getElementById('email-content');
    const whatsappContent = document.getElementById('whatsapp-content');
    
    // Initially hide all content sections
    smsContent.style.display = 'none';
    emailContent.style.display = 'none';
    whatsappContent.style.display = 'none';
    
    channelSms.addEventListener('change', function() {
        smsContent.style.display = this.checked ? 'block' : 'none';
    });
    
    channelEmail.addEventListener('change', function() {
        emailContent.style.display = this.checked ? 'block' : 'none';
    });
    
    channelWhatsapp.addEventListener('change', function() {
        whatsappContent.style.display = this.checked ? 'block' : 'none';
    });
    
    // Segment selection to update estimated recipients
    const segmentSelect = document.querySelector('select[name="segment"]');
    const estimatedRecipientsInput = document.querySelector('input[name="estimated_recipients"]');
    
    segmentSelect.addEventListener('change', function() {
        // This would typically be an AJAX call to get the actual count
        // For demo purposes, we'll use some placeholder values
        const segmentCounts = {
            'all': '22',
            'vip': '3',
            'new': '9',
            'regular': '6',
            'inactive': '4',
            'sensitive': '2',
            'high_spenders': '5',
            'birthday': '1',
            'local': '12'
        };
        
        estimatedRecipientsInput.value = segmentCounts[this.value] || '0';
    });
    
    // Save campaign form
    const saveCampaignBtn = document.getElementById('save-campaign-btn');
    const saveDraftBtn = document.getElementById('save-draft-btn');
    const newCampaignForm = document.getElementById('new-campaign-form');
    
    saveCampaignBtn.addEventListener('click', function() {
        if (newCampaignForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم إنشاء الحملة بنجاح');
            closeCampaignModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newCampaignForm.querySelector(':invalid');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
    
    saveDraftBtn.addEventListener('click', function() {
        // This would typically submit the form via AJAX with draft status
        // For demo purposes, we'll just show an alert
        alert('تم حفظ الحملة كمسودة');
        closeCampaignModal();
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
