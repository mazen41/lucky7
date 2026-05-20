<?php
// bonus_settings.php - A page to manage salon performance bonus rules.

// Include your database configuration
require_once '../config/database.php';

// بداية التخزين المؤقت للإخراج
ob_start();

$message = ''; // لتخزين رسائل النجاح أو الخطأ
$message_type = ''; // 'success' or 'error'

try {
    $database = new Database();
    $db = $database->getConnection();

    // التعامل مع إرسال النموذج (عندما ينقر المستخدم على "حفظ الإعدادات")
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // التحقق من صحة المدخلات وتنقيتها
        $revenue_target = filter_input(INPUT_POST, 'revenue_target', FILTER_VALIDATE_FLOAT);
        $base_bonus = filter_input(INPUT_POST, 'base_bonus', FILTER_VALIDATE_FLOAT);
        $incremental_revenue = filter_input(INPUT_POST, 'incremental_revenue', FILTER_VALIDATE_FLOAT);
        $incremental_bonus = filter_input(INPUT_POST, 'incremental_bonus', FILTER_VALIDATE_FLOAT);

        if ($revenue_target === false || $base_bonus === false || $incremental_revenue === false || $incremental_bonus === false) {
             throw new Exception("مدخلات غير صالحة. الرجاء إدخال أرقام صحيحة.");
        }
        
        // إعداد استعلام واحد لتحديث كافة الإعدادات باستخدام INSERT ... ON DUPLICATE KEY UPDATE
        $update_query = "
            INSERT INTO salon_performance_settings (setting_key, setting_value) 
            VALUES 
                ('revenue_target', ?), 
                ('base_bonus', ?), 
                ('incremental_revenue', ?),
                ('incremental_bonus', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ";
        
        $stmt = $db->prepare($update_query);
        // لا حاجة لربط المتغيرات هنا عند تمريرها مباشرة إلى execute
        $stmt->execute([
            $revenue_target, 
            $base_bonus, 
            $incremental_revenue,
            $incremental_bonus
        ]);

        $message = "✅ تم تحديث الإعدادات بنجاح!";
        $message_type = 'success';
    }

    // جلب الإعدادات الحالية لعرضها في النموذج
    $settings_query = "SELECT setting_key, setting_value FROM salon_performance_settings";
    $settings_stmt = $db->query($settings_query);
    $settings_data = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // تعيين القيم للمتغيرات مع قيم افتراضية في حال عدم العثور عليها
    $current_target = $settings_data['revenue_target'] ?? '50000.00';
    $current_base_bonus = $settings_data['base_bonus'] ?? '500.00';
    $current_incremental_revenue = $settings_data['incremental_revenue'] ?? '10000.00';
    $current_incremental_bonus = $settings_data['incremental_bonus'] ?? '100.00';

} catch (Exception $e) {
    $message = "❌ خطأ: " . $e->getMessage();
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات بونص الموظفين غير المتخصصين</title>
    <style>
        /* A modern font that supports Arabic */
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');

        body { 
            font-family: 'Cairo', sans-serif; 
            background-color: #f4f7f9; 
            color: #333; 
            margin: 0; 
            padding: 20px; 
            direction: rtl; /* Right-to-left direction */
            text-align: right; /* Align text to the right */
        }
        .container { 
            max-width: 800px; 
            margin: 20px auto; 
            background: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
        }
        h1 { 
            color: #2c3e50; 
            border-bottom: 2px solid #e0e0e0; 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 8px; 
            color: #555; 
        }
        input[type="number"] { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
            font-size: 16px; 
            font-family: 'Cairo', sans-serif; /* Ensure font is consistent */
        }
        .description { 
            font-size: 0.9em; 
            color: #777; 
            margin-top: 5px; 
        }
        .btn { 
            background-color: #3498db; 
            color: white; 
            padding: 12px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: bold; 
            transition: background-color 0.3s; 
            font-family: 'Cairo', sans-serif; /* Ensure font is consistent */
        }
        .btn:hover { 
            background-color: #2980b9; 
        }
        .message { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 4px; 
            font-weight: 500; 
            text-align: center; /* Center align message text */
        }
        .message.success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .message.error { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>إعدادات بونص الموظفين غير المتخصصين</h1>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="bonus_settings.php" method="POST">
            <div class="form-group">
                <label for="revenue_target">الإيرادات المستهدفة للصالون (ريال سعودي)</label>
                <input type="number" id="revenue_target" name="revenue_target" step="0.01" value="<?php echo htmlspecialchars($current_target); ?>" required>
                <p class="description">إجمالي إيرادات الصالون المطلوبة لتفعيل البونص الأساسي لجميع الموظفين غير المتخصصين.</p>
            </div>
            
            <div class="form-group">
                <label for="base_bonus">مبلغ البونص الأساسي (ريال سعودي)</label>
                <input type="number" id="base_bonus" name="base_bonus" step="0.01" value="<?php echo htmlspecialchars($current_base_bonus); ?>" required>
                <p class="description">مبلغ البونص الذي يحصل عليه كل موظف غير متخصص عند تحقيق الإيرادات المستهدفة.</p>
            </div>

            <hr style="border: 1px solid #eee; margin: 30px 0;">

            <div class="form-group">
                <label for="incremental_revenue">شريحة الإيرادات الإضافية (ريال سعودي)</label>
                <input type="number" id="incremental_revenue" name="incremental_revenue" step="0.01" value="<?php echo htmlspecialchars($current_incremental_revenue); ?>" required>
                <p class="description">بعد تحقيق الهدف الرئيسي، يتم منح بونص إضافي مقابل كل شريحة من هذا المبلغ.</p>
            </div>

            <div class="form-group">
                <label for="incremental_bonus">مبلغ البونص الإضافي (ريال سعودي)</label>
                <input type="number" id="incremental_bonus" name="incremental_bonus" step="0.01" value="<?php echo htmlspecialchars($current_incremental_bonus); ?>" required>
                <p class="description">مبلغ البونص لكل شريحة إيرادات إضافية يتم تحقيقها.</p>
            </div>

            <button type="submit" class="btn">حفظ الإعدادات</button>
        </form>
    </div>
</body>
</html>
<?php 
// الحصول على محتوى المخزن المؤقت وتضمينه في القالب الأساسي
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>