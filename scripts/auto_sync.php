#!/usr/bin/env php
<?php
/**
 * Automatic Sync Script
 * Syncs products and customers from Brains ERP
 * Run via cron job
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting automatic sync...\n";

$db = Database::getInstance();
$startTime = microtime(true);

// Sync Products
try {
    echo "Syncing products...\n";

    $productsUrl = BRAINS_API_BASE . BRAINS_ITEMS_ENDPOINT;
    $response = @file_get_contents($productsUrl);

    if ($response === false) {
        throw new Exception('Failed to connect to Brains API for products');
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['Content'])) {
        throw new Exception('Invalid response from Brains API');
    }

    $products = $data['Content'];
    $imported = 0;
    $updated = 0;

    foreach ($products as $product) {
        if (empty($product['SKU'])) continue;

        $existing = $db->fetchOne(
            "SELECT id FROM product_info WHERE item_code = ?",
            [$product['SKU']]
        );

        if ($existing) {
            $db->update('product_info', [
                'item_name' => $product['Name'] ?? '',
                'price' => floatval($product['Price'] ?? 0),
                'stock_quantity' => intval($product['StockQuantity'] ?? 0),
                'description' => $product['ShortDescription'] ?? null
            ], 'item_code = :item_code', ['item_code' => $product['SKU']]);
            $updated++;
        } else {
            $db->insert('product_info', [
                'item_code' => $product['SKU'],
                'item_name' => $product['Name'] ?? '',
                'price' => floatval($product['Price'] ?? 0),
                'stock_quantity' => intval($product['StockQuantity'] ?? 0),
                'description' => $product['ShortDescription'] ?? null
            ]);
            $imported++;
        }
    }

    $db->insert('brains_sync_log', [
        'sync_type' => 'products',
        'status' => 'success',
        'items_added' => $imported,
        'items_updated' => $updated,
        'error_message' => null
    ]);

    echo "Products synced: Imported=$imported, Updated=$updated\n";

} catch (Exception $e) {
    echo "ERROR syncing products: " . $e->getMessage() . "\n";

    $db->insert('brains_sync_log', [
        'sync_type' => 'products',
        'status' => 'error',
        'items_added' => 0,
        'items_updated' => 0,
        'error_message' => $e->getMessage()
    ]);
}

// Sync Customers (optional)
try {
    echo "Syncing customers...\n";

    $brainsAPI = new BrainsAPI();
    $accounts = $brainsAPI->fetchAccounts();

    if (!is_array($accounts) || empty($accounts)) {
        echo "No customers to sync\n";
    } else {
        $customerModel = new Customer();
        $imported = 0;
        $updated = 0;

        foreach ($accounts as $account) {
            // Use AccountCode as unique identifier (AccountNumber is always 41110 for all)
            $accountCode = $account['AccountCode'] ?? null;
            if (empty($accountCode)) continue;

            // Clean email - treat "N/A" as null
            $email = $account['Email'] ?? null;
            if ($email === 'N/A' || strtolower($email) === 'n/a' || empty($email)) {
                $email = null;
            }

            // Get phone number - try Telephone field first, then extract from Description
            $phone = $account['Telephone'] ?? null;

            // Clean and extract first valid phone number
            if (!empty($phone) && $phone !== 'N/A') {
                // Extract first valid Lebanese phone (03, 70, 71, 76, 78, 79, 81 for mobile; 01-09 for landline)
                if (preg_match('/\b(0[1-9]\d{6,7}|7[0-9]\d{6})\b/', $phone, $matches)) {
                    $phone = $matches[1];
                } else {
                    $phone = null;
                }
            } else {
                // Try to extract phone from Description (e.g., "M KOZAH 03038442")
                $description = $account['Description'] ?? '';
                if (preg_match('/\b(0[1-9]\d{6,7}|7[0-9]\d{6})\b/', $description, $matches)) {
                    $phone = $matches[1];
                } else {
                    $phone = null;
                }
            }

            // Skip if no phone number found
            if (empty($phone)) continue;

            // Get name - use Name field, or Description without phone
            $name = $account['Name'] ?? null;
            if (empty($name) || $name === 'N/A') {
                $name = $account['Description'] ?? null;
                // Remove phone number from name if present
                if ($name && $phone) {
                    $name = trim(str_replace($phone, '', $name));
                }
            }

            // Check if customer exists by brains_account_code
            $existing = $db->fetchOne(
                "SELECT id FROM customers WHERE brains_account_code = ?",
                [$accountCode]
            );

            if ($existing) {
                // Update existing customer
                $db->update('customers', [
                    'name' => $name,
                    'email' => $email,
                    'address' => $account['Address'] ?? null
                ], 'id = :id', ['id' => $existing['id']]);
                $updated++;
            } else {
                // Check if phone already exists (might be from different account)
                $phoneExists = $db->fetchOne(
                    "SELECT id FROM customers WHERE phone = ?",
                    [$phone]
                );

                if ($phoneExists) {
                    // Update existing customer with this phone
                    $db->update('customers', [
                        'name' => $name,
                        'email' => $email,
                        'brains_account_code' => $accountCode,
                        'address' => $account['Address'] ?? null
                    ], 'id = :id', ['id' => $phoneExists['id']]);
                    $updated++;
                } else {
                    // Insert new customer
                    $db->insert('customers', [
                        'phone' => $phone,
                        'name' => $name,
                        'email' => $email,
                        'brains_account_code' => $accountCode,
                        'address' => $account['Address'] ?? null
                    ]);
                    $imported++;
                }
            }
        }

        $db->insert('brains_sync_log', [
            'sync_type' => 'customers',
            'status' => 'success',
            'items_added' => $imported,
            'items_updated' => $updated,
            'error_message' => null
        ]);

        echo "Customers synced: Imported=$imported, Updated=$updated\n";
    }

} catch (Exception $e) {
    echo "ERROR syncing customers: " . $e->getMessage() . "\n";

    $db->insert('brains_sync_log', [
        'sync_type' => 'customers',
        'status' => 'error',
        'items_added' => 0,
        'items_updated' => 0,
        'error_message' => $e->getMessage()
    ]);
}

$duration = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Sync completed in {$duration} seconds\n";
