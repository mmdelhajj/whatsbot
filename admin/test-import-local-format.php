#!/usr/bin/env php
<?php
/**
 * Test phone number extraction with local format (no +961)
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Phone Number Extraction (Local Format)\n";
echo "═══════════════════════════════════════════════════\n\n";

// Test various phone formats from Brains
$testPhones = [
    '03/080203' => '03080203',
    '09/851721' => '09851721',
    '70/945227' => '70945227',
    '03/296 030' => '03296030',
    '09/921223/4 03/201087' => ['09921223', '03201087'],  // Multiple phones
    '980203' => '0980203',  // 6 digits
];

echo "Testing phone number extraction:\n\n";

foreach ($testPhones as $input => $expected) {
    echo "Input: '{$input}'\n";

    $phoneNumbers = [];

    if (preg_match_all('/(\d{2}\/\d{6}|\d{2}\/\d{3}\s*\d{3}|\d{8})/', $input, $matches)) {
        foreach ($matches[0] as $phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

            if (strlen($cleanPhone) == 8) {
                // Already has leading 0: 03080203
                $phoneNumbers[] = $cleanPhone;
            } elseif (strlen($cleanPhone) == 6) {
                // Add leading 0: 980203 -> 0980203
                $phoneNumbers[] = '0' . $cleanPhone;
            }
        }
    }

    if (is_array($expected)) {
        $match = (count($phoneNumbers) == count($expected)) && empty(array_diff($phoneNumbers, $expected));
        echo "Expected: [" . implode(', ', $expected) . "]\n";
    } else {
        $match = (count($phoneNumbers) == 1 && $phoneNumbers[0] == $expected);
        echo "Expected: '{$expected}'\n";
    }

    echo "Got: [" . implode(', ', $phoneNumbers) . "]\n";
    echo $match ? "✅ PASS\n\n" : "❌ FAIL\n\n";
}

echo "✅ Phone extraction logic tested!\n";
