<?php
/**
 * MAI Salon - Responsive Reports System
 * Fully responsive reports system with mobile support
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
$page_title = 'التقارير';
ob_start();
?>

<!-- Reports System Container -->
<div class="reports-container">
    <!-- Header -->
    <div class="bg-white shadow-sm rounded-lg mb-4">
        <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <h1 class="text-xl font-bold text-mai-dark-brown">التقارير</h1>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <button id="date-range-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>الفترة: آخر 30 يوم</span>
                        <i class="fas fa-chevron-down mr-1 text-xs"></i>
                    </button>
                    <div id="date-range-menu" class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 hidden">
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">اليوم</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">آخر 7 أيام</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">آخر 30 يوم</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">آخر 3 أشهر</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">آخر 6 أشهر</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">السنة الحالية</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">فترة مخصصة</a>
                    </div>
                </div>
                
                <button id="export-report-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-file-export ml-1"></i>
                    <span class="hidden sm:inline">تصدير</span>
                </button>
                
                <button id="print-report-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-print ml-1"></i>
                    <span class="hidden sm:inline">طباعة</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Reports Navigation -->
    <div class="bg-white shadow-sm rounded-lg mb-6 overflow-x-auto">
        <div class="flex p-2 border-b border-gray-200">
            <button class="px-4 py-2 rounded-lg bg-mai-brown text-white">المبيعات</button>
            <button class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">الخدمات</button>
            <button class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">المنتجات</button>
            <button class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">العملاء</button>
            <button class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">الموظفين</button>
            <button class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-100">الحملات</button>
        </div>
    </div>
    
    <!-- Sales Overview -->
    <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="font-bold text-mai-dark-brown">نظرة عامة على المبيعات</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Gross Sales -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center">
                        <div class="p-2 bg-white bg-opacity-20 rounded-full">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <p class="text-xs text-white text-opacity-80">إجمالي المبيعات</p>
                            <p class="text-xl font-bold">45,250.00 ر.س</p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center text-xs">
                        <i class="fas fa-arrow-up ml-1 text-green-300"></i>
                        <span class="text-green-300">12% مقارنة بالفترة السابقة</span>
                    </div>
                </div>
                
                <!-- Net Sales -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center">
                        <div class="p-2 bg-white bg-opacity-20 rounded-full">
                            <i class="fas fa-hand-holding-usd text-white text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <p class="text-xs text-white text-opacity-80">صافي المبيعات</p>
                            <p class="text-xl font-bold">39,350.00 ر.س</p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center text-xs">
                        <i class="fas fa-arrow-up ml-1 text-green-300"></i>
                        <span class="text-green-300">8% مقارنة بالفترة السابقة</span>
                    </div>
                </div>
                
                <!-- Invoices -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center">
                        <div class="p-2 bg-white bg-opacity-20 rounded-full">
                            <i class="fas fa-file-invoice text-white text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <p class="text-xs text-white text-opacity-80">عدد الفواتير</p>
                            <p class="text-xl font-bold">285</p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center text-xs">
                        <i class="fas fa-arrow-up ml-1 text-green-300"></i>
                        <span class="text-green-300">5% مقارنة بالفترة السابقة</span>
                    </div>
                </div>
                
                <!-- Average Ticket -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center">
                        <div class="p-2 bg-white bg-opacity-20 rounded-full">
                            <i class="fas fa-calculator text-white text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <p class="text-xs text-white text-opacity-80">متوسط قيمة الفاتورة</p>
                            <p class="text-xl font-bold">158.75 ر.س</p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center text-xs">
                        <i class="fas fa-arrow-up ml-1 text-green-300"></i>
                        <span class="text-green-300">3% مقارنة بالفترة السابقة</span>
                    </div>
                </div>
            </div>
            
            <!-- Sales Chart -->
            <div class="bg-gray-50 rounded-lg p-4 h-80 mb-6">
                <!-- This would be replaced with an actual chart -->
                <div class="text-center h-full flex items-center justify-center">
                    <div>
                        <i class="fas fa-chart-line text-4xl text-gray-400 mb-2"></i>
                        <p class="text-gray-500">رسم بياني للمبيعات</p>
                    </div>
                </div>
            </div>
            
            <!-- Sales by Payment Method -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-md font-medium text-gray-700 mb-3">المبيعات حسب طريقة الدفع</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="space-y-4">
                            <!-- Cash -->
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium">نقدي</span>
                                    <span class="text-sm text-gray-500">18,250.00 ر.س (40.3%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: 40.3%"></div>
                                </div>
                            </div>
                            
                            <!-- Card -->
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium">بطاقة ائتمان</span>
                                    <span class="text-sm text-gray-500">22,750.00 ر.س (50.3%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: 50.3%"></div>
                                </div>
                            </div>
                            
                            <!-- Bank Transfer -->
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium">تحويل بنكي</span>
                                    <span class="text-sm text-gray-500">4,250.00 ر.س (9.4%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-500 h-2 rounded-full" style="width: 9.4%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sales by Category -->
                <div>
                    <h3 class="text-md font-medium text-gray-700 mb-3">المبيعات حسب الفئة</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="space-y-4">
                            <!-- Services -->
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium">الخدمات</span>
                                    <span class="text-sm text-gray-500">35,450.00 ر.س (78.3%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-mai-brown h-2 rounded-full" style="width: 78.3%"></div>
                                </div>
                            </div>
                            
                            <!-- Products -->
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium">المنتجات</span>
                                    <span class="text-sm text-gray-500">9,800.00 ر.س (21.7%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: 21.7%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Services -->
    <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="font-bold text-mai-dark-brown">أفضل الخدمات مبيعاً</h2>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-3 px-4 text-right font-medium text-gray-700">الخدمة</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">عدد المبيعات</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">الإيرادات</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">متوسط السعر</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">النسبة</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Service 1 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">قص شعر نسائي</td>
                        <td class="py-3 px-4">85</td>
                        <td class="py-3 px-4">12,750.00 ر.س</td>
                        <td class="py-3 px-4">150.00 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 ml-2">
                                    <div class="bg-mai-brown h-1.5 rounded-full" style="width: 36%"></div>
                                </div>
                                <span>36%</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Service 2 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">صبغة شعر كاملة</td>
                        <td class="py-3 px-4">42</td>
                        <td class="py-3 px-4">14,700.00 ر.س</td>
                        <td class="py-3 px-4">350.00 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 ml-2">
                                    <div class="bg-mai-brown h-1.5 rounded-full" style="width: 41%"></div>
                                </div>
                                <span>41%</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Service 3 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">تصفيف شعر</td>
                        <td class="py-3 px-4">68</td>
                        <td class="py-3 px-4">6,800.00 ر.س</td>
                        <td class="py-3 px-4">100.00 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 ml-2">
                                    <div class="bg-mai-brown h-1.5 rounded-full" style="width: 19%"></div>
                                </div>
                                <span>19%</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Service 4 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">عناية بالبشرة</td>
                        <td class="py-3 px-4">12</td>
                        <td class="py-3 px-4">2,400.00 ر.س</td>
                        <td class="py-3 px-4">200.00 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 ml-2">
                                    <div class="bg-mai-brown h-1.5 rounded-full" style="width: 7%"></div>
                                </div>
                                <span>7%</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Daily Sales -->
    <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="font-bold text-mai-dark-brown">المبيعات اليومية</h2>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-3 px-4 text-right font-medium text-gray-700">التاريخ</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">عدد الفواتير</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">إجمالي المبيعات</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">متوسط الفاتورة</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">المبيعات</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Day 1 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">2023-09-15</td>
                        <td class="py-3 px-4">12</td>
                        <td class="py-3 px-4">1,850.00 ر.س</td>
                        <td class="py-3 px-4">154.17 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: 65%"></div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Day 2 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">2023-09-14</td>
                        <td class="py-3 px-4">9</td>
                        <td class="py-3 px-4">1,450.00 ر.س</td>
                        <td class="py-3 px-4">161.11 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: 51%"></div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Day 3 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">2023-09-13</td>
                        <td class="py-3 px-4">15</td>
                        <td class="py-3 px-4">2,250.00 ر.س</td>
                        <td class="py-3 px-4">150.00 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: 79%"></div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Day 4 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">2023-09-12</td>
                        <td class="py-3 px-4">11</td>
                        <td class="py-3 px-4">1,750.00 ر.س</td>
                        <td class="py-3 px-4">159.09 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: 62%"></div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Day 5 -->
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">2023-09-11</td>
                        <td class="py-3 px-4">8</td>
                        <td class="py-3 px-4">1,250.00 ر.س</td>
                        <td class="py-3 px-4">156.25 ر.س</td>
                        <td class="py-3 px-4">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width: 44%"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reports JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range dropdown
    const dateRangeBtn = document.getElementById('date-range-btn');
    const dateRangeMenu = document.getElementById('date-range-menu');
    
    dateRangeBtn.addEventListener('click', function() {
        dateRangeMenu.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!dateRangeBtn.contains(event.target) && !dateRangeMenu.contains(event.target)) {
            dateRangeMenu.classList.add('hidden');
        }
    });
    
    // Date range options
    const dateRangeOptions = dateRangeMenu.querySelectorAll('a');
    dateRangeOptions.forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const selectedText = this.textContent;
            dateRangeBtn.querySelector('span').textContent = 'الفترة: ' + selectedText;
            dateRangeMenu.classList.add('hidden');
            
            // This would typically trigger an AJAX request to refresh the report data
            // For demo purposes, we'll just show an alert
            alert('تم تحديث التقرير للفترة: ' + selectedText);
        });
    });
    
    // Export report
    const exportReportBtn = document.getElementById('export-report-btn');
    exportReportBtn.addEventListener('click', function() {
        // This would typically trigger an export function
        // For demo purposes, we'll just show an alert
        alert('جاري تصدير التقرير...');
    });
    
    // Print report
    const printReportBtn = document.getElementById('print-report-btn');
    printReportBtn.addEventListener('click', function() {
        // This would typically trigger a print function
        // For demo purposes, we'll just show an alert
        window.print();
    });
    
    // Report tabs
    const reportTabs = document.querySelectorAll('.reports-container .flex.p-2.border-b button');
    reportTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            reportTabs.forEach(t => {
                t.classList.remove('bg-mai-brown', 'text-white');
                t.classList.add('text-gray-700', 'hover:bg-gray-100');
            });
            
            // Add active class to clicked tab
            this.classList.remove('text-gray-700', 'hover:bg-gray-100');
            this.classList.add('bg-mai-brown', 'text-white');
            
            // This would typically trigger an AJAX request to load the selected report
            // For demo purposes, we'll just show an alert
            alert('تم تحديد تقرير: ' + this.textContent);
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
