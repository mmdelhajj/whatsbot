<?php
/**
 * Test Complete Webhook Flow
 * Simulates a webhook message and tests the full response cycle
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Testing Complete Webhook Flow ===\n\n";

// Test data simulating ProxSMS webhook
$testPhone = " 9613080203"; // With leading space like ProxSMS sends
$testMessage = "hello";

echo "📥 Simulating incoming webhook message:\n";
echo "   Phone: '$testPhone'\n";
echo "   Message: '$testMessage'\n\n";

try {
    // Create MessageController
    $controller = new MessageController();

    echo "⚙️  Processing message through MessageController...\n\n";

    // Process the message (same as webhook does)
    $result = $controller->processIncomingMessage($testPhone, $testMessage);

    if ($result['success']) {
        echo "✅ SUCCESS!\n\n";
        echo "   Customer ID: {$result['customer_id']}\n";
        echo "   Status: Message received, processed, and response sent\n\n";

        // Check logs
        echo "📋 Recent webhook logs:\n";
        $webhookLog = file_get_contents(WEBHOOK_LOG_FILE);
        $lines = explode("\n", $webhookLog);
        $recentLines = array_slice($lines, -5);
        foreach ($recentLines as $line) {
            if (!empty(trim($line))) {
                echo "   " . $line . "\n";
            }
        }

    } else {
        echo "❌ FAILED\n\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
