<?php
/**
 * Synchronize Inventory and POS Data
 * Matches services and products between inventory system and POS system
 */

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>مزامنة البيانات</title></head><body>";
    echo "<h1>مزامنة بيانات المخزون ونقاط البيع</h1>";
    
    // Get current inventory products
    $inventory_products = $db->query("SELECT * FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>المنتجات الحالية في المخزون: " . count($inventory_products) . "</h2>";
    
    // Clear and rebuild products with proper Arabic data
    $db->exec("DELETE FROM products");
    
    $salon_products = [
        ['أحمر شفاه مقاوم للماء', 'Waterproof Lipstick', 'LIP001', 45.00, 50, 'مستحضرات التجميل'],
        ['بلسم الشعر 500مل', 'Hair Conditioner 500ml', 'CND001', 75.00, 30, 'منتجات الشعر'],
        ['خدمة المانيكير', 'Manicure Service', 'SRV002', 60.00, 999, 'خدمة'],
        ['زيت الأرجان للشعر 100مل', 'Argan Hair Oil 100ml', 'OIL001', 120.00, 25, 'منتجات الشعر'],
        ['شامبو احترافي 500مل', 'Professional Shampoo 500ml', 'SHP001', 85.00, 40, 'منتجات الشعر'],
        ['خدمة المانيكير', 'Manicure Service', 'MAN001', 60.00, 999, 'خدمة أساسية'],
        ['شامبو احترافي 500مل', 'Professional Shampoo 500ml', 'SHP002', 85.00, 25, 'منتجات الشعر'],
        ['كريم مرطب للوجه', 'Face Moisturizer', 'FCE001', 95.00, 35, 'منتجات الوجه'],
        ['مقشر للجسم', 'Body Scrub', 'BDY001', 65.00, 20, 'منتجات الجسم'],
        ['طلاء أظافر', 'Nail Polish', 'NLP001', 25.00, 100, 'منتجات الأظافر'],
        ['ماسك للشعر', 'Hair Mask', 'HMK001', 55.00, 30, 'منتجات الشعر'],
        ['كحل السود', 'Black Eyeliner', 'EYE001', 35.00, 60, 'مستحضرات التجميل'],
        ['قناع مرطب للوجه', 'Hydrating Face Mask', 'MSK001', 80.00, 25, 'منتجات الوجه'],
        ['قص وتصفيف الشعر', 'Hair Cut & Style', 'CUT001', 150.00, 999, 'خدمة'],
        ['كريم مرطب للبشرة الجافة', 'Dry Skin Moisturizer', 'DRY001', 110.00, 20, 'منتجات الوجه']
    ];
    
    $insert_product = $db->prepare("INSERT INTO products (name_ar, name_en, sku, price, stock_quantity, category_ar, cost_price, min_stock_level, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 5, 1)");
    
    foreach ($salon_products as $product) {
        $cost_price = $product[3] * 0.6; // 60% of selling price
        $insert_product->execute([
            $product[0], $product[1], $product[2], $product[3], 
            $product[4], $product[5], $cost_price
        ]);
    }
    
    echo "<p style='color:green'>✓ تم إضافة " . count($salon_products) . " منتج</p>";
    
    // Clear and rebuild services with proper Arabic data
    $db->exec("DELETE FROM services");
    
    $salon_services = [
        ['قص الشعر', 'Hair Cut', 'قص وتصفيف الشعر بأحدث الطرق', 'Professional hair cutting and styling', 80.00, 45],
        ['صبغة الشعر', 'Hair Coloring', 'صبغ الشعر بألوان عصرية وآمنة', 'Modern and safe hair coloring', 150.00, 90],
        ['تسريحة العروس', 'Bridal Hair', 'تسريحة شعر مميزة للعروس', 'Special bridal hairstyling', 250.00, 120],
        ['مكياج', 'Makeup', 'مكياج احترافي للمناسبات', 'Professional makeup for events', 200.00, 60],
        ['تنظيف البشرة', 'Facial Cleansing', 'تنظيف عميق للبشرة وإزالة الشوائب', 'Deep facial cleansing treatment', 120.00, 75],
        ['مانيكير', 'Manicure', 'العناية بالأظافر والأيدي', 'Hand and nail care service', 60.00, 30],
        ['باديكير', 'Pedicure', 'العناية بأظافر القدمين', 'Foot and toenail care service', 80.00, 45],
        ['علاج الوجه', 'Facial Treatment', 'علاج متخصص للبشرة حسب النوع', 'Specialized facial treatment', 180.00, 90],
        ['خلطة الطهارة الأحمر', 'Red Purifying Mix', 'خلطة طبيعية لتنظيف وتطهير البشرة', 'Natural mix for skin cleansing', 100.00, 60],
        ['مقشر فرنسي شعر بالزيوت', 'French Hair Scrub with Oils', 'مقشر فرنسي للشعر بالزيوت الطبيعية', 'French hair scrub with natural oils', 140.00, 75],
        ['العناية بالوجه والمكياج', 'Facial Care & Makeup', 'عناية شاملة بالوجه مع مكياج احترافي', 'Complete facial care with professional makeup', 200.00, 120],
        ['خدمة المانيكير', 'Manicure Service', 'خدمة العناية بالأظافر والأيدي', 'Professional nail and hand care', 60.00, 30],
        ['شامبو احترافي 500مل', 'Professional Shampoo Service', 'خدمة غسيل الشعر بالشامبو الاحترافي', 'Professional hair washing service', 50.00, 30],
        ['العناية بالجسم', 'Body Care', 'عناية شاملة بالجسم والبشرة', 'Complete body and skin care', 160.00, 90],
        ['مقشر بالزيوت', 'Oil Scrub', 'مقشر طبيعي بالزيوت المغذية', 'Natural scrub with nourishing oils', 90.00, 45]
    ];
    
    $insert_service = $db->prepare("INSERT INTO services (title_ar, title_en, description_ar, description_en, price, duration, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    
    foreach ($salon_services as $index => $service) {
        $insert_service->execute([
            $service[0], $service[1], $service[2], $service[3], 
            $service[4], $service[5], ($index + 1) * 10
        ]);
    }
    
    echo "<p style='color:green'>✓ تم إضافة " . count($salon_services) . " خدمة</p>";
    
    // Update team members to match services
    $db->exec("DELETE FROM team_members");
    
    $team_members = [
        ['سارة أحمد', 'Sara Ahmed', 'خبيرة تجميل', 'Beauty Expert', 'متخصصة في المكياج والعناية بالبشرة', '0501234567'],
        ['فاطمة علي', 'Fatima Ali', 'مصففة شعر', 'Hair Stylist', 'خبيرة في قص وصبغ الشعر', '0509876543'],
        ['نور محمد', 'Nour Mohammed', 'أخصائية عناية', 'Skincare Specialist', 'متخصصة في علاج البشرة', '0551234567'],
        ['مريم حسن', 'Mariam Hassan', 'خبيرة أظافر', 'Nail Expert', 'متخصصة في المانيكير والباديكير', '0561234567'],
        ['هدى سالم', 'Huda Salem', 'مدربة جمال', 'Beauty Trainer', 'خبيرة في جميع خدمات الصالون', '0571234567']
    ];
    
    // Check if phone column exists in team_members
    $team_columns = $db->query("SHOW COLUMNS FROM team_members")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('phone', $team_columns)) {
        $insert_team = $db->prepare("INSERT INTO team_members (name_ar, name_en, position_ar, position_en, bio_ar, phone, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        
        foreach ($team_members as $index => $member) {
            $insert_team->execute([
                $member[0], $member[1], $member[2], $member[3], 
                $member[4], $member[5], ($index + 1) * 10
            ]);
        }
    } else {
        $insert_team = $db->prepare("INSERT INTO team_members (name_ar, name_en, position_ar, position_en, bio_ar, is_active, sort_order) VALUES (?, ?, ?, ?, ?, 1, ?)");
        
        foreach ($team_members as $index => $member) {
            $insert_team->execute([
                $member[0], $member[1], $member[2], $member[3], 
                $member[4], ($index + 1) * 10
            ]);
        }
    }
    
    echo "<p style='color:green'>✓ تم إضافة " . count($team_members) . " عضو فريق</p>";
    
    // Verify synchronization
    echo "<h2>التحقق من المزامنة</h2>";
    
    $product_count = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $service_count = $db->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();
    $team_count = $db->query("SELECT COUNT(*) FROM team_members WHERE is_active = 1")->fetchColumn();
    
    echo "<div style='background:#d4edda; padding:20px; border-radius:5px; margin:20px 0;'>";
    echo "<h3 style='color:#155724;'>✅ تمت المزامنة بنجاح!</h3>";
    echo "<ul style='color:#155724;'>";
    echo "<li>المنتجات النشطة: $product_count</li>";
    echo "<li>الخدمات النشطة: $service_count</li>";
    echo "<li>أعضاء الفريق: $team_count</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='margin-top:30px;'>";
    echo "<a href='pos.php' style='background:#28a745; color:white; padding:15px 30px; text-decoration:none; border-radius:5px; margin-left:10px;'>🛒 نقاط البيع</a>";
    echo "<a href='inventory.php' style='background:#007bff; color:white; padding:15px 30px; text-decoration:none; border-radius:5px;'>📦 المخزون</a>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<div style='background:#f8d7da; padding:20px; border-radius:5px;'>";
    echo "<h3 style='color:#721c24;'>❌ خطأ في المزامنة</h3>";
    echo "<p style='color:#721c24;'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
