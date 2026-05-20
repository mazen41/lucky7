<?php
/**
 * Twilio Webhook to handle incoming WHATSAPP replies
 */
header('Content-Type: text/xml');

require_once '../config/database.php'; // Contains DB class and Twilio defines

$database = new Database();
$db = $database->getConnection();

// --- CRITICAL CHANGE FOR WHATSAPP ---
// The 'From' number from Twilio for WhatsApp looks like: "whatsapp:+15551234567"
$from_number_raw = $_POST['From'] ?? null;
$message_body = trim($_POST['Body'] ?? '');
$twilio_sid = $_POST['MessageSid'] ?? null;

// Only proceed if we have the essential data
if ($from_number_raw && $message_body && $twilio_sid) {
    // Remove the "whatsapp:" prefix to get the actual phone number
    $from_number = str_replace('whatsapp:', '', $from_number_raw);
    
    $customer_id = null;
    // Find the customer using the cleaned phone number
    $customer_stmt = $db->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
    $customer_stmt->execute([$from_number]);
    $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $customer_id = $customer['id'];
    }

    // Store the incoming message in the same log table
    try {
        $insert_stmt = $db->prepare(
            "INSERT INTO incoming_sms_log (customer_id, from_number, message_body, twilio_sid) VALUES (?, ?, ?, ?)"
        );
        // We store the cleaned number for consistency
        $insert_stmt->execute([$customer_id, $from_number, $message_body, $twilio_sid]);
    } catch (Exception $e) {
        error_log('Twilio WhatsApp Webhook DB Error: ' . $e->getMessage());
    }
}

// Respond to Twilio to acknowledge receipt
echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
exit();