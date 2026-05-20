<?php
/**
 * MAI Salon - Client Management Header Component
 * Responsive header for the CRM system
 */
?>
<!-- Clients Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">إدارة العملاء (CRM)</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-client-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-user-plus ml-1"></i>
                <span>عميل جديد</span>
            </button>
            
            <button id="import-clients-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-import ml-1"></i>
                <span class="hidden sm:inline">استيراد</span>
            </button>
            
            <button id="export-clients-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-export ml-1"></i>
                <span class="hidden sm:inline">تصدير</span>
            </button>
            
            <div class="relative">
                <button id="segments-dropdown-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-tags ml-1"></i>
                    <span class="hidden sm:inline">شرائح العملاء</span>
                    <i class="fas fa-chevron-down mr-1 text-xs"></i>
                </button>
                <div id="segments-dropdown-menu" class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 hidden">
                    <a href="customer_segments.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">إدارة الشرائح</a>
                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">تشغيل التصنيف التلقائي</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Customers -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-users text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي العملاء</p>
                <p class="text-xl font-bold text-gray-900" id="total-customers">0</p>
            </div>
        </div>
    </div>
    
    <!-- Active Customers -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-user-check text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">العملاء النشطين</p>
                <p class="text-xl font-bold text-gray-900" id="active-customers">0</p>
            </div>
        </div>
    </div>
    
    <!-- New Today -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-mai-brown">
        <div class="flex items-center">
            <div class="p-2 bg-mai-cream rounded-full">
                <i class="fas fa-user-plus text-mai-brown text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">عملاء جدد اليوم</p>
                <p class="text-xl font-bold text-gray-900" id="new-today">0</p>
            </div>
        </div>
    </div>
    
    <!-- Active This Month -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-calendar-check text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">نشطين هذا الشهر</p>
                <p class="text-xl font-bold text-gray-900" id="active-month">0</p>
            </div>
        </div>
    </div>
</div>
