<?php
/**
 * Marketing Campaigns Dashboard - MAI Salon
 * DEBUGGING VERSION: This code is modified to expose the exact error message from Twilio.
 */

session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once 'users_functions.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException; // Import the specific Twilio exception class

// Function to format phone numbers (no changes here, it's correct)
function formatPhoneNumberToE164($number) {
    $trimmedNumber = trim($number);
    if (strpos($trimmedNumber, '+') === 0) return $trimmedNumber;
    $cleaned = preg_replace('/[^0-9]/', '', $trimmedNumber);
    if (strlen($cleaned) == 10 && substr($cleaned, 0, 2) == '05') return '+966' . substr($cleaned, 1);
    if (strlen($cleaned) == 11 && substr($cleaned, 0, 2) == '01') return '+20' . substr($cleaned, 1);
    return null;
}

// Auth & Permission Checks
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
requirePermission('marketing.view');

$database = new Database();
$db = $database->getConnection();

// --- AJAX ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'create_and_send_whatsapp_campaign') {
        requirePermission('marketing.create');
        requirePermission('marketing.send_campaigns');

        $name = trim($_POST['name']);
        $content = trim($_POST['message_content']);
        $tier_id = intval($_POST['tier_id']);

        if (empty($name) || empty($content) || $tier_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Campaign name, message, and target tier are required.']);
            exit;
        }

        // --- MODIFICATION START: We will capture errors here ---
        $detailed_errors = []; 
        // --- MODIFICATION END ---

        $db->beginTransaction();
        try {
            $campaign_stmt = $db->prepare("INSERT INTO campaigns (name, description, message_content, campaign_type, status, created_by) VALUES (?, 'WhatsApp Campaign', ?, 'whatsapp', 'sending', ?)");
            $campaign_stmt->execute([$name, $content, $_SESSION['admin_id'] ?? 1]);
            $campaign_id = $db->lastInsertId();

            $customer_stmt = $db->prepare("SELECT c.id, c.phone FROM customers c JOIN loyalty_wallets lw ON c.id = lw.customer_id WHERE lw.tier_id = ? AND c.phone IS NOT NULL AND c.phone != ''");
            $customer_stmt->execute([$tier_id]);
            $customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($customers)) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'No customers with a phone number were found in this tier.']);
                exit;
            }

            $twilio = new Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
            $sent_count = 0;
            $log_stmt = $db->prepare("INSERT INTO outgoing_sms_log (campaign_id, customer_id, to_number, message_body, status, twilio_sid, sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");

            foreach ($customers as $customer) {
                $formattedPhone = formatPhoneNumberToE164($customer['phone']);
                if (!$formattedPhone) {
                    $detailed_errors[] = "Skipped customer ID {$customer['id']}: Invalid phone format '{$customer['phone']}'.";
                    continue; 
                }

                try {
                    $whatsapp_recipient = 'whatsapp:' . $formattedPhone;
                    $whatsapp_sender = 'whatsapp:' . TWILIO_WHATSAPP_NUMBER;
                    
                    $message = $twilio->messages->create($whatsapp_recipient, ['from' => $whatsapp_sender, 'body' => $content]);

                    $log_stmt->execute([$campaign_id, $customer['id'], $customer['phone'], 'sent', $message->sid, $content]);
                    $sent_count++;
                } catch (TwilioException $e) {
                    // --- THIS IS THE CRITICAL CHANGE ---
                    // Capture the full, detailed error message from Twilio
                    $errorMessage = "Error for {$formattedPhone}: " . $e->getMessage();
                    error_log($errorMessage); // Keep logging for server records
                    $detailed_errors[] = $errorMessage; // Add to our list to show the user
                    
                    // Still log the failure in the database
                    $log_stmt->execute([$campaign_id, $customer['id'], $customer['phone'], 'failed', null, $content]);
                }
            }
            
            $update_stmt = $db->prepare("UPDATE campaigns SET status = 'sent', sent_at = NOW(), total_recipients = ? WHERE id = ?");
            $update_stmt->execute([$sent_count, $campaign_id]);
            
            $db->commit();
            
            $finalMessage = "";
            if ($sent_count > 0) {
                 $finalMessage = "Successfully sent to {$sent_count} customers.";
                 if (!empty($detailed_errors)) {
                     $finalMessage .= "\n\nHowever, some messages failed:\n" . implode("\n", $detailed_errors);
                 }
                 echo json_encode(['success' => true, 'message' => $finalMessage, 'recipients' => $sent_count]);
            } else {
                 // This will now show the detailed errors if sending failed
                 $finalMessage = "Sent to 0 customers. The server reported the following errors:\n\n" . implode("\n", $detailed_errors);
                 echo json_encode(['success' => false, 'message' => $finalMessage]);
            }

        } catch (Exception $e) {
            $db->rollback();
            // This catches other critical errors (like DB connection)
            $fatalError = "A fatal error occurred: " . $e->getMessage();
            error_log($fatalError);
            echo json_encode(['success' => false, 'message' => $fatalError]);
        }
        exit;
    }
}

// --- The rest of the file (HTML, Javascript, etc.) remains exactly the same ---
$campaigns = $db->query("SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$tiers_stmt = $db->prepare(
   "SELECT t.id, t.name_ar, COUNT(c.id) as customer_count 
    FROM loyalty_tiers t
    LEFT JOIN loyalty_wallets w ON t.id = w.tier_id
    LEFT JOIN customers c ON w.customer_id = c.id AND c.phone IS NOT NULL AND c.phone != ''
    GROUP BY t.id, t.name_ar ORDER BY t.min_points DESC"
);
$tiers_stmt->execute();
$tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'WhatsApp Campaigns';
ob_start();
?>
    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow mb-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-mai-dark-brown">حملات WhatsApp</h1>
            <div class="flex items-center gap-2">
                <a href="view_whatsapp_replies.php" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm">
                    <i class="fab fa-whatsapp ml-2"></i> عرض ردود العملاء
                </a>
                <?php if (hasPermission('marketing.create')): ?>
                <button onclick="openWhatsappModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm">
                    <i class="fas fa-plus ml-2"></i> حملة WhatsApp جديدة
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Campaigns Table -->
    <div class="p-6 bg-white rounded-xl shadow-sm border">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">الحملات الأخيرة</h3>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right">الحملة</th>
                    <th class="px-6 py-3 text-right">النوع</th>
                    <th class="px-6 py-3 text-right">المستلمون</th>
                    <th class="px-6 py-3 text-right">الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td class="px-6 py-4"><?= htmlspecialchars($campaign['name']) ?></td>
                    <td class="px-6 py-4"><span class="px-2 py-1 rounded-full bg-green-100 text-green-800"><?= strtoupper(htmlspecialchars($campaign['campaign_type'])) ?></span></td>
                    <td class="px-6 py-4"><?= number_format($campaign['total_recipients'] ?? 0) ?></td>
                    <td class="px-6 py-4"><span class="px-2 py-1 rounded-full bg-green-100 text-green-800"><?= htmlspecialchars($campaign['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- WhatsApp Campaign Modal -->
    <div id="whatsappModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-semibold mb-4">إنشاء وإرسال حملة WhatsApp</h3>
            <form id="whatsappForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">اسم الحملة (للعرض الداخلي)</label>
                    <input type="text" name="name" required class="w-full border-gray-300 rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">محتوى الرسالة</label>
                    <textarea name="message_content" rows="4" required class="w-full border-gray-300 rounded-lg"></textarea>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">الفئة المستهدفة</label>
                    <select name="tier_id" required class="w-full border-gray-300 rounded-lg">
                        <option value="">اختر الفئة</option>
                        <?php foreach ($tiers as $tier): ?>
                        <option value="<?= $tier['id'] ?>"><?= htmlspecialchars($tier['name_ar']) ?> (<?= $tier['customer_count'] ?> عميل)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg">إرسال الحملة</button>
                    <button type="button" onclick="closeWhatsappModal()" class="flex-1 bg-gray-300 py-2 rounded-lg">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openWhatsappModal() { document.getElementById('whatsappModal').classList.remove('hidden'); document.getElementById('whatsappModal').classList.add('flex'); }
        function closeWhatsappModal() { document.getElementById('whatsappModal').classList.add('hidden'); document.getElementById('whatsappModal').classList.remove('flex'); }

        document.getElementById('whatsappForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'جاري الإرسال...';
            
            const formData = new FormData(this);
            formData.append('action', 'create_and_send_whatsapp_campaign');

            fetch('marketing.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'An unknown error occurred.'); // This will now show the detailed error
                if (data.success) {
                    closeWhatsappModal();
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.textContent = 'إرسال الحملة';
                }
            }).catch(() => {
                alert('A network error occurred.');
                btn.disabled = false;
                btn.textContent = 'إرسال الحملة';
            });
        });
    </script>
<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>