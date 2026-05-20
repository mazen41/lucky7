<?php
/**
 * MAI Salon - Inventory Management Search Component
 * Responsive search and filters for the inventory system
 */
?>
<!-- Search and Filters -->
<div class="bg-white shadow-sm rounded-lg mb-6">
    <div class="p-4 border-b border-gray-200">
        <h2 class="font-bold text-mai-dark-brown">بحث وتصفية</h2>
    </div>
    <div class="p-4">
        <!-- Search Bar -->
        <div class="mb-4">
            <div class="relative">
                <input type="text" id="product-search" placeholder="بحث عن منتج بالاسم، الرمز، الباركود..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                <button id="search-product-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <!-- Advanced Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Category Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">التصنيف</label>
                <select id="category-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">الكل</option>
                    <option value="hair">منتجات الشعر</option>
                    <option value="skin">منتجات البشرة</option>
                    <option value="makeup">مكياج</option>
                    <option value="nails">منتجات الأظافر</option>
                    <option value="tools">أدوات وأجهزة</option>
                </select>
            </div>
            
            <!-- Stock Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">حالة المخزون</label>
                <select id="stock-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">الكل</option>
                    <option value="in_stock">متوفر</option>
                    <option value="low_stock">مخزون منخفض</option>
                    <option value="out_of_stock">نفذ من المخزون</option>
                </select>
            </div>
            
            <!-- Sort By -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ترتيب حسب</label>
                <select id="sort-by" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="name_asc">الاسم (تصاعدي)</option>
                    <option value="name_desc">الاسم (تنازلي)</option>
                    <option value="stock_asc">المخزون (تصاعدي)</option>
                    <option value="stock_desc">المخزون (تنازلي)</option>
                    <option value="price_asc">السعر (تصاعدي)</option>
                    <option value="price_desc">السعر (تنازلي)</option>
                </select>
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div class="mt-4 flex justify-end space-x-2 space-x-reverse">
            <button id="reset-filters-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors">
                إعادة تعيين
            </button>
            <button id="apply-filters-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors">
                تطبيق الفلاتر
            </button>
        </div>
    </div>
</div>
