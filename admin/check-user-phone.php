#!/usr/bin/env php
<?php
/**
 * Check if user phone exists in Brains accounts
 */

require_once dirname(__DIR__) . '/config/config.php';

$brainsAPI = new BrainsAPI();

// Fetch all accounts
$accounts = $brainsAPI->fetchAccounts();
echo "Total Brains accounts: " . count($accounts) . "\n\n";

// Search for phone +9613080203 or 03080203
$searchPhone = '03080203';
$found = false;

foreach ($accounts as $account) {
    $telephone = $account['Telephone'] ?? '';
    $accountCode = $account['AccountCode'] ?? '';
    $name = $account['Description'] ?? '';

    // Clean phone for comparison
    if (strpos($telephone, $searchPhone) !== false || strpos($telephone, '03/080203') !== false) {
        echo "✅ FOUND IN BRAINS:\n";
        echo "Account Code: {$accountCode}\n";
        echo "Name: {$name}\n";
        echo "Telephone: {$telephone}\n";
        echo "Email: " . ($account['Email'] ?? 'N/A') . "\n";
        echo "Address: " . ($account['Address'] ?? 'N/A') . "\n";
        $found = true;
        break;
    }
}

if (!$found) {
    echo "❌ Phone +9613080203 NOT found in Brains accounts\n";
    echo "This customer needs to be added manually or they are not in Brains system\n";
}
