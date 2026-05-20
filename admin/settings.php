<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

checkAdminAuth();
 $admin = getCurrentAdmin();

// --- PERMISSION CHECK: Page Access ---
// This ensures no one can even open this page without the 'settings.view' permission.
requirePermission('settings.view');

// Layout configuration
 $page_title = 'الإعدادات';
 $page_subtitle = 'إدارة وعرض البيانات';
 $page_actions = '';

ob_start();
?>


<!-- Main Content -->
<div class="container mx-auto px-4 pb-8">
<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md mb-6 p-6 fade-in">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">الإعدادات</h1>
            <p class="text-sm text-gray-500">إدارة إعدادات النظام والحساب</p>
        </div>
    </div>
</div>

    <!-- Settings Cards - Now fully secured with permission checks -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

         <?php if (hasPermission('content.manage')): ?>
             <!-- Content Management -->
            <a href="content.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-file-alt text-indigo-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إدارة المحتوى</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة محتوى صفحات الموقع</p>
            </a>
        <?php endif; ?>

        <?php if (hasPermission('settings.taxes')): ?>
            <!-- Tax Settings -->
            <a href="tax_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-percentage text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إعدادات الضرائب</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة الضرائب والإعدادات المالية</p>
            </a>
        <?php endif; ?>

        <?php if (hasPermission('settings.payments')): ?>
            <!-- POS Settings -->
            <a href="pos_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-cash-register text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إعدادات نقطة البيع</h3>
                </div>
                <p class="text-gray-600 text-sm">إعدادات نظام نقطة البيع</p>
            </a>
        <?php endif; ?>
        
        <?php if (hasPermission('loyalty.manage')): ?>
            <!-- Loyalty Settings -->
            <a href="loyalty_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-star text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">نظام الولاء</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة نظام المكافآت والولاء</p>
            </a>
        <?php endif; ?>

        <?php if (hasPermission('pos.manage_discounts')): ?>
            <!-- Discount Policies -->
            <a href="discount_policies.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-tags text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">سياسات الخصم</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة سياسات الخصم حسب الأدوار</p>
            </a>
        <?php endif; ?>

        <?php if (hasPermission('settings.general')): ?>
             <!-- Working Hours Settings -->
            <a href="working_hours_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-clock text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إعدادات أوقات الدوام</h3>
                </div>
                <p class="text-gray-600 text-sm">تحديد ساعات العمل الرسمية للحجوزات</p>
            </a>
        <?php endif; ?>
        
        <?php if (hasPermission('settings.general')): ?>
             <!-- Holidays Settings -->
            <a href="holidays.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-calendar-times text-orange-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">العطلات</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة العطلات الرسمية والأسبوعية</p>
            </a>
        <?php endif; ?>
    
        <?php if (hasPermission('settings.general')): ?>
             <!-- Booking Settings -->
             <a href="booking_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-calendar-check text-cyan-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إعدادات الحجوزات</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة إعدادات المواعيد والفواصل الزمنية</p>
            </a>
        <?php endif; ?>

        <?php if (hasPermission('users.view')): ?>
             <!-- User Management -->
            <a href="users.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-users-cog text-teal-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إدارة المستخدمين</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة حسابات المستخدمين وصلاحياتهم</p>
            </a>
        <?php endif; ?>

        <?php if (hasPermission('roles.manage')): ?>
            <!-- Roles Management -->
            <a href="roles.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-user-shield text-amber-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إدارة الأدوار</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة الأدوار وتحديد صلاحيات كل دور في النظام</p>
            </a>
        <?php endif; ?>

       
       
        <?php if (hasPermission('reviews.manage')): ?>
             <!-- Reviews Management -->
            <a href="reviews.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-star-half-alt text-pink-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">التقييمات</h3>
                </div>
                <p class="text-gray-600 text-sm">عرض وإدارة تقييمات العملاء</p>
            </a>
        <?php endif; ?>
        
        <?php if (hasPermission('health_forms.manage')): ?>
            <!-- Health Form Settings -->
            <a href="health-form-settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-lime-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-notes-medical text-lime-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إعدادات نموذج الصحة</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة الأسئلة والحقول في نموذج الصحة</p>
            </a>
        <?php endif; ?>
        
        <?php if (hasPermission('hr.settings.locations')): ?>
            <!-- HR Location Settings -->
            <a href="hr_location_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-sky-100 rounded-lg flex items-center justify-center ml-3">
                        <i class="fas fa-map-marker-alt text-sky-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">إعدادات مواقع الموظفين</h3>
                </div>
                <p class="text-gray-600 text-sm">إدارة مواقع وأفرع عمل الموظفين</p>
            </a>
        <?php endif; ?>
        <a href="manage_breaks.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
    <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center ml-3">
            <i class="fas fa-coffee text-gray-600 text-xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-800">إدارة الاستراحات</h3>
    </div>
    <p class="text-gray-600 text-sm">تنظيم وجدولة أوقات الراحة</p>
</a>

   
    <!-- Show Attendance -->
    <a href="show_attendance.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <div class="flex items-center mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center ml-3">
                <i class="fas fa-user-check text-blue-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800">عرض الحضور</h3>
        </div>
        <p class="text-gray-600 text-sm">عرض ومتابعة حضور الموظفين</p>
    </a>

<a href="bonus_settings.php" class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
    <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center ml-3">
            <i class="fas fa-gift text-indigo-600 text-xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-800">إعدادات البونص</h3>
    </div>
    <p class="text-gray-600 text-sm">التحكم في إعدادات البونص والمكافآت</p>
</a>

    </div>
</div>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>