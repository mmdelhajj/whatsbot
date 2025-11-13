#!/usr/bin/env php
<?php
/**
 * Test Arabic product search
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Arabic Product Search\n";
echo "═════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();

// Get test customer
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);

echo "📱 Test Customer: {$customerRecord['name']}\n\n";

// Test 1: Arabic question with English product name
echo "Test 1: 'هل لديك بربي' (Do you have Barbie?)\n";
echo "Expected: Remove 'هل لديك', search for 'بربي' or 'Barbie'\n\n";

// Simulate the search cleaning
$message1 = "هل لديك بربي";
$cleanMessage1 = preg_replace(
    '/(do you have|are there|is there|looking for|need|want|i want|' .
    'هل لديك|هل عندك|هل يوجد|هل تملك|لديك|عندك|بدي|بدك|موجود|أريد|ابحث عن|اريد|' .
    'je cherche|avez-vous|y a-t-il|je veux|cherche)/ui',
    '',
    $message1
);
$cleanMessage1 = trim($cleanMessage1);

echo "Original: '{$message1}'\n";
echo "Cleaned:  '{$cleanMessage1}'\n";

if ($cleanMessage1 === 'بربي' || $cleanMessage1 === 'Barbie') {
    echo "✅ PASS: Successfully extracted product name!\n\n";
} else {
    echo "❌ FAIL: Got '{$cleanMessage1}'\n\n";
}

// Test 2: Arabic question with different phrase
echo "Test 2: 'هل عندك hotwheels' (Do you have hotwheels?)\n";
$message2 = "هل عندك hotwheels";
$cleanMessage2 = preg_replace(
    '/(do you have|are there|is there|looking for|need|want|i want|' .
    'هل لديك|هل عندك|هل يوجد|هل تملك|لديك|عندك|بدي|بدك|موجود|أريد|ابحث عن|اريد|' .
    'je cherche|avez-vous|y a-t-il|je veux|cherche)/ui',
    '',
    $message2
);
$cleanMessage2 = trim($cleanMessage2);

echo "Original: '{$message2}'\n";
echo "Cleaned:  '{$cleanMessage2}'\n";

if ($cleanMessage2 === 'hotwheels') {
    echo "✅ PASS: Successfully extracted product name!\n\n";
} else {
    echo "❌ FAIL: Got '{$cleanMessage2}'\n\n";
}

// Test 3: Check if we have Barbie products in database
echo "Test 3: Searching for Barbie products in database...\n";
$productModel = new Product();
$barbieProducts = $productModel->search('Barbie', 10);

if (!empty($barbieProducts)) {
    echo "✅ Found " . count($barbieProducts) . " Barbie products:\n";
    foreach (array_slice($barbieProducts, 0, 3) as $product) {
        echo "   • {$product['item_name']}\n";
    }
    echo "\n";
} else {
    echo "❌ No Barbie products found in database\n";
    echo "   Note: User might need to use English 'Barbie' not Arabic 'بربي'\n\n";
}

// Test 4: Full bot test
echo "Test 4: Full bot response test\n";
$response = $controller->processIncomingMessage($testPhone, "هل لديك Barbie");

if ($response['success']) {
    echo "✅ Bot processed the message successfully\n";
} else {
    echo "❌ Bot failed: {$response['error']}\n";
}

echo "\n✅ Test complete!\n";
