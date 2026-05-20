<?php
/**
 * MAI Salon - Inventory Management Header Component
 * Responsive header for the inventory system
 */
?>
<!-- Inventory Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">المخزون والمنتجات</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <button id="new-product-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-plus ml-1"></i>
                <span>منتج جديد</span>
            </button>
            
            <button id="new-category-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-folder-plus ml-1"></i>
                <span class="hidden sm:inline">تصنيف جديد</span>
            </button>
            
            <button id="import-products-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-import ml-1"></i>
                <span class="hidden sm:inline">استيراد</span>
            </button>
            
            <button id="export-products-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <i class="fas fa-file-export ml-1"></i>
                <span class="hidden sm:inline">تصدير</span>
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Products -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-full">
                <i class="fas fa-box text-blue-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">إجمالي المنتجات</p>
                <p class="text-xl font-bold text-gray-900" id="total-products">0</p>
            </div>
        </div>
    </div>
    
    <!-- Low Stock -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-full">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">مخزون منخفض</p>
                <p class="text-xl font-bold text-gray-900" id="low-stock">0</p>
            </div>
        </div>
    </div>
    
    <!-- Out of Stock -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-red-500">
        <div class="flex items-center">
            <div class="p-2 bg-red-100 rounded-full">
                <i class="fas fa-times-circle text-red-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">نفذ من المخزون</p>
                <p class="text-xl font-bold text-gray-900" id="out-of-stock">0</p>
            </div>
        </div>
    </div>
    
    <!-- Total Value -->
    <div class="bg-white rounded-xl shadow-sm p-4 border-r-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-full">
                <i class="fas fa-money-bill-wave text-green-600 text-lg"></i>
            </div>
            <div class="mr-3">
                <p class="text-xs text-gray-600">قيمة المخزون</p>
                <p class="text-xl font-bold text-gray-900" id="inventory-value">0.00 ر.س</p>
            </div>
        </div>
    </div>
</div>
