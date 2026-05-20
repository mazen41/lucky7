<?php
/**
 * Lu7cky Coffee - Enhanced Responsive Base Layout
 * This layout provides a consistent navbar and sidebar across all admin pages
 * with the new light, modern theme.
 */

// Get current admin info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_admin = [
    'username' => $_SESSION['admin_username'] ?? 'المدير',
    'role' => $_SESSION['admin_role'] ?? 'مدير',
    'avatar' => $_SESSION['admin_avatar'] ?? null
];

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'لوحة التحكم'; ?> - Lu7cky</title>
    
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- FullCalendar (Optional) -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/ar.global.min.js"></script>
    
    <!-- Tailwind Config with the Corrected Lu7cky Palette -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'arabic': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        'lucky-offwhite': '#FAF3EB',
                        'lucky-teal': '#38C296',
                        // Neutral colors for text and borders
                        'lucky-dark': '#334155', // A dark slate for text
                        'lucky-medium': '#64748b',
                        'lucky-light': '#cbd5e1',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { 
            font-family: 'Tajawal', sans-serif; 
            background-color: #FAF3EB; /* Off-white background */
        }
        
        /* Custom scrollbar with Teal Green */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #38C296; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #2c9b76; }
        
        /* Sidebar animations */
        .sidebar-enter { animation: slideInRight 0.3s ease-out; }
        .sidebar-exit { animation: slideOutRight 0.3s ease-in; }
        @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
        @keyframes slideOutRight { from { transform: translateX(0); } to { transform: translateX(100%); } }
        
        /* Navbar shadow */
        .navbar-shadow { box-shadow: 0 4px 12px -1px rgba(0, 0, 0, 0.05); }
        
        /* Responsive adjustments */
        @media (min-width: 1024px) {
            .main-content { margin-right: 16rem; /* Sidebar width */ }
        }
        
        /* Toast notifications */
        .toast { transform: translateX(100%); transition: transform 0.3s ease-in-out; }
        .toast.show { transform: translateX(0); }

        /* Style for active navigation links */
        .nav-link.active {
            background-color: #38C296; /* Teal Green */
            color: white;
            font-weight: 700;
        }
        .nav-link.active i {
            color: white !important;
        }
    </style>
</head>
<body class="font-arabic bg-lucky-offwhite text-lucky-dark" x-data="{ sidebarOpen: false, userMenuOpen: false }">

    <!-- Top Navigation Bar -->
    <header class="fixed top-0 left-0 right-0 z-40 bg-white navbar-shadow border-b border-lucky-light/50">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Right side (RTL): Mobile menu button & Page Title -->
                <div class="flex items-center">
                    <div class="lg:hidden">
                        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg text-lucky-medium hover:text-lucky-teal hover:bg-lucky-offwhite/80 transition-colors duration-200">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                     <div class="mr-3 hidden sm:block">
                        <h1 class="text-lg font-bold text-lucky-dark"><?php echo $page_title ?? 'لوحة التحكم'; ?></h1>
                    </div>
                </div>
                
                <!-- Left side (RTL): User menu and actions -->
                <div class="flex items-center space-x-3 space-x-reverse">
                    
                    <div class="hidden md:flex items-center space-x-2 space-x-reverse">
                        <a href="../index.php" target="_blank" title="عرض الموقع" class="w-10 h-10 flex items-center justify-center rounded-full text-lucky-medium hover:text-lucky-teal hover:bg-lucky-offwhite/80 transition-colors duration-200">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <button title="وضع ملء الشاشة" onclick="toggleFullscreen()" class="w-10 h-10 flex items-center justify-center rounded-full text-lucky-medium hover:text-lucky-teal hover:bg-lucky-offwhite/80 transition-colors duration-200">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 space-x-reverse p-2 rounded-full hover:bg-lucky-offwhite/80 transition-colors duration-200">
                            <div class="w-9 h-9 bg-lucky-teal rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium"><?php echo mb_substr($current_admin['username'], 0, 1); ?></span>
                            </div>
                            <div class="hidden md:block text-right">
                                <p class="text-sm font-medium text-lucky-dark"><?php echo htmlspecialchars($current_admin['username']); ?></p>
                                <p class="text-xs text-lucky-medium"><?php echo htmlspecialchars($current_admin['role']); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-lucky-medium transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        
                        <div x-show="open" x-transition @click.away="open = false" class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                            <a href="user_profile.php" class="flex items-center px-4 py-2 text-sm text-lucky-dark hover:bg-lucky-offwhite transition-colors">
                                <i class="fas fa-user ml-3 w-4 text-lucky-medium"></i> الملف الشخصي
                            </a>
                            <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-lucky-dark hover:bg-lucky-offwhite transition-colors">
                                <i class="fas fa-cog ml-3 w-4 text-lucky-medium"></i> الإعدادات
                            </a>
                            <div class="border-t border-gray-200 my-2"></div>
                            <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <i class="fas fa-sign-out-alt ml-3 w-4"></i> تسجيل الخروج
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar (includes sidebar_fixed.php) -->
    <aside class="fixed inset-y-0 right-0 z-50 w-64 bg-white shadow-xl transform transition-transform duration-300 ease-in-out lg:translate-x-0"
           :class="{ 'translate-x-0': sidebarOpen, 'translate-x-full': !sidebarOpen }"
           x-cloak>
        <?php include __DIR__ . '/../includes/sidebar_fixed.php'; ?>
    </aside>

    <!-- Mobile Overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden" x-cloak></div>

    <!-- Main Content Area -->
    <main class="main-content pt-16 min-h-screen">
        <div class="p-4 lg:p-8">
            <?php echo $page_content ?? ''; ?>
        </div>
    </main>

    <!-- Toast & Loading Placeholders -->
    <div id="toast-container" class="fixed top-20 left-4 z-[100] space-y-2"></div>
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-40 z-[101] hidden items-center justify-center">
        <div class="bg-white rounded-lg p-6 flex flex-col items-center shadow-lg">
            <div class="w-12 h-12 border-4 border-lucky-teal border-t-transparent rounded-full animate-spin mb-4"></div>
            <p class="text-lucky-dark font-medium">جاري التحميل...</p>
        </div>
    </div>

    <!-- Global JavaScript -->
    <script>
        window.MAI = {
            showToast: function(message, type = 'info', duration = 3000) {
                const container = document.getElementById('toast-container');
                const toastId = 'toast-' + Date.now();
                const typeClasses = { success: 'bg-lucky-teal', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
                const toast = document.createElement('div');
                toast.id = toastId;
                toast.className = `toast flex items-center p-4 rounded-lg shadow-lg text-white ${typeClasses[type] || typeClasses.info} max-w-sm`;
                toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} ml-3"></i><span class="flex-1">${message}</span><button onclick="MAI.hideToast('${toastId}')" class="mr-2 hover:opacity-75"><i class="fas fa-times"></i></button>`;
                container.appendChild(toast);
                setTimeout(() => toast.classList.add('show'), 100);
                setTimeout(() => MAI.hideToast(toastId), duration);
            },
            hideToast: function(toastId) {
                const toast = document.getElementById(toastId);
                if (toast) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }
            },
            showLoading: function() { document.getElementById('loading-overlay').classList.add('flex'); document.getElementById('loading-overlay').classList.remove('hidden'); },
            hideLoading: function() { document.getElementById('loading-overlay').classList.add('hidden'); document.getElementById('loading-overlay').classList.remove('flex'); },
            formatNumber: function(number) { return new Intl.NumberFormat('ar-SA').format(number); },
            formatCurrency: function(amount) { return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(amount); }
        };
        window.showToast = MAI.showToast;
        window.hideToast = MAI.hideToast;
        function toggleFullscreen() {
            if (!document.fullscreenElement) { document.documentElement.requestFullscreen(); } 
            else { document.exitFullscreen(); }
        }
    </script>
    
    <?php if (isset($additional_js)): ?><?php echo $additional_js; ?><?php endif; ?>
    <?php if (isset($additional_css)): ?><style><?php echo $additional_css; ?></style><?php endif; ?>

</body>
</html>