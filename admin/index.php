<?php
/**
 * Admin Dashboard - Login & Main Page
 */

require_once dirname(__DIR__) . '/config/config.php';

session_start();

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $db = Database::getInstance();
    $user = $db->fetchOne(
        "SELECT * FROM admin_users WHERE username = ?",
        [$username]
    );

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];

        // Update last login
        $db->update('admin_users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $user['id']]
        );

        header('Location: /admin');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin');
    exit;
}

// Check if logged in
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

if (!$loggedIn) {
    // Show login form
    include 'pages/login.php';
    exit;
}

// Get statistics
$db = Database::getInstance();

$customerModel = new Customer();
$messageModel = new Message();
$productModel = new Product();
$orderModel = new Order();

$customerStats = $customerModel->getStats();
$messageStats = $messageModel->getStats();
$productStats = $productModel->getStats();
$orderStats = $orderModel->getStats();

// Get recent activity
$recentMessages = $messageModel->getAllWithCustomers(10);
$recentOrders = $orderModel->getAllWithCustomers(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= STORE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f3f4f6;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 1.5em;
        }
        .nav {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 20px;
        }
        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 20px;
        }
        .nav a {
            display: inline-block;
            padding: 15px 20px;
            text-decoration: none;
            color: #374151;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .nav a:hover, .nav a.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #6b7280;
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .stat-card .sub {
            color: #059669;
            font-size: 0.9em;
        }
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section h2 {
            margin-bottom: 20px;
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-info { background: #dbeafe; color: #0284c7; }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.85em;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📚 <?= STORE_NAME ?> - Admin Dashboard</h1>
            <div>
                <span style="opacity: 0.9; margin-right: 15px;">👤 <?= $_SESSION['admin_username'] ?></span>
                <a href="?logout" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="nav">
        <div class="nav-content">
            <a href="/admin" class="active">Dashboard</a>
            <a href="/admin/customers.php">Customers</a>
            <a href="/admin/messages.php">Messages</a>
            <a href="/admin/orders.php">Orders</a>
            <a href="/admin/products.php">Products</a>
            <a href="/admin/custom-qa.php">Custom Q&A</a>
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/webhook-setup.php">Webhook</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <?php // Temporarily disabled license banner - causes fatal error
        // include 'license-banner.php';
        ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="value"><?= number_format($customerStats['total_customers']) ?></div>
                <div class="sub">+<?= $customerStats['new_this_week'] ?> this week</div>
            </div>

            <div class="stat-card">
                <h3>Total Messages</h3>
                <div class="value"><?= number_format($messageStats['total_messages']) ?></div>
                <div class="sub"><?= number_format($messageStats['last_24h']) ?> in last 24h</div>
            </div>

            <div class="stat-card">
                <h3>Total Products</h3>
                <div class="value"><?= number_format($productStats['total_products']) ?></div>
                <div class="sub"><?= number_format($productStats['in_stock']) ?> in stock</div>
            </div>

            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?= number_format($orderStats['total_orders']) ?></div>
                <div class="sub"><?= number_format($orderStats['pending_orders']) ?> pending</div>
            </div>
        </div>

        <!-- Recent Messages -->
        <div class="section">
            <h2>Recent Messages</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Customer</th>
                        <th>Direction</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentMessages as $msg): ?>
                    <tr>
                        <td><?= formatDateTime($msg['created_at'], 'H:i') ?></td>
                        <td><?= htmlspecialchars($msg['customer_name'] ?? $msg['phone']) ?></td>
                        <td>
                            <?php if ($msg['direction'] === 'RECEIVED'): ?>
                                <span class="badge badge-info">← Received</span>
                            <?php else: ?>
                                <span class="badge badge-success">→ Sent</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(substr($msg['message'], 0, 80)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Orders -->
        <div class="section">
            <h2>Recent Orders</h2>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_number']) ?></td>
                        <td><?= htmlspecialchars($order['customer_name'] ?? $order['phone']) ?></td>
                        <td><?= number_format($order['total_amount'], 0) ?> <?= CURRENCY ?></td>
                        <td><?= $order['item_count'] ?> items</td>
                        <td>
                            <?php
                            $statusClass = $order['status'] === 'confirmed' ? 'success' : 'warning';
                            ?>
                            <span class="badge badge-<?= $statusClass ?>"><?= $order['status'] ?></span>
                        </td>
                        <td><?= formatDateTime($order['created_at'], 'Y-m-d H:i') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
