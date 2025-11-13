#!/usr/bin/env php
<?php
/**
 * Simple test for Arabic numerals
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🔢 SIMPLE ARABIC NUMERALS TEST\n";
echo "════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear any existing state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "Test 1: Show product list\n";
$response = $controller->processIncomingMessage($testPhone, 'pen');
$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

if (strpos($lastMsg['message'], '📚') !== false) {
    echo "✅ Product list shown\n";

    // Extract product names from the list
    preg_match_all('/\*\d+\.\* (.+?)\n/', $lastMsg['message'], $matches);
    if (!empty($matches[1])) {
        echo "\nProducts shown:\n";
        foreach (array_slice($matches[1], 0, 5) as $i => $product) {
            $num = $i + 1;
            echo "  {$num}. {$product}\n";
        }
    }
    echo "\n";

    // Test Arabic numeral ٣ (3)
    echo "Test 2: Select product #3 using Arabic numeral '٣'\n";
    $response = $controller->processIncomingMessage($testPhone, '٣');
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    // Check if product was selected (should show product details or ask for name)
    if (strpos($lastMsg['message'], 'تفاصيل') !== false ||
        strpos($lastMsg['message'], 'Details') !== false ||
        strpos($lastMsg['message'], 'name') !== false ||
        strpos($lastMsg['message'], 'اسم') !== false ||
        strpos($lastMsg['message'], 'selected') !== false ||
        strpos($lastMsg['message'], 'اخترت') !== false) {
        echo "✅ SUCCESS! Arabic numeral '٣' works!\n";
        echo "   Response: " . substr($lastMsg['message'], 0, 100) . "...\n";
    } else {
        echo "❌ FAILED! Arabic numeral not recognized\n";
        echo "   Response: " . substr($lastMsg['message'], 0, 200) . "\n";
    }
    echo "\n";

} else {
    echo "❌ Failed to show product list\n";
    echo "   Response: " . substr($lastMsg['message'], 0, 200) . "\n";
}

echo "════════════════════════════════\n";
echo "Arabic Numerals:\n";
echo "  ٠=0  ١=1  ٢=2  ٣=3  ٤=4\n";
echo "  ٥=5  ٦=6  ٧=7  ٨=8  ٩=9\n";
