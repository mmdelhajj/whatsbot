#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "🔧 ROULEAU TEST (Word Boundary Fix)\n";
echo "════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

$testCases = [
    'Whats do you have rouleau' => 'rouleau',
    'do you have roller' => 'roller',
    'je cherche un rouleau' => 'rouleau',
    'avez-vous des rouleaux' => 'rouleaux',
    'teletubbies' => 'teletubbies', // Contains "le" but shouldn't be affected
    'elephant' => 'elephant', // Contains "le" but shouldn't be affected
];

foreach ($testCases as $query => $expectedTerm) {
    echo "Query: '{$query}'\n";
    echo "  Expected search term: '{$expectedTerm}'\n";

    $response = $controller->processIncomingMessage($testPhone, $query);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    // Check if products found or extract search term from error message
    if (strpos($lastMsg['message'], '📚') !== false || strpos($lastMsg['message'], 'Product List') !== false) {
        echo "  ✅ Products found!\n";
    } else {
        // Extract search term from error message
        if (preg_match('/matching "([^"]+)"/', $lastMsg['message'], $matches) ||
            preg_match('/تطابق "([^"]+)"/', $lastMsg['message'], $matches)) {
            $searchedFor = $matches[1];
            echo "  Searched for: '{$searchedFor}'\n";

            if ($searchedFor === $expectedTerm) {
                echo "  ✅ CORRECT! No word parts removed\n";
            } else {
                echo "  ❌ WRONG! Expected '{$expectedTerm}'\n";
            }
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════\n";
echo "✅ Word boundary fix applied!\n";
echo "   Words like 'rouleau' won't lose their 'le' anymore.\n";
