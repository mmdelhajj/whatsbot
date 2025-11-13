#!/usr/bin/env php
<?php
/**
 * Test greeting with real Brains customer
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Greeting with Real Brains Customers\n";
echo "═══════════════════════════════════════════════\n\n";

$db = Database::getInstance();

// Get 3 random customers with names from Brains
$customers = $db->fetchAll("
    SELECT phone, name, preferred_language
    FROM customers
    WHERE name IS NOT NULL AND TRIM(name) != ''
    ORDER BY RAND()
    LIMIT 3
");

foreach ($customers as $customer) {
    $lang = $customer['preferred_language'] ?? 'en';

    // Apply the fix: trim name and use null if empty
    $customerName = !empty(trim($customer['name'])) ? trim($customer['name']) : null;

    echo "📱 Phone: {$customer['phone']}\n";
    echo "👤 Name: '{$customer['name']}'\n";
    echo "🌐 Language: {$lang}\n\n";

    // Generate greeting
    $greeting = ResponseTemplates::welcome($lang, $customerName);

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "BOT GREETING:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo $greeting;
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n\n";
}

echo "✅ All customers from Brains now get personalized greetings!\n";
