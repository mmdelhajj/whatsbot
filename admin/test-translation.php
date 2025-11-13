#!/usr/bin/env php
<?php
/**
 * Test Arabic to English product name translation
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Product Name Translation\n";
echo "═══════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();

// Get test customer
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);

echo "📱 Test Customer: {$customerRecord['name']}\n\n";

// Test cases
$testCases = [
    "هل لديك بربي" => "Barbie",
    "هل عندك هوتويلز" => "Hotwheels",
    "بدي ديزني" => "Disney",
    "أريد ليغو" => "Lego",
];

foreach ($testCases as $arabicQuery => $expectedEnglish) {
    echo "Testing: '{$arabicQuery}'\n";

    // Clean the message (remove common words)
    $cleanMessage = preg_replace(
        '/(do you have|are there|is there|looking for|need|want|i want|' .
        'هل لديك|هل عندك|هل يوجد|هل تملك|لديك|عندك|بدي|بدك|موجود|أريد|ابحث عن|اريد|' .
        'je cherche|avez-vous|y a-t-il|je veux|cherche)/ui',
        '',
        $arabicQuery
    );
    $cleanMessage = trim($cleanMessage);

    echo "  After cleaning: '{$cleanMessage}'\n";

    // Apply translation
    $translations = [
        'بربي' => 'Barbie',
        'باربي' => 'Barbie',
        'هوتويلز' => 'Hotwheels',
        'هوت ويلز' => 'Hotwheels',
        'ديزني' => 'Disney',
        'ليغو' => 'Lego',
        'ليجو' => 'Lego',
    ];

    $searchTerm = $cleanMessage;
    foreach ($translations as $foreign => $english) {
        if (stripos($cleanMessage, $foreign) !== false) {
            $searchTerm = str_ireplace($foreign, $english, $cleanMessage);
            break;
        }
    }

    echo "  After translation: '{$searchTerm}'\n";

    if ($searchTerm === $expectedEnglish) {
        echo "  ✅ PASS\n";
    } else {
        echo "  ❌ FAIL (expected '{$expectedEnglish}')\n";
    }

    // Search for products
    $productModel = new Product();
    $products = $productModel->search($searchTerm, 3);

    if (!empty($products)) {
        echo "  ✅ Found " . count($products) . " products!\n";
        foreach ($products as $product) {
            echo "     • {$product['item_name']}\n";
        }
    } else {
        echo "  ❌ No products found\n";
    }

    echo "\n";
}

echo "✅ Translation test complete!\n\n";

// Full bot test with Arabic
echo "Full bot test: 'هل لديك بربي'\n";
$response = $controller->processIncomingMessage($testPhone, "هل لديك بربي");

if ($response['success']) {
    echo "✅ Bot successfully processed Arabic query!\n";

    // Get the response message
    $db = Database::getInstance();
    $lastMessage = $db->fetchOne(
        "SELECT message FROM messages
         WHERE customer_id = ? AND direction = 'sent'
         ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    if ($lastMessage && strpos($lastMessage['message'], 'Barbie') !== false) {
        echo "✅ Response contains Barbie products!\n";
    }
} else {
    echo "❌ Bot failed: {$response['error']}\n";
}
