#!/usr/bin/env php
<?php
/**
 * Test cleaning phone numbers from customer names
 */

echo "🧪 Testing Name Cleaning (Remove Phone Numbers)\n";
echo "═══════════════════════════════════════════════\n\n";

// Test cases with names containing phone numbers
$testCases = [
    "M. NABIL 03122552",
    "M EL HAJJ 03080203",
    "Studio Tony 03286930",
    "Mme Khoury 03/296 030",
    "Tony",
    "M. Georges 70945227",
    "Librarie 03 123 456",
];

echo "Testing phone number removal from names:\n\n";

foreach ($testCases as $originalName) {
    // Remove phone numbers from customer name (keep only the actual name)
    $cleanName = preg_replace('/\d{2}[\/\s]*\d{3}[\/\s]*\d{3}|\d{8}/', '', $originalName);
    $cleanName = trim($cleanName);
    // Remove trailing dots and spaces
    $cleanName = rtrim($cleanName, '. ');

    echo "Original: '{$originalName}'\n";
    echo "Cleaned:  '{$cleanName}'\n";

    if (preg_match('/\d{8}|\d{2}[\/\s]*\d{3}[\/\s]*\d{3}/', $cleanName)) {
        echo "❌ FAILED - Phone number still present!\n";
    } else {
        echo "✅ PASS\n";
    }
    echo "\n";
}

echo "✅ Test complete!\n";
