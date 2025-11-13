#!/usr/bin/env php
<?php
/**
 * Test greeting with user's phone number
 */

require_once dirname(__DIR__) . '/config/config.php';

$db = Database::getInstance();

// Get customer
$customer = $db->fetchOne("SELECT * FROM customers WHERE phone = '+9613080203'");

echo "📱 Testing greeting for: {$customer['phone']}\n";
echo "👤 Customer Name: '{$customer['name']}'\n";
echo "🌐 Preferred Language: {$customer['preferred_language']}\n\n";

// Test the greeting logic from MessageController
$lang = $customer['preferred_language'] ?? 'en';

// Apply the fix: trim name and use null if empty
$customerName = !empty(trim($customer['name'])) ? trim($customer['name']) : null;

echo "📝 Name after trim check: " . ($customerName ? "'{$customerName}'" : "NULL (will show generic greeting)") . "\n\n";

// Generate greeting
$greeting = ResponseTemplates::welcome($lang, $customerName);

echo "═══════════════════════════════════════\n";
echo "BOT RESPONSE:\n";
echo "═══════════════════════════════════════\n";
echo $greeting;
echo "\n═══════════════════════════════════════\n";
