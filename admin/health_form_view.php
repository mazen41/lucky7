<?php

/**
 * Health Form Response Viewer - Admin view of completed forms
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

$response_id = isset($_GET['response_id']) ? intval($_GET['response_id']) : 0;

if (!$response_id) {
    header('Location: health_form_responses.php');
    exit;
}

// Get response data
$response_stmt = $db->prepare("
    SELECT hr.*, hft.title, hft.description, c.name as customer_name, c.email, c.phone
    FROM health_form_responses hr 
    JOIN health_form_templates hft ON hr.template_id = hft.id 
    LEFT JOIN customers c ON hr.customer_id = c.id
    WHERE hr.id = ?
");
$response_stmt->execute([$response_id]);
$response = $response_stmt->fetch(PDO::FETCH_ASSOC);

if (!$response) {
    header('Location: health_form_responses.php');
    exit;
}

// Get questions and answers
$qa_stmt = $db->prepare("
    SELECT q.*, a.answer_value
    FROM health_form_questions q
    LEFT JOIN health_form_answers a ON q.id = a.question_id AND a.response_id = ?
    WHERE q.template_id = ?
    ORDER BY q.sort_order ASC
");
$qa_stmt->execute([$response_id, $response['template_id']]);
$questions_answers = $qa_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'عرض الاستمارة الصحية';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>


    <!-- Header -->
    <div class="bg-white shadow-sm no-print">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="health_form_responses.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-arrow-right ml-2"></i>العودة للردود
                    </a>
                    <h1 class="text-xl font-bold text-gray-900">عرض النموذج الصحي</h1>
                </div>
                <div class="flex items-center space-x-3 space-x-reverse">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-print ml-2"></i>طباعة
                    </button>
                    <?php if ($response['pdf_path']): ?>
                    <a href="<?php echo htmlspecialchars($response['pdf_path']); ?>" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-file-pdf ml-2"></i>تحميل PDF
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Form Header -->
            <div class="bg-mai-brown text-white p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">MAI Salon</h2>
                        <h3 class="text-xl mb-1"><?php echo htmlspecialchars($response['title']); ?></h3>
                        <?php if ($response['description']): ?>
                        <p class="text-mai-cream opacity-90"><?php echo htmlspecialchars($response['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="text-sm opacity-75">رقم النموذج</div>
                        <div class="text-lg font-bold">#<?php echo str_pad($response['id'], 6, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="bg-mai-cream p-6 border-b">
                <h4 class="text-lg font-semibold text-mai-dark-brown mb-4">معلومات العميل</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm text-gray-600">اسم العميل</div>
                        <div class="font-medium"><?php echo htmlspecialchars($response['customer_name']); ?></div>
                    </div>
                    <?php if ($response['customer_email'] || $response['email']): ?>
                    <div>
                        <div class="text-sm text-gray-600">البريد الإلكتروني</div>
                        <div class="font-medium"><?php echo htmlspecialchars($response['customer_email'] ?: $response['email']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($response['customer_phone'] || $response['phone']): ?>
                    <div>
                        <div class="text-sm text-gray-600">رقم الهاتف</div>
                        <div class="font-medium"><?php echo htmlspecialchars($response['customer_phone'] ?: $response['phone']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 pt-4 border-t border-mai-gold">
                    <div>
                        <div class="text-sm text-gray-600">تاريخ الإنشاء</div>
                        <div class="font-medium"><?php echo date('Y-m-d H:i', strtotime($response['created_at'])); ?></div>
                    </div>
                    <?php if ($response['completed_at']): ?>
                    <div>
                        <div class="text-sm text-gray-600">تاريخ الإكمال</div>
                        <div class="font-medium"><?php echo date('Y-m-d H:i', strtotime($response['completed_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div class="text-sm text-gray-600">الحالة</div>
                        <div>
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
                            <span class="px-3 py-1 text-sm font-medium rounded-full <?php echo $status_classes[$current_status]; ?>">
                                <?php echo $status_labels[$current_status]; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions and Answers -->
            <div class="p-6">
                <h4 class="text-lg font-semibold text-mai-dark-brown mb-6">الأسئلة والإجابات</h4>
                
                <div class="space-y-6">
                    <?php foreach ($questions_answers as $index => $qa): ?>
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 space-x-reverse mb-2">
                                    <span class="bg-mai-brown text-white text-sm px-2 py-1 rounded"><?php echo $index + 1; ?></span>
                                    <span class="text-sm font-medium text-mai-dark-brown">
                                        <?php
                                        $type_labels = [
                                            'text' => 'نص قصير',
                                            'textarea' => 'نص طويل',
                                            'select' => 'اختيار',
                                            'yesno' => 'نعم/لا',
                                            'date' => 'تاريخ',
                                            'signature' => 'توقيع'
                                        ];
                                        echo $type_labels[$qa['type']] ?? $qa['type'];
                                        ?>
                                    </span>
                                    <?php if ($qa['required']): ?>
                                    <span class="text-red-500 text-sm">*</span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-3"><?php echo htmlspecialchars($qa['label']); ?></h5>
                            </div>
                        </div>
                        
                        <div class="bg-white p-4 rounded border">
                            <?php 
                            $answer = $qa['answer_value'] ?? '';
                            
                            if ($qa['type'] === 'signature'): ?>
                                <?php if ($answer): ?>
                                <div class="text-center">
                                    <img src="<?php echo htmlspecialchars($answer); ?>" alt="التوقيع" class="max-w-full h-auto border-2 border-dashed border-mai-brown rounded p-2">
                                </div>
                                <?php else: ?>
                                <div class="text-center text-gray-500 py-8 border-2 border-dashed border-gray-300 rounded">
                                    <i class="fas fa-signature text-2xl mb-2"></i>
                                    <div>لم يتم التوقيع</div>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($qa['type'] === 'yesno'): ?>
                                <div class="flex items-center space-x-4 space-x-reverse">
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 border-2 border-mai-brown rounded-full flex items-center justify-center ml-2">
                                            <?php if ($answer === 'yes'): ?>
                                            <div class="w-2 h-2 bg-mai-brown rounded-full"></div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="<?php echo $answer === 'yes' ? 'font-semibold text-mai-brown' : 'text-gray-600'; ?>">نعم</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 border-2 border-mai-brown rounded-full flex items-center justify-center ml-2">
                                            <?php if ($answer === 'no'): ?>
                                            <div class="w-2 h-2 bg-mai-brown rounded-full"></div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="<?php echo $answer === 'no' ? 'font-semibold text-mai-brown' : 'text-gray-600'; ?>">لا</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="<?php echo $answer ? 'text-gray-900' : 'text-gray-500 italic'; ?>">
                                    <?php echo $answer ? htmlspecialchars($answer) : 'لم يتم الإجابة على هذا السؤال'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 p-6 border-t">
                <div class="text-center text-sm text-gray-600">
                    <div class="flex items-center justify-center space-x-2 space-x-reverse mb-2">
                        <i class="fas fa-shield-alt text-mai-brown"></i>
                        <span>جميع المعلومات سرية ومحمية وفقاً لمعايير الخصوصية الطبية</span>
                    </div>
                    <div>MAI Salon - نموذج التاريخ الصحي</div>
                    <div class="text-xs text-gray-400 mt-2">تم إنشاء هذا التقرير في: <?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
            </div>
        </div>
    </div>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>