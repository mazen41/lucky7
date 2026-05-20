<?php
/**
 * Health Form Response - Customer-facing form interface
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get form access token and template
$token = $_GET['token'] ?? '';
$template_id = $_GET['template_id'] ?? 0;

if (!$token || !$template_id) {
    die('رابط غير صحيح');
}

// Verify token and get response record
$response_stmt = $db->prepare("
    SELECT hr.*, hft.title, hft.description 
    FROM health_form_responses hr 
    JOIN health_form_templates hft ON hr.template_id = hft.id 
    WHERE hr.access_token = ? AND hr.template_id = ? AND hr.expires_at > NOW()
");
$response_stmt->execute([$token, $template_id]);
$response = $response_stmt->fetch(PDO::FETCH_ASSOC);

if (!$response) {
    die('الرابط منتهي الصلاحية أو غير صحيح');
}

// Check if already completed
if ($response['status'] === 'completed') {
    $completed = true;
} else {
    $completed = false;
}

// Get questions
$questions_stmt = $db->prepare("SELECT * FROM health_form_questions WHERE template_id = ? ORDER BY sort_order ASC");
$questions_stmt->execute([$template_id]);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing answers if any
$answers = [];
if ($response['status'] !== 'draft') {
    $answers_stmt = $db->prepare("SELECT question_id, answer_value FROM health_form_answers WHERE response_id = ?");
    $answers_stmt->execute([$response['id']]);
    while ($row = $answers_stmt->fetch(PDO::FETCH_ASSOC)) {
        $answers[$row['question_id']] = $row['answer_value'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$completed) {
    try {
        $db->beginTransaction();
        
        // Delete existing answers
        $db->prepare("DELETE FROM health_form_answers WHERE response_id = ?")->execute([$response['id']]);
        
        // Insert new answers
        $answer_stmt = $db->prepare("INSERT INTO health_form_answers (response_id, question_id, answer_value) VALUES (?, ?, ?)");
        
        foreach ($questions as $question) {
            $answer_value = '';
            
            switch ($question['type']) {
                case 'signature':
                    if (!empty($_POST['signature_' . $question['id']])) {
                        $answer_value = $_POST['signature_' . $question['id']];
                    }
                    break;
                default:
                    $answer_value = $_POST['question_' . $question['id']] ?? '';
                    break;
            }
            
            if ($answer_value !== '') {
                $answer_stmt->execute([$response['id'], $question['id'], $answer_value]);
            }
        }
        
        // Update response status
        $update_stmt = $db->prepare("UPDATE health_form_responses SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $update_stmt->execute([$response['id']]);
        
        $db->commit();
        
        // Generate PDF
        require_once 'admin/health_form_pdf_generator.php';
        $pdf_generator = new HealthFormPDFGenerator($db);
        $pdf_path = $pdf_generator->generatePDF($response['id']);
        
        // Update PDF path
        if ($pdf_path) {
            $db->prepare("UPDATE health_form_responses SET pdf_path = ? WHERE id = ?")->execute([$pdf_path, $response['id']]);
        }
        
        $success = true;
        $completed = true;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($response['title']); ?> - MAI Salon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'arabic': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        'mai-brown': '#8B4513',
                        'mai-gold': '#D4AF37',
                        'mai-cream': '#F5E6D3',
                        'mai-dark-brown': '#654321',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .signature-canvas {
            border: 2px dashed #d1d5db;
            cursor: crosshair;
            background-color: #fefefe;
        }
        .signature-canvas.signing {
            border-color: #8B4513;
        }
    </style>
</head>

<body class="font-arabic bg-gray-50" x-data="healthForm()">
    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-6">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-mai-brown mb-2">MAI Salon</h1>
                <p class="text-gray-600">نموذج التاريخ الصحي</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <?php if ($completed): ?>
        <!-- Completion Message -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-green-600 text-white p-6 text-center">
                <i class="fas fa-check-circle text-4xl mb-4"></i>
                <h2 class="text-2xl font-bold mb-2">تم إرسال النموذج بنجاح</h2>
                <p class="text-green-100">شكراً لك على إكمال نموذج التاريخ الصحي</p>
            </div>
            <div class="p-6 text-center">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">تم حفظ بياناتك بأمان</h3>
                    <p class="text-gray-600">سيقوم فريقنا الطبي بمراجعة المعلومات قبل موعدك</p>
                </div>
                
                <div class="bg-mai-cream p-4 rounded-lg mb-6">
                    <div class="flex items-center justify-center text-mai-dark-brown">
                        <i class="fas fa-shield-alt ml-2"></i>
                        <span class="font-medium">جميع معلوماتك محمية ومشفرة وفقاً لمعايير الخصوصية</span>
                    </div>
                </div>
                
                <div class="text-sm text-gray-500">
                    <p>إذا كان لديك أي استفسارات، يرجى التواصل معنا</p>
                    <p class="mt-1">
                        <i class="fas fa-phone ml-1"></i>
                        +966 XX XXX XXXX
                        |
                        <i class="fas fa-envelope ml-1"></i>
                        info@maisalon.com
                    </p>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Form -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Form Header -->
            <div class="bg-mai-brown text-white p-6">
                <h2 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($response['title']); ?></h2>
                <?php if ($response['description']): ?>
                <p class="text-mai-cream opacity-90"><?php echo htmlspecialchars($response['description']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Customer Info -->
            <div class="bg-mai-cream p-4 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-mai-dark-brown">العميل: </span>
                        <span><?php echo htmlspecialchars($response['customer_name']); ?></span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-calendar ml-1"></i>
                        <?php echo date('Y-m-d H:i', strtotime($response['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4">
                <i class="fas fa-check-circle ml-2"></i>
                تم حفظ إجاباتك بنجاح
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Form Body -->
            <div class="p-6">
                <form method="POST" @submit="validateForm($event)" class="space-y-6">
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="question-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo htmlspecialchars($question['label']); ?>
                            <?php if ($question['required']): ?>
                            <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>

                        <?php 
                        $current_answer = $answers[$question['id']] ?? '';
                        
                        switch ($question['type']): 
                            case 'text': ?>
                            <input type="text" 
                                   name="question_<?php echo $question['id']; ?>"
                                   value="<?php echo htmlspecialchars($current_answer); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                   placeholder="<?php echo htmlspecialchars($question['placeholder']); ?>"
                                   <?php echo $question['required'] ? 'required' : ''; ?>>
                            <?php break;
                            
                            case 'textarea': ?>
                            <textarea rows="4" 
                                      name="question_<?php echo $question['id']; ?>"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                      placeholder="<?php echo htmlspecialchars($question['placeholder']); ?>"
                                      <?php echo $question['required'] ? 'required' : ''; ?>><?php echo htmlspecialchars($current_answer); ?></textarea>
                            <?php break;
                            
                            case 'select': 
                                $options = $question['options_json'] ? json_decode($question['options_json'], true) : [];
                            ?>
                            <select name="question_<?php echo $question['id']; ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"
                                    <?php echo $question['required'] ? 'required' : ''; ?>>
                                <option value="">اختر إجابة...</option>
                                <?php foreach ($options as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" 
                                        <?php echo $current_answer === $option ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php break;
                            
                            case 'yesno': ?>
                            <div class="flex space-x-6 space-x-reverse">
                                <label class="flex items-center">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="yes" 
                                           class="text-mai-brown focus:ring-mai-brown"
                                           <?php echo $current_answer === 'yes' ? 'checked' : ''; ?>
                                           <?php echo $question['required'] ? 'required' : ''; ?>>
                                    <span class="mr-2">نعم</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="question_<?php echo $question['id']; ?>" value="no" 
                                           class="text-mai-brown focus:ring-mai-brown"
                                           <?php echo $current_answer === 'no' ? 'checked' : ''; ?>
                                           <?php echo $question['required'] ? 'required' : ''; ?>>
                                    <span class="mr-2">لا</span>
                                </label>
                            </div>
                            <?php break;
                            
                            case 'date': ?>
                            <input type="date" 
                                   name="question_<?php echo $question['id']; ?>"
                                   value="<?php echo htmlspecialchars($current_answer); ?>"
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
                                <input type="hidden" name="signature_<?php echo $question['id']; ?>" x-ref="signatureInput<?php echo $question['id']; ?>">
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
                            <button type="submit" 
                                    class="bg-mai-brown text-white px-8 py-3 rounded-lg hover:bg-mai-dark-brown font-medium disabled:opacity-50"
                                    :disabled="submitting">
                                <span x-show="!submitting">
                                    <i class="fas fa-paper-plane ml-2"></i>إرسال النموذج
                                </span>
                                <span x-show="submitting">
                                    <i class="fas fa-spinner fa-spin ml-2"></i>جاري الإرسال...
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Security Notice -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-blue-600 ml-2"></i>
                <div>
                    <div class="font-medium text-blue-900">الأمان والخصوصية</div>
                    <div class="text-sm text-blue-700">جميع البيانات مشفرة ومحمية وفقاً لأعلى معايير الأمان الطبي</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function healthForm() {
            return {
                submitting: false,
                signatures: {},
                
                validateForm(event) {
                    this.submitting = true;
                    
                    // Save all signatures to hidden inputs
                    Object.keys(this.signatures).forEach(questionId => {
                        const canvas = this.$refs[`signatureCanvas${questionId}`];
                        const input = this.$refs[`signatureInput${questionId}`];
                        if (canvas && input) {
                            input.value = canvas.toDataURL();
                        }
                    });
                },
                
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
</body>
</html>
