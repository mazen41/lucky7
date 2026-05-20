<?php
// This is a public page
require_once 'config/database.php';

// Establish database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('خطأ في الاتصال بقاعدة البيانات.');
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('رمز QR غير صالح أو مفقود.');
}

// Fetch customer using the QR token
// *** EDITED: We also need the 'assigned_health_form_id' column ***
$stmt = $db->prepare("SELECT id, name_ar, phone, assigned_health_form_id FROM customers WHERE qr_token = ?");
$stmt->execute([$token]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('رمز QR غير صالح.');
}

// --- *** NEW, SIMPLIFIED LOGIC *** ---
// 1. Check if a specific form has been assigned to this customer.
$form_id = $customer['assigned_health_form_id'];

// 2. If no form is assigned, fall back to the default form (ID #1).
if (empty($form_id)) {
    $form_id = 1; // Default form ID
}
// --- *** LOGIC ENDS HERE *** ---

// Fetch the details of the determined form
$form_stmt = $db->prepare("SELECT * FROM health_forms WHERE id = ?");
$form_stmt->execute([$form_id]);
$form = $form_stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    // This can happen if the assigned form was deleted
    die('لم يتم العثور على النموذج الصحي المطلوب. يرجى مراجعة الإدارة.');
}

// Fetch all questions for this form
$questions_stmt = $db->prepare("SELECT * FROM health_form_questions WHERE form_id = ? ORDER BY id ASC");
$questions_stmt->execute([$form['id']]);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Tajawal', sans-serif; } </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto max-w-2xl my-10 px-4">
        <form action="submit-health-form.php" method="POST" class="bg-white p-8 rounded-xl shadow-lg">
            
            <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
            <input type="hidden" name="form_id" value="<?= $form['id'] ?>">
            
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($form['name']) ?></h1>
                <p class="text-gray-500 mt-2">مرحباً <span class="font-bold"><?= htmlspecialchars($customer['name_ar']) ?></span>، يرجى تعبئة النموذج بدقة.</p>
            </div>

            <div class="space-y-6">
                <div>
                    <label for="name" class="block text-md font-medium text-gray-700 mb-1">الاسم الكامل</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($customer['name_ar']) ?>" required class="border p-3 rounded-lg w-full bg-gray-50">
                </div>
                <div>
                    <label for="phone" class="block text-md font-medium text-gray-700 mb-1">رقم الجوال</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone']) ?>" required class="border p-3 rounded-lg w-full bg-gray-50">
                </div>

                <!-- Dynamic Questions -->
                <?php foreach ($questions as $q): ?>
                <div class="border-t pt-6">
                    <label class="block text-md font-medium text-gray-700 mb-2"><?= htmlspecialchars($q['question_text']) ?></label>
                    <input type="text" name="q_<?= $q['id'] ?>" class="border p-3 rounded-lg w-full">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-10 pt-6 border-t">
                <button type="submit" class="w-full bg-blue-600 text-white px-6 py-4 rounded-lg text-lg font-bold hover:bg-blue-700">إرسال النموذج</button>
            </div>
        </form>
    </div>
</body>
</html>