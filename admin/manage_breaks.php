<?php
session_start();
require_once '../config/database.php'; // Adjust path as needed
// Assuming the base layout might need these, or this page becomes part of a larger admin area.
// require_once 'auth.php'; 
// checkAdminAuth();

// --- Language Setup ---
 $lang = $_SESSION['lang'] ?? 'ar';
 $is_rtl = ($lang === 'ar');

// --- Translations ---
 $t = [
    'en' => [
        'page_title' => 'Manage Break Times',
        'page_subtitle' => 'Create, update, and manage break time rules for your team.',
        'add_new_break' => 'Add New Break Rule',
        'edit_break_rule' => 'Edit Break Rule',
        'break_name_en' => 'Break Name (English)',
        'break_name_ar' => 'Break Name (Arabic)',
        'duration' => 'Duration (Minutes)',
        'break_type' => 'Break Type',
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'add_button' => 'Add Break',
        'edit_button' => 'Update Break',
        'cancel_button' => 'Cancel',
        'current_rules' => 'Current Break Rules',
        'name' => 'Name',
        'duration_col' => 'Duration',
        'type_col' => 'Type',
        'actions_col' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'confirm_delete' => 'Are you sure you want to delete this break rule?',
        'minutes' => 'min',
        'no_rules' => 'No break rules have been defined yet.',
        'back_to_settings' => 'Back to Settings'
    ],
    'ar' => [
        'page_title' => 'إدارة أوقات الراحة',
        'page_subtitle' => 'إنشاء وتحديث وإدارة قواعد أوقات الراحة لفريقك.',
        'add_new_break' => 'إضافة قاعدة راحة جديدة',
        'edit_break_rule' => 'تعديل قاعدة الراحة',
        'break_name_en' => 'اسم الراحة (الإنجليزية)',
        'break_name_ar' => 'اسم الراحة (العربية)',
        'duration' => 'المدة (بالدقائق)',
        'break_type' => 'نوع الراحة',
        'paid' => 'مدفوعة',
        'unpaid' => 'غير مدفوعة',
        'add_button' => 'إضافة راحة',
        'edit_button' => 'تحديث الراحة',
        'cancel_button' => 'إلغاء',
        'current_rules' => 'قواعد الراحة الحالية',
        'name' => 'الاسم',
        'duration_col' => 'المدة',
        'type_col' => 'النوع',
        'actions_col' => 'الإجراءات',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'confirm_delete' => 'هل أنت متأكد من رغبتك في حذف قاعدة الراحة هذه؟',
        'minutes' => 'دقيقة',
        'no_rules' => 'لم يتم تعريف أي قواعد للراحة بعد.',
        'back_to_settings' => 'العودة إلى الإعدادات'
    ]
];

 $database = new Database();
 $db = $database->getConnection();

// --- Data Fetching ---
try {
    $stmt = $db->prepare("SELECT id, break_name_en, break_name_ar, duration_minutes, is_paid FROM hr_break_rules ORDER BY duration_minutes ASC");
    $stmt->execute();
    $breaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $breaks = [];
    $_SESSION['error_on_load'] = "Error fetching data: " . $e->getMessage();
}

// --- Page Content Setup for Layout ---
 $page_title = $t[$lang]['page_title']; // This variable can be used in your layout file
ob_start(); // Start capturing the HTML output
?>

<!-- Add any page-specific styles or scripts here if needed -->
<style>
    :root {
        --mai-brown: #A0522D;
        --mai-dark-brown: #8B4513;
    }
    .form-input {
        @apply block w-full px-4 py-2 text-gray-800 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-mai-brown focus:border-transparent transition-all duration-300;
    }
    .btn {
        @apply inline-flex items-center justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white transition-transform transform hover:scale-105;
    }
    .btn-primary { @apply bg-mai-brown hover:bg-mai-dark-brown; }
    .btn-secondary { @apply bg-gray-600 hover:bg-gray-700; }
    .table-row-animation { transition: all 0.3s ease-in-out; }
</style>

<div class="max-w-5xl mx-auto" x-data="breakManager(<?= htmlspecialchars(json_encode($breaks), ENT_QUOTES) ?>)">

    <!-- Back Button -->
    <div class="flex justify-end mb-4">
        <a href="/admin/settings.php" class="inline-flex items-center gap-2 bg-white hover:bg-gray-100 text-gray-700 px-4 py-2 rounded-lg shadow-md transition-transform transform hover:scale-105">
            <i class="fas fa-arrow-<?= $is_rtl ? 'left' : 'right' ?> text-mai-brown"></i>
            <span><?= $t[$lang]['back_to_settings'] ?></span>
        </a>
    </div>

    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow-lg mb-8 p-6">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown"><?= $t[$lang]['page_title'] ?></h1>
                <p class="text-sm text-gray-500"><?= $t[$lang]['page_subtitle'] ?></p>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Form Card -->
    <div class="bg-white p-6 rounded-xl shadow-lg mb-8" x-transition>
        <h2 class="text-xl font-semibold mb-4 text-mai-dark-brown" x-text="isEditing ? '<?= $t[$lang]['edit_break_rule'] ?>' : '<?= $t[$lang]['add_new_break'] ?>'"></h2>
        
        <!-- EDITED: Changed form submission to use AlpineJS for both add and edit -->
        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="break_name_en" class="block text-sm font-medium text-gray-700 mb-1"><?= $t[$lang]['break_name_en'] ?></label>
                    <input type="text" id="break_name_en" class="form-input" x-model="form.break_name_en" required>
                </div>
                <div>
                    <label for="break_name_ar" class="block text-sm font-medium text-gray-700 mb-1"><?= $t[$lang]['break_name_ar'] ?></label>
                    <input type="text" id="break_name_ar" class="form-input" x-model="form.break_name_ar" required>
                </div>
                <div>
                    <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-1"><?= $t[$lang]['duration'] ?></label>
                    <input type="number" id="duration_minutes" class="form-input" x-model="form.duration_minutes" required min="1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= $t[$lang]['break_type'] ?></label>
                    <select id="is_paid" class="form-input" x-model="form.is_paid" required>
                        <option value="1"><?= $t[$lang]['paid'] ?></option>
                        <option value="0"><?= $t[$lang]['unpaid'] ?></option>
                    </select>
                </div>
            </div>
            <div class="mt-6 flex gap-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus mr-2" x-show="!isEditing"></i>
                    <i class="fas fa-save mr-2" x-show="isEditing"></i>
                    <span x-text="isEditing ? '<?= $t[$lang]['edit_button'] ?>' : '<?= $t[$lang]['add_button'] ?>'"></span>
                </button>
                <button type="button" @click="resetForm" class="btn btn-secondary" x-show="isEditing" x-transition>
                    <?= $t[$lang]['cancel_button'] ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Current Rules Table -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <h2 class="text-xl font-semibold mb-4 text-mai-dark-brown"><?= $t[$lang]['current_rules'] ?></h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-<?= $is_rtl ? 'right' : 'left' ?> text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $t[$lang]['name'] ?></th>
                        <th class="px-6 py-3 text-<?= $is_rtl ? 'right' : 'left' ?> text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $t[$lang]['duration_col'] ?></th>
                        <th class="px-6 py-3 text-<?= $is_rtl ? 'right' : 'left' ?> text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $t[$lang]['type_col'] ?></th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $t[$lang]['actions_col'] ?></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-if="breaks.length === 0">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500"><?= $t[$lang]['no_rules'] ?></td>
                        </tr>
                    </template>
                    <template x-for="b in breaks" :key="b.id">
                        <tr class="hover:bg-gray-50 table-row-animation">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="lang === 'ar' ? b.break_name_ar : b.break_name_en"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><span x-text="b.duration_minutes"></span> <?= $t[$lang]['minutes'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span x-show="b.is_paid == 1" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><?= $t[$lang]['paid'] ?></span>
                                <span x-show="b.is_paid == 0" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"><?= $t[$lang]['unpaid'] ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <button @click="editBreak(b)" class="text-indigo-600 hover:text-indigo-900 mx-2"><i class="fas fa-pen"></i> <?= $t[$lang]['edit'] ?></button>
                                <form action="breaks_handler.php" method="POST" class="inline" onsubmit="return confirm('<?= $t[$lang]['confirm_delete'] ?>')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="break_id" :value="b.id">
                                    <button type="submit" class="text-red-600 hover:text-red-900 mx-2"><i class="fas fa-trash"></i> <?= $t[$lang]['delete'] ?></button>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Page-specific Javascript -->
<script>
function breakManager(initialBreaks) {
    return {
        isEditing: false,
        lang: '<?= $lang ?>',
        breaks: initialBreaks,
        form: { id: null, break_name_en: '', break_name_ar: '', duration_minutes: '', is_paid: '1' },
        
        get action() {
            return this.isEditing ? 'update' : 'add';
        },

        editBreak(b) {
            this.isEditing = true;
            // Ensure is_paid is a string for the select dropdown
            this.form = { ...b, is_paid: String(b.is_paid) }; 
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        resetForm() {
            this.isEditing = false;
            this.form = { id: null, break_name_en: '', break_name_ar: '', duration_minutes: '', is_paid: '1' };
        },
        submitForm() {
            const formData = new FormData();
            // Add the correct action to the form data
            formData.append('action', this.action);
            Object.keys(this.form).forEach(key => formData.append(key, this.form[key]));
            
            fetch('breaks_handler.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json()) // Always expect a JSON response
            .then(result => {
                if (result.success) {
                    // If successful, reload the page to see the changes
                    window.location.reload();
                } else {
                    // If not successful, show the specific error from the server
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                // This catches network errors or if the response isn't valid JSON
                console.error('Fetch Error:', error);
                alert('A critical error occurred. Please check the developer console.');
            });
        }
    }
}
</script>

<?php
 $page_content = ob_get_clean(); // Get all the captured HTML
include 'layouts/responsive_base.php'; // Include the master layout
?>