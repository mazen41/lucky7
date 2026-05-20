<?php
// Redirect to admin POS system
header('Location: admin/pos.php');
exit();

// Get current open shift - with error handling
$current_shift = null;
try {
    // First check if shifts table has required columns
    $columns = $db->query("SHOW COLUMNS FROM shifts")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('user_id', $columns) && in_array('status', $columns)) {
        $shift_query = "SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1";
        $shift_stmt = $db->prepare($shift_query);
        $shift_stmt->execute([$admin['id']]);
        $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Create missing columns
        if (!in_array('user_id', $columns)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN user_id INT(11) NOT NULL DEFAULT 1");
        }
        if (!in_array('status', $columns)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN status ENUM('opened','closing_pending','closed') DEFAULT 'opened'");
        }
        if (!in_array('opened_at', $columns)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        if (!in_array('opening_cash', $columns)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN opening_cash DECIMAL(12,2) DEFAULT 0.00");
        }
        
        // Try query again
        $shift_query = "SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1";
        $shift_stmt = $db->prepare($shift_query);
        $shift_stmt->execute([$admin['id']]);
        $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Shift query error: " . $e->getMessage());
    $current_shift = null;
}

// Get services
$services_query = "SELECT * FROM services WHERE is_active = 1 ORDER BY title_ar";
$services_stmt = $db->prepare($services_query);
$services_stmt->execute();
$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products
$products_query = "SELECT * FROM products WHERE is_active = 1 ORDER BY name_ar";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute();
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team members (specialists)
$specialists_query = "SELECT * FROM team_members WHERE is_active = 1 ORDER BY name_ar";
$specialists_stmt = $db->prepare($specialists_query);
$specialists_stmt->execute();
$specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get discount codes - with error handling
$discount_codes = [];
try {
    // Check if discount_codes table and columns exist
    $tables = $db->query("SHOW TABLES LIKE 'discount_codes'")->fetchAll();
    
    if (!empty($tables)) {
        $dc_columns = $db->query("SHOW COLUMNS FROM discount_codes")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('name_ar', $dc_columns) && in_array('code', $dc_columns)) {
            $dc_query = "SELECT * FROM discount_codes WHERE is_active = 1 ORDER BY name_ar";
            $dc_stmt = $db->prepare($dc_query);
            $dc_stmt->execute();
            $discount_codes = $dc_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Create discount_codes table
        $db->exec("CREATE TABLE IF NOT EXISTS discount_codes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL UNIQUE,
            name_ar VARCHAR(255) NULL,
            name_en VARCHAR(255) NULL,
            type ENUM('percentage','fixed') DEFAULT 'percentage',
            value DECIMAL(10,2) NOT NULL,
            applicable_to ENUM('all','services','products') DEFAULT 'all',
            min_amount DECIMAL(10,2) DEFAULT 0.00,
            usage_limit INT(11) NULL,
            used_count INT(11) DEFAULT 0,
            start_date DATE NULL,
            end_date DATE NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT(11) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Insert sample discount codes
        $db->exec("INSERT IGNORE INTO discount_codes (code, name_ar, type, value, created_by) VALUES 
            ('WELCOME10', 'خصم الترحيب', 'percentage', 10.00, 1),
            ('FIRST50', 'خصم العميل الجديد', 'fixed', 50.00, 1)");
        
        $discount_codes = $db->query("SELECT * FROM discount_codes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Discount codes error: " . $e->getMessage());
    $discount_codes = [];
}

// Handle shift opening with redirect
if ($_POST && isset($_POST['open_shift'])) {
    try {
        $opening_cash = floatval($_POST['opening_cash'] ?? 0);
        
        $insert_shift = $db->prepare("INSERT INTO shifts (user_id, opened_at, opening_cash, status) VALUES (?, NOW(), ?, 'opened')");
        $insert_shift->execute([$admin['id'], $opening_cash]);
        
        // Redirect to POS after opening shift
        header('Location: pos.php?shift_opened=1');
        exit();
    } catch (PDOException $e) {
        $error_message = 'خطأ في فتح الوردية: ' . $e->getMessage();
    }
}

// Get POS settings
$pos_settings_query = "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'pos_%'";
$pos_settings_stmt = $db->prepare($pos_settings_query);
$pos_settings_stmt->execute();
$pos_settings_raw = $pos_settings_stmt->fetchAll(PDO::FETCH_ASSOC);

$pos_settings = [];
foreach ($pos_settings_raw as $setting) {
    $pos_settings[$setting['setting_key']] = $setting['setting_value'];
}


// Layout configuration
$page_title = 'نقطة البيع';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>

<div x-data="posSystem()" class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold text-mai-brown">MAI</span>
                        <span class="text-sm text-gray-600 mr-2">نقطة البيع</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                    <?php if ($current_shift): ?>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                            <i class="fas fa-cash-register ml-1"></i>
                            الوردية مفتوحة
                        </span>
                    <?php else: ?>
                        <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm">
                            <i class="fas fa-lock ml-1"></i>
                            الوردية مغلقة
                        </span>
                    <?php endif; ?>
                    <?php if ($current_shift): ?>
                        <button @click="closeShiftModal = true" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-lock ml-1"></i>
                            إغلاق الوردية
                        </button>
                    <?php endif; ?>
                    <button @click="showReturnsModal = true" class="text-orange-600 hover:text-orange-800">
                        <i class="fas fa-undo ml-1"></i>
                        المرتجعات
                    </button>
                    <a href="pos_discounts.php" class="text-purple-600 hover:text-purple-800">
                        <i class="fas fa-tags ml-1"></i>
                        أكواد الخصم
                    </a>
                    <a href="dashboard.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        

        <!-- Main Content -->
        <div class="flex-1 p-6">
            <!-- Shift Management -->
            <?php if (!$current_shift): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-yellow-800">يجب فتح وردية جديدة</h3>
                        <p class="text-yellow-700">لا يمكن إنشاء فواتير بدون فتح وردية</p>
                    </div>
                    <button @click="openShiftModal = true" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700">
                        <i class="fas fa-plus ml-2"></i>
                        فتح وردية
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel - Items Selection -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Services -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-mai-dark-brown mb-4">الخدمات</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($services as $service): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-mai-brown cursor-pointer transition-colors"
                                 @click="addItem('service', <?php echo htmlspecialchars(json_encode($service)); ?>)">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($service['title_ar'] ?? 'خدمة'); ?></h4>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($service['description_ar'] ?? ''); ?></p>
                                <div class="flex justify-between items-center mt-3">
                                    <span class="text-lg font-bold text-mai-brown"><?php echo number_format($service['price'] ?? 0, 2); ?> ر.س</span>
                                    <span class="text-sm text-gray-500"><?php echo ($service['duration'] ?? 0); ?> دقيقة</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Products -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-mai-dark-brown mb-4">المنتجات</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($products as $product): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-mai-brown cursor-pointer transition-colors"
                                 @click="addItem('product', <?php echo htmlspecialchars(json_encode($product)); ?>)">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name_ar'] ?? 'منتج'); ?></h4>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($product['category_ar'] ?? 'عام'); ?></p>
                                <div class="flex justify-between items-center mt-3">
                                    <span class="text-lg font-bold text-mai-brown"><?php echo number_format($product['price'] ?? 0, 2); ?> ر.س</span>
                                    <span class="text-sm text-gray-500">المخزون: <?php echo $product['stock_quantity'] ?? 0; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Panel - Invoice -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-mai-dark-brown">الفاتورة</h3>
                        <button @click="clearInvoice()" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                    <!-- Customer Info -->
                    <div class="mb-4 space-y-2">
                        <input type="text" x-model="invoice.customer_name" placeholder="اسم العميل (اختياري)" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        <input type="tel" x-model="invoice.customer_phone" placeholder="رقم الهاتف (اختياري)" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                    </div>

                    <!-- Invoice Items -->
                    <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                        <template x-for="(item, index) in invoice.items" :key="index">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <h4 class="font-medium text-sm" x-text="item.name"></h4>
                                    <div class="flex items-center space-x-2 space-x-reverse mt-1">
                                        <button @click="updateQuantity(index, -1)" class="w-6 h-6 bg-gray-300 rounded text-xs">-</button>
                                        <span class="text-sm" x-text="item.quantity"></span>
                                        <button @click="updateQuantity(index, 1)" class="w-6 h-6 bg-gray-300 rounded text-xs">+</button>
                                        <span class="text-sm text-gray-600">×</span>
                                        <span class="text-sm font-medium" x-text="item.unit_price.toFixed(2)"></span>
                                    </div>
                                    <!-- Specialist Selection for Services -->
                                    <div x-show="item.type === 'service'" class="mt-2">
                                        <select x-model="item.specialist_id" class="w-full text-xs border border-gray-300 rounded">
                                            <option value="">اختر المختص</option>
                                            <?php foreach ($specialists as $specialist): ?>
                                            <option value="<?php echo $specialist['id']; ?>"><?php echo htmlspecialchars($specialist['name_ar']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <span class="font-bold text-mai-brown" x-text="item.total_price.toFixed(2)"></span>
                                    <button @click="removeItem(index)" class="block text-red-600 hover:text-red-800 mt-1">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                        
                        <div x-show="invoice.items.length === 0" class="text-center text-gray-500 py-8">
                            لا توجد عناصر في الفاتورة
                        </div>
                    </div>

                    <!-- Discount Section -->
                    <div class="border-t pt-4 mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium">الخصم</span>
                            <button @click="showDiscountModal = true" class="text-mai-brown hover:text-mai-dark-brown">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div x-show="invoice.discount_amount > 0" class="text-sm text-gray-600">
                            <span x-text="invoice.discount_type === 'percentage' ? invoice.discount_value + '%' : invoice.discount_value.toFixed(2) + ' ر.س'"></span>
                            <span class="mr-2">-</span>
                            <span x-text="invoice.discount_amount.toFixed(2)"></span>
                            <span class="mr-1">ر.س</span>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between">
                            <span>المجموع الفرعي:</span>
                            <span x-text="invoice.subtotal.toFixed(2) + ' ر.س'"></span>
                        </div>
                        <div class="flex justify-between" x-show="invoice.discount_amount > 0">
                            <span>الخصم:</span>
                            <span class="text-red-600" x-text="'-' + invoice.discount_amount.toFixed(2) + ' ر.س'"></span>
                        </div>
                        <div class="flex justify-between" x-show="invoice.tax_amount > 0">
                            <span>الضريبة (<?php echo $pos_settings['pos_tax_rate'] ?? 15; ?>%):</span>
                            <span x-text="invoice.tax_amount.toFixed(2) + ' ر.س'"></span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                            <span>المجموع الكلي:</span>
                            <span class="text-mai-brown" x-text="invoice.total_amount.toFixed(2) + ' ر.س'"></span>
                        </div>
                    </div>

                    <!-- Payment Button -->
                    <button @click="showPaymentModal = true" 
                            :disabled="invoice.items.length === 0 || !<?php echo $current_shift ? 'true' : 'false'; ?>"
                            class="w-full mt-4 bg-mai-brown text-white py-3 rounded-lg font-bold hover:bg-mai-dark-brown disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <i class="fas fa-credit-card ml-2"></i>
                        الدفع والطباعة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Template (Hidden, for printing) -->
    <div id="receipt-template" class="print-only receipt" style="display: none;">
        <div class="text-center mb-4">
            <h2 class="font-bold text-lg"><?php echo $pos_settings['pos_receipt_header'] ?? 'صالون MAI للجمال'; ?></h2>
            <p class="text-sm"><?php echo $pos_settings['pos_business_address'] ?? ''; ?></p>
            <p class="text-sm"><?php echo $pos_settings['pos_business_phone'] ?? ''; ?></p>
            <p class="text-sm"><?php echo $pos_settings['pos_business_email'] ?? ''; ?></p>
        </div>
        
        <div class="border-t border-b py-2 mb-2">
            <div class="flex justify-between">
                <span>رقم الفاتورة:</span>
                <span id="receipt-invoice-number"></span>
            </div>
            <div class="flex justify-between">
                <span>التاريخ:</span>
                <span id="receipt-date"></span>
            </div>
            <div class="flex justify-between">
                <span>الموظف:</span>
                <span><?php echo htmlspecialchars($admin['username']); ?></span>
            </div>
            <div id="receipt-customer-info"></div>
        </div>
        
        <div id="receipt-items"></div>
        
        <div class="border-t pt-2 mt-2">
            <div class="flex justify-between">
                <span>المجموع الفرعي:</span>
                <span id="receipt-subtotal"></span>
            </div>
            <div id="receipt-discount" class="flex justify-between" style="display: none;">
                <span>الخصم:</span>
                <span id="receipt-discount-amount"></span>
            </div>
            <div id="receipt-tax" class="flex justify-between" style="display: none;">
                <span>الضريبة:</span>
                <span id="receipt-tax-amount"></span>
            </div>
            <div class="flex justify-between font-bold border-t pt-1">
                <span>المجموع الكلي:</span>
                <span id="receipt-total"></span>
            </div>
            <div class="flex justify-between mt-1">
                <span>طريقة الدفع:</span>
                <span id="receipt-payment-method"></span>
            </div>
        </div>
        
        <div class="text-center mt-4 text-sm">
            <p><?php echo $pos_settings['pos_receipt_footer'] ?? 'شكراً لزيارتكم - نتطلع لخدمتكم مرة أخرى'; ?></p>
            <div id="receipt-qr-code"></div>
        </div>
    </div>

    <?php include 'pos_modals.php'; ?>
    
    <script>
        function posSystem() {
            return {
                services: <?php echo json_encode($services); ?>,
                products: <?php echo json_encode($products); ?>,
                specialists: <?php echo json_encode($specialists); ?>,
                discountCodes: <?php echo json_encode($discount_codes); ?>,
                
                // Modal states
                openShiftModal: false,
                closeShiftModal: false,
                showPaymentModal: false,
                showReturnsModal: false,
                showDiscountModal: false,
                
                // Form data
                shiftData: {
                    opening_cash: 0,
                    closing_cash: 0,
                    notes: ''
                },
                
                // Payment data
                paymentMethod: 'cash',
                amountPaid: 0,
                
                // Search and returns
                searchQuery: '',
                searchResults: [],
                selectedInvoice: null,
                invoiceItems: [],
                returnItems: [],
                
                invoice: {
                    items: [],
                    customer_name: '',
                    customer_phone: '',
                    subtotal: 0,
                    discount_amount: 0,
                    discount_type: '',
                    discount_value: 0,
                    discount_code: '',
                    tax_amount: 0,
                    total_amount: 0
                },
                
                // Initialize method
                init() {
                    this.calculateTotals();
                    console.log('POS System initialized successfully');
                },
                
                   calculateTotals() {
                    // 1. Calculate base subtotal
                    this.invoice.subtotal = this.invoice.items.reduce((sum, item) => sum + item.total_price, 0);
                    
                    // 2. Calculate discount (from base subtotal)
                    if (this.invoice.discount_type === 'percentage') {
                        this.invoice.discount_amount = (this.invoice.subtotal * this.invoice.discount_value) / 100;
                    } else {
                        this.invoice.discount_amount = Math.min(this.invoice.discount_value, this.invoice.subtotal);
                    }
                    
                    const amountAfterDiscount = this.invoice.subtotal - this.invoice.discount_amount;
                    
                    // 3. ADD VAT ON TOP of the price after discount
                    this.invoice.tax_amount = amountAfterDiscount * (this.taxRate / 100);
                    
                    // 4. Final Total = (Price - Discount) + VAT
                    this.invoice.total_amount = amountAfterDiscount + this.invoice.tax_amount;
                },

                
                clearInvoice() {
                    this.invoice = {
                        items: [],
                        customer_name: '',
                        customer_phone: '',
                        subtotal: 0,
                        discount_amount: 0,
                        discount_type: '',
                        discount_value: 0,
                        discount_code: '',
                        tax_amount: 0,
                        total: 0
                    };
                },
                
                async openShift() {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'open_shift');
                        formData.append('opening_cash', this.shiftData.opening_cash);
                        formData.append('notes', this.shiftData.notes);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('تم فتح الوردية بنجاح');
                            location.reload();
                        } else {
                            alert('خطأ: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Shift opening error:', error);
                        alert('حدث خطأ في فتح الوردية');
                    }
                },
                
                addItem(type, item) {
                    const existingIndex = this.invoice.items.findIndex(i => 
                        i.type === type && i.id === item.id
                    );
                    
                    if (existingIndex >= 0) {
                        this.invoice.items[existingIndex].quantity++;
                        this.invoice.items[existingIndex].total_price = 
                            this.invoice.items[existingIndex].quantity * this.invoice.items[existingIndex].unit_price;
                    } else {
                        this.invoice.items.push({
                            type: type,
                            id: item.id,
                            name: type === 'service' ? (item.title_ar ?? 'خدمة') : (item.name_ar ?? 'منتج'),
                            quantity: 1,
                            unit_price: parseFloat(item.price ?? 0),
                            total_price: parseFloat(item.price ?? 0),
                            specialist_id: '',
                            specialist_name: ''
                        });
                    }
                    
                    this.calculateTotals();
                },
                
                removeItem(index) {
                    this.invoice.items.splice(index, 1);
                    this.calculateTotals();
                },
                
                updateQuantity(index, change) {
                    const newQuantity = this.invoice.items[index].quantity + change;
                    if (newQuantity > 0) {
                        this.invoice.items[index].quantity = newQuantity;
                        this.invoice.items[index].total_price = 
                            newQuantity * this.invoice.items[index].unit_price;
                        this.calculateTotals();
                    }
                },
                
                calculateTotals() {
                    this.invoice.subtotal = this.invoice.items.reduce((sum, item) => sum + item.total_price, 0);
                    
                    // Calculate discount
                    if (this.invoice.discount_type === 'percentage') {
                        this.invoice.discount_amount = (this.invoice.subtotal * this.invoice.discount_value) / 100;
                    } else if (this.invoice.discount_type === 'fixed') {
                        this.invoice.discount_amount = Math.min(this.invoice.discount_value, this.invoice.subtotal);
                    }
                    
                    const afterDiscount = this.invoice.subtotal - this.invoice.discount_amount;
                    
                    // Calculate tax
                    const taxRate = <?php echo $pos_settings['pos_tax_rate'] ?? 15; ?>;
                    const taxEnabled = <?php echo $pos_settings['pos_tax_enabled'] ?? 1; ?>;
                    
                    if (taxEnabled) {
                        this.invoice.tax_amount = (afterDiscount * taxRate) / 100;
                    } else {
                        this.invoice.tax_amount = 0;
                    }
                    
                    this.invoice.total_amount = afterDiscount + this.invoice.tax_amount;
                },
                
                clearInvoice() {
                    this.invoice = {
                        items: [],
                        customer_name: '',
                        customer_phone: '',
                        subtotal: 0,
                        discount_amount: 0,
                        discount_type: '',
                        discount_value: 0,
                        discount_code: '',
                        tax_amount: 0,
                        total_amount: 0
                    };
                },
                
                async validateDiscountCode(code) {
                    if (!code || code.trim() === '') {
                        alert('يرجى إدخال كود الخصم');
                        return false;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'validate_discount_code');
                        formData.append('code', code.toUpperCase());
                        formData.append('subtotal', this.invoice.subtotal);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.applyDiscount('code', result.discount.amount, code);
                            alert(result.message);
                            return true;
                        } else {
                            alert(result.message);
                            return false;
                        }
                    } catch (error) {
                        console.error('Discount validation error:', error);
                        alert('حدث خطأ في التحقق من كود الخصم');
                        return false;
                    }
                },
                
                applyDiscount(type, value, code = '') {
                    this.invoice.discount_type = type;
                    this.invoice.discount_value = value;
                    this.invoice.discount_code = code;
                    this.calculateTotals();
                    this.showDiscountModal = false;
                },
                
                async processPayment() {
                    try {
                        // Validate payment amounts for mixed payment
                        if (this.paymentMethod === 'mixed') {
                            const totalPaid = parseFloat(this.cashAmount || 0) + 
                                            parseFloat(this.cardAmount || 0) + 
                                            parseFloat(this.transferAmount || 0);
                            
                            if (Math.abs(totalPaid - this.invoice.total_amount) > 0.01) {
                                alert('مجموع المبالغ المدفوعة لا يساوي المبلغ المطلوب');
                                return;
                            }
                        }
                        
                        // Prepare payment data
                        const paymentData = {
                            method: this.paymentMethod,
                            cash_amount: this.paymentMethod === 'cash' ? this.invoice.total_amount : (this.cashAmount || 0),
                            card_amount: this.paymentMethod === 'card' ? this.invoice.total_amount : (this.cardAmount || 0),
                            transfer_amount: this.paymentMethod === 'transfer' ? this.invoice.total_amount : (this.transferAmount || 0)
                        };
                        
                        // Prepare invoice data
                        const invoiceData = {
                            ...this.invoice,
                            tax_rate: <?php echo $pos_settings['pos_tax_rate'] ?? 15; ?>
                        };
                        
                        // Send to backend
                        const formData = new FormData();
                        formData.append('action', 'process_invoice');
                        formData.append('invoice_data', JSON.stringify(invoiceData));
                        formData.append('payment_data', JSON.stringify(paymentData));
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Generate and print receipt
                            await this.generateReceipt(result.invoice_number, paymentData, result.invoice_id);
                            this.printReceipt();
                            
                            // Clear invoice
                            this.clearInvoice();
                            this.showPaymentModal = false;
                            
                            alert('تم إنشاء الفاتورة بنجاح وطباعة الإيصال');
                        } else {
                            alert('خطأ: ' + result.message);
                        }
                        
                    } catch (error) {
                        console.error('Payment error:', error);
                        alert('حدث خطأ أثناء معالجة الدفع');
                    }
                },
                
                async generateReceipt(invoiceNumber, paymentData, invoiceId) {
                    // Update receipt template with invoice data
                    document.getElementById('receipt-invoice-number').textContent = invoiceNumber;
                    document.getElementById('receipt-date').textContent = new Date().toLocaleDateString('ar-SA');
                    
                    // Customer info
                    const customerInfo = document.getElementById('receipt-customer-info');
                    if (this.invoice.customer_name || this.invoice.customer_phone) {
                        customerInfo.innerHTML = `
                            ${this.invoice.customer_name ? `<div class="flex justify-between"><span>العميل:</span><span>${this.invoice.customer_name}</span></div>` : ''}
                            ${this.invoice.customer_phone ? `<div class="flex justify-between"><span>الهاتف:</span><span>${this.invoice.customer_phone}</span></div>` : ''}
                        `;
                        customerInfo.style.display = 'block';
                    } else {
                        customerInfo.style.display = 'none';
                    }
                    
                    // Items
                    const itemsContainer = document.getElementById('receipt-items');
                    itemsContainer.innerHTML = this.invoice.items.map(item => `
                        <div class="flex justify-between py-1">
                            <div class="flex-1">
                                <div>${item.name}</div>
                                ${item.specialist_name ? `<div class="text-xs text-gray-600">المختص: ${item.specialist_name}</div>` : ''}
                            </div>
                            <div class="text-right">
                                <div>${item.quantity} × ${item.unit_price.toFixed(2)}</div>
                                <div class="font-medium">${item.total_price.toFixed(2)}</div>
                            </div>
                        </div>
                    `).join('');
                    
                    // Totals
                    document.getElementById('receipt-subtotal').textContent = this.invoice.subtotal.toFixed(2) + ' ر.س';
                    
                    if (this.invoice.discount_amount > 0) {
                        document.getElementById('receipt-discount').style.display = 'flex';
                        document.getElementById('receipt-discount-amount').textContent = '-' + this.invoice.discount_amount.toFixed(2) + ' ر.س';
                    } else {
                        document.getElementById('receipt-discount').style.display = 'none';
                    }
                    
                    if (this.invoice.tax_amount > 0) {
                        document.getElementById('receipt-tax').style.display = 'flex';
                        document.getElementById('receipt-tax-amount').textContent = this.invoice.tax_amount.toFixed(2) + ' ر.س';
                    } else {
                        document.getElementById('receipt-tax').style.display = 'none';
                    }
                    
                    document.getElementById('receipt-total').textContent = this.invoice.total_amount.toFixed(2) + ' ر.س';
                    
                    // Payment method
                    const paymentMethodText = {
                        'cash': 'نقدي',
                        'card': 'بطاقة',
                        'transfer': 'تحويل',
                        'mixed': 'مختلط'
                    };
                    document.getElementById('receipt-payment-method').textContent = paymentMethodText[paymentData.method];
                    
                    // Generate ZATCA QR Code if enabled
                    const zatcaEnabled = <?php echo $pos_settings['pos_zatca_enabled'] ?? '0'; ?>;
                    if (zatcaEnabled && invoiceId) {
                        const qrCode = await this.generateZATCAQR(invoiceId);
                        if (qrCode) {
                            const qrContainer = document.getElementById('receipt-qr-code');
                            if (qrContainer) {
                                qrContainer.innerHTML = `<div class="text-center mt-2"><small>QR Code: ${qrCode.substring(0, 20)}...</small></div>`;
                            }
                        }
                    }
                },
                
                printReceipt() {
                    const receiptElement = document.getElementById('receipt-template');
                    receiptElement.style.display = 'block';
                    
                    // Create a new window for printing
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html dir="rtl">
                        <head>
                            <meta charset="UTF-8">
                            <title>إيصال - صالون MAI</title>
                            <style>
                                body { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.2; margin: 0; padding: 5mm; }
                                .receipt { width: 80mm; }
                                .text-center { text-align: center; }
                                .font-bold { font-weight: bold; }
                                .text-lg { font-size: 16px; }
                                .text-sm { font-size: 10px; }
                                .text-xs { font-size: 9px; }
                                .mb-4 { margin-bottom: 16px; }
                                .mb-2 { margin-bottom: 8px; }
                                .py-2 { padding-top: 8px; padding-bottom: 8px; }
                                .py-1 { padding-top: 4px; padding-bottom: 4px; }
                                .pt-2 { padding-top: 8px; }
                                .pt-1 { padding-top: 4px; }
                                .mt-4 { margin-top: 16px; }
                                .mt-2 { margin-top: 8px; }
                                .mt-1 { margin-top: 4px; }
                                .border-t { border-top: 1px solid #000; }
                                .border-b { border-bottom: 1px solid #000; }
                                .flex { display: flex; }
                                .justify-between { justify-content: space-between; }
                                .text-right { text-align: right; }
                                .flex-1 { flex: 1; }
                                .text-gray-600 { color: #666; }
                            </style>
                        </head>
                        <body>
                            ${receiptElement.innerHTML}
                        </body>
                        </html>
                    `);
                    
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                    
                    receiptElement.style.display = 'none';
                },
                
                // Additional functions for modal operations
                calculateDiscountPreview(type, value, code) {
                    if (type === 'percentage') {
                        return (this.invoice.subtotal * value) / 100;
                    } else if (type === 'fixed') {
                        return Math.min(value, this.invoice.subtotal);
                    } else if (type === 'code' && code) {
                        return 0; // Placeholder - would validate against server
                    }
                    return 0;
                },
                
                async searchInvoices(searchTerm) {
                    if (!searchTerm || searchTerm.trim() === '') {
                        alert('يرجى إدخال كلمة البحث');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'search_invoices');
                        formData.append('search_term', searchTerm);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.searchResults = result.invoices;
                        } else {
                            alert('لم يتم العثور على فواتير');
                            this.searchResults = [];
                        }
                    } catch (error) {
                        console.error('Search error:', error);
                        alert('حدث خطأ في البحث');
                    }
                },
                
                async selectInvoiceForReturn(invoice) {
                    this.selectedInvoice = invoice;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'get_invoice_items');
                        formData.append('invoice_id', invoice.id);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.invoiceItems = result.items;
                        } else {
                            alert('خطأ في تحميل عناصر الفاتورة');
                        }
                    } catch (error) {
                        console.error('Invoice items error:', error);
                        alert('حدث خطأ في تحميل عناصر الفاتورة');
                    }
                },
                
                toggleReturnItem(item, checked) {
                    if (checked) {
                        this.returnItems.push(item);
                    } else {
                        const index = this.returnItems.findIndex(i => i.id === item.id);
                        if (index >= 0) {
                            this.returnItems.splice(index, 1);
                        }
                    }
                },
                
                async processReturn(invoiceId, returnItems, reason, refundMethod) {
                    if (!returnItems.length) {
                        alert('يرجى اختيار عناصر للإرجاع');
                        return;
                    }
                    
                    if (!reason.trim()) {
                        alert('يرجى إدخال سبب الإرجاع');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'process_return');
                        formData.append('invoice_id', invoiceId);
                        formData.append('return_items', JSON.stringify(returnItems));
                        formData.append('return_reason', reason);
                        formData.append('refund_method', refundMethod);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('تم معالجة المرتجع بنجاح');
                            this.showReturnsModal = false;
                            this.selectedInvoice = null;
                            this.returnItems = [];
                            this.searchResults = [];
                        } else {
                            alert('خطأ: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Return processing error:', error);
                        alert('حدث خطأ في معالجة المرتجع');
                    }
                },
                
                async closeShift(closingCash, notes) {
                    if (!closingCash || closingCash < 0) {
                        alert('يرجى إدخال الرصيد الفعلي');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'close_shift');
                        formData.append('closing_cash', closingCash);
                        formData.append('notes', notes);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('تم إغلاق الوردية بنجاح');
                            location.reload();
                        } else {
                            alert('خطأ: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Shift closing error:', error);
                        alert('حدث خطأ في إغلاق الوردية');
                    }
                },
                
                async generateZATCAQR(invoiceId) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'generate_zatca_qr');
                        formData.append('invoice_id', invoiceId);
                        
                        const response = await fetch('pos_actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            return result.qr_code;
                        }
                    } catch (error) {
                        console.error('ZATCA QR generation error:', error);
                    }
                    return null;
                }
            }
        }
    </script>
    
    <!-- TailwindCSS and Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom CSS for MAI branding -->
    <style>
        .mai-brown { color: #8B4513; }
        .mai-dark-brown { color: #654321; }
        .bg-mai-brown { background-color: #8B4513; }
        .bg-mai-dark-brown { background-color: #654321; }
        .border-mai-brown { border-color: #8B4513; }
        .focus\:ring-mai-brown:focus { --tw-ring-color: #8B4513; }
        .focus\:border-mai-brown:focus { border-color: #8B4513; }
        
        .print-only { display: none; }
        .no-print { display: block; }
        
        @media print {
            .print-only { display: block !important; }
            .no-print { display: none !important; }
        }
        
        [x-cloak] { display: none !important; }
        
        .receipt {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 80mm;
        }
    </style>
</div>

<?php
$page_content = ob_get_clean();
include 'admin/includes/admin_layout.php';
?>