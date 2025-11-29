<?php
/**
 * Admin Dashboard - Import Products from Brains
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();
$brainsAPI = new BrainsAPI();

$successMessage = '';
$errorMessage = '';
$syncResults = null;

// Handle import request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_products'])) {
    try {
        $startTime = microtime(true);

        // Fetch all products from Brains
        $items = $brainsAPI->fetchItems();

        if (!$items || !is_array($items)) {
            throw new Exception("Failed to fetch products from Brains API");
        }

        // Process each product
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

            // Skip if no item code or name
            if (!$itemCode || !$itemName) {
                $skipped++;
                continue;
            }

            // Check if product already exists
            $existingProduct = $db->fetchOne(
                "SELECT * FROM product_info WHERE item_code = ?",
                [$itemCode]
            );

            if ($existingProduct) {
                // Update existing product but preserve local image_url if API doesn't provide one
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
                // Create new product
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

        $duration = round(microtime(true) - $startTime, 2);

        $syncResults = [
            'total_items' => count($items),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'duration' => $duration
        ];

        $successMessage = "✅ Import completed successfully!";

    } catch (Exception $e) {
        $errorMessage = '❌ Error: ' . $e->getMessage();
    }
}

// Get current product statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_products,
        COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as in_stock,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
        SUM(price * stock_quantity) as inventory_value
    FROM product_info
");

// Get sample products
$sampleProducts = $db->fetchAll("
    SELECT item_code, item_name, price, stock_quantity, category
    FROM product_info
    ORDER BY created_at DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products - <?= STORE_NAME ?> Admin</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #6b7280; font-size: 0.9em; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .value { font-size: 2em; font-weight: bold; color: #1f2937; }
        .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { margin-bottom: 20px; color: #1f2937; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .import-btn { background: #10b981; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; width: 100%; }
        .import-btn:hover { background: #059669; }
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d1fae5; color: #059669; }
        .message.error { background: #fee2e2; color: #dc2626; }
        .results { background: #f9fafb; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .results h3 { margin-top: 0; color: #1f2937; }
        .results .result-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .results .result-row:last-child { border-bottom: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
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
            <a href="/admin/custom-qa.php">Custom Q&A</a>
            <a href="/admin/import-customers.php">Import Customers</a>
            <a href="/admin/import-products.php" class="active">Import Products</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <?php if ($successMessage): ?>
        <div class="message success">
            <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="message error">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Products</h3>
                <div class="value"><?= number_format($stats['total_products'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>In Stock</h3>
                <div class="value"><?= number_format($stats['in_stock'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Out of Stock</h3>
                <div class="value"><?= number_format($stats['out_of_stock'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Inventory Value</h3>
                <div class="value"><?= number_format($stats['inventory_value'] ?? 0) ?> <?= CURRENCY ?></div>
            </div>
        </div>

        <div class="section">
            <h2>📥 Import All Products from Brains ERP</h2>
            <p>This will import ALL products from Brains ERP system. Existing products will be updated with latest prices and stock quantities.</p>

            <form method="POST" onsubmit="return confirm('⚠️ Import all products from Brains? This may take a few seconds.');">
                <button type="submit" name="import_products" class="import-btn">
                    📥 Import Products from Brains
                </button>
            </form>

            <?php if ($syncResults): ?>
            <div class="results">
                <h3>📊 Import Results</h3>
                <div class="result-row">
                    <span>Total Brains Products:</span>
                    <strong><?= number_format($syncResults['total_items']) ?></strong>
                </div>
                <div class="result-row">
                    <span>New Products Created:</span>
                    <strong style="color: #10b981;"><?= number_format($syncResults['created']) ?></strong>
                </div>
                <div class="result-row">
                    <span>Existing Updated:</span>
                    <strong style="color: #3b82f6;"><?= number_format($syncResults['updated']) ?></strong>
                </div>
                <div class="result-row">
                    <span>Skipped (invalid data):</span>
                    <strong style="color: #6b7280;"><?= number_format($syncResults['skipped']) ?></strong>
                </div>
                <div class="result-row">
                    <span>Duration:</span>
                    <strong><?= $syncResults['duration'] ?>s</strong>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>📦 Recent Products</h2>

            <?php if (empty($sampleProducts)): ?>
                <p>No products found. Click "Import Products" above to import from Brains ERP.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sampleProducts as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['item_code']) ?></td>
                            <td><strong><?= htmlspecialchars($product['item_name']) ?></strong></td>
                            <td><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                            <td><?= number_format($product['price'], 0) ?> <?= CURRENCY ?></td>
                            <td><?= $product['stock_quantity'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="color: #6b7280; margin-top: 15px; text-align: center;">
                    Showing recent 20 products. Go to <a href="/admin/products.php">Products</a> to view all.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
