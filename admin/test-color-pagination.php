#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "📄 TESTING PAGINATION WITH 'COLOR' (100+ products)\n";
echo "══════════════════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "Searching for 'color'...\n";
echo "─────────────────────────\n\n";

$response = $controller->processIncomingMessage($testPhone, 'color');
$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

// Show first 500 characters
echo substr($lastMsg['message'], 0, 800) . "...\n\n";

// Count products shown
preg_match_all('/\*\d+\.\*/m', $lastMsg['message'], $matches);
$productCount = count($matches[0]);

// Check page info
preg_match('/Page (\d+) of (\d+)/', $lastMsg['message'], $pageMatches);
$currentPage = $pageMatches[1] ?? 'unknown';
$totalPages = $pageMatches[2] ?? 'unknown';

// Check if it says "next page"
$hasNextPage = (strpos($lastMsg['message'], 'next') !== false ||
                strpos($lastMsg['message'], 'التالي') !== false);

echo "═════════════════════════════════════════════\n";
echo "Products shown: {$productCount}\n";
echo "Page info: Page {$currentPage} of {$totalPages}\n";
echo "Has 'next page' button: " . ($hasNextPage ? "Yes ✅" : "No") . "\n";

if ($productCount == 10 && $totalPages > 1 && $hasNextPage) {
    echo "\n🎉 PERFECT! Showing 10 items with pagination!\n";
} else if ($productCount == 10) {
    echo "\n✅ Showing 10 items\n";
}
echo "═════════════════════════════════════════════\n";
