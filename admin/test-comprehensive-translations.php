#!/usr/bin/env php
<?php
/**
 * Test comprehensive product translations across all languages
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🌍 COMPREHENSIVE TRANSLATION TEST\n";
echo "═══════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

$tests = [
    // School supplies - Arabic
    'مبراة' => 'sharpener (Arabic)',
    'مقص' => 'scissors (Arabic)',
    'فرشاة' => 'brush (Arabic)',
    'ألوان' => 'colors (Arabic)',
    'شنطة' => 'backpack (Arabic)',

    // School supplies - Lebanese
    'shanta' => 'backpack (Lebanese)',
    'farsha' => 'brush (Lebanese)',
    'mabra' => 'sharpener (Lebanese)',

    // School supplies - French
    'cartable' => 'backpack (French)',
    'pinceau' => 'brush (French)',
    'ciseaux' => 'scissors (French)',
    'couleurs' => 'colors (French)',

    // Colors - French
    'rouge' => 'red (French)',
    'rose' => 'pink (French)',

    // Colors - Arabic
    'وردي' => 'pink (Arabic)',
    'بنفسجي' => 'purple (Arabic)',

    // Already working
    'rouleau' => 'tape (French)',
    'barbie' => 'Barbie (English)',
];

$passed = 0;
$failed = 0;

foreach ($tests as $query => $description) {
    $conversationState = new ConversationState();
    $conversationState->clear($customerRecord['id']);

    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    $hasProducts = (strpos($lastMsg['message'], '📚') !== false ||
                    strpos($lastMsg['message'], 'Product List') !== false ||
                    strpos($lastMsg['message'], 'قائمة المنتجات') !== false);

    if ($hasProducts) {
        echo "✅ '{$query}' → {$description} - Found products!\n";
        $passed++;
    } else {
        echo "❌ '{$query}' → {$description} - No products\n";
        $failed++;
    }
}

echo "\n═══════════════════════════════════\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "═══════════════════════════════════\n\n";

$percentage = round(($passed / count($tests)) * 100);
echo "Success Rate: {$percentage}%\n\n";

if ($percentage >= 80) {
    echo "🎉 EXCELLENT! Most translations are working!\n";
} else if ($percentage >= 60) {
    echo "👍 GOOD! Many translations are working!\n";
} else {
    echo "⚠️  Some translations need adjustment\n";
}
