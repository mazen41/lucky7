<?php
/**
 * MAI Salon - Responsive Settings System
 * Fully responsive settings system with mobile support
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
$page_title = 'الإعدادات';
ob_start();
?>

<!-- Settings System Container -->
<div class="settings-container">
    <!-- Header -->
    <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <h1 class="text-xl font-bold text-mai-dark-brown">إعدادات النظام</h1>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <button id="save-settings-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-save ml-1"></i>
                    <span>حفظ الإعدادات</span>
                </button>
                
                <button id="reset-settings-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-undo ml-1"></i>
                    <span class="hidden sm:inline">إعادة تعيين</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Settings Navigation -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Settings Categories -->
        <div class="lg:col-span-1">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden sticky top-4">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">الفئات</h2>
                </div>
                <div class="p-2">
                    <nav class="space-y-1">
                        <a href="#general" class="flex items-center px-3 py-2 rounded-lg bg-mai-cream text-mai-brown">
                            <i class="fas fa-cog ml-2 w-5 text-center"></i>
                            <span>إعدادات عامة</span>
                        </a>
                        <a href="#business" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-store ml-2 w-5 text-center"></i>
                            <span>معلومات الصالون</span>
                        </a>
                        <a href="#tax" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-percentage ml-2 w-5 text-center"></i>
                            <span>إعدادات الضريبة</span>
                        </a>
                        <a href="#booking" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-calendar-alt ml-2 w-5 text-center"></i>
                            <span>إعدادات الحجز</span>
                        </a>
                        <a href="#notifications" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-bell ml-2 w-5 text-center"></i>
                            <span>الإشعارات</span>
                        </a>
                        <a href="#loyalty" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-award ml-2 w-5 text-center"></i>
                            <span>نظام الولاء</span>
                        </a>
                        <a href="#appearance" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-paint-brush ml-2 w-5 text-center"></i>
                            <span>المظهر</span>
                        </a>
                        <a href="#security" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-shield-alt ml-2 w-5 text-center"></i>
                            <span>الأمان</span>
                        </a>
                        <a href="#backup" class="flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-database ml-2 w-5 text-center"></i>
                            <span>النسخ الاحتياطي</span>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Settings Forms -->
        <div class="lg:col-span-3">
            <!-- General Settings -->
            <div id="general" class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">إعدادات عامة</h2>
                </div>
                <div class="p-4">
                    <form id="general-settings-form">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">اسم النظام</label>
                                <input type="text" name="system_name" value="MAI Salon" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">اللغة الافتراضية</label>
                                <select name="default_language" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    <option value="ar" selected>العربية</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">المنطقة الزمنية</label>
                                <select name="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    <option value="Asia/Riyadh" selected>الرياض (GMT+3)</option>
                                    <option value="Asia/Jeddah">جدة (GMT+3)</option>
                                    <option value="Asia/Dubai">دبي (GMT+4)</option>
                                    <option value="Europe/London">لندن (GMT+0)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">تنسيق التاريخ</label>
                                <select name="date_format" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    <option value="d/m/Y" selected>DD/MM/YYYY (31/12/2023)</option>
                                    <option value="Y-m-d">YYYY-MM-DD (2023-12-31)</option>
                                    <option value="m/d/Y">MM/DD/YYYY (12/31/2023)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">رمز العملة</label>
                                    <input type="text" name="currency_symbol" value="ر.س" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">موضع الرمز</label>
                                    <select name="currency_position" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                        <option value="after" selected>بعد المبلغ (100 ر.س)</option>
                                        <option value="before">قبل المبلغ (ر.س 100)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">فاصل العشري</label>
                                    <select name="decimal_separator" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                        <option value="." selected>نقطة (100.50)</option>
                                        <option value=",">فاصلة (100,50)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">الإعدادات المتقدمة</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable-debug" name="enable_debug" class="ml-2">
                                    <label for="enable-debug" class="text-sm">تمكين وضع التصحيح</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable-cache" name="enable_cache" checked class="ml-2">
                                    <label for="enable-cache" class="text-sm">تمكين التخزين المؤقت</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable-logs" name="enable_logs" checked class="ml-2">
                                    <label for="enable-logs" class="text-sm">تمكين سجلات النظام</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Business Information -->
            <div id="business" class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">معلومات الصالون</h2>
                </div>
                <div class="p-4">
                    <form id="business-settings-form">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">اسم الصالون</label>
                                <input type="text" name="salon_name" value="MAI Salon" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم السجل التجاري</label>
                                <input type="text" name="business_registration" value="1234567890" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input type="tel" name="phone" value="0512345678" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input type="email" name="email" value="info@maisalon.com" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-3">
                                    <label class="block text-xs text-gray-600 mb-1">الشارع</label>
                                    <input type="text" name="street" value="شارع الملك فهد" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">المدينة</label>
                                    <input type="text" name="city" value="الرياض" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">المنطقة</label>
                                    <input type="text" name="region" value="الرياض" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">الرمز البريدي</label>
                                    <input type="text" name="postal_code" value="12345" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">شعار الصالون</label>
                            <div class="flex items-center">
                                <div class="w-24 h-24 bg-gray-200 rounded-lg overflow-hidden flex items-center justify-center">
                                    <img src="../assets/images/placeholder.txt" alt="شعار الصالون" class="w-full h-full object-cover">
                                </div>
                                <div class="mr-4">
                                    <button type="button" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors mb-2">
                                        تغيير الشعار
                                    </button>
                                    <p class="text-xs text-gray-500">الحد الأقصى للحجم: 2 ميجابايت. الأبعاد المثالية: 200×200 بكسل.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">ساعات العمل</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">الأحد</label>
                                    <div class="flex items-center">
                                        <input type="time" name="sunday_open" value="09:00" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                        <span class="mx-2">إلى</span>
                                        <input type="time" name="sunday_close" value="21:00" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">الإثنين</label>
                                    <div class="flex items-center">
                                        <input type="time" name="monday_open" value="09:00" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                        <span class="mx-2">إلى</span>
                                        <input type="time" name="monday_close" value="21:00" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                    </div>
                                </div>
                                <!-- Additional days would be added here -->
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tax Settings -->
            <div id="tax" class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">إعدادات الضريبة</h2>
                </div>
                <div class="p-4">
                    <form id="tax-settings-form">
                        <div class="mb-6">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" id="enable-tax" name="enable_tax" checked class="ml-2">
                                <label for="enable-tax" class="text-sm font-medium">تفعيل الضريبة</label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">نسبة الضريبة</label>
                                    <div class="relative">
                                        <input type="number" name="tax_rate" value="15" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع الضريبة</label>
                                    <select name="tax_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                                        <option value="inclusive" selected>شاملة للضريبة</option>
                                        <option value="exclusive">غير شاملة للضريبة</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">الرقم الضريبي</label>
                            <input type="text" name="tax_number" value="123456789012345" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">إعدادات ZATCA</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable-zatca" name="enable_zatca" checked class="ml-2">
                                    <label for="enable-zatca" class="text-sm">تفعيل متطلبات ZATCA</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable-qr" name="enable_qr" checked class="ml-2">
                                    <label for="enable-qr" class="text-sm">تفعيل رمز QR في الفواتير</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Additional settings sections would be added here -->
        </div>
    </div>
</div>

<!-- Settings JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navigation links
    const navLinks = document.querySelectorAll('.settings-container nav a');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            navLinks.forEach(l => {
                l.classList.remove('bg-mai-cream', 'text-mai-brown');
                l.classList.add('text-gray-700', 'hover:bg-gray-100');
            });
            
            // Add active class to clicked link
            this.classList.remove('text-gray-700', 'hover:bg-gray-100');
            this.classList.add('bg-mai-cream', 'text-mai-brown');
            
            // Scroll to the target section
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 20,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Save settings
    const saveSettingsBtn = document.getElementById('save-settings-btn');
    saveSettingsBtn.addEventListener('click', function() {
        // This would typically submit all forms via AJAX
        // For demo purposes, we'll just show an alert
        alert('تم حفظ الإعدادات بنجاح');
    });
    
    // Reset settings
    const resetSettingsBtn = document.getElementById('reset-settings-btn');
    resetSettingsBtn.addEventListener('click', function() {
        // This would typically reset all forms to their default values
        // For demo purposes, we'll just show a confirmation dialog
        if (confirm('هل أنت متأكد من إعادة تعيين جميع الإعدادات؟')) {
            alert('تم إعادة تعيين الإعدادات');
        }
    });
    
    // Tax settings toggle
    const enableTaxCheckbox = document.getElementById('enable-tax');
    const taxFields = document.querySelectorAll('#tax-settings-form input:not(#enable-tax), #tax-settings-form select');
    
    enableTaxCheckbox.addEventListener('change', function() {
        taxFields.forEach(field => {
            field.disabled = !this.checked;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
