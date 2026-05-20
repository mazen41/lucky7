<?php

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Get current open shift
$current_shift_query = "SELECT * FROM cash_shifts WHERE employee_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1";
$current_shift_stmt = $db->prepare($current_shift_query);
$current_shift_stmt->execute([$admin['id']]);
$current_shift = $current_shift_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent shifts
$recent_shifts_query = "SELECT * FROM cash_shifts WHERE employee_id = ? ORDER BY opened_at DESC LIMIT 10";
$recent_shifts_stmt = $db->prepare($recent_shifts_query);
$recent_shifts_stmt->execute([$admin['id']]);
$recent_shifts = $recent_shifts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's invoices for current shift
$today_invoices = [];
if ($current_shift) {
    $invoices_query = "SELECT * FROM invoices WHERE cash_shift_id = ? ORDER BY created_at DESC";
    $invoices_stmt = $db->prepare($invoices_query);
    $invoices_stmt->execute([$current_shift['id']]);
    $today_invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Layout configuration
$page_title = 'ورديات نقاط البيع';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>


    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold text-mai-brown">MAI</span>
                        <span class="text-sm text-gray-600 mr-2">إدارة الورديات</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                    <a href="pos.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة لنقطة البيع
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Current Shift Status -->
        <div class="mb-8">
            <?php if ($current_shift): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-green-800">الوردية الحالية</h2>
                        <p class="text-green-600">مفتوحة منذ <?php echo date('Y-m-d H:i', strtotime($current_shift['opened_at'])); ?></p>
                    </div>
                    <button @click="showCloseShiftModal = true" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700">
                        <i class="fas fa-lock ml-2"></i>
                        إغلاق الوردية
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-white rounded-lg p-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-mai-brown"><?php echo number_format($current_shift['opening_balance'], 2); ?></div>
                            <div class="text-sm text-gray-600">الرصيد الافتتاحي</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600"><?php echo number_format($current_shift['total_sales'], 2); ?></div>
                            <div class="text-sm text-gray-600">إجمالي المبيعات</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600"><?php echo $current_shift['invoice_count']; ?></div>
                            <div class="text-sm text-gray-600">عدد الفواتير</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600"><?php echo number_format($current_shift['opening_balance'] + $current_shift['total_cash'], 2); ?></div>
                            <div class="text-sm text-gray-600">الرصيد المتوقع</div>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods Breakdown -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-money-bill-wave text-green-500 text-xl ml-3"></i>
                            <div>
                                <div class="font-bold"><?php echo number_format($current_shift['total_cash'], 2); ?> ر.س</div>
                                <div class="text-sm text-gray-600">نقدي</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-credit-card text-blue-500 text-xl ml-3"></i>
                            <div>
                                <div class="font-bold"><?php echo number_format($current_shift['total_card'], 2); ?> ر.س</div>
                                <div class="text-sm text-gray-600">بطاقة</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-university text-purple-500 text-xl ml-3"></i>
                            <div>
                                <div class="font-bold"><?php echo number_format($current_shift['total_transfer'], 2); ?> ر.س</div>
                                <div class="text-sm text-gray-600">تحويل</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-yellow-800">لا توجد وردية مفتوحة</h2>
                        <p class="text-yellow-600">يجب فتح وردية جديدة لبدء العمل</p>
                    </div>
                    <button @click="showOpenShiftModal = true" class="bg-mai-brown text-white px-6 py-3 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-unlock ml-2"></i>
                        فتح وردية جديدة
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Today's Invoices -->
        <?php if ($current_shift && !empty($today_invoices)): ?>
        <div class="bg-white rounded-xl shadow-lg mb-8">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-mai-dark-brown">فواتير الوردية الحالية</h3>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-right py-3 px-4 font-medium text-gray-700">رقم الفاتورة</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">العميل</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">المبلغ</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">طريقة الدفع</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">الوقت</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-700">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_invoices as $invoice): ?>
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($invoice['customer_name'] ?: 'عميل غير محدد'); ?></td>
                                <td class="py-3 px-4 font-bold text-mai-brown"><?php echo number_format($invoice['total_amount'], 2); ?> ر.س</td>
                                <td class="py-3 px-4">
                                    <?php
                                    $payment_methods = [
                                        'cash' => 'نقدي',
                                        'card' => 'بطاقة',
                                        'transfer' => 'تحويل',
                                        'mixed' => 'مختلط'
                                    ];
                                    echo $payment_methods[$invoice['payment_method']] ?? $invoice['payment_method'];
                                    ?>
                                </td>
                                <td class="py-3 px-4"><?php echo date('H:i', strtotime($invoice['created_at'])); ?></td>
                                <td class="py-3 px-4">
                                    <button @click="viewInvoice(<?php echo $invoice['id']; ?>)" class="text-mai-brown hover:text-mai-dark-brown ml-2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button @click="printInvoice(<?php echo $invoice['id']; ?>)" class="text-blue-600 hover:text-blue-800 ml-2">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php if ($invoice['status'] !== 'returned'): ?>
                                    <button @click="returnInvoice(<?php echo $invoice['id']; ?>)" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Shifts -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-mai-dark-brown">الورديات السابقة</h3>
            </div>
            <div class="p-6">
                <?php if (empty($recent_shifts)): ?>
                    <p class="text-gray-500 text-center py-8">لا توجد ورديات سابقة</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_shifts as $shift): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center space-x-4 space-x-reverse">
                                        <span class="font-medium">
                                            <?php echo date('Y-m-d', strtotime($shift['opened_at'])); ?>
                                        </span>
                                        <span class="text-sm text-gray-600">
                                            <?php echo date('H:i', strtotime($shift['opened_at'])); ?>
                                            <?php if ($shift['closed_at']): ?>
                                                - <?php echo date('H:i', strtotime($shift['closed_at'])); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $shift['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $shift['status'] === 'open' ? 'مفتوحة' : 'مغلقة'; ?>
                                        </span>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-600">
                                        المبيعات: <?php echo number_format($shift['total_sales'], 2); ?> ر.س |
                                        الفواتير: <?php echo $shift['invoice_count']; ?> |
                                        <?php if ($shift['status'] === 'closed' && $shift['difference'] != 0): ?>
                                            الفرق: <span class="<?php echo $shift['difference'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo number_format($shift['difference'], 2); ?> ر.س
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button @click="viewShiftReport(<?php echo $shift['id']; ?>)" class="text-mai-brown hover:text-mai-dark-brown">
                                    <i class="fas fa-file-alt"></i>
                                    تقرير
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Open Shift Modal -->
    <div x-show="showOpenShiftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-mai-dark-brown">فتح وردية جديدة</h3>
                <button @click="showOpenShiftModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="pos_actions.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="open_shift">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الرصيد الافتتاحي</label>
                    <input type="number" name="opening_balance" step="0.01" min="0" value="0" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    <p class="text-sm text-gray-500 mt-1">المبلغ النقدي الموجود في الصندوق</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات (اختياري)</label>
                    <textarea name="notes" rows="3" placeholder="أي ملاحظات خاصة بالوردية..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                </div>

                <div class="flex space-x-3 space-x-reverse">
                    <button type="button" @click="showOpenShiftModal = false" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-unlock ml-2"></i>
                        فتح الوردية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Close Shift Modal -->
    <div x-show="showCloseShiftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-mai-dark-brown">إغلاق الوردية</h3>
                <button @click="showCloseShiftModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="pos_actions.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="close_shift">
                <input type="hidden" name="shift_id" value="<?php echo $current_shift['id'] ?? ''; ?>">
                
                <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                    <div class="flex justify-between">
                        <span>الرصيد الافتتاحي:</span>
                        <span><?php echo number_format($current_shift['opening_balance'] ?? 0, 2); ?> ر.س</span>
                    </div>
                    <div class="flex justify-between">
                        <span>النقدي المحصل:</span>
                        <span><?php echo number_format($current_shift['total_cash'] ?? 0, 2); ?> ر.س</span>
                    </div>
                    <div class="flex justify-between font-bold border-t pt-2">
                        <span>الرصيد المتوقع:</span>
                        <span><?php echo number_format(($current_shift['opening_balance'] ?? 0) + ($current_shift['total_cash'] ?? 0), 2); ?> ر.س</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الرصيد الفعلي في الصندوق</label>
                    <input type="number" name="closing_balance" step="0.01" min="0" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    <p class="text-sm text-gray-500 mt-1">عد النقود الموجودة في الصندوق</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات الإغلاق (اختياري)</label>
                    <textarea name="notes" rows="3" placeholder="أي ملاحظات خاصة بإغلاق الوردية..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                </div>

                <div class="flex space-x-3 space-x-reverse">
                    <button type="button" @click="showCloseShiftModal = false" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-lock ml-2"></i>
                        إغلاق الوردية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function shiftManager() {
            return {
                showOpenShiftModal: false,
                showCloseShiftModal: false,
                
                viewInvoice(invoiceId) {
                    // Implementation for viewing invoice details
                    console.log('View invoice:', invoiceId);
                },
                
                printInvoice(invoiceId) {
                    // Implementation for reprinting invoice
                    console.log('Print invoice:', invoiceId);
                },
                
                returnInvoice(invoiceId) {
                    // Implementation for returning invoice
                    console.log('Return invoice:', invoiceId);
                },
                
                viewShiftReport(shiftId) {
                    // Implementation for viewing shift report
                    window.open(`pos_shift_report.php?id=${shiftId}`, '_blank');
                }
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>