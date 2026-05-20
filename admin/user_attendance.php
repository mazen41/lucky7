<?php
session_start();
require_once '../config/database.php'; // Ensure this path is correct

// --- LANGUAGE & TRANSLATION LOGIC ---
$lang = $_SESSION['lang'] ?? 'ar';
$is_rtl = ($lang === 'ar');
$t = [
    'en' => [
        'attendance_portal' => 'Attendance Portal',
        'back_to_admin' => 'Back to Admin Dashboard',
        'logout' => 'Logout',
        'welcome_message' => 'Welcome, ',
        'welcome_submessage' => 'Ready to start your day?',
        'check_in_prompt' => 'Press the button below to start your shift.',
        'session_active' => 'Your work session is active.',
        'check_out' => 'Check Out',
        'check_in' => 'Check In',
        'start_break' => 'Start Break',
        'end_break' => 'End Break',
        'you_are_on_break' => 'You are currently on a break.',
        'select_break_type' => 'Select a break type...',
        'getting_location' => 'Getting your location...',
        'location_denied' => 'Location access was denied. You must enable it in your browser to check in/out.',
        'network_error' => 'A network connection error occurred. Please try again.',
    ],
    'ar' => [
        'attendance_portal' => 'بوابة الحضور',
        'back_to_admin' => 'العودة للوحة تحكم المسؤول',
        'logout' => 'تسجيل الخروج',
        'welcome_message' => 'مرحباً بك، ',
        'welcome_submessage' => 'هل أنت مستعد لبدء يومك؟',
        'check_in_prompt' => 'اضغط على الزر أدناه لبدء دوامك.',
        'session_active' => 'جلسة عملك نشطة حالياً.',
        'check_out' => 'تسجيل انصراف',
        'check_in' => 'تسجيل حضور',
        'start_break' => 'بدء راحة',
        'end_break' => 'إنهاء راحة',
        'you_are_on_break' => 'أنت حالياً في فترة راحة.',
        'select_break_type' => 'اختر نوع الراحة...',
        'getting_location' => '...جاري تحديد موقعك',
        'location_denied' => 'تم رفض الوصول للموقع. يجب تفعيل الخاصية في متصفحك.',
        'network_error' => 'حدث خطأ في الاتصال بالشبكة. يرجى المحاولة مرة أخرى.',
    ]
];

// --- DATA FETCHING FOR INITIAL PAGE DISPLAY ---
$user_id = $_SESSION['admin_id'] ?? 0;
$user_name = 'Admin';
$initial_status = 'out';
$available_breaks = [];
$current_break_name = '';

if ($user_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Fetch user name
        $stmt_user = $db->prepare("SELECT name FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['name'])) {
            $user_name = $user['name'];
        }

        // Fetch last attendance event to determine current status
        $stmt_last_event = $db->prepare("SELECT event_type, break_rule_id FROM user_attendance_log WHERE user_id = ? AND DATE(event_time) = CURDATE() ORDER BY event_time DESC LIMIT 1");
        $stmt_last_event->execute([$user_id]);
        $last_event = $stmt_last_event->fetch(PDO::FETCH_ASSOC);

        if ($last_event) {
            $initial_status = $last_event['event_type'];
            // If the last action was ending a break, the current status is 'in'
            if ($initial_status === 'break_end') {
                $initial_status = 'in';
            }
        }

        // Fetch all available break types for the dropdown
        $stmt_breaks = $db->prepare("SELECT id, break_name_en, break_name_ar FROM hr_break_rules ORDER BY id ASC");
        $stmt_breaks->execute();
        $available_breaks = $stmt_breaks->fetchAll(PDO::FETCH_ASSOC);

        // If on break, get the name of the current break
        if ($initial_status === 'break_start' && !empty($last_event['break_rule_id'])) {
            $break_name_col = ($lang === 'ar') ? 'break_name_ar' : 'break_name_en';
            $stmt_break_name = $db->prepare("SELECT $break_name_col FROM hr_break_rules WHERE id = ?");
            $stmt_break_name->execute([$last_event['break_rule_id']]);
            $current_break_name = $stmt_break_name->fetchColumn();
        }

    } catch (Exception $e) {
        error_log("Attendance Page Load Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $is_rtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t[$lang]['attendance_portal'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        [x-cloak] { display: none !important; }
        .attendance-card { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-lg mx-auto p-4">
        <div x-data="attendanceManager()">
            <!-- View 1: CHECKED OUT -->
            <div x-show="currentStatus === 'out'" x-transition x-cloak class="attendance-card text-center bg-white p-8 rounded-2xl shadow-lg">
                <h1 class="text-3xl font-bold text-gray-800 mb-2"><?= $t[$lang]['welcome_message'] ?><?= htmlspecialchars($user_name) ?>!</h1>
                <p class="text-gray-500 mb-8"><?= $t[$lang]['welcome_submessage'] ?></p>
                <div class="bg-indigo-50 p-6 rounded-xl border border-indigo-200">
                     <p class="text-gray-600 mb-5"><?= $t[$lang]['check_in_prompt'] ?></p>
                     <button @click="handleAttendance('in')" :disabled="isProcessing" class="w-full text-center bg-green-600 text-white py-4 px-6 rounded-lg text-lg font-bold hover:bg-green-700 transition flex items-center justify-center gap-3 disabled:bg-gray-400 transform hover:scale-105">
                         <i class="fas" :class="isProcessing ? 'fa-spinner fa-spin' : 'fa-arrow-right-to-bracket'"></i>
                         <span x-text="isProcessing ? message.text : '<?= addslashes($t[$lang]['check_in']) ?>'"></span>
                     </button>
                </div>
            </div>

            <!-- View 2: CHECKED IN -->
            <div x-show="currentStatus === 'in'" x-transition x-cloak class="attendance-card bg-white p-8 rounded-2xl shadow-lg">
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 text-green-600 flex items-center justify-center rounded-full mb-4 mx-auto">
                        <i class="fas fa-user-check text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800"><?= $t[$lang]['session_active'] ?></h2>
                </div>
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg border">
                        <label for="break_select" class="block text-sm font-medium text-gray-700 mb-2"><?= $t[$lang]['start_break'] ?></label>
                        <div class="flex gap-2">
                            <select x-model="break_rule_id" id="break_select" class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value=""><?= $t[$lang]['select_break_type'] ?></option>
                                <?php foreach ($available_breaks as $break): ?>
                                    <option value="<?= $break['id'] ?>"><?= htmlspecialchars($lang === 'ar' ? $break['break_name_ar'] : $break['break_name_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button @click="handleAttendance('break_start')" :disabled="isProcessing || !break_rule_id" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 disabled:bg-gray-400"><i class="fas fa-pause"></i></button>
                        </div>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg border flex items-center">
                         <button @click="handleAttendance('out')" :disabled="isProcessing" class="w-full text-center bg-red-600 text-white py-3 px-6 rounded-lg font-bold hover:bg-red-700 flex items-center justify-center gap-2 disabled:bg-gray-400"><i class="fas fa-arrow-right-from-bracket"></i> <?= $t[$lang]['check_out'] ?></button>
                    </div>
                </div>
            </div>

            <!-- View 3: ON BREAK -->
            <div x-show="currentStatus === 'break_start'" x-transition x-cloak class="attendance-card text-center bg-white p-8 rounded-2xl shadow-lg">
                 <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-yellow-100 text-yellow-600 flex items-center justify-center rounded-full mb-4">
                        <i class="fas fa-mug-hot text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800"><?= $t[$lang]['you_are_on_break'] ?></h2>
                    <p class="text-gray-500 mt-1"><?= htmlspecialchars($current_break_name) ?></p>
                    <div class="mt-8 w-full">
                        <button @click="handleAttendance('break_end')" :disabled="isProcessing" class="w-full text-center bg-yellow-500 text-white py-4 px-6 rounded-lg text-lg font-bold hover:bg-yellow-600 transition flex items-center justify-center gap-3 disabled:bg-gray-400"><i class="fas fa-play"></i> <?= $t[$lang]['end_break'] ?></button>
                    </div>
                </div>
            </div>

            <!-- Feedback Message Area -->
            <p class="mt-6 text-center h-6 font-semibold"
               :class="{ 'text-red-600': message.type === 'error', 'text-green-600': message.type === 'success', 'text-blue-600': message.type === 'info' }"
               x-text="message.text"></p>
        </div>

        <div class="text-center mt-8 flex justify-center items-center gap-6">
            <a href="/admin/dashboard.php" class="text-sm text-gray-600 hover:text-blue-700 hover:underline font-semibold"><i class="fas fa-arrow-left"></i> <?= $t[$lang]['back_to_admin'] ?></a>
            <a href="logout.php" class="text-sm text-gray-600 hover:text-red-700 hover:underline font-semibold"><?= $t[$lang]['logout'] ?> <i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>

    <script>
    function attendanceManager() {
        return {
            isProcessing: false,
            break_rule_id: '',
            currentStatus: '<?= $initial_status ?>',
            message: { text: '', type: '' },

            async handleAttendance(actionType) {
                if (actionType === 'break_start' && !this.break_rule_id) {
                    this.message = { text: '<?= addslashes($t[$lang]['select_break_type']) ?>', type: 'error' };
                    return;
                }
                
                this.isProcessing = true;
                this.message = { text: '<?= addslashes($t[$lang]['getting_location']) ?>', type: 'info' };

                const formData = new FormData();
                formData.append('action', actionType);
                formData.append('break_rule_id', this.break_rule_id);

                // Location is only strictly needed for check-in and check-out
                if (actionType === 'in' || actionType === 'out') {
                    try {
                        const position = await new Promise((resolve, reject) => {
                            navigator.geolocation.getCurrentPosition(resolve, reject, {
                                enableHighAccuracy: true, timeout: 10000, maximumAge: 0
                            });
                        });
                        formData.append('latitude', position.coords.latitude);
                        formData.append('longitude', position.coords.longitude);
                    } catch (error) {
                        this.message = { text: '<?= addslashes($t[$lang]['location_denied']) ?>', type: 'error' };
                        this.isProcessing = false;
                        return;
                    }
                }

                this.message.text = ''; // Clear processing message

                try {
                    const response = await fetch('process_admin_attendance.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        this.message = { text: data.message || 'An unknown error occurred.', type: 'error' };
                    } else {
                        this.message = { text: data.message, type: 'success' };
                        // If the action was successful, immediately reload the page
                        // This is the simplest way to ensure the UI state (like current break name) is perfectly in sync
                        setTimeout(() => window.location.reload(), 1500);
                    }
                } catch (error) {
                    this.message = { text: '<?= addslashes($t[$lang]['network_error']) ?>', type: 'error' };
                } finally {
                    // Don't set isProcessing to false if we are reloading, to prevent double clicks
                    if (!response || !response.ok) {
                        this.isProcessing = false;
                    }
                }
            }
        }
    }
    </script>
</body>
</html>