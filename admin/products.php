<?php
/**
 * Admin Dashboard - Products Management
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();

// Get all products
$products = $db->fetchAll("
    SELECT * FROM product_info
    ORDER BY item_name ASC
");

// Get statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_products,
        COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as in_stock,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
        SUM(price * stock_quantity) as inventory_value
    FROM product_info
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?= STORE_NAME ?> Admin</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #6b7280; font-size: 0.9em; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .value { font-size: 2em; font-weight: bold; color: #1f2937; }
        .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 500; }
        .badge-in-stock { background: #d1fae5; color: #059669; }
        .badge-low-stock { background: #fef3c7; color: #d97706; }
        .badge-out-of-stock { background: #fee2e2; color: #dc2626; }
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
            <a href="/admin/products.php" class="active">Products</a>
            <a href="/admin/sync.php">Sync Products</a>
            <a href="/admin/sync-customers.php">Sync Customers</a>
            <a href="/admin/import-customers.php">Import Customers</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
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
            <h2>All Products</h2>
            <?php if (empty($products)): ?>
                <p>No products found. Run a sync to import products from Brains API.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <?php
                            $stock = $product['stock_quantity'] ?? 0;
                            $stockBadge = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
                            $stockText = $stock > 10 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($product['item_code']) ?></td>
                            <td><?= htmlspecialchars($product['item_name']) ?></td>
                            <td><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                            <td><?= number_format($product['price'], 0) ?> <?= CURRENCY ?></td>
                            <td><?= $stock ?></td>
                            <td><span class="badge badge-<?= $stockBadge ?>"><?= $stockText ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
