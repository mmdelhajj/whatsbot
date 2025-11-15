<?php
/**
 * Test Fast Product Search (No AI)
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Testing Fast Product Search (NO AI) ===\n\n";

$controller = new MessageController();
$testPhone = "+9613080203";

// Test scenarios
$tests = [
    "Do you have lego" => "Should find LEGO items",
    "whiteboard" => "Should find whiteboard items",
    "small whiteboard" => "Should find small whiteboard",
    "قلم" => "Should find pens (Arabic)",
    "livre" => "Should find books (French)"
];

foreach ($tests as $message => $expected) {
    echo "👤 Customer: \"{$message}\"\n";
    echo "   Expected: {$expected}\n";

    $result = $controller->processIncomingMessage($testPhone, $message);

    if ($result['success']) {
        echo "   ✅ Success!\n";

        // Get last message
        $messageModel = new Message();
        $recent = $messageModel->getAllWithCustomers(1);

        if (!empty($recent)) {
            $response = $recent[0]['message'];

            // Check if it contains product list
            if (strpos($response, '📚') !== false && preg_match('/\d+\./', $response)) {
                echo "   ✅ Product list shown (FAST, NO AI)\n";

                // Count products
                preg_match_all('/\*\d+\.\*/', $response, $matches);
                $productCount = count($matches[0]);
                echo "   📦 Found {$productCount} products\n";
            } else if (strpos($response, '❌') !== false) {
                echo "   ℹ️  No products found message\n";
            } else {
                echo "   ⚠️  Response:\n";
                echo "   " . substr($response, 0, 200) . "...\n";
            }
        }
    } else {
        echo "   ❌ Failed: " . ($result['error'] ?? 'Unknown') . "\n";
    }

    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "✅ All tests complete!\n";
