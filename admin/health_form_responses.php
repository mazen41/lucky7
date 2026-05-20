<?php

/**
 * Health Form Responses Management
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_response':
                $template_id = intval($_POST['template_id']);
                $customer_name = trim($_POST['customer_name']);
                $customer_email = trim($_POST['customer_email']);
                $customer_phone = trim($_POST['customer_phone']);
                
                // Generate access token
                $access_token = bin2hex(random_bytes(32));
                
                // Set expiry (72 hours from now)
                $expires_at = date('Y-m-d H:i:s', strtotime('+72 hours'));
                
                $stmt = $db->prepare("INSERT INTO health_form_responses (template_id, customer_name, customer_email, customer_phone, access_token, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$template_id, $customer_name, $customer_email, $customer_phone, $access_token, $expires_at, $admin['id']]);
                
                $response_id = $db->lastInsertId();
                $form_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . "/health_form.php?token=" . $access_token . "&template_id=" . $template_id;
                
                $success = "تم إنشاء النموذج بنجاح. الرابط: " . $form_url;
                break;
                
            case 'resend_link':
                $response_id = intval($_POST['response_id']);
                
                // Get response details
                $stmt = $db->prepare("SELECT * FROM health_form_responses WHERE id = ?");
                $stmt->execute([$response_id]);
                $response = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($response) {
                    $form_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . "/health_form.php?token=" . $response['access_token'] . "&template_id=" . $response['template_id'];
                    $success = "رابط النموذج: " . $form_url;
                }
                break;
        }
    }
}

// Get responses with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$template_filter = $_GET['template'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "hr.status = ?";
    $params[] = $status_filter;
}

if ($template_filter) {
    $where_conditions[] = "hr.template_id = ?";
    $params[] = $template_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) FROM health_form_responses hr $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_responses = $count_stmt->fetchColumn();

// Get responses
$responses_query = "
    SELECT hr.*, hft.title as template_title,
           COUNT(hfa.id) as answer_count
    FROM health_form_responses hr
    JOIN health_form_templates hft ON hr.template_id = hft.id
    LEFT JOIN health_form_answers hfa ON hr.id = hfa.response_id
    $where_clause
    GROUP BY hr.id
    ORDER BY hr.created_at DESC
    LIMIT $limit OFFSET $offset
";

$responses_stmt = $db->prepare($responses_query);
$responses_stmt->execute($params);
$responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get templates for filter
$templates_stmt = $db->prepare("SELECT id, title FROM health_form_templates WHERE is_active = 1 ORDER BY title");
$templates_stmt->execute();
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total_responses / $limit);


// Layout configuration
$page_title = 'ردود الاستمارات الصحية';
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
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-mai-dark-brown">ردود النماذج الصحية</h1>
                        <p class="text-gray-600 mt-2">إدارة ومتابعة ردود العملاء على النماذج الصحية</p>
                    </div>
                    <div class="flex space-x-3 space-x-reverse">
                        <a href="health_forms.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                            <i class="fas fa-arrow-right ml-2"></i>العودة للنماذج
                        </a>
                        <button @click="showCreateResponseModal = true" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                            <i class="fas fa-plus ml-2"></i>إنشاء نموذج جديد
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

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-2">حالة النموذج</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            <option value="">جميع الحالات</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>مسودة</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>منتهي الصلاحية</option>
                        </select>
                    </div>
                    
                    <div class="flex-1 min-w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-2">قالب النموذج</label>
                        <select name="template" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            <option value="">جميع القوالب</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>" <?php echo $template_filter == $template['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($template['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search ml-2"></i>بحث
                        </button>
                    </div>
                </form>
            </div>

            <!-- Responses Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">العميل</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">القالب</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجابات</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاريخ الإنشاء</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($responses as $response): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($response['customer_name']); ?></div>
                                        <?php if ($response['customer_email']): ?>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($response['customer_email']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($response['customer_phone']): ?>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($response['customer_phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($response['template_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_classes = [
                                        'draft' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'expired' => 'bg-red-100 text-red-800'
                                    ];
                                    $status_labels = [
                                        'draft' => 'مسودة',
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $response['answer_count']; ?> إجابة
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('Y-m-d H:i', strtotime($response['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2 space-x-reverse">
                                        <?php if ($response['status'] === 'completed'): ?>
                                        <button onclick="viewResponse(<?php echo $response['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($response['pdf_path']): ?>
                                        <a href="<?php echo htmlspecialchars($response['pdf_path']); ?>" target="_blank" class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="resend_link">
                                            <input type="hidden" name="response_id" value="<?php echo $response['id']; ?>">
                                            <button type="submit" class="text-mai-brown hover:text-mai-dark-brown">
                                                <i class="fas fa-link"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <button onclick="deleteResponse(<?php echo $response['id']; ?>)" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($responses)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <i class="fas fa-file-medical text-4xl text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-500 mb-2">لا توجد ردود</h3>
                                    <p class="text-gray-400">ابدأ بإنشاء نموذج جديد للعملاء</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            عرض <?php echo ($offset + 1); ?> إلى <?php echo min($offset + $limit, $total_responses); ?> من <?php echo $total_responses; ?> نتيجة
                        </div>
                        <div class="flex space-x-1 space-x-reverse">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&template=<?php echo $template_filter; ?>" 
                               class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-mai-brown text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border rounded-md">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Response Modal -->
    <div x-show="showCreateResponseModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">إنشاء نموذج جديد</h3>
                <button @click="showCreateResponseModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_response">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">القالب *</label>
                    <select name="template_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                        <option value="">اختر القالب</option>
                        <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم العميل *</label>
                    <input type="text" name="customer_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                    <input type="email" name="customer_email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                    <input type="tel" name="customer_phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" @click="showCreateResponseModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-plus ml-2"></i>إنشاء النموذج
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function responsesApp() {
            return {
                showCreateResponseModal: false
            }
        }

        function viewResponse(responseId) {
            window.open(`health_form_view.php?response_id=${responseId}`, '_blank');
        }

        function deleteResponse(responseId) {
            if (confirm('هل أنت متأكد من حذف هذا النموذج؟')) {
                // Add delete functionality
                alert('سيتم إضافة وظيفة الحذف قريباً');
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>