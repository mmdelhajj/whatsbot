#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "📄 TESTING MULTI-PAGE PAGINATION\n";
echo "════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "Test: Search 'pen' (should have many results)\n";
echo "─────────────────────────────────────────────\n\n";

$response = $controller->processIncomingMessage($testPhone, 'pen');
$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

echo $lastMsg['message'];
echo "\n\n";

// Count products shown
preg_match_all('/\*\d+\.\*/m', $lastMsg['message'], $matches);
$productCount = count($matches[0]);

// Check if it says "next page"
$hasNextPage = (strpos($lastMsg['message'], 'next') !== false ||
                strpos($lastMsg['message'], 'التالي') !== false ||
                strpos($lastMsg['message'], 'suivant') !== false);

echo "═════════════════════════════════════════════\n";
echo "Products shown: {$productCount}\n";
echo "Has 'next page' button: " . ($hasNextPage ? "Yes ✅" : "No") . "\n";

if ($productCount == 10 && $hasNextPage) {
    echo "\n✅ PERFECT! Showing 10 items with next page option!\n";
} else if ($productCount == 10) {
    echo "\n✅ Showing 10 items (all results fit in one page)\n";
} else {
    echo "\n⚠️  Expected 10 products per page\n";
}
echo "═════════════════════════════════════════════\n";
