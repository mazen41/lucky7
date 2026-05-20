<?php
/**
 * MAI Salon - Team Management Header Component
 * Responsive header for the team management system
 */
?>
<!-- Team Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">إدارة فريق العمل</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-member-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-user-plus ml-1"></i>
                <span>عضو جديد</span>
            </button>
            
            <button id="schedule-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-calendar-alt ml-1"></i>
                <span class="hidden sm:inline">الجدول</span>
            </button>
            
            <button id="specialties-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-certificate ml-1"></i>
                <span class="hidden sm:inline">التخصصات</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Team Members -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-users text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي الفريق</p>
                <p class="text-xl font-bold text-gray-900" id="total-members">12</p>
            </div>
        </div>
    </div>
    
    <!-- Specialists -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-user-md text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">المتخصصين</p>
                <p class="text-xl font-bold text-gray-900" id="specialists-count">8</p>
            </div>
        </div>
    </div>
    
    <!-- Active Today -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-user-check text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">نشط اليوم</p>
                <p class="text-xl font-bold text-gray-900" id="active-today">6</p>
            </div>
        </div>
    </div>
    
    <!-- On Leave -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-user-clock text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">في إجازة</p>
                <p class="text-xl font-bold text-gray-900" id="on-leave">2</p>
            </div>
        </div>
    </div>
</div>
