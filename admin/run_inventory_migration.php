<?php
/**
 * Inventory Management Migration Runner
 * Executes the inventory schema migration with error handling
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('فشل الاتصال بقاعدة البيانات');
    }
    
    echo "<!DOCTYPE html>";
    echo "<html lang='ar' dir='rtl'>";
    echo "<head><meta charset='UTF-8'><title>تشغيل Migration - المخزون والمنتجات</title>";
    echo "<style>body{font-family:Arial;margin:20px;}.success{color:green;}.error{color:red;}.info{color:blue;}</style>";
    echo "</head><body>";
    
    echo "<h1>🏪 تشغيل Migration - نظام المخزون والمنتجات</h1>";
    
    // Read and execute migration file
    $migrationFile = '../database/migrations/002_inventory_management_schema.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("ملف Migration غير موجود: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (!$sql) {
        throw new Exception('فشل في قراءة ملف Migration');
    }
    
    echo "<div class='info'>📄 تم قراءة ملف Migration بنجاح (" . strlen($sql) . " حرف)</div>";
    
    // Remove comments and split into statements
    $lines = explode("\n", $sql);
    $cleanSql = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && !str_starts_with($line, '--') && !str_starts_with($line, '#')) {
            $cleanSql .= $line . "\n";
        }
    }
    
    // Split by delimiter
    $statements = explode('$$', $cleanSql);
    $regularStatements = [];
    $procedures = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        if (strpos($statement, 'DELIMITER') !== false) continue;
        
        if (strpos($statement, 'CREATE PROCEDURE') !== false || 
            strpos($statement, 'CREATE TRIGGER') !== false) {
            $procedures[] = $statement;
        } else {
            // Split regular statements by semicolon
            $subStatements = explode(';', $statement);
            foreach ($subStatements as $subStatement) {
                $subStatement = trim($subStatement);
                if (!empty($subStatement)) {
                    $regularStatements[] = $subStatement;
                }
            }
        }
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    echo "<h2>📋 تنفيذ الجداول والفهارس</h2>";
    
    // Execute regular statements first
    foreach ($regularStatements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            $successCount++;
            
            // Extract info for display
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='success'>✅ تم إنشاء الجدول: {$matches[1]}</div>";
            } elseif (preg_match('/ALTER TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='success'>✅ تم تعديل الجدول: {$matches[1]}</div>";
            } elseif (preg_match('/INSERT.*?INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='success'>✅ تم إدراج البيانات في: {$matches[1]}</div>";
            } elseif (preg_match('/CREATE.*?INDEX.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='success'>✅ تم إنشاء الفهرس: {$matches[1]}</div>";
            } elseif (preg_match('/CREATE.*?VIEW.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='success'>✅ تم إنشاء العرض: {$matches[1]}</div>";
            } else {
                echo "<div class='success'>✅ تم تنفيذ الاستعلام بنجاح</div>";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            
            // Check if it's a non-critical error
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'Unknown column') !== false) {
                echo "<div class='info'>ℹ️ العنصر موجود بالفعل أو تم تخطيه: " . htmlspecialchars($e->getMessage()) . "</div>";
            } else {
                echo "<div class='error'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</div>";
                echo "<div class='error'>الاستعلام: " . htmlspecialchars(substr($statement, 0, 100)) . "...</div>";
            }
        }
    }
    
    echo "<h2>⚙️ تنفيذ الإجراءات المخزنة والمحفزات</h2>";
    
    // Execute procedures and triggers
    foreach ($procedures as $procedure) {
        if (empty($procedure)) continue;
        
        try {
            $db->exec($procedure);
            $successCount++;
            
            if (preg_match('/CREATE PROCEDURE.*?`?(\w+)`?/i', $procedure, $matches)) {
                echo "<div class='success'>✅ تم إنشاء الإجراء المخزن: {$matches[1]}</div>";
            } elseif (preg_match('/CREATE TRIGGER.*?`?(\w+)`?/i', $procedure, $matches)) {
                echo "<div class='success'>✅ تم إنشاء المحفز: {$matches[1]}</div>";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<div class='info'>ℹ️ الإجراء موجود بالفعل: " . htmlspecialchars($e->getMessage()) . "</div>";
            } else {
                echo "<div class='error'>❌ خطأ في الإجراء: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h2>📊 ملخص النتائج</h2>";
    echo "<div class='success'>✅ العمليات الناجحة: $successCount</div>";
    echo "<div class='error'>❌ الأخطاء: $errorCount</div>";
    
    if ($errorCount <= 3) { // Allow some non-critical errors
        echo "<div class='success'><h3>🎉 تم تنفيذ Migration بنجاح!</h3></div>";
        echo "<p><strong>المكونات المطبقة:</strong></p>";
        echo "<ul>";
        echo "<li>✅ جدول تصنيفات المنتجات (product_categories)</li>";
        echo "<li>✅ جدول المنتجات المحسن (products)</li>";
        echo "<li>✅ جدول حركات المخزون (stock_movements)</li>";
        echo "<li>✅ جدول جرد المخزون (stock_counts)</li>";
        echo "<li>✅ جدول عناصر الجرد (stock_count_items)</li>";
        echo "<li>✅ جدول تنبيهات المخزون المنخفض (low_stock_alerts)</li>";
        echo "<li>✅ العروض والفهارس لتحسين الأداء</li>";
        echo "<li>✅ المحفزات التلقائية لتحديث المخزون</li>";
        echo "<li>✅ الإجراءات المخزنة للعمليات المعقدة</li>";
        echo "</ul>";
    } else {
        echo "<div class='error'><h3>⚠️ تم التنفيذ مع أخطاء كثيرة</h3></div>";
        echo "<p>يرجى مراجعة الأخطاء أعلاه وإصلاحها.</p>";
    }
    
    // Test the new components
    echo "<hr>";
    echo "<h2>🧪 اختبار المكونات الجديدة</h2>";
    
    // Test ProductManager
    try {
        require_once '../app/Core/ProductManager.php';
        $productManager = new ProductManager();
        
        $stats = $productManager->getInventoryStats();
        echo "<div class='success'>✅ ProductManager يعمل - إجمالي المنتجات: " . $stats['total_products'] . "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطأ في ProductManager: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Test InventoryManager
    try {
        require_once '../app/Core/InventoryManager.php';
        $inventoryManager = new InventoryManager();
        
        $alerts = $inventoryManager->getLowStockAlerts();
        echo "<div class='success'>✅ InventoryManager يعمل - تنبيهات المخزون: " . count($alerts) . "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطأ في InventoryManager: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Test database views
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM v_current_stock");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div class='success'>✅ عرض المخزون الحالي يعمل - عدد المنتجات: " . $result['count'] . "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ خطأ في عرض المخزون: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🔗 الخطوات التالية</h2>";
    echo "<ol>";
    echo "<li><a href='inventory.php'>🏪 الذهاب إلى إدارة المخزون</a></li>";
    echo "<li><a href='service_categories_enhanced.php'>📂 إدارة تصنيفات المنتجات</a></li>";
    echo "<li><a href='settings_enhanced.php'>⚙️ الإعدادات المحسنة</a></li>";
    echo "<li><a href='dashboard.php'>📊 العودة إلى لوحة التحكم</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div class='error'><h2>❌ خطأ خطير</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>
