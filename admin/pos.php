<?php
$logo_path = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo.png'; // Adjust this path if needed

$logo_data_uri = '';
if (file_exists($logo_path)) {
    $logo_content = file_get_contents($logo_path);
    $logo_mime_type = mime_content_type($logo_path);
    $logo_base64 = base64_encode($logo_content);
    $logo_data_uri = "data:{$logo_mime_type};base64,{$logo_base64}";
} else {
    // Optional: Log an error if the logo file is not found
    error_log("Logo file not found at: " . $logo_path);
}
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';
require_once 'pos_functions.php';

checkAdminAuth();

requirePermission('pos.view');

$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

// --- START: FIXED CODE FOR DISCOUNT POLICY ---
$discount_policy = [
    'max_discount_percent' => 0.0,
    'max_discount_amount' => 0.0 // Default to 0 if no policy is found
];
// Assuming getCurrentAdmin() returns an array with a 'role' key.
try {
    // This single query implements your exact logic:
    // It joins users -> roles -> role_discount_policies to get the correct limits.
    $policy_stmt = $db->prepare(
        "SELECT
            p.max_discount_percent,
            p.max_discount_amount
         FROM
            users u
         JOIN
            roles r ON u.role_id = r.id
         JOIN
            role_discount_policies p ON r.role_name = p.role_name
         WHERE
            u.id = ? AND p.is_active = 1"
    );
    $policy_stmt->execute([$admin['id']]);
    $policy_result = $policy_stmt->fetch(PDO::FETCH_ASSOC);

    if ($policy_result) {
        // If a policy is found for the user's role, use its values
        $discount_policy['max_discount_percent'] = floatval($policy_result['max_discount_percent']);
        $discount_policy['max_discount_amount'] = $policy_result['max_discount_amount'] !== null ? floatval($policy_result['max_discount_amount']) : null;
    }
    // If no policy is found, the defaults (0.0) will be used automatically.

} catch (PDOException $e) {
    // Log the error, but allow the page to load with the default 0% discount policy
    error_log("Discount policy fetch error for user ID {$admin['id']}: " . $e->getMessage());
}
// --- END: FIXED CODE FOR DISCOUNT POLICY ---


// --- Functions from previous steps ---
if (!function_exists('findOrCreateCustomerByName')) {
    function findOrCreateCustomerByName($db, $name)
    {
        // If name is empty, it's a guest order, return null.
        if (empty($name)) {
            return null;
        }

        // Check if a customer with this name already exists.
        // NOTE: This is not a reliable unique identifier.
        $stmt = $db->prepare("SELECT id FROM customers WHERE name_ar = ? OR name = ? LIMIT 1");
        $stmt->execute([$name, $name]);
        $customerId = $stmt->fetchColumn();

        if ($customerId) {
            return $customerId;
        } else {
            // Create a new customer if not found. Phone is omitted.
            $stmt = $db->prepare("INSERT INTO customers (name_ar, status, created_at) VALUES (?, 'active', NOW())");
            $stmt->execute([$name]);
            return $db->lastInsertId();
        }
    }
}
if (!function_exists('processLoyaltyPoints')) {
    function processLoyaltyPoints($db, $customerId, $totalAmount, $referenceId, $referenceType = 'invoice')
    {
        if (!$customerId || $totalAmount <= 0) {
            return;
        }
        try {
            $rules_stmt = $db->query("SELECT * FROM loyalty_rules ORDER BY id DESC LIMIT 1");
            $rules = $rules_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rules)
                return;
            $points_per_sar = $rules['points_per_sar'] ?? 1;
            $min_purchase_for_points = $rules['min_purchase_for_points'] ?? 0;
            if ($totalAmount < $min_purchase_for_points) {
                return;
            }
            $earned_points = floor($totalAmount * $points_per_sar);
            if ($earned_points <= 0) {
                return;
            }
            $trans_stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, points, transaction_type, earning_type, reference_id, reference_type, description_ar, created_at) VALUES (?, ?, 'earn', 'purchase', ?, ?, 'نقاط من عملية شراء', NOW())");
            $trans_stmt->execute([$customerId, $earned_points, $referenceId, $referenceType]);
            $wallet_stmt = $db->prepare("SELECT * FROM loyalty_wallets WHERE customer_id = ?");
            $wallet_stmt->execute([$customerId]);
            $wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
            if ($wallet) {
                $update_wallet_stmt = $db->prepare("UPDATE loyalty_wallets SET points = points + ?, lifetime_points = lifetime_points + ?, last_activity_at = NOW() WHERE customer_id = ?");
                $update_wallet_stmt->execute([$earned_points, $earned_points, $customerId]);
            } else {
                $create_wallet_stmt = $db->prepare("INSERT INTO loyalty_wallets (customer_id, points, lifetime_points, last_activity_at) VALUES (?, ?, ?, NOW())");
                $create_wallet_stmt->execute([$customerId, $earned_points, $earned_points]);
            }
            $current_lifetime_points = ($wallet['lifetime_points'] ?? 0) + $earned_points;
            $tiers_stmt = $db->query("SELECT id, min_points FROM loyalty_tiers ORDER BY min_points DESC");
            $tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);
            $new_tier_id = $wallet['tier_id'] ?? null;
            foreach ($tiers as $tier) {
                if ($current_lifetime_points >= $tier['min_points']) {
                    $new_tier_id = $tier['id'];
                    break;
                }
            }
            if ($new_tier_id && $new_tier_id != ($wallet['tier_id'] ?? null)) {
                $update_tier_stmt = $db->prepare("UPDATE loyalty_wallets SET tier_id = ? WHERE customer_id = ?");
                $update_tier_stmt->execute([$new_tier_id, $customerId]);
            }
        } catch (Exception $e) {
            error_log("Loyalty Points Error: " . $e->getMessage());
            throw $e;
        }
    }
}

$current_shift = null;
try {
    $shift_query = "SELECT * FROM shifts WHERE user_id = ? AND status = 'opened' ORDER BY opened_at DESC LIMIT 1";
    $shift_stmt = $db->prepare($shift_query);
    $shift_stmt->execute([$admin['id']]);
    $current_shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Shift query error: " . $e->getMessage());
}

$services = [];
$service_categories = [];
$products = [];
$specialists = [];
try {
    // Combined query for categories and their item counts
    $categories_stmt = $db->prepare("
        SELECT
            sc.id,
            sc.name_ar,
            sc.name_en,
            sc.sort_order,
            (SELECT COUNT(*) FROM services WHERE category_id = sc.id) +
            (SELECT COUNT(*) FROM products WHERE category_id = sc.id AND is_active = 1 AND (product_usage IS NULL OR product_usage != 'internal_use')) as item_count
        FROM service_categories sc
        WHERE sc.is_active = 1
        ORDER BY sc.sort_order ASC, sc.name_ar ASC
    ");
    $categories_stmt->execute();
    $service_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all services and products
    $services_stmt = $db->prepare("SELECT s.*, sc.name_en as category_name_en FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.is_active = 1");
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

    $products_stmt = $db->prepare("SELECT * FROM products WHERE is_active = 1 AND (product_usage IS NULL OR product_usage != 'internal_use') ORDER BY name_ar");
    $products_stmt->execute();
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine services and products into one list for easier filtering in JS
    $allItems = [];
    foreach ($services as $service) {
        $allItems[] = array_merge($service, ['item_type' => 'service']);
    }
    foreach ($products as $product) {
        $allItems[] = array_merge($product, ['item_type' => 'product']);
    }


    $specialists_stmt = $db->prepare("SELECT id, name_ar, name_en FROM team_members WHERE is_active = 1 AND is_specialist = 1 ORDER BY name_ar");
    $specialists_stmt->execute();
    $specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Data fetching error: " . $e->getMessage());
}

$specialist_service_map = [];
try {
    $map_stmt = $db->query("SELECT service_id, specialist_id FROM specialist_services");
    $links = $map_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($links as $link) {
        $service_id = $link['service_id'];
        $specialist_id = $link['specialist_id'];
        if (!isset($specialist_service_map[$service_id])) {
            $specialist_service_map[$service_id] = [];
        }
        $specialist_service_map[$service_id][] = $specialist_id;
    }
} catch (PDOException $e) {
    error_log("Specialist-Service map fetching error: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_shift'])) {
    if (hasPermission('pos.manage_shifts')) {
        try {
            $opening_cash = floatval($_POST['opening_cash'] ?? 0);
            $insert_shift = $db->prepare("INSERT INTO shifts (user_id, opened_at, opening_cash, status) VALUES (?, NOW(), ?, 'opened')");
            $insert_shift->execute([$admin['id'], $opening_cash]);
            header('Location: pos.php?shift_opened=1');
            exit();
        } catch (PDOException $e) {
            $error_message = 'خطأ في فتح الوردية: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'ليس لديك الصلاحية لفتح وردية.';
        header('Location: pos.php');
        exit();
    }
}

// --- START: MODIFIED SETTINGS LOGIC ---
// This function gets settings from the new `settings` table as defined in pos_settings.php
function get_pos_settings($db) {
    $settings = [];
    $defaults = [
        'pos_default_payment_method' => 'cash' // Default if not in DB
    ];
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'settings'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings WHERE `key` LIKE 'pos_%'");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['value'];
                switch ($row['type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    // Add other types if necessary
                }
                $settings[$row['key']] = $value;
            }
        }
    } catch (Exception $e) {
        error_log("Could not fetch from 'settings' table: " . $e->getMessage());
    }
    return array_merge($defaults, $settings);
}

// Fetch settings from the new `settings` table
$pos_settings = get_pos_settings($db);

// Continue to fetch legacy/other settings from `site_settings` to not break other functionalities
$legacy_settings = [];
$tax_enabled = false;
try {
    $legacy_query = "SELECT setting_key, setting_value, status FROM site_settings WHERE setting_key IN ('tax_rate', 'tax_number', 'pos_receipt_header', 'pos_receipt_footer')";
    $legacy_stmt = $db->prepare($legacy_query);
    $legacy_stmt->execute();
    $legacy_raw = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($legacy_raw as $setting) {
        $legacy_settings[$setting['setting_key']] = $setting['setting_value'];
        if ($setting['setting_key'] === 'tax_rate' && $setting['status'] === 'active') {
            $tax_enabled = true;
        }
    }
} catch (PDOException $e) {
    error_log("Legacy Settings (site_settings) Error: " . $e->getMessage());
}
$legacy_settings['tax_rate'] = $legacy_settings['tax_rate'] ?? 15;

// --- END: MODIFIED SETTINGS LOGIC ---


$page_title = 'نقطة البيع';
$admin = ['id' => $_SESSION['admin_id'] ?? 1, 'username' => $_SESSION['admin_username'] ?? 'admin'];
ob_start();
?>

<!-- Main POS Container -->
<div x-data="posSystem()" x-init="init()" class="h-screen bg-background flex text-text-dark overflow-hidden" :dir="currentLanguage === 'ar' ? 'rtl' : 'ltr'">

    <!-- Left Panel: Categories -->
    <aside class="w-56 flex-shrink-0 bg-white p-4 flex flex-col no-print border-r border-gray-100">
        <h2 class="text-xl font-extrabold text-secondary mb-4" x-text="t('categories')"></h2>
        <div class="flex-grow overflow-y-auto custom-scrollbar -mr-2 pr-2">
            <div class="flex flex-col gap-2">
                 <button @click="selectedCategory = null" :class="{ 'bg-primary-light text-primary-dark font-bold': selectedCategory === null, 'text-gray-600 hover:bg-gray-50': selectedCategory !== null }" class="w-full text-start flex justify-between items-center px-3 py-2 text-sm rounded-lg transition-all duration-200">
                    <span x-text="t('all')"></span>
                </button>
                <?php foreach ($service_categories as $category): ?>
                    <button @click="selectedCategory = <?php echo $category['id']; ?>" :class="{ 'bg-primary-light text-primary-dark font-bold': selectedCategory == <?php echo $category['id']; ?>, 'text-gray-600 hover:bg-gray-50': selectedCategory != <?php echo $category['id']; ?> }" class="w-full text-start flex justify-between items-center px-3 py-2 text-sm rounded-lg transition-all duration-200">
                        <span class="truncate" x-text="currentLanguage === 'en' && '<?php echo htmlspecialchars($category['name_en'] ?? ''); ?>' ? '<?php echo htmlspecialchars($category['name_en']); ?>' : '<?php echo htmlspecialchars($category['name_ar']); ?>'"></span>
                        <span class="text-xs ml-1 bg-gray-200 rounded-full px-2 py-0.5 text-text-dark" x-text="<?php echo $category['item_count']; ?>"></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <!-- Middle Panel: Items Grid -->
    <main class="flex-grow p-4 flex flex-col">
        <!-- Header -->
        <header class="flex justify-between items-center mb-4 no-print flex-shrink-0">
            <div>
                <p class="text-sm text-accent"><?php echo date('l, d F Y'); ?></p>
            </div>
            <div class="flex items-center gap-3">
                 <!-- Language Switcher -->
             <div class="flex items-center gap-1 bg-white p-1 rounded-xl shadow-sm border border-gray-100">
    <button 
        @click="currentLanguage = 'ar'" 
        :class="{ 'bg-green-400 text-white shadow-md': currentLanguage === 'ar', 'text-gray-700': currentLanguage !== 'ar' }" 
        class="px-3 py-1.5 text-sm font-bold rounded-lg transition-all duration-200 ease-in-out">
        عربي
    </button>
    <button 
        @click="currentLanguage = 'en'" 
        :class="{ 'bg-green-400 text-white shadow-md': currentLanguage === 'en', 'text-gray-700': currentLanguage !== 'en' }" 
        class="px-3 py-1.5 text-sm font-bold rounded-lg transition-all duration-200 ease-in-out">
        EN
    </button>
</div>

                 <!-- Shift Management -->
                 <?php if (hasPermission('pos.manage_shifts')): ?>
                    <?php if (!$current_shift): ?>
                        <button @click="openShiftModal = true" class="flex items-center px-4 py-2 rounded-xl bg-primary text-white font-semibold hover:bg-primary-dark transition-colors shadow-md">
                            <i class="fas fa-door-open" :class="currentLanguage === 'ar' ? 'ml-2' : 'mr-2'"></i>
                            <span x-text="t('openShift')"></span>
                        </button>
                    <?php else: ?>
                        <button @click="openCloseShiftModal()" class="flex items-center px-4 py-2 rounded-xl border border-secondary text-secondary font-semibold hover:bg-secondary hover:text-white transition-colors shadow-sm">
                            <i class="fas fa-door-closed" :class="currentLanguage === 'ar' ? 'ml-2' : 'mr-2'"></i>
                            <span x-text="t('closeShift')"></span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <!-- Search -->
        <div class="mb-4 no-print flex-shrink-0">
            <div class="relative">
                <i class="fas fa-search absolute top-1/2 -translate-y-1/2 text-gray-400" :class="currentLanguage === 'ar' ? 'right-4' : 'left-4'"></i>
                <input type="text" x-model.debounce.300ms="searchQuery" :placeholder="t('searchPlaceholder')" class="w-full bg-white border border-gray-200 rounded-xl py-3 pl-12 pr-4 focus:ring-primary focus:border-primary shadow-sm transition-all duration-200 text-text-dark">
            </div>
        </div>

        <!-- Items Grid -->
        <div class="flex-grow overflow-y-auto pr-2 -mr-2 custom-scrollbar">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                <template x-for="item in filteredItems" :key="item.item_type + '-' + item.id">
                     <div @click="addItem(item)" class="group bg-white rounded-xl shadow-sm hover:shadow-lg hover:border-primary border border-transparent p-3 flex flex-col justify-center items-center text-center cursor-pointer transition-all duration-200 h-28">
                        <h4 class="font-bold text-sm text-secondary leading-tight mb-1" x-text="getItemName(item)"></h4>
                        <p class="text-primary font-extrabold text-base" x-text="getItemPrice(item)"></p>
                    </div>
                </template>
                <template x-if="filteredItems.length === 0">
                    <div class="col-span-full text-center text-gray-400 py-10">
                        <i class="fas fa-box-open fa-3x mb-3"></i>
                        <p x-text="t('noItemsFound')"></p>
                    </div>
                </template>
            </div>
        </div>
    </main>

    <!-- Right Panel: Order / Invoice -->
    <aside class="w-[340px] flex-shrink-0 bg-white shadow-2xl flex flex-col p-4 border-l border-gray-100">
        <!-- Header -->
        <div class="flex justify-between items-center mb-4 flex-shrink-0">
             <h2 class="text-xl font-bold text-secondary" x-text="t('currentInvoice')"></h2>
             <button @click="clearInvoice()" :title="t('newInvoiceTitle')" class="text-gray-500 hover:text-red-500 transition-colors text-xl">
                 <i class="fas fa-sync-alt"></i>
            </button>
        </div>

        <!-- Customer -->
        <div class="relative mb-4 flex-shrink-0" @click.away="customerSearchResults = []">
            <input type="text" x-model="invoice.customer_name" :placeholder="t('customerNamePlaceholder')" @input.debounce.300ms="searchCustomers()" @focus="searchCustomers()" class="w-full py-2.5 pl-4 pr-4 border border-gray-200 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm transition-all duration-200 text-text-dark">
             <div x-cloak x-show="isSearchingCustomers || customerSearchResults.length > 0" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute top-full left-0 w-full bg-white border border-gray-300 rounded-xl mt-1 shadow-lg max-h-48 overflow-y-auto z-20">
                <template x-for="customer in customerSearchResults" :key="customer.id">
                    <div @click="selectCustomer(customer)" class="px-4 py-2 cursor-pointer hover:bg-primary-light transition-colors">
                        <p class="font-semibold text-text-dark text-sm" x-text="customer.name_ar || customer.name"></p>
                    </div>
                </template>
                 <div x-show="!isSearchingCustomers && customerSearchResults.length === 0 && invoice.customer_name.trim()" class="px-4 py-3 text-gray-500 italic" x-text="t('noCustomersFound')"></div>
            </div>
        </div>

        <!-- Order List -->
        <div class="flex-grow overflow-y-auto -mr-2 pr-2 border-t border-b border-gray-100 py-3 custom-scrollbar">
            <div class="space-y-2">
                <template x-for="(item, index) in invoice.items" :key="index">
                     <div class="flex items-center gap-2 bg-background p-1.5 rounded-lg">
                        <div class="flex-grow">
                             <p class="font-semibold text-sm text-text-dark truncate" x-text="item.name"></p>
                             <p class="text-xs text-accent" x-text="formatCurrency(item.unit_price)"></p>
                        </div>
                        <div class="flex items-center border border-gray-200 rounded-lg bg-white flex-shrink-0">
                            <button @click="updateQuantity(index, -1)" class="w-6 h-6 text-base flex items-center justify-center text-text-dark hover:bg-gray-100 rounded-l-lg transition-colors">-</button>
                            <span class="text-sm px-2 font-medium text-text-dark" x-text="item.quantity"></span>
                            <button @click="updateQuantity(index, 1)" class="w-6 h-6 text-base flex items-center justify-center text-text-dark hover:bg-gray-100 rounded-r-lg transition-colors">+</button>
                        </div>
                        <p class="font-bold w-20 text-right text-secondary text-sm flex-shrink-0" x-text="formatCurrency(Number(item.total_price || 0))"></p>
                    </div>
                </template>
                 <div x-show="invoice.items.length === 0" class="text-center text-gray-400 py-16">
                    <i class="fas fa-shopping-basket fa-2x mb-2"></i>
                    <p class="text-sm" x-text="t('cartEmpty')"></p>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="pt-2 flex-shrink-0">
            <div class="space-y-1 text-sm mb-2">
                <div class="flex justify-between items-center"><span class="text-accent" x-text="t('subtotal')"></span><span class="font-medium text-text-dark" x-text="formatCurrency(invoice.subtotal)"></span></div>
                <div class="flex justify-between items-center" x-show="isTaxEnabled"><span class="text-accent"><span x-text="t('tax')"></span> (<?php echo floatval($legacy_settings['tax_rate'] ?? 15); ?>%)</span><span class="font-medium text-text-dark" x-text="formatCurrency(invoice.tax_amount)"></span></div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center"><span class="text-accent" x-text="t('discount')"></span>
                        <?php if (hasPermission('pos.manage_discounts')): ?>
                            <button @click="showDiscountModal = true" class="text-primary hover:opacity-80 transition-colors text-md" :class="currentLanguage === 'ar' ? 'mr-2' : 'ml-2'"><i class="fas fa-tag"></i></button>
                        <?php endif; ?>
                    </div>
                    <span class="font-medium text-red-500" x-text="'-' + formatCurrency(invoice.discount_amount)"></span>
                </div>
            </div>
            <div class="flex justify-between items-center text-xl font-extrabold border-t-2 border-dashed border-accent pt-2 text-secondary">
                <span x-text="t('total')"></span>
                <span x-text="formatCurrency(invoice.total_amount)"></span>
            </div>
      <button 
    @click="openPaymentModal()" 
    :disabled="invoice.items.length === 0 || !<?php echo $current_shift ? 'true' : 'false'; ?> || !<?php echo hasPermission('pos.create_invoice') ? 'true' : 'false'; ?>" 
    class="w-full mt-3 bg-green-400 text-white py-3 rounded-xl font-bold text-base hover:bg-green-400 disabled:bg-gray-400 disabled:cursor-not-allowed transition-all duration-200 shadow-lg shadow-green-400/30">
    <span x-text="t('placeOrder')"></span> (<span x-text="formatCurrency(invoice.total_amount)"></span>)
</button>

        </div>
    </aside>

    <!-- Hidden iframe for silent printing -->
    <iframe id="print-frame" class="print-only" style="display: none;"></iframe>

    <!-- MODALS -->
    <?php #region Modals ?>
    <div>
        <!-- Payment Modal -->
        <div x-cloak x-show="showPaymentModal" @keydown.escape.window="showPaymentModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div @click.away="showPaymentModal = false" class="bg-white w-full max-w-md rounded-2xl shadow-2xl animate-fade-in-up">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-secondary" x-text="t('completePayment')"></h3><button
                        @click="showPaymentModal = false" class="text-gray-500 hover:text-gray-700 text-2xl transition-colors"><i
                            class="fas fa-times"></i></button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="text-center mb-3">
                        <p class="text-accent text-md" x-text="t('totalAmountDue')"></p>
                        <p class="text-4xl font-black text-primary mt-1" x-text="formatCurrency(invoice.total_amount)"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-dark mb-2" x-text="t('paymentMethod')"></label>
                        <select x-model="paymentMethod" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm transition-all text-text-dark">
                            <option value="cash" x-text="t('cash')"></option>
                            <option value="card" x-text="t('card')"></option>
                            <option value="bank_transfer" x-text="t('bankTransfer')"></option>
                            <option value="mixed" x-text="t('mixed')"></option>
                        </select>
                    </div>
                    <div x-show="paymentMethod === 'mixed'" class="border-t border-gray-100 pt-4 space-y-3">
                        <h4 class="font-semibold text-lg text-secondary" x-text="t('splitAmount')"></h4>
                        <div>
                            <label class="block text-sm text-text-dark mb-1" x-text="t('cashAmount')"></label>
                            <input type="number" step="0.01" placeholder="0.00" x-model.number="cashAmount"
                                @input="autoCalculateMixedPayment('cash')"
                                class="w-full mt-1 px-4 py-2.5 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark">
                        </div>
                        <div>
                            <label class="block text-sm text-text-dark mb-1" x-text="t('cardAmount')"></label>
                            <input type="number" step="0.01" placeholder="0.00" x-model.number="cardAmount"
                                @input="autoCalculateMixedPayment('card')"
                                class="w-full mt-1 px-4 py-2.5 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark">
                        </div>
                    </div>
                    <div class="pt-3">
                     <button @click="processPayment()"
        class="w-full bg-green-400 text-white py-3 rounded-xl font-bold hover:bg-green-500 text-lg transition-colors duration-200 shadow-lg shadow-green-400/30">
        <i class="fas fa-check-circle" :class="currentLanguage === 'ar' ? 'ml-2' : 'mr-2'"></i>
        <span x-text="t('confirmPayment')"></span>
</button>

                    </div>
                </div>
            </div>
        </div>

        <!-- Discount Modal -->
        <div x-cloak x-show="showDiscountModal" @keydown.escape.window="showDiscountModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div @click.away="showDiscountModal = false" class="bg-white w-full max-w-sm rounded-2xl shadow-2xl animate-fade-in-up">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-secondary" x-text="t('applyDiscount')"></h3>
                    <button @click="showDiscountModal = false" class="text-gray-500 hover:text-gray-700 text-2xl transition-colors"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-5">
                    <div class="flex bg-background p-1 rounded-xl mb-5">
                        <button @click="discountTab = 'fixed'"
                            :class="{ 'bg-primary text-white shadow-md': discountTab === 'fixed', 'text-text-dark': discountTab !== 'fixed' }"
                            class="flex-1 py-2 text-sm font-semibold rounded-lg transition-all duration-200" x-text="t('fixedAmount')"></button>
                        <button @click="discountTab = 'percentage'"
                            :class="{ 'bg-primary text-white shadow-md': discountTab === 'percentage', 'text-text-dark': discountTab !== 'percentage' }"
                            class="flex-1 py-2 text-sm font-semibold rounded-lg transition-all duration-200" x-text="t('percentage')"></button>
                    </div>
                    <div x-show="discountTab === 'fixed'" class="space-y-4">
                        <label class="block text-sm font-medium text-text-dark" x-text="t('enterFixedDiscount')"></label>
                        <input type="number" step="0.01"
                            x-model.number="discountValueInput" placeholder="0.00"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark">
                        <button @click="applyManualDiscount('fixed')"
                            class="w-full bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary-dark transition-colors duration-200 shadow-md shadow-primary/30"
                            x-text="t('apply')"></button>
                    </div>
                    <div x-show="discountTab === 'percentage'" class="space-y-4">
                        <label class="block text-sm font-medium text-text-dark" x-text="t('enterPercentageDiscount')"></label>
                        <input type="number" x-model.number="discountValueInput" placeholder="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark">
                        <button @click="applyManualDiscount('percentage')"
                            class="w-full bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary-dark transition-colors duration-200 shadow-md shadow-primary/30"
                            x-text="t('apply')"></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Popup -->
        <div x-cloak x-show="showSuccessPopup" @keydown.escape.window="showSuccessPopup = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div @click.away="showSuccessPopup = false"
                class="bg-white w-full max-w-sm rounded-2xl shadow-2xl text-center p-8 animate-fade-in-up">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-primary-light mb-5 scale-animation">
                    <i class="fas fa-check fa-2x text-primary"></i>
                </div>
                <h3 class="text-xl font-bold text-secondary mb-2" x-text="successPopupData.message"></h3>
                <p class="text-accent text-md mb-5">
                    <span x-text="t('invoiceNumber')"></span>:
                    <span class="font-semibold text-primary-dark" x-text="successPopupData.invoiceNumber"></span>
                </p>
                <button @click="showSuccessPopup = false; clearInvoice();"
                    class="w-full mt-2 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary-dark transition-colors duration-200 shadow-lg shadow-primary/30">
                    <i class="fas fa-plus" :class="currentLanguage === 'ar' ? 'ml-2' : 'mr-2'"></i>
                    <span x-text="t('newInvoice')"></span>
                </button>
            </div>
        </div>

        <!-- Shift Open Modal -->
        <div x-cloak x-show="openShiftModal" @keydown.escape.window="openShiftModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div @click.away="openShiftModal = false" class="bg-white w-full max-w-md rounded-2xl shadow-2xl animate-fade-in-up">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-secondary" x-text="t('openShift')"></h3>
                    <button @click="openShiftModal = false" class="text-gray-500 hover:text-gray-700 text-2xl transition-colors"><i class="fas fa-times"></i></button>
                </div>
                <form action="pos.php" method="POST" class="p-5 space-y-4">
                    <input type="hidden" name="open_shift" value="1">
                    <div>
                        <label for="opening_cash" class="block text-sm font-medium text-text-dark mb-2" x-text="t('openingCash')"></label>
                        <input type="number" step="0.01" id="opening_cash" name="opening_cash"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark"
                            placeholder="0.00" value="0.00">
                    </div>
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary-dark transition-colors duration-200 shadow-lg shadow-primary/30">
                        <i class="fas fa-door-open" :class="currentLanguage === 'ar' ? 'ml-2' : 'mr-2'"></i>
                        <span x-text="t('confirmOpenShift')"></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Shift Close Modal -->
        <div x-cloak x-show="closeShiftModal" @keydown.escape.window="closeShiftModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div @click.away="closeShiftModal = false" class="bg-white w-full max-w-md rounded-2xl shadow-2xl animate-fade-in-up">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-secondary" x-text="t('closeShift')"></h3>
                    <button @click="closeShiftModal = false" class="text-gray-500 hover:text-gray-700 text-2xl transition-colors"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-md text-text-dark" x-text="t('confirmCloseShiftText')"></p>
                    <div class="bg-background p-3 rounded-xl space-y-2 text-sm">
                        <div class="flex justify-between items-center text-text-dark">
                            <span x-text="t('openingCash') + ':'"></span>
                            <span class="font-semibold" x-text="formatCurrency(currentShift.opening_cash || 0)"></span>
                        </div>
                        <div class="flex justify-between items-center text-text-dark">
                            <span x-text="t('totalSales') + ':'"></span>
                            <span class="font-semibold" x-text="formatCurrency(currentShift.total_sales || 0)"></span>
                        </div>
                        <div class="flex justify-between items-center text-text-dark">
                            <span x-text="t('totalCashSales') + ':'"></span>
                            <span class="font-semibold" x-text="formatCurrency(currentShift.cash_sales || 0)"></span>
                        </div>
                        <div class="flex justify-between items-center text-text-dark">
                            <span x-text="t('totalCardSales') + ':'"></span>
                            <span class="font-semibold" x-text="formatCurrency(currentShift.card_sales || 0)"></span>
                        </div>
                        <div class="flex justify-between items-center text-text-dark">
                            <span x-text="t('totalBankTransferSales') + ':'"></span>
                            <span class="font-semibold" x-text="formatCurrency(currentShift.bank_transfer_sales || 0)"></span>
                        </div>
                        <div class="flex justify-between items-center text-secondary font-bold border-t-2 border-dashed border-accent pt-2 mt-2">
                            <span x-text="t('expectedCash') + ':'"></span>
                            <span x-text="formatCurrency(parseFloat(currentShift.opening_cash || 0) + parseFloat(currentShift.cash_sales || 0) - parseFloat(currentShift.cash_payouts || 0))"></span>
                        </div>
                    </div>
                    <form @submit.prevent="closeShift" class="space-y-4">
                        <div>
                            <label for="closing_cash" class="block text-sm font-medium text-text-dark mb-2" x-text="t('closingCash')"></label>
                            <input type="number" step="0.01" id="closing_cash" x-model.number="closingCash"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark"
                                placeholder="0.00" required>
                        </div>
                        <div>
                            <label for="cash_payouts" class="block text-sm font-medium text-text-dark mb-2" x-text="t('cashPayouts')"></label>
                            <input type="number" step="0.01" id="cash_payouts" x-model.number="cashPayouts"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-background focus:ring-primary focus:border-primary shadow-sm text-text-dark"
                                placeholder="0.00">
                        </div>
                        <button type="submit" class="w-full bg-secondary text-white py-3 rounded-xl font-bold hover:bg-opacity-90 transition-colors duration-200 shadow-lg">
                            <i class="fas fa-door-closed" :class="currentLanguage === 'ar' ? 'ml-2' : 'mr-2'"></i>
                            <span x-text="t('confirmCloseShift')"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php #endregion ?>
</div>


<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('posSystem', () => ({
            logoUrl: '<?php echo $logo_data_uri; ?>', 

            currentLanguage: 'ar',
            translations: {
                 ar: {
                    posTitle: 'نقطة البيع', posSubtitle: 'إدارة الطلبات', openShift: 'فتح وردية', closeShift: 'إغلاق الوردية',
                    searchPlaceholder: 'ابحث عن منتج أو خدمة...', services: 'الخدمات', products: 'المنتجات', categories: 'الفئات', all: 'الكل',
                    currentInvoice: 'الطلب الحالي', newInvoiceTitle: 'طلب جديد',
                    customerNamePlaceholder: 'اسم العميل (اختياري)',
                    searching: 'جاري البحث...', noCustomersFound: 'لم يتم العثور على عملاء.', noItemsFound: 'لم يتم العثور على عناصر.',
                    cartEmpty: 'سلة الطلبات فارغة',
                    subtotal: 'المجموع الفرعي', tax: 'الضريبة', discount: 'الخصم', total: 'الإجمالي',
                    placeOrder: 'تأكيد الطلب',
                    completePayment: 'إتمام الدفع', totalAmountDue: 'المبلغ الإجمالي', paymentMethod: 'طريقة الدفع',
                    cash: 'نقدي', card: 'بطاقة', bankTransfer: 'تحويل', mixed: 'مختلط',
                    splitAmount: 'تقسيم المبلغ', cashAmount: 'المبلغ النقدي', cardAmount: 'المبلغ بالبطاقة',
                    confirmPayment: 'تأكيد الدفع', applyDiscount: 'تطبيق الخصم', fixedAmount: 'مبلغ ثابت', percentage: 'نسبة',
                    enterFixedDiscount: 'أدخل مبلغ الخصم', apply: 'تطبيق', enterPercentageDiscount: 'أدخل نسبة الخصم',
                    invoiceNumber: 'رقم الفاتورة', newInvoice: 'طلب جديد',
                    successInvoiceCreated: 'تم إنشاء الطلب بنجاح',
                    alertProductOutOfStock: 'هذا المنتج غير متوفر.',
                    alertInsufficientStock: 'الكمية المطلوبة غير متوفرة.',
                    dineIn: 'محلي', takeAway: 'سفري', delivery: 'توصيل',
                    openingCash: 'رأس مال الوردية', closingCash: 'نقدية الإغلاق', cashPayouts: 'مصروفات نقدية',
                    confirmOpenShift: 'تأكيد فتح الوردية', confirmCloseShift: 'تأكيد إغلاق الوردية',
                    confirmCloseShiftText: 'هل أنت متأكد من إغلاق الوردية الحالية؟',
                    totalSales: 'إجمالي المبيعات', totalCashSales: 'مبيعات نقدية', totalCardSales: 'مبيعات بطاقة',
                    totalBankTransferSales: 'مبيعات تحويل بنكي', expectedCash: 'النقدية المتوقعة',
                },
                en: {
                    posTitle: 'Point of Sale', posSubtitle: 'Order Management', openShift: 'Open Shift', closeShift: 'Close Shift',
                    searchPlaceholder: 'Search for product or service...', services: 'Services', products: 'Products', categories: 'Categories', all: 'All',
                    currentInvoice: 'Current Order', newInvoiceTitle: 'New Order',
                    customerNamePlaceholder: 'Customer Name (Optional)',
                    searching: 'Searching...', noCustomersFound: 'No customers found.', noItemsFound: 'No items found.',
                    cartEmpty: 'Order cart is empty',
                    subtotal: 'Subtotal', tax: 'Tax', discount: 'Discount', total: 'Total',
                    placeOrder: 'Place Order',
                    completePayment: 'Complete Payment', totalAmountDue: 'Total Amount Due', paymentMethod: 'Payment Method',
                    cash: 'Cash', card: 'Card', bankTransfer: 'Transfer', mixed: 'Mixed',
                    splitAmount: 'Split Amount', cashAmount: 'Cash Amount', cardAmount: 'Card Amount',
                    confirmPayment: 'Confirm Payment', applyDiscount: 'Apply Discount', fixedAmount: 'Fixed Amount', percentage: 'Percentage',
                    enterFixedDiscount: 'Enter discount amount', apply: 'Apply', enterPercentageDiscount: 'Enter discount percentage',
                    invoiceNumber: 'Invoice Number', newInvoice: 'New Order',
                    successInvoiceCreated: 'Order created successfully',
                    alertProductOutOfStock: 'This product is out of stock.',
                    alertInsufficientStock: 'Requested quantity is not available.',
                    dineIn: 'Dine In', takeAway: 'Take Away', delivery: 'Delivery',
                    openingCash: 'Opening Cash', closingCash: 'Closing Cash', cashPayouts: 'Cash Payouts',
                    confirmOpenShift: 'Confirm Open Shift', confirmCloseShift: 'Confirm Close Shift',
                    confirmCloseShiftText: 'Are you sure you want to close the current shift?',
                    totalSales: 'Total Sales', totalCashSales: 'Cash Sales', totalCardSales: 'Card Sales',
                    totalBankTransferSales: 'Bank Transfer Sales', expectedCash: 'Expected Cash',
                }
            },
            t(key) { return this.translations[this.currentLanguage][key] || key; },
            formatCurrency(amount) { const currency = this.currentLanguage === 'ar' ? 'ج.م' : 'EGP'; const value = Number(amount || 0).toFixed(2); return this.currentLanguage === 'ar' ? `${value} ${currency}` : `${currency} ${value}`; },
            getItemName(item) {
                if (!item) return '';
                if (this.currentLanguage === 'en' && item.name_en) return item.name_en;
                return item.name_ar || item.title_ar || 'Item';
            },
            getItemPrice(item) {
                if (!item) return 'N/A';
                let price = 0;
                if (item.item_type === 'service') {
                    price = (item.offer_price && Number(item.offer_price) > 0) ? item.offer_price : item.price;
                } else {
                    price = item.selling_price;
                }
                return this.formatCurrency(price);
            },

            // STATE
            invoice: { items: [], customer_id: null, customer_name: '', subtotal: 0, discount_amount: 0, discount_type: null, discount_value: 0, tax_amount: 0, total_amount: 0 },
            showSuccessPopup: false, successPopupData: { message: '', invoiceNumber: '' }, lastSuccessfulInvoice: null,
            customerSearchResults: [], isSearchingCustomers: false,
            showPaymentModal: false, showDiscountModal: false, openShiftModal: false, closeShiftModal: false,
            currentShift: <?php echo json_encode($current_shift); ?>,
            paymentMethod: '<?php echo htmlspecialchars($pos_settings['pos_default_payment_method'] ?? 'cash'); ?>',
            cashAmount: 0, cardAmount: 0,
            discountTab: 'fixed', discountValueInput: 0,
            isTaxEnabled: <?php echo $tax_enabled ? 'true' : 'false'; ?>,
            allItems: <?php echo json_encode($allItems); ?>,
            allSpecialists: <?php echo json_encode($specialists); ?>,
            searchQuery: '',
            selectedCategory: null,
            orderType: 'dine_in', // Defaulted to dine_in as other options are removed from UI
            roleDiscountPolicy: <?php echo json_encode($discount_policy); ?>,
            closingCash: 0,
            cashPayouts: 0,

            // COMPUTED PROPERTIES
            get filteredItems() {
                const search = this.searchQuery.toLowerCase();
                return this.allItems.filter(item => {
                    const inCategory = this.selectedCategory === null || item.category_id == this.selectedCategory;
                    const nameAr = (item.name_ar || item.title_ar || '').toLowerCase();
                    const nameEn = (item.name_en || '').toLowerCase();
                    const matchesSearch = !search || nameAr.includes(search) || nameEn.includes(search);
                    return inCategory && matchesSearch;
                });
            },

            // METHODS
            init() { this.calculateTotals(); },
            openPaymentModal() {
                this.cashAmount = this.invoice.total_amount;
                this.cardAmount = 0;
                this.paymentMethod = '<?php echo htmlspecialchars($pos_settings['pos_default_payment_method'] ?? 'cash'); ?>';
                this.showPaymentModal = true;
            },
            
            // --- EDITED: TAX CALCULATION LOGIC ---
            calculateTotals() {
                const taxRate = <?php echo floatval($legacy_settings['tax_rate'] ?? 15); ?> / 100;

                // 1. Calculate the subtotal (sum of all item prices without tax)
                const subtotalFromItems = this.invoice.items.reduce((acc, item) => {
                    item.total_price = item.quantity * item.unit_price;
                    return acc + item.total_price;
                }, 0);
                this.invoice.subtotal = subtotalFromItems;

                // 2. Calculate tax based on the new subtotal
                let taxAmount = 0;
                if (this.isTaxEnabled) {
                    // Tax is added ON TOP of the subtotal
                    taxAmount = this.invoice.subtotal * taxRate;
                }
                this.invoice.tax_amount = taxAmount;

                // 3. Calculate discount (based on subtotal, which is correct)
                let discountAmount = 0;
                if (this.invoice.discount_type === 'percentage') {
                    discountAmount = (this.invoice.subtotal * this.invoice.discount_value) / 100;
                } else if (this.invoice.discount_type === 'fixed') {
                    discountAmount = parseFloat(this.invoice.discount_value) || 0;
                }

                // Apply max discount policy if applicable
                const maxPercent = this.roleDiscountPolicy.max_discount_percent;
                const maxAmount = this.roleDiscountPolicy.max_discount_amount;

                if (maxPercent > 0 && discountAmount > (this.invoice.subtotal * maxPercent / 100)) {
                    discountAmount = this.invoice.subtotal * maxPercent / 100;
                }
                if (maxAmount !== null && discountAmount > maxAmount) {
                    discountAmount = maxAmount;
                }
                this.invoice.discount_amount = discountAmount;

                // 4. Calculate final total: Subtotal + Tax - Discount
                let totalAmount = this.invoice.subtotal + this.invoice.tax_amount - this.invoice.discount_amount;
                this.invoice.total_amount = Math.max(0, totalAmount);
            },
            // --- END OF EDITED TAX LOGIC ---

            addItem(item) {
                if (item.item_type === 'product' && (!item.stock_quantity || item.stock_quantity <= 0)) {
                    alert(this.t('alertProductOutOfStock'));
                    return;
                }
                const existingIndex = this.invoice.items.findIndex(i => i.type === item.item_type && i.id === item.id);
                if (existingIndex >= 0) {
                    const existingItem = this.invoice.items[existingIndex];
                    const newQuantity = existingItem.quantity + 1;
                    if (item.item_type === 'product' && newQuantity > item.original_stock) { // Use original_stock for checking
                        alert(this.t('alertInsufficientStock'));
                        return;
                    }
                    existingItem.quantity = newQuantity;
                } else {
                     let finalPrice = 0;
                    if (item.item_type === 'service') {
                        finalPrice = (item.offer_price && parseFloat(item.offer_price) > 0) ? parseFloat(item.offer_price) : parseFloat(item.price ?? 0);
                    } else { // Product
                        finalPrice = parseFloat(item.selling_price ?? 0);
                    }
                    this.invoice.items.push({
                        type: item.item_type,
                        id: item.id,
                        name: this.getItemName(item),
                        quantity: 1,
                        unit_price: finalPrice,
                        original_stock: (item.item_type === 'product') ? item.stock_quantity : null
                    });
                }
                this.calculateTotals();
            },

            updateQuantity(index, change) {
                const item = this.invoice.items[index];
                const newQuantity = item.quantity + change;
                if (change > 0 && item.type === 'product' && newQuantity > item.original_stock) {
                    alert(this.t('alertInsufficientStock'));
                    return;
                }
                if (newQuantity > 0) {
                    item.quantity = newQuantity;
                } else {
                    this.invoice.items.splice(index, 1); // Remove item if quantity is 0 or less
                }
                this.calculateTotals();
            },

            clearInvoice() { this.invoice = { items: [], customer_id: null, customer_name: '', subtotal: 0, discount_amount: 0, discount_type: null, discount_value: 0, tax_amount: 0, total_amount: 0 }; this.calculateTotals(); },

            async searchCustomers() {
                const nameQuery = this.invoice.customer_name.trim();
                if (!nameQuery) { this.customerSearchResults = []; return; }
                this.isSearchingCustomers = true;
                try {
                    const formData = new FormData();
                    formData.append('action', 'search_customers');
                    formData.append('name', nameQuery);
                    const response = await fetch('pos_actions.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    this.customerSearchResults = result.success ? result.customers : [];
                } catch (error) { console.error('Customer search error:', error); }
                finally { this.isSearchingCustomers = false; }
            },

            selectCustomer(customer) {
                this.invoice.customer_id = customer.id;
                this.invoice.customer_name = customer.name_ar || customer.name;
                this.customerSearchResults = [];
            },

            applyManualDiscount(type) {
                let value = parseFloat(this.discountValueInput);
                if (isNaN(value) || value < 0) return;

                const maxPercent = this.roleDiscountPolicy.max_discount_percent;
                const maxAmount = this.roleDiscountPolicy.max_discount_amount;

                if (type === 'percentage') {
                    if (maxPercent > 0 && value > maxPercent) {
                        alert(`Maximum allowed percentage discount is ${maxPercent}%`);
                        value = maxPercent;
                    }
                    if (maxAmount !== null && (this.invoice.subtotal * value / 100) > maxAmount) {
                         alert(`Discount amount exceeds the maximum fixed amount of ${this.formatCurrency(maxAmount)}. Setting to max allowed.`);
                        value = (maxAmount / this.invoice.subtotal) * 100;
                    }
                } else if (type === 'fixed') {
                    if (maxAmount !== null && value > maxAmount) {
                        alert(`Maximum allowed fixed discount is ${this.formatCurrency(maxAmount)}`);
                        value = maxAmount;
                    }
                    if (maxPercent > 0 && value > (this.invoice.subtotal * maxPercent / 100)) {
                         alert(`Discount amount exceeds the maximum percentage of ${maxPercent}%. Setting to max allowed.`);
                        value = this.invoice.subtotal * maxPercent / 100;
                    }
                }

                this.invoice.discount_type = type;
                this.invoice.discount_value = value;
                this.calculateTotals();
                this.showDiscountModal = false;
                this.discountValueInput = 0;
            },

            autoCalculateMixedPayment(changedField) {
                const total = this.invoice.total_amount;
                if (changedField === 'cash') {
                    this.cardAmount = Math.max(0, total - (this.cashAmount || 0));
                } else if (changedField === 'card') {
                    this.cashAmount = Math.max(0, total - (this.cardAmount || 0));
                }
            },

            // --- Shift Management ---
            openCloseShiftModal() {
                fetch('pos_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_current_shift_data'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.shift) {
                        this.currentShift = { ...this.currentShift, ...data.shift };
                        this.closingCash = parseFloat(this.currentShift.opening_cash || 0) + parseFloat(this.currentShift.cash_sales || 0) - parseFloat(this.currentShift.cash_payouts || 0);
                        this.cashPayouts = 0;
                        this.closeShiftModal = true;
                    } else {
                        alert(data.message || 'Failed to fetch shift data.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching shift data:', error);
                    alert('Network error fetching shift data.');
                });
            },

            async closeShift() {
                if (!confirm(this.t('confirmCloseShiftText'))) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'close_shift');
                formData.append('shift_id', this.currentShift.id);
                formData.append('closing_cash', this.closingCash);
                formData.append('cash_payouts', this.cashPayouts);

                try {
                    const response = await fetch('pos_actions.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        this.currentShift = null;
                        this.closeShiftModal = false;
                        window.location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Failed to close shift.'));
                    }
                } catch (error) {
                    console.error('Error closing shift:', error);
                    alert('Network error closing shift.');
                }
            },

            // --- PRINTING LOGIC (MODIFIED) ---
            getBarReceiptHtml(invoiceData) {
                const finalInvoiceData = { ...this.invoice, ...invoiceData };
                let itemsHtml = '';
                
                (finalInvoiceData.items || []).forEach(item => {
                    itemsHtml += `<div class="item">
                                    <span class="qty">${item.quantity}x</span>
                                    <span class="name">${item.name}</span>
                                  </div>`;
                });

                if (!itemsHtml) {
                    itemsHtml = "<p>No items found in this order.</p>";
                }

                // --- EDITED: PRINTING STYLES ---
                const printStyles = `
                    <style>
                        body { 
                            font-family: sans-serif; 
                            width: 78mm; /* Wider paper */
                            margin: 0 auto; 
                            padding: 5px; 
                            font-size: 15px; /* Slightly larger font */
                            line-height: 1.3;
                        }
                        .ticket { text-align: left; }
                        .header { text-align: center; font-size: 22px; font-weight: bold; margin-bottom: 5px; } /* Larger font, less margin */
                        .info { border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 5px 0; margin-bottom: 5px; font-size: 13px; } /* less margin */
                        .info div { display: flex; justify-content: space-between; }
                        .items-list { margin-bottom: 0; } /* No margin at the end */
                        .item { display: flex; align-items: center; font-size: 17px; margin-bottom: 3px; } /* larger font, less margin */
                        .item .qty { font-weight: bold; min-width: 40px; }
                        .item .name { flex-grow: 1; }
                    </style>
                `;

                return `
                    <html>
                        <head>
                            <title>Bar Order</title>
                            ${printStyles}
                        </head>
                        <body>
                            <div class="ticket">
                                <div class="header">NEW ORDER</div>
                                <div class="info">
                                    <div><span>Order #:</span> <span>${finalInvoiceData.invoice_number || 'N/A'}</span></div>
                                    <div><span>Time:</span> <span>${new Date().toLocaleTimeString('en-US')}</span></div>
                                </div>
                                <div class="items-list">
                                    ${itemsHtml}
                                </div>
                            </div>
                        </body>
                    </html>
                `;
            },

            getCustomerReceiptHtml(invoiceData, zatcaQR) {
                const companyName = '<?php echo htmlspecialchars($legacy_settings['pos_receipt_header'] ?? 'Lucky Cafe'); ?>';
                const taxNumber = '<?php echo htmlspecialchars($legacy_settings['tax_number'] ?? ''); ?>';
                const footerText = '<?php echo htmlspecialchars($legacy_settings['pos_receipt_footer'] ?? 'Thank You! Visit Again!'); ?>';
                const finalInvoiceData = { ...this.invoice, ...invoiceData };
                
                let itemsHtml = '';
                (finalInvoiceData.items || []).forEach(item => {
                    const itemTotal = (item.unit_price * item.quantity).toFixed(2);
                    itemsHtml += `
                        <div class="item">
                            <div class="item-name">${item.name} (${item.quantity} x ${Number(item.unit_price).toFixed(2)})</div>
                            <div class="item-price">${itemTotal}</div>
                        </div>
                    `;
                });

                const taxRowHtml = this.isTaxEnabled 
                    ? `<div class="summary-line"><span>Tax (${Number(finalInvoiceData.tax_rate || 0).toFixed(0)}%)</span><span>${Number(finalInvoiceData.tax_amount).toFixed(2)}</span></div>` 
                    : '';
                
                // --- EDITED: PRINTING STYLES ---
                const printStyles = `
                    <style>
                        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono&display=swap');
                        body { 
                            font-family: 'Roboto Mono', monospace; 
                            width: 78mm; /* Wider paper */
                            margin: 0 auto; 
                            padding: 5px; 
                            color: #000;
                            font-size: 13px; /* Slightly larger font */
                            line-height: 1.3; /* Tighter line height */
                        }
                        .receipt { width: 100%; }
                        .logo { text-align: center; padding: 5px 0; } /* Less padding */
                        .logo div { height: 50px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #888; }
                        .header { text-align: center; margin-bottom: 10px; } /* Less margin */
                        .header h2 { margin: 0; font-size: 19px; font-weight: bold; } /* Slightly larger */
                        .header p { margin: 1px 0; } /* Tighter */
                        .details { margin-bottom: 8px; font-size: 12px; } /* Less margin, larger font */
                        .details .line { display: flex; justify-content: space-between; }
                        .items-table { border-top: 1px dashed #000; padding-top: 8px; margin-top: 8px; } /* Tighter spacing */
                        .items-table .table-header { display: flex; justify-content: space-between; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 4px; } /* Tighter */
                        .items-table .item { display: flex; justify-content: space-between; margin-bottom: 3px; } /* Tighter */
                        .items-table .item-name { flex-grow: 1; margin-right: 10px; }
                        .summary { border-top: 1px dashed #000; padding-top: 8px; margin-top: 8px; } /* Tighter */
                        .summary .summary-line { display: flex; justify-content: space-between; }
                        .summary .total { font-weight: bold; font-size: 17px; margin-top: 4px; } /* Slightly larger */
                        .qr-code { text-align: center; padding: 8px 0; } /* Tighter */
                        .qr-code img { width: 120px; height: 120px; }
                        .footer { text-align: center; border-top: 1px dashed #000; padding-top: 8px; margin-top: 8px; font-size: 12px; } /* Tighter */
                    </style>
                `;

                return `
                    <html>
                        <head>
                            <title>Receipt</title>
                            ${printStyles}
                        </head>
                        <body>
                            <div class="receipt">
                                <div class="logo">
                        <div><img src="${this.logoUrl}" alt="Logo" style="max-height: 50px;"/></div>
                                </div>
                                <div class="header">
                                    <p>Simplified Tax Invoice</p>
                                    ${this.isTaxEnabled && taxNumber ? `<p>Tax Number: ${taxNumber}</p>` : ''}
                                </div>
                                <div class="details">
                                    <div class="line"><span>Invoice No:</span><span>${finalInvoiceData.invoice_number || 'N/A'}</span></div>
                                    <div class="line"><span>Date:</span><span>${new Date().toLocaleString('en-US')}</span></div>
                                    <div class="line"><span>Customer:</span><span>${finalInvoiceData.customer_name || 'Guest'}</span></div>
                                </div>
                                <div class="items-table">
                                    <div class="table-header">
                                        <span>Item</span>
                                        <span>Total</span>
                                    </div>
                                    ${itemsHtml}
                                </div>
                                <div class="summary">
                                    <div class="summary-line"><span>Subtotal</span><span>${Number(finalInvoiceData.subtotal).toFixed(2)}</span></div>
                                    ${taxRowHtml}
                                    <div class="summary-line"><span>Discount</span><span>${Number(finalInvoiceData.discount_amount).toFixed(2)}</span></div>
                                    <div class="summary-line total"><span>TOTAL</span><span>${Number(finalInvoiceData.total_amount).toFixed(2)}</span></div>
                                </div>
                                <div class="footer">
                                    <p>${footerText}</p>
                                </div>
                            </div>
                        </body>
                    </html>
                `;
            },

            async _printJob(htmlContent) {
                const frame = document.getElementById('print-frame');
                frame.contentDocument.open();
                frame.contentDocument.write(htmlContent);
                frame.contentDocument.close();
                await new Promise(resolve => setTimeout(resolve, 500));
                frame.contentWindow.focus();
                frame.contentWindow.print();
                await new Promise(resolve => setTimeout(resolve, 1000));
                frame.contentDocument.body.innerHTML = '';
            },

            async processPayment() {
                if (this.invoice.items.length === 0) {
                    alert('Cannot process an empty invoice.');
                    return;
                }

                if (this.paymentMethod === 'mixed' && Math.abs(((this.cashAmount || 0) + (this.cardAmount || 0)) - this.invoice.total_amount) > 0.01) {
                    alert('Mixed payment amount does not match total.');
                    return;
                }
                const paymentData = { method: this.paymentMethod, cash_amount: 0, card_amount: 0, bank_transfer_amount: 0 };
                if (this.paymentMethod === 'mixed') {
                    paymentData.cash_amount = this.cashAmount || 0;
                    paymentData.card_amount = this.cardAmount || 0;
                    paymentData.bank_transfer_amount = this.invoice.total_amount - paymentData.cash_amount - paymentData.card_amount;
                }
                else if (this.paymentMethod === 'cash') { paymentData.cash_amount = this.invoice.total_amount; }
                else if (this.paymentMethod === 'card') { paymentData.card_amount = this.invoice.total_amount; }
                else if (this.paymentMethod === 'bank_transfer') { paymentData.bank_transfer_amount = this.invoice.total_amount; }

                const formData = new FormData();
                formData.append('action', 'process_invoice');
                formData.append('invoice_data', JSON.stringify({ ...this.invoice, order_type: this.orderType }));
                formData.append('payment_data', JSON.stringify(paymentData));

                try {
                    const response = await fetch('pos_actions.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        this.showPaymentModal = false;
                        this.successPopupData = { message: this.t('successInvoiceCreated'), invoiceNumber: result.invoice_number };
                        const invoicePrintData = { ...this.invoice, ...result.invoice_data, invoice_number: result.invoice_number };

                        const barHtml = this.getBarReceiptHtml(invoicePrintData);
                        await this._printJob(barHtml);

                        const customerHtml = this.getCustomerReceiptHtml(invoicePrintData, result.zatca_qr);
                        await this._printJob(customerHtml);

                        this.showSuccessPopup = true;
                        this.clearInvoice();

                    } else {
                        alert('Error: ' + (result.message || 'Failed to process invoice.'));
                    }
                } catch (error) {
                    console.error('Payment error:', error);
                    alert('Network error during payment processing.');
                }
            }
        }));
    });
</script>
<?php
$page_content = ob_get_clean();
// --- EDITED: COLOR THEME ---
$page_specific_head = '
 <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800;900&display=swap" rel="stylesheet">
 <style>
    :root {
        --color-background: #F0FDF8;    /* Very light mint green */
        --color-primary: #14B8A6;       /* New Mint/Teal Green */
        --color-primary-dark: #0D9488;  /* Darker Mint/Teal Green */
        --color-primary-light: #E0F2F1; /* Lighter Mint/Teal Green */
        --color-secondary: #111827;     /* Near Black for headings */
        --color-accent: #6B7280;        /* Neutral grey for accents */
        --color-text-dark: #1F2937;     /* Dark grey for text */
    }
    body { font-family: "Tajawal", sans-serif; }
    .bg-background { background-color: var(--color-background); }
    .bg-primary { background-color: var(--color-primary); }
    .bg-primary-dark { background-color: var(--color-primary-dark); }
    .bg-primary-light { background-color: var(--color-primary-light); }
    .hover\:bg-primary-dark:hover { background-color: var(--color-primary-dark); }
    .text-primary { color: var(--color-primary); }
    .text-primary-dark { color: var(--color-primary-dark); }
    .border-primary { border-color: var(--color-primary); }
    .focus\:border-primary:focus { border-color: var(--color-primary); }
    .focus\:ring-primary:focus { --tw-ring-color: var(--color-primary); }
    .shadow-primary\/30 { --tw-shadow-color: rgba(20, 184, 166, 0.3); --tw-shadow: var(--tw-shadow-colored); }
    .bg-secondary { background-color: var(--color-secondary); }
    .hover\:bg-opacity-90:hover { background-color: rgba(17, 24, 39, 0.9); }
    .text-secondary { color: var(--color-secondary); }
    .border-secondary { border-color: var(--color-secondary); }
    .hover\:bg-secondary:hover { background-color: var(--color-secondary); }
    .text-accent { color: var(--color-accent); }
    .text-text-dark { color: var(--color-text-dark); }
    .border-accent { border-color: var(--color-accent); }


    [x-cloak] { display: none !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #aaa; }

    .animate-fade-in-up { animation: fadeInUp 0.3s ease-out; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .scale-animation { animation: scaleUp 0.3s ease-out; }
    @keyframes scaleUp {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .print-only { display: none; }
    @media print {
        body * { visibility: hidden; }
        .print-only, .print-only * { visibility: visible; }
        .print-only { position: absolute; left: 0; top: 0; width: 100%; height: 100%; }
        .no-print { display: none !important; }
        @page { size: auto; margin: 0; }
    }
 </style>';
$page_specific_footer_scripts = '';
include 'layouts/responsive_base.php';
?>