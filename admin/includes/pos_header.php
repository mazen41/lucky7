<?php
/**
 * MAI Salon - POS System Header Component
 * Responsive header for the Point of Sale system
 */
?>
<!-- POS Header -->
<div class="bg-white shadow-sm rounded-lg mb-4">
    <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
        <div class="flex items-center mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-mai-dark-brown">نقطة البيع (POS)</h1>
            <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full mr-2">نشط</span>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <!-- Shift Status -->
            <?php if (isset($shift_active) && $shift_active): ?>
            <div class="bg-green-100 text-green-800 px-3 py-1 rounded-lg text-sm flex items-center">
                <i class="fas fa-clock ml-1"></i>
                <span>الوردية مفتوحة</span>
            </div>
            <?php else: ?>
            <div class="bg-red-100 text-red-800 px-3 py-1 rounded-lg text-sm flex items-center">
                <i class="fas fa-clock ml-1"></i>
                <span>الوردية مغلقة</span>
            </div>
            <?php endif; ?>
            
            <!-- POS Actions -->
            <div class="flex gap-2">
                <button id="open-shift-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm flex items-center transition-colors">
                    <i class="fas fa-door-open ml-1"></i>
                    <span class="hidden sm:inline">فتح وردية</span>
                </button>
                
                <button id="close-shift-btn" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1 rounded-lg text-sm flex items-center transition-colors">
                    <i class="fas fa-door-closed ml-1"></i>
                    <span class="hidden sm:inline">إغلاق وردية</span>
                </button>
                
                <button id="reports-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded-lg text-sm flex items-center transition-colors">
                    <i class="fas fa-chart-bar ml-1"></i>
                    <span class="hidden sm:inline">التقارير</span>
                </button>
            </div>
        </div>
    </div>
</div>
