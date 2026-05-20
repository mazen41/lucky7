<?php
/**
 * MAI Salon - Responsive POS System
 * Fully responsive Point of Sale system with mobile support
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'auth.php';

// Check admin authentication
checkAdminAuth();

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get shift status (placeholder - replace with actual logic)
$shift_active = true; // This would be determined from database

// Set page title and start output buffering for the responsive layout
$page_title = 'نقطة البيع (POS)';
ob_start();
?>

<!-- POS System Container -->
<div class="pos-container">
    <!-- POS Header -->
    <?php include 'includes/pos_header.php'; ?>
    
    <!-- Main POS Interface -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column (Products/Services) -->
        <div class="lg:col-span-2">
            <?php include 'includes/pos_products.php'; ?>
        </div>
        
        <!-- Right Column (Cart) -->
        <div class="lg:col-span-1">
            <?php include 'includes/pos_cart.php'; ?>
        </div>
    </div>
</div>

<!-- Payment Modal (hidden by default) -->
<div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-mai-dark-brown">إتمام عملية الدفع</h3>
            <button id="close-payment-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Left Side (Payment Methods) -->
                <div>
                    <h4 class="font-medium mb-2">طريقة الدفع</h4>
                    <div class="space-y-2">
                        <div class="payment-method bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" id="payment-cash" checked class="ml-2">
                                <label for="payment-cash" class="flex items-center cursor-pointer">
                                    <i class="fas fa-money-bill-wave text-green-600 ml-2"></i>
                                    <span>نقدي</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-method bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" id="payment-card" class="ml-2">
                                <label for="payment-card" class="flex items-center cursor-pointer">
                                    <i class="fas fa-credit-card text-blue-600 ml-2"></i>
                                    <span>بطاقة ائتمان</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-method bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" id="payment-bank" class="ml-2">
                                <label for="payment-bank" class="flex items-center cursor-pointer">
                                    <i class="fas fa-university text-purple-600 ml-2"></i>
                                    <span>تحويل بنكي</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-method bg-white border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" id="payment-mixed" class="ml-2">
                                <label for="payment-mixed" class="flex items-center cursor-pointer">
                                    <i class="fas fa-random text-amber-600 ml-2"></i>
                                    <span>دفع مختلط</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cash Payment Fields -->
                    <div id="cash-payment-fields" class="mt-4">
                        <div class="mb-3">
                            <label for="amount-received" class="block text-sm font-medium text-gray-700 mb-1">المبلغ المستلم</label>
                            <input type="number" id="amount-received" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div class="mb-3">
                            <label for="change-amount" class="block text-sm font-medium text-gray-700 mb-1">المبلغ المتبقي</label>
                            <input type="text" id="change-amount" value="0.00 ر.س" class="w-full border border-gray-300 rounded-lg px-4 py-2 bg-gray-100" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side (Invoice Summary) -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium mb-3">ملخص الفاتورة</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span>المجموع:</span>
                            <span id="modal-subtotal">350.00 ر.س</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>الضريبة (15%):</span>
                            <span id="modal-tax">52.50 ر.س</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>الخصم:</span>
                            <span id="modal-discount">0.00 ر.س</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg pt-2 border-t border-gray-300">
                            <span>الإجمالي:</span>
                            <span id="modal-total" class="text-mai-dark-brown">402.50 ر.س</span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label for="invoice-notes" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                        <textarea id="invoice-notes" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 flex flex-wrap justify-end gap-2">
            <button id="print-receipt-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-print ml-1"></i>
                <span>طباعة الإيصال</span>
            </button>
            <button id="complete-payment-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-check ml-1"></i>
                <span>إتمام الدفع</span>
            </button>
        </div>
    </div>
</div>

<!-- POS JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching between services and products
    const servicesTab = document.getElementById('services-tab');
    const productsTab = document.getElementById('products-tab');
    const serviceItems = document.querySelectorAll('.service-item');
    const productItems = document.querySelectorAll('.product-item');
    
    servicesTab.addEventListener('click', function() {
        servicesTab.classList.add('bg-mai-brown', 'text-white');
        servicesTab.classList.remove('bg-gray-200', 'text-gray-700');
        productsTab.classList.add('bg-gray-200', 'text-gray-700');
        productsTab.classList.remove('bg-mai-brown', 'text-white');
        
        serviceItems.forEach(item => item.classList.remove('hidden'));
        productItems.forEach(item => item.classList.add('hidden'));
    });
    
    productsTab.addEventListener('click', function() {
        productsTab.classList.add('bg-mai-brown', 'text-white');
        productsTab.classList.remove('bg-gray-200', 'text-gray-700');
        servicesTab.classList.add('bg-gray-200', 'text-gray-700');
        servicesTab.classList.remove('bg-mai-brown', 'text-white');
        
        productItems.forEach(item => item.classList.remove('hidden'));
        serviceItems.forEach(item => item.classList.add('hidden'));
    });
    
    // Customer selection
    const customerSearch = document.getElementById('customer-search');
    const selectedCustomer = document.getElementById('selected-customer');
    const removeCustomerBtn = document.getElementById('remove-customer-btn');
    
    customerSearch.addEventListener('focus', function() {
        // This would typically trigger an AJAX search
        // For demo purposes, we'll just show the selected customer
        selectedCustomer.classList.remove('hidden');
    });
    
    removeCustomerBtn.addEventListener('click', function() {
        selectedCustomer.classList.add('hidden');
        customerSearch.value = '';
    });
    
    // Checkout process
    const checkoutBtn = document.getElementById('checkout-btn');
    const paymentModal = document.getElementById('payment-modal');
    const closePaymentModal = document.getElementById('close-payment-modal');
    
    checkoutBtn.addEventListener('click', function() {
        paymentModal.classList.remove('hidden');
    });
    
    closePaymentModal.addEventListener('click', function() {
        paymentModal.classList.add('hidden');
    });
    
    // Payment method selection
    const paymentMethods = document.querySelectorAll('.payment-method');
    const cashPaymentFields = document.getElementById('cash-payment-fields');
    
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Show/hide payment-specific fields
            if (radio.id === 'payment-cash') {
                cashPaymentFields.classList.remove('hidden');
            } else {
                cashPaymentFields.classList.add('hidden');
            }
        });
    });
    
    // Calculate change
    const amountReceived = document.getElementById('amount-received');
    const changeAmount = document.getElementById('change-amount');
    const modalTotal = document.getElementById('modal-total');
    
    amountReceived.addEventListener('input', function() {
        const total = parseFloat(modalTotal.textContent);
        const received = parseFloat(this.value) || 0;
        const change = received - total;
        
        if (change >= 0) {
            changeAmount.value = change.toFixed(2) + ' ر.س';
            changeAmount.classList.remove('text-red-600');
            changeAmount.classList.add('text-green-600');
        } else {
            changeAmount.value = Math.abs(change).toFixed(2) + ' ر.س (متبقي)';
            changeAmount.classList.remove('text-green-600');
            changeAmount.classList.add('text-red-600');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
