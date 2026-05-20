<?php
/**
 * View Customer WhatsApp Conversations
 */
session_start();
require_once '../config/database.php';
require_once 'users_functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Fetch customers who have replied
$conversations_stmt = $db->query(
   "SELECT c.id, c.name, c.phone, MAX(i.received_at) as last_reply_time
    FROM incoming_sms_log i
    JOIN customers c ON i.customer_id = c.id
    GROUP BY c.id, c.name, c.phone
    ORDER BY last_reply_time DESC"
);
$conversations = $conversations_stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$messages = [];
$customer_name = 'Select a conversation';

if ($selected_customer_id) {
    $cust_name_stmt = $db->prepare("SELECT name FROM customers WHERE id = ?");
    $cust_name_stmt->execute([$selected_customer_id]);
    $customer_name = $cust_name_stmt->fetchColumn() ?: 'Unknown';

    // Get all messages (outgoing and incoming)
    $messages_stmt = $db->prepare(
       "(SELECT message_body, sent_at as message_time, 'outgoing' as type FROM outgoing_sms_log WHERE customer_id = :id AND status = 'sent')
        UNION ALL
        (SELECT message_body, received_at as message_time, 'incoming' as type FROM incoming_sms_log WHERE customer_id = :id)
        ORDER BY message_time ASC"
    );
    $messages_stmt->execute([':id' => $selected_customer_id]);
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Customer WhatsApp Replies';
ob_start();
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6 h-[calc(100vh-100px)]">
    <!-- Conversations List -->
    <div class="bg-white rounded-xl shadow-sm border flex flex-col h-full">
        <div class="p-4 border-b"><h3 class="text-lg font-semibold">Conversations</h3></div>
        <div class="p-2 space-y-1 overflow-y-auto flex-grow">
            <?php foreach ($conversations as $convo): ?>
                <a href="?customer_id=<?= $convo['id'] ?>" class="block p-3 rounded-lg <?= ($selected_customer_id == $convo['id']) ? 'bg-blue-100' : 'hover:bg-gray-50' ?>">
                    <p class="font-semibold"><?= htmlspecialchars($convo['name']) ?></p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($convo['phone']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Message Display -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border flex flex-col h-full">
        <div class="p-4 border-b"><h3 class="text-lg font-semibold">WhatsApp Chat with: <span class="text-blue-600"><?= htmlspecialchars($customer_name) ?></span></h3></div>
        <div id="messages-container" class="p-6 space-y-4 overflow-y-auto flex-grow">
            <?php if (!$selected_customer_id): ?>
                <p class="text-center text-gray-500">Select a conversation to view messages.</p>
            <?php endif; ?>
            <?php foreach ($messages as $msg): ?>
                <div class="flex <?= $msg['type'] === 'outgoing' ? 'justify-end' : 'justify-start' ?>">
                    <div class="p-3 rounded-lg max-w-md <?= $msg['type'] === 'outgoing' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' ?>">
                        <p><?= nl2br(htmlspecialchars($msg['message_body'])) ?></p>
                        <div class="text-xs mt-1 text-right opacity-75"><?= date('M j, g:i a', strtotime($msg['message_time'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
    const container = document.getElementById('messages-container');
    container.scrollTop = container.scrollHeight;
</script>
<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>