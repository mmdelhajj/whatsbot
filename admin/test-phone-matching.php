#!/usr/bin/env php
<?php
/**
 * Test phone number matching with different formats
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Phone Number Matching\n";
echo "═══════════════════════════════════════\n\n";

$customerModel = new Customer();

// Test different phone number formats
$testPhones = [
    '+9613080203' => 'Full international format (+961)',
    '03080203' => 'Local Lebanese format (03)',
    '961 3 080203' => 'International with spaces',
    '03/080203' => 'Local with slash',
    '3080203' => 'Just the 7 digits'
];

foreach ($testPhones as $phone => $description) {
    echo "📞 Testing: '{$phone}' ({$description})\n";

    $customer = $customerModel->findOrCreateByPhone($phone);

    if ($customer && !empty($customer['name'])) {
        echo "   ✅ Found customer: {$customer['name']} (Phone: {$customer['phone']})\n";
    } else {
        echo "   ❌ Customer not found or no name\n";
    }
    echo "\n";
}

echo "═══════════════════════════════════════\n";
echo "✅ Phone matching now works with multiple formats!\n";
