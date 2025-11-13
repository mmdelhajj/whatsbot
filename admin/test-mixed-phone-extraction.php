#!/usr/bin/env php
<?php
/**
 * Test phone extraction from mixed fields (like the user's example)
 */

echo "🧪 Testing Phone Extraction from Mixed Fields\n";
echo "═══════════════════════════════════════════════\n\n";

// Simulate the problematic Brains data from user
$testAccounts = [
    [
        "AccountCode" => "014574",
        "Description" => "M. NABIL 03122552",  // Phone is HERE!
        "Telephone" => "M. NABIL",             // Name is here (wrong field)
    ],
    [
        "AccountCode" => "000123",
        "Description" => "Studio Tony 03286930",
        "Telephone" => "03286930",             // Phone in both places
    ],
    [
        "AccountCode" => "000456",
        "Description" => "Mme Khoury",
        "Telephone" => "03/296 030",           // Phone only in Telephone
    ],
];

echo "Testing phone extraction:\n\n";

foreach ($testAccounts as $account) {
    $customerName = $account['Description'];
    $telephone = $account['Telephone'];

    echo "Account: {$account['AccountCode']}\n";
    echo "  Description: '{$customerName}'\n";
    echo "  Telephone: '{$telephone}'\n";

    // NEW LOGIC: Search in BOTH fields
    $searchText = $telephone . ' ' . $customerName;
    $phoneNumbers = [];

    if (preg_match_all('/(\d{2}\/\d{6}|\d{2}\/\d{3}\s*\d{3}|\d{8})/', $searchText, $matches)) {
        foreach ($matches[0] as $phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

            if (strlen($cleanPhone) == 8) {
                $phoneNumbers[] = $cleanPhone;
            } elseif (strlen($cleanPhone) == 6) {
                $phoneNumbers[] = '0' . $cleanPhone;
            }
        }
    }

    $phoneNumbers = array_unique($phoneNumbers);

    if (!empty($phoneNumbers)) {
        echo "  ✅ Found phones: " . implode(', ', $phoneNumbers) . "\n";
    } else {
        echo "  ❌ No phones found\n";
    }
    echo "\n";
}

echo "✅ Test complete!\n";
