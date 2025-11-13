#!/usr/bin/env php
<?php
/**
 * Test advanced Arabic search with colors and products
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Advanced Arabic Search\n";
echo "═══════════════════════════════════\n\n";

// Test cases
$testCases = [
    "ماذا يوجد اديك قلم أصفر" => ["pen", "yellow"],
    "هل لديك كتاب أحمر" => ["book", "red"],
    "بدي دفتر أزرق" => ["notebook", "blue"],
    "شو عندك بربي" => ["Barbie"],
];

foreach ($testCases as $arabicQuery => $expectedWords) {
    echo "Testing: '{$arabicQuery}'\n";

    // Step 1: Clean the message (remove common words)
    $cleanMessage = preg_replace(
        '/(do you have|are there|is there|what do you have|looking for|need|want|i want|show me|' .
        'هل لديك|هل عندك|هل يوجد|هل تملك|لديك|عندك|اديك|عندكم|لديكم|بدي|بدك|بدنا|' .
        'موجود|يوجد|فيه|أريد|ابحث عن|اريد|بحاجة|ماذا يوجد|ماذا لديك|ماذا عندك|شو عندك|شو فيه|' .
        'je cherche|avez-vous|y a-t-il|je veux|cherche|qu\'avez-vous)/ui',
        '',
        $arabicQuery
    );
    $cleanMessage = trim($cleanMessage);

    echo "  After cleaning: '{$cleanMessage}'\n";

    // Step 2: Apply translation
    $translations = [
        // Popular toys
        'بربي' => 'Barbie',
        'باربي' => 'Barbie',
        // School supplies
        'قلم' => 'pen',
        'كتاب' => 'book',
        'دفتر' => 'notebook',
        'كراس' => 'notebook',
        // Colors
        'أحمر' => 'red',
        'أزرق' => 'blue',
        'أصفر' => 'yellow',
        'أخضر' => 'green',
    ];

    $searchTerm = $cleanMessage;
    foreach ($translations as $foreign => $english) {
        if (stripos($searchTerm, $foreign) !== false) {
            $searchTerm = str_ireplace($foreign, $english, $searchTerm);
        }
    }

    echo "  After translation: '{$searchTerm}'\n";

    // Check if all expected words are in the search term
    $allFound = true;
    foreach ($expectedWords as $expectedWord) {
        if (stripos($searchTerm, $expectedWord) === false) {
            $allFound = false;
            break;
        }
    }

    if ($allFound) {
        echo "  ✅ PASS: Contains all expected words!\n";
    } else {
        echo "  ❌ FAIL: Missing some expected words\n";
        echo "     Expected: " . implode(", ", $expectedWords) . "\n";
    }

    // Search for products
    $productModel = new Product();
    $products = $productModel->search($searchTerm, 5);

    if (!empty($products)) {
        echo "  ✅ Found " . count($products) . " products!\n";
        foreach (array_slice($products, 0, 2) as $product) {
            echo "     • {$product['item_name']}\n";
        }
    } else {
        echo "  ⚠️  No products found (might not exist in database)\n";
    }

    echo "\n";
}

echo "✅ Advanced Arabic search test complete!\n\n";

// Test the actual bot
echo "Full Bot Test: 'ماذا يوجد اديك قلم أصفر'\n";
$controller = new MessageController();
$customer = new Customer();

$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);

$response = $controller->processIncomingMessage($testPhone, "ماذا يوجد اديك قلم أصفر");

if ($response['success']) {
    echo "✅ Bot successfully processed the query!\n";
} else {
    echo "❌ Bot failed: {$response['error']}\n";
}
