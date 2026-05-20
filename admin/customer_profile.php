<?php

/**
 * Customer Profile - Detailed Customer View
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$customer_id) {
    header('Location: clients.php');
    exit;
}

// Get customer details
$customer_stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$customer_stmt->execute([$customer_id]);
$customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: clients.php');
    exit;
}

// Get customer statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
        COUNT(DISTINCT CASE WHEN DATE(a.start_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN a.id END) as appointments_this_month,
        MAX(a.start_at) as last_appointment_date,
        COUNT(DISTINCT hr.id) as health_forms_count,
        COUNT(DISTINCT CASE WHEN hr.status = 'completed' THEN hr.id END) as completed_health_forms
    FROM customers c
    LEFT JOIN appointments a ON c.id = a.customer_id
    LEFT JOIN health_form_responses hr ON c.id = hr.customer_id
    WHERE c.id = ?
");
$stats_stmt->execute([$customer_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent appointments
$appointments_stmt = $db->prepare("
    SELECT a.*, s.title_ar as service_name, tm.name_ar as specialist_name
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.id
    LEFT JOIN team_members tm ON a.specialist_id = tm.id
    WHERE a.customer_id = ?
    ORDER BY a.start_at DESC
    LIMIT 5
");
$appointments_stmt->execute([$customer_id]);
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer notes
$notes_stmt = $db->prepare("
    SELECT cn.*, u.username as created_by_name
    FROM customer_notes cn
    LEFT JOIN users u ON cn.created_by = u.id
    WHERE cn.customer_id = ?
    ORDER BY cn.created_at DESC
    LIMIT 10
");
$notes_stmt->execute([$customer_id]);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get health forms
$health_forms_stmt = $db->prepare("
    SELECT hr.*, hft.title as template_title
    FROM health_form_responses hr
    JOIN health_form_templates hft ON hr.template_id = hft.id
    WHERE hr.customer_id = ?
    ORDER BY hr.created_at DESC
    LIMIT 5
");
$health_forms_stmt->execute([$customer_id]);
$health_forms = $health_forms_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'ملف العميل';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>


    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold text-mai-brown">MAI</span>
                        <span class="text-sm text-gray-600 mr-2">ملف العميل</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="clients.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-arrow-right ml-2"></i>العودة للعملاء
                    </a>
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Customer Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-20 h-20 bg-mai-brown rounded-full flex items-center justify-center text-white font-bold text-2xl ml-6">
                        <?php echo strtoupper(substr($customer['name'], 0, 2)); ?>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></h1>
                        <div class="flex items-center space-x-4 space-x-reverse mt-2">
                            <span class="text-lg text-gray-600"><?php echo htmlspecialchars($customer['customer_code']); ?></span>
                            <?php
                            $status_classes = [
                                'active' => 'bg-green-100 text-green-800',
                                'inactive' => 'bg-yellow-100 text-yellow-800',
                                'blocked' => 'bg-red-100 text-red-800'
                            ];
                            $status_labels = [
                                'active' => 'نشط',
                                'inactive' => 'غير نشط',
                                'blocked' => 'محظور'
                            ];
                            ?>
                            <span class="px-3 py-1 text-sm font-medium rounded-full <?php echo $status_classes[$customer['status']]; ?>">
                                <?php echo $status_labels[$customer['status']]; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-3 space-x-reverse">
                    <button onclick="editCustomer()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-edit ml-2"></i>تعديل البيانات
                    </button>
                    <button onclick="newAppointment()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-calendar-plus ml-2"></i>حجز موعد
                    </button>
                    <button onclick="newHealthForm()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-file-medical ml-2"></i>نموذج صحي
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_appointments']; ?></div>
                        <div class="text-sm text-gray-600">إجمالي المواعيد</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_appointments']; ?></div>
                        <div class="text-sm text-gray-600">مواعيد مكتملة</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-file-medical text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $stats['health_forms_count']; ?></div>
                        <div class="text-sm text-gray-600">النماذج الصحية</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-mai-cream text-mai-brown">
                        <i class="fas fa-star text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo number_format($customer['loyalty_points']); ?></div>
                        <div class="text-sm text-gray-600">نقاط الولاء</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Personal Information -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">المعلومات الشخصية</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">الاسم الكامل</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">رقم العميل</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($customer['customer_code']); ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">البريد الإلكتروني</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($customer['email'] ?: 'غير محدد'); ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">رقم الهاتف</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($customer['phone']); ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">تاريخ الميلاد</label>
                            <div class="text-gray-900"><?php echo $customer['date_of_birth'] ? date('Y-m-d', strtotime($customer['date_of_birth'])) : 'غير محدد'; ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">الجنس</label>
                            <div class="text-gray-900"><?php echo $customer['gender'] === 'female' ? 'أنثى' : 'ذكر'; ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">المدينة</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($customer['city'] ?: 'غير محدد'); ?></div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">نوع البشرة</label>
                            <div class="text-gray-900">
                                <?php 
                                $skin_types = [
                                    'normal' => 'عادية',
                                    'dry' => 'جافة', 
                                    'oily' => 'دهنية',
                                    'combination' => 'مختلطة',
                                    'sensitive' => 'حساسة'
                                ];
                                echo $skin_types[$customer['skin_type']] ?? 'غير محدد';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">المواعيد الأخيرة</h3>
                        <a href="bookings.php?customer_id=<?php echo $customer_id; ?>" class="text-mai-brown hover:text-mai-dark-brown text-sm">
                            عرض الكل <i class="fas fa-arrow-left mr-1"></i>
                        </a>
                    </div>
                    
                    <?php if (!empty($appointments)): ?>
                    <div class="space-y-4">
                        <?php foreach ($appointments as $appointment): ?>
                        <div class="border-r-4 border-mai-brown pr-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($appointment['service_name'] ?? 'خدمة غير محددة'); ?></div>
                                    <div class="text-sm text-gray-600">مع: <?php echo htmlspecialchars($appointment['specialist_name'] ?? 'غير محدد'); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($appointment['start_at'])); ?></div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php 
                                    switch($appointment['status']) {
                                        case 'confirmed': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-yellow-100 text-yellow-800';
                                    }
                                    ?>">
                                    <?php 
                                    $statuses = [
                                        'pending' => 'في الانتظار',
                                        'confirmed' => 'مؤكد',
                                        'completed' => 'مكتمل',
                                        'cancelled' => 'ملغي',
                                        'no_show' => 'لم يحضر'
                                    ];
                                    echo $statuses[$appointment['status']] ?? $appointment['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-calendar-times text-3xl mb-2"></i>
                        <div>لا توجد مواعيد سابقة</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Health Forms -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">النماذج الصحية</h3>
                        <a href="customer_health_forms.php?customer_id=<?php echo $customer_id; ?>" class="text-mai-brown hover:text-mai-dark-brown text-sm">
                            عرض الكل <i class="fas fa-arrow-left mr-1"></i>
                        </a>
                    </div>
                    
                    <?php if (!empty($health_forms)): ?>
                    <div class="space-y-4">
                        <?php foreach ($health_forms as $form): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($form['template_title']); ?></div>
                                    <div class="text-sm text-gray-600">تاريخ الإرسال: <?php echo date('Y-m-d', strtotime($form['created_at'])); ?></div>
                                    <?php if ($form['completed_at']): ?>
                                    <div class="text-sm text-gray-600">تاريخ الإكمال: <?php echo date('Y-m-d', strtotime($form['completed_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php 
                                    switch($form['status']) {
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'draft': echo 'bg-yellow-100 text-yellow-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php 
                                    $form_statuses = [
                                        'draft' => 'في الانتظار',
                                        'completed' => 'مكتمل'
                                    ];
                                    echo $form_statuses[$form['status']] ?? $form['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-file-medical text-3xl mb-2"></i>
                        <div>لا توجد نماذج صحية</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">إجراءات سريعة</h3>
                    <div class="space-y-3">
                        <button onclick="editCustomer()" class="w-full bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown">
                            <i class="fas fa-edit ml-2"></i>تعديل البيانات
                        </button>
                        <button onclick="newAppointment()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-calendar-plus ml-2"></i>حجز موعد جديد
                        </button>
                        <button onclick="newHealthForm()" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-file-medical ml-2"></i>إرسال نموذج صحي
                        </button>
                        <button onclick="addNote()" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                            <i class="fas fa-sticky-note ml-2"></i>إضافة ملاحظة
                        </button>
                    </div>
                </div>

                <!-- Customer Notes -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">الملاحظات</h3>
                    
                    <?php if (!empty($notes)): ?>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($notes as $note): ?>
                        <div class="border-b border-gray-200 pb-3 last:border-b-0">
                            <?php if ($note['title']): ?>
                            <div class="font-medium text-sm"><?php echo htmlspecialchars($note['title']); ?></div>
                            <?php endif; ?>
                            <div class="text-sm text-gray-700"><?php echo htmlspecialchars($note['content']); ?></div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>
                                <?php if ($note['created_by_name']): ?>
                                - <?php echo htmlspecialchars($note['created_by_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-sticky-note text-2xl mb-2"></i>
                        <div class="text-sm">لا توجد ملاحظات</div>
                    </div>
                    <?php endif; ?>
                    
                    <button onclick="addNote()" class="w-full mt-4 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                        <i class="fas fa-plus ml-2"></i>إضافة ملاحظة
                    </button>
                </div>

                <!-- Account Summary -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">ملخص الحساب</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">تاريخ التسجيل:</span>
                            <span class="font-medium"><?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">آخر زيارة:</span>
                            <span class="font-medium">
                                <?php echo $stats['last_appointment_date'] ? date('Y-m-d', strtotime($stats['last_appointment_date'])) : 'لا توجد'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">إجمالي الإنفاق:</span>
                            <span class="font-medium"><?php echo number_format($customer['total_spent'], 2); ?> ريال</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">نقاط الولاء:</span>
                            <span class="font-medium text-mai-brown"><?php echo number_format($customer['loyalty_points']); ?> نقطة</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editCustomer() {
            window.location.href = `customer_edit.php?id=<?php echo $customer_id; ?>`;
        }

        function newAppointment() {
            window.location.href = `bookings.php?action=new&customer_id=<?php echo $customer_id; ?>`;
        }

        function newHealthForm() {
            window.location.href = `customer_health_forms.php?customer_id=<?php echo $customer_id; ?>`;
        }

        function addNote() {
            const note = prompt('أدخل الملاحظة:');
            if (note && note.trim()) {
                // Add note functionality
                alert('سيتم إضافة وظيفة الملاحظات قريباً');
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>