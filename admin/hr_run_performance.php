<?php
session_start();
require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkAdminAuth();
// requirePermission('hr.performance.manage'); // Assumes a permission to run this action

// PART 1: HANDLE THE FORM SUBMISSION (SAVE ACTION)
// This code runs ONLY when the "Confirm & Save" button is clicked.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This is a server-to-server call. It's more reliable than browser fetch.
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/hr_performance_calculator.php?mode=execute';
    $result = @file_get_contents($url);

    if ($result === false) {
        $_SESSION['payroll_error'] = 'فشل في تشغيل أداة حساب الأداء. تحقق من سجلات الخادم.';
    } elseif (strpos($result, '❌ Error:') !== false) {
        $_SESSION['payroll_error'] = 'حدث خطأ أثناء حساب الأداء: ' . strip_tags($result);
    } else {
        $_SESSION['payroll_message'] = 'تم تحديث سجلات الأداء بنجاح!';
    }
    
    // Redirect back to the dashboard to show the result message
    header('Location: hr_dashboard.php');
    exit();
}


// PART 2: FETCH PREVIEW DATA (PAGE LOAD ACTION)
// This code runs when the page is first loaded.
$preview_data = null;
$error_message = null;
$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/hr_performance_calculator.php?mode=preview';
$json_data = @file_get_contents($url);

if ($json_data === false) {
    $error_message = "لا يمكن الوصول إلى أداة حساب الأداء. يرجى التحقق من أن الملف موجود وقابل للتشغيل.";
} else {
    $preview_data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "فشل في فهم البيانات من أداة الحساب. خطأ JSON: " . json_last_error_msg();
        $preview_data = null;
    }
}


$page_title = 'مراجعة وتحديث الأداء';
ob_start();
?>

<div class="bg-white rounded-lg shadow-md mb-6 p-4">
    <h1 class="text-lg font-bold text-mai-dark-brown">مراجعة بيانات الأداء</h1>
    <p class="text-xs text-gray-500">البيانات أدناه هي للمراجعة فقط ولم يتم حفظها بعد. قم بالتأكيد لحفظ السجلات.</p>
</div>

<?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
        <p class="font-bold">خطأ فادح</p>
        <p><?= htmlspecialchars($error_message); ?></p>
    </div>
<?php elseif ($preview_data && !empty($preview_data['months'])): ?>
    <!-- This form will submit to this same page, triggering the POST logic at the top -->
    <form method="POST" action="hr_run_performance.php" onsubmit="return confirm('هل أنت متأكد من رغبتك في حفظ هذه البيانات بشكل دائم؟');">
        <?php foreach ($preview_data['months'] as $monthData): ?>
            <div class="mb-8 p-6 border rounded-xl bg-gray-50 shadow-sm">
                <h4 class="text-xl font-bold text-mai-brown mb-3">شهر: <?= htmlspecialchars($monthData['month']) ?></h4>
                <p class="text-base text-gray-600 mb-6">إجمالي إيرادات الصالون للشهر: <strong class="text-green-700 text-lg"><?= number_format($monthData['total_salon_revenue'], 2) ?> ر.س</strong></p>
                
                <h5 class="font-semibold text-gray-800 text-lg mb-3">🧑‍🔬 الأخصائيات (أداء فردي)</h5>
                <div class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-200"><tr class="text-right"><th class="p-3">الاسم</th><th class="p-3 text-center">عدد الخدمات</th><th class="p-3 text-center">الإيرادات المحققة</th><th class="p-3 text-center font-bold">البونص المستحق</th></tr></thead>
                        <tbody class="bg-white divide-y">
                        <?php foreach($monthData['specialists'] as $sp): ?>
                            <tr>
                                <td class="p-3 font-medium"><?= htmlspecialchars($sp['name_ar'] ?? $sp['name']) ?></td> <!-- Handles both name_ar and name for safety -->
                                <td class="p-3 text-center"><?= htmlspecialchars($sp['service_count']) ?></td>
                                <td class="p-3 text-center"><?= number_format($sp['revenue_sum'], 2) ?> ر.س</td>
                                <td class="p-3 text-center font-bold text-green-600 bg-green-50"><?= number_format($sp['bonus_earned'], 2) ?> ر.س</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MODIFIED SECTION FOR NON-SPECIALISTS -->
                <h5 class="font-semibold text-gray-800 text-lg mt-8 mb-3">💼 الموظفون غير الأخصائيين (أداء الصالون)</h5>
                <div class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-200"><tr class="text-right"><th class="p-3">الاسم</th><th class="p-3 text-center font-bold">البونص المستحق</th></tr></thead>
                        <tbody class="bg-white divide-y">
                        <?php foreach($monthData['non_specialists'] as $user): ?>
                            <tr>
                                <td class="p-3 font-medium"><?= htmlspecialchars($user['name']) ?></td>
                                <td class="p-3 text-center font-bold text-green-600 bg-green-50"><?= number_format($user['bonus_earned'], 2) ?> ر.س</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="mt-8 flex items-center justify-end gap-4 p-4 bg-white rounded-lg shadow-md">
            <a href="hr_dashboard.php" class="bg-gray-300 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-400 font-semibold">إلغاء</a>
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 font-semibold flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                تأكيد وحفظ البيانات
            </button>
        </div>
    </form>
<?php else: ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg">
        <p class="font-bold">لا توجد بيانات</p>
        <p>لم يتم العثور على بيانات لحسابها في الوقت الحالي.</p>
    </div>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>