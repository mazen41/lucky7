<?php
/**
 * HR Performance Calculator - Calculate from POS Data
 * UPGRADED: Handles both Specialist (individual revenue) and Admin (total salon revenue) bonus structures.
 * NEW: Includes a 'preview' mode to return data without saving.
 * MODIFIED: Non-specialist bonus is now calculated based on dynamic rules, and users are identified via the users/roles tables.
 * ENHANCED: Attendance calculation now dynamically fetches and respects salon-wide holidays and weekly off days.
 */

require_once '../config/database.php';

// Determine the run mode. 'execute' is the default (original) behavior.
$mode = $_GET['mode'] ?? 'execute';

/**
 * NEW: Helper function to check if a given date is a salon-wide off day (weekend or holiday).
 * @param string $date_str The date to check in 'Y-m-d' format.
 * @param array $weekly_off_days Array of integers representing weekly off days (0=Sun, 6=Sat).
 * @param array $specific_holidays Array of specific holiday dates ('Y-m-d').
 * @param array $recurring_holidays_md Array of recurring holidays in 'm-d' format.
 * @return bool True if the date is an off day, false otherwise.
 */
function is_day_off($date_str, $weekly_off_days, $specific_holidays, $recurring_holidays_md) {
    $timestamp = strtotime($date_str);
    $day_of_week = (int) date('w', $timestamp); // 0 for Sunday, 6 for Saturday

    // 1. Check if it's a weekly day off
    if (in_array($day_of_week, $weekly_off_days)) {
        return true;
    }

    // 2. Check if it's a specific, non-recurring holiday
    $date_ymd = date('Y-m-d', $timestamp);
    if (in_array($date_ymd, $specific_holidays)) {
        return true;
    }

    // 3. Check if it's a recurring holiday (match month-day)
    $date_md = date('m-d', $timestamp);
    if (in_array($date_md, $recurring_holidays_md)) {
        return true;
    }

    return false;
}


/**
 * Helper function to save performance data to the database.
 * MODIFIED: Now calculates total working days accurately by excluding holidays and weekends.
 */
function save_performance_record($db, $employee_id, $month, $service_count, $revenue_sum, $bonus_earned, $off_days_config) {
    // --- START: MODIFIED ATTENDANCE CALCULATION ---
    // This part now accurately calculates the total working days in the month, respecting holidays.

    // Calculate attendance days (days the employee was present)
    $attendance_query = "
        SELECT COUNT(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 END) as attendance_days
        FROM hr_attendance
        WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ";
    $att_stmt = $db->prepare($attendance_query);
    $att_stmt->execute([$employee_id, $month]);
    $attendance_days = (int) ($att_stmt->fetchColumn() ?? 0);

    // Calculate total working days in the month, excluding salon-wide off days
    $year = (int) substr($month, 0, 4);
    $month_num = (int) substr($month, 5, 2);
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);
    $total_working_days = 0;

    for ($day = 1; $day <= $days_in_month; $day++) {
        $current_date_str = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        if (!is_day_off($current_date_str, $off_days_config['weekly'], $off_days_config['specific'], $off_days_config['recurring'])) {
            $total_working_days++;
        }
    }

    // Calculate attendance rate, handle division by zero
    $attendance_rate = $total_working_days > 0 ? round(($attendance_days / $total_working_days) * 100, 2) : 0.00;
    // --- END: MODIFIED ATTENDANCE CALCULATION ---

    // Insert or update the record. Targets and achievements are set to 0 as they are deprecated by this new system.
    $insert_query = "
        INSERT INTO hr_performance (
            employee_id, month, service_count, revenue_sum, bonus_earned,
            attendance_days, total_working_days, attendance_rate,
            target_service_count, target_revenue, service_achievement, revenue_achievement, calculated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            service_count = VALUES(service_count),
            revenue_sum = VALUES(revenue_sum),
            bonus_earned = VALUES(bonus_earned),
            attendance_days = VALUES(attendance_days),
            total_working_days = VALUES(total_working_days),
            attendance_rate = VALUES(attendance_rate),
            target_service_count = 0, -- Deprecated, set to 0
            target_revenue = 0,     -- Deprecated, set to 0
            service_achievement = 0, -- Deprecated, set to 0
            revenue_achievement = 0, -- Deprecated, set to 0
            calculated_at = CURRENT_TIMESTAMP
    ";

    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->execute([
        $employee_id, $month, $service_count, $revenue_sum, $bonus_earned,
        $attendance_days, $total_working_days, $attendance_rate
    ]);
}


try {
    $database = new Database();
    $db = $database->getConnection();

    // In 'execute' mode, output plain text for logging. In 'preview', we'll output JSON later.
    if ($mode === 'execute') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "📊 Calculating HR Performance for ALL Employee Types...\n\n";
    }
    
    // --- START: NEW - FETCH SALON OFF DAYS ---
    $weekly_off_days_q = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'weekly_days_off'");
    $weekly_off_days_str = $weekly_off_days_q->fetchColumn() ?: '';
    $weekly_off_days = !empty($weekly_off_days_str) ? array_map('intval', explode(',', $weekly_off_days_str)) : [];

    $non_recurring_q = $db->query("SELECT date FROM holidays WHERE is_recurring = 0");
    $specific_holidays = $non_recurring_q->fetchAll(PDO::FETCH_COLUMN, 0);

    $recurring_q = $db->query("SELECT DATE_FORMAT(date, '%m-%d') AS month_day FROM holidays WHERE is_recurring = 1");
    $recurring_holidays_md = $recurring_q->fetchAll(PDO::FETCH_COLUMN, 0);

    $off_days_config = [
        'weekly' => $weekly_off_days,
        'specific' => $specific_holidays,
        'recurring' => $recurring_holidays_md
    ];
    // --- END: NEW - FETCH SALON OFF DAYS ---

    $current_month = date('Y-m');
    $previous_month = date('Y-m', strtotime('-1 month'));
    $months_to_calculate = array_unique([$current_month, $previous_month]);

    $preview_data = ['months' => []];

    foreach ($months_to_calculate as $month) {
        if ($mode === 'execute') {
            echo "📅 Processing month: $month\n";
        }

        $monthly_preview = [
            'month' => $month,
            'specialists' => [],
            'non_specialists' => [],
            'total_salon_revenue' => 0
        ];

        // STEP 1: Calculate Total Salon Revenue for the month
        $total_revenue_query = "SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'paid'";
        $total_revenue_stmt = $db->prepare($total_revenue_query);
        $total_revenue_stmt->execute([$month]);
        $total_salon_revenue = (float) $total_revenue_stmt->fetchColumn();
        $monthly_preview['total_salon_revenue'] = $total_salon_revenue;
        
        if ($mode === 'execute') {
            echo "💰 Total Salon Revenue for $month: " . number_format($total_salon_revenue, 2) . " SAR\n";
        }

        // STEP 1.5 - Fetch the dynamic bonus settings for non-specialists
        $settings_query = "SELECT setting_key, setting_value FROM salon_performance_settings";
        $settings_stmt = $db->query($settings_query);
        $settings_data = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $revenue_target = (float)($settings_data['revenue_target'] ?? 50000.00);
        $base_bonus = (float)($settings_data['base_bonus'] ?? 500.00);
        $incremental_revenue = (float)($settings_data['incremental_revenue'] ?? 10000.00);
        $incremental_bonus = (float)($settings_data['incremental_bonus'] ?? 100.00);
        
        if ($mode === 'execute') {
            echo "📈 Non-Specialist Bonus Rules: Target " . number_format($revenue_target) . " SAR for a " . number_format($base_bonus) . " SAR bonus. Then, " . number_format($incremental_bonus) . " SAR for each additional " . number_format($incremental_revenue) . " SAR.\n\n";
            echo "--- 🧑‍🔬 Processing Specialists (Individual Performance) ---\n";
        }
        
        // STEP 2: Process Specialists
        $specialists_query = "SELECT id, name_ar, salary FROM team_members WHERE is_active = 1 AND is_specialist = 1";
        $specialists_stmt = $db->prepare($specialists_query);
        $specialists_stmt->execute();
        $specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($specialists as $specialist) {
            $employee_id = $specialist['id'];
            $employee_name = $specialist['name_ar'];
            $base_salary = (float) ($specialist['salary'] ?? 0.00);

            $performance_query = "
                SELECT COALESCE(SUM(ROUND(ii.unit_price * ii.quantity, 2)), 0) as revenue_sum
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                WHERE ii.specialist_id = ? AND DATE_FORMAT(i.created_at, '%Y-%m') = ? AND i.status = 'paid'";
            $perf_stmt = $db->prepare($performance_query);
            $perf_stmt->execute([$employee_id, $month]);
            $revenue_sum = (float) $perf_stmt->fetchColumn();

            $service_count_query = "
                SELECT COALESCE(SUM(ii.quantity), 0)
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.id
                WHERE ii.specialist_id = ? AND DATE_FORMAT(i.created_at, '%Y-%m') = ? AND i.status = 'paid' AND ii.item_type = 'service'";
            $service_count_stmt = $db->prepare($service_count_query);
            $service_count_stmt->execute([$employee_id, $month]);
            $service_count = (int) $service_count_stmt->fetchColumn();

            $bonus_earned = 0.00;
            $revenue_threshold = $base_salary * 5;
            if ($base_salary > 0 && $revenue_sum > $revenue_threshold) {
                $surplus_revenue = $revenue_sum - $revenue_threshold;
                $bonus_earned = $surplus_revenue * 0.05;
            }
            
            if ($mode === 'preview') {
                 $monthly_preview['specialists'][] = [
                    'name_ar' => $employee_name,
                    'service_count' => $service_count,
                    'revenue_sum' => round($revenue_sum, 2),
                    'bonus_earned' => round($bonus_earned, 2)
                 ];
            } else {
                save_performance_record($db, $employee_id, $month, $service_count, $revenue_sum, $bonus_earned, $off_days_config);
                echo "✅ [Specialist] $employee_name: Revenue " . number_format($revenue_sum, 2) . " SAR, Services: $service_count -> Bonus " . number_format($bonus_earned, 2) . " SAR\n";
            }
        }
        if ($mode === 'execute') echo "\n";

        // STEP 3: Process ALL Non-Specialist Staff
        if ($mode === 'execute') echo "--- 💼 Processing ALL Non-Specialist Staff (Salon Performance) ---\n";
        
        $non_specialists_query = "
            SELECT u.id, u.name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.role_name != 'Specialist' AND u.is_active = 1
        ";
        $non_specialists_stmt = $db->prepare($non_specialists_query);
        $non_specialists_stmt->execute();
        $non_specialists = $non_specialists_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($non_specialists as $user) {
            $employee_id = $user['id'];
            $employee_name = $user['name'];
            
            $bonus_earned = 0.00;
            if ($total_salon_revenue >= $revenue_target) {
                $bonus_earned += $base_bonus;
                $surplus_after_target = $total_salon_revenue - $revenue_target;
                if ($surplus_after_target > 0 && $incremental_revenue > 0) {
                    $incremental_chunks = floor($surplus_after_target / $incremental_revenue);
                    $bonus_earned += $incremental_chunks * $incremental_bonus;
                }
            }
            
            if ($mode === 'preview') {
                 $monthly_preview['non_specialists'][] = [
                    'name' => $employee_name,
                    'bonus_earned' => round($bonus_earned, 2)
                 ];
            } else {
                save_performance_record($db, $employee_id, $month, 0, 0.00, $bonus_earned, $off_days_config);
                echo "✅ [User] $employee_name: Bonus based on total salon revenue -> " . number_format($bonus_earned, 2) . " SAR\n";
            }
        }
        if ($mode === 'execute') echo "\n";
        
        $preview_data['months'][] = $monthly_preview;
    }

    if ($mode === 'preview') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($preview_data);
        exit();
    }

    // --- START: MODIFIED SAMPLE ATTENDANCE GENERATION ---
    // Now uses the is_day_off() function to skip holidays and weekly off days correctly.
    echo "⏰ Checking if sample attendance data is needed...\n";
    $attendance_check = $db->prepare("SELECT COUNT(*) FROM hr_attendance WHERE DATE_FORMAT(date, '%Y-%m') = ?");
    $attendance_check->execute([$current_month]);
    $attendance_count = $attendance_check->fetchColumn();

    if ($attendance_count == 0) {
        echo "⏳ No attendance data found for the current month. Generating samples...\n";
        $all_employees_query = "SELECT id FROM team_members WHERE is_active = 1";
        $all_employees_stmt = $db->query($all_employees_query);
        $all_employees = $all_employees_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Loop through all days of the current month
        $days_in_current_month = (int)date('t');
        for ($day = 1; $day <= $days_in_current_month; $day++) {
            $date = date('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
            
            // MODIFIED: Skip this day if it's a salon-wide off day
            if (is_day_off($date, $weekly_off_days, $specific_holidays, $recurring_holidays_md)) {
                continue;
            }

            foreach ($all_employees as $employee) {
                $employee_id = $employee['id'];
                $status = 'absent';
                $check_in = null; $check_out = null; $hours = 0.00;
                $overtime_hours = 0.00;
                $present_chance = rand(1, 100);

                if ($present_chance <= 90) { // 90% chance of being present
                    $check_in_base = strtotime($date . ' 09:00:00');
                    $check_in_variation = rand(-30, 60);
                    $check_in = date('H:i:s', $check_in_base + ($check_in_variation * 60));
                    
                    $check_out_base = strtotime($date . ' 17:00:00');
                    $check_out_variation = rand(-30, 90);
                    $check_out = date('H:i:s', $check_out_base + ($check_out_variation * 60));

                    $worked_seconds = strtotime($date . ' ' . $check_out) - strtotime($date . ' ' . $check_in);
                    $hours = max(0, round($worked_seconds / 3600, 2));
                    
                    $status = 'present';
                    if ($check_in_variation > 15) { $status = 'late'; }
                    if ($hours < 4 && $hours > 0) { $status = 'half_day'; }
                    elseif ($hours >= 8.5) { $overtime_hours = $hours - 8.5; }

                } elseif ($present_chance > 90 && $present_chance <= 95) { // 5% chance of sick
                    $status = 'sick'; 
                }
                // The rest (5%) remain absent

                $att_insert = $db->prepare("INSERT IGNORE INTO hr_attendance (employee_id, date, check_in, check_out, hours, overtime_hours, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $att_insert->execute([$employee_id, $date, $check_in, $check_out, $hours, $overtime_hours, $status]);
            }
        }
         echo "✅ Generated sample attendance data for all active employees.\n";
    } else {
        echo "👍 Attendance data already exists. Skipping sample generation.\n";
    }
    // --- END: MODIFIED SAMPLE ATTENDANCE GENERATION ---

    echo "\n🎉 HR Performance calculation completed for all roles!\n";

} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
?>