<?php
/**
 * MAI Salon - Responsive Bookings System
 * Fully responsive appointment booking system with mobile support
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'auth.php';

// Check admin authentication
checkAdminAuth();

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get current date for calendar
$current_date = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

// Set page title and start output buffering for the responsive layout
$page_title = 'التقويم والحجوزات';
ob_start();
?>

<!-- Bookings System Container -->
<div class="bookings-container">
    <!-- Header with Actions -->
    <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-4 flex flex-col md:flex-row items-start md:items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <h1 class="text-xl font-bold text-mai-dark-brown">التقويم والحجوزات</h1>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <button id="new-booking-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-plus ml-1"></i>
                    <span>حجز جديد</span>
                </button>
                
                <div class="relative">
                    <button id="view-dropdown-btn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        <span>طريقة العرض</span>
                        <i class="fas fa-chevron-down mr-1 text-xs"></i>
                    </button>
                    <div id="view-dropdown-menu" class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 hidden">
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">عرض اليوم</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">عرض الأسبوع</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">عرض الشهر</a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">عرض القائمة</a>
                    </div>
                </div>
                
                <div class="flex items-center bg-gray-100 rounded-lg">
                    <button id="prev-date-btn" class="px-3 py-2 text-gray-700 hover:bg-gray-200 rounded-r-lg">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button id="today-btn" class="px-3 py-2 text-gray-700 hover:bg-gray-200">
                        <span>اليوم</span>
                    </button>
                    <button id="next-date-btn" class="px-3 py-2 text-gray-700 hover:bg-gray-200 rounded-l-lg">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Calendar and Bookings View -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Left Column (Mini Calendar & Filters) -->
        <div class="lg:col-span-1">
            <!-- Mini Calendar -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">التقويم</h2>
                </div>
                <div class="p-4">
                    <!-- Month Navigation -->
                    <div class="flex justify-between items-center mb-4">
                        <button class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <h3 class="font-medium"><?php echo date('F Y'); ?></h3>
                        <button class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                    
                    <!-- Calendar Grid -->
                    <div class="grid grid-cols-7 gap-1 text-center">
                        <!-- Days of Week -->
                        <div class="text-xs font-medium text-gray-500">أحد</div>
                        <div class="text-xs font-medium text-gray-500">إثن</div>
                        <div class="text-xs font-medium text-gray-500">ثلا</div>
                        <div class="text-xs font-medium text-gray-500">أرب</div>
                        <div class="text-xs font-medium text-gray-500">خمي</div>
                        <div class="text-xs font-medium text-gray-500">جمع</div>
                        <div class="text-xs font-medium text-gray-500">سبت</div>
                        
                        <!-- Calendar Days (would be dynamically generated) -->
                        <?php
                        // Get first day of month
                        $first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
                        $day_of_week = date('w', $first_day);
                        $days_in_month = date('t', $first_day);
                        
                        // Add empty cells for days before the 1st
                        for ($i = 0; $i < $day_of_week; $i++) {
                            echo '<div class="h-8 flex items-center justify-center text-gray-300"></div>';
                        }
                        
                        // Add days of month
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date = date('Y-m-d', mktime(0, 0, 0, $current_month, $day, $current_year));
                            $is_today = $date == date('Y-m-d');
                            $has_bookings = ($day % 3 == 0); // Placeholder logic
                            
                            $class = 'h-8 flex items-center justify-center text-sm rounded-full cursor-pointer ';
                            if ($is_today) {
                                $class .= 'bg-mai-brown text-white';
                            } elseif ($has_bookings) {
                                $class .= 'bg-mai-cream text-mai-brown';
                            } else {
                                $class .= 'hover:bg-gray-100';
                            }
                            
                            echo "<div class=\"{$class}\">{$day}</div>";
                        }
                        
                        // Add empty cells for days after the last day
                        $cells_used = $day_of_week + $days_in_month;
                        $cells_remaining = 42 - $cells_used; // 6 rows of 7 days
                        for ($i = 0; $i < $cells_remaining; $i++) {
                            echo '<div class="h-8 flex items-center justify-center text-gray-300"></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">الفلاتر</h2>
                </div>
                <div class="p-4">
                    <!-- Specialist Filter -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">المتخصص</label>
                        <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <option value="">الكل</option>
                            <option value="1">سارة محمد</option>
                            <option value="2">نورة العتيبي</option>
                            <option value="3">منى الشمري</option>
                        </select>
                    </div>
                    
                    <!-- Service Filter -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">الخدمة</label>
                        <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <option value="">الكل</option>
                            <option value="1">قص شعر</option>
                            <option value="2">صبغة شعر</option>
                            <option value="3">تصفيف شعر</option>
                            <option value="4">عناية بالبشرة</option>
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="status-pending" class="ml-2" checked>
                                <label for="status-pending" class="text-sm">معلق</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="status-confirmed" class="ml-2" checked>
                                <label for="status-confirmed" class="text-sm">مؤكد</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="status-completed" class="ml-2" checked>
                                <label for="status-completed" class="text-sm">مكتمل</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="status-cancelled" class="ml-2">
                                <label for="status-cancelled" class="text-sm">ملغي</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Apply Filters Button -->
                    <button class="w-full bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg transition-colors">
                        تطبيق الفلاتر
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Right Column (Main Calendar View) -->
        <div class="lg:col-span-3">
            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-mai-dark-brown">جدول المواعيد - <?php echo date('l, F j, Y'); ?></h2>
                </div>
                
                <!-- Day View -->
                <div id="day-view" class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Time Slots -->
                        <?php
                        $start_hour = 9; // 9 AM
                        $end_hour = 21; // 9 PM
                        
                        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                            $time_slot = sprintf('%02d:00', $hour);
                            $has_booking = ($hour % 3 == 0); // Placeholder logic
                            
                            if ($has_booking) {
                                // Slot with booking
                                echo '<div class="bg-mai-cream rounded-lg p-3 border-r-4 border-mai-brown">';
                                echo '<div class="flex justify-between items-start">';
                                echo '<div>';
                                echo '<p class="text-sm text-gray-500">' . $time_slot . '</p>';
                                echo '<h4 class="font-medium">قص شعر نسائي</h4>';
                                echo '<p class="text-sm">فاطمة أحمد السالم</p>';
                                echo '</div>';
                                echo '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">مؤكد</span>';
                                echo '</div>';
                                echo '<div class="mt-2 flex justify-between items-center">';
                                echo '<span class="text-sm text-gray-600">المتخصص: سارة محمد</span>';
                                echo '<div class="flex space-x-1 space-x-reverse">';
                                echo '<button class="text-blue-600 hover:text-blue-800"><i class="fas fa-eye"></i></button>';
                                echo '<button class="text-green-600 hover:text-green-800"><i class="fas fa-check"></i></button>';
                                echo '<button class="text-red-600 hover:text-red-800"><i class="fas fa-times"></i></button>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            } else {
                                // Empty slot
                                echo '<div class="border border-dashed border-gray-300 rounded-lg p-3 hover:bg-gray-50 cursor-pointer">';
                                echo '<p class="text-sm text-gray-500">' . $time_slot . '</p>';
                                echo '<p class="text-sm text-gray-400">متاح للحجز</p>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Booking Modal (hidden by default) -->
<div id="new-booking-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-mai-dark-brown">حجز موعد جديد</h3>
            <button id="close-booking-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Customer Selection -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">العميل</label>
                    <div class="relative">
                        <input type="text" id="customer-search" placeholder="بحث عن عميل..." class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <button id="search-customer-btn" class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-mai-brown">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Service Selection -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">الخدمة</label>
                    <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <option value="">اختر الخدمة</option>
                        <option value="1">قص شعر نسائي (150 ر.س - 45 دقيقة)</option>
                        <option value="2">صبغة شعر كاملة (350 ر.س - 120 دقيقة)</option>
                        <option value="3">تصفيف شعر (100 ر.س - 30 دقيقة)</option>
                        <option value="4">عناية بالبشرة (200 ر.س - 60 دقيقة)</option>
                    </select>
                </div>
                
                <!-- Specialist Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المتخصص</label>
                    <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <option value="">اختر المتخصص</option>
                        <option value="1">سارة محمد</option>
                        <option value="2">نورة العتيبي</option>
                        <option value="3">منى الشمري</option>
                    </select>
                </div>
                
                <!-- Date Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                    <input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <!-- Time Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوقت</label>
                    <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <option value="">اختر الوقت</option>
                        <?php
                        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                            $time_slot = sprintf('%02d:00', $hour);
                            echo "<option value=\"{$time_slot}\">{$time_slot}</option>";
                            
                            // Add half-hour slots
                            $time_slot = sprintf('%02d:30', $hour);
                            echo "<option value=\"{$time_slot}\">{$time_slot}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Status Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        <option value="pending">معلق</option>
                        <option value="confirmed">مؤكد</option>
                    </select>
                </div>
                
                <!-- Notes -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end">
            <button id="cancel-booking-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-booking-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ الموعد
            </button>
        </div>
    </div>
</div>

<!-- Bookings JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // View dropdown toggle
    const viewDropdownBtn = document.getElementById('view-dropdown-btn');
    const viewDropdownMenu = document.getElementById('view-dropdown-menu');
    
    viewDropdownBtn.addEventListener('click', function() {
        viewDropdownMenu.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!viewDropdownBtn.contains(event.target) && !viewDropdownMenu.contains(event.target)) {
            viewDropdownMenu.classList.add('hidden');
        }
    });
    
    // New booking modal
    const newBookingBtn = document.getElementById('new-booking-btn');
    const newBookingModal = document.getElementById('new-booking-modal');
    const closeBookingModal = document.getElementById('close-booking-modal');
    const cancelBookingBtn = document.getElementById('cancel-booking-btn');
    
    newBookingBtn.addEventListener('click', function() {
        newBookingModal.classList.remove('hidden');
    });
    
    function closeModal() {
        newBookingModal.classList.add('hidden');
    }
    
    closeBookingModal.addEventListener('click', closeModal);
    cancelBookingBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    newBookingModal.addEventListener('click', function(event) {
        if (event.target === newBookingModal) {
            closeModal();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
