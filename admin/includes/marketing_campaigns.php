<?php
/**
 * MAI Salon - Marketing Campaigns List Component
 * Responsive campaigns list for the marketing system
 */
?>
<!-- Campaigns List -->
<div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="font-bold text-mai-dark-brown">الحملات الحالية</h2>
        <div class="text-sm text-gray-500">
            إجمالي الحملات: <span id="total-campaigns">5</span>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="relative flex-grow">
                <input type="text" id="campaign-search" placeholder="بحث عن حملة..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                <button id="search-campaign-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <div class="flex gap-2">
                <select id="status-filter" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">جميع الحالات</option>
                    <option value="active">نشطة</option>
                    <option value="scheduled">مجدولة</option>
                    <option value="completed">مكتملة</option>
                    <option value="draft">مسودة</option>
                </select>
                <select id="type-filter" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">جميع الأنواع</option>
                    <option value="sms">رسائل نصية</option>
                    <option value="email">بريد إلكتروني</option>
                    <option value="whatsapp">واتساب</option>
                </select>
                <button id="apply-filters-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors">
                    تطبيق
                </button>
            </div>
        </div>
    </div>
    
    <!-- Campaigns Grid -->
    <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Campaign Card 1 -->
            <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                <div class="bg-green-500 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold">عروض الصيف</h3>
                        <span class="bg-white bg-opacity-25 text-white text-xs px-2 py-1 rounded-full">نشطة</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>15 يونيو - 15 أغسطس 2023</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <i class="fas fa-paper-plane ml-1"></i>
                        <span>رسائل نصية + بريد إلكتروني</span>
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">خصومات صيفية على جميع خدمات العناية بالبشرة والشعر تصل إلى 30%</p>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-xs text-gray-500">المستلمون</div>
                            <div class="font-medium">350 عميل</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل الفتح</div>
                            <div class="font-medium">48%</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل التحويل</div>
                            <div class="font-medium">12%</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-1 space-x-reverse">
                        <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="إيقاف">
                            <i class="fas fa-pause"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Campaign Card 2 -->
            <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                <div class="bg-purple-500 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold">عيد ميلاد سعيد</h3>
                        <span class="bg-white bg-opacity-25 text-white text-xs px-2 py-1 rounded-full">نشطة</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>مستمرة</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <i class="fas fa-paper-plane ml-1"></i>
                        <span>واتساب</span>
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">رسائل تهنئة بعيد الميلاد مع قسيمة خصم 20% على الخدمات</p>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-xs text-gray-500">المستلمون</div>
                            <div class="font-medium">22 عميل</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل الفتح</div>
                            <div class="font-medium">95%</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل التحويل</div>
                            <div class="font-medium">45%</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-1 space-x-reverse">
                        <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="إيقاف">
                            <i class="fas fa-pause"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Campaign Card 3 -->
            <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                <div class="bg-blue-500 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold">إعادة تنشيط العملاء</h3>
                        <span class="bg-white bg-opacity-25 text-white text-xs px-2 py-1 rounded-full">نشطة</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>1 سبتمبر - 30 سبتمبر 2023</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <i class="fas fa-paper-plane ml-1"></i>
                        <span>بريد إلكتروني</span>
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">حملة لإعادة تنشيط العملاء غير النشطين مع عرض خاص للعودة</p>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-xs text-gray-500">المستلمون</div>
                            <div class="font-medium">85 عميل</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل الفتح</div>
                            <div class="font-medium">32%</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل التحويل</div>
                            <div class="font-medium">8%</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-1 space-x-reverse">
                        <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="إيقاف">
                            <i class="fas fa-pause"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Campaign Card 4 -->
            <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                <div class="bg-yellow-500 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold">عروض العيد</h3>
                        <span class="bg-white bg-opacity-25 text-white text-xs px-2 py-1 rounded-full">مجدولة</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>1 أكتوبر - 15 أكتوبر 2023</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <i class="fas fa-paper-plane ml-1"></i>
                        <span>رسائل نصية + واتساب</span>
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">عروض وباقات خاصة بمناسبة العيد مع خصومات تصل إلى 25%</p>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-xs text-gray-500">المستلمون</div>
                            <div class="font-medium">420 عميل</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل الفتح</div>
                            <div class="font-medium">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل التحويل</div>
                            <div class="font-medium">-</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-1 space-x-reverse">
                        <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="إلغاء">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Campaign Card 5 -->
            <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                <div class="bg-gray-500 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold">حملة نهاية السنة</h3>
                        <span class="bg-white bg-opacity-25 text-white text-xs px-2 py-1 rounded-full">مسودة</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>15 ديسمبر - 31 ديسمبر 2023</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <i class="fas fa-paper-plane ml-1"></i>
                        <span>بريد إلكتروني + رسائل نصية</span>
                    </div>
                    <p class="text-sm mb-3 line-clamp-2">عروض نهاية السنة وبداية السنة الجديدة مع باقات خاصة</p>
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-xs text-gray-500">المستلمون</div>
                            <div class="font-medium">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل الفتح</div>
                            <div class="font-medium">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">معدل التحويل</div>
                            <div class="font-medium">-</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-1 space-x-reverse">
                        <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="حذف">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pagination -->
    <div class="p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center">
        <div class="mb-3 sm:mb-0 text-sm text-gray-500">
            عرض 1-5 من 5 حملات
        </div>
        <div class="flex items-center">
            <button class="bg-gray-100 text-gray-700 px-3 py-1 rounded-r-lg hover:bg-gray-200 disabled:opacity-50" disabled>
                <i class="fas fa-chevron-right"></i>
            </button>
            <div class="flex mx-1">
                <button class="bg-mai-brown text-white w-8 h-8 rounded-lg mx-1">1</button>
            </div>
            <button class="bg-gray-100 text-gray-700 px-3 py-1 rounded-l-lg hover:bg-gray-200 disabled:opacity-50" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
    </div>
</div>
