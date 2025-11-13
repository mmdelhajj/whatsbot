#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "📄 TESTING NEW PAGINATION (10 items per page)\n";
echo "═════════════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "Test 1: Search 'plush' (should show 10 items if available)\n";
echo "─────────────────────────────────────────────────────────\n";

$response = $controller->processIncomingMessage($testPhone, 'plush');
$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

echo $lastMsg['message'];
echo "\n\n";

// Count how many products are shown
preg_match_all('/^\d+\./m', $lastMsg['message'], $matches);
$productCount = count($matches[0]);

echo "═════════════════════════════════════════════\n";
echo "Products shown: {$productCount}\n";

if ($productCount == 10) {
    echo "✅ SUCCESS! Showing 10 products per page\n";
} else if ($productCount > 5 && $productCount < 10) {
    echo "✅ Good! Showing {$productCount} products (less than 10 available)\n";
} else {
    echo "⚠️  Expected 10 products, got {$productCount}\n";
}
echo "═════════════════════════════════════════════\n";
