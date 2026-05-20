<?php
/**
 * MAI Salon - Marketing Campaigns Header Component
 * Responsive header for the marketing system
 */
?>
<!-- Marketing Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">الحملات التسويقية</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-campaign-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-plus ml-1"></i>
                <span>حملة جديدة</span>
            </button>
            
            <button id="templates-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-alt ml-1"></i>
                <span class="hidden sm:inline">القوالب</span>
            </button>
            
            <button id="analytics-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-chart-line ml-1"></i>
                <span class="hidden sm:inline">التحليلات</span>
            </button>
            
            <button id="settings-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-cog ml-1"></i>
                <span class="hidden sm:inline">الإعدادات</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Active Campaigns -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-bullhorn text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">حملات نشطة</p>
                <p class="text-xl font-bold text-gray-900" id="active-campaigns">3</p>
            </div>
        </div>
    </div>
    
    <!-- Total Sent -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-paper-plane text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي الرسائل</p>
                <p class="text-xl font-bold text-gray-900" id="total-sent">1,250</p>
            </div>
        </div>
    </div>
    
    <!-- Open Rate -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-envelope-open text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">معدل الفتح</p>
                <p class="text-xl font-bold text-gray-900" id="open-rate">42%</p>
            </div>
        </div>
    </div>
    
    <!-- Conversion Rate -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-exchange-alt text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">معدل التحويل</p>
                <p class="text-xl font-bold text-gray-900" id="conversion-rate">8.5%</p>
            </div>
        </div>
    </div>
</div>
