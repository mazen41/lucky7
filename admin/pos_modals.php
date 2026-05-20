<!-- Payment Modal -->
<div x-show="showPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 no-print" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-mai-dark-brown">طريقة الدفع</h3>
            <button @click="showPaymentModal = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4">
            <!-- Payment Method Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">طريقة الدفع</label>
                <div class="grid grid-cols-2 gap-2">
                    <button @click="paymentMethod = 'cash'; cashAmount = invoice.total_amount; cardAmount = 0; transferAmount = 0"
                            :class="paymentMethod === 'cash' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-3 rounded-lg font-medium">
                        <i class="fas fa-money-bill-wave ml-2"></i>
                        نقدي
                    </button>
                    <button @click="paymentMethod = 'card'; cardAmount = invoice.total_amount; cashAmount = 0; transferAmount = 0"
                            :class="paymentMethod === 'card' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-3 rounded-lg font-medium">
                        <i class="fas fa-credit-card ml-2"></i>
                        بطاقة
                    </button>
                    <button @click="paymentMethod = 'transfer'; transferAmount = invoice.total_amount; cashAmount = 0; cardAmount = 0"
                            :class="paymentMethod === 'transfer' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-3 rounded-lg font-medium">
                        <i class="fas fa-university ml-2"></i>
                        تحويل
                    </button>
                    <button @click="paymentMethod = 'mixed'"
                            :class="paymentMethod === 'mixed' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-3 rounded-lg font-medium">
                        <i class="fas fa-coins ml-2"></i>
                        مختلط
                    </button>
                </div>
            </div>

            <!-- Mixed Payment Details -->
            <div x-show="paymentMethod === 'mixed'" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ النقدي</label>
                    <input type="number" x-model="cashAmount" step="0.01" min="0" :max="invoice.total_amount"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مبلغ البطاقة</label>
                    <input type="number" x-model="cardAmount" step="0.01" min="0" :max="invoice.total_amount"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مبلغ التحويل</label>
                    <input type="number" x-model="transferAmount" step="0.01" min="0" :max="invoice.total_amount"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                <div class="text-sm text-gray-600">
                    المجموع: <span x-text="(parseFloat(cashAmount || 0) + parseFloat(cardAmount || 0) + parseFloat(transferAmount || 0)).toFixed(2)"></span> ر.س
                    من أصل <span x-text="Number(invoice.total_amount || 0).toFixed(2)"></span> ر.س
                </div>
            </div>

            <!-- Total Display -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex justify-between items-center">
                    <span class="font-medium">المبلغ المطلوب:</span>
                    <span class="text-xl font-bold text-mai-brown" x-text="Number(invoice.total_amount || 0).toFixed(2) + ' ر.س'"></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-3 space-x-reverse">
                <button @click="showPaymentModal = false" 
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    إلغاء
                </button>
                <button @click="processPayment()" 
                        class="flex-1 px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                    <i class="fas fa-check ml-2"></i>
                    تأكيد الدفع
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Discount Modal -->
<div x-show="showDiscountModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 no-print" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-mai-dark-brown">إضافة خصم</h3>
            <button @click="showDiscountModal = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4">
            <!-- Discount Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">نوع الخصم</label>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="discountType = 'percentage'"
                            :class="discountType === 'percentage' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-2 rounded-lg text-sm font-medium">
                        نسبة مئوية
                    </button>
                    <button @click="discountType = 'fixed'"
                            :class="discountType === 'fixed' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-2 rounded-lg text-sm font-medium">
                        مبلغ ثابت
                    </button>
                    <button @click="discountType = 'code'"
                            :class="discountType === 'code' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                            class="p-2 rounded-lg text-sm font-medium">
                        كود خصم
                    </button>
                </div>
            </div>

            <!-- Discount Value -->
            <div x-show="discountType !== 'code'">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    <span x-show="discountType === 'percentage'">نسبة الخصم (%)</span>
                    <span x-show="discountType === 'fixed'">مبلغ الخصم (ر.س)</span>
                </label>
                <input type="number" x-model="discountValue" step="0.01" min="0" 
                       :max="discountType === 'percentage' ? 100 : invoice.subtotal"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
            </div>

            <!-- Discount Code -->
            <div x-show="discountType === 'code'">
                <label class="block text-sm font-medium text-gray-700 mb-1">كود الخصم</label>
                <div class="flex space-x-2 space-x-reverse">
                    <input type="text" x-model="$parent.discountCode" placeholder="أدخل كود الخصم"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    <button @click="validateDiscountCode($parent.discountCode)" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        تحقق
                    </button>
                </div>
                
                <!-- Available Discount Codes -->
                <div class="mt-2">
                    <p class="text-sm text-gray-600 mb-2">الأكواد المتاحة:</p>
                    <div class="space-y-1">
                        <?php foreach ($discount_codes as $code): ?>
                        <button @click="$parent.discountCode = '<?php echo $code['code'] ?? ''; ?>'; validateDiscountCode('<?php echo $code['code'] ?? ''; ?>')"
                                class="block w-full text-left p-2 text-sm bg-gray-50 hover:bg-gray-100 rounded">
                            <span class="font-medium"><?php echo htmlspecialchars($code['code'] ?? ''); ?></span>
                            <span class="text-gray-600 mr-2">
                                (<?php echo ($code['type'] ?? 'percentage') === 'percentage' ? ($code['value'] ?? 0) . '%' : ($code['value'] ?? 0) . ' ر.س'; ?>)
                            </span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="bg-gray-50 p-3 rounded-lg">
                <div class="text-sm">
                    <div class="flex justify-between">
                        <span>المجموع الفرعي:</span>
                        <span x-text="Number(invoice.subtotal || 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                    <div class="flex justify-between text-red-600" x-show="discountValue > 0 || discountCode">
                        <span>الخصم:</span>
                        <span x-text="'-' + Number(calculateDiscountPreview(discountType, discountValue, discountCode) || 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                    <div class="flex justify-between font-medium border-t pt-1 mt-1">
                        <span>بعد الخصم:</span>
                        <span x-text="Number((invoice.subtotal || 0) - (calculateDiscountPreview(discountType, discountValue, discountCode) || 0)).toFixed(2) + ' ر.س'"></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-3 space-x-reverse">
                <button @click="showDiscountModal = false" 
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    إلغاء
                </button>
                <button @click="applyDiscount(discountType, discountValue, discountCode)" 
                        class="flex-1 px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                    <i class="fas fa-check ml-2"></i>
                    تطبيق الخصم
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Open Shift Modal -->
<div x-show="openShiftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 no-print" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-mai-dark-brown">فتح وردية جديدة</h3>
            <button @click="openShiftModal = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="pos.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">المبلغ النقدي في بداية الوردية</label>
                    <input type="number" name="opening_cash" step="0.01" min="0" placeholder="0.00" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    <p class="text-sm text-gray-500 mt-1">أدخل المبلغ النقدي الموجود في الدرج</p>
                </div>

                <div class="flex space-x-3 space-x-reverse">
                    <button type="button" @click="openShiftModal = false" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" name="open_shift" value="1"
                            class="flex-1 px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-unlock ml-2"></i>
                        فتح الوردية
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Close Shift Modal -->
<div x-show="closeShiftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 no-print" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-mai-dark-brown">إغلاق الوردية</h3>
            <button @click="closeShiftModal = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4" x-data="{ closingCash: 0, shiftNotes: '' }">
            <!-- Shift Summary -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium mb-2">ملخص الوردية</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">الرصيد الافتتاحي:</span>
                        <span class="font-medium" x-text="Number(currentShift?.opening_cash || 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">المبيعات النقدية:</span>
                        <span class="font-medium text-green-600" x-text="Number(shiftSummary?.total_cash_in || 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">المرتجعات النقدية:</span>
                        <span class="font-medium text-red-600" x-text="Number(shiftSummary?.total_cash_out || 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">الرصيد المتوقع:</span>
                        <span class="font-bold" x-text="Number(shiftSummary?.expected_cash || 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                </div>
            </div>

            <!-- Closing Cash Input -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الرصيد الفعلي عند الإغلاق</label>
                <input type="number" x-model="closingCash" step="0.01" min="0" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                <p class="text-sm text-gray-500 mt-1">عد النقود الموجودة في الصندوق فعلياً</p>
            </div>

            <!-- Difference Display -->
            <div class="bg-yellow-50 p-3 rounded-lg" x-show="closingCash > 0">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">الفرق:</span>
                    <span class="font-bold" 
                          :class="(closingCash - (shiftSummary?.expected_cash || 0)) >= 0 ? 'text-green-600' : 'text-red-600'"
                          x-text="(closingCash - (shiftSummary?.expected_cash || 0)).toFixed(2) + ' ر.س'">
                    </span>
                </div>
                <p class="text-xs text-gray-600 mt-1" x-show="Math.abs(closingCash - (shiftSummary?.expected_cash || 0)) > 0.01">
                    <span x-show="(closingCash - (shiftSummary?.expected_cash || 0)) > 0">زيادة في الصندوق</span>
                    <span x-show="(closingCash - (shiftSummary?.expected_cash || 0)) < 0">نقص في الصندوق</span>
                </p>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات الإغلاق (اختياري)</label>
                <textarea x-model="shiftNotes" rows="3" placeholder="أي ملاحظات حول الوردية..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-3 space-x-reverse">
                <button @click="closeShiftModal = false" 
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    إلغاء
                </button>
                <button @click="closeShift(closingCash, shiftNotes)" 
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-lock ml-2"></i>
                    إغلاق الوردية
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Returns Modal -->
<div x-show="showReturnsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 no-print" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-mai-dark-brown">معالجة المرتجعات</h3>
            <button @click="showReturnsModal = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4" x-data="{ searchTerm: '', selectedInvoice: null, returnItems: [], returnReason: '', refundMethod: 'cash' }">
            <!-- Search Invoice -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البحث عن فاتورة</label>
                <div class="flex space-x-2 space-x-reverse">
                    <input type="text" x-model="searchTerm" placeholder="رقم الفاتورة أو رقم الهاتف أو اسم العميل"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    <button @click="searchInvoices(searchTerm)" 
                            class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <!-- Search Results -->
            <div x-show="searchResults.length > 0" class="border rounded-lg">
                <div class="max-h-48 overflow-y-auto">
                    <template x-for="invoice in searchResults" :key="invoice.id">
                        <div @click="selectInvoiceForReturn(invoice)" 
                             class="p-3 border-b hover:bg-gray-50 cursor-pointer">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-medium" x-text="invoice.invoice_number"></span>
                                    <span class="text-gray-600 mr-2" x-text="invoice.customer_name || 'عميل غير محدد'"></span>
                                </div>
                                <div class="text-left">
                                    <div class="font-medium" x-text="invoice.net_total + ' ر.س'"></div>
                                    <div class="text-sm text-gray-500" x-text="new Date(invoice.created_at).toLocaleDateString('ar-SA')"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Selected Invoice Items -->
            <div x-show="selectedInvoice" class="border rounded-lg p-4">
                <h4 class="font-medium mb-3">عناصر الفاتورة - <span x-text="selectedInvoice?.invoice_number"></span></h4>
                <div class="space-y-2">
                    <template x-for="item in invoiceItems" :key="item.id">
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <div class="flex items-center space-x-3 space-x-reverse">
                                <input type="checkbox" :value="item.id" 
                                       @change="toggleReturnItem(item, $event.target.checked)"
                                       class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                <div>
                                    <div class="font-medium" x-text="item.item_name"></div>
                                    <div class="text-sm text-gray-600">
                                        الكمية: <span x-text="item.qty"></span> × 
                                        <span x-text="item.unit_price"></span> ر.س
                                    </div>
                                </div>
                            </div>
                            <div class="text-left">
                                <div class="font-medium" x-text="item.total + ' ر.س'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Return Details -->
            <div x-show="returnItems.length > 0" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سبب الإرجاع</label>
                    <textarea x-model="returnReason" rows="3" required placeholder="اذكر سبب الإرجاع..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">طريقة الاسترداد</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button @click="refundMethod = 'cash'"
                                :class="refundMethod === 'cash' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                                class="p-2 rounded-lg text-sm font-medium">
                            نقدي
                        </button>
                        <button @click="refundMethod = 'card'"
                                :class="refundMethod === 'card' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                                class="p-2 rounded-lg text-sm font-medium">
                            بطاقة
                        </button>
                        <button @click="refundMethod = 'transfer'"
                                :class="refundMethod === 'transfer' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                                class="p-2 rounded-lg text-sm font-medium">
                            تحويل
                        </button>
                        <button @click="refundMethod = 'store_credit'"
                                :class="refundMethod === 'store_credit' ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-700'"
                                class="p-2 rounded-lg text-sm font-medium">
                            رصيد
                        </button>
                    </div>
                </div>

                <!-- Return Summary -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">إجمالي المرتجع:</span>
                        <span class="text-xl font-bold text-red-600" 
                              x-text="returnItems.reduce((sum, item) => sum + (item.qty * item.unit_price), 0).toFixed(2) + ' ر.س'"></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-3 space-x-reverse">
                    <button @click="showReturnsModal = false" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button @click="processReturn(selectedInvoice.id, returnItems, returnReason, refundMethod)" 
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-undo ml-2"></i>
                        معالجة المرتجع
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script moved to main pos.php file -->
