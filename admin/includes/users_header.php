<?php
/**
 * MAI Salon - User Management Header Component
 * Responsive header for the user management system
 */
?>
<!-- Users Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">إدارة المستخدمين</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-user-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-user-plus ml-1"></i>
                <span>مستخدم جديد</span>
            </button>
            
            <button id="roles-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-users-cog ml-1"></i>
                <span class="hidden sm:inline">الأدوار</span>
            </button>
            
            <button id="permissions-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-key ml-1"></i>
                <span class="hidden sm:inline">الصلاحيات</span>
            </button>
            
            <button id="audit-log-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-history ml-1"></i>
                <span class="hidden sm:inline">سجل التغييرات</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Users -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-users text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي المستخدمين</p>
                <p class="text-xl font-bold text-gray-900" id="total-users">12</p>
            </div>
        </div>
    </div>
    
    <!-- Active Users -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-user-check text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">المستخدمين النشطين</p>
                <p class="text-xl font-bold text-gray-900" id="active-users">10</p>
            </div>
        </div>
    </div>
    
    <!-- Roles -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-full">
                <i class="fas fa-user-tag text-purple-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">الأدوار</p>
                <p class="text-xl font-bold text-gray-900" id="total-roles">6</p>
            </div>
        </div>
    </div>
    
    <!-- Permissions -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-shield-alt text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">الصلاحيات</p>
                <p class="text-xl font-bold text-gray-900" id="total-permissions">60</p>
            </div>
        </div>
    </div>
</div>
