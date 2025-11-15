#!/usr/bin/env php
<?php
/**
 * Real bot test - verify Arabic translations work end-to-end
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "═══════════════════════════════════════════════════\n";
echo "REAL BOT TEST - Arabic Product Search\n";
echo "═══════════════════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

$tests = [
    'ها يوجد لديك دفتر' => 'notebook/cahier',
    'هل لديك بربي' => 'Barbie',
    'شو عندك قلم' => 'pen',
    'ها يوجد لديك دفتر أصفر' => 'yellow notebook',
];

foreach ($tests as $query => $description) {
    echo "Test: '{$query}' ({$description})\n";
    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne("SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1", [$customerRecord['id']]);

    if (strpos($lastMsg['message'], 'قائمة المنتجات') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
        echo "✅ PASS: Products found!\n";
    } else if (strpos($lastMsg['message'], 'لم أجد منتجات') !== false || strpos($lastMsg['message'], "couldn't find") !== false) {
        // Extract the search term from error message
        preg_match('/تطابق "([^"]+)"/', $lastMsg['message'], $matches);
        if (!empty($matches[1])) {
            echo "⚠️  No products (searched for: '{$matches[1]}')\n";
        } else {
            echo "⚠️  No products found\n";
        }
    } else {
        echo "❓ Unexpected response: " . substr($lastMsg['message'], 0, 100) . "\n";
    }
    echo "\n";
}

echo "✅ All real bot tests complete!\n";
