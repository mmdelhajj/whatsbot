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

$successMessage = '';
$errorMessage = '';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $productId = intval($_POST['product_id'] ?? 0);

    if ($productId > 0) {
        try {
            $product = $db->fetchOne("SELECT item_name FROM product_info WHERE id = ?", [$productId]);

            if ($product) {
                $db->delete('product_info', 'id = :id', ['id' => $productId]);
                $successMessage = "✅ Product \"" . htmlspecialchars($product['item_name']) . "\" deleted successfully.";
            } else {
                $errorMessage = "❌ Product not found.";
            }
        } catch (Exception $e) {
            $errorMessage = "❌ Error deleting product: " . $e->getMessage();
        }
    }
}

// Search functionality
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination settings
$itemsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure page is at least 1
$offset = ($currentPage - 1) * $itemsPerPage;

// Build SQL query with optional search
$whereClause = '';
$params = [];
if (!empty($searchTerm)) {
    $whereClause = "WHERE item_name LIKE ? OR item_code LIKE ? OR category LIKE ?";
    $searchParam = "%{$searchTerm}%";
    $params = [$searchParam, $searchParam, $searchParam];
}

// Get total count
$totalProducts = $db->fetchOne("SELECT COUNT(*) as count FROM product_info {$whereClause}", $params)['count'];
$totalPages = ceil($totalProducts / $itemsPerPage);

// Get products for current page
$products = $db->fetchAll("
    SELECT * FROM product_info
    {$whereClause}
    ORDER BY item_name ASC
    LIMIT ? OFFSET ?
", array_merge($params, [$itemsPerPage, $offset]));

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
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; }
        .no-img { width: 50px; height: 50px; background: #f3f4f6; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 0.75em; border: 1px solid #e5e7eb; }
        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 20px 0; border-top: 1px solid #e5e7eb; }
        .pagination-info { color: #6b7280; font-size: 0.9em; }
        .pagination-buttons { display: flex; gap: 10px; }
        .pagination-btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .pagination-btn.prev, .pagination-btn.next { background: #667eea; color: white; }
        .pagination-btn.prev:hover, .pagination-btn.next:hover { background: #5568d3; }
        .pagination-btn.disabled { background: #e5e7eb; color: #9ca3af; pointer-events: none; }
        .search-box { margin-bottom: 20px; }
        .search-form { display: flex; gap: 10px; align-items: center; }
        .search-input { flex: 1; max-width: 400px; padding: 10px 15px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95em; }
        .search-input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .search-btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .search-btn:hover { background: #5568d3; }
        .clear-btn { padding: 10px 15px; background: #6b7280; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .clear-btn:hover { background: #4b5563; }
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d1fae5; color: #059669; }
        .message.error { background: #fee2e2; color: #dc2626; }
        .delete-btn { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: all 0.3s; }
        .delete-btn:hover { background: #dc2626; }
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
            <a href="/admin/custom-qa.php">Custom Q&A</a>
            
            
            <a href="/admin/import-customers.php">Import Customers</a>
            <a href="/admin/import-products.php">Import Products</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <?php if ($successMessage): ?>
        <div class="message success">
            <?= $successMessage ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="message error">
            <?= $errorMessage ?>
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
            <h2>All Products</h2>

            <!-- Search Box -->
            <div class="search-box">
                <form method="GET" action="" class="search-form">
                    <input type="text"
                           name="search"
                           class="search-input"
                           placeholder="Search by product name, code, or category..."
                           value="<?= htmlspecialchars($searchTerm) ?>">
                    <button type="submit" class="search-btn">Search</button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="?" class="clear-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($products)): ?>
                <p>No products found<?= !empty($searchTerm) ? ' matching your search' : '. Run a sync to import products from Brains API' ?>.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
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
                            <td>
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                         alt="<?= htmlspecialchars($product['item_name']) ?>"
                                         class="product-img"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="no-img" style="display:none;">No Image</div>
                                <?php else: ?>
                                    <div class="no-img">No Image</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['item_code']) ?></td>
                            <td><?= htmlspecialchars($product['item_name']) ?></td>
                            <td><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                            <td><?= number_format($product['price'], 0) ?> <?= CURRENCY ?></td>
                            <td><?= $stock ?></td>
                            <td><span class="badge badge-<?= $stockBadge ?>"><?= $stockText ?></span></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Delete product \'<?= htmlspecialchars($product['item_name']) ?>\'?\n\nThis action cannot be undone.');">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" name="delete_product" class="delete-btn">🗑️ Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?= ($offset + 1) ?> - <?= min($offset + $itemsPerPage, $totalProducts) ?> of <?= number_format($totalProducts) ?> products<?= !empty($searchTerm) ? ' (filtered)' : '' ?> (Page <?= $currentPage ?> of <?= $totalPages ?>)
                </div>
                <div class="pagination-buttons">
                    <?php
                    $prevUrl = "?page=" . ($currentPage - 1);
                    $nextUrl = "?page=" . ($currentPage + 1);
                    if (!empty($searchTerm)) {
                        $prevUrl .= "&search=" . urlencode($searchTerm);
                        $nextUrl .= "&search=" . urlencode($searchTerm);
                    }
                    ?>
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $prevUrl ?>" class="pagination-btn prev">← Previous</a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">← Previous</span>
                    <?php endif; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= $nextUrl ?>" class="pagination-btn next">Next →</a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">Next →</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
