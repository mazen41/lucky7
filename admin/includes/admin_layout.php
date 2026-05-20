<?php
/**
 * Standard Admin Layout Template
 * Ensures consistent sidebar and topbar across all admin pages
 */

// Ensure admin authentication
if (!function_exists('checkAdminAuth')) {
    require_once __DIR__ . '/../auth.php';
}

if (!function_exists('getCurrentAdmin')) {
    function getCurrentAdmin() {
        return [
            'id' => $_SESSION['admin_id'] ?? 1,
            'username' => $_SESSION['admin_username'] ?? 'admin'
        ];
    }
}

checkAdminAuth();
$admin = getCurrentAdmin();

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'صالون MAI - لوحة التحكم'; ?></title>
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
        .sidebar-fixed {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 16rem;
            z-index: 40;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .content-with-sidebar {
            margin-right: 16rem;
            min-height: 100vh;
            width: calc(100% - 16rem);
        }
        .topbar-fixed {
            position: sticky;
            top: 0;
            z-index: 30;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        .main-content {
            flex: 1;
            overflow-x: auto;
        }
        <?php if (isset($additional_styles)): ?>
        <?php echo $additional_styles; ?>
        <?php endif; ?>
    </style>
    
    <?php if (isset($additional_head_content)): ?>
    <?php echo $additional_head_content; ?>
    <?php endif; ?>
</head>
<body class="font-arabic bg-gray-50">
    
    <!-- Fixed Sidebar -->
    <div class="sidebar-fixed">
        <?php include 'sidebar.php'; ?>
    </div>
    
    <!-- Main Content Area -->
    <div class="content-with-sidebar">
        <!-- Fixed Top Bar -->
        <div class="topbar-fixed">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-mai-dark-brown"><?php echo $page_title ?? 'لوحة التحكم'; ?></h1>
                    <?php if (isset($page_subtitle)): ?>
                    <span class="text-gray-600 mr-4"><?php echo $page_subtitle; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center gap-4">
                    <?php if (isset($page_actions)): ?>
                    <?php echo $page_actions; ?>
                    <?php endif; ?>
                    
                    <!-- User Info -->
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="fas fa-user-circle text-lg"></i>
                        <span>مرحباً، <?php echo htmlspecialchars($admin['username']); ?></span>
                    </div>
                    
                    <!-- Logout -->
                    <a href="logout.php" class="text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-sign-out-alt ml-1"></i>
                        تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="main-content p-6">
            <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Main Content Area -->
            <div class="w-full">
                <?php if (isset($page_content)): ?>
                <?php echo $page_content; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($additional_scripts)): ?>
    <?php echo $additional_scripts; ?>
    <?php endif; ?>
    
</body>
</html>
