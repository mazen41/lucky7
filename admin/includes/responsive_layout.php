<?php
/**
 * MAI Salon - Responsive Layout Template
 * This template provides a consistent responsive layout for all admin pages
 * with proper mobile support and RTL layout for Arabic.
 * 
 * Usage:
 * 1. Set $page_title variable before including this file
 * 2. Put your page content in a variable called $content
 * 3. Include this file at the end of your PHP file
 */

// Default page title if not set
if (!isset($page_title)) {
    $page_title = 'لوحة التحكم';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - صالون MAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        body { 
            font-family: 'Tajawal', sans-serif; 
        }
        
        /* Custom scrollbar for sidebar */
        #sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        #sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        #sidebar::-webkit-scrollbar-thumb {
            background: #d1d1d1;
            border-radius: 3px;
        }
        
        #sidebar::-webkit-scrollbar-thumb:hover {
            background: #c1c1c1;
        }
        
        /* Responsive layout adjustments */
        @media (min-width: 1024px) {
            .main-content {
                 CHANGE: Reduced margin to 12rem to match the new smaller sidebar width 
                margin-right: 12rem; 
            }
        }
        
        /* Print styles */
        @media print {
            #sidebar, #mobile-menu-button, #mobile-overlay {
                display: none !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="font-arabic bg-gray-100">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-lg lg:hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl font-bold text-mai-brown">MAI</span>
                        <span class="text-sm text-gray-600 mr-2">لوحة التحكم</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse">
                    <a href="../index.php" target="_blank" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-external-link-alt ml-1"></i>
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- CHANGE: Reduced sidebar width from w-56 to w-48 (12rem) -->
    <div id="sidebar" class="fixed top-0 right-0 h-full z-30 hidden lg:block w-48">
        <?php include_once 'includes/responsive_sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <main class="main-content p-4 lg:p-8 pb-24">
        <?php echo $content; ?>
    </main>

    <!-- Footer -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 text-center text-gray-500 text-sm lg:hidden">
        <p>© <?php echo date('Y'); ?> صالون MAI - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>
<?php
// End of file
?>