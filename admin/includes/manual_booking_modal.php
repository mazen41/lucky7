<?php
/**
 * Manual Booking Modal - Complete Implementation
 * Senior PHP Engineer Implementation with robust error handling
 */

// Security check - ensure this is included, not accessed directly
if (!defined('ABSPATH') && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Database connection for dropdown data
$modalDb = null;
$categories = [];
$services = [];
$specialists = [];

try {
    // Try multiple database connection approaches
    if (file_exists(__DIR__ . '/../../config/database.php')) {
        require_once __DIR__ . '/../../config/database.php';
        if (class_exists('Database')) {
            $modalDb = (new Database())->getConnection();
        }
    }
    
    // Fallback: try direct PDO connection
    if (!$modalDb && defined('DB_HOST')) {
        $modalDb = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
    
    // Load categories, services and specialists
    if ($modalDb) {
        // Get active service categories
        try {
            $stmt = $modalDb->prepare("SELECT id, name_ar as name, color FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name_ar");
            $stmt->execute();
            $categories = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if service_categories table doesn't exist
            $categories = [];
        }
        
        // Get active services - try multiple approaches
        try {
            // First, check what columns exist in services table
            $stmt = $modalDb->query("DESCRIBE services");
            $serviceColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build query based on available columns
            $selectFields = ['id'];
            if (in_array('name_ar', $serviceColumns)) $selectFields[] = 'name_ar as name';
            elseif (in_array('name', $serviceColumns)) $selectFields[] = 'name';
            elseif (in_array('title_ar', $serviceColumns)) $selectFields[] = 'title_ar as name';
            elseif (in_array('title', $serviceColumns)) $selectFields[] = 'title as name';
            else $selectFields[] = "'خدمة' as name";
            
            if (in_array('duration_min', $serviceColumns)) $selectFields[] = 'duration_min';
            elseif (in_array('duration', $serviceColumns)) $selectFields[] = 'duration';
            
            if (in_array('fixed_price', $serviceColumns)) $selectFields[] = 'fixed_price';
            elseif (in_array('price', $serviceColumns)) $selectFields[] = 'price';
            
            if (in_array('category_id', $serviceColumns)) $selectFields[] = 'category_id';
            
            $whereClause = 'WHERE 1=1';
            if (in_array('is_active', $serviceColumns)) $whereClause .= ' AND is_active = 1';
            
            $orderClause = 'ORDER BY id';
            if (in_array('name_ar', $serviceColumns)) $orderClause = 'ORDER BY name_ar';
            elseif (in_array('name', $serviceColumns)) $orderClause = 'ORDER BY name';
            
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM services $whereClause $orderClause";
            $stmt = $modalDb->prepare($sql);
            $stmt->execute();
            $services = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Services query error: " . $e->getMessage());
            $services = [];
        }
        
        // Get active specialists from team_members - try multiple approaches
        try {
            // First, check what columns exist in team_members table
            $stmt = $modalDb->query("DESCRIBE team_members");
            $teamColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build query based on available columns
            $selectFields = ['id'];
            if (in_array('name_ar', $teamColumns)) $selectFields[] = 'name_ar as name';
            elseif (in_array('name', $teamColumns)) $selectFields[] = 'name';
            else $selectFields[] = "'متخصص' as name";
            
            $whereClause = 'WHERE 1=1';
            if (in_array('is_active', $teamColumns)) $whereClause .= ' AND is_active = 1';
            
            // Try to filter specialists if possible
            if (in_array('is_specialist', $teamColumns)) {
                $whereClause .= ' AND is_specialist = 1';
            } elseif (in_array('position_ar', $teamColumns)) {
                $whereClause .= " AND (position_ar LIKE '%أخصائي%' OR position_ar LIKE '%متخصص%')";
            }
            
            $orderClause = 'ORDER BY id';
            if (in_array('name_ar', $teamColumns)) $orderClause = 'ORDER BY name_ar';
            elseif (in_array('name', $teamColumns)) $orderClause = 'ORDER BY name';
            
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM team_members $whereClause $orderClause";
            $stmt = $modalDb->prepare($sql);
            $stmt->execute();
            $specialists = $stmt->fetchAll();
            
            // If no specialists found, try without specialist filter
            if (empty($specialists) && (in_array('is_specialist', $teamColumns) || in_array('position_ar', $teamColumns))) {
                $whereClause = 'WHERE 1=1';
                if (in_array('is_active', $teamColumns)) $whereClause .= ' AND is_active = 1';
                
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM team_members $whereClause $orderClause";
                $stmt = $modalDb->prepare($sql);
                $stmt->execute();
                $specialists = $stmt->fetchAll();
            }
            
        } catch (PDOException $e) {
            error_log("Team members query error: " . $e->getMessage());
            $specialists = [];
        }
    }
} catch (Exception $e) {
    error_log("Manual booking modal DB error: " . $e->getMessage());
}

// Debug output - remove after testing
if (empty($services)) {
    error_log("DEBUG: No services found in modal");
}
if (empty($specialists)) {
    error_log("DEBUG: No specialists found in modal");
}
?>

<!-- Manual Booking Modal - Clean Professional Implementation -->
<div id="manualBookingModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[95vh] overflow-y-auto" dir="rtl">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-mai-brown">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-plus text-mai-brown"></i>
                </div>
                <h3 class="text-lg font-bold text-white">إنشاء حجز جديد</h3>
            </div>
            <button type="button" class="close-modal text-white hover:text-gray-200 transition-all">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <form id="manualBookingForm" class="p-5" method="POST" action="booking_actions.php">
            <input type="hidden" name="action" value="create_booking">
            <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)); ?>">
            
            <!-- Alert Messages -->
            <div id="bookingAlert" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            
            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- Left Column: Customer Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-mai-brown mb-3 flex items-center gap-2">
                        <i class="fas fa-user"></i>
                        معلومات العميل
                    </h4>
                    
                    <div class="space-y-3">
                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال *</label>
                            <div class="relative">
                                <input 
                                    type="tel" 
                                    id="customer_phone" 
                                    name="customer_phone" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                    placeholder="05xxxxxxxx"
                                    required
                                >
                                <button type="button" id="searchCustomerBtn" class="absolute left-2 top-2 text-mai-brown hover:text-mai-dark-brown">
                                    <i class="fas fa-search text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم العميل *</label>
                            <input 
                                type="text" 
                                id="customer_name" 
                                name="customer_name" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                placeholder="الاسم الكامل"
                                required
                            >
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                            <input 
                                type="email" 
                                id="customer_email" 
                                name="customer_email" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                placeholder="email@example.com"
                            >
                        </div>
                    </div>
                </div>

                <!-- Right Column: Appointment Details -->
                <div class="bg-mai-light-cream p-4 rounded-lg">
                    <h4 class="font-semibold text-mai-brown mb-3 flex items-center gap-2">
                        <i class="fas fa-calendar-alt"></i>
                        تفاصيل الموعد
                    </h4>
                    
                    <div class="space-y-3">
                        <!-- Service Category -->
                        <?php if (!empty($categories)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تصنيف الخدمة</label>
                            <select 
                                id="service_category_id" 
                                name="service_category_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm"
                                onchange="filterServicesByCategory()"
                            >
                                <option value="">جميع التصنيفات</option>
                                <?php foreach ($categories as $category): ?>
                                    <option 
                                        value="<?php echo htmlspecialchars($category['id']); ?>"
                                        data-color="<?php echo htmlspecialchars($category['color'] ?? '#8B4513'); ?>"
                                    >
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Service -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الخدمة المطلوبة *</label>
                            <select 
                                id="service_id" 
                                name="service_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                required
                            >
                                <option value="">اختر الخدمة</option>
                                <?php foreach ($services as $service): ?>
                                    <option 
                                        value="<?php echo htmlspecialchars($service['id']); ?>" 
                                        data-duration="<?php echo htmlspecialchars($service['duration_min'] ?? $service['duration'] ?? 60); ?>"
                                        data-price="<?php echo htmlspecialchars($service['fixed_price'] ?? $service['price'] ?? 0); ?>"
                                        data-category="<?php echo htmlspecialchars($service['category_id'] ?? ''); ?>"
                                    >
                                        <?php 
                                        $serviceName = $service['name'] ?? $service['name_ar'] ?? 'خدمة';
                                        $duration = $service['duration_min'] ?? $service['duration'] ?? '';
                                        echo htmlspecialchars($serviceName);
                                        if (!empty($duration)): ?>
                                            (<?php echo $duration; ?> دقيقة)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Specialist -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الأخصائية *</label>
                            <select 
                                id="specialist_id" 
                                name="specialist_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                required
                            >
                                <option value="">اختر الأخصائية</option>
                                <?php if (!empty($specialists)): ?>
                                    <?php foreach ($specialists as $specialist): ?>
                                        <option value="<?php echo htmlspecialchars($specialist['id']); ?>">
                                            <?php echo htmlspecialchars($specialist['name'] ?? $specialist['name_ar'] ?? 'متخصص'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>لا توجد أخصائيات متاحة</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Date & Time -->
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ *</label>
                                <input 
                                    type="date" 
                                    id="appointment_date" 
                                    name="appointment_date" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                    min="<?php echo date('Y-m-d'); ?>"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الوقت *</label>
                                <input 
                                    type="time" 
                                    id="appointment_time" 
                                    name="appointment_time" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                    required
                                >
                            </div>
                        </div>

                        <!-- Duration -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المدة (بالدقائق) *</label>
                            <input 
                                type="number" 
                                id="duration" 
                                name="duration" 
                                min="15" 
                                max="480" 
                                step="15" 
                                value="60"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                required
                            >
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">حالة الموعد *</label>
                            <select 
                                id="status" 
                                name="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm" 
                                required
                            >
                                <option value="confirmed" selected>مؤكد</option>
                                <option value="pending">في الانتظار</option>
                                <option value="done">منتهي</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات إضافية</label>
                <textarea 
                    id="notes" 
                    name="notes" 
                    rows="2" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-mai-brown focus:border-mai-brown text-sm resize-vertical" 
                    placeholder="أي ملاحظات خاصة بالموعد..."
                ></textarea>
            </div>

            <!-- Conflict Warning -->
            <div id="conflictWarning" class="hidden mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-center text-sm">
                    <i class="fas fa-exclamation-triangle text-yellow-600 ml-2"></i>
                    <span class="text-yellow-800">تحذير: يوجد موعد آخر للأخصائية في نفس الوقت المحدد</span>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-gray-200">
                <button 
                    type="button" 
                    class="close-modal px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-all text-sm"
                >
                    إلغاء
                </button>
                <button 
                    type="submit" 
                    id="saveBookingBtn" 
                    class="px-4 py-2 bg-mai-brown text-white rounded-md hover:bg-mai-dark-brown transition-all text-sm"
                >
                    <i class="fas fa-save ml-1"></i>
                    حفظ الموعد
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('manualBookingModal');
    const form = document.getElementById('manualBookingForm');
    const alert = document.getElementById('bookingAlert');
    
    // Inline validator helpers
    const fieldConfigs = {
        customer_phone: {
            label: 'رقم الجوال',
            validate: (v) => /^05\d{8}$/.test(String(v).trim()),
            message: 'رقم الجوال يجب أن يكون بالشكل 05xxxxxxxx'
        },
        customer_name: {
            label: 'اسم العميل',
            validate: (v) => String(v).trim().length >= 2,
            message: 'الاسم مطلوب'
        },
        service_id: {
            label: 'الخدمة المطلوبة',
            validate: (v) => String(v).trim() !== '',
            message: 'يرجى اختيار الخدمة'
        },
        specialist_id: {
            label: 'الأخصائية',
            validate: (v) => String(v).trim() !== '',
            message: 'يرجى اختيار الأخصائية'
        },
        appointment_date: {
            label: 'التاريخ',
            validate: (v) => !!v,
            message: 'يرجى تحديد التاريخ'
        },
        appointment_time: {
            label: 'الوقت',
            validate: (v) => !!v,
            message: 'يرجى تحديد الوقت'
        },
        duration: {
            label: 'المدة',
            validate: (v) => Number(v) >= 15,
            message: 'المدة يجب ألا تقل عن 15 دقيقة'
        }
    };

    function getField(id){ return document.getElementById(id); }

    function ensureErrorContainer(inputEl){
        if (!inputEl) return null;
        const key = 'err_'+inputEl.id;
        let err = document.getElementById(key);
        if (!err){
            err = document.createElement('small');
            err.id = key;
            err.className = 'block mt-1 text-xs text-red-600 hidden';
            // Append after the input element's container if exists
            const wrapper = inputEl.closest('div') || inputEl.parentElement;
            (wrapper || inputEl).appendChild(err);
        }
        return err;
    }

    function showFieldError(id, message){
        const el = getField(id);
        const err = ensureErrorContainer(el);
        if (el){ el.classList.add('border-red-500'); el.classList.add('focus:border-red-500'); }
        if (err){ err.textContent = message; err.classList.remove('hidden'); }
    }

    function clearFieldError(id){
        const el = getField(id);
        const err = el ? document.getElementById('err_'+id) : null;
        if (el){ el.classList.remove('border-red-500','focus:border-red-500'); }
        if (err){ err.textContent = ''; err.classList.add('hidden'); }
    }

    function validateField(id){
        const cfg = fieldConfigs[id];
        if (!cfg) return true;
        const val = (getField(id)?.value) ?? '';
        if (!cfg.validate(val)){
            showFieldError(id, cfg.message);
            return false;
        }
        clearFieldError(id);
        return true;
    }

    function validateForm(){
        let ok = true;
        Object.keys(fieldConfigs).forEach(id => {
            const valid = validateField(id);
            if (!valid) ok = false;
        });
        // hide top alert if using inline errors
        hideAlert();
        return ok;
    }
    
    // Modal controls
    document.querySelectorAll('.open-booking-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            // Set default date to today
            document.getElementById('appointment_date').value = new Date().toISOString().split('T')[0];
            // Clear all inline errors when opening
            Object.keys(fieldConfigs).forEach(clearFieldError);
            hideAlert();
        });
    });

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            form.reset();
            hideAlert();
        });
    });

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            form.reset();
            hideAlert();
        }
    });

    // Customer search
    document.getElementById('searchCustomerBtn').addEventListener('click', function() {
        const phone = document.getElementById('customer_phone').value.trim();
        if (!phone) {
            showAlert('الرجاء إدخال رقم الجوال أولاً', 'warning');
            return;
        }
        
        fetch('booking_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=search_customer&phone=${encodeURIComponent(phone)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer) {
                document.getElementById('customer_name').value = data.customer.name || '';
                document.getElementById('customer_email').value = data.customer.email || '';
                showAlert('تم العثور على العميل', 'success');
            } else {
                showAlert('لم يتم العثور على العميل - سيتم إنشاء عميل جديد', 'info');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            showAlert('حدث خطأ في البحث', 'error');
        });
    });

    // Service category filtering
    function filterServicesByCategory() {
        const categorySelect = document.getElementById('service_category_id');
        const serviceSelect = document.getElementById('service_id');
        const selectedCategory = categorySelect.value;
        
        // Get all service options
        const allOptions = serviceSelect.querySelectorAll('option[data-category]');
        
        // Show/hide options based on category
        allOptions.forEach(option => {
            const optionCategory = option.dataset.category;
            if (!selectedCategory || optionCategory === selectedCategory || optionCategory === '') {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
        
        // Reset service selection if current selection is now hidden
        const currentService = serviceSelect.value;
        if (currentService) {
            const currentOption = serviceSelect.querySelector(`option[value="${currentService}"]`);
            if (currentOption && currentOption.style.display === 'none') {
                serviceSelect.value = '';
                document.getElementById('duration').value = 60; // Reset duration
            }
        }
    }

    // Service duration auto-fill
    document.getElementById('service_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const duration = selectedOption.dataset.duration;
        if (duration) {
            document.getElementById('duration').value = duration;
        }
    });

    // Conflict checking
    function checkConflict() {
        const specialistId = document.getElementById('specialist_id').value;
        const date = document.getElementById('appointment_date').value;
        const time = document.getElementById('appointment_time').value;
        const duration = document.getElementById('duration').value;

        if (specialistId && date && time && duration) {
            const datetime = date + ' ' + time;
            
            fetch('booking_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=check_conflict&specialist_id=${specialistId}&datetime=${encodeURIComponent(datetime)}&duration=${duration}`
            })
            .then(response => response.json())
            .then(data => {
                const warning = document.getElementById('conflictWarning');
                if (data.conflict) {
                    warning.classList.remove('hidden');
                } else {
                    warning.classList.add('hidden');
                }
            })
            .catch(error => console.error('Conflict check error:', error));
        }
    }

    // Add conflict checking listeners
    ['specialist_id', 'appointment_date', 'appointment_time', 'duration'].forEach(id => {
        document.getElementById(id).addEventListener('change', checkConflict);
    });

    // Attach per-field live validators from the beginning
    Object.keys(fieldConfigs).forEach(id => {
        const el = getField(id);
        if (!el) return;
        const evt = (el.tagName === 'SELECT') ? 'change' : 'input';
        el.addEventListener(evt, () => validateField(id));
    });

    // Form submission with early validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!validateForm()) {
            // Focus first invalid field
            for (const id of Object.keys(fieldConfigs)){
                const el = getField(id);
                const cfg = fieldConfigs[id];
                if (el && !cfg.validate(el.value)) { el.focus(); break; }
            }
            return;
        }
        
        const submitBtn = document.getElementById('saveBookingBtn');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i>جاري الحفظ...';
        
        const formData = new FormData(form);
        
        // Debug: Log form data
        console.log('Submitting booking with data:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        fetch('booking_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            });
        })
        .then(data => {
            console.log('Parsed response:', data);
            if (data.success) {
                showAlert('تم إنشاء الموعد بنجاح!', 'success');
                setTimeout(() => {
                    location.reload(); // Reload to show new booking
                }, 1500);
            } else {
                showAlert(data.message || 'حدث خطأ في حفظ الموعد', 'error');
            }
        })
        .catch(error => {
            console.error('Submit error:', error);
            showAlert('حدث خطأ في الاتصال بالخادم: ' + error.message, 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });

    // Alert functions
    function showAlert(message, type = 'info') {
        const alertClasses = {
            success: 'bg-green-50 border-green-200 text-green-800',
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800'
        };
        
        alert.className = `p-4 rounded-lg border ${alertClasses[type] || alertClasses.info}`;
        alert.textContent = message;
        alert.classList.remove('hidden');
        
        setTimeout(hideAlert, 5000);
    }
    
    function hideAlert() {
        alert.classList.add('hidden');
    }
});
</script>
