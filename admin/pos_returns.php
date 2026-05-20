<?php

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Get recent invoices for returns
$recent_invoices_query = "SELECT i.*, 
                         (SELECT COUNT(*) FROM returns r WHERE r.original_invoice_id = i.id) as return_count
                         FROM invoices i 
                         WHERE i.status != 'cancelled' 
                         AND DATE(i.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         ORDER BY i.created_at DESC 
                         LIMIT 50";
$recent_invoices_stmt = $db->prepare($recent_invoices_query);
$recent_invoices_stmt->execute();
$recent_invoices = $recent_invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent returns
$recent_returns_query = "SELECT r.*, i.invoice_number as original_invoice_number
                        FROM returns r
                        LEFT JOIN invoices i ON r.original_invoice_id = i.id
                        ORDER BY r.created_at DESC
                        LIMIT 20";
$recent_returns_stmt = $db->prepare($recent_returns_query);
$recent_returns_stmt->execute();
$recent_returns = $recent_returns_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle return processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'process_return') {
            $invoice_id = intval($_POST['invoice_id']);
            $return_items = json_decode($_POST['return_items'], true);
            $return_reason = $_POST['return_reason'] ?? '';
            $refund_method = $_POST['refund_method'] ?? 'cash';
            
            // Get invoice details
            $invoice_query = "SELECT * FROM invoices WHERE id = ? AND status != 'cancelled'";
            $invoice_stmt = $db->prepare($invoice_query);
            $invoice_stmt->execute([$invoice_id]);
            $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                throw new Exception('الفاتورة غير موجودة أو ملغاة');
            }
            
            // Get invoice items
            $items_query = "SELECT * FROM invoice_items WHERE invoice_id = ?";
            $items_stmt = $db->prepare($items_query);
            $items_stmt->execute([$invoice_id]);
            $invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate return amount
            $return_amount = 0;
            $valid_return_items = [];
            
            foreach ($return_items as $return_item) {
                $item_id = intval($return_item['item_id']);
                $return_quantity = intval($return_item['quantity']);
                
                // Find the original item
                $original_item = null;
                foreach ($invoice_items as $item) {
                    if ($item['id'] == $item_id) {
                        $original_item = $item;
                        break;
                    }
                }
                
                if (!$original_item) {
                    throw new Exception('عنصر غير صحيح في الإرجاع');
                }
                
                if ($return_quantity > $original_item['quantity']) {
                    throw new Exception('كمية الإرجاع أكبر من الكمية الأصلية');
                }
                
                $item_return_amount = ($original_item['total_price'] / $original_item['quantity']) * $return_quantity;
                $return_amount += $item_return_amount;
                
                $valid_return_items[] = [
                    'item_id' => $item_id,
                    'quantity' => $return_quantity,
                    'amount' => $item_return_amount,
                    'original_item' => $original_item
                ];
            }
            
            if ($return_amount <= 0) {
                throw new Exception('مبلغ الإرجاع يجب أن يكون أكبر من صفر');
            }
            
            // Get current open shift
            $shift_query = "SELECT * FROM cash_shifts WHERE employee_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1";
            $shift_stmt = $db->prepare($shift_query);
            $shift_stmt->execute([$admin['id']]);
            $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
            
            $db->beginTransaction();
            
            try {
                // Generate return number
                $return_number = 'RET-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Determine return type
                $return_type = (count($valid_return_items) == count($invoice_items) && 
                               array_sum(array_column($valid_return_items, 'quantity')) == 
                               array_sum(array_column($invoice_items, 'quantity'))) ? 'full' : 'partial';
                
                // Insert return record
                $return_query = "INSERT INTO returns (
                    return_number, original_invoice_id, original_invoice_number, return_type, 
                    return_amount, refund_method, reason, employee_id, employee_name, cash_shift_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $return_stmt = $db->prepare($return_query);
                $return_stmt->execute([
                    $return_number,
                    $invoice_id,
                    $invoice['invoice_number'],
                    $return_type,
                    $return_amount,
                    $refund_method,
                    $return_reason,
                    $admin['id'],
                    $admin['username'],
                    $current_shift['id'] ?? null
                ]);
                
                $return_id = $db->lastInsertId();
                
                // Insert return items
                $return_item_query = "INSERT INTO return_items (
                    return_id, invoice_item_id, item_type, item_id, item_name, 
                    quantity_returned, unit_price, total_amount
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $return_item_stmt = $db->prepare($return_item_query);
                
                foreach ($valid_return_items as $return_item) {
                    $original = $return_item['original_item'];
                    $unit_price = $original['total_price'] / $original['quantity'];
                    
                    $return_item_stmt->execute([
                        $return_id,
                        $original['id'],
                        $original['item_type'],
                        $original['item_id'],
                        $original['item_name'],
                        $return_item['quantity'],
                        $unit_price,
                        $return_item['amount']
                    ]);
                    
                    // Update product stock if it's a product
                    if ($original['item_type'] === 'product') {
                        $update_stock_query = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?";
                        $update_stock_stmt = $db->prepare($update_stock_query);
                        $update_stock_stmt->execute([$return_item['quantity'], $original['item_id']]);
                    }
                }
                
                // Update invoice status if full return
                if ($return_type === 'full') {
                    $update_invoice_query = "UPDATE invoices SET status = 'returned' WHERE id = ?";
                    $update_invoice_stmt = $db->prepare($update_invoice_query);
                    $update_invoice_stmt->execute([$invoice_id]);
                }
                
                // Update shift totals if there's an open shift
                if ($current_shift) {
                    $update_shift_query = "UPDATE cash_shifts SET 
                                         total_returns = total_returns + ?,
                                         return_count = return_count + 1
                                         WHERE id = ?";
                    $update_shift_stmt = $db->prepare($update_shift_query);
                    $update_shift_stmt->execute([$return_amount, $current_shift['id']]);
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم إجراء الإرجاع بنجاح',
                    'return_number' => $return_number,
                    'return_amount' => $return_amount
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } else {
            throw new Exception('إجراء غير صحيح');
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'خطأ: ' . $e->getMessage()
        ]);
    }
    
    exit;
}


// Layout configuration
$page_title = 'مرتجعات نقاط البيع';
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
                        <span class="text-sm text-gray-600 mr-2">المرتجعات والاسترداد</span>
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Invoices for Returns -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-mai-dark-brown">الفواتير المتاحة للإرجاع</h3>
                    <p class="text-sm text-gray-600 mt-1">الفواتير من آخر 30 يوم</p>
                </div>
                <div class="p-6">
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($recent_invoices as $invoice): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:border-mai-brown cursor-pointer transition-colors"
                             @click="selectInvoiceForReturn(<?php echo htmlspecialchars(json_encode($invoice)); ?>)">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($invoice['customer_name'] ?: 'عميل غير محدد'); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('Y-m-d H:i', strtotime($invoice['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="font-bold text-mai-brown">
                                        <?php echo number_format($invoice['total_amount'], 2); ?> ر.س
                                    </div>
                                    <?php if ($invoice['return_count'] > 0): ?>
                                    <div class="text-xs text-orange-600">
                                        <?php echo $invoice['return_count']; ?> إرجاع
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($invoice['status'] === 'returned'): ?>
                                    <div class="text-xs text-red-600">مرتجعة</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Returns -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-mai-dark-brown">المرتجعات الأخيرة</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_returns)): ?>
                        <p class="text-gray-500 text-center py-8">لا توجد مرتجعات</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($recent_returns as $return): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($return['return_number']); ?></div>
                                        <div class="text-sm text-gray-600">
                                            الفاتورة الأصلية: <?php echo htmlspecialchars($return['original_invoice_number']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('Y-m-d H:i', strtotime($return['created_at'])); ?>
                                        </div>
                                        <?php if ($return['reason']): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            السبب: <?php echo htmlspecialchars($return['reason']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-left">
                                        <div class="font-bold text-red-600">
                                            -<?php echo number_format($return['return_amount'], 2); ?> ر.س
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            <?php
                                            $refund_methods = [
                                                'cash' => 'نقدي',
                                                'card' => 'بطاقة',
                                                'transfer' => 'تحويل'
                                            ];
                                            echo $refund_methods[$return['refund_method']] ?? $return['refund_method'];
                                            ?>
                                        </div>
                                        <div class="text-xs <?php echo $return['return_type'] === 'full' ? 'text-red-600' : 'text-orange-600'; ?>">
                                            <?php echo $return['return_type'] === 'full' ? 'إرجاع كامل' : 'إرجاع جزئي'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Modal -->
    <div x-show="showReturnModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
        <div class="bg-white rounded-xl p-6 w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-mai-dark-brown">إرجاع فاتورة</h3>
                <button @click="showReturnModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div x-show="selectedInvoice" class="space-y-6">
                <!-- Invoice Info -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="font-medium">رقم الفاتورة:</span>
                            <span x-text="selectedInvoice?.invoice_number"></span>
                        </div>
                        <div>
                            <span class="font-medium">العميل:</span>
                            <span x-text="selectedInvoice?.customer_name || 'غير محدد'"></span>
                        </div>
                        <div>
                            <span class="font-medium">التاريخ:</span>
                            <span x-text="selectedInvoice ? new Date(selectedInvoice.created_at).toLocaleDateString('ar-SA') : ''"></span>
                        </div>
                        <div>
                            <span class="font-medium">المبلغ الأصلي:</span>
                            <span x-text="selectedInvoice ? selectedInvoice.total_amount.toFixed(2) + ' ر.س' : ''"></span>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div>
                    <h4 class="font-medium mb-3">عناصر الفاتورة</h4>
                    <div class="space-y-2" x-show="invoiceItems.length > 0">
                        <template x-for="(item, index) in invoiceItems" :key="item.id">
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3 space-x-reverse">
                                        <input type="checkbox" 
                                               :id="'item-' + item.id"
                                               @change="toggleReturnItem(item, $event.target.checked)"
                                               class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                        <label :for="'item-' + item.id" class="flex-1">
                                            <div class="font-medium" x-text="item.item_name"></div>
                                            <div class="text-sm text-gray-600">
                                                <span x-text="item.quantity"></span> × 
                                                <span x-text="(item.total_price / item.quantity).toFixed(2)"></span> ر.س = 
                                                <span x-text="item.total_price.toFixed(2)"></span> ر.س
                                            </div>
                                        </label>
                                    </div>
                                    <div x-show="returnItems.find(ri => ri.item_id === item.id)" class="flex items-center space-x-2 space-x-reverse">
                                        <label class="text-sm">الكمية:</label>
                                        <input type="number" 
                                               :value="returnItems.find(ri => ri.item_id === item.id)?.quantity || 1"
                                               @input="updateReturnQuantity(item.id, parseInt($event.target.value))"
                                               :max="item.quantity"
                                               min="1"
                                               class="w-16 px-2 py-1 border border-gray-300 rounded text-center">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Return Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الاسترداد</label>
                        <select x-model="refundMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            <option value="cash">نقدي</option>
                            <option value="card">بطاقة</option>
                            <option value="transfer">تحويل</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مبلغ الاسترداد</label>
                        <input type="text" :value="calculateReturnAmount().toFixed(2) + ' ر.س'" readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سبب الإرجاع</label>
                    <textarea x-model="returnReason" rows="3" placeholder="اذكر سبب الإرجاع..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-3 space-x-reverse">
                    <button @click="showReturnModal = false" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button @click="processReturn()" 
                            :disabled="returnItems.length === 0"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <i class="fas fa-undo ml-2"></i>
                        تأكيد الإرجاع
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function returnsManager() {
            return {
                showReturnModal: false,
                selectedInvoice: null,
                invoiceItems: [],
                returnItems: [],
                refundMethod: 'cash',
                returnReason: '',
                
                async selectInvoiceForReturn(invoice) {
                    this.selectedInvoice = invoice;
                    this.returnItems = [];
                    this.returnReason = '';
                    
                    // Fetch invoice items
                    try {
                        const response = await fetch(`pos_invoice_items.php?invoice_id=${invoice.id}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.invoiceItems = result.items;
                            this.showReturnModal = true;
                        } else {
                            alert('خطأ في تحميل عناصر الفاتورة: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error fetching invoice items:', error);
                        alert('خطأ في تحميل عناصر الفاتورة');
                    }
                },
                
                toggleReturnItem(item, checked) {
                    if (checked) {
                        this.returnItems.push({
                            item_id: item.id,
                            quantity: 1
                        });
                    } else {
                        this.returnItems = this.returnItems.filter(ri => ri.item_id !== item.id);
                    }
                },
                
                updateReturnQuantity(itemId, quantity) {
                    const returnItem = this.returnItems.find(ri => ri.item_id === itemId);
                    if (returnItem) {
                        returnItem.quantity = Math.max(1, quantity);
                    }
                },
                
                calculateReturnAmount() {
                    let total = 0;
                    
                    this.returnItems.forEach(returnItem => {
                        const originalItem = this.invoiceItems.find(item => item.id === returnItem.item_id);
                        if (originalItem) {
                            const unitPrice = originalItem.total_price / originalItem.quantity;
                            total += unitPrice * returnItem.quantity;
                        }
                    });
                    
                    return total;
                },
                
                async processReturn() {
                    if (this.returnItems.length === 0) {
                        alert('يجب اختيار عنصر واحد على الأقل للإرجاع');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'process_return');
                        formData.append('invoice_id', this.selectedInvoice.id);
                        formData.append('return_items', JSON.stringify(this.returnItems));
                        formData.append('return_reason', this.returnReason);
                        formData.append('refund_method', this.refundMethod);
                        
                        const response = await fetch('pos_returns.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert(`تم إجراء الإرجاع بنجاح\nرقم الإرجاع: ${result.return_number}\nمبلغ الاسترداد: ${result.return_amount.toFixed(2)} ر.س`);
                            this.showReturnModal = false;
                            location.reload(); // Refresh to show updated data
                        } else {
                            alert('خطأ: ' + result.message);
                        }
                        
                    } catch (error) {
                        console.error('Return processing error:', error);
                        alert('حدث خطأ أثناء معالجة الإرجاع');
                    }
                }
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>