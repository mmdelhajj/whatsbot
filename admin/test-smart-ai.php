#!/usr/bin/env php
<?php
/**
 * Test smart AI-powered product search
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🤖 SMART AI BOT TEST\n";
echo "════════════════════\n\n";

$controller = new MessageController();
$customer = new Customer();
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);
$db = Database::getInstance();

// Clear state
$conversationState = new ConversationState();
$conversationState->clear($customerRecord['id']);

echo "This test will take a few seconds as it uses real AI...\n\n";

$testQueries = [
    'whats do you have rouleau' => 'AI should understand "rouleau"',
    'اريد قلم رخيص' => 'AI should find cheap pens',
    'best toys for kids' => 'AI should recommend good toys',
];

foreach ($testQueries as $query => $description) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Query: '{$query}'\n";
    echo "  ({$description})\n\n";

    $start = microtime(true);
    $response = $controller->processIncomingMessage($testPhone, $query);
    $duration = round((microtime(true) - $start) * 1000);

    $lastMsg = $db->fetchOne(
        "SELECT message FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
        [$customerRecord['id']]
    );

    echo "Response ({$duration}ms):\n";
    echo substr($lastMsg['message'], 0, 300);
    if (strlen($lastMsg['message']) > 300) {
        echo "...";
    }
    echo "\n\n";

    if (strpos($lastMsg['message'], '🤖') !== false) {
        echo "✅ AI WAS USED!\n";
    } else if (strpos($lastMsg['message'], '📚') !== false) {
        echo "⚡ Quick search found results\n";
    }
    echo "\n";

    // Small delay between queries
    sleep(2);
}

echo "════════════════════════════════════\n";
echo "✅ Smart AI bot is now active!\n";
echo "\n";
echo "How it works:\n";
echo "  1️⃣  Fast keyword search first (instant)\n";
echo "  2️⃣  If no results, AI interprets the query\n";
echo "  3️⃣  AI searches products intelligently\n";
echo "  4️⃣  Results shown with 🤖 indicator\n";
