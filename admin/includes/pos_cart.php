<?php
/**
 * MAI Salon - POS Cart Component
 * Responsive cart for the Point of Sale system
 */
?>
<!-- POS Cart -->
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="bg-mai-cream p-4 border-b border-mai-brown/20">
        <h2 class="text-lg font-bold text-mai-dark-brown">سلة المشتريات</h2>
    </div>
    
    <!-- Customer Selection -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <div class="relative flex-grow">
                <input type="text" id="customer-search" placeholder="بحث عن عميل..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                <button id="search-customer-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <button id="new-customer-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                <i class="fas fa-user-plus ml-1"></i>
                <span>عميل جديد</span>
            </button>
        </div>
        
        <!-- Selected Customer Info (hidden by default) -->
        <div id="selected-customer" class="hidden mt-3 p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-mai-cream rounded-full flex items-center justify-center">
                        <span class="text-mai-brown font-bold">ف</span>
                    </div>
                    <div class="mr-3">
                        <p class="font-medium">فاطمة أحمد السالم</p>
                        <p class="text-xs text-gray-500">CUS2025000001 | 0512345678</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full ml-2">عميل VIP</span>
                    <button id="remove-customer-btn" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cart Items -->
    <div class="p-4 max-h-80 overflow-y-auto">
        <div id="cart-items" class="space-y-3">
            <!-- Empty Cart Message (shown when cart is empty) -->
            <div id="empty-cart-message" class="text-center py-8 text-gray-500">
                <i class="fas fa-shopping-cart text-3xl mb-2"></i>
                <p>السلة فارغة</p>
                <p class="text-sm">أضف خدمات أو منتجات للبدء</p>
            </div>
            
            <!-- Sample Cart Item (hidden in empty state) -->
            <div class="cart-item hidden bg-gray-50 rounded-lg p-3">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="font-medium">قص شعر نسائي</h4>
                        <p class="text-xs text-gray-500">خدمة | المتخصص: سارة محمد</p>
                    </div>
                    <button class="remove-item-btn text-red-500 hover:text-red-700">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <div class="flex items-center">
                        <button class="decrease-qty bg-gray-200 hover:bg-gray-300 w-6 h-6 rounded-full flex items-center justify-center">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <span class="mx-2 text-sm">1</span>
                        <button class="increase-qty bg-gray-200 hover:bg-gray-300 w-6 h-6 rounded-full flex items-center justify-center">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                    <div class="text-left">
                        <span class="font-medium">150.00 ر.س</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cart Summary -->
    <div class="border-t border-gray-200 p-4 bg-gray-50">
        <div class="space-y-2">
            <div class="flex justify-between text-sm">
                <span>المجموع:</span>
                <span id="subtotal">0.00 ر.س</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>الضريبة (15%):</span>
                <span id="tax">0.00 ر.س</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>الخصم:</span>
                <span id="discount">0.00 ر.س</span>
            </div>
            <div class="flex justify-between font-bold text-lg pt-2 border-t border-gray-300">
                <span>الإجمالي:</span>
                <span id="total" class="text-mai-dark-brown">0.00 ر.س</span>
            </div>
        </div>
        
        <!-- Discount Button -->
        <button id="add-discount-btn" class="mt-4 w-full bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
            <i class="fas fa-tag ml-1"></i>
            <span>إضافة خصم</span>
        </button>
        
        <!-- Checkout Button -->
        <button id="checkout-btn" class="mt-2 w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg transition-colors flex items-center justify-center">
            <i class="fas fa-cash-register ml-1"></i>
            <span>إتمام عملية الدفع</span>
        </button>
    </div>
</div>
