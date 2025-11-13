#!/usr/bin/env php
<?php
/**
 * Clean phone numbers from all customer names in database
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧹 Cleaning Customer Names in Database\n";
echo "══════════════════════════════════════\n\n";

$db = Database::getInstance();

// Get all customers with names
$customers = $db->fetchAll('SELECT id, phone, name FROM customers WHERE name IS NOT NULL AND name != ""');

$updated = 0;

echo "Processing " . count($customers) . " customers...\n\n";

foreach ($customers as $customer) {
    // Clean the name - remove phone numbers
    $cleanName = preg_replace('/\d{2}[\/\s]*\d{3}[\/\s]*\d{3}|\d{8}/', '', $customer['name']);
    $cleanName = trim($cleanName);
    $cleanName = rtrim($cleanName, '. ');

    // Only update if name changed
    if ($cleanName !== $customer['name']) {
        $db->update('customers', ['name' => $cleanName], 'id = :id', ['id' => $customer['id']]);
        echo "✅ {$customer['phone']}: '{$customer['name']}' → '{$cleanName}'\n";
        $updated++;
    }
}

echo "\n";
echo "📊 Summary:\n";
echo "   Total customers: " . count($customers) . "\n";
echo "   Names cleaned: {$updated}\n";
echo "\n✅ Cleanup complete!\n";
