#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "🤖 SIMPLE AI TEST\n";
echo "═════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "Testing: 'whats do you have rouleau'\n";
echo "This will use AI if database search finds nothing...\n\n";

$start = microtime(true);
$response = $controller->processIncomingMessage($testPhone, 'whats do you have rouleau');
$duration = round((microtime(true) - $start) * 1000);

$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

echo "Response ({$duration}ms):\n";
echo "─────────────────────\n";
echo $lastMsg['message'];
echo "\n\n";

if (strpos($lastMsg['message'], '🤖') !== false) {
    echo "✅ SUCCESS! AI was used to find products!\n";
} else if (strpos($lastMsg['message'], '📚') !== false) {
    echo "⚡ Fast search found results (AI not needed)\n";
} else {
    echo "❓ Check if AI API is working\n";

    // Check logs
    echo "\nChecking recent logs...\n";
    $logs = shell_exec("tail -20 /var/www/whatsapp-bot/logs/app.log | grep -i 'claude\\|ai\\|smart\\|error' | tail -5");
    if ($logs) {
        echo $logs;
    } else {
        echo "No AI-related logs found\n";
    }
}
