<?php
/**
 * REVISED Sidebar - Lu7cky
 * This is the main shell for the admin sidebar with the new light and clean theme.
 */

// This variable is necessary for the included navigation file to highlight the active link.
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Shell -->
<div class="h-full flex flex-col bg-white text-lucky-dark">

    <!-- New Sidebar Header for Lu7cky Coffee -->
    <div class="p-4 border-b border-lucky-light/60">
        <a href="dashboard.php" class="flex items-center justify-center space-x-3 space-x-reverse h-16">
            <!-- LOGO PLACEHOLDER -->
            <div class="w-10 h-10 rounded-lg flex items-center justify-center">
                <!-- <i class="fas fa-mug-hot text-white text-xl"></i> -->
                 <img src="./assets/pattern copy.png" alt="">
            </div>
   
        </a>
    </div>

    <!-- Navigation Items Container -->
    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        <?php
            // This includes your navigation items file. All links and logic are preserved.
            // The active link styling is handled by the 'active' class defined in responsive_base.php
            // You should ensure links inside this file have the class 'nav-link'.
            include __DIR__ . '/navigation_items.php';
        ?>
    </nav>
    
    <!-- Optional: Sidebar Footer -->
    <div class="p-4 border-t border-lucky-light/60 mt-auto">
        <p class="text-center text-xs text-lucky-medium">© <?php echo date("Y"); ?> Lu7cky</p>
    </div>

</div>