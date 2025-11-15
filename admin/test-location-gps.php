#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "📍 TESTING LOCATION WITH GPS COORDINATES\n";
echo "═════════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

// Test different location queries
$queries = ['location', 'wen', 'where', 'address'];

foreach ($queries as $query) {
    $conversationState->clear($customerRecord['id']);

    echo "Testing: '{$query}'\n";
    echo "─────────────────\n";

    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    echo $lastMsg['message'];
    echo "\n\n";

    // Verify GPS link is present
    if (strpos($lastMsg['message'], 'maps.google.com') !== false &&
        strpos($lastMsg['message'], '34.011981') !== false) {
        echo "✅ GPS coordinates link included!\n";
    } else {
        echo "❌ GPS coordinates missing!\n";
    }
    echo "\n════════════════════════════════════════\n\n";
}

echo "✅ Location query test complete!\n";
