<?php

/**
 * Customer Health Forms Integration - CRM Health Forms Management
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_health_form':
                $template_id = intval($_POST['template_id']);
                
                // Generate access token
                $access_token = bin2hex(random_bytes(32));
                
                // Set expiry (72 hours from now)
                $expires_at = date('Y-m-d H:i:s', strtotime('+72 hours'));
                
                $stmt = $db->prepare("INSERT INTO health_form_responses (template_id, customer_id, customer_name, customer_email, customer_phone, access_token, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$template_id, $customer_id, $customer['name'], $customer['email'], $customer['phone'], $access_token, $expires_at, $admin['id']]);
                
                $response_id = $db->lastInsertId();
                $form_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . "/health_form.php?token=" . $access_token . "&template_id=" . $template_id;
                
                $success = "تم إنشاء النموذج الصحي بنجاح. الرابط: " . $form_url;
                break;
        }
    }
}

// Get customer's health form responses
$responses_stmt = $db->prepare("
    SELECT hr.*, hft.title as template_title,
           COUNT(hfa.id) as answer_count
    FROM health_form_responses hr
    JOIN health_form_templates hft ON hr.template_id = hft.id
    LEFT JOIN health_form_answers hfa ON hr.id = hfa.response_id
    WHERE hr.customer_id = ?
    GROUP BY hr.id
    ORDER BY hr.created_at DESC
");
$responses_stmt->execute([$customer_id]);
$responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active templates
$templates_stmt = $db->prepare("SELECT id, title, description FROM health_form_templates WHERE is_active = 1 ORDER BY title");
$templates_stmt->execute();
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer's recent appointments
$appointments_stmt = $db->prepare("
    SELECT a.*, s.title_ar as service_name, tm.name_ar as specialist_name
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.id
    LEFT JOIN team_members tm ON a.specialist_id = tm.id
    WHERE a.customer_id = ? OR a.customer_email = ?
    ORDER BY a.start_at DESC
    LIMIT 5
");
$appointments_stmt->execute([$customer_id, $customer['email']]);
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'استمارات صحة العملاء';
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
                        <span class="text-sm text-gray-600 mr-2">لوحة التحكم</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-sign-out-alt ml-1"></i>تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <?php  ?>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="flex items-center space-x-3 space-x-reverse mb-2">
                            <a href="clients.php" class="text-mai-brown hover:text-mai-dark-brown">
                                <i class="fas fa-arrow-right ml-2"></i>العودة للعملاء
                            </a>
                            <span class="text-gray-400">|</span>
                            <h1 class="text-3xl font-bold text-mai-dark-brown">النماذج الصحية</h1>
                        </div>
                        <div class="flex items-center space-x-4 space-x-reverse">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-mai-brown rounded-full flex items-center justify-center text-white font-bold text-lg ml-3">
                                    <?php echo strtoupper(substr($customer['name'], 0, 2)); ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-lg"><?php echo htmlspecialchars($customer['name']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($customer['email']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-3 space-x-reverse">
                        <button @click="showCreateFormModal = true" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                            <i class="fas fa-plus ml-2"></i>إنشاء نموذج صحي
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Health Forms List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold">النماذج الصحية</h3>
                            <p class="text-sm text-gray-500 mt-1">جميع النماذج الصحية المرسلة للعميل</p>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($responses as $response): ?>
                            <div class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 space-x-reverse mb-2">
                                            <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($response['template_title']); ?></h4>
                                            <?php
                                            $status_classes = [
                                                'draft' => 'bg-yellow-100 text-yellow-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                'expired' => 'bg-red-100 text-red-800'
                                            ];
                                            $status_labels = [
                                                'draft' => 'في الانتظار',
                                                'completed' => 'مكتمل',
                                                'expired' => 'منتهي الصلاحية'
                                            ];
                                            
                                            $current_status = $response['status'];
                                            if ($current_status === 'draft' && strtotime($response['expires_at']) < time()) {
                                                $current_status = 'expired';
                                            }
                                            ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $status_classes[$current_status]; ?>">
                                                <?php echo $status_labels[$current_status]; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="text-sm text-gray-600 space-y-1">
                                            <div>تاريخ الإرسال: <?php echo date('Y-m-d H:i', strtotime($response['created_at'])); ?></div>
                                            <?php if ($response['completed_at']): ?>
                                            <div>تاريخ الإكمال: <?php echo date('Y-m-d H:i', strtotime($response['completed_at'])); ?></div>
                                            <?php endif; ?>
                                            <div>عدد الإجابات: <?php echo $response['answer_count']; ?></div>
                                            <?php if ($current_status === 'draft'): ?>
                                            <div>ينتهي في: <?php echo date('Y-m-d H:i', strtotime($response['expires_at'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 space-x-reverse">
                                        <?php if ($response['status'] === 'completed'): ?>
                                        <button onclick="viewResponse(<?php echo $response['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-2">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($response['pdf_path']): ?>
                                        <a href="<?php echo htmlspecialchars($response['pdf_path']); ?>" target="_blank" class="text-green-600 hover:text-green-800 p-2">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <button onclick="copyFormLink('<?php echo $response['access_token']; ?>', <?php echo $response['template_id']; ?>)" class="text-mai-brown hover:text-mai-dark-brown p-2" title="نسخ الرابط">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button onclick="resendForm(<?php echo $response['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-2" title="إعادة إرسال">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($responses)): ?>
                            <div class="p-12 text-center">
                                <i class="fas fa-file-medical text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-500 mb-2">لا توجد نماذج صحية</h3>
                                <p class="text-gray-400 mb-4">لم يتم إرسال أي نماذج صحية لهذا العميل بعد</p>
                                <button @click="showCreateFormModal = true" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                                    <i class="fas fa-plus ml-2"></i>إنشاء نموذج صحي
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="space-y-6">
                    <!-- Customer Summary -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4">معلومات العميل</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">الاسم:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($customer['name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">البريد:</span>
                                <span class="font-medium text-sm"><?php echo htmlspecialchars($customer['email']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">الهاتف:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($customer['phone']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">تاريخ التسجيل:</span>
                                <span class="font-medium"><?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Appointments -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4">المواعيد الأخيرة</h3>
                        <?php if (!empty($appointments)): ?>
                        <div class="space-y-3">
                            <?php foreach ($appointments as $appointment): ?>
                            <div class="border-l-4 border-mai-brown pl-3">
                                <div class="font-medium text-sm"><?php echo htmlspecialchars($appointment['service_name'] ?? 'خدمة غير محددة'); ?></div>
                                <div class="text-xs text-gray-600"><?php echo date('Y-m-d', strtotime($appointment['start_at'])); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($appointment['specialist_name'] ?? 'غير محدد'); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-gray-500 text-sm">لا توجد مواعيد سابقة</p>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4">إجراءات سريعة</h3>
                        <div class="space-y-2">
                            <a href="clients.php?action=edit&id=<?php echo $customer['id']; ?>" class="block w-full text-center bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200">
                                <i class="fas fa-edit ml-2"></i>تعديل بيانات العميل
                            </a>
                            <a href="bookings.php?customer_id=<?php echo $customer['id']; ?>" class="block w-full text-center bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200">
                                <i class="fas fa-calendar ml-2"></i>عرض المواعيد
                            </a>
                            <button @click="showCreateFormModal = true" class="w-full bg-mai-brown text-white px-4 py-2 rounded hover:bg-mai-dark-brown">
                                <i class="fas fa-plus ml-2"></i>نموذج صحي جديد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Health Form Modal -->
    <div x-show="showCreateFormModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">إنشاء نموذج صحي</h3>
                <button @click="showCreateFormModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_health_form">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اختر القالب *</label>
                    <select name="template_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        <option value="">اختر قالب النموذج</option>
                        <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <div class="bg-mai-cream p-3 rounded-lg">
                        <div class="text-sm font-medium text-mai-dark-brown mb-1">سيتم إرسال النموذج إلى:</div>
                        <div class="text-sm text-gray-700">
                            <div><?php echo htmlspecialchars($customer['name']); ?></div>
                            <div><?php echo htmlspecialchars($customer['email']); ?></div>
                            <div><?php echo htmlspecialchars($customer['phone']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" @click="showCreateFormModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-paper-plane ml-2"></i>إرسال النموذج
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function customerHealthFormsApp() {
            return {
                showCreateFormModal: false
            }
        }

        function viewResponse(responseId) {
            window.open(`health_form_view.php?response_id=${responseId}`, '_blank');
        }

        function copyFormLink(token, templateId) {
            const url = `${window.location.protocol}//${window.location.host}${window.location.pathname.replace('/admin/customer_health_forms.php', '')}/health_form.php?token=${token}&template_id=${templateId}`;
            navigator.clipboard.writeText(url).then(() => {
                alert('تم نسخ الرابط');
            });
        }

        function resendForm(responseId) {
            if (confirm('هل تريد إعادة إرسال رابط النموذج؟')) {
                // Add resend functionality
                alert('سيتم إضافة وظيفة إعادة الإرسال قريباً');
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>