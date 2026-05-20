<?php
/**
 * MAI Salon - Loyalty Tiers Component
 * Responsive tiers display for the loyalty program
 */
?>
<!-- Loyalty Tiers -->
<div class="bg-white shadow-sm rounded-lg mb-6">
    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="font-bold text-mai-dark-brown">شرائح برنامج الولاء</h2>
        <span class="text-sm text-gray-500">4 شرائح</span>
    </div>
    <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Bronze Tier -->
            <div class="border border-amber-300 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 p-4 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">برونزي</h3>
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i class="fas fa-award text-white"></i>
                        </div>
                    </div>
                    <p class="text-sm mt-1 text-white text-opacity-80">المستوى الأول</p>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">النقاط المطلوبة</p>
                        <p class="font-bold">0 - 999 نقطة</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">نسبة الخصم</p>
                        <p class="font-bold">5%</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">المزايا</p>
                        <ul class="text-sm list-disc list-inside">
                            <li>خصم 5% على جميع الخدمات</li>
                            <li>هدية عيد الميلاد</li>
                        </ul>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-amber-600 hover:text-amber-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Silver Tier -->
            <div class="border border-gray-300 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-gray-400 to-gray-500 p-4 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">فضي</h3>
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i class="fas fa-medal text-white"></i>
                        </div>
                    </div>
                    <p class="text-sm mt-1 text-white text-opacity-80">المستوى الثاني</p>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">النقاط المطلوبة</p>
                        <p class="font-bold">1,000 - 2,999 نقطة</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">نسبة الخصم</p>
                        <p class="font-bold">10%</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">المزايا</p>
                        <ul class="text-sm list-disc list-inside">
                            <li>خصم 10% على جميع الخدمات</li>
                            <li>هدية عيد الميلاد</li>
                            <li>أولوية الحجز</li>
                        </ul>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Gold Tier -->
            <div class="border border-yellow-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 p-4 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">ذهبي</h3>
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i class="fas fa-crown text-white"></i>
                        </div>
                    </div>
                    <p class="text-sm mt-1 text-white text-opacity-80">المستوى الثالث</p>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">النقاط المطلوبة</p>
                        <p class="font-bold">3,000 - 4,999 نقطة</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">نسبة الخصم</p>
                        <p class="font-bold">15%</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">المزايا</p>
                        <ul class="text-sm list-disc list-inside">
                            <li>خصم 15% على جميع الخدمات</li>
                            <li>هدية عيد الميلاد</li>
                            <li>أولوية الحجز</li>
                            <li>خدمة مجانية شهرياً</li>
                        </ul>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-yellow-600 hover:text-yellow-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Platinum Tier -->
            <div class="border border-purple-400 rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-4 text-white">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-lg">بلاتينيوم</h3>
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i class="fas fa-gem text-white"></i>
                        </div>
                    </div>
                    <p class="text-sm mt-1 text-white text-opacity-80">المستوى الرابع</p>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">النقاط المطلوبة</p>
                        <p class="font-bold">5,000+ نقطة</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">نسبة الخصم</p>
                        <p class="font-bold">20%</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-sm text-gray-500">المزايا</p>
                        <ul class="text-sm list-disc list-inside">
                            <li>خصم 20% على جميع الخدمات</li>
                            <li>هدية عيد الميلاد</li>
                            <li>أولوية الحجز</li>
                            <li>خدمتين مجانيتين شهرياً</li>
                            <li>خدمة VIP</li>
                        </ul>
                    </div>
                    <div class="flex justify-end">
                        <button class="text-purple-600 hover:text-purple-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
