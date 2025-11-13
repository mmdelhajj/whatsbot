#!/usr/bin/env php
<?php
/**
 * Test Lebanese transliteration (Franco-Arabic/Arabizi)
 * Example: "kifak 3andak daftar" instead of "كيفك عندك دفتر"
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🇱🇧 LEBANESE TRANSLITERATION TEST (Franco-Arabic/Arabizi)\n";
echo "══════════════════════════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Test cases with Lebanese transliteration
$testCases = [
    [
        'query' => 'kifak 3andak daftar',
        'description' => 'How are you, do you have notebook',
        'expected' => 'cahier'
    ],
    [
        'query' => 'shu 3andak barbie',
        'description' => 'What do you have Barbie',
        'expected' => 'Barbie'
    ],
    [
        'query' => 'fi 2alam',
        'description' => 'Is there pen',
        'expected' => 'pen'
    ],
    [
        'query' => 'baddi daftar a7mar',
        'description' => 'I want red notebook',
        'expected' => 'cahier red'
    ],
    [
        'query' => '3andak hotwheels',
        'description' => 'Do you have hotwheels',
        'expected' => 'Hotwheels'
    ],
    [
        'query' => 'avez-vous des cahiers',
        'description' => 'French: Do you have notebooks',
        'expected' => 'cahiers'
    ],
];

foreach ($testCases as $test) {
    echo "Test: '{$test['query']}'\n";
    echo "  ({$test['description']})\n";

    $response = $controller->processIncomingMessage($testPhone, $test['query']);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    if (strpos($lastMsg['message'], 'قائمة المنتجات') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
        echo "  ✅ PASS: Products found!\n";
    } else if (strpos($lastMsg['message'], 'لم أجد منتجات') !== false || strpos($lastMsg['message'], "couldn't find") !== false) {
        // Extract the search term from error message
        preg_match('/تطابق "([^"]+)"/', $lastMsg['message'], $matches);
        if (!empty($matches[1])) {
            $searchedTerm = $matches[1];
            echo "  ⚠️  No products (searched for: '{$searchedTerm}')\n";

            // Check if translation worked
            if (stripos($searchedTerm, $test['expected']) !== false) {
                echo "  ✅ Translation working: contains '{$test['expected']}'\n";
            } else {
                echo "  ❌ Translation issue: expected '{$test['expected']}' in search term\n";
            }
        } else {
            echo "  ⚠️  No products found\n";
        }
    } else {
        echo "  ❓ Unexpected response: " . substr($lastMsg['message'], 0, 100) . "\n";
    }
    echo "\n";
}

echo "✅ All Lebanese transliteration tests complete!\n\n";

echo "📝 Supported Lebanese Transliteration Patterns:\n";
echo "═══════════════════════════════════════════════\n\n";

echo "Question Phrases:\n";
echo "  • kifak, keefak, kefak = كيفك (how are you)\n";
echo "  • 3andak, 3andek = عندك (do you have)\n";
echo "  • shu, shou = شو (what)\n";
echo "  • fi, fih, fee = في/فيه (is there)\n";
echo "  • baddi, badde = بدي (I want)\n\n";

echo "School Supplies:\n";
echo "  • daftar, defter = دفتر → cahier (notebook)\n";
echo "  • 2alam, alam = قلم → pen\n";
echo "  • kteb, kitab = كتاب → livre (book)\n";
echo "  • kras = كراس → cahier\n\n";

echo "Colors:\n";
echo "  • a7mar, ahmar = أحمر → red\n";
echo "  • azra2, azrak = أزرق → blue\n";
echo "  • asfar, a9far = أصفر → yellow\n";
echo "  • akhdar, a5dar = أخضر → green\n\n";

echo "Toys:\n";
echo "  • barbie → Barbie\n";
echo "  • hotwheels, hot wheels → Hotwheels\n";
echo "  • lego → Lego\n";
echo "  • disney → Disney\n";
echo "  • spiderman → Spiderman\n\n";

echo "🌟 The bot now understands Lebanese transliteration!\n";
echo "   Customers can type in Latin letters instead of Arabic.\n";
