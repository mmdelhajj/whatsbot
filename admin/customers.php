<?php
/**
 * Admin Dashboard - Customers Management
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();

// Handle delete single customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customerId = $_POST['customer_id'] ?? 0;
    if ($customerId) {
        // Delete related data first
        $db->query("DELETE FROM messages WHERE customer_id = ?", [$customerId]);
        $db->query("DELETE FROM conversation_context WHERE customer_id = ?", [$customerId]);
        $db->query("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE customer_id = ?)", [$customerId]);
        $db->query("DELETE FROM orders WHERE customer_id = ?", [$customerId]);
        // Delete customer
        $db->query("DELETE FROM customers WHERE id = ?", [$customerId]);
        $successMessage = "Customer deleted successfully! ✅";
    }
}

// Handle delete all customers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_customers'])) {
    $db->query("DELETE FROM messages");
    $db->query("DELETE FROM conversation_context");
    $db->query("DELETE FROM order_items");
    $db->query("DELETE FROM orders");
    $db->query("DELETE FROM customers");
    $successMessage = "All customers deleted successfully! ✅";
}

// Handle search
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get customers (with search if provided)
if (!empty($searchQuery)) {
    $searchTerm = "%{$searchQuery}%";
    $customers = $db->fetchAll("
        SELECT * FROM customers
        WHERE phone LIKE ? OR name LIKE ? OR email LIKE ?
        ORDER BY created_at DESC
        LIMIT 100
    ", [$searchTerm, $searchTerm, $searchTerm]);
} else {
    $customers = $db->fetchAll("
        SELECT * FROM customers
        ORDER BY created_at DESC
    ");
}

// Get statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_customers,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
    FROM customers
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - <?= STORE_NAME ?> Admin</title>
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
        .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 500; background: #dbeafe; color: #0284c7; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { margin: 0; }
        .delete-btn { background: #dc2626; color: white; padding: 6px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85em; }
        .delete-btn:hover { background: #b91c1c; }
        .delete-all-btn { background: #dc2626; color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .delete-all-btn:hover { background: #b91c1c; }
        .success { background: #d1fae5; color: #059669; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
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
            <a href="/admin/customers.php" class="active">Customers</a>
            <a href="/admin/messages.php">Messages</a>
            <a href="/admin/orders.php">Orders</a>
            <a href="/admin/products.php">Products</a>
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
                <h3>Total Customers</h3>
                <div class="value"><?= number_format($stats['total_customers'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>New This Month</h3>
                <div class="value"><?= number_format($stats['new_this_month'] ?? 0) ?></div>
            </div>
        </div>

        <?php if (isset($successMessage)): ?>
            <div class="success">✅ <?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <div class="section" style="margin-bottom: 20px;">
            <h2>🔍 Search Customers</h2>
            <form method="GET" style="margin-top: 15px;">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>"
                           placeholder="Search by phone, name, or email (e.g., Tony or 03080203)"
                           style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 1em;">
                    <button type="submit" style="background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        🔍 Search
                    </button>
                    <?php if (!empty($searchQuery)): ?>
                    <a href="customers.php" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: flex; align-items: center;">
                        ✕ Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($searchQuery)): ?>
                <p style="color: #6b7280; margin-top: 15px; margin-bottom: 0;">
                    <?= count($customers) ?> result(s) for "<?= htmlspecialchars($searchQuery) ?>"
                </p>
            <?php endif; ?>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><?= !empty($searchQuery) ? 'Search Results' : 'All Customers' ?> (<?= count($customers) ?>)</h2>
                <?php if (!empty($customers)): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to DELETE ALL CUSTOMERS? This will also delete all their orders and messages. This cannot be undone!');">
                        <button type="submit" name="delete_all_customers" class="delete-all-btn">🗑️ Delete All Customers</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (empty($customers)): ?>
                <p>No customers found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Phone</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Brains Code</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= htmlspecialchars($customer['phone']) ?></td>
                            <td><strong><?= htmlspecialchars($customer['name'] ?: 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($customer['email'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($customer['brains_account_code'] ?: 'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete customer <?= htmlspecialchars($customer['phone']) ?>? This will also delete their orders and messages.');">
                                    <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                    <button type="submit" name="delete_customer" class="delete-btn">🗑️ Delete</button>
                                </form>
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
