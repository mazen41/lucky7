<?php
// twilio_test.php
// Make sure to include your configuration file and the Twilio library
require_once '../config/database.php';
require_once '../vendor/autoload.php';


use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;
echo "--- Twilio Connection Test ---<br>";

// 1. Check if constants are defined
if (!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN')) {
    die("<strong>Error:</strong> TWILIO_ACCOUNT_SID or TWILIO_AUTH_TOKEN is not defined. Please check your config file.");
}

// 2. Display the credentials you are about to use (partially hidden for security)
echo "Using Account SID: " . substr(TWILIO_ACCOUNT_SID, 0, 5) . "....<br>";
echo "Using Auth Token: " . substr(TWILIO_AUTH_TOKEN, 0, 5) . "....<br><br>";
echo "Raw SID: '" . TWILIO_ACCOUNT_SID . "'<br>";
echo "Raw Token: '" . TWILIO_AUTH_TOKEN . "'<br>";

// 3. Attempt to connect to Twilio and fetch a list of your numbers
try {
    $twilio = new Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);

    // This is a simple, harmless API call to check authentication
    $incomingPhoneNumbers = $twilio->incomingPhoneNumbers
                                   ->read([], 1); // Limit to 1 result for speed

    if (is_array($incomingPhoneNumbers)) {
        echo "<strong>Success!</strong> Successfully authenticated and connected to Twilio API.";
    }

} catch (TwilioException $e) {
    echo "<strong>Connection Failed!</strong><br>";
    echo "Twilio returned an error: <strong>" . htmlspecialchars($e->getMessage()) . "</strong>";
} catch (Exception $e) {
    echo "<strong>A non-Twilio error occurred:</strong> " . htmlspecialchars($e->getMessage());
}

?>