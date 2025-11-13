#!/usr/bin/env php
<?php
/**
 * Import ALL Customer Accounts from Brains ERP
 * Creates new customer records for all Brains accounts
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "📥 Importing ALL Customers from Brains ERP\n";
echo "==========================================\n\n";

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
    $created = 0;
    $updated = 0;
    $skipped = 0;

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

        // Clean up phone number - extract from BOTH Telephone AND Description fields
        $phoneNumbers = [];

        // Combine both fields to search for phone numbers (phone might be in name field!)
        $searchText = $telephone . ' ' . $customerName;

        // Remove phone numbers from customer name (keep only the actual name)
        $cleanName = preg_replace('/\d{2}[\/\s]*\d{3}[\/\s]*\d{3}|\d{8}/', '', $customerName);
        $cleanName = trim($cleanName);
        // Remove trailing dots and spaces
        $cleanName = rtrim($cleanName, '. ');

        if (preg_match_all('/(\d{2}\/\d{6}|\d{2}\/\d{3}\s*\d{3}|\d{8})/', $searchText, $matches)) {
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

        // Remove duplicates
        $phoneNumbers = array_unique($phoneNumbers);

        // If no valid phone numbers found, skip
        if (empty($phoneNumbers)) {
            $skipped++;
            continue;
        }

        // Process each phone number
        foreach ($phoneNumbers as $phone) {
            // Check if customer already exists
            $existingCustomer = $db->fetchOne(
                "SELECT * FROM customers WHERE phone = ?",
                [$phone]
            );

            if ($existingCustomer) {
                // Update existing customer
                $updateData = [];

                if (empty($existingCustomer['name']) || $existingCustomer['name'] !== $cleanName) {
                    $updateData['name'] = $cleanName;
                }
                if ($email && empty($existingCustomer['email'])) {
                    $updateData['email'] = $email;
                }
                if ($address && empty($existingCustomer['address'])) {
                    $updateData['address'] = $address;
                }
                if (!empty($accountCode) && empty($existingCustomer['brains_account_code'])) {
                    $updateData['brains_account_code'] = $accountCode;
                }

                if (!empty($updateData)) {
                    $db->update('customers', $updateData, 'id = :id', ['id' => $existingCustomer['id']]);
                    $updated++;
                    echo "✅ Updated: {$phone} → {$cleanName}\n";
                }
            } else {
                // Create new customer
                $db->insert('customers', [
                    'phone' => $phone,
                    'name' => $cleanName,
                    'email' => $email ?: null,
                    'address' => $address ?: null,
                    'brains_account_code' => $accountCode
                ]);
                $created++;
                echo "🆕 Created: {$phone} → {$cleanName}\n";
            }

            break; // Only use first valid phone number per account
        }
    }

    echo "\n";
    echo "📊 Import Summary:\n";
    echo "   Total Brains Accounts: " . count($accounts) . "\n";
    echo "   New Customers Created: {$created}\n";
    echo "   Existing Updated: {$updated}\n";
    echo "   Skipped (no phone): {$skipped}\n";
    echo "\n✅ Import completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
