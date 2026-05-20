<!-- Open Shift Modal -->
<div x-show="openShiftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-gray-900 mb-4">فتح وردية جديدة</h3>
        <form @submit.prevent="openShift()">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">الرصيد الافتتاحي</label>
                <input type="number" step="0.01" x-model="shiftData.opening_cash" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-mai-brown focus:border-mai-brown" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات</label>
                <textarea x-model="shiftData.notes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"></textarea>
            </div>
            <div class="flex justify-end space-x-3 space-x-reverse">
                <button type="button" @click="openShiftModal = false" 
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                    فتح الوردية
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Close Shift Modal -->
<div x-show="closeShiftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-gray-900 mb-4">إغلاق الوردية</h3>
        <form @submit.prevent="closeShift(shiftData.closing_cash, shiftData.notes)">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">الرصيد الفعلي</label>
                <input type="number" step="0.01" x-model="shiftData.closing_cash" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-mai-brown focus:border-mai-brown" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات</label>
                <textarea x-model="shiftData.notes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-mai-brown focus:border-mai-brown"></textarea>
            </div>
            <div class="flex justify-end space-x-3 space-x-reverse">
                <button type="button" @click="closeShiftModal = false" 
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    إغلاق الوردية
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Modal -->
<div x-show="showPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-bold text-gray-900 mb-4">معالجة الدفع</h3>
        <div class="mb-4">
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <div class="flex justify-between">
                    <span>المجموع الفرعي:</span>
                    <span x-text="invoice.subtotal.toFixed(2) + ' ر.س'"></span>
                </div>
                <div class="flex justify-between">
                    <span>الخصم:</span>
                    <span x-text="invoice.discount_amount.toFixed(2) + ' ر.س'"></span>
                </div>
                <div class="flex justify-between">
                    <span>الضريبة:</span>
                    <span x-text="invoice.tax_amount.toFixed(2) + ' ر.س'"></span>
                </div>
                <div class="flex justify-between font-bold text-lg border-t pt-2">
                    <span>الإجمالي:</span>
                    <span x-text="invoice.total_amount.toFixed(2) + ' ر.س'"></span>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">طريقة الدفع</label>
                <select x-model="paymentMethod" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="cash">نقدي</option>
                    <option value="card">بطاقة</option>
                    <option value="transfer">تحويل</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">المبلغ المدفوع</label>
                <input type="number" step="0.01" x-model="amountPaid" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            
            <div x-show="paymentMethod === 'cash' && amountPaid > invoice.total_amount" class="mb-4">
                <div class="bg-green-50 p-3 rounded-lg">
                    <span class="text-green-800">الباقي: </span>
                    <span class="font-bold text-green-800" x-text="(amountPaid - invoice.total_amount).toFixed(2) + ' ر.س'"></span>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 space-x-reverse">
            <button type="button" @click="showPaymentModal = false" 
                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                إلغاء
            </button>
            <button @click="processPayment()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                تأكيد الدفع
            </button>
        </div>
    </div>
</div>

<!-- Returns Modal -->
<div x-show="showReturnsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
    <div class="bg-white rounded-xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-4">إدارة المرتجعات</h3>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">البحث عن فاتورة</label>
            <input type="text" x-model="searchQuery" @input="searchInvoices()" 
                   placeholder="رقم الفاتورة أو رقم الهاتف"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        
        <div x-show="searchResults.length > 0" class="mb-4">
            <h4 class="font-medium mb-2">نتائج البحث:</h4>
            <div class="space-y-2">
                <template x-for="invoice in searchResults">
                    <div class="border p-3 rounded-lg cursor-pointer hover:bg-gray-50" 
                         @click="selectInvoiceForReturn(invoice)">
                        <div class="flex justify-between">
                            <span x-text="'فاتورة رقم: ' + invoice.invoice_number"></span>
                            <span x-text="invoice.total_amount + ' ر.س'"></span>
                        </div>
                        <div class="text-sm text-gray-600">
                            <span x-text="invoice.customer_name || 'عميل غير مسجل'"></span>
                            <span x-text="' - ' + invoice.created_at"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <div class="flex justify-end">
            <button @click="showReturnsModal = false" 
                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                إغلاق
            </button>
        </div>
    </div>
</div>
