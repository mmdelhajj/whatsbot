#!/usr/bin/env php
<?php
/**
 * Test phone number normalization (converts to local format)
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Phone Number Normalization\n";
echo "═══════════════════════════════════════\n\n";

$customerModel = new Customer();

// Use reflection to access private method
$reflection = new ReflectionClass($customerModel);
$method = $reflection->getMethod('normalizePhone');
$method->setAccessible(true);

$testCases = [
    // WhatsApp format (what we receive)
    '+9613080203' => '03080203',
    '+96170945227' => '070945227',
    '+96109851721' => '09851721',

    // International without +
    '9613080203' => '03080203',
    '96170945227' => '070945227',

    // Local format (already correct)
    '03080203' => '03080203',
    '070945227' => '070945227',
    '09851721' => '09851721',

    // Without leading 0
    '3080203' => '03080203',
    '70945227' => '070945227',
];

echo "Testing phone normalization:\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $input => $expected) {
    $normalized = $method->invoke($customerModel, $input);
    $match = ($normalized === $expected);

    if ($match) {
        echo "✅ PASS: '{$input}' → '{$normalized}'\n";
        $passed++;
    } else {
        echo "❌ FAIL: '{$input}' → '{$normalized}' (expected: '{$expected}')\n";
        $failed++;
    }
}

echo "\n═══════════════════════════════════════\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed == 0) {
    echo "✅ All tests passed!\n";
} else {
    echo "❌ Some tests failed\n";
}
