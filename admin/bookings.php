<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';
checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// --- PERMISSION CHECK: Page Access ---
requirePermission('bookings.view');

// --- UTILITY & LOYALTY FUNCTIONS ---

/**
 * Normalizes a Saudi phone number to its 9-digit format starting with 5.
 * @param string $phone The raw phone number.
 * @return string|null The normalized 9-digit number or null if invalid.
 */
function normalizeSaPhone($phone)
{
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) > 9) {
        $phone = substr($phone, -9);
    }
    return (substr($phone, 0, 1) === '5' && strlen($phone) == 9) ? $phone : null;
}

/**
 * Finds a customer by phone or creates a new one.
 * EDITED: Now searches for all phone formats to avoid creating duplicate customers.
 */
function findOrCreateCustomerByPhone($db, $name, $phone, $email = null)
{
    // Step 1: Normalize the phone number to the 9-digit format (5xxxxxxxx)
    $normalizedPhone = normalizeSaPhone($phone);
    if (empty($normalizedPhone)) {
        return null;
    }

    // We will search for all common formats to find an existing customer
    $phoneWithZero = '0' . $normalizedPhone;
    $phoneIntl = '966' . $normalizedPhone;
    $phoneIntlPlus = '+966' . $normalizedPhone;

    // Step 2: Try to find an existing customer using ALL possible phone formats
    $stmt = $db->prepare("SELECT id FROM customers WHERE phone IN (?, ?, ?, ?) LIMIT 1");
    $stmt->execute([$normalizedPhone, $phoneWithZero, $phoneIntl, $phoneIntlPlus]);
    $customerId = $stmt->fetchColumn();

    if ($customerId) {
        // Step 3a: Customer exists. Update their name if a new one was provided.
        if (!empty($name)) {
            $update_stmt = $db->prepare("UPDATE customers SET name = ?, name_ar = ? WHERE id = ?");
            $update_stmt->execute([$name, $name, $customerId]);
        }
        return $customerId;
    } else {
        // Step 3b: Customer does not exist. Create a new one.
        $emailToInsert = !empty($email) ? $email : null;

        $customer_code = 'CUS' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $qr_token = uniqid('cus_', true);

        // The phone number is always saved in the standardized '05...' format for consistency in the customers table.
        $stmt = $db->prepare(
            "INSERT INTO customers (name, name_ar, phone, email, customer_code, qr_token, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())"
        );
        $stmt->execute([$name, $name, $phoneWithZero, $emailToInsert, $customer_code, $qr_token]);

        return $db->lastInsertId();
    }
}


function processLoyaltyPoints($db, $customerId, $totalAmount, $referenceId, $referenceType)
{
    if (!$customerId || $totalAmount <= 0)
        return;
    try {
        $rules_stmt = $db->query("SELECT * FROM loyalty_rules ORDER BY id DESC LIMIT 1");
        $rules = $rules_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rules)
            return;

        $points_per_sar = $rules['points_per_sar'] ?? 1;
        $min_purchase_for_points = $rules['min_purchase_for_points'] ?? 0;
        if ($totalAmount < $min_purchase_for_points)
            return;

        $earned_points = floor($totalAmount * $points_per_sar);
        if ($earned_points <= 0)
            return;

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

// --- CRUD ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_appointment':
                if (!hasPermission('bookings.create')) {
                    throw new Exception('ليس لديك الصلاحية لإنشاء حجوزات.');
                }

                $db->beginTransaction();

                $customer_name = trim($_POST['customer_name'] ?? '');
                $customer_phone = trim($_POST['customer_phone'] ?? ''); // This holds the original phone number
                $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
                $specialist_id = filter_input(INPUT_POST, 'specialist_id', FILTER_VALIDATE_INT);
                $appointment_date = trim($_POST['appointment_date'] ?? '');
                $appointment_time = trim($_POST['appointment_time'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                if (empty($customer_name) || empty($customer_phone) || !$service_id || !$specialist_id || empty($appointment_date) || empty($appointment_time)) {
                    throw new Exception("الرجاء ملء جميع الحقول المطلوبة: اسم العميل، الهاتف، الخدمة، الأخصائي، التاريخ، والوقت.");
                }

                $cleaned_phone = preg_replace('/\D/', '', $customer_phone);
                if (!preg_match('/^(9665[0-9]{8}|05[0-9]{8}|5[0-9]{8})$/', $cleaned_phone)) {
                    throw new Exception('رقم الجوال غير صحيح. يجب أن يكون بتنسيق سعودي صحيح (مثال: 0512345678 أو +966512345678).');
                }

                // Normalization is still needed for lookup, but not for saving
                $normalizedPhone = normalizeSaPhone($customer_phone);
                if (!$normalizedPhone) {
                    throw new Exception('حدث خطأ غير متوقع أثناء معالجة رقم الجوال.');
                }

                $start_datetime = new DateTime($appointment_date . ' ' . $appointment_time);
                $day_of_week_name = strtolower($start_datetime->format('l'));

                $tomorrow_obj = new DateTime('tomorrow');
                $tomorrow_obj->setTime(0, 0, 0);
                if ($start_datetime < $tomorrow_obj) {
                    throw new Exception('لا يمكن الحجز في نفس اليوم أو في الماضي. يجب أن يكون الحجز ليوم غد على الأقل.');
                }

                $settings_stmt = $db->query("SELECT default_buffer FROM booking_settings WHERE id = 1");
                $default_buffer = $settings_stmt->fetchColumn() ?? 20;

                $service_stmt = $db->prepare("SELECT duration, price, buffer_time FROM services WHERE id = ? AND is_active = 1");
                $service_stmt->execute([$service_id]);
                $service_details = $service_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$service_details)
                    throw new Exception('الخدمة المحددة غير صالحة أو غير متوفرة.');

                $duration = $service_details['duration'];
                $service_price = $service_details['price'];
                $buffer = $service_details['buffer_time'] ?? $default_buffer;
                $end_datetime = (clone $start_datetime)->modify("+{$duration} minutes");

                $working_hours = null;
                $spec_hours_stmt = $db->prepare("SELECT open_time, close_time, is_working FROM specialist_working_hours WHERE specialist_id = ? AND day_of_week = ?");
                $spec_hours_stmt->execute([$specialist_id, $day_of_week_name]);
                $specialist_schedule = $spec_hours_stmt->fetch(PDO::FETCH_ASSOC);

                if ($specialist_schedule && $specialist_schedule['is_working'] == 1) {
                    $working_hours = ['open' => $specialist_schedule['open_time'], 'close' => $specialist_schedule['close_time']];
                }

                if (!$working_hours)
                    throw new Exception('لا توجد ساعات عمل محددة لهذا اليوم.');

                $day_open_time = new DateTime($appointment_date . ' ' . $working_hours['open']);
                $day_close_time = new DateTime($appointment_date . ' ' . $working_hours['close']);

                if ($start_datetime < $day_open_time || $end_datetime > $day_close_time) {
                    throw new Exception('الوقت المحدد خارج ساعات العمل الرسمية.');
                }

                $end_with_buffer = (clone $end_datetime)->modify("+{$buffer} minutes");
                $appt_stmt = $db->prepare("SELECT a.start_at, a.end_at, COALESCE(s.buffer_time, ?) as buffer FROM appointments a LEFT JOIN services s ON a.service_id = s.id WHERE a.specialist_id = ? AND DATE(a.start_at) = ? AND a.status IN ('pending', 'confirmed') AND a.id != ?");
                $appt_stmt->execute([$default_buffer, $specialist_id, $appointment_date, $_POST['id'] ?? 0]);

                foreach ($appt_stmt->fetchAll(PDO::FETCH_ASSOC) as $appt) {
                    $existing_start = new DateTime($appt['start_at']);
                    $existing_end_with_buffer = (new DateTime($appt['end_at']))->modify("+{$appt['buffer']} minutes");
                    if ($start_datetime < $existing_end_with_buffer && $end_with_buffer > $existing_start) {
                        throw new Exception('عذراً، هذا الوقت لم يعد متاحاً. يرجى اختيار وقت آخر.');
                    }
                }

                $customerId = findOrCreateCustomerByPhone($db, $customer_name, $customer_phone, null);

                if (!$customerId) {
                    throw new Exception("لم نتمكن من العثور على العميل أو إنشاءه. يرجى التحقق من رقم الهاتف.");
                }

                $query = "INSERT INTO appointments (customer_id, customer_name, customer_phone, service_id, specialist_id, start_at, end_at, status, notes, source, created_by, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'internal', ?, ?)";
                $stmt = $db->prepare($query);

                // --- START: MODIFIED LINE ---
                // Save the ORIGINAL phone number ($customer_phone) from the form directly into the table.
                $stmt->execute([$customerId, $customer_name, $customer_phone, $service_id, $specialist_id, $start_datetime->format('Y-m-d H:i:s'), $end_datetime->format('Y-m-d H:i:s'), 'confirmed', $notes, $admin['id'], $service_price]);
                // --- END: MODIFIED LINE ---

                $appointmentId = $db->lastInsertId();

                if ($customerId && $service_price > 0) {
                    processLoyaltyPoints($db, $customerId, $service_price, $appointmentId, 'appointment');
                }

                $db->commit();
                $_SESSION['message'] = "تم إنشاء الموعد بنجاح.";
                break;

            case 'update_status':
                if (!hasPermission('bookings.edit')) {
                    throw new Exception('ليس لديك الصلاحية لتعديل الحجوزات.');
                }
                $query = "UPDATE appointments SET status = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['status'], $_POST['id']]);
                $_SESSION['message'] = "تم تحديث حالة الموعد بنجاح.";
                break;

            case 'delete':
                if (!hasPermission('bookings.delete')) {
                    throw new Exception('ليس لديك الصلاحية لحذف الحجوزات.');
                }
                $query = "DELETE FROM appointments WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['id']]);
                $_SESSION['message'] = "تم حذف الموعد بنجاح.";
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction())
            $db->rollBack();
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
    $status_redirect = $_POST['status_redirect'] ?? 'all';
    header("Location: " . $_SERVER['PHP_SELF'] . '?status=' . $status_redirect);
    exit;
}

// --- START: DATA FETCHING FOR DISPLAY ---
try {
    // --- START: ADDED QUERY FOR PENDING APPOINTMENTS ALERT ---
    $pending_count_stmt = $db->query("SELECT COUNT(id) FROM appointments WHERE status = 'pending'");
    $pending_count = $pending_count_stmt->fetchColumn();
    // --- END: ADDED QUERY FOR PENDING APPOINTMENTS ALERT ---

    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    $valid_statuses = ['pending', 'confirmed', 'done', 'no_show', 'cancelled'];
    $status_filter = isset($_GET['status']) && in_array($_GET['status'], $valid_statuses) ? $_GET['status'] : 'all';
    $where_clause = "";
    $params = [];
    if ($status_filter !== 'all') {
        $where_clause = "WHERE a.status = :status";
        $params[':status'] = $status_filter;
    }
    $count_query = "SELECT COUNT(id) FROM appointments a " . $where_clause;
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $totalAppointments = $count_stmt->fetchColumn();
    $totalPages = ceil($totalAppointments / $perPage);

    // --- QUERY REVERTED TO ORIGINAL STATE AS REQUESTED ---
    $query = "SELECT 
                a.*, 
                s.name_ar AS service_name, 
                t.name_ar AS specialist_name 
              FROM appointments a
              LEFT JOIN services s ON a.service_id = s.id
              LEFT JOIN team_members t ON a.specialist_id = t.id
              $where_clause
              ORDER BY a.start_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (isset($_GET['json']) && $_GET['json'] == 'true') {
        header('Content-Type: application/json');
        echo json_encode(['appointments' => $appointments, 'currentPage' => $page, 'totalPages' => $totalPages]);
        exit;
    }
    $services = $db->query("SELECT id, name_ar FROM services WHERE is_active = 1 ORDER BY name_ar ASC")->fetchAll(PDO::FETCH_ASSOC);
    $specialists = $db->query("SELECT id, name_ar FROM team_members WHERE is_specialist = 1 AND is_active = 1 ORDER BY name_ar ASC")->fetchAll(PDO::FETCH_ASSOC);

    $customers_query = "SELECT 
                            id, 
                            COALESCE(NULLIF(name_ar, ''), NULLIF(name_en, ''), name) AS name, 
                            phone 
                        FROM customers 
                        ORDER BY name ASC";
    $customers = $db->query($customers_query)->fetchAll(PDO::FETCH_ASSOC);

    // --- ENHANCED HOLIDAY FETCHING FOR FRONTEND ---
    $current_year = date('Y');
    $next_year = $current_year + 1;

    $non_recurring_q = $db->query("SELECT date FROM holidays WHERE is_recurring = 0");
    $disabled_dates = $non_recurring_q->fetchAll(PDO::FETCH_COLUMN, 0);

    $recurring_q = $db->query("SELECT DATE_FORMAT(date, '%m-%d') AS month_day FROM holidays WHERE is_recurring = 1");
    $recurring_month_days = $recurring_q->fetchAll(PDO::FETCH_COLUMN, 0);

    foreach ($recurring_month_days as $md) {
        $disabled_dates[] = $current_year . '-' . $md;
        $disabled_dates[] = $next_year . '-' . $md;
    }
    $disabled_dates = array_values(array_unique($disabled_dates));

    $weekly_off_days_q = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'weekly_days_off'");
    $weekly_off_days = explode(',', $weekly_off_days_q->fetchColumn() ?: '');

} catch (Exception $e) {
    $appointments = [];
    $services = [];
    $specialists = [];
    $customers = [];
    $disabled_dates = [];
    $weekly_off_days = [];
    $totalPages = 1;
    $page = 1;
    $status_filter = 'all';
    $pending_count = 0;
    $_SESSION['error'] = "خطأ في جلب البيانات: " . $e->getMessage();
}

// --- PREPARE INITIAL STATE FOR ALPINE.JS ---
$initialState = [
    'appointments' => $appointments,
    'currentPage' => $page,
    'totalPages' => $totalPages,
    'customers' => $customers,
    'disabledDates' => $disabled_dates,
    'weeklyOffDays' => $weekly_off_days,
    'currentStatus' => $status_filter
];
$page_title = 'التقويم والحجوزات';
$page_subtitle = 'إدارة وعرض جميع المواعيد';
ob_start();
?>

<!-- Main Alpine.js Component -->
<div x-data="appointmentManager(<?= htmlspecialchars(json_encode($initialState)) ?>)" class="space-y-6">

    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow-md p-5 flex items-center justify-between" data-aos="fade-down">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-mai-brown/10 text-mai-brown rounded-lg flex items-center justify-center text-xl">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-800"><?= $page_title ?></h1>
                <p class="text-sm text-gray-500"><?= $page_subtitle ?></p>
            </div>
        </div>
        <?php if (hasPermission('bookings.create')): ?>
            <button type="button" @click="openAddModal()"
                class="bg-mai-brown text-white font-semibold px-5 py-2.5 rounded-lg hover:bg-mai-dark-brown transition-colors shadow-sm hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-mai-brown focus:ring-offset-2">
                <i class="fas fa-plus mr-2"></i>حجز جديد
            </button>
        <?php endif; ?>
    </div>

    <!-- Session Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)"
            class="bg-green-50 border-r-4 border-green-400 text-green-800 p-4 rounded-lg shadow-sm" role="alert">
            <p class="font-bold">نجاح</p>
            <p><?= htmlspecialchars($_SESSION['message']) ?></p>
        </div>
        <?php unset($_SESSION['message']); endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 8000)"
            class="bg-red-50 border-r-4 border-red-400 text-red-800 p-4 rounded-lg shadow-sm" role="alert">
            <p class="font-bold">خطأ</p>
            <p><?= htmlspecialchars($_SESSION['error']) ?></p>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

    <!-- START: PENDING APPOINTMENTS ALERT -->
    <!--<?php if ($pending_count > 0): ?>-->
    <!--    <div x-data="{ show: true }" x-show="show" x-transition-->
    <!--        class="bg-yellow-50 border-r-4 border-yellow-400 text-yellow-800 p-4 rounded-lg shadow-sm flex items-center justify-between"-->
    <!--        role="alert" data-aos="fade-down" data-aos-delay="50">-->
    <!--        <div>-->
    <!--            <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>تنبيه: يوجد لديك <?= $pending_count ?>-->
    <!--                موعداً معلقاً</p>-->
    <!--            <p>هذه المواعيد تتطلب تأكيداً أو إجراءً منك.</p>-->
    <!--        </div>-->
    <!--        <div class="flex items-center gap-4">-->
    <!--            <button-->
    <!--                @click="switchTab('pending'); document.getElementById('filters-container').scrollIntoView({ behavior: 'smooth' })"-->
    <!--                class="bg-yellow-400 text-white font-semibold px-4 py-2 rounded-lg hover:bg-yellow-500 transition-colors text-sm">-->
    <!--                عرض المواعيد المعلقة-->
    <!--            </button>-->
    <!--            <button @click="show = false" class="text-yellow-600 hover:text-yellow-800 text-xl">&times;</button>-->
    <!--        </div>-->
    <!--    </div>-->
    <!--<?php endif; ?>-->
    <!-- END: PENDING APPOINTMENTS ALERT -->

    <!-- Status Tabs & Filters Container -->
    <div id="filters-container" class="bg-white rounded-xl shadow-md" data-aos="fade-up">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6 px-6" aria-label="Tabs">
                <button @click="switchTab('all')"
                    :class="currentStatus === 'all' ? 'border-mai-brown text-mai-brown' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition-colors">الكل</button>
                <button @click="switchTab('pending')"
                    :class="currentStatus === 'pending' ? 'border-mai-brown text-mai-brown' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition-colors">معلق</button>
                <button @click="switchTab('confirmed')"
                    :class="currentStatus === 'confirmed' ? 'border-mai-brown text-mai-brown' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition-colors">مؤكد</button>
                <button @click="switchTab('done')"
                    :class="currentStatus === 'done' ? 'border-mai-brown text-mai-brown' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition-colors">منجز</button>
                <button @click="switchTab('no_show')"
                    :class="currentStatus === 'no_show' ? 'border-mai-brown text-mai-brown' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition-colors">لم
                    يحضر</button>
                <button @click="switchTab('cancelled')"
                    :class="currentStatus === 'cancelled' ? 'border-mai-brown text-mai-brown' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition-colors">ملغي</button>
            </nav>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">فلترة بالخدمة</label>
                    <select x-model="filters.service"
                        class="w-full bg-gray-50 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-mai-brown focus:border-mai-brown">
                        <option value="">جميع الخدمات</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= $service['id']; ?>"><?= htmlspecialchars($service['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">من تاريخ</label>
                    <input type="date" x-model="filters.dateFrom"
                        class="w-full bg-gray-50 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-mai-brown focus:border-mai-brown">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">إلى تاريخ</label>
                    <input type="date" x-model="filters.dateTo"
                        class="w-full bg-gray-50 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-mai-brown focus:border-mai-brown">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden" data-aos="fade-up" data-aos-delay="100">
        <div class="overflow-x-auto relative">
            <div x-show="isLoading"
                class="absolute inset-0 bg-white/70 backdrop-blur-sm flex items-center justify-center z-10" x-cloak>
                <div class="text-mai-brown"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <table class="w-full text-sm text-right text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">العميل</th>
                        <th scope="col" class="px-6 py-3">الخدمة / الأخصائي</th>
                        <th scope="col" class="px-6 py-3">التاريخ والوقت</th>
                        <th scope="col" class="px-6 py-3 text-center">الحالة</th>
                        <th scope="col" class="px-6 py-3 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="!isLoading && appointments.length === 0">
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500"><i
                                    class="fas fa-calendar-times fa-2x mb-2"></i><br>لا توجد مواعيد تطابق الفلتر الحالي.
                            </td>
                        </tr>
                    </template>
                    <template x-for="appt in appointments" :key="appt.id">
                        <tr class="bg-white border-b hover:bg-gray-50 transition-colors"
                            x-show="filterAppointment(appt)">
                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                <div x-text="appt.customer_name"></div>
                                <div class="text-xs text-gray-500" dir="ltr" x-text="appt.customer_phone"></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-800" x-text="appt.service_name || 'غير محدد'"></div>
                                <div class="text-xs text-gray-500" x-text="appt.specialist_name || 'أي أخصائي'"></div>
                            </td>
                            <td class="px-6 py-4" x-text="formatDateTime(appt.start_at)"></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="{
                                    'bg-yellow-100 text-yellow-800': appt.status === 'pending',
                                    'bg-green-100 text-green-800': appt.status === 'confirmed',
                                    'bg-blue-100 text-blue-800': appt.status === 'done',
                                    'bg-red-100 text-red-800': appt.status === 'no_show',
                                    'bg-gray-200 text-gray-800': appt.status === 'cancelled'
                                }" x-text="{
                                    'pending': 'معلق', 'confirmed': 'مؤكد', 'done': 'منجز',
                                    'no_show': 'لم يحضر', 'cancelled': 'ملغي'
                                }[appt.status] || appt.status"></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex gap-4 justify-center items-center">
                                    <button @click="sendWhatsAppMessage(appt)" title="إرسال تأكيد واتساب"
                                        class="text-gray-500 hover:text-green-600 transition-colors">
                                        <i class="fab fa-whatsapp text-lg"></i>
                                    </button>
                                    <button @click="openViewModal(appt)" title="عرض التفاصيل"
                                        class="text-gray-500 hover:text-mai-brown transition-colors"><i
                                            class="fas fa-eye"></i></button>
                                    <?php if (hasPermission('bookings.edit')): ?>
                                        <button @click="openEditStatusModal(appt.id, appt.status)" title="تغيير الحالة"
                                            class="text-gray-500 hover:text-blue-600 transition-colors"><i
                                                class="fas fa-edit"></i></button>
                                    <?php endif; ?>
                                    <?php if (hasPermission('bookings.delete')): ?>
                                        <form method="POST"
                                            onsubmit="return confirm('هل أنت متأكد من حذف هذا الموعد؟ لا يمكن التراجع عن هذا الإجراء.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" :value="appt.id">
                                            <input type="hidden" name="status_redirect" :value="currentStatus">
                                            <button type="submit" title="حذف"
                                                class="text-gray-500 hover:text-red-600 transition-colors"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION CONTROLS -->
        <div class="p-4 border-t flex items-center justify-center" x-show="totalPages > 1 && !isLoading" x-cloak>
            <nav class="flex items-center gap-2 text-sm">
                <button @click="fetchData(currentPage - 1)" :disabled="currentPage === 1"
                    class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-mai-brown hover:text-white transition-colors">السابق</button>
                <div class="px-3 py-1 bg-gray-100 rounded-md">
                    صفحة <span x-text="currentPage"></span> من <span x-text="totalPages"></span>
                </div>
                <button @click="fetchData(currentPage + 1)" :disabled="currentPage === totalPages"
                    class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-mai-brown hover:text-white transition-colors">التالي</button>
            </nav>
        </div>
    </div>

    <!-- MODALS SECTION -->

    <!-- Add/Edit Modal -->
    <div x-show="addModalOpen" x-trap.inert.noscroll="addModalOpen" @keydown.escape.window="addModalOpen = false"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" x-cloak>
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col"
            @click.outside="phoneListVisible = false; addModalOpen = false" x-show="addModalOpen"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

            <div class="p-6 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">إنشاء حجز جديد</h3>
                <button @click="addModalOpen = false"
                    class="text-gray-400 hover:text-red-500 text-2xl transition-colors">&times;</button>
            </div>

            <form method="POST" class="flex-grow overflow-y-auto">
                <input type="hidden" name="action" value="add_appointment">
                <div class="p-8 space-y-8">
                    <!-- Customer Information Section -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b"><i
                                class="fas fa-user text-gray-400 mr-2"></i>معلومات العميل</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="relative">
                                <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">رقم
                                    الهاتف *</label>
                                <input type="tel" id="customer_phone" name="customer_phone"
                                    placeholder="أدخل 0 لعرض الكل..." required
                                    class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition"
                                    x-model="newAppointment.customer_phone"
                                    @input.debounce.300ms="searchCustomerByPhone()" @focus="phoneListVisible = true"
                                    autocomplete="off">
                                <!-- Search Results Dropdown -->
                                <div x-show="phoneListVisible && phoneSearchResults.length > 0"
                                    class="absolute z-20 w-full bg-white border rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto"
                                    x-cloak @click.outside="phoneListVisible = false">
                                    <template x-for="customer in phoneSearchResults" :key="customer.id">
                                        <div @click="selectCustomer(customer)"
                                            class="cursor-pointer hover:bg-gray-100 border-b">
                                            <p class="font-semibold px-3 py-2" x-text="customer.name || 'اسم غير مسجل'">
                                            </p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">اسم
                                    العميل *</label>
                                <input type="text" id="customer_name" name="customer_name" placeholder="أدخل اسم العميل"
                                    required
                                    class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition readonly:bg-gray-100 readonly:text-gray-500"
                                    x-model="newAppointment.customer_name" :readonly="!isNewCustomer">
                            </div>
                        </div>
                    </div>
                    <!-- Appointment Details Section -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b"><i
                                class="fas fa-cut text-gray-400 mr-2"></i>تفاصيل الموعد</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الخدمة *</label>
                                <select name="service_id" required x-model="newAppointment.service_id"
                                    @change="fetchAvailableTimes()"
                                    class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition">
                                    <option value="">-- اختر خدمة --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['name_ar']) ?>
                                        </option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الأخصائي *</label>
                                <select name="specialist_id" required x-model="newAppointment.specialist_id"
                                    @change="fetchAvailableTimes()"
                                    class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition">
                                    <option value="">-- اختر أخصائي --</option>
                                    <?php foreach ($specialists as $specialist): ?>
                                        <option value="<?= $specialist['id'] ?>">
                                            <?= htmlspecialchars($specialist['name_ar']) ?>
                                        </option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الموعد *</label>
                                <input type="date" name="appointment_date" required
                                    class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition"
                                    :min="tomorrowDateString()" x-model="newAppointment.date"
                                    @change="validateDate(); fetchAvailableTimes()">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">وقت الموعد *</label>
                                <select name="appointment_time" required
                                    :disabled="timeSlotsLoading || availableTimeSlots.length === 0"
                                    class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition disabled:bg-gray-100">
                                    <template x-if="timeSlotsLoading">
                                        <option value="">جاري تحميل الأوقات المتاحة...</option>
                                    </template>
                                    <template x-if="!timeSlotsLoading && availableTimeSlots.length > 0">
                                        <option value="">-- اختر الوقت --</option>
                                    </template>
                                    <template x-if="!timeSlotsLoading && availableTimeSlots.length === 0">
                                        <option value="">لا توجد أوقات متاحة في هذا اليوم</option>
                                    </template>
                                    <template x-for="timeSlot in availableTimeSlots" :key="timeSlot">
                                        <option :value="timeSlot" x-text="timeSlot"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                                <textarea name="notes" placeholder="أي تفاصيل إضافية..."
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition"
                                    rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 border-t flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" @click="addModalOpen = false"
                        class="px-6 py-2.5 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors">إلغاء</button>
                    <button type="submit"
                        class="px-6 py-2.5 bg-mai-brown text-white font-bold rounded-lg hover:bg-mai-dark-brown transition-colors">حفظ
                        الموعد</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div x-show="editStatusModal.isOpen" x-trap.inert.noscroll="editStatusModal.isOpen"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" x-cloak>
        <div class="bg-white rounded-xl max-w-md w-full" @click.outside="editStatusModal.isOpen = false"
            x-show="editStatusModal.isOpen" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">
            <div class="p-5 border-b">
                <h3 class="text-lg font-bold text-gray-800">تغيير حالة الحجز</h3>
            </div>
            <form method="POST">
                <div class="p-6">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" :value="editStatusModal.id">
                    <input type="hidden" name="status_redirect" :value="currentStatus">
                    <select name="status" :value="editStatusModal.currentStatus"
                        class="w-full py-2.5 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-mai-brown/50 focus:border-mai-brown transition">
                        <option value="pending">معلق</option>
                        <option value="confirmed">مؤكد</option>
                        <option value="done">منجز</option>
                        <option value="no_show">لم يحضر</option>
                        <option value="cancelled">ملغي</option>
                    </select>
                </div>
                <div class="p-4 bg-gray-50 flex justify-end gap-3 rounded-b-xl">
                    <button type="button" @click="editStatusModal.isOpen = false"
                        class="px-5 py-2 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-colors">إلغاء</button>
                    <button type="submit"
                        class="px-5 py-2 bg-mai-brown text-white font-bold rounded-lg hover:bg-mai-dark-brown transition-colors">تحديث</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div x-show="viewModal.isOpen" x-trap.inert.noscroll="viewModal.isOpen"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" x-cloak>
        <div class="bg-white rounded-xl max-w-2xl w-full" @click.outside="viewModal.isOpen = false"
            x-show="viewModal.isOpen" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">تفاصيل الحجز</h3><button @click="viewModal.isOpen = false"
                    class="text-gray-400 hover:text-red-500 text-2xl transition-colors">&times;</button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="bg-gray-50 p-3 rounded-lg"><strong>العميل:</strong> <span
                            x-text="viewModal.data.customer_name"></span></div>
                    <div class="bg-gray-50 p-3 rounded-lg"><strong>الهاتف:</strong> <span
                            x-text="viewModal.data.customer_phone"></span></div>
                    <div class="bg-gray-50 p-3 rounded-lg"><strong>الخدمة:</strong> <span
                            x-text="viewModal.data.service_name || 'N/A'"></span></div>
                    <div class="bg-gray-50 p-3 rounded-lg"><strong>الأخصائي:</strong> <span
                            x-text="viewModal.data.specialist_name || 'N/A'"></span></div>
                    <div class="bg-gray-50 p-3 rounded-lg"><strong>يبدأ في:</strong> <span
                            x-text="formatDateTime(viewModal.data.start_at)"></span></div>
                    <div class="bg-gray-50 p-3 rounded-lg"><strong>ينتهي في:</strong> <span
                            x-text="formatDateTime(viewModal.data.end_at)"></span></div>
                </div>
            </div>
        </div>
    </div>

</div> <!-- END: Main Alpine.js Component -->

<script>
    function appointmentManager(initialState) {
        return {
            // State from server
            appointments: initialState.appointments,
            currentPage: initialState.currentPage,
            totalPages: initialState.totalPages,
            allCustomers: initialState.customers,
            disabledDates: initialState.disabledDates,
            weeklyOffDays: initialState.weeklyOffDays.map(d => parseInt(d, 10)),
            currentStatus: initialState.currentStatus,

            // Local UI State
            isLoading: false,
            addModalOpen: false,
            viewModal: { isOpen: false, data: {} },
            editStatusModal: { isOpen: false, id: null, currentStatus: '' },
            filters: { service: '', dateFrom: '', dateTo: '' },

            isNewCustomer: true,
            phoneSearchResults: [],
            phoneListVisible: false,
            newAppointment: {
                customer_name: '',
                customer_phone: '',
                service_id: '',
                specialist_id: '',
                date: ''
            },
            availableTimeSlots: [],
            timeSlotsLoading: false,

            init() {
                AOS.init({ duration: 600, once: true, offset: 20 });
            },

            async fetchData(page) {
                if (page < 1 || (page > this.totalPages && this.totalPages > 0) || this.isLoading) return;
                this.isLoading = true;
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', page);
                    url.searchParams.set('status', this.currentStatus);
                    url.searchParams.set('json', 'true');

                    const response = await fetch(url.toString());
                    if (!response.ok) throw new Error('Network response was not ok');

                    const data = await response.json();
                    this.appointments = data.appointments;
                    this.currentPage = data.currentPage;
                    this.totalPages = data.totalPages;

                    const displayUrl = new URL(window.location.href);
                    displayUrl.searchParams.set('status', this.currentStatus);
                    displayUrl.searchParams.set('page', page);
                    window.history.pushState({}, '', displayUrl);

                } catch (error) {
                    console.error("Failed to fetch appointments:", error);
                } finally {
                    this.isLoading = false;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            switchTab(newStatus) {
                if (this.currentStatus === newStatus) return;
                this.currentStatus = newStatus;
                this.currentPage = 1;
                this.totalPages = 1;
                this.fetchData(1);
            },

            openAddModal() {
                this.newAppointment = { customer_name: '', customer_phone: '', service_id: '', specialist_id: '', date: '' };
                this.isNewCustomer = true;
                this.phoneSearchResults = [];
                this.phoneListVisible = false;
                this.availableTimeSlots = [];
                this.timeSlotsLoading = false;
                this.addModalOpen = true;
            },

            searchCustomerByPhone() {
                const rawInput = this.newAppointment.customer_phone.trim();
                // Normalize the search query by removing non-digits
                const query = rawInput.replace(/\D/g, '');

                if (rawInput === '0') {
                    this.phoneSearchResults = this.allCustomers;
                    this.phoneListVisible = true;
                    return;
                }

                if (query.length < 2) {
                    this.phoneSearchResults = [];
                    this.phoneListVisible = false;
                    // If the user clears the input, reset the name field if it was an existing customer
                    if (!this.isNewCustomer && this.newAppointment.customer_phone === '') {
                        this.newAppointment.customer_name = '';
                        this.isNewCustomer = true;
                    }
                    return;
                }

                this.phoneListVisible = true;
                this.phoneSearchResults = this.allCustomers.filter(customer =>
                    customer.phone && customer.phone.replace(/\D/g, '').includes(query)
                );

                // Check for an exact match to auto-fill name
                const exactMatch = this.allCustomers.find(c => {
                    if (!c.phone) return false;
                    const customerPhone = c.phone.replace(/\D/g, '');
                    // Match if '5x' equals '5x' or '05x' equals '05x'
                    return customerPhone === query || customerPhone.substr(-9) === query.substr(-9);
                });

                if (exactMatch) {
                    this.isNewCustomer = false;
                    this.newAppointment.customer_name = exactMatch.name;
                } else {
                    this.isNewCustomer = true;
                    // Don't clear the name if user is typing, only clear if no match found
                    if (this.newAppointment.customer_name && !this.isNewCustomer) {
                        this.newAppointment.customer_name = '';
                    }
                }
            },

            selectCustomer(customer) {
                this.newAppointment.customer_name = customer.name;
                this.newAppointment.customer_phone = customer.phone;
                this.isNewCustomer = false;
                this.phoneListVisible = false;
                this.phoneSearchResults = [];
            },

            tomorrowDateString() {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                return tomorrow.toISOString().split('T')[0];
            },

            async fetchAvailableTimes() {
                if (!this.newAppointment.date || !this.newAppointment.specialist_id || !this.newAppointment.service_id) {
                    this.availableTimeSlots = [];
                    return;
                }

                this.timeSlotsLoading = true;
                this.availableTimeSlots = [];

                try {
                    const params = new URLSearchParams({
                        date: this.newAppointment.date,
                        specialist_id: this.newAppointment.specialist_id,
                        service_id: this.newAppointment.service_id
                    });

                    const response = await fetch(`api/get_available_times.php?${params.toString()}`);

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Server error fetching time slots.');
                    }

                    const data = await response.json();
                    this.availableTimeSlots = data;

                } catch (error) {
                    console.error("Error fetching available times:", error);
                } finally {
                    this.timeSlotsLoading = false;
                }
            },

            validateDate() {
                if (!this.newAppointment.date) return;
                const selectedDate = new Date(this.newAppointment.date + 'T00:00:00Z');
                const dayOfWeek = selectedDate.getUTCDay();

                if (this.weeklyOffDays.includes(dayOfWeek)) {
                    alert('هذا اليوم هو يوم عطلة أسبوعية. الرجاء اختيار يوم آخر.');
                    this.newAppointment.date = '';
                    return;
                }
                if (this.disabledDates.includes(this.newAppointment.date)) {
                    alert('هذا اليوم هو عطلة رسمية. الرجاء اختيار يوم آخر.');
                    this.newAppointment.date = '';
                }
            },

            // --- WHATSAPP FUNCTIONS ---
            normalizeForWhatsApp(phone) {
                if (!phone) return '';
                let cleaned = phone.toString().replace(/\D/g, ''); // Remove non-digits
                if (cleaned.startsWith('05')) {
                    return '966' + cleaned.substring(1);
                }
                if (cleaned.startsWith('5') && cleaned.length === 9) {
                    return '966' + cleaned;
                }
                if (cleaned.startsWith('9665') && cleaned.length === 12) {
                    return cleaned;
                }
                // Fallback for numbers that might already have +966 or are in a weird format
                if (cleaned.length > 9) {
                    return cleaned;
                }
                return '966' + cleaned; // Default assumption for numbers like '551234567'
            },

  sendWhatsAppMessage(appt) {
    const phone = this.normalizeForWhatsApp(appt.customer_phone);
    if (!phone) {
        alert('رقم هاتف العميل غير صحيح.');
        return;
    }

    const formattedDate = this.formatDateTime(appt.start_at);

    const RTL = "\u202B";  // Force Right-To-Left
    const LTR = "\u202A";  // Force Left-To-Right
    const END = "\u202C";  // End formatting

    // It's a good practice to replace the emojis with their actual characters
    const message = `${RTL}مرحباً ${appt.customer_name} 👋

*تم تأكيد حجزك في* ${LTR}MAI Salon${END} ✨

*تفاصيل الموعد:*
✨ الخدمة: ${appt.service_name || 'غير محدد'}
👤 الأخصائي: ${appt.specialist_name || 'غير محدد'}
⏰ الوقت: ${formattedDate}

📍 *الموقع:*
الطائف – الأخباب، بجوار مصرف الراجحي

📞 *للاتصال:*
0554596885

الرجاء الحضور قبل الموعد بـ 10 دقائق حفاظاً على وقت الجميع.
نتطلع لخدمتك!${END}`;

    const encodedMessage = encodeURIComponent(message);
    
    // **The Fix:** Use api.whatsapp.com instead of wa.me
    const url = `https://api.whatsapp.com/send?phone=${phone}&text=${encodedMessage}`;
    
    window.open(url, '_blank');
},


            openViewModal(appt) { this.viewModal.data = appt; this.viewModal.isOpen = true; },
            openEditStatusModal(id, status) {
                this.editStatusModal.id = id;
                this.editStatusModal.currentStatus = status;
                this.editStatusModal.isOpen = true;
            },

            formatDateTime(dateTimeString) {
                if (!dateTimeString) return 'N/A';
                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                    calendar: 'gregory'
                };
                return new Date(dateTimeString).toLocaleString('ar-SA-u-nu-latn', options);
            },

            filterAppointment(appt) {
                const apptDate = appt.start_at.substring(0, 10);
                const serviceMatch = !this.filters.service || appt.service_id == this.filters.service;
                const dateFromMatch = !this.filters.dateFrom || apptDate >= this.filters.dateFrom;
                const dateToMatch = !this.filters.dateTo || apptDate <= this.filters.dateTo;
                return serviceMatch && dateFromMatch && dateToMatch;
            }
        }
    }
</script>

<?php
$page_content = ob_get_clean();
// Make sure you have Font Awesome's brand icons (fab) included in your base layout for the WhatsApp icon to appear.
$page_specific_head = '<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"><script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>';
include 'layouts/responsive_base.php';
?>