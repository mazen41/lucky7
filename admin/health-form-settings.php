<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
 $admin = getCurrentAdmin();

 $database = new Database();
 $db = $database->getConnection();

 $success = $_SESSION['flash_success'] ?? null;
 $error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle all form submissions for forms and questions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            // Form Actions
            case 'create_form':
                $name = trim($_POST['name']);
                if (empty($name)) throw new Exception("اسم النموذج مطلوب.");
                $stmt = $db->prepare("INSERT INTO health_forms (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $_POST['description']]);
                $_SESSION['flash_success'] = "تم إنشاء النموذج الصحي بنجاح.";
                break;
            case 'delete_form':
                $form_id = intval($_POST['form_id']);
                $stmt = $db->prepare("DELETE FROM health_forms WHERE id = ?");
                $stmt->execute([$form_id]);
                $_SESSION['flash_success'] = "تم حذف النموذج الصحي.";
                break;
            
            // Question Actions
            case 'create_question':
                $form_id = intval($_POST['form_id']);
                $question_text = trim($_POST['question_text']);
                if (empty($question_text)) throw new Exception("نص السؤال مطلوب.");
                $stmt = $db->prepare("INSERT INTO health_form_questions (form_id, question_text) VALUES (?, ?)");
                $stmt->execute([$form_id, $question_text]);
                // Redirect back to the same page with the form open
                header('Location: ' . $_SERVER['PHP_SELF'] . '?view_form=' . $form_id);
                exit();
            case 'delete_question':
                $question_id = intval($_POST['question_id']);
                $form_id = intval($_POST['form_id']); // for redirect
                $stmt = $db->prepare("DELETE FROM health_form_questions WHERE id = ?");
                $stmt->execute([$question_id]);
                $_SESSION['flash_success'] = "تم حذف السؤال.";
                header('Location: ' . $_SERVER['PHP_SELF'] . '?view_form=' . $form_id);
                exit();
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all health forms
 $forms_stmt = $db->query("SELECT * FROM health_forms ORDER BY created_at DESC");
 $health_forms = $forms_stmt->fetchAll(PDO::FETCH_ASSOC);

 $view_form_id = $_GET['view_form'] ?? null;
 $current_form = null;
 $questions = [];
if ($view_form_id) {
    $form_stmt = $db->prepare("SELECT * FROM health_forms WHERE id = ?");
    $form_stmt->execute([$view_form_id]);
    $current_form = $form_stmt->fetch(PDO::FETCH_ASSOC);

    if($current_form) {
        $questions_stmt = $db->prepare("SELECT * FROM health_form_questions WHERE form_id = ? ORDER BY id ASC");
        $questions_stmt->execute([$view_form_id]);
        $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


 $page_title = 'إعدادات النموذج الصحي';
ob_start();
?>
<div class="p-4 md:p-6" x-data="{ addFormModal: false }">
    <div class="bg-white rounded-xl shadow-md mb-6 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-lg font-bold text-mai-dark">إعدادات النموذج الصحي</h1>
                    <p class="text-sm text-gray-500">إدارة النماذج الصحية والأسئلة الخاصة بها.</p>
                </div>
            </div>
            <!-- EDITED: Added a flex container for buttons -->
            <div class="flex items-center gap-3">
                 <a href="/admin/settings.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span>العودة للإعدادات</span>
                </a>
                <button @click="addFormModal = true" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark"><i class="fas fa-plus ml-2"></i>إضافة نموذج جديد</button>
            </div>
        </div>
    </div>

    <?php if ($success): ?><div class="bg-green-100 border-r-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert"><p><?php echo htmlspecialchars($success); ?></p></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert"><p><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Forms List -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-mai-dark border-b pb-3 mb-4">النماذج المتاحة</h3>
                <?php if(empty($health_forms)): ?>
                    <p class="text-gray-500 text-center py-4">لا توجد نماذج صحية. قم بإضافة نموذج جديد للبدء.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach($health_forms as $form): ?>
                        <li class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 <?= ($view_form_id == $form['id']) ? 'bg-mai-cream' : '' ?>">
                            <a href="?view_form=<?= $form['id'] ?>" class="font-semibold text-gray-800 flex-grow"><?= htmlspecialchars($form['name']) ?></a>
                            <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا النموذج وكل أسئلته؟');" class="inline-block">
                                <input type="hidden" name="action" value="delete_form">
                                <input type="hidden" name="form_id" value="<?= $form['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 p-2"><i class="fas fa-trash"></i></button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Questions Management -->
        <div class="lg:col-span-2">
            <?php if($current_form): ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-mai-dark">أسئلة نموذج: "<?= htmlspecialchars($current_form['name']) ?>"</h3>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="bg-gray-200 text-gray-700 px-3 py-1 rounded-lg hover:bg-gray-300 text-sm flex items-center gap-2 transition-colors">
                        <i class="fas fa-arrow-right"></i>
                        <span>العودة</span>
                    </a>
                </div>
                
                <!-- Add Question Form -->
                <form method="POST" class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <input type="hidden" name="action" value="create_question">
                    <input type="hidden" name="form_id" value="<?= $current_form['id'] ?>">
                    <label class="block text-sm font-medium text-gray-700">إضافة سؤال جديد</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input type="text" name="question_text" required placeholder="اكتب نص السؤال هنا..." class="flex-grow p-2 border rounded-lg">
                        <button type="submit" class="bg-mai-dark text-white px-4 py-2 rounded-lg hover:bg-black">إضافة</button>
                    </div>
                </form>

                <!-- Questions List -->
                <div class="space-y-3">
                    <?php if(empty($questions)): ?>
                        <p class="text-gray-500 text-center py-8">لا توجد أسئلة في هذا النموذج بعد.</p>
                    <?php else: ?>
                    <?php foreach($questions as $question): ?>
                    <div class="flex items-center justify-between p-3 border rounded-lg">
                        <p class="text-gray-800"><?= htmlspecialchars($question['question_text']) ?></p>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا السؤال؟');" class="inline-block">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                            <input type="hidden" name="form_id" value="<?= $current_form['id'] ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700 p-2"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-arrow-left text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-600">الرجاء اختيار نموذج من القائمة لعرض وإدارة أسئلته.</h3>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Form Modal -->
    <div x-show="addFormModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div @click.away="addFormModal = false" class="bg-white rounded-xl p-6 w-full max-w-lg" x-show="addFormModal" x-transition>
            <h3 class="text-xl font-bold text-mai-dark mb-4">إضافة نموذج صحي جديد</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_form">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">اسم النموذج (إلزامي)</label>
                        <input type="text" name="name" required placeholder="مثال: نموذج خدمات الليزر" class="mt-1 w-full p-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">الوصف (اختياري)</label>
                        <textarea name="description" rows="3" placeholder="وصف موجز عن الغرض من هذا النموذج" class="mt-1 w-full p-2 border rounded-lg"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <button type="button" @click="addFormModal = false" class="px-6 py-2 border rounded-lg">إلغاء</button>
                    <button type="submit" class="bg-mai-brown text-white px-8 py-2 rounded-lg hover:bg-mai-dark">إنشاء النموذج</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>