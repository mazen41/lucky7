<?php
/**
 * MAI Salon - POS Products/Services Component
 * Responsive product and service selection for the Point of Sale system
 */
?>
<!-- POS Products/Services Selection -->
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="bg-mai-cream p-4 border-b border-mai-brown/20">
        <h2 class="text-lg font-bold text-mai-dark-brown">الخدمات والمنتجات</h2>
    </div>
    
    <!-- Search and Category Filters -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-grow">
                <input type="text" id="product-search" placeholder="بحث عن خدمة أو منتج..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                <button id="search-product-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <div class="flex gap-2">
                <button id="services-tab" class="bg-mai-brown text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-cut ml-1"></i>
                    <span class="hidden sm:inline">الخدمات</span>
                </button>
                <button id="products-tab" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-shopping-bag ml-1"></i>
                    <span class="hidden sm:inline">المنتجات</span>
                </button>
            </div>
        </div>
        
        <!-- Category Pills -->
        <div class="flex flex-wrap gap-2 mt-3 overflow-x-auto pb-2">
            <button class="category-pill bg-mai-cream text-mai-brown px-3 py-1 rounded-full text-sm whitespace-nowrap">
                الكل
            </button>
            <button class="category-pill bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm whitespace-nowrap hover:bg-gray-200">
                قص الشعر
            </button>
            <button class="category-pill bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm whitespace-nowrap hover:bg-gray-200">
                صبغة الشعر
            </button>
            <button class="category-pill bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm whitespace-nowrap hover:bg-gray-200">
                تصفيف الشعر
            </button>
            <button class="category-pill bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm whitespace-nowrap hover:bg-gray-200">
                العناية بالبشرة
            </button>
            <button class="category-pill bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm whitespace-nowrap hover:bg-gray-200">
                المكياج
            </button>
            <button class="category-pill bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm whitespace-nowrap hover:bg-gray-200">
                العناية بالأظافر
            </button>
        </div>
    </div>
    
    <!-- Products/Services Grid -->
    <div class="p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-3">
            <!-- Service Item -->
            <div class="service-item bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex justify-between items-start">
                    <h4 class="font-medium">قص شعر نسائي</h4>
                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">خدمة</span>
                </div>
                <div class="flex justify-between items-end mt-2">
                    <div class="text-sm text-gray-500">
                        <span>45 دقيقة</span>
                    </div>
                    <div class="text-left">
                        <span class="font-medium">150.00 ر.س</span>
                    </div>
                </div>
            </div>
            
            <!-- Service Item -->
            <div class="service-item bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex justify-between items-start">
                    <h4 class="font-medium">صبغة شعر كاملة</h4>
                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">خدمة</span>
                </div>
                <div class="flex justify-between items-end mt-2">
                    <div class="text-sm text-gray-500">
                        <span>120 دقيقة</span>
                    </div>
                    <div class="text-left">
                        <span class="font-medium">350.00 ر.س</span>
                    </div>
                </div>
            </div>
            
            <!-- Product Item (hidden when services tab is active) -->
            <div class="product-item hidden bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex justify-between items-start">
                    <h4 class="font-medium">شامبو مرطب</h4>
                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">منتج</span>
                </div>
                <div class="flex justify-between items-end mt-2">
                    <div class="text-sm text-gray-500">
                        <span>المخزون: 15</span>
                    </div>
                    <div class="text-left">
                        <span class="font-medium">85.00 ر.س</span>
                    </div>
                </div>
            </div>
            
            <!-- More items would be dynamically loaded here -->
        </div>
        
        <!-- Load More Button -->
        <div class="text-center mt-4">
            <button id="load-more-btn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors">
                عرض المزيد
            </button>
        </div>
    </div>
</div>

<!-- Specialist Selection Modal (hidden by default) -->
<div id="specialist-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-mai-dark-brown">اختر المتخصص</h3>
        </div>
        <div class="p-4 max-h-80 overflow-y-auto">
            <div class="space-y-2">
                <!-- Specialist Option -->
                <div class="specialist-option bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                            <span class="text-purple-700 font-bold">س</span>
                        </div>
                        <div class="mr-3">
                            <p class="font-medium">سارة محمد</p>
                            <p class="text-xs text-gray-500">متخصصة شعر</p>
                        </div>
                    </div>
                </div>
                
                <!-- Specialist Option -->
                <div class="specialist-option bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-700 font-bold">ن</span>
                        </div>
                        <div class="mr-3">
                            <p class="font-medium">نورة العتيبي</p>
                            <p class="text-xs text-gray-500">متخصصة بشرة</p>
                        </div>
                    </div>
                </div>
                
                <!-- Specialist Option -->
                <div class="specialist-option bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-700 font-bold">م</span>
                        </div>
                        <div class="mr-3">
                            <p class="font-medium">منى الشمري</p>
                            <p class="text-xs text-gray-500">متخصصة مكياج</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end">
            <button id="cancel-specialist-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="select-specialist-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                اختيار
            </button>
        </div>
    </div>
</div>
