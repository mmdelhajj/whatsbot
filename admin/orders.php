<?php
/**
 * Admin Dashboard - Orders Management with Status Updates
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();

// Handle delete single order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $orderId = $_POST['order_id'] ?? 0;
    if ($orderId) {
        // Delete order items first
        $db->query("DELETE FROM order_items WHERE order_id = ?", [$orderId]);
        // Delete order
        $db->query("DELETE FROM orders WHERE id = ?", [$orderId]);
        $successMessage = "Order #{$orderId} deleted successfully! ✅";
    }
}

// Handle delete all orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_orders'])) {
    $db->query("DELETE FROM order_items");
    $db->query("DELETE FROM orders");
    $successMessage = "All orders deleted successfully! ✅";
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = $_POST['order_id'] ?? 0;
    $newStatus = $_POST['status'] ?? '';

    if ($orderId && $newStatus) {
        // Get order details before updating
        $order = $db->fetchOne(
            "SELECT o.*, c.phone, c.preferred_language
             FROM orders o
             LEFT JOIN customers c ON o.customer_id = c.id
             WHERE o.id = ?",
            [$orderId]
        );

        if ($order) {
            // Get order items
            $order['items'] = $db->fetchAll(
                "SELECT * FROM order_items WHERE order_id = ?",
                [$orderId]
            );

            // Update status
            $db->update('orders',
                ['status' => $newStatus],
                'id = :id',
                ['id' => $orderId]
            );

            // Send WhatsApp notification to customer
            try {
                // Detect customer's language (default to English)
                $customerLang = $order['preferred_language'] ?? 'en';
                if (!in_array($customerLang, ['en', 'ar', 'fr'])) {
                    $customerLang = 'en';
                }

                // Create notification message
                $notificationMessage = ResponseTemplates::orderStatusNotification(
                    $customerLang,
                    $order,
                    $newStatus
                );

                // Send via ProxSMS
                $proxSMS = new ProxSMSService();
                $proxSMS->sendMessage($order['phone'], $notificationMessage);

                $successMessage = "Order #{$orderId} status updated to: {$newStatus} - Customer notified via WhatsApp ✅";
            } catch (Exception $e) {
                logMessage("Failed to send status notification: " . $e->getMessage(), 'ERROR');
                $successMessage = "Order #{$orderId} status updated to: {$newStatus} - Failed to send notification ⚠️";
            }
        }
    }
}

// Get all orders with items
$orders = $db->fetchAll("
    SELECT
        o.*,
        c.name as customer_name,
        c.phone as customer_phone,
        c.address as customer_address,
        c.email as customer_email,
        COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

// Get order items for each order
foreach ($orders as &$order) {
    $order['items'] = $db->fetchAll(
        "SELECT * FROM order_items WHERE order_id = ?",
        [$order['id']]
    );
}

// Get statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_orders,
        COUNT(CASE WHEN status = 'on_the_way' THEN 1 END) as shipping_orders,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_orders
    FROM orders
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - <?= STORE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5em; }
        .logout-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .nav { background: white; border-bottom: 1px solid #e5e7eb; padding: 0 20px; }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 20px; }
        .nav a { display: inline-block; padding: 15px 20px; text-decoration: none; color: #374151; border-bottom: 3px solid transparent; }
        .nav a:hover, .nav a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #6b7280; font-size: 0.85em; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .value { font-size: 1.8em; font-weight: bold; color: #1f2937; }
        .success { background: #d1fae5; color: #059669; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .order-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f3f4f6; }
        .order-number { font-size: 1.3em; font-weight: bold; color: #1f2937; }
        .order-date { color: #6b7280; font-size: 0.9em; }
        .order-body { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .order-section h4 { color: #374151; margin-bottom: 10px; font-size: 0.95em; text-transform: uppercase; letter-spacing: 0.5px; }
        .order-items { background: #f9fafb; padding: 15px; border-radius: 8px; }
        .order-item { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .order-item:last-child { border-bottom: none; }
        .order-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 2px solid #f3f4f6; }
        .order-total { font-size: 1.3em; font-weight: bold; color: #1f2937; }
        .status-form { display: flex; gap: 10px; align-items: center; }
        .status-select { padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 0.9em; background: white; cursor: pointer; }
        .status-select:focus { outline: none; border-color: #667eea; }
        .update-btn { background: #667eea; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .update-btn:hover { background: #5568d3; }

        /* Status Badges */
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 0.85em; font-weight: 600; display: inline-block; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-confirmed { background: #dbeafe; color: #0284c7; }
        .badge-preparing { background: #e0e7ff; color: #4f46e5; }
        .badge-on_the_way { background: #fbcfe8; color: #be185d; }
        .badge-delivered { background: #d1fae5; color: #059669; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .badge-out_of_stock { background: #f3f4f6; color: #6b7280; }

        .customer-info { font-size: 0.9em; color: #6b7280; line-height: 1.6; }
        .customer-info strong { color: #374151; }

        /* Delete buttons */
        .delete-btn { background: #dc2626; color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; margin-left: 10px; }
        .delete-btn:hover { background: #b91c1c; }
        .delete-all-btn { background: #dc2626; color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; float: right; }
        .delete-all-btn:hover { background: #b91c1c; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🛍️ <?= STORE_NAME ?> - Orders Management</h1>
            <a href="?logout" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav">
        <div class="nav-content">
            <a href="/admin">Dashboard</a>
            <a href="/admin/customers.php">Customers</a>
            <a href="/admin/messages.php">Messages</a>
            <a href="/admin/orders.php" class="active">Orders</a>
            <a href="/admin/products.php">Products</a>
            <a href="/admin/custom-qa.php">Custom Q&A</a>
            
            
            <a href="/admin/import-customers.php">Import Customers</a>
            <a href="/admin/import-products.php">Import Products</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($successMessage)): ?>
            <div class="success">✅ <?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?= number_format($stats['total_orders'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="value"><?= number_format($stats['pending_orders'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Preparing</h3>
                <div class="value"><?= number_format($stats['preparing_orders'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Shipping</h3>
                <div class="value"><?= number_format($stats['shipping_orders'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Today</h3>
                <div class="value"><?= number_format($stats['today_orders'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Revenue</h3>
                <div class="value"><?= number_format($stats['total_revenue'] ?? 0, 0) ?> <?= CURRENCY ?></div>
            </div>
        </div>

        <div class="page-header">
            <h2 style="color: #1f2937; margin: 0;">All Orders (<?= count($orders) ?>)</h2>
            <?php if (!empty($orders)): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to DELETE ALL ORDERS? This cannot be undone!');">
                    <button type="submit" name="delete_all_orders" class="delete-all-btn">🗑️ Delete All Orders</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="order-card">
                <p style="text-align: center; color: #6b7280;">No orders found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                            <div class="order-date">📅 <?= formatDateTime($order['created_at'], 'F d, Y • H:i') ?></div>
                        </div>
                        <span class="badge badge-<?= $order['status'] ?>">
                            <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="order-section">
                            <h4>👤 Customer Information</h4>
                            <div class="customer-info">
                                <strong>Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?><br>
                                <strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?><br>
                                <strong>Email:</strong> <?= htmlspecialchars($order['customer_email'] ?? 'N/A') ?><br>
                                <strong>Address:</strong> <?= htmlspecialchars($order['customer_address'] ?? 'N/A') ?>
                            </div>
                        </div>

                        <div class="order-section">
                            <h4>📦 Order Items (<?= count($order['items']) ?>)</h4>
                            <div class="order-items">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                        <small style="color: #6b7280;">
                                            Qty: <?= $item['quantity'] ?> ×
                                            <?= number_format($item['unit_price'], 0) ?> <?= CURRENCY ?> =
                                            <strong><?= number_format($item['total_price'], 0) ?> <?= CURRENCY ?></strong>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="order-footer">
                        <div class="order-total">
                            💰 Total: <?= number_format($order['total_amount'], 0) ?> <?= CURRENCY ?>
                        </div>

                        <div class="status-form">
                            <form method="POST" style="display: inline-flex; gap: 10px; align-items: center;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <select name="status" class="status-select">
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                    <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                                    <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>📦 Preparing</option>
                                    <option value="on_the_way" <?= $order['status'] === 'on_the_way' ? 'selected' : '' ?>>🚚 On the Way</option>
                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>✅ Delivered</option>
                                    <option value="out_of_stock" <?= $order['status'] === 'out_of_stock' ? 'selected' : '' ?>>❌ Out of Stock</option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>🚫 Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="update-btn">Update Status</button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete order <?= htmlspecialchars($order['order_number']) ?>?');">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" name="delete_order" class="delete-btn">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>

                    <?php if ($order['notes']): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #fef3c7; border-radius: 6px; font-size: 0.9em;">
                            📝 <strong>Notes:</strong> <?= htmlspecialchars($order['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
