<?php
/**
 * Test New State-Based Bot Flow
 * Tests language detection, product catalog, and order flow
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Testing New State-Based Bot Flow ===\n\n";

$testPhone = "9613080203";
$controller = new MessageController();

// Test scenarios
$scenarios = [
    // English greeting
    [
        'lang' => 'English',
        'messages' => [
            'hello',
            'products',
            '1',  // Select product 1
            'John Doe',  // Name
            'john@example.com',  // Email
            '123 Main St, Tripoli'  // Address
        ]
    ],

    // Arabic greeting
    [
        'lang' => 'Arabic',
        'messages' => [
            'مرحبا',
            'منتجات',
            '2',  // Select product 2
            'أحمد محمد',  // Name
            'ahmad@example.com',  // Email
            'طرابلس، شارع المعرض'  // Address
        ]
    ],

    // French greeting
    [
        'lang' => 'French',
        'messages' => [
            'bonjour',
            'produits',
            '1',  // Select product 1
            'Marie Dubois',  // Name
            'marie@example.com',  // Email
            'Rue des Livres, Tripoli'  // Address
        ]
    ]
];

// Run first scenario (English)
$scenario = $scenarios[0];
echo "📝 Testing {$scenario['lang']} Flow:\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($scenario['messages'] as $index => $message) {
    echo "👤 Customer: {$message}\n";

    $result = $controller->processIncomingMessage($testPhone, $message);

    if ($result['success']) {
        // Get the last sent message
        $messageModel = new Message();
        $recentMessages = $messageModel->getAllWithCustomers(1);

        if (!empty($recentMessages)) {
            $botResponse = $recentMessages[0]['message'];

            // Truncate long responses
            if (strlen($botResponse) > 300) {
                $botResponse = substr($botResponse, 0, 300) . "...\n[Message truncated]";
            }

            echo "🤖 Bot: {$botResponse}\n";
        }
    } else {
        echo "❌ Error: {$result['error']}\n";
    }

    echo "\n" . str_repeat("-", 60) . "\n\n";

    // Pause between messages
    usleep(500000); // 0.5 seconds
}

echo "\n✅ Test Complete!\n\n";

// Show conversation state
$conversationState = new ConversationState();
$state = $conversationState->get($result['customer_id']);

echo "📊 Final Conversation State:\n";
echo "   State: {$state['last_intent']}\n";
echo "   Language: " . ($state['data']['language'] ?? 'N/A') . "\n";

// Show recent orders
echo "\n📦 Recent Orders:\n";
$orderModel = new Order();
$recentOrders = $orderModel->getAllWithCustomers(3);

foreach ($recentOrders as $order) {
    echo "   Order #{$order['order_number']} - {$order['customer_name']} - " .
         number_format($order['total_amount'], 0) . " LBP\n";
}

echo "\n=== All Tests Complete ===\n";
