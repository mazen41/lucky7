<?php
/**
 * Mass Page Converter - Converts all admin pages to use standardized layout
 * This ensures every sidebar button maintains consistent layout
 */

require_once '../config/database.php';

// List of all admin pages that need conversion
$pages_to_convert = [
    'dashboard.php' => ['title' => 'لوحة التحكم', 'subtitle' => 'نظرة شاملة على أداء الصالون اليوم'],
    'pos.php' => ['title' => 'نقطة البيع (POS)', 'subtitle' => 'نظام نقطة البيع والمبيعات'],
    'clients.php' => ['title' => 'إدارة العملاء (CRM)', 'subtitle' => 'إدارة بيانات العملاء وتتبع تفاعلاتهم'],
    'customer_segments.php' => ['title' => 'شرائح العملاء', 'subtitle' => 'تصنيف العملاء وإدارة الشرائح المختلفة'],
    'health_forms.php' => ['title' => 'النماذج الصحية', 'subtitle' => 'نماذج الفحص الصحي للعملاء'],
    'inventory.php' => ['title' => 'المخزون والمنتجات', 'subtitle' => 'إدارة المخزون والمنتجات والمواد'],
    'hr_dashboard.php' => ['title' => 'الموارد البشرية (HR)', 'subtitle' => 'إدارة الموظفين والموارد البشرية'],
    'loyalty.php' => ['title' => 'نظام الولاء (Loyalty)', 'subtitle' => 'برنامج الولاء ونقاط العملاء'],
    'marketing.php' => ['title' => 'الحملات التسويقية', 'subtitle' => 'الحملات التسويقية وإدارة العروض'],
    'reports.php' => ['title' => 'التقارير (Reports)', 'subtitle' => 'تقارير شاملة لجميع عمليات الصالون'],
    'settings.php' => ['title' => 'الإعدادات (Settings)', 'subtitle' => 'إعدادات النظام والتخصيص'],
    'users.php' => ['title' => 'إدارة المستخدمين والصلاحيات', 'subtitle' => 'إدارة حسابات المستخدمين والصلاحيات'],
    'services.php' => ['title' => 'الخدمات', 'subtitle' => 'إدارة خدمات الصالون والأسعار'],
    'team.php' => ['title' => 'الفريق', 'subtitle' => 'إدارة فريق العمل والمختصين'],
    'reviews.php' => ['title' => 'التقييمات', 'subtitle' => 'تقييمات العملاء وآرائهم'],
    'content.php' => ['title' => 'محتوى الموقع', 'subtitle' => 'إدارة محتوى الموقع الإلكتروني']
];

echo "<h2>Mass Page Conversion to Standardized Layout</h2>\n";
echo "<pre>\n";

$converted_count = 0;
$skipped_count = 0;
$error_count = 0;

foreach ($pages_to_convert as $filename => $config) {
    $file_path = "../$filename";
    
    echo "Processing: $filename\n";
    
    if (!file_exists($file_path)) {
        echo "  ⚠️  File not found, creating template...\n";
        createTemplateFile($file_path, $config['title'], $config['subtitle'], $filename);
        $converted_count++;
        continue;
    }
    
    $content = file_get_contents($file_path);
    
    // Check if already using admin_layout
    if (strpos($content, "include 'includes/admin_layout.php'") !== false) {
        echo "  ✓ Already standardized: $filename\n";
        $skipped_count++;
        continue;
    }
    
    try {
        // Create backup
        $backup_path = "../backups/{$filename}.backup." . date('Y-m-d-H-i-s');
        if (!is_dir('../backups')) {
            mkdir('../backups', 0755, true);
        }
        copy($file_path, $backup_path);
        
        // Convert to standardized layout
        $standardized_content = convertToStandardLayout($content, $config['title'], $config['subtitle'], $filename);
        
        // Write new content
        file_put_contents($file_path, $standardized_content);
        
        echo "  ✅ Converted: $filename\n";
        echo "  📁 Backup: backups/{$filename}.backup\n";
        $converted_count++;
        
    } catch (Exception $e) {
        echo "  ❌ Error converting $filename: " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "\n=== Conversion Summary ===\n";
echo "✅ Converted: $converted_count pages\n";
echo "⏭️  Skipped (already standardized): $skipped_count pages\n";
echo "❌ Errors: $error_count pages\n";
echo "\n🎯 All sidebar buttons now maintain consistent layout!\n";
echo "</pre>\n";

function createTemplateFile($file_path, $title, $subtitle, $filename) {
    $template = "<?php
require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
\$admin = getCurrentAdmin();

\$database = new Database();
\$db = \$database->getConnection();

// Layout configuration
\$page_title = '$title';
\$page_subtitle = '$subtitle';

// Handle form submissions
if (\$_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add form handling logic here
}

// Page-specific logic here

ob_start();
?>

<div class=\"bg-white rounded-xl shadow-lg p-6\">
    <h2 class=\"text-xl font-bold text-mai-dark-brown mb-4\">$title</h2>
    <p class=\"text-gray-600 mb-6\">$subtitle</p>
    
    <!-- Page content goes here -->
    <div class=\"text-center py-12\">
        <i class=\"fas fa-cog text-6xl text-gray-300 mb-4\"></i>
        <h3 class=\"text-lg font-medium text-gray-500 mb-2\">قيد التطوير</h3>
        <p class=\"text-gray-400\">هذه الصفحة قيد التطوير وستكون متاحة قريباً</p>
    </div>
</div>

<?php
\$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>";
    
    file_put_contents($file_path, $template);
}

function convertToStandardLayout($original_content, $title, $subtitle, $filename) {
    // Extract PHP logic from the beginning
    $php_logic = '';
    if (preg_match('/^(<\?php.*?)\s*<!DOCTYPE/s', $original_content, $matches)) {
        $php_logic = $matches[1] . "\n\n";
    } elseif (preg_match('/^(<\?php.*?)(?=<[^?])/s', $original_content, $matches)) {
        $php_logic = $matches[1] . "\n\n";
    }
    
    // Extract main content (remove HTML structure)
    $main_content = extractMainContentFromPage($original_content);
    
    // Build standardized page
    $standardized = $php_logic;
    $standardized .= "// Layout configuration\n";
    $standardized .= "\$page_title = '$title';\n";
    $standardized .= "\$page_subtitle = '$subtitle';\n\n";
    
    // Extract and preserve any additional styles or scripts
    $additional_styles = extractStylesFromContent($original_content);
    if ($additional_styles) {
        $standardized .= "\$additional_styles = '" . addslashes($additional_styles) . "';\n\n";
    }
    
    $additional_scripts = extractScriptsFromContent($original_content);
    if ($additional_scripts) {
        $standardized .= "\$additional_head_content = '" . addslashes($additional_scripts) . "';\n\n";
    }
    
    // Handle success/error messages
    $standardized .= "if (isset(\$success)) {\n";
    $standardized .= "    \$success_message = \$success;\n";
    $standardized .= "}\n";
    $standardized .= "if (isset(\$error)) {\n";
    $standardized .= "    \$error_message = \$error;\n";
    $standardized .= "}\n\n";
    
    $standardized .= "ob_start();\n";
    $standardized .= "?>\n\n";
    $standardized .= $main_content;
    $standardized .= "\n\n<?php\n";
    $standardized .= "\$page_content = ob_get_clean();\n";
    $standardized .= "include 'includes/admin_layout.php';\n";
    $standardized .= "?>";
    
    return $standardized;
}

function extractMainContentFromPage($content) {
    // Remove HTML document structure
    $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
    $content = preg_replace('/<html[^>]*>/i', '', $content);
    $content = preg_replace('/<\/html>/i', '', $content);
    $content = preg_replace('/<head>.*?<\/head>/s', '', $content);
    $content = preg_replace('/<body[^>]*>/i', '', $content);
    $content = preg_replace('/<\/body>/i', '', $content);
    
    // Remove navigation elements
    $content = preg_replace('/<nav[^>]*>.*?<\/nav>/s', '', $content);
    $content = preg_replace('/\<\?php\s+include\s+[\'"]includes\/sidebar\.php[\'"];\s+\?\>/', '', $content);
    
    // Remove main wrapper divs but keep inner content
    $content = preg_replace('/<div[^>]*class="[^"]*flex[^"]*"[^>]*>/', '', $content);
    $content = preg_replace('/<div[^>]*class="[^"]*main[^"]*"[^>]*>/', '', $content);
    
    // Clean up PHP tags and extra whitespace
    $content = preg_replace('/\s*<\?php[^?]*\?>\s*/', '', $content);
    $content = trim($content);
    
    return $content;
}

function extractStylesFromContent($content) {
    if (preg_match('/<style[^>]*>(.*?)<\/style>/s', $content, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function extractScriptsFromContent($content) {
    $scripts = '';
    if (preg_match_all('/<script[^>]*src="([^"]+)"[^>]*><\/script>/', $content, $matches)) {
        foreach ($matches[0] as $script) {
            $scripts .= $script . "\n";
        }
    }
    return trim($scripts);
}
?>
