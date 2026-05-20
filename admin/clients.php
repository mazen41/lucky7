<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * CRM - Customer Relationship Management System (Corrected & Enhanced with Pagination)
 */

require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';
checkAdminAuth();
$admin = getCurrentAdmin();

// --- PERMISSION CHECK: Page Access ---
requirePermission('clients.view');

$database = new Database();
$db = $database->getConnection();

$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_customer':
                if (!hasPermission('clients.create')) throw new Exception("ليس لديك الصلاحية لإضافة عملاء جدد.");
                $name_ar = trim($_POST['name_ar']);
                $phone = trim($_POST['phone']);
                if (empty($name_ar) || empty($phone)) {
                    throw new Exception("الاسم ورقم الجوال حقول إلزامية.");
                }
                $customer_code = 'CUS' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $qr_token = uniqid('cus_', true);
                $stmt = $db->prepare("INSERT INTO customers (customer_code, name_ar, phone, qr_token, status, created_at, created_by) VALUES (?, ?, ?, ?, 'active', NOW(), ?)");
                $stmt->execute([$customer_code, $name_ar, $phone, $qr_token, $admin['id']]);
                $_SESSION['flash_success'] = "تم إضافة العميل بنجاح.";
                break;

            case 'update_customer':
                if (!hasPermission('clients.edit')) throw new Exception("ليس لديك الصلاحية لتعديل بيانات العملاء.");
                $id = intval($_POST['customer_id']);
                $name_ar = trim($_POST['name_ar']);
                $phone = trim($_POST['phone']);
                 if (empty($name_ar) || empty($phone)) {
                    throw new Exception("الاسم ورقم الجوال حقول إلزامية.");
                }
                $stmt = $db->prepare("UPDATE customers SET name_ar = ?, phone = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name_ar, $phone, $admin['id'], $id]);
                $_SESSION['flash_success'] = "تم تحديث بيانات العميل بنجاح.";
                break;
            
            case 'set_customer_status':
                if (!hasPermission('clients.edit')) throw new Exception("ليس لديك الصلاحية لتغيير حالة العملاء.");
                $id = intval($_POST['customer_id']);
                $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'inactive';
                $stmt = $db->prepare("UPDATE customers SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $admin['id'], $id]);
                $_SESSION['flash_success'] = $status === 'inactive' ? "تم إخفاء العميل." : "تم تنشيط العميل.";
                break;

            case 'permanent_delete_customer':
                 if (!hasPermission('clients.delete')) throw new Exception("ليس لديك الصلاحية لحذف العملاء.");
                 $id = intval($_POST['customer_id']);
                 $check_stmt = $db->prepare("(SELECT COUNT(*) FROM appointments WHERE customer_id = ?) UNION ALL (SELECT COUNT(*) FROM invoices WHERE customer_id = ?)");
                 $check_stmt->execute([$id, $id]);
                 $counts = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
                 if (array_sum($counts) > 0) {
                     $_SESSION['flash_error'] = "لا يمكن حذف العميل لوجود سجلات مرتبطة به.";
                 } else {
                     $db->beginTransaction();
                     try {
                         $db->prepare("DELETE FROM client_forms WHERE customer_id = ?")->execute([$id]);
                         $db->prepare("DELETE FROM loyalty_wallets WHERE customer_id = ?")->execute([$id]);
                         $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
                         $db->commit();
                         $_SESSION['flash_success'] = "تم حذف العميل نهائياً.";
                     } catch (Exception $e) {
                         $db->rollBack();
                         $_SESSION['flash_error'] = "حدث خطأ أثناء الحذف.";
                     }
                 }
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = ($e->getCode() == 23000) ? "خطأ: رقم الجوال أو التوكن المدخل موجود بالفعل لعميل آخر." : "خطأ في قاعدة البيانات.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit();
}

// --- Data Fetching & Pagination Logic ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tier_filter = $_GET['tier'] ?? '';

// Pagination Setup
$items_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure current page is at least 1
$offset = ($current_page - 1) * $items_per_page;

// Build WHERE clause with named placeholders for security and clarity
$where_conditions = [];
$params = [];
if ($search) {
    $where_conditions[] = "(c.name_ar LIKE :search OR c.phone LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($status_filter) {
    $where_conditions[] = "c.status = :status";
    $params[':status'] = $status_filter;
}
if ($tier_filter) {
    $where_conditions[] = "lw.tier_id = :tier";
    $params[':tier'] = $tier_filter;
}
$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total matching customers for pagination
$count_query = "SELECT COUNT(c.id) FROM customers c LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_customers = $count_stmt->fetchColumn();

// Calculate total pages
$total_pages = $items_per_page > 0 ? ceil($total_customers / $items_per_page) : 0;
$total_pages = max(1, $total_pages); // Ensure at least 1 page

// Adjust current page if it's out of bounds and recalculate offset
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// Fetch the customers for the current page
$customers_query = "
    SELECT 
        c.id, c.name_ar, c.phone, c.status, c.customer_code,
        lt.name_ar as tier_name,
        lt.tier_color,
        (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE customer_id = c.id AND status = 'paid') as total_spend_calculated,
        (SELECT COUNT(id) FROM invoices WHERE customer_id = c.id) as invoice_count_calculated,
        (SELECT COUNT(id) FROM appointments WHERE customer_id = c.id) as appointment_count_calculated
    FROM customers c
    LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
    LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
    $where_clause
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
";

$customers_stmt = $db->prepare($customers_query);

// Add pagination params to the existing filter params
$params[':limit'] = $items_per_page;
$params[':offset'] = $offset;

// Bind all parameters, ensuring correct types for LIMIT and OFFSET
foreach ($params as $param => &$value) {
    if ($param === ':limit' || $param === ':offset') {
        $customers_stmt->bindParam($param, $value, PDO::PARAM_INT);
    } else {
        $customers_stmt->bindParam($param, $value);
    }
}
$customers_stmt->execute();
$customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tiers for the filter dropdown
$tiers_stmt = $db->prepare("SELECT id, name_ar FROM loyalty_tiers WHERE is_active = 1 ORDER BY min_points ASC");
$tiers_stmt->execute();
$all_tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'إدارة العملاء';
ob_start();
?>
<!-- START: PAGE CONTENT -->
<div class="bg-white rounded-xl shadow-md mb-6 p-4" x-data="{ showFilters: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-mai-brown rounded-lg flex items-center justify-center text-white text-xl"><i class="fas fa-users"></i></div>
            <div>
                <h1 class="text-2xl font-bold text-mai-dark">إدارة العملاء (CRM)</h1>
                <p class="text-sm text-gray-500">عرض، إضافة، وتعديل بيانات العملاء وفئاتهم.</p>
            </div>
        </div>
         <div class="flex items-center space-x-2 space-x-reverse">
            <button @click="showFilters = !showFilters" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300"><i class="fas fa-filter ml-2"></i><span x-text="showFilters ? 'إخفاء الفلاتر' : 'إظهار الفلاتر'"></span></button>
            <?php if (hasPermission('clients.create')): ?>
            <button @click="$dispatch('open-modal', { id: 'addCustomerModal' })" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark"><i class="fas fa-plus ml-2"></i>إضافة عميل</button>
            <?php endif; ?>
        </div>
    </div>
    <div x-show="showFilters" x-transition class="mt-4 pt-4 border-t">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="بحث بالاسم أو الهاتف..." class="md:col-span-2 w-full px-4 py-2 border rounded-lg">
            <select name="status" class="w-full px-3 py-2 border rounded-lg">
                <option value="">جميع الحالات</option>
                <option value="active" <?php if($status_filter === 'active') echo 'selected'; ?>>نشط</option>
                <option value="inactive" <?php if($status_filter === 'inactive') echo 'selected'; ?>>غير نشط</option>
            </select>
            <select name="tier" class="w-full px-3 py-2 border rounded-lg">
                <option value="">جميع الفئات</option>
                <?php foreach ($all_tiers as $tier): ?><option value="<?php echo $tier['id']; ?>" <?php if($tier_filter == $tier['id']) echo 'selected'; ?>><?php echo htmlspecialchars($tier['name_ar']); ?></option><?php endforeach; ?>
            </select>
            <div class="flex items-center space-x-2 space-x-reverse md:col-start-4">
                <button type="submit" class="w-full bg-mai-dark text-white px-4 py-2 rounded-lg hover:bg-black"><i class="fas fa-search"></i> بحث</button>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="w-full text-center bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700"><i class="fas fa-times"></i> مسح</a>
            </div>
        </form>
    </div>
</div>

<?php if ($success): ?><div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert"><p><?php echo htmlspecialchars($success); ?></p></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert"><p><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>

<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">العميل</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">الإحصائيات</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">فئة العميل</th>
                    <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">الحالة</th>
                    <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($customers as $customer): ?>
                <tr class="hover:bg-gray-50" x-data='{ customer: <?php echo htmlspecialchars(json_encode($customer, JSON_UNESCAPED_UNICODE)); ?> }'>
                    <td class="px-6 py-4 whitespace-nowrap"><div class="flex items-center"><div class="w-10 h-10 bg-mai-brown rounded-full flex items-center justify-center text-white font-bold text-sm ml-4 flex-shrink-0"><?php echo strtoupper(mb_substr($customer['name_ar'], 0, 1)); ?></div><div><div class="font-medium text-gray-900"><?php echo htmlspecialchars($customer['name_ar']); ?></div><div class="text-xs text-gray-500"><?php echo htmlspecialchars($customer['phone']); ?></div></div></div></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><div>إجمالي الإنفاق: <span class="font-bold"><?php echo number_format($customer['total_spend_calculated'] ?? 0, 2); ?> ر.س</span></div><div class="text-xs">الفواتير: <span class="font-bold"><?php echo (int)($customer['invoice_count_calculated'] ?? 0); ?></span> | المواعيد: <span class="font-bold"><?php echo (int)($customer['appointment_count_calculated'] ?? 0); ?></span></div></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?php if ($customer['tier_name']): ?><span class="px-3 py-1 text-xs rounded-full text-white font-semibold" style="background-color: <?= htmlspecialchars($customer['tier_color'] ?? '#3B82F6') ?>;"><?= htmlspecialchars($customer['tier_name']) ?></span><?php else: ?><span class="text-xs text-gray-400">لا يوجد</span><?php endif; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-center"><span class="px-3 py-1 text-xs font-medium rounded-full <?php echo $customer['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>"><?php echo $customer['status'] === 'active' ? 'نشط' : 'غير نشط'; ?></span></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center"><div class="flex justify-center items-center space-x-2 space-x-reverse">
                        <?php if (hasPermission('clients.view_profile')): ?>
                        <a href="client_profile.php?id=<?= $customer['id'] ?>" class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-blue-100" title="عرض الملف"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
                        <?php if (hasPermission('clients.edit')): ?>
                        <button @click="$dispatch('edit-customer', customer)" class="text-mai-brown hover:text-mai-dark p-2 rounded-full hover:bg-mai-cream" title="تعديل"><i class="fas fa-edit"></i></button>
                        <?php if ($customer['status'] === 'active'): ?><button @click="$dispatch('open-modal', { id: 'inactiveCustomerModal', customer: customer })" class="text-yellow-600 hover:text-yellow-900 p-2 rounded-full hover:bg-yellow-100" title="جعله غير نشط"><i class="fas fa-user-slash"></i></button><?php else: ?><button @click="$dispatch('open-modal', { id: 'activeCustomerModal', customer: customer })" class="text-green-600 hover:text-green-900 p-2 rounded-full hover:bg-green-100" title="جعله نشط"><i class="fas fa-user-check"></i></button><?php endif; ?>
                        <?php endif; ?>
                        <?php if (hasPermission('clients.delete')): ?>
                        <button @click="$dispatch('open-modal', { id: 'deleteCustomerModal', customer: customer })" class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-red-100" title="حذف نهائي"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?><tr><td colspan="5" class="text-center py-12 text-gray-500">لا توجد بيانات تطابق البحث.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- START: PAGINATION -->
    <?php
    if ($total_pages > 1):
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $baseUrl = $_SERVER['PHP_SELF'] . '?' . ($queryString ? $queryString . '&' : '');
        $pageLink = fn($page) => $baseUrl . 'page=' . $page;
        $window = 2; // Number of pages to show around the current page
    ?>
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 rounded-b-xl">
        <div class="flex-1 flex justify-between sm:hidden">
            <a href="<?= $current_page > 1 ? $pageLink($current_page - 1) : '#' ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $current_page <= 1 ? 'pointer-events-none opacity-60' : '' ?>"> السابق </a>
            <a href="<?= $current_page < $total_pages ? $pageLink($current_page + 1) : '#' ?>" class="mr-auto relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $current_page >= $total_pages ? 'pointer-events-none opacity-60' : '' ?>"> التالي </a>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    عرض
                    <span class="font-medium"><?= $offset + 1 ?></span>
                    إلى
                    <span class="font-medium"><?= min($offset + $items_per_page, $total_customers) ?></span>
                    من
                    <span class="font-medium"><?= $total_customers ?></span>
                    نتائج
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm" aria-label="Pagination">
                    <a href="<?= $current_page > 1 ? $pageLink($current_page - 1) : '#' ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $current_page <= 1 ? 'cursor-not-allowed opacity-50' : '' ?>">
                        <span class="sr-only">السابق</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                    </a>
                    <?php
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $window && $i <= $current_page + $window)):
                    ?>
                        <a href="<?= $pageLink($i) ?>" aria-current="page" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium border-gray-300 <?= $i == $current_page ? 'z-10 bg-mai-cream border-mai-brown text-mai-dark' : 'bg-white text-gray-500 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php
                        elseif ($i == $current_page - ($window + 1) || $i == $current_page + ($window + 1)):
                    ?>
                        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                    <?php
                        endif;
                    endfor;
                    ?>
                    <a href="<?= $current_page < $total_pages ? $pageLink($current_page + 1) : '#' ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $current_page >= $total_pages ? 'cursor-not-allowed opacity-50' : '' ?>">
                        <span class="sr-only">التالي</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    </a>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- END: PAGINATION -->
</div>

<!-- MODALS -->
<div x-data="modalsManager()">
    <div x-show="modals.addCustomerModal" @open-modal.window="if($event.detail.id === 'addCustomerModal') open($event.detail.id, $event.detail.customer || null)" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div @click.away="close('addCustomerModal')" class="bg-white rounded-xl p-6 w-full max-w-lg" x-show="modals.addCustomerModal" x-transition>
            <h3 class="text-xl font-bold text-mai-dark mb-4" x-text="isEditing ? 'تعديل بيانات العميل' : 'إضافة عميل جديد'"></h3>
            <form method="POST">
                <input type="hidden" name="action" :value="isEditing ? 'update_customer' : 'create_customer'">
                <input type="hidden" name="customer_id" x-model="customer.id">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-gray-700">اسم العميل (إلزامي)</label><input type="text" name="name_ar" x-model="customer.name_ar" required placeholder="الاسم الكامل بالعربي" class="mt-1 w-full p-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium text-gray-700">رقم الجوال (إلزامي)</label><input type="tel" name="phone" x-model="customer.phone" required placeholder="e.g., 05xxxxxxxx" class="mt-1 w-full p-2 border rounded-lg"></div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t"><button type="button" @click="close('addCustomerModal')" class="px-6 py-2 border rounded-lg">إلغاء</button><button type="submit" class="bg-mai-brown text-white px-8 py-2 rounded-lg hover:bg-mai-dark" x-text="isEditing ? 'حفظ التغييرات' : 'إضافة العميل'"></button></div>
            </form>
        </div>
    </div>
    <div x-show="modals.inactiveCustomerModal" @open-modal.window="if($event.detail.id === 'inactiveCustomerModal') open($event.detail.id, $event.detail.customer)" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div @click.away="close('inactiveCustomerModal')" class="bg-white rounded-xl p-8 w-full max-w-md" x-show="modals.inactiveCustomerModal" x-transition>
            <p class="text-center text-gray-600 my-4">هل أنت متأكد من رغبتك في جعل العميل "<strong x-text="customer.name_ar"></strong>" غير نشط؟</p>
            <form method="POST"><input type="hidden" name="action" value="set_customer_status"><input type="hidden" name="status" value="inactive"><input type="hidden" name="customer_id" x-model="customer.id"><div class="flex justify-center gap-4 mt-6"><button type="button" @click="close('inactiveCustomerModal')" class="px-8 py-2 border rounded-lg">إلغاء</button><button type="submit" class="bg-yellow-500 text-white px-8 py-2 rounded-lg hover:bg-yellow-600">نعم، تأكيد</button></div></form>
        </div>
    </div>
    <div x-show="modals.activeCustomerModal" @open-modal.window="if($event.detail.id === 'activeCustomerModal') open($event.detail.id, $event.detail.customer)" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div @click.away="close('activeCustomerModal')" class="bg-white rounded-xl p-8 w-full max-w-md" x-show="modals.activeCustomerModal" x-transition>
            <p class="text-center text-gray-600 my-4">هل أنت متأكد من رغبتك في إعادة تنشيط العميل "<strong x-text="customer.name_ar"></strong>"؟</p>
            <form method="POST"><input type="hidden" name="action" value="set_customer_status"><input type="hidden" name="status" value="active"><input type="hidden" name="customer_id" x-model="customer.id"><div class="flex justify-center gap-4 mt-6"><button type="button" @click="close('activeCustomerModal')" class="px-8 py-2 border rounded-lg">إلغاء</button><button type="submit" class="bg-green-500 text-white px-8 py-2 rounded-lg hover:bg-green-600">نعم، تنشيط</button></div></form>
        </div>
    </div>
    <div x-show="modals.deleteCustomerModal" @open-modal.window="if($event.detail.id === 'deleteCustomerModal') open($event.detail.id, $event.detail.customer)" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div @click.away="close('deleteCustomerModal')" class="bg-white rounded-xl p-8 w-full max-w-lg border-t-4 border-red-600" x-show="modals.deleteCustomerModal" x-transition>
            <h3 class="text-2xl font-bold text-center">تحذير: إجراء نهائي</h3>
            <p class="text-center text-gray-600 my-4">أنت على وشك حذف العميل "<strong x-text="customer.name_ar"></strong>" بشكل <strong class="text-red-600">نهائي</strong>. هذا الإجراء لا يمكن التراجع عنه.</p>
            <form method="POST"><input type="hidden" name="action" value="permanent_delete_customer"><input type="hidden" name="customer_id" x-model="customer.id"><div class="flex justify-center gap-4 mt-6"><button type="button" @click="close('deleteCustomerModal')" class="px-8 py-2 border rounded-lg">إلغاء</button><button type="submit" class="bg-red-600 text-white px-8 py-2 rounded-lg hover:bg-red-700">نعم، أحذف نهائياً</button></div></form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('modalsManager', () => ({
        modals: { addCustomerModal: false, inactiveCustomerModal: false, deleteCustomerModal: false, activeCustomerModal: false },
        isEditing: false,
        customer: {},
        init() {
            window.addEventListener('edit-customer', (event) => {
                this.open('addCustomerModal', event.detail, true);
            });
        },
        open(id, customerData, isEditing = false) {
            this.isEditing = isEditing;
            const defaultCustomer = { id: null, name_ar: '', phone: '' };
            this.customer = customerData ? { ...customerData } : defaultCustomer;
            this.modals[id] = true;
        },
        close(id) {
            this.modals[id] = false;
        }
    }));
});
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php'; 
?>