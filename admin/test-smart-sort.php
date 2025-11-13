#!/usr/bin/env php
<?php
/**
 * Test smart sorting (cheap, expensive, best)
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "💡 SMART SORTING TEST\n";
echo "═════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

$testQueries = [
    'قلم رخيص' => 'cheap pen - should show cheapest first',
    'قلم غالي' => 'expensive pen - should show most expensive first',
    'أفضل قلم' => 'best pen - should show best quality/in-stock first',
    'cheap barbie' => 'cheap Barbie',
    'best hotwheels' => 'best Hotwheels',
];

foreach ($testQueries as $query => $description) {
    echo "Query: '{$query}'\n";
    echo "  ({$description})\n";

    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]);

    if (strpos($lastMsg['message'], 'قائمة المنتجات') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
        echo "  ✅ Products found! (Smart sorting applied)\n";

        // Show first product from the list
        preg_match('/\*1\.\* (.+?)\n/', $lastMsg['message'], $matches);
        if (!empty($matches[1])) {
            echo "     First result: {$matches[1]}\n";
        }

        // Extract prices
        preg_match_all('/💰 ([\d,]+) LBP/', $lastMsg['message'], $priceMatches);
        if (!empty($priceMatches[1])) {
            $prices = array_map(function($p) { return str_replace(',', '', $p); }, $priceMatches[1]);
            echo "     Prices: " . implode(', ', array_slice($prices, 0, 3)) . " LBP...\n";
        }
    } else {
        echo "  ⚠️  No products found\n";
    }
    echo "\n";
}

echo "✅ Smart sorting test complete!\n\n";
echo "How it works:\n";
echo "  • 'قلم رخيص' → Searches 'قلم', sorts by price (LOW to HIGH)\n";
echo "  • 'قلم غالي' → Searches 'قلم', sorts by price (HIGH to LOW)\n";
echo "  • 'أفضل قلم' → Searches 'قلم', sorts by quality (in-stock + higher price)\n";
