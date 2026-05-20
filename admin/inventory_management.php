<?php
require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';

checkAdminAuth();

$admin = getCurrentAdmin();
$db = (new Database())->getConnection();

// AJAX request handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        switch ($_POST['action']) {
            case 'save_inventory_count':
                $month = $_POST['month'] ?? null;
                $year = $_POST['year'] ?? null;
                $notes = $_POST['notes'] ?? '';
                $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

                if (empty($month) || empty($year) || empty($items)) {
                    throw new Exception('Missing required data.');
                }

                $db->beginTransaction();

                $stmt_check = $db->prepare("SELECT id FROM inventory_counts WHERE month = ? AND year = ?");
                $stmt_check->execute([$month, $year]);
                if ($stmt_check->fetch()) {
                    throw new Exception('An inventory count for this month and year already exists.');
                }

                $stmt_main = $db->prepare("INSERT INTO inventory_counts (month, year, notes, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt_main->execute([$month, $year, $notes, $admin['id']]); // Corrected to use the logged-in admin's ID
                $countId = $db->lastInsertId();

                // Updated INSERT statement to include new columns
                $stmt_items = $db->prepare("
                    INSERT INTO inventory_count_items 
                        (inventory_count_id, product_id, product_name_ar, 
                         beginning_quantity, purchases, `usage`, wastage, 
                         system_quantity, counted_quantity, difference) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($items as $item) {
                    $system_quantity = (float) $item['beginning'] + (float) $item['purchases'] - (float) $item['usage'] - (float) $item['wastage'];
                    $diff = (float) $item['counted'] - $system_quantity;
                    $stmt_items->execute([
                        $countId,
                        $item['id'],
                        $item['name'],
                        $item['beginning'],
                        $item['purchases'],
                        $item['usage'],
                        $item['wastage'],
                        $system_quantity, // Calculated system quantity
                        $item['counted'], // This is the "End of Month Quantity"
                        $diff
                    ]);
                }

                $db->commit();
                $response = ['success' => true, 'message' => 'تم حفظ الجرد بنجاح.'];
                break;

            case 'get_monthly_counts':
                $filter_type = $_POST['filter_type'] ?? 'last_5';

                $query = "SELECT ic.*, u.name as admin_name 
                          FROM inventory_counts ic 
                          LEFT JOIN users u ON ic.created_by = u.id 
                          ORDER BY ic.year DESC, ic.month DESC";

                if ($filter_type === 'last_5') {
                    $query .= " LIMIT 5";
                }

                $stmt = $db->query($query);
                $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $counts];
                break;

            case 'get_count_details':
                $countId = $_POST['count_id'] ?? 0;
                // Updated SELECT to fetch all new columns
                $stmt = $db->prepare("SELECT * FROM inventory_count_items WHERE inventory_count_id = ? ORDER BY product_name_ar ASC");
                $stmt->execute([$countId]);
                $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $details];
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction())
            $db->rollBack();
        http_response_code(400);
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Fetch products for the selector
$stmt_products = $db->query("SELECT id, name_ar, stock_quantity FROM products WHERE is_active = 1 ORDER BY name_ar ASC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'الجرد اليدوي للمخزون';
ob_start();
?>
<style>
    .diff-positive {
        background-color: #f0fff4;
        color: #2f855a;
    }

    .diff-negative {
        background-color: #fff5f5;
        color: #c53030;
    }

    .diff-zero {
        background-color: #f7fafc;
    }

    .filter-btn.active {
        background-color: #4299e1;
        color: white;
        font-weight: bold;
    }

    #inventory-table-body input[type="number"] {
        width: 90px;
    }
</style>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md mb-6 p-6">
    <h1 class="text-2xl font-bold text-gray-800">إدارة الجرد اليدوي للمخزون</h1>
    <p class="text-sm text-gray-500">حفظ وعرض تقارير الجرد الشهرية للمخزون.</p>
</div>

<!-- Main Content -->
<div id="main-view">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
            <h2 class="text-xl font-bold">سجلات الجرد المحفوظة</h2>
            <div class="flex items-center gap-2">
                <div class="flex rounded-md shadow-sm">
                    <button id="filter-last-5"
                        class="filter-btn active rounded-r-lg px-4 py-2 bg-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-300 focus:z-10 focus:outline-none">آخر
                        5 شهور</button>
                    <button id="filter-all"
                        class="filter-btn rounded-l-lg px-4 py-2 bg-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-300 focus:z-10 focus:outline-none">عرض
                        الكل</button>
                </div>
                <button id="start-new-count-btn"
                    class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition-colors"><i
                        class="fas fa-plus mr-2"></i>بدء جرد جديد</button>
            </div>
        </div>
        <div id="counts-list" class="overflow-x-auto">
            <!-- Saved counts will be loaded here -->
        </div>
    </div>
</div>

<!-- New Count View (Initially Hidden) -->
<div id="new-count-view" class="hidden">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold mb-4">جرد مخزون جديد</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 border-b pb-6">
            <div>
                <label for="count-year" class="block text-sm font-medium text-gray-700">السنة</label>
                <select id="count-year" class="form-select mt-1 w-full">
                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?= $i; ?>"><?= $i; ?></option><?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="count-month" class="block text-sm font-medium text-gray-700">الشهر</label>
                <select id="count-month" class="form-select mt-1 w-full">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m; ?>" <?= ($m == date('m')) ? 'selected' : ''; ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 10)); ?></option><?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="count-notes" class="block text-sm font-medium text-gray-700">ملاحظات (اختياري)</label>
                <input type="text" id="count-notes" class="form-input mt-1 w-full" placeholder="مثال: الجرد السنوي">
            </div>
        </div>

        <div class="mb-6">
            <label for="product-selector" class="block text-sm font-medium text-gray-700 mb-2">اختر منتج لإضافته
                للقائمة:</label>
            <select id="product-selector" class="form-select w-full">
                <option value="">-- بحث واختيار منتج --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= $product['id']; ?>" data-name="<?= htmlspecialchars($product['name_ar']); ?>"
                        data-stock="<?= $product['stock_quantity']; ?>">
                        <?= htmlspecialchars($product['name_ar']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="overflow-x-auto">
            <!-- Updated table with new columns -->
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-right p-3">اسم المادة</th>
                        <th class="text-center p-3">الكمية بداية الشهر</th>
                        <th class="text-center p-3">المشتريات</th>
                        <th class="text-center p-3">الاستخدام</th>
                        <th class="text-center p-3">الهالك</th>
                        <th class="text-center p-3 bg-gray-200">الكمية (حسب النظام)</th>
                        <th class="text-center p-3">الكمية نهاية الشهر (الجرد)</th>
                        <th class="text-center p-3 font-semibold">الفرق</th>
                        <th class="text-center p-3"></th>
                    </tr>
                </thead>
                <tbody id="inventory-table-body"></tbody>
            </table>
        </div>

        <div class="mt-6 flex gap-4">
            <button id="save-count-btn"
                class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors"><i
                    class="fas fa-save mr-2"></i>حفظ الجرد</button>
            <button id="cancel-count-btn"
                class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">إلغاء</button>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="view-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-6xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-bold text-gray-900" id="modal-title">تفاصيل الجرد</h3>
            <button onclick="closeModal('view-details-modal')" class="text-black text-2xl">&times;</button>
        </div>
        <div id="modal-content" class="max-h-[70vh] overflow-y-auto"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mainView = document.getElementById('main-view');
        const newCountView = document.getElementById('new-count-view');
        const startNewBtn = document.getElementById('start-new-count-btn');
        const cancelBtn = document.getElementById('cancel-count-btn');
        const productSelector = document.getElementById('product-selector');
        const tableBody = document.getElementById('inventory-table-body');
        const saveBtn = document.getElementById('save-count-btn');
        const filterLast5Btn = document.getElementById('filter-last-5');
        const filterAllBtn = document.getElementById('filter-all');

        loadSavedCounts('last_5');

        startNewBtn.addEventListener('click', () => {
            mainView.classList.add('hidden');
            newCountView.classList.remove('hidden');
        });

        cancelBtn.addEventListener('click', () => {
            if (confirm('هل أنت متأكد؟ سيتم فقدان جميع البيانات غير المحفوظة.')) {
                tableBody.innerHTML = '';
                newCountView.classList.add('hidden');
                mainView.classList.remove('hidden');
            }
        });

        productSelector.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (!selected.value) return;
            if (document.getElementById(`row-${selected.value}`)) {
                alert('هذا المنتج موجود بالفعل في القائمة.');
                this.value = '';
                return;
            }
            addProductRow(selected.value, selected.dataset.name, selected.dataset.stock);
            this.value = '';
        });

        saveBtn.addEventListener('click', saveInventoryCount);

        filterLast5Btn.addEventListener('click', () => {
            setActiveFilter(filterLast5Btn);
            loadSavedCounts('last_5');
        });

        filterAllBtn.addEventListener('click', () => {
            setActiveFilter(filterAllBtn);
            loadSavedCounts('all');
        });

        function sendAjax(formData, btn, callback) {
            const originalBtnText = btn.innerHTML;
            btn.innerHTML = 'جاري الحفظ...';
            btn.disabled = true;

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        if (callback) callback(data);
                    } else {
                        throw new Error(data.message || 'An error occurred');
                    }
                })
                .catch(error => alert('خطأ: ' + error.message))
                .finally(() => {
                    btn.innerHTML = originalBtnText;
                    btn.disabled = false;
                });
        }

        function loadSavedCounts(filterType) {
            const listContainer = document.getElementById('counts-list');
            listContainer.innerHTML = '<p class="text-center text-gray-500 p-4">جاري التحميل...</p>';
            const formData = new FormData();
            formData.append('action', 'get_monthly_counts');
            formData.append('filter_type', filterType);

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        const monthNames = ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"];
                        let tableHTML = `<table class="min-w-full bg-white"><thead class="bg-gray-50"><tr><th class="p-3 text-right">الشهر والسنة</th><th class="p-3 text-right">تاريخ الإنشاء</th><th class="p-3 text-right">تم بواسطة</th><th class="p-3"></th></tr></thead><tbody>`;
                        data.data.forEach(count => {
                            tableHTML += `<tr class="border-t"><td class="p-3 font-semibold">${monthNames[count.month - 1]} ${count.year}</td><td class="p-3">${new Date(count.created_at).toLocaleDateString('ar-EG')}</td><td class="p-3">${count.admin_name || 'N/A'}</td><td class="p-3 text-center"><button onclick="viewCountDetails(${count.id}, '${monthNames[count.month - 1]} ${count.year}')" class="bg-gray-200 text-gray-700 px-3 py-1 rounded hover:bg-gray-300">عرض</button></td></tr>`;
                        });
                        listContainer.innerHTML = tableHTML + '</tbody></table>';
                    } else {
                        listContainer.innerHTML = '<p class="text-center text-gray-500 p-4">لا توجد سجلات جرد محفوظة.</p>';
                    }
                });
        }

        function setActiveFilter(activeBtn) {
            [filterLast5Btn, filterAllBtn].forEach(btn => btn.classList.remove('active'));
            activeBtn.classList.add('active');
        }

        // Updated to add new input fields
        function addProductRow(id, name, stock) {
            const row = document.createElement('tr');
            row.id = `row-${id}`;
            row.className = 'border-t';
            row.dataset.id = id;
            row.dataset.name = name;

            row.innerHTML = `
            <td class="p-3 font-semibold">${name}</td>
            <td class="p-2"><input type="number" name="beginning" class="form-input mx-auto text-center" value="${stock}" oninput="calculateRow(this)"></td>
            <td class="p-2"><input type="number" name="purchases" class="form-input mx-auto text-center" value="0" oninput="calculateRow(this)"></td>
            <td class="p-2"><input type="number" name="usage" class="form-input mx-auto text-center" value="0" oninput="calculateRow(this)"></td>
            <td class="p-2"><input type="number" name="wastage" class="form-input mx-auto text-center" value="0" oninput="calculateRow(this)"></td>
            <td class="p-2 text-center text-gray-600 bg-gray-200" name="system_quantity">0.00</td>
            <td class="p-2"><input type="number" name="counted" class="form-input mx-auto text-center" value="0" oninput="calculateRow(this)"></td>
            <td class="p-2 text-center font-bold text-lg" name="difference">0.00</td>
            <td class="p-2 text-center"><button onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700 p-2"><i class="fas fa-trash-alt"></i></button></td>`;

            tableBody.appendChild(row);
            calculateRow(row.querySelector('input')); // Initial calculation
        }

        // Updated to collect data from all new fields
        function saveInventoryCount() {
            const items = [];
            const rows = tableBody.querySelectorAll('tr');
            if (rows.length === 0) {
                alert('الرجاء إضافة منتجات إلى قائمة الجرد أولاً.');
                return;
            }

            rows.forEach(row => {
                items.push({
                    id: row.dataset.id,
                    name: row.dataset.name,
                    beginning: row.querySelector('[name="beginning"]').value,
                    purchases: row.querySelector('[name="purchases"]').value,
                    usage: row.querySelector('[name="usage"]').value,
                    wastage: row.querySelector('[name="wastage"]').value,
                    counted: row.querySelector('[name="counted"]').value
                });
            });

            const formData = new FormData();
            formData.append('action', 'save_inventory_count');
            formData.append('year', document.getElementById('count-year').value);
            formData.append('month', document.getElementById('count-month').value);
            formData.append('notes', document.getElementById('count-notes').value);
            formData.append('items', JSON.stringify(items));

            sendAjax(formData, saveBtn, (data) => {
                alert(data.message);
                tableBody.innerHTML = '';
                newCountView.classList.add('hidden');
                mainView.classList.remove('hidden');
                setActiveFilter(filterLast5Btn);
                loadSavedCounts('last_5');
            });
        }

        // Updated to calculate based on all new fields
        window.calculateRow = function (input) {
            const row = input.closest('tr');
            const beginningQty = parseFloat(row.querySelector('[name="beginning"]').value) || 0;
            const purchasesQty = parseFloat(row.querySelector('[name="purchases"]').value) || 0;
            const usageQty = parseFloat(row.querySelector('[name="usage"]').value) || 0;
            const wastageQty = parseFloat(row.querySelector('[name="wastage"]').value) || 0;
            const countedQty = parseFloat(row.querySelector('[name="counted"]').value) || 0;

            const systemQtyCell = row.querySelector('[name="system_quantity"]');
            const diffCell = row.querySelector('[name="difference"]');

            const systemCalculatedQty = beginningQty + purchasesQty - usageQty - wastageQty;
            systemQtyCell.textContent = systemCalculatedQty.toFixed(2);

            const difference = countedQty - systemCalculatedQty;
            diffCell.textContent = difference.toFixed(2);

            row.classList.remove('diff-positive', 'diff-negative', 'diff-zero');
            if (difference > 0) { diffCell.textContent = '+' + difference.toFixed(2); row.classList.add('diff-positive'); }
            else if (difference < 0) { row.classList.add('diff-negative'); }
            else { row.classList.add('diff-zero'); }
        }

        // Updated to display all new columns in the details modal
        window.viewCountDetails = function (countId, title) {
            const formData = new FormData();
            formData.append('action', 'get_count_details');
            formData.append('count_id', countId);
            fetch(window.location.href, { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if (data.success) {
                    const modalContent = document.getElementById('modal-content');
                    document.getElementById('modal-title').textContent = `تفاصيل جرد: ${title}`;
                    let contentHTML = `<div class="overflow-x-auto"><table class="min-w-full"><thead class="bg-gray-100"><tr>
                    <th class="p-2 text-right">المنتج</th>
                    <th class="p-2">بداية الشهر</th>
                    <th class="p-2">المشتريات</th>
                    <th class="p-2">الاستخدام</th>
                    <th class="p-2">الهالك</th>
                    <th class="p-2 bg-gray-200">كمية النظام</th>
                    <th class="p-2">نهاية الشهر</th>
                    <th class="p-2">الفرق</th>
                </tr></thead><tbody>`;
                    data.data.forEach(item => {
                        let diffClass = 'diff-zero', diffText = parseFloat(item.difference).toFixed(2);
                        if (item.difference > 0) { diffClass = 'diff-positive'; diffText = `+${diffText}`; }
                        if (item.difference < 0) { diffClass = 'diff-negative'; }
                        contentHTML += `<tr class="border-t ${diffClass}">
                        <td class="p-2 font-medium">${item.product_name_ar}</td>
                        <td class="p-2 text-center">${parseFloat(item.beginning_quantity).toFixed(2)}</td>
                        <td class="p-2 text-center">${parseFloat(item.purchases).toFixed(2)}</td>
                        <td class="p-2 text-center">${parseFloat(item.usage).toFixed(2)}</td>
                        <td class="p-2 text-center">${parseFloat(item.wastage).toFixed(2)}</td>
                        <td class="p-2 text-center bg-gray-200">${parseFloat(item.system_quantity).toFixed(2)}</td>
                        <td class="p-2 text-center">${parseFloat(item.counted_quantity).toFixed(2)}</td>
                        <td class="p-2 text-center font-bold">${diffText}</td>
                    </tr>`;
                    });
                    modalContent.innerHTML = contentHTML + '</tbody></table></div>';
                    openModal('view-details-modal');
                }
            });
        }

        window.openModal = (id) => document.getElementById(id).classList.remove('hidden');
        window.closeModal = (id) => document.getElementById(id).classList.add('hidden');
    });
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>