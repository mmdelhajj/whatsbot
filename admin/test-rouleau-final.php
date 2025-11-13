#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "🎯 FINAL ROULEAU TEST\n";
echo "═══════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "Testing: 'whats do you have rouleau'\n\n";

$response = $controller->processIncomingMessage($testPhone, 'whats do you have rouleau');
$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

echo "Response:\n";
echo "─────────\n";
echo $lastMsg['message'];
echo "\n\n";

if (strpos($lastMsg['message'], 'Tape') !== false || strpos($lastMsg['message'], 'tape') !== false) {
    echo "✅ SUCCESS! Tape products found!\n";
    echo "   'rouleau' → 'tape' translation working!\n";
} else if (strpos($lastMsg['message'], 'Product') !== false || strpos($lastMsg['message'], '📚') !== false) {
    echo "✅ SUCCESS! Products found!\n";
} else {
    echo "⚠️  No products found\n";
}
