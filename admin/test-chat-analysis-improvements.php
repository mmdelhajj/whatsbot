#!/usr/bin/env php
<?php
/**
 * Test bot improvements based on WhatsApp chat analysis
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 TESTING CHAT ANALYSIS IMPROVEMENTS\n";
echo "════════════════════════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();
$conversationState = new ConversationState();

// Test queries based on the chat analysis report
$tests = [
    // FAQ queries
    ['query' => 'cava', 'description' => 'How are you (Lebanese)', 'expected' => 'friendly response'],
    ['query' => 'kifak', 'description' => 'How are you (Arabic)', 'expected' => 'friendly response'],
    ['query' => 'hours', 'description' => 'Opening hours', 'expected' => 'hours info'],
    ['query' => 'sa3et', 'description' => 'Opening hours (Lebanese)', 'expected' => 'hours info'],
    ['query' => 'maftouh', 'description' => 'Are you open (Lebanese)', 'expected' => 'hours info'],
    ['query' => 'location', 'description' => 'Location query', 'expected' => 'Kfarhbab, Ghazir'],
    ['query' => 'wen', 'description' => 'Where (Lebanese)', 'expected' => 'location info'],
    ['query' => 'delivery', 'description' => 'Delivery info', 'expected' => 'Aramex info'],
    ['query' => 'tewsil', 'description' => 'Delivery (Lebanese)', 'expected' => 'Aramex info'],

    // Greetings
    ['query' => 'Bonjour', 'description' => 'French greeting', 'expected' => 'welcome message'],
    ['query' => 'Hello!', 'description' => 'Greeting with exclamation', 'expected' => 'welcome message'],
    ['query' => 'Hii', 'description' => 'Casual greeting', 'expected' => 'welcome message'],

    // Lebanese Arabic product queries
    ['query' => 'fi plush', 'description' => 'Is there plush (Lebanese)', 'expected' => 'plush products'],
    ['query' => 'kteb', 'description' => 'Book (Lebanese)', 'expected' => 'book products'],
    ['query' => 'mesta3mal', 'description' => 'Used (Lebanese)', 'expected' => 'used products'],
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $conversationState->clear($customerRecord['id']);

    $response = $controller->processIncomingMessage($testPhone, $test['query']);
    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    $responseText = $lastMsg['message'];
    $hasResponse = !empty($responseText);

    // Check if response matches expected pattern
    $success = false;
    switch ($test['expected']) {
        case 'friendly response':
            $success = (stripos($responseText, 'help') !== false ||
                       stripos($responseText, 'مساعد') !== false ||
                       stripos($responseText, '😊') !== false);
            break;
        case 'hours info':
            $success = (stripos($responseText, '7:00') !== false ||
                       stripos($responseText, 'Mon-Fri') !== false ||
                       stripos($responseText, 'Opening Hours') !== false ||
                       stripos($responseText, 'Horaires') !== false);
            break;
        case 'Kfarhbab, Ghazir':
        case 'location info':
            $success = (stripos($responseText, 'Kfarhbab') !== false ||
                       stripos($responseText, 'Ghazir') !== false ||
                       stripos($responseText, 'Location') !== false ||
                       stripos($responseText, 'Localisation') !== false);
            break;
        case 'Aramex info':
            $success = (stripos($responseText, 'Aramex') !== false ||
                       stripos($responseText, 'Delivery') !== false ||
                       stripos($responseText, 'livraison') !== false ||
                       stripos($responseText, '3 days') !== false);
            break;
        case 'welcome message':
            $success = (stripos($responseText, 'Welcome') !== false ||
                       stripos($responseText, 'Bienvenue') !== false ||
                       stripos($responseText, 'مرحباً') !== false ||
                       stripos($responseText, 'help') !== false);
            break;
        case 'plush products':
            $success = (stripos($responseText, 'Plush') !== false ||
                       stripos($responseText, 'Product List') !== false);
            break;
        case 'book products':
            $success = (stripos($responseText, 'book') !== false ||
                       stripos($responseText, 'Product') !== false ||
                       stripos($responseText, 'livre') !== false);
            break;
        case 'used products':
            $success = (stripos($responseText, 'used') !== false ||
                       stripos($responseText, 'Product') !== false);
            break;
    }

    if ($success && $hasResponse) {
        echo "✅ '{$test['query']}' → {$test['description']}\n";
        $passed++;
    } else {
        echo "❌ '{$test['query']}' → {$test['description']}\n";
        echo "   Response: " . substr($responseText, 0, 100) . "...\n";
        $failed++;
    }
}

echo "\n════════════════════════════════════════\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "════════════════════════════════════════\n\n";

$percentage = round(($passed / count($tests)) * 100);
echo "Success Rate: {$percentage}%\n\n";

if ($percentage >= 80) {
    echo "🎉 EXCELLENT! Bot is following chat analysis best practices!\n";
} else if ($percentage >= 60) {
    echo "👍 GOOD! Most improvements are working!\n";
} else {
    echo "⚠️  Some features need adjustment\n";
}
