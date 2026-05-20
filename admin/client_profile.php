<?php
// Prevent browser caching and setup error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * CRM - Customer Profile Page (With AJAX Pagination & Interactive Modals)
 */

require_once 'auth.php';
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

checkAdminAuth();
$admin = getCurrentAdmin();
$database = new Database();
$db = $database->getConnection();

$customer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$customer_id) {
    header("Location: clients.php"); 
    exit;
}

// --- FORM HANDLING LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_customer':
                $id = intval($_POST['customer_id']);
                $name_ar = trim($_POST['name_ar']);
                $phone = trim($_POST['phone']);
                if (empty($name_ar) || empty($phone)) throw new Exception("الاسم ورقم الجوال حقول إلزامية.");
                
                $stmt = $db->prepare("UPDATE customers SET name_ar = ?, phone = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name_ar, $phone, $admin['id'], $id]);
                $_SESSION['flash_success'] = "تم تحديث بيانات العميل بنجاح.";
                break;

            case 'assign_form':
                $id = intval($_POST['customer_id']);
                $form_id = !empty($_POST['health_form_id']) ? intval($_POST['health_form_id']) : null;

                $stmt = $db->prepare("UPDATE customers SET assigned_health_form_id = ? WHERE id = ?");
                $stmt->execute([$form_id, $id]);
                $_SESSION['flash_success'] = "تم تعيين النموذج الصحي بنجاح.";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "خطأ: " . $e->getMessage();
    }
    header("Location: client_profile.php?id=" . $customer_id);
    exit();
}


// --- DATA FETCHING ---
$limit = 5; // Records per page. Must match the limit in fetch_tab_data.php

// Customer Info
$customer_query = "
    SELECT c.*, 
           hf.name as assigned_form_name
    FROM customers c
    LEFT JOIN health_forms hf ON c.assigned_health_form_id = hf.id
    WHERE c.id = ?
";
$stmt = $db->prepare($customer_query);
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['flash_error'] = "لم يتم العثور على العميل.";
    header("Location: clients.php");
    exit;
}

// All Health Forms (for assign modal)
$forms_stmt = $db->query("SELECT id, name FROM health_forms ORDER BY name ASC");
$all_health_forms = $forms_stmt->fetchAll(PDO::FETCH_ASSOC);

// All Health Form Questions (for answers modal lookup)
$all_questions_stmt = $db->query("SELECT id, question_text FROM health_form_questions");
$questions_map = $all_questions_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Function to fetch initial page data + total count for pagination
function getInitialTabData($db, $query, $count_query, $params, $limit) {
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();

    $data_stmt = $db->prepare($query . " LIMIT " . $limit);
    $data_stmt->execute($params);
    $items = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'items' => $items,
        'total' => (int)$total,
        'page' => 1,
        'totalPages' => ceil($total / $limit),
        'loading' => false
    ];
}

// Fetch Page 1 for each tab to initialize the component
$initial_data = [];
$initial_data['appointments'] = getInitialTabData($db, "SELECT * FROM appointments WHERE customer_id = ? ORDER BY start_at DESC", "SELECT COUNT(id) FROM appointments WHERE customer_id = ?", [$customer_id], $limit);
$initial_data['invoices'] = getInitialTabData($db, "SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC", "SELECT COUNT(id) FROM invoices WHERE customer_id = ?", [$customer_id], $limit);
$initial_data['health_forms'] = getInitialTabData($db, "SELECT cf.*, hf.name as form_name FROM client_forms cf JOIN health_forms hf ON cf.form_id = hf.id WHERE cf.customer_id = ? ORDER BY cf.created_at DESC", "SELECT COUNT(cf.id) FROM client_forms cf WHERE cf.customer_id = ?", [$customer_id], $limit);
$initial_data['services'] = getInitialTabData($db, "SELECT s.name_ar as service_name, COUNT(ii.id) as times_done, MAX(i.created_at) as last_done_date FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id JOIN services s ON ii.item_id = s.id WHERE i.customer_id = ? AND i.status = 'paid' GROUP BY s.id, s.name_ar ORDER BY last_done_date DESC", "SELECT COUNT(DISTINCT ii.item_id) FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id WHERE i.customer_id = ? AND i.status = 'paid'", [$customer_id], $limit);

$page_title = 'ملف العميل: ' . htmlspecialchars($customer['name_ar']);
ob_start();
?>
<!-- Initializing Alpine component with customer data, initial paginated data, and the questions map -->
<div class="p-4 md:p-6" x-data='pageManager(<?php echo json_encode($customer); ?>, <?php echo json_encode($initial_data); ?>, <?php echo json_encode($questions_map); ?>)'>
    
    <!-- Profile Header -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start gap-6">
            <div class="w-24 h-24 bg-mai-brown rounded-full flex items-center justify-center text-white text-4xl font-bold flex-shrink-0"><?= strtoupper(mb_substr($customer['name_ar'], 0, 1)); ?></div>
            <div class="flex-grow">
                <h1 class="text-3xl font-bold text-mai-dark"><?= htmlspecialchars($customer['name_ar']); ?></h1>
                <p class="mt-3 text-sm text-gray-600"><a href="tel:<?= htmlspecialchars($customer['phone']); ?>" class="hover:text-mai-brown"><i class="fas fa-phone-alt ml-2"></i><?= htmlspecialchars($customer['phone']); ?></a></p>
            </div>
            <div class="flex flex-wrap gap-2 mt-4 sm:mt-0 flex-shrink-0">
                <a href="clients.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">عودة</a>
                <button @click="open('editCustomerModal')" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark"><i class="fas fa-edit ml-2"></i>تعديل</button>
                <button @click="open('assignFormModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-800"><i class="fas fa-file-signature ml-2"></i>تعيين نموذج</button>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
            <!-- Details Card -->
            <div class="bg-white rounded-xl shadow-md p-6 h-fit">
                <h3 class="text-lg font-bold text-mai-dark border-b pb-3 mb-4">تفاصيل العميل</h3>
                <div class="flex justify-between text-sm"><span class="font-semibold text-gray-600">تاريخ التسجيل:</span><span class="text-gray-800" dir="ltr"><?= date('d M, Y', strtotime($customer['created_at'])); ?></span></div>
            </div>
            <!-- QR Code Card -->
            <div class="bg-white rounded-xl shadow-md p-6 h-fit">
                <h3 class="text-lg font-bold text-mai-dark border-b pb-3 mb-4">رمز QR للنموذج الصحي</h3>
                <div class="text-center p-4 border rounded-lg">
                    <?php if (!empty($customer['qr_token'])):
                        $formUrl = 'https://shop.mai-salon.com/health-form.php?token=' . $customer['qr_token'];
                        $qrCode = new QrCode($formUrl);
                        $writer = new PngWriter();
                        echo '<img src="' . $writer->write($qrCode)->getDataUri() . '" alt="QR Code" class="mx-auto">';
                        echo '<p class="text-xs text-gray-500 mt-2">امسح الرمز لتعبئة النموذج المطلوب</p>';
                    else:
                        echo '<p class="text-sm text-red-500">لا يوجد رمز QR لهذا العميل.</p>';
                    endif; ?>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-sm font-semibold text-gray-600">النموذج المعيّن حالياً:</p>
                    <p class="font-bold <?= $customer['assigned_form_name'] ? 'text-blue-700' : 'text-gray-500' ?>"><?= htmlspecialchars($customer['assigned_form_name'] ?? 'سيتم استخدام النموذج الافتراضي') ?></p>
                </div>
            </div>
        </div>

        <!-- Tabs Section -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6">
            <div class="border-b"><nav class="-mb-px flex space-x-4 space-x-reverse">
                <button @click="activeTab = 'appointments'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'appointments' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">المواعيد</button>
                <button @click="activeTab = 'invoices'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'invoices' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">الفواتير</button>
                <button @click="activeTab = 'services'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'services' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">الخدمات المفضلة</button>
                <button @click="activeTab = 'health_forms'" :class="{ 'border-mai-brown text-mai-brown': activeTab === 'health_forms' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">النماذج الصحية</button>
            </nav></div>

            <!-- Tab Content Area -->
            <div class="pt-4 min-h-[300px]">
                <!-- Appointments Tab Content -->
                <div x-show="activeTab === 'appointments'">
                    <div x-show="tabs.appointments.loading" class="text-center py-12"><span>...جاري التحميل</span></div>
                    <div x-show="!tabs.appointments.loading">
                        <ul class="divide-y divide-gray-200" x-show="tabs.appointments.items.length > 0">
                            <template x-for="item in tabs.appointments.items" :key="item.id">
                                <li class="py-3">
                                    <p class="font-semibold text-gray-800" x-text="'تاريخ: ' + new Date(item.start_at).toLocaleDateString()"></p>
                                    <p class="text-sm text-gray-500" x-text="'الحالة: ' + item.status"></p>
                                </li>
                            </template>
                        </ul>
                        <div x-show="tabs.appointments.items.length === 0" class="text-center py-12"><p>لا توجد مواعيد مسجلة.</p></div>
                        <div x-show="tabs.appointments.totalPages > 1" class="flex justify-between items-center mt-4 border-t pt-2">
                            <button @click="fetchTabData('appointments', tabs.appointments.page - 1)" :disabled="tabs.appointments.page <= 1" class="px-3 py-1 border rounded-md disabled:opacity-50">السابق</button>
                            <span>صفحة <span x-text="tabs.appointments.page"></span> من <span x-text="tabs.appointments.totalPages"></span></span>
                            <button @click="fetchTabData('appointments', tabs.appointments.page + 1)" :disabled="tabs.appointments.page >= tabs.appointments.totalPages" class="px-3 py-1 border rounded-md disabled:opacity-50">التالي</button>
                        </div>
                    </div>
                </div>

                <!-- Invoices Tab Content -->
                <div x-show="activeTab === 'invoices'">
                    <div x-show="tabs.invoices.loading" class="text-center py-12"><span>...جاري التحميل</span></div>
                    <div x-show="!tabs.invoices.loading">
                        <ul class="divide-y divide-gray-200" x-show="tabs.invoices.items.length > 0">
                            <template x-for="item in tabs.invoices.items" :key="item.id">
                               <li class="py-3">
                                    <p class="font-semibold text-gray-800" x-text="'فاتورة رقم #' + item.id + ' - ' + item.total_amount + ' SAR'"></p>
                                    <p class="text-sm text-gray-500" x-text="'بتاريخ: ' + new Date(item.created_at).toLocaleDateString() + ' | الحالة: ' + item.status"></p>
                                </li>
                            </template>
                        </ul>
                        <div x-show="tabs.invoices.items.length === 0" class="text-center py-12"><p>لا توجد فواتير مسجلة.</p></div>
                        <div x-show="tabs.invoices.totalPages > 1" class="flex justify-between items-center mt-4 border-t pt-2">
                            <button @click="fetchTabData('invoices', tabs.invoices.page - 1)" :disabled="tabs.invoices.page <= 1" class="px-3 py-1 border rounded-md disabled:opacity-50">السابق</button>
                            <span>صفحة <span x-text="tabs.invoices.page"></span> من <span x-text="tabs.invoices.totalPages"></span></span>
                            <button @click="fetchTabData('invoices', tabs.invoices.page + 1)" :disabled="tabs.invoices.page >= tabs.invoices.totalPages" class="px-3 py-1 border rounded-md disabled:opacity-50">التالي</button>
                        </div>
                    </div>
                </div>

                <!-- Services Tab Content -->
                <div x-show="activeTab === 'services'">
                    <div x-show="tabs.services.loading" class="text-center py-12"><span>...جاري التحميل</span></div>
                    <div x-show="!tabs.services.loading">
                        <ul class="divide-y divide-gray-200" x-show="tabs.services.items.length > 0">
                            <template x-for="service in tabs.services.items" :key="service.service_name">
                                <li class="py-4 flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold text-gray-800" x-text="service.service_name"></p>
                                        <p class="text-sm text-gray-500">مرات الطلب: <span class="font-medium" x-text="service.times_done"></span> | آخر مرة: <span dir="ltr" x-text="new Date(service.last_done_date).toLocaleDateString()"></span></p>
                                    </div>
                                </li>
                            </template>
                        </ul>
                        <div x-show="tabs.services.items.length === 0" class="text-center py-12"><p>لا توجد خدمات مسجلة في فواتير العميل المدفوعة.</p></div>
                        <div x-show="tabs.services.totalPages > 1" class="flex justify-between items-center mt-4 border-t pt-2">
                            <button @click="fetchTabData('services', tabs.services.page - 1)" :disabled="tabs.services.page <= 1" class="px-3 py-1 border rounded-md disabled:opacity-50">السابق</button>
                            <span>صفحة <span x-text="tabs.services.page"></span> من <span x-text="tabs.services.totalPages"></span></span>
                            <button @click="fetchTabData('services', tabs.services.page + 1)" :disabled="tabs.services.page >= tabs.services.totalPages" class="px-3 py-1 border rounded-md disabled:opacity-50">التالي</button>
                        </div>
                    </div>
                </div>

                <!-- Health Forms Tab Content -->
                <div x-show="activeTab === 'health_forms'">
                     <div x-show="tabs.health_forms.loading" class="text-center py-12"><span>...جاري التحميل</span></div>
                     <div x-show="!tabs.health_forms.loading">
                        <ul class="divide-y divide-gray-200" x-show="tabs.health_forms.items.length > 0">
                            <template x-for="form in tabs.health_forms.items" :key="form.id">
                                <li>
                                    <!-- Each item is a button to open the answers modal -->
                                    <button @click="open('viewAnswersModal', form)" class="w-full text-right py-4 flex justify-between items-center hover:bg-gray-50 px-2 rounded-md">
                                        <div>
                                            <p class="font-semibold text-gray-800" x-text="form.form_name"></p>
                                            <p class="text-sm text-gray-500">تم الإرسال في: <span dir="ltr" x-text="new Date(form.created_at).toLocaleString('en-GB')"></span></p>
                                        </div>
                                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-green-100 text-green-800">عرض الإجابات <i class="fas fa-eye mr-1"></i></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <div x-show="tabs.health_forms.items.length === 0" class="text-center py-12"><p>لم يقم العميل بتعبئة أي نماذج صحية بعد.</p></div>
                        <div x-show="tabs.health_forms.totalPages > 1" class="flex justify-between items-center mt-4 border-t pt-2">
                            <button @click="fetchTabData('health_forms', tabs.health_forms.page - 1)" :disabled="tabs.health_forms.page <= 1" class="px-3 py-1 border rounded-md disabled:opacity-50">السابق</button>
                            <span>صفحة <span x-text="tabs.health_forms.page"></span> من <span x-text="tabs.health_forms.totalPages"></span></span>
                            <button @click="fetchTabData('health_forms', tabs.health_forms.page + 1)" :disabled="tabs.health_forms.page >= tabs.health_forms.totalPages" class="px-3 py-1 border rounded-md disabled:opacity-50">التالي</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS SECTION -->
    <!-- Edit Customer Modal -->
    <div x-show="modals.editCustomerModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"><div @click.away="close('editCustomerModal')" class="bg-white rounded-xl p-6 w-full max-w-lg" x-transition><h3 class="text-xl font-bold text-mai-dark mb-4">تعديل بيانات العميل</h3><form method="POST"><input type="hidden" name="action" value="update_customer"><input type="hidden" name="customer_id" :value="customer.id"><div class="space-y-4"><div><label class="block text-sm font-medium text-gray-700">اسم العميل</label><input type="text" name="name_ar" :value="customer.name_ar" required class="mt-1 w-full p-2 border rounded-lg"></div><div><label class="block text-sm font-medium text-gray-700">رقم الجوال</label><input type="tel" name="phone" :value="customer.phone" required class="mt-1 w-full p-2 border rounded-lg"></div></div><div class="flex justify-end gap-3 mt-6 pt-4 border-t"><button type="button" @click="close('editCustomerModal')" class="px-6 py-2 border rounded-lg">إلغاء</button><button type="submit" class="bg-mai-brown text-white px-8 py-2 rounded-lg hover:bg-mai-dark">حفظ التغييرات</button></div></form></div></div>
    
    <!-- Assign Form Modal -->
    <div x-show="modals.assignFormModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"><div @click.away="close('assignFormModal')" class="bg-white rounded-xl p-6 w-full max-w-lg" x-transition><h3 class="text-xl font-bold text-mai-dark mb-4">تعيين نموذج صحي للعميل</h3><p class="text-sm text-gray-600 mb-4">اختر النموذج الذي تريد أن يقوم العميل "<strong x-text="customer.name_ar"></strong>" بتعبئته.</p><form method="POST"><input type="hidden" name="action" value="assign_form"><input type="hidden" name="customer_id" :value="customer.id"><div><label for="health_form_id" class="block text-sm font-medium text-gray-700">اختر النموذج</label><select name="health_form_id" id="health_form_id" class="mt-1 w-full p-2 border rounded-lg"><option value="">-- إلغاء التعيين (استخدام الافتراضي) --</option><?php foreach ($all_health_forms as $form): ?><option value="<?= $form['id'] ?>" <?= ($customer['assigned_health_form_id'] == $form['id']) ? 'selected' : '' ?>><?= htmlspecialchars($form['name']) ?></option><?php endforeach; ?></select></div><div class="flex justify-end gap-3 mt-6 pt-4 border-t"><button type="button" @click="close('assignFormModal')" class="px-6 py-2 border rounded-lg">إلغاء</button><button type="submit" class="bg-blue-600 text-white px-8 py-2 rounded-lg hover:bg-blue-800">حفظ التعيين</button></div></form></div></div>

    <!-- View Answers Modal -->
    <div x-show="modals.viewAnswersModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div @click.away="close('viewAnswersModal')" class="bg-white rounded-xl p-6 w-full max-w-2xl" x-transition>
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <div>
                    <h3 class="text-xl font-bold text-mai-dark" x-text="'إجابات نموذج: ' + selectedForm.name"></h3>
                    <p class="text-sm text-gray-500" dir="ltr" x-text="new Date(selectedForm.date).toLocaleString('en-GB')"></p>
                </div>
                <button @click="close('viewAnswersModal')" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
            </div>
            <div class="max-h-[60vh] overflow-y-auto pr-4">
                <dl class="space-y-4">
                    <template x-for="[questionId, answerText] in Object.entries(selectedForm.answers)" :key="questionId">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <dt class="font-semibold text-gray-700" x-text="questionsMap[questionId] || 'سؤال غير معروف (ID: ' + questionId + ')'"></dt>
                            <dd class="mt-1 text-gray-900 whitespace-pre-wrap" x-text="answerText || '-'"></dd>
                        </div>
                    </template>
                </dl>
            </div>
            <div class="flex justify-between items-center mt-6 pt-4 border-t">
                <!-- NEW: DOWNLOAD BUTTON -->
                <a :href="'download_answers_pdf.php?id=' + selectedForm.id" target="_blank" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-download ml-2"></i>تحميل PDF
                </a>
                <button type="button" @click="close('viewAnswersModal')" class="px-8 py-2 border rounded-lg">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- Complete Alpine.js script with AJAX pagination and modal controls -->
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pageManager', (customerData, initialData, questionsMap) => ({
        // --- STATE ---
        activeTab: 'appointments',
        customer: customerData,
        questionsMap: questionsMap,
        modals: { editCustomerModal: false, assignFormModal: false, viewAnswersModal: false },
        // MODIFIED: Added 'id' to store the client_form ID
        selectedForm: { id: null, name: '', date: '', answers: {} }, 
        
        // Holds data for all tabs, initialized by PHP
        tabs: initialData,

        // --- METHODS ---
        open(modalId, data = null) {
            if (modalId === 'viewAnswersModal' && data) {
                // MODIFIED: Store the form submission's unique ID
                this.selectedForm.id = data.id; 
                this.selectedForm.name = data.form_name;
                this.selectedForm.date = data.created_at;
                try {
                    this.selectedForm.answers = JSON.parse(data.answers || '{}');
                } catch (e) { 
                    this.selectedForm.answers = {}; 
                }
            }
            this.modals[modalId] = true;
        },
        close(modalId) {
            this.modals[modalId] = false;
        },

        // --- AJAX Pagination Method ---
        fetchTabData(tabName, page) {
            if (page < 1 || page > this.tabs[tabName].totalPages) {
                return; // Do nothing if page is out of bounds
            }

            this.tabs[tabName].loading = true;

            const url = `fetch_tab_data.php?customer_id=${this.customer.id}&tab=${tabName}&page=${page}`;

            fetch(url)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        this.tabs[tabName].items = result.data;
                        this.tabs[tabName].page = result.pagination.current_page;
                        this.tabs[tabName].totalPages = result.pagination.total_pages;
                    } else {
                        console.error('Failed to load data:', result.error);
                    }
                })
                .catch(error => console.error('Network error:', error))
                .finally(() => {
                    this.tabs[tabName].loading = false;
                });
        }
    }));
});
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>