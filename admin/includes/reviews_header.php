<?php
/**
 * MAI Salon - Reviews Management Header Component
 * Responsive header for the reviews management system
 */
?>
<!-- Reviews Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">إدارة التقييمات</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-review-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-plus ml-1"></i>
                <span>تقييم جديد</span>
            </button>
            
            <button id="export-reviews-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-export ml-1"></i>
                <span class="hidden sm:inline">تصدير</span>
            </button>
            
            <button id="settings-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-cog ml-1"></i>
                <span class="hidden sm:inline">الإعدادات</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Reviews -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-star text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي التقييمات</p>
                <p class="text-xl font-bold text-gray-900" id="total-reviews">48</p>
            </div>
        </div>
    </div>
    
    <!-- Average Rating -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-star-half-alt text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">متوسط التقييم</p>
                <p class="text-xl font-bold text-gray-900" id="average-rating">4.7</p>
            </div>
        </div>
    </div>
    
    <!-- Pending Reviews -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-clock text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">تقييمات معلقة</p>
                <p class="text-xl font-bold text-gray-900" id="pending-reviews">5</p>
            </div>
        </div>
    </div>
    
    <!-- Published Reviews -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-check-circle text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">تقييمات منشورة</p>
                <p class="text-xl font-bold text-gray-900" id="published-reviews">43</p>
            </div>
        </div>
    </div>
</div>
