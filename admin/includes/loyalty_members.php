<?php
/**
 * MAI Salon - Loyalty Members Component
 * Responsive members table for the loyalty program
 */
?>
<!-- Loyalty Members -->
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="font-bold text-mai-dark-brown">أعضاء برنامج الولاء</h2>
        <div class="text-sm text-gray-500">
            إجمالي الأعضاء: <span id="total-members-count">22</span>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="relative flex-grow">
                <input type="text" id="member-search" placeholder="بحث عن عضو..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                <button id="search-member-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <div class="flex gap-2">
                <select id="tier-filter" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">جميع الشرائح</option>
                    <option value="bronze">برونزي</option>
                    <option value="silver">فضي</option>
                    <option value="gold">ذهبي</option>
                    <option value="platinum">بلاتينيوم</option>
                </select>
                <button id="apply-filters-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors">
                    تطبيق
                </button>
            </div>
        </div>
    </div>
    
    <!-- Table Container with Horizontal Scroll on Small Screens -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="py-3 px-4 text-right font-medium text-gray-700">العميل</th>
                    <th class="py-3 px-4 text-right font-medium text-gray-700 hidden sm:table-cell">معلومات الاتصال</th>
                    <th class="py-3 px-4 text-right font-medium text-gray-700">الشريحة</th>
                    <th class="py-3 px-4 text-right font-medium text-gray-700">النقاط</th>
                    <th class="py-3 px-4 text-right font-medium text-gray-700 hidden md:table-cell">آخر نشاط</th>
                    <th class="py-3 px-4 text-right font-medium text-gray-700">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <!-- Member 1 -->
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <span class="text-purple-700 font-bold">س</span>
                            </div>
                            <div class="mr-3">
                                <p class="font-medium">سارة عبدالله القحطاني</p>
                                <p class="text-xs text-gray-500">CUS2025000003</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <p class="text-sm">0534567890</p>
                        <p class="text-xs text-gray-500">sarah@example.com</p>
                    </td>
                    <td class="py-3 px-4">
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">بلاتينيوم</span>
                    </td>
                    <td class="py-3 px-4">
                        <p class="font-medium">5,250</p>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                            <div class="bg-purple-500 h-1.5 rounded-full" style="width: 100%"></div>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell">
                        <p class="text-sm">2023-09-12</p>
                        <p class="text-xs text-gray-500">قبل 3 أيام</p>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex space-x-1 space-x-reverse">
                            <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-green-600 hover:text-green-800" title="تعديل النقاط">
                                <i class="fas fa-coins"></i>
                            </button>
                            <button class="text-purple-600 hover:text-purple-800" title="تغيير الشريحة">
                                <i class="fas fa-layer-group"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                
                <!-- Member 2 -->
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-700 font-bold">ن</span>
                            </div>
                            <div class="mr-3">
                                <p class="font-medium">نورا محمد العتيبي</p>
                                <p class="text-xs text-gray-500">CUS2025000002</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <p class="text-sm">0523456789</p>
                        <p class="text-xs text-gray-500">noura@example.com</p>
                    </td>
                    <td class="py-3 px-4">
                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">ذهبي</span>
                    </td>
                    <td class="py-3 px-4">
                        <p class="font-medium">3,750</p>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                            <div class="bg-yellow-500 h-1.5 rounded-full" style="width: 75%"></div>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell">
                        <p class="text-sm">2023-09-05</p>
                        <p class="text-xs text-gray-500">قبل 10 أيام</p>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex space-x-1 space-x-reverse">
                            <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-green-600 hover:text-green-800" title="تعديل النقاط">
                                <i class="fas fa-coins"></i>
                            </button>
                            <button class="text-purple-600 hover:text-purple-800" title="تغيير الشريحة">
                                <i class="fas fa-layer-group"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                
                <!-- Member 3 -->
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <span class="text-purple-700 font-bold">ف</span>
                            </div>
                            <div class="mr-3">
                                <p class="font-medium">فاطمة أحمد السالم</p>
                                <p class="text-xs text-gray-500">CUS2025000001</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden sm:table-cell">
                        <p class="text-sm">0512345678</p>
                        <p class="text-xs text-gray-500">fatima@example.com</p>
                    </td>
                    <td class="py-3 px-4">
                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">فضي</span>
                    </td>
                    <td class="py-3 px-4">
                        <p class="font-medium">1,850</p>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                            <div class="bg-gray-500 h-1.5 rounded-full" style="width: 50%"></div>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell">
                        <p class="text-sm">2023-06-15</p>
                        <p class="text-xs text-gray-500">قبل 3 أشهر</p>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex space-x-1 space-x-reverse">
                            <button class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-green-600 hover:text-green-800" title="تعديل النقاط">
                                <i class="fas fa-coins"></i>
                            </button>
                            <button class="text-purple-600 hover:text-purple-800" title="تغيير الشريحة">
                                <i class="fas fa-layer-group"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center">
        <div class="mb-3 sm:mb-0 text-sm text-gray-500">
            عرض 1-3 من 22 عضو
        </div>
        <div class="flex items-center">
            <button class="bg-gray-100 text-gray-700 px-3 py-1 rounded-r-lg hover:bg-gray-200 disabled:opacity-50" disabled>
                <i class="fas fa-chevron-right"></i>
            </button>
            <div class="flex mx-1">
                <button class="bg-mai-brown text-white w-8 h-8 rounded-lg mx-1">1</button>
                <button class="bg-gray-100 text-gray-700 w-8 h-8 rounded-lg mx-1 hover:bg-gray-200">2</button>
                <button class="bg-gray-100 text-gray-700 w-8 h-8 rounded-lg mx-1 hover:bg-gray-200">3</button>
            </div>
            <button class="bg-gray-100 text-gray-700 px-3 py-1 rounded-l-lg hover:bg-gray-200">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
    </div>
</div>
