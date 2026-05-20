<?php
/**
 * MAI Salon - Loyalty System Header Component
 * Responsive header for the loyalty program
 */
?>
<!-- Loyalty Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">برنامج الولاء والنقاط</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-tier-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-layer-group ml-1"></i>
                <span>شريحة جديدة</span>
            </button>
            
            <button id="adjust-points-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-coins ml-1"></i>
                <span class="hidden sm:inline">تعديل النقاط</span>
            </button>
            
            <button id="loyalty-settings-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-cog ml-1"></i>
                <span class="hidden sm:inline">الإعدادات</span>
            </button>
            
            <button id="export-loyalty-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-export ml-1"></i>
                <span class="hidden sm:inline">تصدير</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Members -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-users text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">أعضاء البرنامج</p>
                <p class="text-xl font-bold text-gray-900" id="total-members">22</p>
            </div>
        </div>
    </div>
    
    <!-- Total Points -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-coins text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي النقاط</p>
                <p class="text-xl font-bold text-gray-900" id="total-points">3,450</p>
            </div>
        </div>
    </div>
    
    <!-- Points Redeemed -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-exchange-alt text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">نقاط مستبدلة</p>
                <p class="text-xl font-bold text-gray-900" id="redeemed-points">1,250</p>
            </div>
        </div>
    </div>
    
    <!-- Loyalty Value -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-money-bill-wave text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">القيمة النقدية</p>
                <p class="text-xl font-bold text-gray-900" id="loyalty-value">2,200.00 ر.س</p>
            </div>
        </div>
    </div>
</div>
