<?php
// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define hasPermission function with proper error handling
if (!function_exists('hasPermission')) {
    function hasPermission($permissionKey) {
        // For now, always return true to avoid database connection issues
        // This can be enhanced later when RBAC is fully implemented
        return true;
    }
}
?>

<!-- Sidebar -->
<div class="w-64 bg-white shadow-lg h-full flex-shrink-0 overflow-y-auto">
    <div class="p-6">
        <!-- Logo and Header -->
        <div class="flex items-center mb-8">
            <div class="w-10 h-10 bg-mai-brown rounded-lg flex items-center justify-center">
                <i class="fas fa-spa text-white text-lg"></i>
            </div>
            <div class="mr-3">
                <h1 class="text-lg font-bold text-mai-brown">MAI Salon</h1>
                <p class="text-xs text-gray-500">نظام إدارة الصالون</p>
            </div>
        </div>
        
        <nav class="space-y-2">
            <?php if (hasPermission('dashboard.view')): ?>
            <a href="dashboard.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'dashboard.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-tachometer-alt ml-3 w-5"></i>
                <span class="font-medium">لوحة التحكم</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('pos.view')): ?>
            <a href="pos.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'pos.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-cash-register ml-3 w-5"></i>
                <span class="font-medium">نقطة البيع (POS)</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('bookings.view')): ?>
            <a href="bookings.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'bookings.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-calendar-alt ml-3 w-5"></i>
                <span class="font-medium">التقويم والحجوزات</span>
                <?php if (isset($stats['bookings']) && $stats['bookings'] > 0): ?>
                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full mr-2"><?php echo $stats['bookings']; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('clients.view')): ?>
            <a href="clients.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'clients.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-users ml-3 w-5"></i>
                <span class="font-medium">إدارة العملاء (CRM)</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('clients.view')): ?>
            <a href="customer_segments.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'customer_segments.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-layer-group ml-3 w-5"></i>
                <span class="font-medium">شرائح العملاء</span>
            </a>
            <?php endif; ?>
            
            
            <!-- Health Forms Section -->
            <?php if (hasPermission('clients.view')): ?>
            <a href="health_forms.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'health_forms.php' || $current_page == 'health_form_builder.php' || $current_page == 'health_form_responses.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-file-medical ml-3 w-5"></i>
                <span class="font-medium">النماذج الصحية</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('inventory.view')): ?>
            <a href="inventory-admin.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'inventory-admin.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-boxes ml-3 w-5"></i>
                <span class="font-medium">المخزون والمنتجات</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('hr.view')): ?>
            <a href="hr_dashboard.php" class="flex items-center px-4 py-3 <?php echo (in_array($current_page, ['hr_dashboard.php', 'hr_attendance.php', 'hr_performance.php', 'hr_requests.php'])) ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-users-cog ml-3 w-5"></i>
                <span class="font-medium">الموارد البشرية (HR)</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('loyalty.view')): ?>
            <a href="loyalty.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'loyalty.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-award ml-3 w-5"></i>
                <span class="font-medium">نظام الولاء (Loyalty)</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('marketing.view')): ?>
            <a href="marketing.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'marketing.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-bullhorn ml-3 w-5"></i>
                <span class="font-medium">الحملات التسويقية</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('reports.view')): ?>
            <a href="reports.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'reports.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-chart-bar ml-3 w-5"></i>
                <span class="font-medium">التقارير (Reports)</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('settings.view')): ?>
            <a href="settings.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'settings.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-cog ml-3 w-5"></i>
                <span class="font-medium">الإعدادات</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('users.view')): ?>
            <a href="users.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'users.php') ? 'text-mai-brown bg-mai-cream border-r-4 border-mai-brown' : 'text-gray-700 hover:bg-mai-cream hover:text-mai-brown'; ?> rounded-lg transition-all duration-200">
                <i class="fas fa-user-shield ml-3 w-5"></i>
                <span class="font-medium">إدارة المستخدمين والصلاحيات</span>
            </a>
            <?php endif; ?>
            
            <!-- Frontend Management Section -->
            <div class="border-t border-gray-200 mt-4 pt-4">
                <p class="text-xs text-gray-500 mb-2 px-4">إدارة المحتوى والواجهة</p>
                
                <?php if (hasPermission('content.services')): ?>
                <a href="services.php" class="flex items-center px-4 py-2 <?php echo ($current_page == 'services.php') ? 'text-mai-brown bg-mai-cream' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg text-sm">
                    <i class="fas fa-cut ml-3 w-4"></i>
                    <span class="font-medium">الخدمات</span>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('content.team')): ?>
                <a href="team.php" class="flex items-center px-4 py-2 <?php echo ($current_page == 'team.php') ? 'text-mai-brown bg-mai-cream' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg text-sm">
                    <i class="fas fa-users ml-3 w-4"></i>
                    <span>الفريق</span>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('content.reviews')): ?>
                <a href="reviews.php" class="flex items-center px-4 py-2 <?php echo ($current_page == 'reviews.php') ? 'text-mai-brown bg-mai-cream' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg text-sm">
                    <i class="fas fa-star ml-3 w-4"></i>
                    <span>التقييمات</span>
                    <?php if (isset($stats['reviews']) && $stats['reviews'] > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full mr-2"><?php echo $stats['reviews']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <?php if (hasPermission('content.view')): ?>
                
                <a href="content.php" class="flex items-center px-4 py-2 <?php echo ($current_page == 'content.php') ? 'text-mai-brown bg-mai-cream' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg text-sm">
                    <i class="fas fa-edit ml-3 w-4"></i>
                    <span>محتوى الموقع</span>
                </a>
                
                
                
                
                <?php endif; ?>
            </div>
        </nav>
    </div>
</div>
