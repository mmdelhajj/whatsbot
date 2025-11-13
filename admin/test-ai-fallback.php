#!/usr/bin/env php
<?php
/**
 * Test AI fallback for descriptive queries
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🤖 AI FALLBACK TEST\n";
echo "═══════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

$testQueries = [
    'قلم رخيص' => 'cheap pen (should use AI)',
    'أفضل قلم' => 'best pen (should use AI)',
    'قلم جيد' => 'good pen (should use AI)',
    'قلم' => 'just pen (direct search)',
];

foreach ($testQueries as $query => $description) {
    echo "Query: '{$query}'\n";
    echo "  ({$description})\n";

    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    // Check if AI was used or direct search
    if (strpos($lastMsg['message'], '🤖') !== false) {
        echo "  ✅ AI was used!\n";
    } else if (strpos($lastMsg['message'], 'قائمة المنتجات') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
        echo "  ✅ Direct search found products\n";
    } else if (strpos($lastMsg['message'], 'لم أجد منتجات') !== false) {
        echo "  ⚠️  Direct search, no products\n";
    } else {
        echo "  📋 Response: " . substr($lastMsg['message'], 0, 100) . "...\n";
    }
    echo "\n";

    // Small delay to see the processing
    sleep(1);
}

echo "✅ Test complete!\n\n";
echo "Expected behavior:\n";
echo "  • 'قلم رخيص' (cheap pen) → Should use AI to find cheapest\n";
echo "  • 'أفضل قلم' (best pen) → Should use AI to recommend\n";
echo "  • 'قلم' (just pen) → Direct database search\n";
