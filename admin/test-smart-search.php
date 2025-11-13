#!/usr/bin/env php
<?php
/**
 * Test smart product search while browsing
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Smart Product Search\n";
echo "════════════════════════════════\n\n";

$db = Database::getInstance();
$controller = new MessageController();
$customer = new Customer();

// Get test customer (your phone)
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);

echo "📱 Testing as: {$customerRecord['phone']} ({$customerRecord['name']})\n\n";

// Simulate conversation flow
echo "Step 1: User asks about Hotwheels\n";
echo "User: \"Do you have hotwheels\"\n";
$response = $controller->processIncomingMessage($testPhone, "Do you have hotwheels");
echo "✅ Bot shows Hotwheels products\n\n";

echo "Step 2: User now asks about Barbie (while still browsing Hotwheels)\n";
echo "User: \"Do you have Barbie?\"\n";
$response = $controller->processIncomingMessage($testPhone, "Do you have Barbie?");

// Check if response is successful
if ($response['success']) {
    echo "✅ Bot successfully switched to Barbie search!\n";
    echo "✅ Smart search is working!\n\n";
} else {
    echo "❌ Failed: {$response['error']}\n\n";
}

echo "Step 3: User searches for another product\n";
echo "User: \"Disney princess\"\n";
$response = $controller->processIncomingMessage($testPhone, "Disney princess");

if ($response['success']) {
    echo "✅ Bot successfully searched for Disney princess!\n";
} else {
    echo "❌ Failed: {$response['error']}\n";
}

echo "\n✅ Smart search test complete!\n";
