#!/usr/bin/env php
<?php
/**
 * Test French language support
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🇫🇷 FRENCH LANGUAGE TEST\n";
echo "═══════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

$queries = [
    'avez-vous des cahiers' => 'Do you have notebooks',
    'je cherche un stylo' => 'I am looking for a pen',
    'vous avez des livres' => 'Do you have books',
    'est-ce que vous avez du Barbie' => 'Do you have Barbie',
];

foreach ($queries as $query => $translation) {
    echo "Query: '{$query}'\n";
    echo "  ({$translation})\n";

    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    if (strpos($lastMsg['message'], 'قائمة المنتجات') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
        echo "  ✅ Products found!\n";
    } else {
        preg_match('/matching "([^"]+)"/', $lastMsg['message'], $matches);
        if (!empty($matches[1])) {
            echo "  ⚠️  Searched for: '{$matches[1]}'\n";
        } else {
            echo "  ❓ Unexpected response\n";
        }
    }
    echo "\n";
}

echo "✅ French language test complete!\n";
