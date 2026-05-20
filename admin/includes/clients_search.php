<?php
/**
 * MAI Salon - Client Management Search Component
 * Responsive search and filters for the CRM system
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
                <input type="text" id="client-search" placeholder="بحث عن عميل بالاسم، البريد الإلكتروني، رقم الهاتف، أو رمز العميل..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                <button id="search-client-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <!-- Advanced Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Segment Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">شريحة العملاء</label>
                <select id="segment-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">الكل</option>
                    <option value="vip">عملاء VIP</option>
                    <option value="new">عملاء جدد</option>
                    <option value="regular">عملاء منتظمون</option>
                    <option value="inactive">عملاء غير نشطين</option>
                    <option value="sensitive">بشرة حساسة</option>
                    <option value="high_spenders">كثيرو الإنفاق</option>
                    <option value="birthday">عيد ميلاد هذا الشهر</option>
                    <option value="local">عملاء محليون</option>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                <select id="status-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">الكل</option>
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                    <option value="blocked">محظور</option>
                </select>
            </div>
            
            <!-- City Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المدينة</label>
                <select id="city-filter" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">الكل</option>
                    <option value="riyadh">الرياض</option>
                    <option value="jeddah">جدة</option>
                    <option value="dammam">الدمام</option>
                    <option value="mecca">مكة المكرمة</option>
                    <option value="medina">المدينة المنورة</option>
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
