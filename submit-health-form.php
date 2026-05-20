<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Access denied.");
}

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('خطأ في الاتصال بقاعدة البيانات.');
}

$customer_id = $_POST['customer_id'] ?? 0;
$form_id = $_POST['form_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (empty($customer_id) || empty($form_id) || empty($name) || empty($phone)) {
    die("بيانات أساسية مفقودة. لا يمكن حفظ النموذج.");
}

$answers = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'q_') === 0) {
        $q_id = str_replace('q_', '', $key);
        $answers[$q_id] = trim($value);
    }
}
$json_answers = json_encode($answers, JSON_UNESCAPED_UNICODE);

try {
    $stmt = $db->prepare("INSERT INTO client_forms (customer_id, form_id, name, phone, answers, approved_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$customer_id, $form_id, $name, $phone, $json_answers]);
    $message = "✅ تم إرسال النموذج بنجاح. شكراً لك.";
    $bgColor = "bg-green-100";
    $textColor = "text-green-800";
} catch (PDOException $e) {
    $message = "❌ حدث خطأ أثناء حفظ النموذج. يرجى المحاولة مرة أخرى.";
    $bgColor = "bg-red-100";
    $textColor = "text-red-800";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالة إرسال النموذج</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Tajawal', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="text-center p-10 rounded-xl shadow-lg <?= $bgColor ?> max-w-md mx-auto">
        <h1 class="text-2xl font-bold <?= $textColor ?>"><?= $message ?></h1>
        <p class="text-gray-600 mt-4">يمكنك الآن إغلاق هذه الصفحة.</p>
    </div>
</body>
</html>