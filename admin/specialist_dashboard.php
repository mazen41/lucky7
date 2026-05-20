<?php
session_start();
require_once '../config/database.php';

// --- LANGUAGE & TRANSLATION LOGIC ---
$available_langs = ['ar', 'en'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: specialist_dashboard.php");
    exit;
}
$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');

// --- TRANSLATIONS ---
$t = [
    'en' => [
        'dashboard_title' => 'Dashboard', 'employee_portal' => 'Employee Portal', 'home' => 'Home', 'my_profile' => 'My Profile', 'attendance' => 'Attendance', 'leaves' => 'Leaves', 'performance' => 'Performance', 'payroll' => 'Payroll', 'requests' => 'Requests', 'logout' => 'Logout', 
        'welcome_message' => 'Welcome, ', 'welcome_submessage' => 'Ready to start your day?', 'check_in_prompt' => 'Press the button below to start your shift.',
        'check_in' => 'Check In', 'check_out' => 'Check Out', 
        'checked_in_at' => 'Checked In at', 'session_active' => 'Your work session is active.',
        'start_break' => 'Start Break', 'end_break' => 'End Break', 'you_are_on_break' => 'You are currently on a break.', 'select_break_type' => 'Select a break type...',
        'db_error' => 'Database error. Could not load initial data.',
        'getting_location' => 'Getting your location...',
        'location_denied' => 'Could not get your location. Please allow location access in your browser.',
        'network_error' => 'A network connection error occurred.',
    ],
    'ar' => [
        'dashboard_title' => 'لوحة التحكم', 'employee_portal' => 'بوابة الموظفين', 'home' => 'الرئيسية', 'my_profile' => 'ملفي الشخصي', 'attendance' => 'الحضور', 'leaves' => 'الإجازات', 'performance' => 'الأداء', 'payroll' => 'الرواتب', 'requests' => 'الطلبات', 'logout' => 'تسجيل الخروج',
        'welcome_message' => 'مرحباً بك، ', 'welcome_submessage' => 'هل أنت مستعد لبدء يومك؟', 'check_in_prompt' => 'اضغط على الزر أدناه لبدء دوامك.',
        'check_in' => 'تسجيل حضور', 'check_out' => 'تسجيل انصراف',
        'checked_in_at' => 'تم تسجيل الحضور الساعة', 'session_active' => 'جلسة عملك نشطة حالياً.',
        'start_break' => 'بدء راحة', 'end_break' => 'إنهاء راحة', 'you_are_on_break' => 'أنت حالياً في فترة راحة.', 'select_break_type' => 'اختر نوع الراحة...',
        'db_error' => 'خطأ في قاعدة البيانات. لا يمكن تحميل البيانات الأولية.',
        'getting_location' => '...جاري تحديد موقعك',
        'location_denied' => 'لا يمكن الوصول لموقعك. يرجى السماح للمتصفح بالوصول إلى الموقع.',
        'network_error' => 'حدث خطأ في الاتصال بالشبكة.',
    ]
];

// --- AUTHENTICATION & INITIAL DATA SETUP ---
if (!isset($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

// --- DATA FETCHING FOR INITIAL PAGE LOAD ---
$user_name = 'Employee';
$initial_status = 'out'; // Default status is 'out'
$check_in_time = null;
$current_break_name = null;
$available_breaks = [];

try {
    $name_col = ($lang === 'ar') ? 'name_ar' : 'name_en';
    $stmt_user = $db->prepare("SELECT $name_col AS name FROM team_members WHERE id = ?"); 
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC); 
    $user_name = $user['name'] ?? 'Employee';

    $stmt_last_event = $db->prepare("SELECT event_type, event_time, break_rule_id FROM hr_attendance_log WHERE employee_id = ? AND DATE(event_time) = CURDATE() ORDER BY event_time DESC LIMIT 1");
    $stmt_last_event->execute([$user_id]);
    $last_event = $stmt_last_event->fetch(PDO::FETCH_ASSOC);
    
    $initial_status = $last_event['event_type'] ?? 'out';
    
    if ($initial_status === 'break_end') $initial_status = 'in';

    if ($initial_status !== 'out') {
        $stmt_checkin = $db->prepare("SELECT event_time FROM hr_attendance_log WHERE employee_id = ? AND DATE(event_time) = CURDATE() AND event_type = 'in' ORDER BY event_time ASC LIMIT 1");
        $stmt_checkin->execute([$user_id]);
        $check_in_time = $stmt_checkin->fetchColumn();
    }
    
    $stmt_breaks = $db->prepare("SELECT id, break_name_en, break_name_ar FROM hr_break_rules ORDER BY duration_minutes ASC");
    $stmt_breaks->execute(); 
    $available_breaks = $stmt_breaks->fetchAll(PDO::FETCH_ASSOC);

    if ($initial_status === 'break_start' && !empty($last_event['break_rule_id'])) {
        $break_name_col = ($lang === 'ar') ? 'break_name_ar' : 'break_name_en';
        $stmt_break_name = $db->prepare("SELECT $break_name_col FROM hr_break_rules WHERE id = ?");
        $stmt_break_name->execute([$last_event['break_rule_id']]);
        $current_break_name = $stmt_break_name->fetchColumn();
    }

} catch (Exception $e) { 
    $page_error = $t[$lang]['db_error'] . " - " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $is_rtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t[$lang]['dashboard_title'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: '<?= $is_rtl ? 'Cairo' : 'Poppins' ?>', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .main-content { transition: margin-<?= $is_rtl ? 'right' : 'left' ?> 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(<?= $is_rtl ? '100%' : '-100%' ?>); } .sidebar.active { transform: translateX(0); } }
        @media (min-width: 768px) { .main-content { margin-<?= $is_rtl ? 'right' : 'left' ?>: 16rem; } }
        [x-cloak] { display: none !important; }
        .attendance-card { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100">

<?php if (isset($_SESSION['original_admin_id'])): ?>
<div class="fixed top-0 left-0 right-0 bg-yellow-400 text-black p-3 text-center z-50 shadow-lg"><i class="fas fa-exclamation-triangle"></i> You are viewing as **<?= htmlspecialchars($user_name) ?>**. <a href="return_to_admin.php" class="font-bold underline hover:text-blue-700 mr-4">Return to Admin</a></div><div class="pt-12"></div>
<?php endif; ?>

<div class="flex">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar bg-gray-800 text-white w-64 min-h-screen p-4 fixed <?= $is_rtl ? 'right-0' : 'left-0' ?> top-0 z-40 md:block">
        <div class="flex justify-between items-center mb-8"><div class="text-2xl font-bold"><?= $t[$lang]['employee_portal'] ?></div><a href="?lang=<?= $is_rtl ? 'en' : 'ar' ?>" class="text-sm p-2 bg-gray-700 rounded-md hover:bg-gray-600"><?= $is_rtl ? 'EN' : 'AR' ?></a></div>
        <nav>
             <a href="specialist_dashboard.php" class="flex items-center p-3 my-2 bg-gray-900 rounded-lg font-bold"><i class="fas fa-home <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['home'] ?></a>
            <a href="profile.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-user <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['my_profile'] ?></a>
            <!-- EDITED: Added the Attendance link below -->
            <a href="attendance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-clock <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['attendance'] ?></a>
            <a href="leaves.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-calendar-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['leaves'] ?></a>
            <a href="performance.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-chart-line <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['performance'] ?></a>
            <a href="payroll.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-file-invoice-dollar <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['payroll'] ?></a>
            <a href="requests.php" class="flex items-center p-3 my-2 hover:bg-gray-700 rounded-lg transition-colors"><i class="fas fa-clipboard-list <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['requests'] ?></a>
        </nav>
        <div class="absolute bottom-4 right-4 left-4"><a href="logouttwo.php" class="flex items-center p-3 my-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors justify-center"><i class="fas fa-sign-out-alt <?= $is_rtl ? 'ml-3' : 'mr-3' ?>"></i> <?= $t[$lang]['logout'] ?></a></div>
    </aside>

    <!-- Main Content -->
    <main class="main-content flex-1 p-6 md:p-8">
        <button id="menu-toggle" class="md:hidden fixed top-4 <?= $is_rtl ? 'right-4' : 'left-4' ?> bg-gray-800 text-white p-2 rounded-md z-50"><i class="fas fa-bars"></i></button>

        <div x-data="attendanceManager()">
            
            <?php if (isset($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><?= $page_error ?></div>
            <?php endif; ?>

            <!-- View 1: CHECKED OUT -->
            <div x-show="currentStatus === 'out'" x-transition x-cloak class="attendance-card max-w-lg mx-auto text-center bg-white p-8 rounded-2xl shadow-lg mt-10">
                <h1 class="text-3xl font-bold text-gray-800 mb-2"><?= $t[$lang]['welcome_message'] ?><?= htmlspecialchars($user_name) ?>!</h1>
                <p class="text-gray-500 mb-8"><?= $t[$lang]['welcome_submessage'] ?></p>
                <div class="bg-indigo-50 p-6 rounded-xl border border-indigo-200">
                     <p class="text-gray-600 mb-5"><?= $t[$lang]['check_in_prompt'] ?></p>
                     <button @click="handleAttendance('in')" :disabled="isProcessing" class="w-full text-center bg-green-600 text-white py-4 px-6 rounded-lg text-lg font-bold hover:bg-green-700 transition flex items-center justify-center gap-3 disabled:bg-gray-400 transform hover:scale-105">
                         <i class="fas" :class="isProcessing ? 'fa-spinner fa-spin' : 'fa-arrow-right-to-bracket'"></i>
                         <span x-text="isProcessing ? '<?= $t[$lang]['getting_location'] ?>' : '<?= $t[$lang]['check_in'] ?>'"></span>
                     </button>
                </div>
            </div>

            <!-- View 2: CHECKED IN -->
            <div x-show="currentStatus === 'in'" x-transition x-cloak class="attendance-card max-w-2xl mx-auto text-center bg-white p-8 rounded-2xl shadow-lg mt-10">
                <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-green-100 text-green-600 flex items-center justify-center rounded-full mb-4">
                        <i class="fas fa-user-check text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800"><?= $t[$lang]['session_active'] ?></h2>
                    <p class="text-gray-500 mt-1"><?= $t[$lang]['checked_in_at'] ?> <span class="font-semibold text-gray-700" dir="ltr" x-text="checkInTime"></span></p>
                </div>
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-6 bg-gray-50 rounded-lg border text-<?= $is_rtl ? 'right' : 'left' ?>">
                        <label for="break_select" class="block text-sm font-medium text-gray-700 mb-2"><?= $t[$lang]['start_break'] ?></label>
                        <div class="flex gap-2">
                            <select x-model="break_rule_id" id="break_select" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value=""><?= $t[$lang]['select_break_type'] ?></option>
                                <?php foreach ($available_breaks as $break): ?><option value="<?= $break['id'] ?>"><?= htmlspecialchars($lang === 'ar' ? $break['break_name_ar'] : $break['break_name_en']) ?></option><?php endforeach; ?>
                            </select>
                            <button @click="handleAttendance('break_start')" :disabled="isProcessing || !break_rule_id" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"><i class="fas fa-pause"></i></button>
                        </div>
                    </div>
                    <div class="p-6 bg-red-50 rounded-lg border border-red-200 flex flex-col justify-center">
                         <button @click="handleAttendance('out')" :disabled="isProcessing" class="w-full text-center bg-red-600 text-white py-3 px-6 rounded-lg font-bold hover:bg-red-700 transition flex items-center justify-center gap-2 disabled:bg-gray-400"><i class="fas fa-arrow-right-from-bracket"></i> <?= $t[$lang]['check_out'] ?></button>
                    </div>
                </div>
            </div>

            <!-- View 3: ON BREAK -->
            <div x-show="currentStatus === 'break_start'" x-transition x-cloak class="attendance-card max-w-lg mx-auto text-center bg-white p-8 rounded-2xl shadow-lg mt-10">
                 <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-yellow-100 text-yellow-600 flex items-center justify-center rounded-full mb-4">
                        <i class="fas fa-mug-hot text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800"><?= $t[$lang]['you_are_on_break'] ?></h2>
                    <p class="text-gray-500 mt-1"><?= htmlspecialchars($current_break_name ?? '') ?></p>
                    <div class="mt-8 w-full">
                        <button @click="handleAttendance('break_end')" :disabled="isProcessing" class="w-full text-center bg-yellow-500 text-white py-4 px-6 rounded-lg text-lg font-bold hover:bg-yellow-600 transition flex items-center justify-center gap-3 disabled:bg-gray-400 transform hover:scale-105"><i class="fas fa-play"></i> <?= $t[$lang]['end_break'] ?></button>
                    </div>
                </div>
            </div>
            
            <p class="mt-6 text-center h-auto min-h-[1.5rem] break-words" :class="{ 'text-red-600': message.type === 'error', 'text-green-600': message.type === 'success' }" x-text="message.text" x-show="message.text" x-transition></p>
        </div>
    </main>
</div>

<script>
document.getElementById('menu-toggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('active'); });

function attendanceManager() {
    return {
        isProcessing: false,
        break_rule_id: '',
        currentStatus: '<?= $initial_status ?>',
        checkInTime: '<?= $check_in_time ? date('h:i A', strtotime($check_in_time)) : '' ?>',
        message: { text: '', type: '' },

        async handleAttendance(actionType) {
            if (this.isProcessing) return;

            if (actionType === 'break_start' && !this.break_rule_id) {
                this.message = { text: '<?= addslashes($t[$lang]['select_break_type']) ?>', type: 'error' };
                return;
            }

            this.isProcessing = true;
            this.message = { text: '', type: '' };
            
            const formData = new FormData();
            formData.append('action', actionType);
            formData.append('break_rule_id', this.break_rule_id);

            // === NEW: GEOLOCATION LOGIC FOR CHECK-IN ===
            if (actionType === 'in') {
                try {
                    // Create a promise to handle the async nature of geolocation API
                    const position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            enableHighAccuracy: true,
                            timeout: 15000, // Increased timeout
                            maximumAge: 0
                        });
                    });
                    // Append coordinates to form data
                    formData.append('latitude', position.coords.latitude);
                    formData.append('longitude', position.coords.longitude);
                } catch (geoError) {
                    this.message = { text: '<?= addslashes($t[$lang]['location_denied']) ?>', type: 'error' };
                    this.isProcessing = false;
                    return; // Stop the function execution
                }
            }
            // === END: GEOLOCATION LOGIC ===

            try {
                const response = await fetch('attendance_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (!response.ok) {
                    this.message = { text: data.message || 'Server Error', type: 'error' };
                    throw new Error(data.message);
                }

                this.message = { text: data.message, type: data.success ? 'success' : 'error' };

                if (data.success) {
                    this.currentStatus = data.new_status;
                    if (data.check_in_time) this.checkInTime = data.check_in_time;
                    
                    if (data.new_status === 'out' || actionType === 'break_start') {
                         setTimeout(() => window.location.reload(), 1500);
                    }
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                if (!this.message.text) {
                     this.message = { text: '<?= addslashes($t[$lang]['network_error']) ?>', type: 'error' };
                }
            } finally {
                this.isProcessing = false;
            }
        }
    }
}
</script>
</body>
</html>