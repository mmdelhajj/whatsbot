#!/usr/bin/env php
<?php
/**
 * Test "Welcome Back" greeting for returning customers
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Welcome Back Greeting\n";
echo "═════════════════════════════════\n\n";

$db = Database::getInstance();
$controller = new MessageController();
$customer = new Customer();

// Test with your phone
$testPhone = '03080203';
$customerRecord = $customer->findOrCreateByPhone($testPhone);

echo "📱 Test Customer: {$customerRecord['phone']} ({$customerRecord['name']})\n\n";

// Scenario 1: Fresh greeting (no previous messages or recent)
echo "📍 Scenario 1: First time or recent customer\n";
echo "   Expected: 'Hello [Name]!'\n";

// Clear old test messages
$db->execute("DELETE FROM messages WHERE customer_id = ?", [$customerRecord['id']]);

// Send greeting
$response = $controller->processIncomingMessage($testPhone, "hello");

// Get the sent message
$sentMessage = $db->fetchOne(
    "SELECT * FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

if ($sentMessage && strpos($sentMessage['message'], 'Hello M EL HAJJ') !== false) {
    echo "   ✅ PASS: Got 'Hello M EL HAJJ!'\n\n";
} else {
    echo "   ❌ FAIL\n\n";
}

// Scenario 2: Returning customer (last message was 2 days ago)
echo "📍 Scenario 2: Returning customer (2 days ago)\n";
echo "   Expected: 'Welcome back [Name]!'\n";

// Update the last message to be 2 days old
$twoDaysAgo = date('Y-m-d H:i:s', strtotime('-2 days'));
$db->execute(
    "UPDATE messages SET created_at = ? WHERE customer_id = ? AND direction = 'received'",
    [$twoDaysAgo, $customerRecord['id']]
);

// Send greeting again
$response = $controller->processIncomingMessage($testPhone, "hi");

// Get the new sent message
$sentMessage = $db->fetchOne(
    "SELECT * FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

if ($sentMessage && strpos($sentMessage['message'], 'Welcome back M EL HAJJ') !== false) {
    echo "   ✅ PASS: Got 'Welcome back M EL HAJJ!'\n\n";
} else {
    echo "   ❌ FAIL: Got something else\n";
    echo "   Message: " . substr($sentMessage['message'], 0, 100) . "...\n\n";
}

// Scenario 3: Returning customer (last message was 1 month ago)
echo "📍 Scenario 3: Returning customer (1 month ago)\n";
echo "   Expected: 'Welcome back [Name]!'\n";

// Update the last message to be 1 month old
$oneMonthAgo = date('Y-m-d H:i:s', strtotime('-1 month'));
$db->execute(
    "UPDATE messages SET created_at = ? WHERE customer_id = ? AND direction = 'received' ORDER BY created_at DESC LIMIT 1",
    [$oneMonthAgo, $customerRecord['id']]
);

// Send greeting again
$response = $controller->processIncomingMessage($testPhone, "hello");

// Get the new sent message
$sentMessage = $db->fetchOne(
    "SELECT * FROM messages WHERE customer_id = ? AND direction = 'sent' ORDER BY created_at DESC LIMIT 1",
    [$customerRecord['id']]
);

if ($sentMessage && strpos($sentMessage['message'], 'Welcome back M EL HAJJ') !== false) {
    echo "   ✅ PASS: Got 'Welcome back M EL HAJJ!'\n\n";
} else {
    echo "   ❌ FAIL\n\n";
}

echo "✅ Test complete!\n\n";
echo "💡 How it works:\n";
echo "   • If customer messages within 24 hours: 'Hello [Name]!'\n";
echo "   • If customer returns after 24+ hours: 'Welcome back [Name]!'\n";
