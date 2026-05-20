<?php
/**
 * MAI Salon - Responsive Sidebar Component
 * This component provides a fully responsive sidebar that works across all screen sizes
 * with proper mobile support and RTL layout for Arabic.
 */

// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Menu Button -->
<div class="lg:hidden fixed top-4 right-4 z-50">
    <button id="mobile-menu-button" class="bg-white p-2 rounded-lg shadow-lg text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-mai-brown">
        <i class="fas fa-bars text-xl"></i>
    </button>
</div>

<!-- Mobile Overlay -->
<div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="fixed right-0 top-0 h-full w-28 bg-white shadow-lg z-50 overflow-y-auto transform translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <!-- Logo -->
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-mai-cream rounded-lg flex items-center justify-center">
                <span class="text-mai-brown font-bold text-xl">MAI</span>
            </div>
            <div class="mr-3">
                <h2 class="text-lg font-bold text-mai-dark-brown">صالون MAI</h2>
                <p class="text-xs text-gray-500">لوحة التحكم</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="p-6">
        <div class="space-y-2">
            <!-- Main Navigation -->
            <a href="dashboard_new.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'dashboard_new.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-tachometer-alt ml-3 w-5"></i>
                <span class="font-medium">لوحة التحكم</span>
            </a>
            <a href="pos.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'pos.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-cash-register ml-3 w-5"></i>
                <span class="font-medium">نقطة البيع (POS)</span>
            </a>
            <a href="bookings.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'bookings.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-calendar-alt ml-3 w-5"></i>
                <span class="font-medium">التقويم والحجوزات</span>
                <?php if (isset($stats['bookings']) && $stats['bookings'] > 0): ?>
                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full mr-2"><?php echo $stats['bookings']; ?></span>
                <?php endif; ?>
            </a>
            <a href="clients.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'clients.php' || $current_page == 'customer_segments.php' || $current_page == 'health_forms.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-users ml-3 w-5"></i>
                <span class="font-medium">إدارة العملاء (CRM)</span>
            </a>
            <a href="inventory.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'inventory.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-boxes ml-3 w-5"></i>
                <span class="font-medium">المخزون والمنتجات</span>
            </a>
            <a href="hr_dashboard.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'hr_dashboard.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-user-tie ml-3 w-5"></i>
                <span class="font-medium">الموارد البشرية (HR)</span>
            </a>
            <a href="loyalty.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'loyalty.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-award ml-3 w-5"></i>
                <span class="font-medium">نظام الولاء (Loyalty)</span>
            </a>
            <a href="marketing.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'marketing.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-bullhorn ml-3 w-5"></i>
                <span class="font-medium">الحملات التسويقية</span>
            </a>
            <a href="reports.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'reports.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-chart-bar ml-3 w-5"></i>
                <span class="font-medium">التقارير (Reports)</span>
            </a>
            <a href="settings.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'settings.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-cog ml-3 w-5"></i>
                <span class="font-medium">الإعدادات (Settings)</span>
            </a>
            <a href="users.php" class="flex items-center px-4 py-3 <?php echo $current_page == 'users.php' ? 'text-mai-brown bg-mai-cream rounded-lg border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown rounded-lg transition-all duration-200'; ?>">
                <i class="fas fa-user-shield ml-3 w-5"></i>
                <span class="font-medium">إدارة المستخدمين</span>
            </a>
            
            <!-- Legacy Links (for backward compatibility) -->
            <div class="border-t border-gray-200 mt-4 pt-4">
                <p class="text-xs text-gray-500 mb-2 px-4">إدارة المحتوى</p>
                <a href="content.php" class="flex items-center px-4 py-2 <?php echo $current_page == 'content.php' ? 'text-mai-brown bg-mai-cream rounded-lg' : 'text-gray-600 hover:bg-gray-100 rounded-lg'; ?> text-sm">
                    <i class="fas fa-edit ml-3 w-4"></i>
                    <span>إدارة المحتوى</span>
                </a>
                <a href="services.php" class="flex items-center px-4 py-2 <?php echo $current_page == 'services.php' ? 'text-mai-brown bg-mai-cream rounded-lg' : 'text-gray-600 hover:bg-gray-100 rounded-lg'; ?> text-sm">
                    <i class="fas fa-cut ml-3 w-4"></i>
                    <span>الخدمات</span>
                </a>
                <a href="team.php" class="flex items-center px-4 py-2 <?php echo $current_page == 'team.php' ? 'text-mai-brown bg-mai-cream rounded-lg' : 'text-gray-600 hover:bg-gray-100 rounded-lg'; ?> text-sm">
                    <i class="fas fa-users ml-3 w-4"></i>
                    <span>الفريق</span>
                </a>
                <a href="reviews.php" class="flex items-center px-4 py-2 <?php echo $current_page == 'reviews.php' ? 'text-mai-brown bg-mai-cream rounded-lg' : 'text-gray-600 hover:bg-gray-100 rounded-lg'; ?> text-sm">
                    <i class="fas fa-star ml-3 w-4"></i>
                    <span>التقييمات</span>
                    <?php if (isset($stats['reviews']) && $stats['reviews'] > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full mr-2"><?php echo $stats['reviews']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>
</div>

<!-- JavaScript for mobile menu -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    function toggleSidebar() {
        sidebar.classList.toggle('translate-x-full');
        overlay.classList.toggle('hidden');
        
        // Update button icon
        const icon = mobileMenuButton.querySelector('i');
        if (sidebar.classList.contains('translate-x-full')) {
            icon.className = 'fas fa-bars text-xl';
        } else {
            icon.className = 'fas fa-times text-xl';
        }
    }
    
    // Toggle sidebar on button click
    mobileMenuButton.addEventListener('click', toggleSidebar);
    
    // Close sidebar when clicking overlay
    overlay.addEventListener('click', toggleSidebar);
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !sidebar.classList.contains('translate-x-full')) {
            toggleSidebar();
        }
    });
    
    // Close sidebar when clicking on menu items (mobile only)
    if (window.innerWidth < 1024) {
        const menuLinks = sidebar.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (!sidebar.classList.contains('translate-x-full')) {
                    toggleSidebar();
                }
            });
        });
    }
});
</script>
