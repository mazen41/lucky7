<?php
/**
 * MAI Salon - Services Management Header Component
 * Responsive header for the services management system
 */
?>
<!-- Services Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">إدارة الخدمات</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-service-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-plus ml-1"></i>
                <span>خدمة جديدة</span>
            </button>
            
            <button id="categories-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-folder-open ml-1"></i>
                <span class="hidden sm:inline">التصنيفات</span>
            </button>
            
            <button id="pricing-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-tag ml-1"></i>
                <span class="hidden sm:inline">الأسعار</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Services -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-cut text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي الخدمات</p>
                <p class="text-xl font-bold text-gray-900" id="total-services">18</p>
            </div>
        </div>
    </div>
    
    <!-- Categories -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-folder text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">التصنيفات</p>
                <p class="text-xl font-bold text-gray-900" id="total-categories">6</p>
            </div>
        </div>
    </div>
    
    <!-- Most Booked -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-chart-line text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">الأكثر حجزاً</p>
                <p class="text-xl font-bold text-gray-900" id="most-booked">قص شعر</p>
            </div>
        </div>
    </div>
    
    <!-- Average Price -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-money-bill-wave text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">متوسط السعر</p>
                <p class="text-xl font-bold text-gray-900" id="average-price">175.00 ر.س</p>
            </div>
        </div>
    </div>
</div>
