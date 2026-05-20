<?php

/**
 * Loyalty Management Dashboard - MAI Salon
 * Senior PHP/MySQL Engineer Implementation
 */

session_start();
require_once '../config/database.php';
require_once 'loyalty_engine.php';
require_once 'users_functions.php';

// Simple auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$loyalty = new LoyaltyEngine($db);
if (!hasPermission('loyalty.view')) {
    $_SESSION['error'] = 'ليس لديك الصلاحية لعرض نظام الولاء.';
    header('Location: dashboard.php');
    exit();
}
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'award_birthday_points':
            $customer_id = intval($_POST['customer_id']);
            $points = $loyalty->awardBirthdayPoints($customer_id, false);
            echo json_encode(['success' => $points > 0, 'points' => $points]);
            exit;
            
        case 'award_referral_points':
            $referrer_id = intval($_POST['referrer_id']);
            $referred_id = intval($_POST['referred_id']);
            $points = $loyalty->awardReferralPoints($referrer_id, $referred_id);
            echo json_encode(['success' => $points !== false, 'points' => $points]);
            exit;
            
        case 'award_google_review_points':
            $customer_id = intval($_POST['customer_id']);
            $rating = intval($_POST['rating']);
            $points = $loyalty->awardGoogleReviewPoints($customer_id, $rating);
            echo json_encode(['success' => $points > 0, 'points' => $points]);
            exit;
            
        case 'manual_points_adjustment':
            $customer_id = intval($_POST['customer_id']);
            $points = intval($_POST['points']);
            $description = $_POST['description'] ?? 'تعديل يدوي للنقاط';
            
            $stmt = $db->prepare("
                INSERT INTO loyalty_transactions 
                (customer_id, points, transaction_type, earning_type, description_ar, admin_id, created_at)
                VALUES (?, ?, 'earn', 'manual', ?, ?, NOW())
            ");
            $success = $stmt->execute([$customer_id, $points, $description, $_SESSION['admin_id'] ?? 1]);
            
            if ($success) {
                $stmt = $db->prepare("
                    UPDATE loyalty_wallets 
                    SET points = points + ?, last_activity_at = NOW()
                    WHERE customer_id = ?
                ");
                $stmt->execute([$points, $customer_id]);
                $loyalty->updateCustomerTier($customer_id);
            }
            
            echo json_encode(['success' => $success]);
            exit;

        case 'process_inactivity':
            // Run inactivity downgrade process via engine
            try {
                $affected = $loyalty->processInactivityDowngrades();
                echo json_encode(['success' => true, 'affected' => $affected]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get loyalty statistics
$stats = $loyalty->getLoyaltyStats();

// Get loyalty rules
$rules_stmt = $db->prepare("SELECT * FROM loyalty_rules WHERE is_active = 1 ORDER BY rule_key");
$rules_stmt->execute();
$loyalty_rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get loyalty tiers
$tiers_stmt = $db->prepare("SELECT * FROM loyalty_tiers WHERE is_active = 1 ORDER BY sort_order");
$tiers_stmt->execute();
$loyalty_tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top customers by points
$top_customers_stmt = $db->prepare("
    SELECT lw.*, c.name_ar as name, c.phone, c.email, lt.name_ar as tier_name, lt.tier_color
    FROM loyalty_wallets lw
    JOIN customers c ON lw.customer_id = c.id
    JOIN loyalty_tiers lt ON lw.tier_id = lt.id
    ORDER BY lw.points DESC
    LIMIT 20
");
$top_customers_stmt->execute();
$top_customers = $top_customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- START: PAGINATION LOGIC FOR RECENT TRANSACTIONS ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 25; // Number of transactions per page
$offset = ($page - 1) * $items_per_page;

// Get total number of transactions for pagination
$total_transactions_stmt = $db->prepare("SELECT COUNT(*) FROM loyalty_transactions");
$total_transactions_stmt->execute();
$total_transactions = $total_transactions_stmt->fetchColumn();
$total_pages = ceil($total_transactions / $items_per_page);

// Get recent transactions with pagination
$recent_transactions_stmt = $db->prepare("
    SELECT lt.*, c.name_ar as customer_name, c.phone
    FROM loyalty_transactions lt
    JOIN customers c ON lt.customer_id = c.id
    ORDER BY lt.created_at DESC
    LIMIT :limit OFFSET :offset
");
$recent_transactions_stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$recent_transactions_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$recent_transactions_stmt->execute();
$recent_transactions = $recent_transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
// --- END: PAGINATION LOGIC ---


// Get customers with upcoming birthdays (next 30 days)
$upcoming_birthdays_stmt = $db->prepare("
    SELECT c.id, c.name_ar as name, c.phone, c.email, c.date_of_birth, lw.birthday_confirmed, lw.points
    FROM customers c
    JOIN loyalty_wallets lw ON c.id = lw.customer_id
    WHERE c.date_of_birth IS NOT NULL
    AND (
        (MONTH(c.date_of_birth) = MONTH(NOW()) AND DAY(c.date_of_birth) >= DAY(NOW()))
        OR 
        (MONTH(c.date_of_birth) = MONTH(DATE_ADD(NOW(), INTERVAL 1 MONTH)) AND DAY(c.date_of_birth) <= DAY(LAST_DAY(NOW())))
    )
    ORDER BY 
        MONTH(c.date_of_birth), 
        DAY(c.date_of_birth)
    LIMIT 10
");
$upcoming_birthdays_stmt->execute();
$upcoming_birthdays = $upcoming_birthdays_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'نظام الولاء';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>

    <!-- Page Header (content-level) -->
    <div class="bg-white rounded-xl shadow mb-4 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-mai-brown rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-award"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-mai-dark-brown">نظام الولاء (Loyalty)</h1>
                    <p class="text-xs text-gray-500">إدارة النقاط والمستويات ومعاملات الولاء</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="processInactivityDowngrades()" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                    <i class="fas fa-clock ml-2"></i>
                    معالجة الخمول
                </button>
                <button onclick="openManualAdjustment()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                    <i class="fas fa-plus ml-2"></i>
                    تعديل يدوي
                </button>
            </div>
        </div>
    </div>

            <!-- Statistics Cards -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">إجمالي العملاء</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_customers']) ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">النقاط الشهرية</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['monthly_points']) ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-lg">
                                <i class="fas fa-star text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($stats['by_tier'] as $tier): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($tier['name_ar']) ?></p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($tier['count']) ?></p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-lg">
                                <i class="fas fa-medal text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Loyalty Tiers -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">مستويات الولاء</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <?php foreach ($loyalty_tiers as $tier): ?>
                            <div class="tier-<?= strtolower($tier['name_en']) ?> rounded-lg p-4 text-white">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-semibold text-lg"><?= htmlspecialchars($tier['name_ar']) ?></h4>
                                        <p class="text-sm opacity-90"><?= number_format($tier['min_points']) ?> نقطة</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold"><?= $tier['discount_percent'] ?>%</p>
                                        <p class="text-sm opacity-90">خصم</p>
                                    </div>
                                </div>
                                <?php if ($tier['benefits_ar']): ?>
                                <p class="mt-2 text-sm opacity-90"><?= htmlspecialchars($tier['benefits_ar']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Top Customers -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">أفضل العملاء</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4 max-h-96 overflow-y-auto">
                                <?php foreach ($top_customers as $index => $customer): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-sm font-semibold text-blue-600">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($customer['name']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($customer['phone']) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900"><?= number_format($customer['points']) ?></p>
                                        <p class="text-sm" style="color: <?= $customer['tier_color'] ?>"><?= htmlspecialchars($customer['tier_name']) ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Birthdays -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">أعياد الميلاد القادمة</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4 max-h-96 overflow-y-auto">
                                <?php foreach ($upcoming_birthdays as $birthday): ?>
                                <div class="flex items-center justify-between p-3 bg-pink-50 rounded-lg border border-pink-100">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($birthday['name']) ?></p>
                                        <p class="text-sm text-gray-600"><?= date('d/m', strtotime($birthday['date_of_birth'])) ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php if (!$birthday['birthday_confirmed']): ?>
                                        <button onclick="awardBirthdayPoints(<?= $birthday['id'] ?>)" 
                                                class="bg-pink-600 hover:bg-pink-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                            <i class="fas fa-gift ml-1"></i>
                                            منح نقاط
                                        </button>
                                        <?php else: ?>
                                        <span class="text-green-600 text-sm">
                                            <i class="fas fa-check ml-1"></i>
                                            تم المنح
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">المعاملات الأخيرة</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">العميل</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">النقاط</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الوصف</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">لا توجد معاملات لعرضها.</td>
                                </tr>
                                <?php endif; ?>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($transaction['customer_name']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($transaction['phone']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="<?= $transaction['transaction_type'] === 'earn' ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                                            <?= $transaction['transaction_type'] === 'earn' ? '+' : '-' ?><?= number_format($transaction['points']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full <?= 
                                            $transaction['earning_type'] === 'purchase' ? 'bg-blue-100 text-blue-800' :
                                            ($transaction['earning_type'] === 'referral' ? 'bg-purple-100 text-purple-800' :
                                            ($transaction['earning_type'] === 'birthday' ? 'bg-pink-100 text-pink-800' :
                                            ($transaction['earning_type'] === 'google_review' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')))
                                        ?>">
                                            <?= 
                                                $transaction['earning_type'] === 'purchase' ? 'شراء' :
                                                ($transaction['earning_type'] === 'referral' ? 'إحالة' :
                                                ($transaction['earning_type'] === 'birthday' ? 'عيد ميلاد' :
                                                ($transaction['earning_type'] === 'google_review' ? 'تقييم Google' : 
                                                ($transaction['earning_type'] === 'manual' ? 'يدوي' : $transaction['earning_type']))))
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= htmlspecialchars($transaction['description_ar'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- START: PAGINATION CONTROLS -->
                    <?php if ($total_pages > 1): ?>
                    <div class="p-4 border-t border-gray-200 flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            صفحة <span class="font-semibold"><?= $page ?></span> من <span class="font-semibold"><?= $total_pages ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    السابق
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>" class="px-3 py-1 border rounded-md text-sm font-medium <?= $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    التالي
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <!-- END: PAGINATION CONTROLS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Points Adjustment Modal -->
    <div id="manualAdjustmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">تعديل النقاط يدوياً</h3>
            <form id="manualAdjustmentForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">العميل</label>
                    <select name="customer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">اختر العميل</option>
                        <?php foreach ($top_customers as $customer): ?>
                        <option value="<?= $customer['customer_id'] ?>"><?= htmlspecialchars($customer['name']) ?> - <?= htmlspecialchars($customer['phone']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">النقاط</label>
                    <input type="number" name="points" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">الوصف</label>
                    <input type="text" name="description" placeholder="سبب التعديل" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition-colors">
                        حفظ
                    </button>
                    <button type="button" onclick="closeManualAdjustment()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 rounded-lg transition-colors">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Award birthday points
        function awardBirthdayPoints(customerId) {
            if (!confirm('هل تريد منح نقاط عيد الميلاد لهذا العميل؟')) return;
            
            fetch('loyalty.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=award_birthday_points&customer_id=${customerId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`تم منح ${data.points} نقطة بنجاح!`);
                    location.reload();
                } else {
                    alert('حدث خطأ أو تم منح النقاط مسبقاً');
                }
            });
        }

        // Manual adjustment modal
        function openManualAdjustment() {
            document.getElementById('manualAdjustmentModal').classList.remove('hidden');
            document.getElementById('manualAdjustmentModal').classList.add('flex');
        }

        function closeManualAdjustment() {
            document.getElementById('manualAdjustmentModal').classList.add('hidden');
            document.getElementById('manualAdjustmentModal').classList.remove('flex');
        }

        // Handle manual adjustment form
        document.getElementById('manualAdjustmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'manual_points_adjustment');
            
            fetch('loyalty.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تم تعديل النقاط بنجاح!');
                    closeManualAdjustment();
                    location.reload();
                } else {
                    alert('حدث خطأ في تعديل النقاط');
                }
            });
        });

        // Process inactivity downgrades
        function processInactivityDowngrades() {
            if (!confirm('هل تريد معالجة تراجع مستويات العملاء الخاملين؟')) return;
            const btn = event.target;
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري المعالجة...';
            btn.disabled = true;
            fetch('loyalty.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=process_inactivity'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('تمت المعالجة. عدد العملاء الذين تم تخفيض مستواهم: ' + (data.affected || 0));
                    location.reload();
                } else {
                    alert('حدث خطأ: ' + (data.message || 'غير معروف'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('فشل الاتصال بالخادم');
            })
            .finally(() => { btn.innerHTML = original; btn.disabled = false; });
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>