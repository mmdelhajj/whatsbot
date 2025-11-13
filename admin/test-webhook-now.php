<?php
/**
 * Test Webhook After Fix
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Testing Webhook After Fix ===\n\n";

// Test that classes load
echo "1. Testing class loading:\n";
try {
    $detector = new LanguageDetector();
    echo "   ✅ LanguageDetector loaded\n";
} catch (Exception $e) {
    echo "   ❌ LanguageDetector failed: " . $e->getMessage() . "\n";
}

try {
    $state = new ConversationState();
    echo "   ✅ ConversationState loaded\n";
} catch (Exception $e) {
    echo "   ❌ ConversationState failed: " . $e->getMessage() . "\n";
}

try {
    $templates = new ResponseTemplates();
    echo "   ✅ ResponseTemplates loaded\n";
} catch (Exception $e) {
    echo "   ❌ ResponseTemplates failed: " . $e->getMessage() . "\n";
}

// Test message processing
echo "\n2. Testing message flow:\n";
$controller = new MessageController();
$testPhone = "+9613080203";

echo "   Sending 'hello'...\n";
$result = $controller->processIncomingMessage($testPhone, 'hello');

if ($result['success']) {
    echo "   ✅ Message processed successfully!\n";
    echo "   Customer ID: {$result['customer_id']}\n";
} else {
    echo "   ❌ Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
}

echo "\n✅ All tests complete!\n";
