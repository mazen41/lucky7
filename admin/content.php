<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php';

checkAdminAuth();
$admin = getCurrentAdmin();

// --- Database Connection ---
$database = new Database();
$db = $database->getConnection();
$success = '';
$error = '';

// --- Helper Functions ---
// Function for the 'site_settings' table
if (!function_exists('updateSiteSetting')) {
    function updateSiteSetting($db, $key, $value) {
        try {
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
            return $stmt->execute(['key' => $key, 'value' => $value]);
        } catch (PDOException $e) {
            error_log("Failed to update site_settings for key '$key': " . $e->getMessage());
            return false;
        }
    }
}
// Function for the 'settings' table
if (!function_exists('updateBusinessSetting')) {
    function updateBusinessSetting($db, $key, $value) {
        try {
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, 'string') ON DUPLICATE KEY UPDATE `value` = ?");
            return $stmt->execute([$key, $value, $value]);
        } catch (PDOException $e) {
            error_log("Failed to update settings for key '$key': " . $e->getMessage());
            return false;
        }
    }
}

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Action: Delete a social media link (from site_settings)
    if (isset($_POST['action']) && $_POST['action'] == 'delete_social_link') {
        $keyToDelete = $_POST['setting_key_to_delete'];
        if (substr($keyToDelete, -4) === '_url') {
            try {
                $stmt = $db->prepare("DELETE FROM site_settings WHERE setting_key = :key");
                if ($stmt->execute(['key' => $keyToDelete])) {
                    $success = 'تم حذف الرابط الاجتماعي بنجاح.';
                } else {
                    $error = 'فشل حذف الرابط.';
                }
            } catch (PDOException $e) { $error = 'خطأ في قاعدة البيانات.'; }
        } else {
            $error = 'محاولة حذف غير صالحة.';
        }
    }
    // Action: Update Business Info (from settings table)
    elseif (isset($_POST['update_business_info'])) {
        $settings_to_update = [
            'company_name'      => $_POST['company_name'] ?? '',
            'company_address'   => $_POST['company_address'] ?? '',
            'company_phone'     => $_POST['company_phone'] ?? '',
            'company_email'     => $_POST['company_email'] ?? '',
            'company_twitter'   => $_POST['company_twitter'] ?? '',
            'company_instagram' => $_POST['company_instagram'] ?? ''
        ];
        $all_ok = true;
        foreach ($settings_to_update as $key => $value) {
            if (!updateBusinessSetting($db, $key, $value)) {
                $all_ok = false;
            }
        }
        if ($all_ok) {
            $success = 'تم تحديث معلومات الشركة بنجاح';
        } else {
            $error = 'حدث خطأ أثناء حفظ معلومات الشركة';
        }
    }
    // Action: Add a new social media link (to site_settings)
    elseif (isset($_POST['add_social_link'])) {
        $socialName = trim(strtolower($_POST['new_social_name']));
        $socialUrl = trim($_POST['new_social_url']);
        if (!empty($socialName) && !empty($socialUrl) && filter_var($socialUrl, FILTER_VALIDATE_URL)) {
            $socialName = preg_replace('/[^a-z0-9]/', '', $socialName);
            $setting_key = $socialName . '_url';
            try {
                $stmt_check = $db->prepare("SELECT COUNT(*) FROM site_settings WHERE setting_key = :key");
                $stmt_check->execute(['key' => $setting_key]);
                if ($stmt_check->fetchColumn()) {
                    $error = "هذا الرابط الاجتماعي ('" . htmlspecialchars($socialName) . "') موجود بالفعل.";
                } else {
                    $stmt_insert = $db->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES (:key, :value, 'url')");
                    if ($stmt_insert->execute(['key' => $setting_key, 'value' => $socialUrl])) {
                        $success = 'تمت إضافة الرابط الاجتماعي بنجاح.';
                    } else {
                        $error = 'حدث خطأ أثناء إضافة الرابط.';
                    }
                }
            } catch (PDOException $e) { $error = 'خطأ في قاعدة البيانات.'; }
        } else {
            $error = 'يرجى إدخال اسم منصة صحيح (انجليزي) ورابط URL صالح.';
        }
    }
    // Action: Update any other single setting (to site_settings)
    elseif (isset($_POST['setting_key'])) {
        if (updateSiteSetting($db, $_POST['setting_key'], $_POST['setting_value'])) {
            $success = 'تم حفظ التغييرات بنجاح';
        } else {
            $error = 'حدث خطأ أثناء حفظ التغييرات';
        }
    }
}

// --- DATA FETCHING for site_settings ---
$site_settings_raw = $db->query("SELECT * FROM site_settings ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
$site_settings = [];
foreach ($site_settings_raw as $s) {
    $site_settings[$s['setting_key']] = $s['setting_value'];
}

// --- DATA FETCHING for settings table ---
$business_settings = [];
try {
    $stmt = $db->query("SELECT `key`, `value` FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $business_settings[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    $error = "لم يتم العثور على جدول الإعدادات 'settings'.";
}

try {
    $categories_stmt = $db->prepare("SELECT id, name_ar, image_url FROM service_categories ORDER BY id ASC LIMIT 4");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    $error .= " خطأ في جلب بيانات الأقسام.";
}

$page_title = 'محتوى وإعدادات الموقع';
ob_start();
?>

<!-- Main Content Wrapper -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-mai-brown rounded-lg flex items-center justify-center text-white"><i class="fas fa-pencil-ruler text-xl"></i></div>
                <div>
                    <h1 class="text-lg font-bold text-mai-dark-brown">محتوى وإعدادات الموقع</h1>
                    <p class="text-xs text-gray-500">تحرير النصوص، الصور، ومعلومات الشركة الظاهرة في الموقع</p>
                </div>
            </div>
            <!-- START: ADDED BUTTON -->
            <a href="/admin/settings.php" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800 font-semibold hover:bg-gray-300 transition-colors text-sm">
                <i class="fas fa-arrow-left ml-2"></i>
                <span>العودة للإعدادات</span>
            </a>
            <!-- END: ADDED BUTTON -->
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg" role="alert"><p class="font-bold">نجاح</p><p><?php echo htmlspecialchars($success); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert"><p class="font-bold">خطأ</p><p><?php echo htmlspecialchars($error); ?></p></div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-lg">
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8 space-x-reverse px-6 -mb-px">
                <button onclick="switchTab('content')" id="content-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm focus:outline-none border-mai-brown text-mai-brown"><i class="fas fa-file-alt mr-2"></i>المحتوى الأساسي</button>
                <button onclick="switchTab('business')" id="business-tab" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm focus:outline-none"><i class="fas fa-building mr-2"></i>معلومات الشركة</button>
                <button onclick="switchTab('social')" id="social-tab" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm focus:outline-none"><i class="fas fa-share-alt mr-2"></i>روابط التواصل</button>
                <button onclick="switchTab('images')" id="images-tab" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm focus:outline-none"><i class="fas fa-images mr-2"></i>إدارة الصور</button>
            </nav>
        </div>
    </div>

    <!-- Tab Content: Basic Content -->
    <div id="content-content" class="tab-content" style="display: block; animation: fadeIn 0.5s;">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><i class="fas fa-home mr-2"></i>القسم الرئيسي (Hero)</h2>
                <form method="POST" class="mb-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">العنوان الرئيسي</label>
                        <input type="text" name="setting_value" value="<?= htmlspecialchars($site_settings['hero_title_ar'] ?? '') ?>" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        <input type="hidden" name="setting_key" value="hero_title_ar">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">النص التوضيحي</label>
                        <textarea name="setting_value" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"><?= htmlspecialchars($site_settings['hero_subtitle_ar'] ?? '') ?></textarea>
                         <input type="hidden" name="setting_key" value="hero_subtitle_ar">
                    </div>
                    <button type="submit" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown">حفظ القسم الرئيسي</button>
                </form>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><i class="fas fa-info-circle mr-2"></i>قسم من نحن</h2>
                <form method="POST" class="space-y-4">
                     <div>
                         <label class="block text-sm font-medium text-gray-700 mb-2">عنوان القسم</label>
                         <input type="text" name="setting_value" value="<?= htmlspecialchars($site_settings['about_title_ar'] ?? '') ?>" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                         <input type="hidden" name="setting_key" value="about_title_ar">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">وصف القسم</label>
                        <textarea name="setting_value" rows="4" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"><?= htmlspecialchars($site_settings['about_description_ar'] ?? '') ?></textarea>
                        <input type="hidden" name="setting_key" value="about_description_ar">
                    </div>
                    <button type="submit" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown">حفظ قسم من نحن</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Content: Business Info -->
    <div id="business-content" class="tab-content" style="display: none; animation: fadeIn 0.5s;">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-mai-dark-brown mb-6">معلومات الشركة</h2>
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">اسم الشركة</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($business_settings['company_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                        <input type="text" name="company_phone" value="<?php echo htmlspecialchars($business_settings['company_phone'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">عنوان الشركة</label>
                    <textarea name="company_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required><?php echo htmlspecialchars($business_settings['company_address'] ?? ''); ?></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                        <input type="email" name="company_email" value="<?php echo htmlspecialchars($business_settings['company_email'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">رابط تويتر</label>
                        <input type="url" name="company_twitter" value="<?php echo htmlspecialchars($business_settings['company_twitter'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">رابط انستجرام</label>
                    <input type="url" name="company_instagram" value="<?php echo htmlspecialchars($business_settings['company_instagram'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                </div>
                <button type="submit" name="update_business_info" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown transition-colors"><i class="fas fa-save mr-2"></i>حفظ معلومات الشركة</button>
            </form>
        </div>
    </div>
    
    <!-- Tab Content: Social Links -->
    <div id="social-content" class="tab-content" style="display: none; animation: fadeIn 0.5s;">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><i class="fas fa-share-alt mr-2"></i>روابط التواصل الاجتماعي</h2>
            <?php $social_links = array_filter($site_settings_raw, function($s) { return strpos($s['setting_key'], '_url') !== false; }); ?>
            <?php foreach ($social_links as $setting): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars(ucfirst(str_replace('_url', '', $setting['setting_key']))) ?></label>
                    <div class="flex items-center gap-2">
                        <form method="POST" class="flex-grow flex items-center gap-2">
                            <input type="hidden" name="setting_key" value="<?= htmlspecialchars($setting['setting_key']) ?>">
                            <input type="url" name="setting_value" value="<?= htmlspecialchars($setting['setting_value']) ?>" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown" placeholder="https://...">
                            <button type="submit" title="حفظ" class="bg-mai-brown text-white px-4 h-full rounded-lg hover:bg-mai-dark-brown flex items-center"><i class="fas fa-save"></i></button>
                        </form>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الرابط؟');">
                            <input type="hidden" name="action" value="delete_social_link">
                            <input type="hidden" name="setting_key_to_delete" value="<?= htmlspecialchars($setting['setting_key']) ?>">
                            <button type="submit" title="حذف" class="bg-red-600 text-white px-4 h-full rounded-lg hover:bg-red-700 flex items-center"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
             <div class="mt-6 pt-4 border-t border-gray-200">
                <h3 class="text-md font-bold text-mai-dark-brown mb-3"><i class="fas fa-plus-circle mr-2"></i>إضافة رابط جديد</h3>
                <form method="POST" class="space-y-3">
                    <div><label for="new_social_name" class="block text-sm font-medium text-gray-700">اسم المنصة (انجليزي، كلمة واحدة)</label><input type="text" id="new_social_name" name="new_social_name" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown" placeholder="مثال: linkedin" required></div>
                    <div><label for="new_social_url" class="block text-sm font-medium text-gray-700">الرابط الكامل</label><input type="url" id="new_social_url" name="new_social_url" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown" placeholder="https://..." required></div>
                    <button type="submit" name="add_social_link" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"><i class="fas fa-plus"></i><span>إضافة الرابط</span></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Content: Image Management -->
    <div id="images-content" class="tab-content" style="display: none; animation: fadeIn 0.5s;">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><i class="fas fa-images mr-2"></i>إدارة الصور الرئيسية</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center"><h3 class="font-medium text-gray-700 mb-2">صورة القسم الرئيسي</h3><img src="<?= !empty($site_settings['hero_image']) ? '../' . htmlspecialchars($site_settings['hero_image']) : 'https://via.placeholder.com/150/mai-brown/FFFFFF?text=Hero'; ?>" alt="Hero Preview" class="w-24 h-24 object-cover rounded-lg mb-4 border shadow-sm"><p class="text-xs text-gray-500 mb-4">الصورة الظاهرة في القسم الرئيسي</p><input type="file" id="heroImageInput" accept="image/*" class="hidden"><button onclick="document.getElementById('heroImageInput').click()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown mt-auto">تغيير الصورة</button></div>
                <div class="text-center p-4 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center"><h3 class="font-medium text-gray-700 mb-2">صورة قسم من نحن</h3><img src="<?= !empty($site_settings['about_image']) ? '../' . htmlspecialchars($site_settings['about_image']) : 'https://via.placeholder.com/150/mai-brown/FFFFFF?text=About'; ?>" alt="About Preview" class="w-24 h-24 object-cover rounded-lg mb-4 border shadow-sm"><p class="text-xs text-gray-500 mb-4">الصورة الظاهرة في قسم من نحن</p><input type="file" id="aboutImageInput" accept="image/*" class="hidden"><button onclick="document.getElementById('aboutImageInput').click()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown mt-auto">تغيير الصورة</button></div>
                <div class="text-center p-4 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center"><h3 class="font-medium text-gray-700 mb-2">شعار الموقع</h3><img src="<?= !empty($site_settings['site_logo']) ? '../' . htmlspecialchars($site_settings['site_logo']) : 'https://via.placeholder.com/150/mai-brown/FFFFFF?text=Logo'; ?>" alt="Logo Preview" class="w-24 h-24 object-contain rounded-lg mb-4 border shadow-sm p-2"><p class="text-xs text-gray-500 mb-4">شعار صالون MAI</p><input type="file" id="logoImageInput" accept="image/*" class="hidden"><button onclick="document.getElementById('logoImageInput').click()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown mt-auto">تغيير الشعار</button></div>
            </div>
        </div>
        <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><i class="fas fa-tags mr-2"></i>صور الأقسام (عرض أول 4)</h2>
            <?php if (!empty($categories)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($categories as $category): ?>
                        <div class="text-center p-4 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-between"><img src="<?= !empty($category['image_url']) ? '../' . htmlspecialchars($category['image_url']) : 'https://via.placeholder.com/150/mai-brown/FFFFFF?text=MAI'; ?>" alt="<?= htmlspecialchars($category['name_ar']) ?>" class="w-24 h-24 object-cover rounded-lg mb-4 border shadow-sm"><h3 class="font-medium text-gray-700 mb-2"><?= htmlspecialchars($category['name_ar']) ?></h3><input type="file" id="categoryImageInput_<?= $category['id'] ?>" accept="image/*" class="hidden"><button onclick="document.getElementById('categoryImageInput_<?= $category['id'] ?>').click()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown w-full mt-auto">تغيير الصورة</button></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500">لم يتم العثور على أقسام. يرجى إضافة أقسام من صفحة الخدمات.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upload Modal & Scripts -->
<style> @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } } </style>
<div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"><div class="bg-white rounded-2xl max-w-md w-full p-6 text-center"><div id="uploadProgress"><i class="fas fa-spinner fa-spin text-4xl text-mai-brown mb-4"></i><h3 class="text-lg font-bold text-mai-dark-brown">جاري رفع الصورة...</h3><p class="text-gray-600">يرجى الانتظار</p></div><div id="uploadResult" class="hidden"><div id="uploadSuccess" class="hidden"><i class="fas fa-check-circle text-4xl text-green-600 mb-4"></i><h3 class="text-lg font-bold text-green-600">تم رفع الصورة بنجاح!</h3></div><div id="uploadError" class="hidden"><i class="fas fa-exclamation-circle text-4xl text-red-600 mb-4"></i><h3 class="text-lg font-bold text-red-600">فشل في رفع الصورة</h3><p id="errorMessage" class="text-gray-600"></p></div><button onclick="closeUploadModal()" class="mt-4 bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">إغلاق</button></div></div></div>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('border-mai-brown', 'text-mai-brown');
            button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        });
        document.getElementById(tabName + '-content').style.display = 'block';
        const buttonToActivate = document.getElementById(tabName + '-tab');
        buttonToActivate.classList.add('border-mai-brown', 'text-mai-brown');
        buttonToActivate.classList.remove('border-transparent', 'text-gray-500');
    }

    function setupImageUpload(inputId, uploadType, itemId = null) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', e => { if (e.target.files[0]) uploadImage(e.target.files[0], uploadType, itemId); });
    }

    function uploadImage(file, uploadType, itemId = null) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('upload_type', uploadType);
        if (itemId) formData.append('item_id', itemId);
        
        const modal = document.getElementById('uploadModal');
        modal.classList.remove('hidden');
        ['uploadProgress', 'uploadResult', 'uploadSuccess', 'uploadError'].forEach(id => document.getElementById(id).classList.add('hidden'));
        document.getElementById('uploadProgress').classList.remove('hidden');

        fetch('upload.php', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
            document.getElementById('uploadProgress').classList.add('hidden');
            document.getElementById('uploadResult').classList.remove('hidden');
            if (data.success) {
                document.getElementById('uploadSuccess').classList.remove('hidden');
                setTimeout(() => location.reload(), 1500);
            } else {
                document.getElementById('uploadError').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = data.message || 'An unknown error occurred.';
            }
        }).catch(error => {
            document.getElementById('uploadError').classList.remove('hidden');
            document.getElementById('errorMessage').textContent = 'حدث خطأ في الاتصال بالخادم.';
        });
    }

    function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); }

    document.addEventListener('DOMContentLoaded', function() {
        setupImageUpload('heroImageInput', 'hero');
        setupImageUpload('aboutImageInput', 'about');
        setupImageUpload('logoImageInput', 'logo');
        <?php foreach ($categories as $category): ?>
            setupImageUpload('categoryImageInput_<?= $category['id'] ?>', 'category', <?= $category['id'] ?>);
        <?php endforeach; ?>
        
        document.getElementById('uploadModal').addEventListener('click', e => { if (e.target.id === 'uploadModal') closeUploadModal(); });
    });
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>