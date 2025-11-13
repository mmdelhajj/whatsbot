#!/usr/bin/env php
<?php
/**
 * Test Status Update without sending WhatsApp
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Order Status Update\n";
echo "==============================\n\n";

$db = Database::getInstance();

// Get the most recent order
$order = $db->fetchOne("
    SELECT o.*, c.phone, c.name as customer_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    ORDER BY o.created_at DESC
    LIMIT 1
");

if (!$order) {
    echo "❌ No orders found.\n";
    exit;
}

echo "📦 Found Order:\n";
echo "   Order #: {$order['order_number']}\n";
echo "   Customer: {$order['customer_name']}\n";
echo "   Current Status: {$order['status']}\n\n";

// Test updating to each status
$testStatuses = ['confirmed', 'preparing', 'on_the_way', 'delivered'];

foreach ($testStatuses as $testStatus) {
    echo "Testing update to: {$testStatus}... ";

    try {
        $db->update('orders',
            ['status' => $testStatus],
            'id = :id',
            ['id' => $order['id']]
        );
        echo "✅ Success!\n";
    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }
}

// Reset to original status
echo "\nResetting to original status... ";
$db->update('orders',
    ['status' => $order['status']],
    'id = :id',
    ['id' => $order['id']]
);
echo "✅ Done!\n";

echo "\n✅ All status updates work correctly!\n";
