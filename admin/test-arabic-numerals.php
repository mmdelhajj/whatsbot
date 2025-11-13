#!/usr/bin/env php
<?php
/**
 * Test Arabic numerals (٠١٢٣٤٥٦٧٨٩) support
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🔢 ARABIC NUMERALS TEST\n";
echo "═════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// First, show product list
echo "Step 1: Show product list (search for 'pen')\n";
$response = $controller->processIncomingMessage($testPhone, 'pen');
$lastMsg = $db->fetchOne(
    "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

if (strpos($lastMsg['message'], 'قائمة المنتجات') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
    echo "✅ Product list shown\n\n";

    // Test Western numeral
    echo "Step 2: Select product using Western numeral '2'\n";
    $response = $controller->processIncomingMessage($testPhone, '2');
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    if (strpos($lastMsg['message'], 'Product Details') !== false || strpos($lastMsg['message'], 'تفاصيل المنتج') !== false) {
        echo "✅ Western numeral '2' works!\n";
        preg_match('/\*\*(.+?)\*\*/', $lastMsg['message'], $matches);
        if (!empty($matches[1])) {
            echo "   Selected: {$matches[1]}\n";
        }
    } else {
        echo "❌ Western numeral failed\n";
        echo "   Response: " . substr($lastMsg['message'], 0, 100) . "\n";
    }
    echo "\n";

    // Show list again
    echo "Step 3: Show product list again\n";
    $response = $controller->processIncomingMessage($testPhone, 'pen');
    echo "✅ Product list shown\n\n";

    // Test Arabic numeral
    echo "Step 4: Select product using Arabic numeral '٣' (3)\n";
    $response = $controller->processIncomingMessage($testPhone, '٣');
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    if (strpos($lastMsg['message'], 'Product Details') !== false || strpos($lastMsg['message'], 'تفاصيل المنتج') !== false) {
        echo "✅ Arabic numeral '٣' works!\n";
        preg_match('/\*\*(.+?)\*\*/', $lastMsg['message'], $matches);
        if (!empty($matches[1])) {
            echo "   Selected: {$matches[1]}\n";
        }
    } else {
        echo "❌ Arabic numeral failed\n";
        echo "   Response: " . substr($lastMsg['message'], 0, 100) . "\n";
    }
    echo "\n";

} else {
    echo "❌ Failed to show product list\n";
}

echo "═════════════════════════════════\n";
echo "Arabic Numerals Reference:\n";
echo "  ٠ = 0    ١ = 1    ٢ = 2\n";
echo "  ٣ = 3    ٤ = 4    ٥ = 5\n";
echo "  ٦ = 6    ٧ = 7    ٨ = 8\n";
echo "  ٩ = 9\n\n";
echo "✅ Test complete!\n";
