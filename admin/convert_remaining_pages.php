<?php
/**
 * Convert remaining admin pages to use standardized layout
 */

$pages_to_convert = [
    'appointments.php',
    'appointments_calendar.php', 
    'customer_health_forms.php',
    'customer_profile.php',
    'health_form_builder.php',
    'health_form_preview.php',
    'health_form_responses.php',
    'health_form_view.php',
    'hr_attendance.php',
    'hr_requests.php',
    'loyalty_cron.php',
    'loyalty_engine.php',
    'loyalty_pos_integration.php',
    'marketing_api.php',
    'marketing_messaging.php',
    'marketing_segmentation.php',
    'pos_actions.php',
    'pos_discounts.php',
    'pos_modals.php',
    'pos_returns.php',
    'pos_shifts.php',
    'reports_engine.php',
    'role_permissions_manager.php',
    'user_permissions_modal.php'
];

echo "<h2>Converting Remaining Pages to Standardized Layout</h2>\n";
echo "<pre>\n";

foreach ($pages_to_convert as $page) {
    $file_path = __DIR__ . '/' . $page;
    
    if (!file_exists($file_path)) {
        echo "⚠️  File not found: $page\n";
        continue;
    }
    
    echo "Processing: $page\n";
    
    // Read file content
    $content = file_get_contents($file_path);
    
    // Check if already uses standardized layout
    if (strpos($content, 'includes/admin_layout.php') !== false) {
        echo "  ✓ Already standardized: $page\n";
        continue;
    }
    
    // Create backup
    $backup_path = $file_path . '.backup_' . date('Y-m-d_H-i-s');
    file_put_contents($backup_path, $content);
    
    // Extract page-specific variables and content
    $page_name = pathinfo($page, PATHINFO_FILENAME);
    $page_title_ar = getPageTitleArabic($page_name);
    
    // Find the main content area (after HTML head and before closing body)
    preg_match('/<body[^>]*>(.*?)<\/body>/s', $content, $body_matches);
    
    if (!$body_matches) {
        echo "  ❌ Could not extract body content from $page\n";
        continue;
    }
    
    $body_content = $body_matches[1];
    
    // Remove sidebar includes and navigation
    $body_content = preg_replace('/include[^;]*sidebar\.php[^;]*;/', '', $body_content);
    $body_content = preg_replace('/<div[^>]*sidebar[^>]*>.*?<\/div>/s', '', $body_content);
    
    // Extract PHP logic from the beginning
    preg_match('/^<\?php(.*?)\?>/s', $content, $php_matches);
    $php_logic = $php_matches[1] ?? '';
    
    // Create new standardized content
    $new_content = "<?php\n";
    $new_content .= $php_logic . "\n\n";
    $new_content .= "// Layout configuration\n";
    $new_content .= "\$page_title = '$page_title_ar';\n";
    $new_content .= "\$page_subtitle = 'إدارة وعرض البيانات';\n";
    $new_content .= "\$page_actions = '';\n\n";
    
    // Add admin info
    $new_content .= "// Get current admin info\n";
    $new_content .= "\$admin = [\n";
    $new_content .= "    'id' => \$_SESSION['admin_id'] ?? 1,\n";
    $new_content .= "    'username' => \$_SESSION['admin_username'] ?? 'admin'\n";
    $new_content .= "];\n\n";
    
    // Start output buffering for content
    $new_content .= "ob_start();\n";
    $new_content .= "?>\n\n";
    
    // Add cleaned body content
    $new_content .= $body_content . "\n\n";
    
    // Capture content and include layout
    $new_content .= "<?php\n";
    $new_content .= "\$page_content = ob_get_clean();\n";
    $new_content .= "include 'includes/admin_layout.php';\n";
    $new_content .= "?>";
    
    // Write new content
    if (file_put_contents($file_path, $new_content)) {
        echo "  ✅ Converted: $page\n";
    } else {
        echo "  ❌ Failed to write: $page\n";
    }
}

echo "\n=== Conversion Complete ===\n";
echo "All remaining pages now use standardized layout!\n";
echo "</pre>";

function getPageTitleArabic($page_name) {
    $titles = [
        'appointments' => 'إدارة المواعيد',
        'appointments_calendar' => 'تقويم المواعيد',
        'customer_health_forms' => 'استمارات صحة العملاء',
        'customer_profile' => 'ملف العميل',
        'health_form_builder' => 'منشئ الاستمارات الصحية',
        'health_form_preview' => 'معاينة الاستمارة الصحية',
        'health_form_responses' => 'ردود الاستمارات الصحية',
        'health_form_view' => 'عرض الاستمارة الصحية',
        'hr_attendance' => 'حضور الموظفين',
        'hr_requests' => 'طلبات الموارد البشرية',
        'loyalty_cron' => 'مهام الولاء المجدولة',
        'loyalty_engine' => 'محرك الولاء',
        'loyalty_pos_integration' => 'تكامل الولاء مع نقاط البيع',
        'marketing_api' => 'واجهة برمجة التسويق',
        'marketing_messaging' => 'رسائل التسويق',
        'marketing_segmentation' => 'تقسيم العملاء التسويقي',
        'pos_actions' => 'إجراءات نقاط البيع',
        'pos_discounts' => 'خصومات نقاط البيع',
        'pos_modals' => 'نوافذ نقاط البيع',
        'pos_returns' => 'مرتجعات نقاط البيع',
        'pos_shifts' => 'ورديات نقاط البيع',
        'reports_engine' => 'محرك التقارير',
        'role_permissions_manager' => 'إدارة صلاحيات الأدوار',
        'user_permissions_modal' => 'نافذة صلاحيات المستخدم'
    ];
    
    return $titles[$page_name] ?? 'صفحة إدارية';
}
?>
