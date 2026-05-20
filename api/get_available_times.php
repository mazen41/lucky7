<?php
// api/get_available_times.php

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    // --- 1. VALIDATE INPUTS ---
    if (!isset($_GET['date']) || !isset($_GET['specialist_id']) || !isset($_GET['service_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Missing required parameters.']);
        exit;
    }

    $date = $_GET['date'];
    $specialistId = filter_var($_GET['specialist_id'], FILTER_VALIDATE_INT);
    $serviceId = filter_var($_GET['service_id'], FILTER_VALIDATE_INT);

    if (!$specialistId || !$serviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid specialist or service ID.']);
        exit;
    }
    
    // --- 2. CONNECT TO DATABASE ---
    $database = new Database();
    $db = $database->getConnection();

    // --- 3. NEW: CHECK IF THE DAY IS A HOLIDAY ---
    $selectedDateObj = new DateTime($date);
    $selectedMonthDay = $selectedDateObj->format('m-d');

    $holiday_stmt = $db->prepare(
        "SELECT COUNT(*) FROM holidays 
         WHERE (is_recurring = 0 AND date = :selected_date) 
         OR (is_recurring = 1 AND DATE_FORMAT(date, '%m-%d') = :month_day)"
    );
    $holiday_stmt->execute([':selected_date' => $date, ':month_day' => $selectedMonthDay]);
    $isHoliday = $holiday_stmt->fetchColumn();

    if ($isHoliday > 0) {
        // This day is a holiday, return no available slots and exit.
        echo json_encode([]);
        exit;
    }
    // --- END NEW HOLIDAY CHECK ---


    // --- 4. FETCH REQUIRED DATA (WORKING HOURS & SERVICE DURATION) ---
    $dayOfWeekName = strtolower($selectedDateObj->format('l'));

    $service_stmt = $db->prepare("SELECT duration FROM services WHERE id = ?");
    $service_stmt->execute([$serviceId]);
    $serviceDuration = $service_stmt->fetchColumn();
    if (!$serviceDuration) {
        http_response_code(404);
        echo json_encode(['error' => 'Service not found.']);
        exit;
    }

    $hours_stmt = $db->prepare("SELECT open_time, close_time FROM specialist_working_hours WHERE specialist_id = ? AND day_of_week = ? AND is_working = 1");
    $hours_stmt->execute([$specialistId, $dayOfWeekName]);
    $workingHours = $hours_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$workingHours) {
        echo json_encode([]);
        exit;
    }

    // --- 5. FETCH EXISTING APPOINTMENTS FOR THE DAY ---
    $appt_stmt = $db->prepare(
        "SELECT start_at, end_at FROM appointments 
         WHERE specialist_id = ? AND DATE(start_at) = ? AND status IN ('pending', 'confirmed')"
    );
    $appt_stmt->execute([$specialistId, $date]);
    $existingAppointments = $appt_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 6. GENERATE & FILTER TIME SLOTS ---
    $availableSlots = [];
    $interval = 15;
    $cleaningBuffer = 20;

    $dayStartTime = new DateTime($date . ' ' . $workingHours['open_time']);
    $dayEndTime = new DateTime($date . ' ' . $workingHours['close_time']);
    $currentTimeSlot = clone $dayStartTime;

    while ($currentTimeSlot < $dayEndTime) {
        $potentialStartTime = clone $currentTimeSlot;
        $potentialEndTime = (clone $currentTimeSlot)->modify("+{$serviceDuration} minutes");

        if ($potentialEndTime > $dayEndTime) {
            break;
        }

        $isConflict = false;
        foreach ($existingAppointments as $appt) {
            $bookedStartTime = new DateTime($appt['start_at']);
            $bookedEndTimeWithBuffer = (new DateTime($appt['end_at']))->modify("+{$cleaningBuffer} minutes");

            if ($potentialStartTime < $bookedEndTimeWithBuffer && $potentialEndTime > $bookedStartTime) {
                $isConflict = true;
                break;
            }
        }

        if (!$isConflict) {
            $availableSlots[] = $potentialStartTime->format('g:i A');
        }

        $currentTimeSlot->modify("+{$interval} minutes");
    }

    // --- 7. RETURN THE RESULTS ---
    echo json_encode($availableSlots);

} catch (Exception $e) {
    http_response_code(500);
    error_log("get_available_times.php error: " . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred.']);
}