<?php
/**
 * Sync Script - Products and Accounts from Brains ERP
 * Run this via cron every 4 hours
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "====================================\n";
echo "Brains ERP Sync Script\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "====================================\n\n";

$brainsAPI = new BrainsAPI();

// Sync Products
echo "[1/2] Syncing products...\n";
$productResult = $brainsAPI->syncProducts();

if ($productResult['success']) {
    echo "✅ Products synced successfully!\n";
    echo "   Total: {$productResult['total']}\n";
    echo "   Added: {$productResult['added']}\n";
    echo "   Updated: {$productResult['updated']}\n";
    echo "   Duration: {$productResult['duration']}s\n\n";
} else {
    echo "❌ Products sync failed!\n";
    echo "   Error: {$productResult['error']}\n\n";
}

// Sync Accounts
echo "[2/2] Syncing customer accounts...\n";
$accountResult = $brainsAPI->syncAccounts();

if ($accountResult['success']) {
    echo "✅ Accounts synced successfully!\n";
    echo "   Total: {$accountResult['total']}\n";
    echo "   Updated: {$accountResult['updated']}\n";
    echo "   Duration: {$accountResult['duration']}s\n\n";
} else {
    echo "❌ Accounts sync failed!\n";
    echo "   Error: {$accountResult['error']}\n\n";
}

// Summary
echo "====================================\n";
echo "Sync completed: " . date('Y-m-d H:i:s') . "\n";

if ($productResult['success'] && $accountResult['success']) {
    echo "Status: ✅ ALL SUCCESSFUL\n";
    exit(0);
} else {
    echo "Status: ⚠️ SOME FAILED\n";
    exit(1);
}
