<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Riyadh');

require_once '../config/database.php';
require_once 'auth.php';
require_once 'users_functions.php';

checkAdminAuth();

// --- START: PERMISSION CHECK ---
if (!hasPermission('dashboard.view')) {
    // User does not have permission, show access denied message
    $page_title = 'Access Denied';
    ob_start();
    ?>
    <div class="bg-white rounded-2xl shadow-lg p-6 text-center" data-aos="fade-down">
        <div class="flex flex-col items-center justify-center gap-4">
             <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-red-700 rounded-2xl flex items-center justify-center text-white text-4xl shadow-lg"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <h1 class="text-4xl font-bold text-red-600">Permission Denied</h1>
                <p class="text-lg text-gray-600 mt-3">You do not have the necessary permission to view this page.</p>
                <p class="text-sm text-gray-500 mt-2">Please contact the administrator if you believe this is an error.</p>
            </div>
        </div>
    </div>
    <?php
    $page_content = ob_get_clean();
    $page_specific_head = '<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"><script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>';
    $page_specific_footer_scripts = '<script>AOS.init({ duration: 600, once: true, offset: 20 });</script>';
    include 'layouts/responsive_base.php';
    exit();
}
// --- END: PERMISSION CHECK ---


class DashboardEngine {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getFilteredKPIs($filter_type = 'day', $filter_value = null) {
        $today = date('Y-m-d');
        $date_condition = "";
        $bind_params = [];

        if ($filter_type == 'month' && $filter_value) {
            $date_condition = "DATE_FORMAT(created_at, '%Y-%m') = ?";
            $bind_params[] = $filter_value;
        } elseif ($filter_type == 'day' && $filter_value) {
            $date_condition = "DATE(created_at) = ?";
            $bind_params[] = $filter_value;
        } else {
            $date_condition = "DATE(created_at) = ?";
            $bind_params[] = $today;
        }

        // --- EDITED QUERY FOR SALES KPIS ---
        // Added a conditional sum to fetch bank transfer sales.
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(total_amount), 0) as gross_sales,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(SUM(cash_amount), 0) as cash_sales,
                COALESCE(SUM(card_amount), 0) as card_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN total_amount ELSE 0 END), 0) as transfer_sales,
                COUNT(id) as invoice_count
            FROM invoices
            WHERE $date_condition AND status = 'paid' AND is_return = 0
        ");
        $stmt->execute($bind_params);
        $salesData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch returns separately
        $returns_stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total_returns
            FROM invoices
            WHERE $date_condition AND is_return = 1
        ");
        $returns_stmt->execute($bind_params);
        $returns = abs($returns_stmt->fetchColumn()); // Use abs to make it a positive number

        // Fetch expenses
        $expense_date_condition = str_replace('created_at', 'expense_date', $date_condition);
        $expenses = 0;
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE $expense_date_condition");
            $stmt->execute($bind_params);
            $expenses = $stmt->fetchColumn();
        } catch (PDOException $e) { error_log("Error fetching expenses: " . $e->getMessage()); }

        // Calculate net profit based on fetched values
        $net_profit = ($salesData['gross_sales'] - $returns) - $expenses - $salesData['total_tax'];

        $newCustomers = 0; $upcomingAppointments = 0; $lowStockItems = 0; $openShifts = 0;

        if ($filter_type == 'day' && ($filter_value == $today || empty($filter_value))) {
            $newCustomers = $this->db->query("SELECT COUNT(id) FROM customers WHERE DATE(created_at) = '$today'")->fetchColumn();
            $upcomingAppointments = $this->db->query("SELECT COUNT(id) FROM appointments WHERE DATE(start_at) = '$today' AND status IN ('confirmed', 'pending')")->fetchColumn();
            $lowStockItems = $this->db->query("SELECT COUNT(id) FROM products WHERE is_active = 1 AND track_stock = 1 AND min_stock_level > 0 AND stock_quantity <= min_stock_level")->fetchColumn();
            $openShifts = $this->db->query("SELECT COUNT(id) FROM shifts WHERE status = 'opened'")->fetchColumn();
        }

        $kpis = [
            'gross_sales' => (float) $salesData['gross_sales'],
            'total_tax' => (float) $salesData['total_tax'],
            'cash_sales' => (float) $salesData['cash_sales'],
            'card_sales' => (float) $salesData['card_sales'],
            'transfer_sales' => (float) $salesData['transfer_sales'], // EDITED: Populate with fetched data
            'invoice_count' => (int) $salesData['invoice_count'],
            'returns' => (float) $returns,
            'expenses' => (float) $expenses,
            'net_profit' => (float) $net_profit,
            'new_customers' => (int) $newCustomers,
            'upcoming_appointments' => (int) $upcomingAppointments,
            'low_stock_items' => (int) $lowStockItems,
            'open_shifts' => (int) $openShifts,
        ];

        return $kpis;
    }

    public function getFilteredAppointments($date, $page = 1, $limit = 5) {
        $offset = ($page - 1) * $limit;

        $total_stmt = $this->db->prepare("SELECT COUNT(id) FROM appointments WHERE DATE(start_at) = :date AND status IN ('confirmed', 'pending')");
        $total_stmt->execute([':date' => $date]);
        $total = $total_stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT
                a.start_at,
                COALESCE(c.name_ar, c.name_en, c.name, a.customer_name) AS customer_name,
                a.customer_phone,
                s.name_ar AS service_name,
                tm.name_ar AS specialist_name
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN team_members tm ON a.specialist_id = tm.id
            LEFT JOIN customers c ON a.customer_id = c.id
            WHERE DATE(a.start_at) = :date AND a.status IN ('confirmed', 'pending')
            ORDER BY a.start_at ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['records' => $records, 'total' => (int)$total];
    }

    public function getFilteredInvoices($date, $page = 1, $limit = 5) {
        $offset = ($page - 1) * $limit;

        // --- EDITED TOTAL COUNT QUERY ---
        // Exclude returns from the list total
        $total_stmt = $this->db->prepare("SELECT COUNT(i.id) FROM invoices i WHERE DATE(i.created_at) = :date AND i.status = 'paid' AND i.is_return = 0");
        $total_stmt->execute([':date' => $date]);
        $total = $total_stmt->fetchColumn();

        // --- EDITED QUERY FOR INVOICES ---
        // Corrected column names and joins for accurate data display
        $stmt = $this->db->prepare("
            SELECT
                i.invoice_number,
                i.total_amount,
                i.tax_amount,
                COALESCE(c.name_ar, c.name_en, c.name, 'عميل عابر') as customer_name,
                c.phone as customer_phone
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE DATE(i.created_at) = :date AND i.status = 'paid' AND i.is_return = 0
            ORDER BY i.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['records' => $records, 'total' => (int)$total];
    }
}

$database = new Database(); $db = $database->getConnection(); $dashboard = new DashboardEngine($db);

$filter_type = $_GET['filter_type'] ?? 'day';
$filter_value = $_GET['filter_value'] ?? date('Y-m-d');

if ($filter_type == 'month' && !preg_match('/^\d{4}-\d{2}$/', $filter_value)) { $filter_value = date('Y-m'); }
elseif ($filter_type == 'day' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) { $filter_value = date('Y-m-d'); }

$kpis = $dashboard->getFilteredKPIs($filter_type, $filter_value);

// --- START: ADDED QUERY FOR PENDING APPOINTMENTS ALERT ---
$pending_count_stmt = $db->query("SELECT COUNT(id) FROM appointments WHERE status = 'pending'");
$pending_count = $pending_count_stmt->fetchColumn();
// --- END: ADDED QUERY FOR PENDING APPOINTMENTS ALERT ---

define('ITEMS_PER_PAGE', 5);
$list_date = ($filter_type == 'day') ? $filter_value : date('Y-m-d');
$page_appts = isset($_GET['page_appts']) ? max(1, (int)$_GET['page_appts']) : 1;
$appointments_data = $dashboard->getFilteredAppointments($list_date, $page_appts, ITEMS_PER_PAGE);
$total_pages_appts = ceil($appointments_data['total'] / ITEMS_PER_PAGE);

$page_invs = isset($_GET['page_invs']) ? max(1, (int)$_GET['page_invs']) : 1;
$invoices_data = $dashboard->getFilteredInvoices($list_date, $page_invs, ITEMS_PER_PAGE);
$total_pages_invs = ceil($invoices_data['total'] / ITEMS_PER_PAGE);

$page_title = 'لوحة التحكم الرئيسية';

function render_kpi_card($title, $value, $currency, $icon, $color_class, $delay) {
    return <<<HTML
    <div class="bg-white rounded-2xl shadow-lg p-6 relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:-translate-y-1.5 border-l-8 $color_class" data-aos="fade-up" data-aos-delay="$delay">
        <div class="relative z-10">
            <p class="text-base font-medium text-gray-500">$title</p>
            <p class="text-3xl font-semibold text-mai-dark-brown mt-2">$value <span class="text-sm font-medium text-gray-600">$currency</span></p>
        </div>
    </div>
HTML;
}

function render_pagination_links($currentPage, $totalPages, $paramName) {
    if ($totalPages <= 1) return '';
    $queryParams = $_GET;
    $output = '<nav class="mt-4 flex justify-center items-center gap-2 text-sm">';
    if ($currentPage > 1) {
        $queryParams[$paramName] = $currentPage - 1;
        $output .= '<a href="?' . http_build_query($queryParams) . '" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-mai-brown hover:text-white transition-colors">السابق</a>';
    } else {
        $output .= '<span class="px-3 py-1 bg-gray-100 text-gray-400 rounded-md cursor-not-allowed">السابق</span>';
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        $queryParams[$paramName] = $i;
        if ($i == $currentPage) {
            $output .= '<span class="px-3 py-1 bg-mai-brown text-white rounded-md font-bold">' . $i . '</span>';
        } else {
            $output .= '<a href="?' . http_build_query($queryParams) . '" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-mai-brown hover:text-white transition-colors">' . $i . '</a>';
        }
    }
    if ($currentPage < $totalPages) {
        $queryParams[$paramName] = $currentPage + 1;
        $output .= '<a href="?' . http_build_query($queryParams) . '" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-mai-brown hover:text-white transition-colors">التالي</a>';
    } else {
        $output .= '<span class="px-3 py-1 bg-gray-100 text-gray-400 rounded-md cursor-not-allowed">التالي</span>';
    }
    $output .= '</nav>';
    return $output;
}

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md mb-6 p-4" data-aos="fade-down">
  <div class="flex flex-col md:flex-row items-center justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-mai-dark">لوحة التحكم</h1>
    </div>

    <div class="flex items-center gap-2">
      <form method="GET" class="flex items-center gap-1.5 bg-gray-100 p-1.5 rounded-md">
        <select
          name="filter_type"
          class="bg-transparent border-0 text-gray-700 text-xs font-semibold focus:ring-0"
          onchange="this.form.submit()"
        >
          <option value="day" <?= $filter_type == 'day' ? 'selected' : '' ?>>يومي</option>
          <option value="month" <?= $filter_type == 'month' ? 'selected' : '' ?>>شهري</option>
        </select>

        <?php if ($filter_type == 'day'): ?>
          <input
            type="date"
            name="filter_value"
            value="<?= htmlspecialchars($filter_value) ?>"
            class="bg-transparent border-0 text-gray-700 text-xs font-semibold focus:ring-0 p-0.5 rounded"
            onchange="this.form.submit()"
          >
        <?php elseif ($filter_type == 'month'): ?>
          <input
            type="month"
            name="filter_value"
            value="<?= htmlspecialchars($filter_value) ?>"
            class="bg-transparent border-0 text-gray-700 text-xs font-semibold focus:ring-0 p-0.5 rounded"
            onchange="this.form.submit()"
          >
        <?php endif; ?>
      </form>

      <form method="GET" action="">
          <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filter_type) ?>">
          <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
          <button
            type="submit"
            title="تحديث البيانات"
            class="bg-mai-dark text-white w-9 h-9 rounded-md hover:bg-black transition-colors shadow-sm hover:shadow-md flex items-center justify-center"
          >
            <i class="fas fa-sync-alt text-sm"></i>
          </button>
      </form>
    </div>
  </div>
</div>

<!-- START: PENDING APPOINTMENTS ALERT -->
<?php if ($pending_count > 0): ?>
    <div x-data="{ show: true }" x-show="show" x-transition
        class="bg-yellow-50 border-r-4 border-yellow-400 text-yellow-800 p-4 rounded-lg shadow-sm flex items-center justify-between mb-6"
        role="alert" data-aos="fade-down" data-aos-delay="50">
        <div>
            <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>تنبيه: يوجد لديك <?= $pending_count ?> موعداً معلقاً</p>
            <p>هذه المواعيد تتطلب تأكيداً أو إجراءً منك.</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="bookings.php?status=pending"
                class="bg-yellow-400 text-white font-semibold px-4 py-2 rounded-lg hover:bg-yellow-500 transition-colors text-sm">
                عرض المواعيد المعلقة
            </a>
            <button @click="show = false" class="text-yellow-600 hover:text-yellow-800 text-xl">&times;</button>
        </div>
    </div>
<?php endif; ?>
<!-- END: PENDING APPOINTMENTS ALERT -->


<!-- Main KPIs Grid -->
<?php if (hasPermission('dashboard.view_kpis')): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-6">
    <?= render_kpi_card('إجمالي المبيعات', number_format($kpis['gross_sales'], 2), 'ر.س', '', 'border-green-500', 0) ?>
    <?= render_kpi_card('صافي الربح', number_format($kpis['net_profit'], 2), 'ر.س', '', 'border-teal-500', 100) ?>
    <?= render_kpi_card('إجمالي الضريبة', number_format($kpis['total_tax'], 2), 'ر.س', '', 'border-cyan-500', 150) ?>
    <?= render_kpi_card('المصروفات', number_format($kpis['expenses'], 2), 'ر.س', '', 'border-red-500', 200) ?>
    <?= render_kpi_card('المرتجعات', number_format($kpis['returns'], 2), 'ر.س', '', 'border-orange-500', 300) ?>
</div>

<!-- EDITED: Added Bank Transfer card and adjusted grid columns -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <?= render_kpi_card('البيع نقدًا', number_format($kpis['cash_sales'], 2), 'ر.س', '', 'border-blue-500', 0) ?>
    <?= render_kpi_card('البيع بطاقة', number_format($kpis['card_sales'], 2), 'ر.س', '', 'border-purple-500', 100) ?>
    <?= render_kpi_card('البيع تحويل', number_format($kpis['transfer_sales'], 2), 'ر.س', '', 'border-yellow-500', 200) ?>
    <?= render_kpi_card('عدد الفواتير', $kpis['invoice_count'], '', '', 'border-indigo-500', 300) ?>
</div>
<?php endif; ?>


<!-- Secondary KPIs (Today Only) -->
<?php if ($filter_type == 'day' && ($filter_value == date('Y-m-d') || empty($filter_value)) && hasPermission('dashboard.view_kpis')): ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow-lg p-5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1.5 border-l-8 border-cyan-400" data-aos="fade-up" data-aos-delay="0"><i class="fas fa-user-plus text-3xl text-cyan-500"></i><div><p class="text-3xl font-bold text-mai-dark-brown"><?= $kpis['new_customers'] ?></p><p class="text-sm text-gray-500 mt-1">عميل جديد</p></div></div>
    <div class="bg-white rounded-2xl shadow-lg p-5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1.5 border-l-8 border-sky-400" data-aos="fade-up" data-aos-delay="100"><i class="fas fa-calendar-day text-3xl text-sky-500"></i><div><p class="text-3xl font-bold text-mai-dark-brown"><?= $kpis['upcoming_appointments'] ?></p><p class="text-sm text-gray-500 mt-1">موعد لليوم</p></div></div>
    <div class="bg-white rounded-2xl shadow-lg p-5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1.5 border-l-8 border-amber-400" data-aos="fade-up" data-aos-delay="200"><i class="fas fa-box-open text-3xl text-amber-500"></i><div><p class="text-3xl font-bold text-mai-dark-brown"><?= $kpis['low_stock_items'] ?></p><p class="text-sm text-gray-500 mt-1">نقص بالمخزون</p></div></div>
    <div class="bg-white rounded-2xl shadow-lg p-5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1.5 border-l-8 border-rose-400" data-aos="fade-up" data-aos-delay="300"><i class="fas fa-store text-3xl text-rose-500"></i><div><p class="text-3xl font-bold text-mai-dark-brown"><?= $kpis['open_shifts'] ?></p><p class="text-sm text-gray-500 mt-1">وردية مفتوحة</p></div></div>
</div>
<?php endif; ?>

<!-- Today's Live Lists -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <?php if (hasPermission('dashboard.view_appointments_table')): ?>
    <div class="bg-white rounded-2xl shadow-lg p-6 flex flex-col" data-aos="fade-up">
        <h3 class="text-xl font-bold text-mai-dark mb-5 flex items-center gap-3"><i class="fas fa-calendar-check text-mai-brown"></i>قائمة المواعيد</h3>
        <div class="space-y-3 pr-2 flex-grow">
            <?php if(empty($appointments_data['records'])): ?><div class="text-center py-8 flex flex-col justify-center items-center h-full"><i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i><p class="text-gray-500">لا توجد مواعيد لهذا اليوم.</p></div><?php else: ?><?php foreach($appointments_data['records'] as $appt): ?>
                <div class="flex items-center p-4 bg-mai-cream/50 rounded-lg border-r-4 border-mai-gold">
                    <div class="flex-grow">
                        <p class="font-bold text-mai-dark-brown"><?= htmlspecialchars($appt['customer_name'] ?? 'عميل') ?></p>
                        <p class="text-sm text-gray-500">الهاتف: <?= htmlspecialchars($appt['customer_phone']) ?></p>
                        <p class="text-sm text-gray-500">الخدمة: <?= htmlspecialchars($appt['service_name'] ?? 'N/A') ?></p>
                        <p class="text-sm text-gray-500">الأخصائي: <?= htmlspecialchars($appt['specialist_name'] ?? 'N/A') ?></p>
                    </div>
                    <div class="text-right font-semibold text-mai-brown text-lg" dir="ltr"><?= date('h:i A', strtotime($appt['start_at'])) ?></div>
                </div>
            <?php endforeach; ?><?php endif; ?>
        </div>
        <?php echo render_pagination_links($page_appts, $total_pages_appts, 'page_appts'); ?>
    </div>
    <?php endif; ?>

    <?php if (hasPermission('dashboard.view_invoices_table')): ?>
    <div class="bg-white rounded-2xl shadow-lg p-6 flex flex-col" data-aos="fade-up" data-aos-delay="100">
        <h3 class="text-xl font-bold text-mai-dark mb-5 flex items-center gap-3"><i class="fas fa-file-invoice text-mai-brown"></i>الفواتير</h3>
        <div class="space-y-3 pr-2 flex-grow">
            <?php if(empty($invoices_data['records'])): ?><div class="text-center py-8 flex flex-col justify-center items-center h-full"><i class="fas fa-receipt text-4xl text-gray-300 mb-3"></i><p class="text-gray-500">لا توجد فواتير صادرة في هذا اليوم.</p></div><?php else: ?><?php foreach($invoices_data['records'] as $inv): ?>
                <div class="flex items-center p-4 bg-green-50 rounded-lg border-r-4 border-green-500">
                    <div class="flex-grow">
                        <p class="font-bold text-mai-dark-brown"><?= htmlspecialchars($inv['customer_name'] ?? 'عميل عابر') ?> (<?= htmlspecialchars($inv['customer_phone'] ?? 'N/A') ?>)</p>
                        <!-- START: EDITED INVOICE DISPLAY -->
                        <p class="text-sm text-gray-500">فاتورة #<?= htmlspecialchars($inv['invoice_number']) ?> | <span class="font-medium text-cyan-700">الضريبة: <?= number_format($inv['tax_amount'], 2) ?> ر.س</span></p>
                        <!-- END: EDITED INVOICE DISPLAY -->
                    </div>
                    <div class="text-right font-semibold text-green-600 text-lg"><?= number_format($inv['total_amount'], 2) ?> <span class="text-sm">ر.س</span></div>
                </div>
            <?php endforeach; ?><?php endif; ?>
        </div>
        <?php echo render_pagination_links($page_invs, $total_pages_invs, 'page_invs'); ?>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
// The following line for AlpineJS is added to support the dismissible alert
$page_specific_head = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"><script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script><style>@import url(\'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap\'); body { font-family: \'Tajawal\', sans-serif; background-color: #f9fafb; }</style>';
$page_specific_footer_scripts = '<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script><script>AOS.init({ duration: 600, once: true, offset: 20 });</script>';
include 'layouts/responsive_base.php';
?>