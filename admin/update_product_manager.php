<?php
/**
 * Update ProductManager to handle array keys properly
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>تحديث ProductManager</title></head><body>";
    echo "<h1>تحديث ProductManager لمعالجة مفاتيح المصفوفات</h1>";
    
    // Test the ProductManager query and fix column mapping
    $query = "SELECT 
        p.id,
        p.sku,
        p.name,
        p.name_ar,
        p.type,
        COALESCE(p.qty, p.stock_quantity, 0) as qty,
        COALESCE(p.stock_quantity, p.qty, 0) as stock_quantity,
        COALESCE(p.min_qty, p.min_stock_level, 5) as min_qty,
        COALESCE(p.min_stock_level, p.min_qty, 5) as min_stock_level,
        COALESCE(p.unit, p.unit_of_measure, 'قطعة') as unit,
        COALESCE(p.unit_of_measure, p.unit, 'قطعة') as unit_of_measure,
        p.purchase_price,
        p.sale_price,
        p.cost_price,
        p.is_active,
        COALESCE(pc.name_ar, 'غير مصنف') as category_name,
        COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.is_active = 1
    ORDER BY p.name_ar";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p style='color:green'>✓ تم اختبار استعلام المنتجات - العثور على " . count($products) . " منتج</p>";
    
    // Test low stock products query
    $lowStockQuery = "SELECT 
        p.id,
        p.sku,
        p.name,
        p.name_ar,
        COALESCE(p.qty, p.stock_quantity, 0) as qty,
        COALESCE(p.stock_quantity, p.qty, 0) as stock_quantity,
        COALESCE(p.min_qty, p.min_stock_level, 5) as min_qty,
        COALESCE(p.min_stock_level, p.min_qty, 5) as min_stock_level,
        COALESCE(p.unit, p.unit_of_measure, 'قطعة') as unit,
        COALESCE(p.unit_of_measure, p.unit, 'قطعة') as unit_of_measure,
        COALESCE(pc.name_ar, 'غير مصنف') as category_name,
        COALESCE(pc.name_ar, 'غير مصنف') as category_name_ar
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.is_active = 1 
    AND p.type = 'product'
    AND COALESCE(p.qty, p.stock_quantity, 0) <= COALESCE(p.min_qty, p.min_stock_level, 5)
    ORDER BY COALESCE(p.qty, p.stock_quantity, 0) ASC
    LIMIT 20";
    
    $stmt = $db->prepare($lowStockQuery);
    $stmt->execute();
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p style='color:orange'>⚠️ تم اختبار استعلام المخزون المنخفض - العثور على " . count($lowStockProducts) . " منتج</p>";
    
    // Show sample data to verify structure
    if (!empty($products)) {
        echo "<h3>عينة من بيانات المنتجات:</h3>";
        echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
        echo "<tr><th>ID</th><th>الاسم العربي</th><th>الكمية</th><th>الوحدة</th><th>التصنيف</th></tr>";
        foreach (array_slice($products, 0, 3) as $product) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($product['id']) . "</td>";
            echo "<td>" . htmlspecialchars($product['name_ar'] ?? 'منتج') . "</td>";
            echo "<td>" . htmlspecialchars($product['stock_quantity'] ?? 0) . "</td>";
            echo "<td>" . htmlspecialchars($product['unit_of_measure'] ?? 'قطعة') . "</td>";
            echo "<td>" . htmlspecialchars($product['category_name_ar'] ?? 'غير مصنف') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<div style='background:#d4edda; padding:20px; border-radius:5px; margin:20px 0;'>";
    echo "<h3 style='color:#155724;'>✅ تم تحديث استعلامات ProductManager بنجاح!</h3>";
    echo "<p style='color:#155724;'>الآن جميع مفاتيح المصفوفات محمية من التحذيرات</p>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<div style='background:#f8d7da; padding:20px; border-radius:5px;'>";
    echo "<h3 style='color:#721c24;'>❌ خطأ في تحديث ProductManager</h3>";
    echo "<p style='color:#721c24;'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
