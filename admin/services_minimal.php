<?php
// Minimal error checking version
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'auth.php';
    require_once '../config/database.php';

    checkAdminAuth();
    $admin = getCurrentAdmin();

    $database = new Database();
    $db = $database->getConnection();

    // Simple category fetch with error handling
    $categories = [];
    try {
        $stmt = $db->prepare("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name_ar");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "Categories error: " . $e->getMessage();
    }

    // Simple services fetch with error handling
    $services = [];
    try {
        $stmt = $db->prepare("
            SELECT s.*, c.name_ar as category_name_ar, c.color as category_color
            FROM services_new s
            LEFT JOIN service_categories c ON s.category_id = c.id
            ORDER BY c.sort_order, s.sort_order, s.name_ar
        ");
        $stmt->execute();
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "Services error: " . $e->getMessage();
    }

} catch (Exception $e) {
    die("Fatal error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الخدمات - صالون MAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    </style>
</head>
<body class="font-arabic bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed top-0 left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold text-mai-brown">MAI</span>
                        <span class="text-sm text-gray-600 mr-2">لوحة التحكم</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></span>
                    <a href="../index.php" target="_blank" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-external-link-alt ml-1"></i>
                        عرض الموقع
                    </a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-sign-out-alt ml-1"></i>
                        تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg mt-16">
            <div class="p-6">
                <nav class="space-y-2">
                    <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg">
                        <i class="fas fa-tachometer-alt ml-3"></i>
                        لوحة التحكم
                    </a>
                    <a href="services.php" class="flex items-center px-4 py-2 bg-mai-brown text-white rounded-lg">
                        <i class="fas fa-cut ml-3"></i>
                        إدارة الخدمات
                    </a>
                    <a href="settings.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg">
                        <i class="fas fa-cog ml-3"></i>
                        الإعدادات
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8 mt-16">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-mai-dark-brown">إدارة الخدمات</h1>
                <p class="text-gray-600 mt-2">إضافة وتعديل خدمات الصالون</p>
            </div>

            <!-- Debug Info -->
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <strong>Debug Info:</strong>
                Categories: <?php echo count($categories); ?> | 
                Services: <?php echo count($services); ?>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-white rounded-xl shadow-lg mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 space-x-reverse px-6">
                        <button onclick="switchTab('services')" id="services-tab" 
                                class="tab-button py-4 px-1 border-b-2 font-medium text-sm focus:outline-none border-mai-brown text-mai-brown">
                            <i class="fas fa-cut ml-2"></i>
                            الخدمات
                        </button>
                        <button onclick="switchTab('categories')" id="categories-tab" 
                                class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm focus:outline-none">
                            <i class="fas fa-tags ml-2"></i>
                            التصنيفات
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Services Tab Content -->
            <div id="services-content" class="tab-content">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-mai-dark-brown mb-4">قائمة الخدمات</h2>
                    <div id="services-list">
                        <?php if (empty($services)): ?>
                            <p class="text-gray-500 text-center py-8">لا توجد خدمات مضافة حتى الآن</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($services as $service): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h4 class="text-lg font-bold text-mai-dark-brown mb-2">
                                        <?php echo htmlspecialchars($service['name_ar'] ?? 'خدمة'); ?>
                                    </h4>
                                    <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($service['description_ar'] ?? ''); ?></p>
                                    <div class="flex items-center space-x-4 space-x-reverse text-sm text-mai-brown">
                                        <span><i class="fas fa-money-bill-wave ml-1"></i> <?php echo $service['fixed_price'] ?? '0'; ?> ريال</span>
                                        <span><i class="fas fa-clock ml-1"></i> <?php echo $service['duration_minutes'] ?? '0'; ?> دقيقة</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Categories Tab Content -->
            <div id="categories-content" class="tab-content" style="display: none;">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-mai-dark-brown mb-4">قائمة التصنيفات</h2>
                    <div id="categories-list">
                        <?php if (empty($categories)): ?>
                            <p class="text-gray-500 text-center py-8">لا توجد تصنيفات مضافة حتى الآن</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($categories as $category): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h4 class="text-lg font-bold text-mai-dark-brown mb-2 flex items-center">
                                        <span class="w-4 h-4 rounded-full mr-2" style="background-color: <?php echo $category['color'] ?? '#8B4513'; ?>"></span>
                                        <?php echo htmlspecialchars($category['name_ar'] ?? 'تصنيف'); ?>
                                    </h4>
                                    <?php if (!empty($category['description'])): ?>
                                    <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($category['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            console.log('Switching to tab:', tabName);
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-mai-brown', 'text-mai-brown');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            const targetContent = document.getElementById(tabName + '-content');
            if (targetContent) {
                targetContent.style.display = 'block';
                console.log('Showed content for:', tabName);
            }
            
            // Add active class to selected tab button
            const activeButton = document.getElementById(tabName + '-tab');
            if (activeButton) {
                activeButton.classList.remove('border-transparent', 'text-gray-500');
                activeButton.classList.add('border-mai-brown', 'text-mai-brown');
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded successfully');
        });
    </script>
</body>
</html>
