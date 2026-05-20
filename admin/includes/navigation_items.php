<?php
/**
 * Navigation Items - MAI Salon
 *
 * This file generates the sidebar navigation menu.
 * Visibility of navigation items is controlled by the hasPermission() function,
 * which is loaded from 'users_functions.php'.
 */

// Include the user functions file which contains the hasPermission() function.
// Make sure the path is correct relative to this file's location.
require_once __DIR__ . '/../users_functions.php';

// Get the name of the currently executing script (e.g., 'dashboard.php')
$current_page = basename($_SERVER['PHP_SELF']);

// Navigation items with their required view permissions
$nav_items = [
    [ 
        'id' => 'dashboard', 
        'title' => 'لوحة التحكم', 
        'icon' => 'fas fa-chart-line', 
        'url' => 'dashboard.php', 
        'pages' => ['dashboard.php', 'dashboard_new.php'],
        'permission_key' => 'dashboard.view'
    ],
    [ 
        'id' => 'pos', 
        'title' => 'نقطة البيع', 
        'icon' => 'fas fa-shopping-cart', 
        'url' => 'pos.php', 
        'pages' => ['pos.php'],
        'permission_key' => 'pos.view'
    ],
    [ 
        'id' => 'invoices', 
        'title' => 'الفواتير', 
        'icon' => 'fas fa-file-invoice-dollar', 
        'url' => 'invoices.php', 
        'pages' => ['invoices.php'],
        'permission_key' => 'invoices.view'
    ],
    [ 
        'id' => 'clients', 
        'title' => 'العملاء', 
        'icon' => 'fas fa-users', 
        'url' => 'clients.php', 
        'pages' => ['clients.php', 'customers.php', 'customer_segments.php', 'health_forms.php'],
        'permission_key' => 'clients.view'
    ],
    [ 
        'id' => 'loyalty', 
        'title' => 'الولاء', 
        'icon' => 'fas fa-award', 
        'url' => 'loyalty.php', 
        'pages' => ['loyalty.php', 'points.php', 'rewards.php'],
        'permission_key' => 'loyalty.view'
    ],
    [ 
        'id' => 'reports', 
        'title' => 'التقارير', 
        'icon' => 'fas fa-chart-bar', 
        'url' => 'reports.php', 
        'pages' => ['reports.php', 'analytics.php', 'statistics.php'],
        'permission_key' => 'reports.view'
    ],
    [ 
        'id' => 'services', 
        'title' => 'الخدمات', 
        'icon' => 'fas fa-cut', 
        'url' => 'services.php', 
        'pages' => ['services.php', 'service_categories.php'],
        'permission_key' => 'services.view'
    ],
    [ 
        'id' => 'inventory', 
        'title' => 'المخزون', 
        'icon' => 'fas fa-boxes', 
        'url' => 'inventory.php', 
        'pages' => ['inventory.php', 'products.php', 'stock.php'],
        'permission_key' => 'inventory.view'
    ],
  
    [ 
        'id' => 'expenses', 
        'title' => 'المصاريف', 
        'icon' => 'fas fa-money-bill-wave', 
        'url' => 'expenses.php', 
        'pages' => ['expenses.php'],
        'permission_key' => 'expenses.view'
    ],
    
    // <<< NEWLY ADDED CODE BLOCK START >>>
    [ 
        'id' => 'accounting', 
        'title' => 'المركز المالي', 
        'icon' => 'fas fa-calculator', // Icon for accounting
        'url' => 'controllers/AccountingController.php', // URL points to the new controller
        'pages' => ['AccountingController.php'], // This makes the link active when on the controller page
        'permission_key' => 'accounting.dashboard.view' // The main permission to see the hub
    ],
    // <<< NEWLY ADDED CODE BLOCK END >>>
    
    [ 
        'id' => 'settings', 
        'title' => 'الإعـدادات', 
        'icon' => 'fas fa-cog', 
        'url' => 'settings.php', 
        'pages' => ['settings.php', 'preferences.php', 'configuration.php'],
        'permission_key' => 'settings.view'
    ],

];

function isCurrentPage($pages, $current_page) {
    return in_array($current_page, $pages ?? []);
}
?>

<style>
@font-face {
  font-family: 'SalonCustomFont';
  src: url('../../fonts/AGCBold.ttf') format('truetype');
  font-weight: normal;
  font-style: normal;
}

.salon-font {
  font-family: 'SalonCustomFont', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
}
</style>

<div class="h-full flex flex-col salon-font">
    <nav class="flex-grow">
        <ul class="space-y-3">
            <?php foreach ($nav_items as $item): ?>
                <?php // Check if the user has the required permission for this navigation item ?>
                <?php if (hasPermission($item['permission_key'])): ?>
                    <?php
                        // Determine if the current item is active
                        $is_active = isCurrentPage($item['pages'], $current_page);
                        $link_classes = $is_active
                            ? 'bg-[#824820] text-white' // Active state
                            : 'text-gray-700 hover:bg-gray-100'; // Default state
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($item['url']); ?>"
                           class="flex items-center p-3 rounded-xl transition-colors duration-200 <?= $link_classes; ?>">
                            <i class="<?= htmlspecialchars($item['icon']); ?> w-6 text-center text-lg"></i>
                            <span class="mr-4 text-base font-medium"><?= htmlspecialchars($item['title']); ?></span>
                        </a>
                    </li>
                <?php endif; // End of permission check ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="mt-auto pt-4 border-t" style="border-color: #EAD8C8;">
        <a href="logout.php"
           class="flex items-center p-3 rounded-lg text-[#D32F2F] hover:bg-red-50 transition-colors duration-200">
            <i class="fas fa-sign-out-alt w-6 text-center text-lg"></i>
            <span class="mr-4 text-base font-bold">تسجيل الخروج</span>
        </a>
    </div>
</div>