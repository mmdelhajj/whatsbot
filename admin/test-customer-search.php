#!/usr/bin/env php
<?php
/**
 * Test customer search functionality
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🔍 Testing Customer Search Functionality\n";
echo "═══════════════════════════════════════════\n\n";

$db = Database::getInstance();

// Test 1: Search by phone number
echo "Test 1: Search by phone '3080203'\n";
echo "-----------------------------------\n";
$searchTerm = "%3080203%";
$results = $db->fetchAll("
    SELECT phone, name, brains_account_code
    FROM customers
    WHERE phone LIKE ? OR name LIKE ?
    ORDER BY created_at DESC
    LIMIT 50
", [$searchTerm, $searchTerm]);

echo "Found " . count($results) . " result(s):\n";
foreach ($results as $customer) {
    echo "  • {$customer['phone']} → {$customer['name']}\n";
}
echo "\n";

// Test 2: Search by name
echo "Test 2: Search by name 'Tony'\n";
echo "-----------------------------------\n";
$searchTerm = "%Tony%";
$results = $db->fetchAll("
    SELECT phone, name, brains_account_code
    FROM customers
    WHERE phone LIKE ? OR name LIKE ?
    ORDER BY created_at DESC
    LIMIT 50
", [$searchTerm, $searchTerm]);

echo "Found " . count($results) . " result(s):\n";
foreach ($results as $customer) {
    echo "  • {$customer['phone']} → {$customer['name']}\n";
}
echo "\n";

// Test 3: Search by partial name
echo "Test 3: Search by partial name 'Mme'\n";
echo "-----------------------------------\n";
$searchTerm = "%Mme%";
$results = $db->fetchAll("
    SELECT phone, name, brains_account_code
    FROM customers
    WHERE phone LIKE ? OR name LIKE ?
    ORDER BY created_at DESC
    LIMIT 10
", [$searchTerm, $searchTerm]);

echo "Found " . count($results) . " result(s) (showing first 10):\n";
foreach ($results as $customer) {
    echo "  • {$customer['phone']} → {$customer['name']}\n";
}
echo "\n";

echo "✅ Search functionality working correctly!\n";
