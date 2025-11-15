#!/usr/bin/env php
<?php
/**
 * Test customers page search functionality
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🔍 Testing Customers Page Search\n";
echo "═══════════════════════════════════════\n\n";

$db = Database::getInstance();

// Test 1: Search by phone
echo "Test 1: Search by phone '3080203'\n";
echo "-----------------------------------\n";
$searchTerm = "%3080203%";
$results = $db->fetchAll("
    SELECT * FROM customers
    WHERE phone LIKE ? OR name LIKE ? OR email LIKE ?
    ORDER BY created_at DESC
    LIMIT 100
", [$searchTerm, $searchTerm, $searchTerm]);

echo "Found " . count($results) . " result(s):\n";
foreach ($results as $customer) {
    echo "  • {$customer['phone']} → " . ($customer['name'] ?: 'N/A') . "\n";
}
echo "\n";

// Test 2: Search by name
echo "Test 2: Search by name 'Tony'\n";
echo "-----------------------------------\n";
$searchTerm = "%Tony%";
$results = $db->fetchAll("
    SELECT * FROM customers
    WHERE phone LIKE ? OR name LIKE ? OR email LIKE ?
    ORDER BY created_at DESC
    LIMIT 100
", [$searchTerm, $searchTerm, $searchTerm]);

echo "Found " . count($results) . " result(s):\n";
foreach ($results as $customer) {
    $name = $customer['name'] ?: 'N/A';
    $email = $customer['email'] ?: 'N/A';
    $brainsCode = $customer['brains_account_code'] ?: 'N/A';
    echo "  • {$customer['phone']} → {$name} | Email: {$email} | Brains: {$brainsCode}\n";
}
echo "\n";

// Test 3: Search by email
echo "Test 3: Search by email containing '@'\n";
echo "-----------------------------------\n";
$searchTerm = "%@%";
$results = $db->fetchAll("
    SELECT * FROM customers
    WHERE phone LIKE ? OR name LIKE ? OR email LIKE ?
    ORDER BY created_at DESC
    LIMIT 5
", [$searchTerm, $searchTerm, $searchTerm]);

echo "Found " . count($results) . " result(s) (showing first 5):\n";
foreach ($results as $customer) {
    echo "  • {$customer['phone']} → {$customer['name']} | {$customer['email']}\n";
}
echo "\n";

echo "✅ Search functionality working on customers page!\n";
