#!/usr/bin/env php
<?php
/**
 * Automatic Sync Script - Brains ERP
 * Syncs products and customers from Brains ERP
 * Designed to run via cron job
 */

require_once dirname(__DIR__) . '/config/config.php';

// Log start
$logFile = dirname(__DIR__) . '/logs/sync.log';
$lastSyncFile = dirname(__DIR__) . '/logs/last-sync.txt';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function logSync($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    echo "[{$timestamp}] {$message}\n";
}

// Get sync interval from .env (in minutes)
$syncInterval = intval(getenv('SYNC_INTERVAL') ?: 240); // Default: 240 minutes (4 hours)

// Check last sync time
$lastSyncTime = 0;
if (file_exists($lastSyncFile)) {
    $lastSyncTime = intval(file_get_contents($lastSyncFile));
}

$currentTime = time();
$timeSinceLastSync = ($currentTime - $lastSyncTime) / 60; // Convert to minutes

// Only sync if enough time has passed
if ($timeSinceLastSync < $syncInterval && $lastSyncTime > 0) {
    $nextSyncIn = ceil($syncInterval - $timeSinceLastSync);
    logSync("Skipping sync - Last sync was " . round($timeSinceLastSync, 1) . " minutes ago. Next sync in {$nextSyncIn} minutes (Interval: {$syncInterval} min)");
    exit(0);
}

logSync("========================================");
logSync("Starting Brains ERP Sync (Interval: {$syncInterval} min)");

$db = Database::getInstance();
$brainsAPI = new BrainsAPI();

// ======================
// SYNC PRODUCTS
// ======================
try {
    logSync("Fetching products from Brains...");
    $items = $brainsAPI->fetchItems();

    if (!$items || !is_array($items)) {
        throw new Exception("Failed to fetch products from Brains API");
    }

    logSync("Found " . count($items) . " products");

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($items as $item) {
        // Brains API uses: SKU, Name, StockQuantity, Price
        $itemCode = $item['SKU'] ?? ($item['ItemCode'] ?? null);
        $itemName = $item['Name'] ?? ($item['ItemName'] ?? null);
        $price = floatval($item['Price'] ?? 0);
        $stockQty = intval($item['StockQuantity'] ?? ($item['StockQty'] ?? 0));
        $category = $item['Category'] ?? '';
        $imageUrl = $item['ImageURL'] ?? '';

        if (!$itemCode || !$itemName) {
            $skipped++;
            continue;
        }

        $existingProduct = $db->fetchOne(
            "SELECT * FROM product_info WHERE item_code = ?",
            [$itemCode]
        );

        if ($existingProduct) {
            // Update product but preserve existing image_url if API doesn't provide one
            $updateData = [
                'item_name' => $itemName,
                'price' => $price,
                'stock_quantity' => $stockQty,
                'category' => $category
            ];

            // Only update image_url if API provides a non-empty value
            if (!empty($imageUrl)) {
                $updateData['image_url'] = $imageUrl;
            }

            $db->update('product_info', $updateData, 'item_code = :code', ['code' => $itemCode]);
            $updated++;
        } else {
            $db->insert('product_info', [
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'price' => $price,
                'stock_quantity' => $stockQty,
                'category' => $category,
                'image_url' => $imageUrl
            ]);
            $created++;
        }
    }

    logSync("Products: Created={$created}, Updated={$updated}, Skipped={$skipped}");

} catch (Exception $e) {
    logSync("ERROR syncing products: " . $e->getMessage());
}

// ======================
// SYNC CUSTOMERS
// ======================
try {
    logSync("Fetching customers from Brains...");
    $accounts = $brainsAPI->fetchAccounts();

    if (!$accounts || !is_array($accounts)) {
        throw new Exception("Failed to fetch accounts from Brains API");
    }

    logSync("Found " . count($accounts) . " accounts");

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($accounts as $account) {
        $accountCode = $account['AccountCode'] ?? null;
        $customerName = $account['Description'] ?? null;
        $telephone = $account['Telephone'] ?? '';
        $email = $account['Email'] ?? '';
        $address = $account['Address'] ?? '';

        if (!$customerName) {
            continue;
        }

        // Clean up phone number
        $phoneNumbers = [];
        $searchText = $telephone . ' ' . $customerName;
        $cleanName = preg_replace('/\d{2}[\/\s]*\d{3}[\/\s]*\d{3}|\d{8}/', '', $customerName);
        $cleanName = trim($cleanName);
        $cleanName = rtrim($cleanName, '. ');

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

        if (empty($phoneNumbers)) {
            $skipped++;
            continue;
        }

        foreach ($phoneNumbers as $phone) {
            $existingCustomer = $db->fetchOne(
                "SELECT * FROM customers WHERE phone = ?",
                [$phone]
            );

            if ($existingCustomer) {
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
                }
            } else {
                $db->insert('customers', [
                    'phone' => $phone,
                    'name' => $cleanName,
                    'email' => $email ?: null,
                    'address' => $address ?: null,
                    'brains_account_code' => $accountCode
                ]);
                $created++;
            }

            break;
        }
    }

    logSync("Customers: Created={$created}, Updated={$updated}, Skipped={$skipped}");

} catch (Exception $e) {
    logSync("ERROR syncing customers: " . $e->getMessage());
}

// Save current sync time
file_put_contents($lastSyncFile, time());

logSync("Sync completed successfully");
logSync("========================================");
