<?php
/**
 * MAI Salon - Responsive HR Dashboard
 * Fully responsive HR management system with mobile support
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
$page_title = 'الموارد البشرية';
ob_start();
?>

<!-- HR Dashboard Container -->
<div class="hr-container">
    <!-- Header with Stats -->
    <div class="bg-white shadow-sm rounded-lg mb-4">
        <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <h1 class="text-xl font-bold text-mai-dark-brown">لوحة الموارد البشرية</h1>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <a href="hr_attendance.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-clock ml-1"></i>
                    <span>الحضور والانصراف</span>
                </a>
                
                <a href="hr_requests.php" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-clipboard-list ml-1"></i>
                    <span class="hidden sm:inline">الطلبات والإجازات</span>
                </a>
                
                <a href="hr_performance.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-chart-line ml-1"></i>
                    <span class="hidden sm:inline">تقييم الأداء</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Employees -->
        <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-full">
                    <i class="fas fa-users text-blue-600 text-lg"></i>
                </div>
                <div class="mr-3">
                    <p class="text-xs text-gray-600">إجمالي الموظفين</p>
                    <p class="text-xl font-bold text-gray-900">12</p>
                </div>
            </div>
        </div>
        
        <!-- Today's Attendance -->
        <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-full">
                    <i class="fas fa-user-check text-green-600 text-lg"></i>
                </div>
                <div class="mr-3">
                    <p class="text-xs text-gray-600">حضور اليوم</p>
                    <p class="text-xl font-bold text-gray-900">8</p>
                </div>
            </div>
        </div>
        
        <!-- Pending Requests -->
        <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-full">
                    <i class="fas fa-clock text-yellow-600 text-lg"></i>
                </div>
                <div class="mr-3">
                    <p class="text-xs text-gray-600">طلبات معلقة</p>
                    <p class="text-xl font-bold text-gray-900">3</p>
                </div>
            </div>
        </div>
        
        <!-- Pending Leaves -->
        <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-full">
                    <i class="fas fa-calendar-alt text-purple-600 text-lg"></i>
                </div>
                <div class="mr-3">
                    <p class="text-xs text-gray-600">إجازات معلقة</p>
                    <p class="text-xl font-bold text-gray-900">2</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Performance Overview -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold text-mai-dark-brown">نظرة عامة على الأداء</h2>
            </div>
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-3 px-4 text-right font-medium text-gray-700">الموظف</th>
                                <th class="py-3 px-4 text-right font-medium text-gray-700 hidden sm:table-cell">القسم</th>
                                <th class="py-3 px-4 text-right font-medium text-gray-700">الخدمات</th>
                                <th class="py-3 px-4 text-right font-medium text-gray-700 hidden md:table-cell">الإيرادات</th>
                                <th class="py-3 px-4 text-right font-medium text-gray-700">نسبة الإنجاز</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Employee 1 -->
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                            <span class="text-purple-700 font-bold">س</span>
                                        </div>
                                        <span class="mr-2">سارة محمد</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 hidden sm:table-cell">متخصصة شعر</td>
                                <td class="py-3 px-4">42</td>
                                <td class="py-3 px-4 hidden md:table-cell">8,450 ر.س</td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: 85%"></div>
                                        </div>
                                        <span class="mr-2 text-sm">85%</span>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Employee 2 -->
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-700 font-bold">ن</span>
                                        </div>
                                        <span class="mr-2">نورة العتيبي</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 hidden sm:table-cell">متخصصة بشرة</td>
                                <td class="py-3 px-4">38</td>
                                <td class="py-3 px-4 hidden md:table-cell">7,200 ر.س</td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: 72%"></div>
                                        </div>
                                        <span class="mr-2 text-sm">72%</span>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Employee 3 -->
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <span class="text-green-700 font-bold">م</span>
                                        </div>
                                        <span class="mr-2">منى الشمري</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 hidden sm:table-cell">متخصصة مكياج</td>
                                <td class="py-3 px-4">35</td>
                                <td class="py-3 px-4 hidden md:table-cell">6,850 ر.س</td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-500 h-2 rounded-full" style="width: 68%"></div>
                                        </div>
                                        <span class="mr-2 text-sm">68%</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="hr_performance.php" class="text-mai-brown hover:text-mai-dark-brown font-medium">
                        عرض جميع الموظفين
                        <i class="fas fa-arrow-left mr-2"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="lg:col-span-1 bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold text-mai-dark-brown">سجل الحضور الأخير</h2>
            </div>
            <div class="p-4">
                <div class="space-y-3">
                    <!-- Attendance Record 1 -->
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-700 font-bold">س</span>
                        </div>
                        <div class="mr-3 flex-1">
                            <div class="flex justify-between">
                                <p class="font-medium">سارة محمد</p>
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">حاضر</span>
                            </div>
                            <p class="text-xs text-gray-500">تسجيل دخول: 08:55 صباحاً</p>
                        </div>
                    </div>
                    
                    <!-- Attendance Record 2 -->
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-700 font-bold">ن</span>
                        </div>
                        <div class="mr-3 flex-1">
                            <div class="flex justify-between">
                                <p class="font-medium">نورة العتيبي</p>
                                <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">متأخر</span>
                            </div>
                            <p class="text-xs text-gray-500">تسجيل دخول: 09:15 صباحاً</p>
                        </div>
                    </div>
                    
                    <!-- Attendance Record 3 -->
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-700 font-bold">م</span>
                        </div>
                        <div class="mr-3 flex-1">
                            <div class="flex justify-between">
                                <p class="font-medium">منى الشمري</p>
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">حاضر</span>
                            </div>
                            <p class="text-xs text-gray-500">تسجيل دخول: 08:45 صباحاً</p>
                        </div>
                    </div>
                    
                    <!-- Attendance Record 4 -->
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <span class="text-red-700 font-bold">ل</span>
                        </div>
                        <div class="mr-3 flex-1">
                            <div class="flex justify-between">
                                <p class="font-medium">ليلى الدوسري</p>
                                <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">غائب</span>
                            </div>
                            <p class="text-xs text-gray-500">إجازة مرضية</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="hr_attendance.php" class="text-mai-brown hover:text-mai-dark-brown font-medium">
                        عرض سجل الحضور الكامل
                        <i class="fas fa-arrow-left mr-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Requests -->
    <div class="mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="font-bold text-mai-dark-brown">الطلبات المعلقة</h2>
        </div>
        <div class="p-4">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-3 px-4 text-right font-medium text-gray-700">الموظف</th>
                            <th class="py-3 px-4 text-right font-medium text-gray-700">نوع الطلب</th>
                            <th class="py-3 px-4 text-right font-medium text-gray-700 hidden md:table-cell">تاريخ التقديم</th>
                            <th class="py-3 px-4 text-right font-medium text-gray-700 hidden sm:table-cell">الملاحظات</th>
                            <th class="py-3 px-4 text-right font-medium text-gray-700">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Request 1 -->
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                        <span class="text-purple-700 font-bold">س</span>
                                    </div>
                                    <span class="mr-2">سارة محمد</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">إجازة</span>
                            </td>
                            <td class="py-3 px-4 hidden md:table-cell">2023-09-10</td>
                            <td class="py-3 px-4 hidden sm:table-cell">إجازة لمدة 3 أيام بدءاً من 15 سبتمبر</td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-1 space-x-reverse">
                                    <button class="text-green-600 hover:text-green-800" title="موافقة">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800" title="رفض">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Request 2 -->
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-700 font-bold">ن</span>
                                    </div>
                                    <span class="mr-2">نورة العتيبي</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">تغيير جدول</span>
                            </td>
                            <td class="py-3 px-4 hidden md:table-cell">2023-09-12</td>
                            <td class="py-3 px-4 hidden sm:table-cell">تغيير وقت الدوام ليوم الخميس من 9 صباحاً إلى 12 ظهراً</td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-1 space-x-reverse">
                                    <button class="text-green-600 hover:text-green-800" title="موافقة">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800" title="رفض">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Request 3 -->
                        <tr class="border-b border-gray-100">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <span class="text-green-700 font-bold">م</span>
                                    </div>
                                    <span class="mr-2">منى الشمري</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">سلفة</span>
                            </td>
                            <td class="py-3 px-4 hidden md:table-cell">2023-09-13</td>
                            <td class="py-3 px-4 hidden sm:table-cell">طلب سلفة بقيمة 2000 ريال</td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-1 space-x-reverse">
                                    <button class="text-green-600 hover:text-green-800" title="موافقة">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800" title="رفض">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-center">
                <a href="hr_requests.php" class="text-mai-brown hover:text-mai-dark-brown font-medium">
                    عرض جميع الطلبات
                    <i class="fas fa-arrow-left mr-2"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
