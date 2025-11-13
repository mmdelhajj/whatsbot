<?php
/**
 * Admin Dashboard - Sync with Brains API
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();
$message = '';
$messageType = '';

// Handle sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync'])) {
    try {
        // Import Products
        $productsUrl = BRAINS_ITEMS_ENDPOINT;
        $response = @file_get_contents($productsUrl);

        if ($response === false) {
            throw new Exception('Failed to connect to Brains API');
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['Content'])) {
            throw new Exception('Invalid response from Brains API');
        }

        $products = $data['Content'];
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($products as $product) {
            // Skip products without SKU
            if (empty($product['SKU'])) {
                $skipped++;
                continue;
            }

            try {
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
            } catch (Exception $e) {
                $errors[] = "SKU {$product['SKU']}: " . $e->getMessage();
                if (count($errors) < 5) { // Only store first 5 errors
                    continue;
                } else {
                    break;
                }
            }
        }

        // Update sync log
        $db->insert('brains_sync_log', [
            'sync_type' => 'products',
            'status' => 'success',
            'records_count' => $imported + $updated,
            'records_added' => $imported,
            'records_updated' => $updated,
            'error_message' => !empty($errors) ? implode('; ', $errors) : null
        ]);

        $message = "✅ Sync completed! Imported: $imported, Updated: $updated products.";
        if ($skipped > 0) {
            $message .= " (Skipped: $skipped without SKU)";
        }
        if (!empty($errors)) {
            $message .= " ⚠️ Errors: " . count($errors) . " - " . implode('; ', array_slice($errors, 0, 2));
        }
        $messageType = 'success';

    } catch (Exception $e) {
        $message = "❌ Sync failed: " . $e->getMessage();
        $messageType = 'error';

        // Log the error
        $db->insert('brains_sync_log', [
            'sync_type' => 'products',
            'status' => 'error',
            'records_count' => 0,
            'records_added' => 0,
            'records_updated' => 0,
            'error_message' => $e->getMessage()
        ]);
    }
}

// Get recent sync logs
$syncLogs = $db->fetchAll("
    SELECT * FROM brains_sync_log
    ORDER BY created_at DESC
    LIMIT 20
");

// Get product count
$productCount = $db->fetchOne("SELECT COUNT(*) as count FROM product_info")['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync - <?= STORE_NAME ?> Admin</title>
    <link rel="stylesheet" href="/admin/assets/admin-style.css">
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: white; padding: 20px; border-bottom: 1px solid #e5e7eb; }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; color: #1f2937; }
        .logout-btn { background: #ef4444; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        .nav { background: white; border-bottom: 1px solid #e5e7eb; padding: 0 20px; }
        .nav-content { max-width: 1200px; margin: 0 auto; display: flex; gap: 20px; }
        .nav a { display: inline-block; padding: 15px 20px; text-decoration: none; color: #374151; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .nav a:hover, .nav a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .sync-button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 1em; font-weight: 600; cursor: pointer; }
        .sync-button:hover { background: #059669; }
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d1fae5; color: #059669; }
        .message.error { background: #fee2e2; color: #dc2626; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 500; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-error { background: #fee2e2; color: #dc2626; }
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🛍️ <?= STORE_NAME ?></h1>
            <a href="?logout" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav">
        <div class="nav-content">
            <a href="/admin">Dashboard</a>
            <a href="/admin/customers.php">Customers</a>
            <a href="/admin/messages.php">Messages</a>
            <a href="/admin/orders.php">Orders</a>
            <a href="/admin/products.php">Products</a>
            <a href="/admin/sync.php" class="active">Sync</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>🔄 Sync with Brains API</h2>
            <div class="info-box">
                <strong>Current Products:</strong> <?= number_format($productCount) ?><br>
                <strong>API Endpoint:</strong> <?= htmlspecialchars(BRAINS_ITEMS_ENDPOINT) ?><br>
                <strong>Sync Interval:</strong> Every <?= SYNC_INTERVAL_HOURS ?> hours
            </div>

            <form method="POST">
                <button type="submit" name="sync" class="sync-button">▶️ Run Sync Now</button>
            </form>
        </div>

        <div class="section">
            <h2>Sync History</h2>
            <?php if (empty($syncLogs)): ?>
                <p>No sync history available.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($syncLogs as $log): ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><?= ucfirst($log['sync_type']) ?></td>
                            <td><span class="badge badge-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></span></td>
                            <td><?= $log['records_count'] ?></td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    Imported: <?= $log['records_added'] ?>, Updated: <?= $log['records_updated'] ?>
                                <?php elseif ($log['status'] === 'error' && $log['error_message']): ?>
                                    <?= htmlspecialchars($log['error_message']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
