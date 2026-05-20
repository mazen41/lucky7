<?php

/**
 * Health Form Builder - Dynamic Form Designer
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

// Get template details
if ($template_id) {
    $template_stmt = $db->prepare("SELECT * FROM health_form_templates WHERE id = ?");
    $template_stmt->execute([$template_id]);
    $template = $template_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        header('Location: health_forms.php');
        exit;
    }
    
    // Get existing questions
    $questions_stmt = $db->prepare("SELECT * FROM health_form_questions WHERE template_id = ? ORDER BY sort_order ASC");
    $questions_stmt->execute([$template_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    header('Location: health_forms.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_questions':
                // Delete existing questions
                $db->prepare("DELETE FROM health_form_questions WHERE template_id = ?")->execute([$template_id]);
                
                // Insert new questions
                $questions_data = json_decode($_POST['questions_data'], true);
                $insert_stmt = $db->prepare("INSERT INTO health_form_questions (template_id, type, label, placeholder, options_json, required, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($questions_data as $index => $question) {
                    $options_json = null;
                    if ($question['type'] === 'select' && !empty($question['options'])) {
                        $options_json = json_encode(array_filter(explode("\n", $question['options'])));
                    }
                    
                    $insert_stmt->execute([
                        $template_id,
                        $question['type'],
                        $question['label'],
                        $question['placeholder'] ?? '',
                        $options_json,
                        isset($question['required']) ? 1 : 0,
                        $index + 1
                    ]);
                }
                
                $success = "تم حفظ الأسئلة بنجاح";
                
                // Refresh questions
                $questions_stmt = $db->prepare("SELECT * FROM health_form_questions WHERE template_id = ? ORDER BY sort_order ASC");
                $questions_stmt->execute([$template_id]);
                $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
    }
}


// Layout configuration
$page_title = 'منشئ الاستمارات الصحية';
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
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="health_forms.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-arrow-right ml-2"></i>العودة للنماذج
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($template['title']); ?></h1>
                </div>
                <div class="flex items-center space-x-3 space-x-reverse">
                    <button @click="previewForm()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-eye ml-2"></i>معاينة
                    </button>
                    <button @click="saveForm()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-save ml-2"></i>حفظ النموذج
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mx-4 mt-4 rounded-lg">
        <i class="fas fa-check-circle ml-2"></i>
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Question Types Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-6">
                    <h3 class="text-lg font-semibold mb-4">أنواع الأسئلة</h3>
                    
                    <div class="space-y-3">
                        <button @click="addQuestion('text')" class="w-full text-right p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-font text-blue-600 ml-2"></i>
                            <span class="font-medium">نص قصير</span>
                            <div class="text-sm text-gray-500">حقل نص من سطر واحد</div>
                        </button>
                        
                        <button @click="addQuestion('textarea')" class="w-full text-right p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-align-left text-green-600 ml-2"></i>
                            <span class="font-medium">نص طويل</span>
                            <div class="text-sm text-gray-500">حقل نص متعدد الأسطر</div>
                        </button>
                        
                        <button @click="addQuestion('select')" class="w-full text-right p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-list text-purple-600 ml-2"></i>
                            <span class="font-medium">اختيار من قائمة</span>
                            <div class="text-sm text-gray-500">قائمة منسدلة</div>
                        </button>
                        
                        <button @click="addQuestion('yesno')" class="w-full text-right p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-check-circle text-orange-600 ml-2"></i>
                            <span class="font-medium">نعم/لا</span>
                            <div class="text-sm text-gray-500">سؤال بإجابة نعم أو لا</div>
                        </button>
                        
                        <button @click="addQuestion('date')" class="w-full text-right p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-calendar text-red-600 ml-2"></i>
                            <span class="font-medium">تاريخ</span>
                            <div class="text-sm text-gray-500">حقل اختيار التاريخ</div>
                        </button>
                        
                        <button @click="addQuestion('signature')" class="w-full text-right p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-signature text-indigo-600 ml-2"></i>
                            <span class="font-medium">التوقيع</span>
                            <div class="text-sm text-gray-500">لوحة التوقيع</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Builder Area -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold">أسئلة النموذج</h3>
                        <p class="text-sm text-gray-500 mt-1">اسحب وأفلت لإعادة ترتيب الأسئلة</p>
                    </div>
                    
                    <div class="p-6">
                        <div id="questions-container" class="space-y-4">
                            <template x-for="(question, index) in questions" :key="question.id">
                                <div class="question-item border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center space-x-2 space-x-reverse">
                                            <i class="fas fa-grip-vertical text-gray-400 cursor-move"></i>
                                            <span class="text-sm font-medium text-gray-600" x-text="`السؤال ${index + 1}`"></span>
                                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded" x-text="getQuestionTypeLabel(question.type)"></span>
                                        </div>
                                        <button @click="removeQuestion(index)" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">نص السؤال *</label>
                                            <input type="text" x-model="question.label" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown" placeholder="أدخل نص السؤال">
                                        </div>
                                        
                                        <div x-show="question.type !== 'yesno' && question.type !== 'signature'">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">النص التوضيحي</label>
                                            <input type="text" x-model="question.placeholder" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown" placeholder="نص توضيحي اختياري">
                                        </div>
                                        
                                        <div>
                                            <label class="flex items-center">
                                                <input type="checkbox" x-model="question.required" class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                                <span class="mr-2 text-sm text-gray-700">سؤال إجباري</span>
                                            </label>
                                        </div>
                                        
                                        <div x-show="question.type === 'select'" class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">خيارات الاختيار (كل خيار في سطر منفصل)</label>
                                            <textarea x-model="question.options" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown" placeholder="الخيار الأول&#10;الخيار الثاني&#10;الخيار الثالث"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            
                            <div x-show="questions.length === 0" class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
                                <i class="fas fa-plus-circle text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-500 mb-2">لا توجد أسئلة</h3>
                                <p class="text-gray-400">ابدأ بإضافة أسئلة من الشريط الجانبي</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for saving -->
    <form id="saveForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="save_questions">
        <input type="hidden" name="questions_data" id="questionsData">
    </form>

    <script>
        function formBuilder() {
            return {
                questions: <?php echo json_encode(array_map(function($q) {
                    return [
                        'id' => $q['id'],
                        'type' => $q['type'],
                        'label' => $q['label'],
                        'placeholder' => $q['placeholder'],
                        'options' => $q['options_json'] ? implode("\n", json_decode($q['options_json'], true)) : '',
                        'required' => (bool)$q['required']
                    ];
                }, $questions)); ?>,
                
                nextId: <?php echo count($questions) + 1; ?>,
                
                addQuestion(type) {
                    const question = {
                        id: this.nextId++,
                        type: type,
                        label: '',
                        placeholder: '',
                        options: '',
                        required: false
                    };
                    
                    // Set default labels
                    switch(type) {
                        case 'text':
                            question.label = 'سؤال نصي جديد';
                            question.placeholder = 'أدخل إجابتك هنا';
                            break;
                        case 'textarea':
                            question.label = 'سؤال نص طويل جديد';
                            question.placeholder = 'أدخل إجابتك التفصيلية هنا';
                            break;
                        case 'select':
                            question.label = 'سؤال اختيار جديد';
                            question.options = 'الخيار الأول\nالخيار الثاني\nالخيار الثالث';
                            break;
                        case 'yesno':
                            question.label = 'سؤال نعم/لا جديد';
                            break;
                        case 'date':
                            question.label = 'سؤال تاريخ جديد';
                            break;
                        case 'signature':
                            question.label = 'التوقيع';
                            question.required = true;
                            break;
                    }
                    
                    this.questions.push(question);
                },
                
                removeQuestion(index) {
                    if (confirm('هل أنت متأكد من حذف هذا السؤال؟')) {
                        this.questions.splice(index, 1);
                    }
                },
                
                getQuestionTypeLabel(type) {
                    const labels = {
                        'text': 'نص قصير',
                        'textarea': 'نص طويل',
                        'select': 'اختيار',
                        'yesno': 'نعم/لا',
                        'date': 'تاريخ',
                        'signature': 'توقيع'
                    };
                    return labels[type] || type;
                },
                
                saveForm() {
                    if (this.questions.length === 0) {
                        alert('يجب إضافة سؤال واحد على الأقل');
                        return;
                    }
                    
                    // Validate questions
                    for (let i = 0; i < this.questions.length; i++) {
                        if (!this.questions[i].label.trim()) {
                            alert(`يجب إدخال نص للسؤال رقم ${i + 1}`);
                            return;
                        }
                    }
                    
                    document.getElementById('questionsData').value = JSON.stringify(this.questions);
                    document.getElementById('saveForm').submit();
                },
                
                previewForm() {
                    window.open(`health_form_preview.php?template_id=<?php echo $template_id; ?>`, '_blank');
                }
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>