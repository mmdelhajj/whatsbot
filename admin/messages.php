<?php
/**
 * Admin Dashboard - Messages
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();

// Handle delete all messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_messages'])) {
    $db->query("DELETE FROM messages");
    $successMessage = "All messages deleted successfully! ✅";
}

// Get recent messages
$messages = $db->fetchAll("
    SELECT
        m.*,
        c.name as customer_name,
        c.phone as customer_phone
    FROM messages m
    LEFT JOIN customers c ON m.customer_id = c.id
    ORDER BY m.created_at DESC
    LIMIT 100
");

// Get statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_messages,
        COUNT(CASE WHEN direction = 'incoming' THEN 1 END) as incoming,
        COUNT(CASE WHEN direction = 'outgoing' THEN 1 END) as outgoing,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
    FROM messages
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?= STORE_NAME ?> Admin</title>
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
        .badge-incoming { background: #d1fae5; color: #059669; }
        .badge-outgoing { background: #dbeafe; color: #0284c7; }
        .message-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { margin: 0; }
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
            <a href="/admin/customers.php">Customers</a>
            <a href="/admin/messages.php" class="active">Messages</a>
            <a href="/admin/orders.php">Orders</a>
            <a href="/admin/products.php">Products</a>
            
            
            <a href="/admin/import-customers.php">Import Customers</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Messages</h3>
                <div class="value"><?= number_format($stats['total_messages'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Incoming</h3>
                <div class="value"><?= number_format($stats['incoming'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Outgoing</h3>
                <div class="value"><?= number_format($stats['outgoing'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Today</h3>
                <div class="value"><?= number_format($stats['today'] ?? 0) ?></div>
            </div>
        </div>

        <?php if (isset($successMessage)): ?>
            <div class="success">✅ <?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <div class="section">
            <div class="section-header">
                <h2>Recent Messages (<?= count($messages) ?>)</h2>
                <?php if (!empty($messages)): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to DELETE ALL MESSAGES? This cannot be undone!');">
                        <button type="submit" name="delete_all_messages" class="delete-all-btn">🗑️ Delete All Messages</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (empty($messages)): ?>
                <p>No messages found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Direction</th>
                            <th>Message</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= formatDateTime($msg['created_at'], 'M d, H:i') ?></td>
                            <td>
                                <?= htmlspecialchars($msg['customer_name'] ?? 'Unknown') ?><br>
                                <small style="color: #6b7280;"><?= htmlspecialchars($msg['customer_phone']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $msg['direction'] ?>">
                                    <?= $msg['direction'] === 'incoming' ? '📥' : '📤' ?> <?= ucfirst($msg['direction']) ?>
                                </span>
                            </td>
                            <td class="message-preview"><?= htmlspecialchars($msg['content']) ?></td>
                            <td><?= htmlspecialchars($msg['status'] ?? 'sent') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
