#!/usr/bin/env php
<?php
/**
 * Sync Customer Accounts from Brains ERP
 * Maps customer names from Brains to local database by phone number
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🔄 Syncing Customer Accounts from Brains ERP\n";
echo "=============================================\n\n";

$db = Database::getInstance();
$brainsAPI = new BrainsAPI();

try {
    // Fetch all customer accounts from Brains
    echo "📥 Fetching customer accounts from Brains API...\n";
    $accounts = $brainsAPI->fetchAccounts();

    if (!$accounts || !is_array($accounts)) {
        echo "❌ Failed to fetch accounts from Brains API\n";
        exit(1);
    }

    echo "✅ Fetched " . count($accounts) . " accounts from Brains\n\n";

    // Process each account
    $matched = 0;
    $updated = 0;

    foreach ($accounts as $account) {
        $accountCode = $account['AccountCode'] ?? null;
        $customerName = $account['Description'] ?? null;
        $telephone = $account['Telephone'] ?? '';
        $email = $account['Email'] ?? '';
        $address = $account['Address'] ?? '';

        // Skip if no customer name
        if (!$customerName) {
            continue;
        }

        // Clean up phone number - extract valid phone numbers from the field
        $phoneNumbers = [];

        // Try to extract phone numbers from the telephone field
        // Examples: "09/851721", "09/921223/4 03/201087", "03/296030"
        if (preg_match_all('/(\d{2}\/\d{6}|\d{2}\/\d{3}\s*\d{3}|\d{8})/', $telephone, $matches)) {
            foreach ($matches[0] as $phone) {
                // Clean up the phone number
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

                // Convert to Lebanese format with +961
                if (strlen($cleanPhone) == 8) {
                    // Mobile number (03, 70, 71, 76, 78, 79, 81) or landline
                    $phoneNumbers[] = '+961' . $cleanPhone;
                } elseif (strlen($cleanPhone) == 6) {
                    // Old format landline, add 0 prefix
                    $phoneNumbers[] = '+9610' . $cleanPhone;
                }
            }
        }

        // Try to find matching customer in local database by phone
        foreach ($phoneNumbers as $phone) {
            $customer = $db->fetchOne(
                "SELECT * FROM customers WHERE phone = ?",
                [$phone]
            );

            if ($customer) {
                $matched++;

                // Update customer name if not already set or different
                if (empty($customer['name']) || $customer['name'] !== $customerName) {
                    $updateData = ['name' => $customerName];

                    // Also update email and address if not set
                    if ($email && empty($customer['email'])) {
                        $updateData['email'] = $email;
                    }
                    if ($address && empty($customer['address'])) {
                        $updateData['address'] = $address;
                    }

                    $db->update('customers', $updateData, 'id = :id', ['id' => $customer['id']]);
                    $updated++;

                    echo "✅ Updated: {$phone} → {$customerName}\n";
                }

                break; // Found a match, move to next account
            }
        }
    }

    echo "\n";
    echo "📊 Sync Summary:\n";
    echo "   Total Accounts: " . count($accounts) . "\n";
    echo "   Matched Customers: {$matched}\n";
    echo "   Updated Records: {$updated}\n";
    echo "\n✅ Sync completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
