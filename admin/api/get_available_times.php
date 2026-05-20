<?php
// File: /api/get_available_times.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once '../../config/database.php';

// --- MAIN LOGIC ---
try {
    $database = new Database();
    $db = $database->getConnection();

    // --- 1. Get and Validate Inputs ---
    $date = filter_input(INPUT_GET, 'date', FILTER_UNSAFE_RAW);
    $specialist_id = filter_input(INPUT_GET, 'specialist_id', FILTER_VALIDATE_INT);
    $service_id = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);

    if (!$date || !$specialist_id || !$service_id) {
        throw new Exception('Missing required parameters: date, specialist_id, and service_id are required.');
    }

    $selected_date = new DateTime($date);
    $day_of_week_name = strtolower($selected_date->format('l'));

    // --- 2. Get Service Duration, Buffer, and Slot Interval from Settings ---
    $settings_stmt = $db->query("SELECT default_buffer, slot_interval FROM booking_settings WHERE id = 1");
    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
    $default_buffer = $settings['default_buffer'] ?? 10;
    $slot_interval = $settings['slot_interval'] ?? 15; // Fallback to 15 mins if not set

    $service_stmt = $db->prepare("SELECT duration, buffer_time FROM services WHERE id = ?");
    $service_stmt->execute([$service_id]);
    $service = $service_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        throw new Exception('Invalid service selected.');
    }
    $duration = (int)$service['duration'];
    $buffer = $service['buffer_time'] ?? $default_buffer;

    // --- 3. Determine Working Hours for the Day ---
    $working_hours = null;
    $spec_hours_stmt = $db->prepare("SELECT open_time, close_time, is_working FROM specialist_working_hours WHERE specialist_id = ? AND day_of_week = ?");
    $spec_hours_stmt->execute([$specialist_id, $day_of_week_name]);
    $specialist_schedule = $spec_hours_stmt->fetch(PDO::FETCH_ASSOC);

    if ($specialist_schedule && $specialist_schedule['is_working']) {
        $working_hours = ['open' => $specialist_schedule['open_time'], 'close' => $specialist_schedule['close_time']];
    } else {
        // Fallback to general working hours if specialist has no specific schedule for the day
        $gen_hours_stmt = $db->prepare("SELECT open_time, close_time, is_closed FROM working_hours WHERE day_of_week = ?");
        $gen_hours_stmt->execute([$day_of_week_name]);
        $general_schedule = $gen_hours_stmt->fetch(PDO::FETCH_ASSOC);
        if ($general_schedule && !$general_schedule['is_closed']) {
            $working_hours = ['open' => $general_schedule['open_time'], 'close' => $general_schedule['close_time']];
        }
    }

    if (!$working_hours) {
        echo json_encode([]); // Day is off, return empty array
        exit;
    }

    // --- 4. Get All Booked Time Slots for the Day ---
    $busy_slots = [];
    $appt_stmt = $db->prepare("
        SELECT a.start_at, a.end_at, COALESCE(s.buffer_time, ?) as buffer
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.specialist_id = ? AND DATE(a.start_at) = ? AND a.status IN ('pending', 'confirmed')
    ");
    $appt_stmt->execute([$default_buffer, $specialist_id, $date]);
    
    foreach ($appt_stmt->fetchAll(PDO::FETCH_ASSOC) as $appt) {
        $busy_slots[] = [
            'start' => new DateTime($appt['start_at']),
            // The end of a busy period includes its buffer time
            'end' => (new DateTime($appt['end_at']))->modify("+{$appt['buffer']} minutes")
        ];
    }

    // --- 5. Generate and Filter Available Slots based on slot_interval ---
    $available_slots = [];
    
    $start_of_day = new DateTime($date . ' ' . $working_hours['open']);
    $end_of_day = new DateTime($date . ' ' . $working_hours['close']);

    $current_slot_start = clone $start_of_day;

    while ($current_slot_start < $end_of_day) {
        $potential_slot_end = (clone $current_slot_start)->modify("+{$duration} minutes");
        
        // A slot is only valid if the service itself finishes before or exactly at the closing time.
        if ($potential_slot_end > $end_of_day) {
            break; // No more possible slots will fit
        }

        $is_available = true;
        // The total time we need to check for a collision is the service duration plus its own buffer
        $potential_slot_end_with_buffer = (clone $potential_slot_end)->modify("+{$buffer} minutes");

        foreach ($busy_slots as $busy_slot) {
            // Standard overlap check: (StartA < EndB) and (EndA > StartB)
            // Does our potential new appointment overlap with an existing busy slot?
            if ($current_slot_start < $busy_slot['end'] && $potential_slot_end_with_buffer > $busy_slot['start']) {
                $is_available = false;
                break; // Found a collision, this slot is not available
            }
        }
        
        if ($is_available) {
            // EDITED: Changed format from 'H:i' to 'g:i A' for 12-hour format with AM/PM
            $available_slots[] = $current_slot_start->format('g:i A');
        }

        // Move to the next potential slot based on the interval from settings
        $current_slot_start->modify("+{$slot_interval} minutes");
    }

    echo json_encode($available_slots);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>