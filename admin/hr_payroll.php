<?php
/**
 * HR Management - Payroll Management (Upgraded with Interactive Generation & Edit/Confirm Modal)
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$current_month = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month;

// --- ACTION: UPDATE AND MARK AS PAID (NEW ACTION FOR THE EDIT MODAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_and_pay') {
    $payroll_id = $_POST['payroll_id'];
    $bonus = $_POST['bonus'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    try {
        // 1. Fetch the original payroll record to get base salary and overtime
        $fetch_stmt = $db->prepare("SELECT * FROM hr_payroll WHERE id = ?");
        $fetch_stmt->execute([$payroll_id]);
        $original_payroll = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original_payroll) {
            throw new Exception("لم يتم العثور على سجل الراتب.");
        }

        // 2. Recalculate the net salary based on the new inputs
        $net_salary = ($original_payroll['base_salary'] + $bonus + $original_payroll['overtime_pay']) - $deductions;
        
        // 3. Update the record with new values and set status to 'paid'
        $update_stmt = $db->prepare("
            UPDATE hr_payroll 
            SET 
                bonus = ?, 
                deductions = ?, 
                notes = ?, 
                net_salary = ?, 
                status = 'paid', 
                paid_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$bonus, $deductions, $notes, $net_salary, $payroll_id]);
        
        $_SESSION['message'] = "تم تأكيد وتحديث الراتب بنجاح.";

    } catch (Exception $e) {
        $_SESSION['error'] = "خطأ أثناء تحديث الراتب: " . $e->getMessage();
    }
    header('Location: hr_payroll.php?month=' . $selected_month);
    exit;
}

// --- DATA FETCHING ---
try {
    // Fetch existing payroll data for the selected month
    $query = "
        SELECT p.*, tm.name_ar
        FROM hr_payroll p
        JOIN team_members tm ON p.employee_id = tm.id
        WHERE p.month = ?
        ORDER BY tm.name_ar ASC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_month]);
    $payroll_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no payroll data exists, fetch active employees for the generation modal
    $active_employees = [];
    if (empty($payroll_data)) {
        $emp_stmt = $db->prepare("SELECT id, name_ar, salary FROM team_members WHERE is_active = 1 ORDER BY name_ar ASC");
        $emp_stmt->execute();
        $active_employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $payroll_data = [];
    $active_employees = [];
    $page_error = "خطأ في جلب البيانات: " . $e->getMessage();
}

$page_title = 'إدارة الرواتب';
ob_start();
?>
<div x-data="{ isModalOpen: false, isEditModalOpen: false, editingPayroll: null, openEditModal(payroll) { this.editingPayroll = payroll; this.isEditModalOpen = true; } }">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold text-mai-dark-brown"><?= $page_title ?></h1>
            <div class="flex items-center gap-3">
                <a href="hr_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm">العودة</a>
                <form method="GET" class="flex items-center">
                    <label for="month_filter" class="text-sm ml-2 whitespace-nowrap">اختر الشهر:</label>
                    <input type="month" id="month_filter" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm">
                </form>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-medium">الموظف</th>
                        <th class="px-4 py-3 text-right text-xs font-medium">الراتب الأساسي</th>
                        <th class="px-4 py-3 text-right text-xs font-medium">المكافأة</th>
                        <th class="px-4 py-3 text-right text-xs font-medium">أجر إضافي</th>
                        <th class="px-4 py-3 text-right text-xs font-medium">الخصومات</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-green-600 font-bold">صافي الراتب</th>
                        <th class="px-4 py-3 text-center text-xs font-medium">الحالة</th>
                        <th class="px-4 py-3 text-center text-xs font-medium">الإجراء</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($payroll_data as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4 whitespace-nowrap font-medium text-gray-800"><?= htmlspecialchars($row['name_ar']) ?></td>
                        <td class="px-4 py-4"><?= number_format($row['base_salary'], 2) ?> ر.س</td>
                        <td class="px-4 py-4 text-blue-600"><?= number_format($row['bonus'], 2) ?> ر.س</td>
                        <td class="px-4 py-4"><?= number_format($row['overtime_pay'], 2) ?> ر.س</td>
                        <td class="px-4 py-4 text-red-600"><?= number_format($row['deductions'], 2) ?> ر.س</td>
                        <td class="px-4 py-4 font-bold text-lg text-green-700"><?= number_format($row['net_salary'], 2) ?> ر.س</td>
                        <td class="px-4 py-4 text-center">
                            <span class="px-3 py-1 text-xs rounded-full font-semibold <?= $row['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                <?= $row['status'] === 'paid' ? 'مدفوع' : 'مسودة' ?>
                            </span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <?php if ($row['status'] !== 'paid'): ?>
                            <!-- START: EDITED BUTTON -->
                            <button @click="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" type="button" class="text-sm bg-green-600 text-white px-3 py-1 rounded-md hover:bg-green-700">تأكيد الدفع</button>
                            <!-- END: EDITED BUTTON -->
                            <?php else: ?>
                                <span class="text-xs text-gray-500">تم الدفع في <?= date('Y/m/d', strtotime($row['paid_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payroll_data)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-gray-500">
                                <p>لم يتم إنشاء مسير الرواتب لشهر <?= htmlspecialchars($selected_month) ?> بعد.</p>
                                <button @click="isModalOpen = true" class="mt-4 bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                                    <i class="fas fa-plus-circle mr-2"></i>إنشاء مسير رواتب لشهر <?= htmlspecialchars($selected_month) ?>
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Generate Payroll Modal (Unchanged) -->
    <div x-show="isModalOpen" x-cloak class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div @click.outside="isModalOpen = false" class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="p-6 border-b">
                <h2 class="text-2xl font-bold">إنشاء مسير رواتب - <?= htmlspecialchars($selected_month) ?></h2>
                <p class="text-sm text-gray-500">أضف أي مكافآت أو خصومات يدوية قبل اعتماد المسير. سيتم احتساب المكافآت والخصومات التلقائية (الغياب، السلف) تلقائياً.</p>
            </div>
            <form action="hr_payroll_processor.php" method="POST" class="flex-grow overflow-y-auto">
                <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
                <input type="hidden" name="run_payroll" value="1">
                <div class="p-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100"><tr><th class="px-4 py-2 text-right text-sm">الموظف</th><th class="px-4 py-2 text-right text-sm">مكافأة إضافية</th><th class="px-4 py-2 text-right text-sm">خصم يدوي</th><th class="px-4 py-2 text-right text-sm">ملاحظات</th></tr></thead>
                        <tbody>
                            <?php foreach ($active_employees as $employee): ?>
                            <tr class="border-b">
                                <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($employee['name_ar']) ?></td>
                                <td><input type="number" step="0.01" name="manual_bonus[<?= $employee['id'] ?>]" placeholder="0.00" class="w-full p-2 border rounded-md"></td>
                                <td><input type="number" step="0.01" name="manual_deduction[<?= $employee['id'] ?>]" placeholder="0.00" class="w-full p-2 border rounded-md"></td>
                                <td><input type="text" name="notes[<?= $employee['id'] ?>]" placeholder="سبب المكافأة/الخصم..." class="w-full p-2 border rounded-md"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-6 bg-gray-50 border-t flex justify-end gap-3">
                    <button type="button" @click="isModalOpen = false" class="px-5 py-2 border rounded-lg bg-white">إلغاء</button>
                    <button type="submit" class="bg-green-600 text-white px-8 py-2 rounded-lg hover:bg-green-700" onclick="return confirm('سيتم إنشاء مسير رواتب نهائي لهذا الشهر بناءً على البيانات الحالية والمدخلات اليدوية. هل أنت متأكد؟')">اعتماد وإنشاء المسير</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- START: NEW EDIT/CONFIRM PAYROLL MODAL -->
    <div x-show="isEditModalOpen" x-cloak class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div @click.outside="isEditModalOpen = false" class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col" x-show="isEditModalOpen" x-transition>
            <template x-if="editingPayroll">
                <form method="POST">
                    <input type="hidden" name="action" value="update_and_pay">
                    <input type="hidden" name="payroll_id" :value="editingPayroll.id">

                    <div class="p-6 border-b">
                        <h2 class="text-2xl font-bold">تأكيد وتعديل راتب - <span x-text="editingPayroll.name_ar"></span></h2>
                        <p class="text-sm text-gray-500">راجع البيانات وقم بإجراء أي تعديلات نهائية قبل تأكيد الدفع.</p>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="bg-gray-50 p-3 rounded-md">
                                <label class="block text-gray-500">الراتب الأساسي</label>
                                <p class="font-bold text-lg" x-text="`${Number(editingPayroll.base_salary).toFixed(2)} ر.س`"></p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-md">
                                <label class="block text-gray-500">الأجر الإضافي المحسوب</label>
                                <p class="font-bold text-lg" x-text="`${Number(editingPayroll.overtime_pay).toFixed(2)} ر.س`"></p>
                            </div>
                        </div>
                        <div>
                            <label for="edit_bonus" class="block font-medium">المكافأة</label>
                            <input id="edit_bonus" type="number" step="0.01" name="bonus" x-model.number="editingPayroll.bonus" class="w-full p-2 border rounded-md mt-1">
                        </div>
                        <div>
                            <label for="edit_deductions" class="block font-medium">الخصومات</label>
                            <input id="edit_deductions" type="number" step="0.01" name="deductions" x-model.number="editingPayroll.deductions" class="w-full p-2 border rounded-md mt-1">
                        </div>
                        <div>
                            <label for="edit_notes" class="block font-medium">ملاحظات</label>
                            <input id="edit_notes" type="text" name="notes" x-model="editingPayroll.notes" placeholder="سبب المكافأة/الخصم..." class="w-full p-2 border rounded-md mt-1">
                        </div>
                    </div>

                    <div class="p-6 bg-gray-50 border-t flex justify-end gap-3">
                        <button type="button" @click="isEditModalOpen = false" class="px-5 py-2 border rounded-lg bg-white">إلغاء</button>
                        <button type="submit" class="bg-green-600 text-white px-8 py-2 rounded-lg hover:bg-green-700">تأكيد ووضع علامة كمدفوع</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
    <!-- END: NEW EDIT/CONFIRM PAYROLL MODAL -->

</div>
<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>