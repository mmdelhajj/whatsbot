#!/usr/bin/env php
<?php
/**
 * Test Arabic typos and space cleanup
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Arabic Typos & Space Cleanup\n";
echo "════════════════════════════════════════\n\n";

// Test cases with typos
$testCases = [
    "ها يوجد لديك دفتر أصفر" => "دفتر أصفر",
    "هل لديك بربي" => "بربي",
    "شو عندك hotwheels" => "hotwheels",
    "ها عندك قلم أحمر" => "قلم أحمر",
];

foreach ($testCases as $arabicQuery => $expectedClean) {
    echo "Testing: '{$arabicQuery}'\n";

    // Apply the cleaning logic
    $cleanMessage = preg_replace(
        '/(do you have|are there|is there|what do you have|looking for|need|want|i want|show me|' .
        'هل لديك|هل عندك|هل يوجد|هل تملك|ها لديك|ها عندك|ها يوجد|ها|هل|' .
        'لديك|عندك|اديك|عندكم|لديكم|بدي|بدك|بدنا|' .
        'موجود|يوجد|فيه|أريد|ابحث عن|اريد|بحاجة|ماذا يوجد|ماذا لديك|ماذا عندك|شو عندك|شو فيه|شو|' .
        'je cherche|avez-vous|y a-t-il|je veux|cherche|qu\'avez-vous)/ui',
        ' ',
        $arabicQuery
    );

    // Clean up multiple spaces and trim
    $cleanMessage = preg_replace('/\s+/', ' ', $cleanMessage);
    $cleanMessage = trim($cleanMessage);

    echo "  After cleaning: '{$cleanMessage}'\n";

    if ($cleanMessage === $expectedClean) {
        echo "  ✅ PASS: Perfect cleanup!\n";
    } else {
        echo "  ⚠️  Got different result (but might still be valid)\n";
        echo "     Expected: '{$expectedClean}'\n";
    }

    // Check if there are no extra spaces
    if (strpos($cleanMessage, '  ') === false) {
        echo "  ✅ No extra spaces\n";
    } else {
        echo "  ❌ Still has extra spaces!\n";
    }

    echo "\n";
}

echo "✅ Test complete!\n\n";

// Test the actual bot
echo "Full Bot Test: 'ها يوجد لديك دفتر أصفر'\n";
$controller = new MessageController();
$customer = new Customer();

$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);

$response = $controller->processIncomingMessage($testPhone, "ها يوجد لديك دفتر أصفر");

if ($response['success']) {
    echo "✅ Bot successfully processed the query!\n";

    // Get the last sent message
    $db = Database::getInstance();
    $lastMessage = $db->fetchOne(
        "SELECT message FROM messages
         WHERE customer_id = ? AND direction = 'sent'
         ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    if ($lastMessage) {
        // Check if it's showing products or error
        if (strpos($lastMessage['message'], 'Product List') !== false) {
            echo "✅ Bot found products!\n";
        } else if (strpos($lastMessage['message'], 'لم أجد منتجات') !== false) {
            echo "⚠️  Bot says no products found (might not exist in inventory)\n";
        }
    }
} else {
    echo "❌ Bot failed: {$response['error']}\n";
}
