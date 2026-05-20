<?php

/**
 * Health Form Preview - Display form as customers would see it
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();

$database = new Database();
$db = $database->getConnection();

$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

if (!$template_id) {
    header('Location: health_forms.php');
    exit;
}

// Get template details
$template_stmt = $db->prepare("SELECT * FROM health_form_templates WHERE id = ?");
$template_stmt->execute([$template_id]);
$template = $template_stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    header('Location: health_forms.php');
    exit;
}

// Get questions
$questions_stmt = $db->prepare("SELECT * FROM health_form_questions WHERE template_id = ? ORDER BY sort_order ASC");
$questions_stmt->execute([$template_id]);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'معاينة الاستمارة الصحية';
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
    <div class="bg-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-mai-dark-brown">MAI Salon</h1>
                    <p class="text-gray-600">نموذج التاريخ الصحي</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">معاينة النموذج</div>
                    <button onclick="window.close()" class="text-mai-brown hover:text-mai-dark-brown text-sm">
                        <i class="fas fa-times ml-1"></i>إغلاق المعاينة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Form Header -->
            <div class="bg-mai-brown text-white p-6">
                <h2 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($template['title']); ?></h2>
                <?php if ($template['description']): ?>
                <p class="text-mai-cream opacity-90"><?php echo htmlspecialchars($template['description']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Form Body -->
            <div class="p-6">
                <?php if (empty($questions)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">لا توجد أسئلة</h3>
                    <p class="text-gray-500">يجب إضافة أسئلة للنموذج أولاً</p>
                    <a href="health_form_builder.php?template_id=<?php echo $template_id; ?>" class="inline-block mt-4 bg-mai-brown text-white px-6 py-2 rounded-lg hover:bg-mai-dark-brown">
                        <i class="fas fa-edit ml-2"></i>إضافة أسئلة
                    </a>
                </div>
                <?php else: ?>
                
                <form class="space-y-6">
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="question-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo htmlspecialchars($question['label']); ?>
                            <?php if ($question['required']): ?>
                            <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>

                        <?php switch ($question['type']): 
                            case 'text': ?>
                            <input type="text" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                   placeholder="<?php echo htmlspecialchars($question['placeholder']); ?>"
                                   <?php echo $question['required'] ? 'required' : ''; ?>>
                            <?php break;
                            
                            case 'textarea': ?>
                            <textarea rows="4" 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                      placeholder="<?php echo htmlspecialchars($question['placeholder']); ?>"
                                      <?php echo $question['required'] ? 'required' : ''; ?>></textarea>
                            <?php break;
                            
                            case 'select': 
                                $options = $question['options_json'] ? json_decode($question['options_json'], true) : [];
                            ?>
                            <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                    <?php echo $question['required'] ? 'required' : ''; ?>>
                                <option value="">اختر إجابة...</option>
                                <?php foreach ($options as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php break;
                            
                            case 'yesno': ?>
                            <div class="flex space-x-6 space-x-reverse">
                                <label class="flex items-center">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="yes" 
                                           class="text-mai-brown focus:ring-mai-brown" <?php echo $question['required'] ? 'required' : ''; ?>>
                                    <span class="mr-2">نعم</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="no" 
                                           class="text-mai-brown focus:ring-mai-brown" <?php echo $question['required'] ? 'required' : ''; ?>>
                                    <span class="mr-2">لا</span>
                                </label>
                            </div>
                            <?php break;
                            
                            case 'date': ?>
                            <input type="date" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                   <?php echo $question['required'] ? 'required' : ''; ?>>
                            <?php break;
                            
                            case 'signature': ?>
                            <div class="signature-container">
                                <canvas class="signature-canvas w-full h-32 rounded-lg" 
                                        width="600" height="150"
                                        x-ref="signatureCanvas<?php echo $question['id']; ?>"
                                        @mousedown="startSigning($event, <?php echo $question['id']; ?>)"
                                        @mousemove="sign($event, <?php echo $question['id']; ?>)"
                                        @mouseup="stopSigning(<?php echo $question['id']; ?>)"
                                        @touchstart="startSigning($event, <?php echo $question['id']; ?>)"
                                        @touchmove="sign($event, <?php echo $question['id']; ?>)"
                                        @touchend="stopSigning(<?php echo $question['id']; ?>)">
                                </canvas>
                                <div class="flex justify-between items-center mt-2">
                                    <button type="button" @click="clearSignature(<?php echo $question['id']; ?>)" 
                                            class="text-sm text-gray-600 hover:text-gray-800">
                                        <i class="fas fa-eraser ml-1"></i>مسح التوقيع
                                    </button>
                                    <div class="text-xs text-gray-500">وقع في المربع أعلاه</div>
                                </div>
                            </div>
                            <?php break;
                        endswitch; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Submit Button -->
                    <div class="pt-6 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-lock ml-1"></i>
                                جميع المعلومات محمية وسرية
                            </div>
                            <button type="button" onclick="alert('هذه معاينة فقط - لا يمكن إرسال النموذج')" 
                                    class="bg-mai-brown text-white px-8 py-3 rounded-lg hover:bg-mai-dark-brown font-medium">
                                <i class="fas fa-paper-plane ml-2"></i>إرسال النموذج
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preview Notice -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-blue-600 ml-2"></i>
                <div>
                    <div class="font-medium text-blue-900">معاينة النموذج</div>
                    <div class="text-sm text-blue-700">هذه معاينة للنموذج كما سيراه العملاء. لا يمكن إرسال البيانات من هذه الصفحة.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function healthFormPreview() {
            return {
                signatures: {},
                
                startSigning(event, questionId) {
                    event.preventDefault();
                    const canvas = this.$refs[`signatureCanvas${questionId}`];
                    const rect = canvas.getBoundingClientRect();
                    
                    this.signatures[questionId] = {
                        isDrawing: true,
                        ctx: canvas.getContext('2d'),
                        lastX: (event.clientX || event.touches[0].clientX) - rect.left,
                        lastY: (event.clientY || event.touches[0].clientY) - rect.top
                    };
                    
                    canvas.classList.add('signing');
                    this.signatures[questionId].ctx.strokeStyle = '#8B4513';
                    this.signatures[questionId].ctx.lineWidth = 2;
                    this.signatures[questionId].ctx.lineCap = 'round';
                },
                
                sign(event, questionId) {
                    if (!this.signatures[questionId] || !this.signatures[questionId].isDrawing) return;
                    
                    event.preventDefault();
                    const canvas = this.$refs[`signatureCanvas${questionId}`];
                    const rect = canvas.getBoundingClientRect();
                    const ctx = this.signatures[questionId].ctx;
                    
                    const currentX = (event.clientX || event.touches[0].clientX) - rect.left;
                    const currentY = (event.clientY || event.touches[0].clientY) - rect.top;
                    
                    ctx.beginPath();
                    ctx.moveTo(this.signatures[questionId].lastX, this.signatures[questionId].lastY);
                    ctx.lineTo(currentX, currentY);
                    ctx.stroke();
                    
                    this.signatures[questionId].lastX = currentX;
                    this.signatures[questionId].lastY = currentY;
                },
                
                stopSigning(questionId) {
                    if (this.signatures[questionId]) {
                        this.signatures[questionId].isDrawing = false;
                        const canvas = this.$refs[`signatureCanvas${questionId}`];
                        canvas.classList.remove('signing');
                    }
                },
                
                clearSignature(questionId) {
                    const canvas = this.$refs[`signatureCanvas${questionId}`];
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>