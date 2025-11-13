#!/usr/bin/env php
<?php
/**
 * Test Order Status Notification
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Order Status Notification System\n";
echo "==========================================\n\n";

$db = Database::getInstance();

// Get the most recent order
$order = $db->fetchOne("
    SELECT o.*, c.phone, c.preferred_language, c.name as customer_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    ORDER BY o.created_at DESC
    LIMIT 1
");

if (!$order) {
    echo "❌ No orders found in the database.\n";
    exit;
}

// Get order items
$order['items'] = $db->fetchAll(
    "SELECT * FROM order_items WHERE order_id = ?",
    [$order['id']]
);

echo "📦 Testing with Order:\n";
echo "   Order #: {$order['order_number']}\n";
echo "   Customer: {$order['customer_name']}\n";
echo "   Phone: {$order['phone']}\n";
echo "   Current Status: {$order['status']}\n";
echo "   Language: " . ($order['preferred_language'] ?? 'en') . "\n\n";

// Test different status messages
$statuses = ['confirmed', 'preparing', 'on_the_way', 'delivered'];

foreach ($statuses as $status) {
    echo "📝 Testing status: {$status}\n";
    echo "----------------------------------------\n";

    $lang = $order['preferred_language'] ?? 'en';
    $message = ResponseTemplates::orderStatusNotification($lang, $order, $status);

    echo $message;
    echo "\n\n";
}

echo "✅ Test completed!\n\n";

// Ask if user wants to send a real notification
echo "💬 Do you want to send a REAL notification for status 'confirmed'? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) == 'yes') {
    echo "\n📤 Sending notification...\n";

    try {
        $proxSMS = new ProxSMSService();
        $lang = $order['preferred_language'] ?? 'en';
        $message = ResponseTemplates::orderStatusNotification($lang, $order, 'confirmed');

        $result = $proxSMS->sendMessage($order['phone'], $message);

        if ($result['success']) {
            echo "✅ Notification sent successfully!\n";
        } else {
            echo "❌ Failed to send notification: " . ($result['error'] ?? 'Unknown error') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✅ No notification sent.\n";
}
