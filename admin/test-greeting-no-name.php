#!/usr/bin/env php
<?php
/**
 * Test greeting WITHOUT name (for customers not in Brains)
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "Testing greeting for customer WITHOUT name in database:\n\n";

// Simulate customer with empty/whitespace name
$customer = [
    'id' => 999,
    'phone' => '+96199999999',
    'name' => '  ', // whitespace only
    'preferred_language' => 'en'
];

echo "📱 Phone: {$customer['phone']}\n";
echo "👤 Name in DB: '{$customer['name']}' (whitespace only)\n\n";

// Apply the fix: trim name and use null if empty
$customerName = !empty(trim($customer['name'])) ? trim($customer['name']) : null;

echo "📝 Name after trim: " . ($customerName ? "'{$customerName}'" : "NULL") . "\n\n";

// Generate greeting
$greeting = ResponseTemplates::welcome('en', $customerName);

echo "═══════════════════════════════════════\n";
echo "BOT RESPONSE (without name):\n";
echo "═══════════════════════════════════════\n";
echo $greeting;
echo "\n═══════════════════════════════════════\n\n";

// Now test with actual name
echo "Now testing WITH name 'Tony':\n\n";
$greeting2 = ResponseTemplates::welcome('en', 'Tony');

echo "═══════════════════════════════════════\n";
echo "BOT RESPONSE (with name):\n";
echo "═══════════════════════════════════════\n";
echo $greeting2;
echo "\n═══════════════════════════════════════\n";
