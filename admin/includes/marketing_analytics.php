<?php
/**
 * MAI Salon - Marketing Analytics Component
 * Responsive analytics for the marketing system
 */
?>
<!-- Marketing Analytics -->
<div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-200">
        <h2 class="font-bold text-mai-dark-brown">تحليلات التسويق</h2>
    </div>
    
    <!-- Analytics Filters -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-grow">
                <label class="block text-sm font-medium text-gray-700 mb-1">الفترة الزمنية</label>
                <div class="flex gap-2">
                    <button class="px-3 py-1 rounded-lg bg-mai-brown text-white">آخر 30 يوم</button>
                    <button class="px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">آخر 3 أشهر</button>
                    <button class="px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">آخر 6 أشهر</button>
                    <button class="px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">سنة</button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الحملة</label>
                <select class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                    <option value="">جميع الأنواع</option>
                    <option value="sms">رسائل نصية</option>
                    <option value="email">بريد إلكتروني</option>
                    <option value="whatsapp">واتساب</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Analytics Charts -->
    <div class="p-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Performance Chart -->
            <div>
                <h3 class="text-md font-medium text-gray-700 mb-3">أداء الحملات</h3>
                <div class="bg-gray-50 rounded-lg p-4 h-64 flex items-center justify-center">
                    <!-- This would be replaced with an actual chart -->
                    <div class="text-center">
                        <i class="fas fa-chart-line text-4xl text-gray-400 mb-2"></i>
                        <p class="text-gray-500">رسم بياني لأداء الحملات</p>
                    </div>
                </div>
            </div>
            
            <!-- Conversion Rate Chart -->
            <div>
                <h3 class="text-md font-medium text-gray-700 mb-3">معدل التحويل</h3>
                <div class="bg-gray-50 rounded-lg p-4 h-64 flex items-center justify-center">
                    <!-- This would be replaced with an actual chart -->
                    <div class="text-center">
                        <i class="fas fa-chart-pie text-4xl text-gray-400 mb-2"></i>
                        <p class="text-gray-500">رسم بياني لمعدل التحويل</p>
                    </div>
                </div>
            </div>
            
            <!-- Campaign Comparison -->
            <div>
                <h3 class="text-md font-medium text-gray-700 mb-3">مقارنة الحملات</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="py-2 px-3 text-right font-medium text-gray-700">الحملة</th>
                                <th class="py-2 px-3 text-right font-medium text-gray-700">المستلمون</th>
                                <th class="py-2 px-3 text-right font-medium text-gray-700">معدل الفتح</th>
                                <th class="py-2 px-3 text-right font-medium text-gray-700">معدل التحويل</th>
                                <th class="py-2 px-3 text-right font-medium text-gray-700">العائد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100">
                                <td class="py-2 px-3">عروض الصيف</td>
                                <td class="py-2 px-3">350</td>
                                <td class="py-2 px-3">48%</td>
                                <td class="py-2 px-3">12%</td>
                                <td class="py-2 px-3">5,250 ر.س</td>
                            </tr>
                            <tr class="border-b border-gray-100">
                                <td class="py-2 px-3">عيد ميلاد سعيد</td>
                                <td class="py-2 px-3">22</td>
                                <td class="py-2 px-3">95%</td>
                                <td class="py-2 px-3">45%</td>
                                <td class="py-2 px-3">1,850 ر.س</td>
                            </tr>
                            <tr class="border-b border-gray-100">
                                <td class="py-2 px-3">إعادة تنشيط العملاء</td>
                                <td class="py-2 px-3">85</td>
                                <td class="py-2 px-3">32%</td>
                                <td class="py-2 px-3">8%</td>
                                <td class="py-2 px-3">1,200 ر.س</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Channel Performance -->
            <div>
                <h3 class="text-md font-medium text-gray-700 mb-3">أداء القنوات</h3>
                <div class="space-y-4">
                    <!-- SMS Channel -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium">رسائل نصية</span>
                            <span class="text-sm text-gray-500">معدل التحويل: 9.5%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: 38%"></div>
                        </div>
                    </div>
                    
                    <!-- Email Channel -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium">بريد إلكتروني</span>
                            <span class="text-sm text-gray-500">معدل التحويل: 7.2%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: 29%"></div>
                        </div>
                    </div>
                    
                    <!-- WhatsApp Channel -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium">واتساب</span>
                            <span class="text-sm text-gray-500">معدل التحويل: 12.8%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-500 h-2 rounded-full" style="width: 51%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
