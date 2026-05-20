<?php
/**
 * MAI Salon - User Management Roles Component
 * Responsive roles display for the user management system
 */
?>
<!-- Roles Section -->
<div class="bg-white shadow-sm rounded-lg mb-6">
    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="font-bold text-mai-dark-brown">الأدوار والصلاحيات</h2>
        <button id="new-role-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-3 py-1 rounded-lg transition-colors text-sm">
            <i class="fas fa-plus ml-1"></i>
            <span>دور جديد</span>
        </button>
    </div>
    <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Super Admin Role -->
            <div class="border border-purple-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">Super Admin</h3>
                        <div class="text-xs bg-white bg-opacity-25 px-2 py-1 rounded-full">
                            1 مستخدم
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm text-gray-600 mb-3">صلاحيات كاملة للنظام</p>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>الصلاحيات:</span>
                            <span>60 من 60</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-purple-500 h-1.5 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-0.5 rounded-full">جميع الصلاحيات</span>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-blue-600 hover:text-blue-800 ml-2" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800 ml-2" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800 opacity-50" title="حذف" disabled>
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Admin Role -->
            <div class="border border-blue-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">Admin</h3>
                        <div class="text-xs bg-white bg-opacity-25 px-2 py-1 rounded-full">
                            2 مستخدمين
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm text-gray-600 mb-3">صلاحيات إدارية مع بعض القيود</p>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>الصلاحيات:</span>
                            <span>50 من 60</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: 83%"></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">لوحة التحكم</span>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">العملاء</span>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">الحجوزات</span>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">المخزون</span>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-blue-600 hover:text-blue-800 ml-2" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800 ml-2" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="حذف">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Manager Role -->
            <div class="border border-green-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">Manager</h3>
                        <div class="text-xs bg-white bg-opacity-25 px-2 py-1 rounded-full">
                            1 مستخدم
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm text-gray-600 mb-3">صلاحيات إدارة العمليات اليومية</p>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>الصلاحيات:</span>
                            <span>40 من 60</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full" style="width: 67%"></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full">لوحة التحكم</span>
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full">الحجوزات</span>
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full">الموظفين</span>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-blue-600 hover:text-blue-800 ml-2" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800 ml-2" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="حذف">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Partner Role -->
            <div class="border border-yellow-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">Partner</h3>
                        <div class="text-xs bg-white bg-opacity-25 px-2 py-1 rounded-full">
                            1 مستخدم
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm text-gray-600 mb-3">صلاحيات عرض التقارير والإحصائيات</p>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>الصلاحيات:</span>
                            <span>20 من 60</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-yellow-500 h-1.5 rounded-full" style="width: 33%"></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded-full">لوحة التحكم</span>
                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded-full">التقارير</span>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-blue-600 hover:text-blue-800 ml-2" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800 ml-2" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="حذف">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Receptionist Role -->
            <div class="border border-pink-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-pink-500 to-pink-600 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">Receptionist</h3>
                        <div class="text-xs bg-white bg-opacity-25 px-2 py-1 rounded-full">
                            3 مستخدمين
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm text-gray-600 mb-3">صلاحيات إدارة الحجوزات والعملاء</p>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>الصلاحيات:</span>
                            <span>25 من 60</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-pink-500 h-1.5 rounded-full" style="width: 42%"></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <span class="bg-pink-100 text-pink-800 text-xs px-2 py-0.5 rounded-full">الحجوزات</span>
                        <span class="bg-pink-100 text-pink-800 text-xs px-2 py-0.5 rounded-full">العملاء</span>
                        <span class="bg-pink-100 text-pink-800 text-xs px-2 py-0.5 rounded-full">نقاط البيع</span>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-blue-600 hover:text-blue-800 ml-2" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800 ml-2" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" title="حذف">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Specialist Role -->
            <div class="border border-indigo-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 p-3 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">Specialist</h3>
                        <div class="text-xs bg-white bg-opacity-25 px-2 py-1 rounded-full">
                            4 مستخدمين
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <p class="text-sm text-gray-600 mb-3">صلاحيات محدودة لجدول المواعيد</p>
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>الصلاحيات:</span>
                            <span>10 من 60</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-indigo-500 h-1.5 rounded-full" style="width: 17%"></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-0.5 rounded-full">الحجوزات (عرض)</span>
                        <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-0.5 rounded-full">الجدول الشخصي</span>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-blue-600 hover:text-blue-800 ml-2" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="text-green-600 hover:text-green-800 ml-2" title="تعديل">
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
</div>
