<?php
/**
 * Admin Dashboard - Sync Customer Names from Brains
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();
$successMessage = null;
$errorMessage = null;

// Handle sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_customers'])) {
    try {
        $brainsAPI = new BrainsAPI();

        // Fetch all customer accounts from Brains
        $accounts = $brainsAPI->fetchAccounts();

        if (!$accounts || !is_array($accounts)) {
            throw new Exception("Failed to fetch accounts from Brains API");
        }

        // Process each account
        $matched = 0;
        $updated = 0;

        foreach ($accounts as $account) {
            $customerName = $account['Description'] ?? null;
            $telephone = $account['Telephone'] ?? '';
            $email = $account['Email'] ?? '';
            $address = $account['Address'] ?? '';

            // Skip if no customer name
            if (!$customerName) {
                continue;
            }

            // Clean up phone number - extract from BOTH Telephone AND Description fields
            $phoneNumbers = [];

            // Combine both fields to search for phone numbers (phone might be in name field!)
            $searchText = $telephone . ' ' . $customerName;

            // Remove phone numbers from customer name (keep only the actual name)
            $cleanName = preg_replace('/\d{2}[\/\s]*\d{3}[\/\s]*\d{3}|\d{8}/', '', $customerName);
            $cleanName = trim($cleanName);
            // Remove trailing dots and spaces
            $cleanName = rtrim($cleanName, '. ');

            if (preg_match_all('/(\d{2}\/\d{6}|\d{2}\/\d{3}\s*\d{3}|\d{8})/', $searchText, $matches)) {
                foreach ($matches[0] as $phone) {
                    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

                    if (strlen($cleanPhone) == 8) {
                        // Already has leading 0: 03080203
                        $phoneNumbers[] = $cleanPhone;
                    } elseif (strlen($cleanPhone) == 6) {
                        // Add leading 0: 980203 -> 0980203
                        $phoneNumbers[] = '0' . $cleanPhone;
                    }
                }
            }

            // Remove duplicates
            $phoneNumbers = array_unique($phoneNumbers);

            // Try to find matching customer in local database
            foreach ($phoneNumbers as $phone) {
                $customer = $db->fetchOne(
                    "SELECT * FROM customers WHERE phone = ?",
                    [$phone]
                );

                if ($customer) {
                    $matched++;

                    // Update customer data if not already set
                    if (empty($customer['name']) || $customer['name'] !== $cleanName) {
                        $updateData = ['name' => $cleanName];

                        if ($email && empty($customer['email'])) {
                            $updateData['email'] = $email;
                        }
                        if ($address && empty($customer['address'])) {
                            $updateData['address'] = $address;
                        }

                        $db->update('customers', $updateData, 'id = :id', ['id' => $customer['id']]);
                        $updated++;
                    }

                    break;
                }
            }
        }

        $successMessage = "Sync completed! Total accounts: " . count($accounts) . " | Matched: {$matched} | Updated: {$updated}";

    } catch (Exception $e) {
        $errorMessage = "Sync failed: " . $e->getMessage();
    }
}

// Get statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_customers,
        COUNT(CASE WHEN name IS NOT NULL AND name != '' THEN 1 END) as with_names,
        COUNT(CASE WHEN name IS NULL OR name = '' THEN 1 END) as without_names
    FROM customers
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Customers - <?= STORE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5em; }
        .logout-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .nav { background: white; border-bottom: 1px solid #e5e7eb; padding: 0 20px; }
        .nav-content { max-width: 1200px; margin: 0 auto; display: flex; gap: 20px; }
        .nav a { display: inline-block; padding: 15px 20px; text-decoration: none; color: #374151; border-bottom: 3px solid transparent; }
        .nav a:hover, .nav a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #6b7280; font-size: 0.9em; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .value { font-size: 2em; font-weight: bold; color: #1f2937; }
        .success { background: #d1fae5; color: #059669; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .sync-btn { background: #667eea; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-size: 1.1em; cursor: pointer; font-weight: 600; }
        .sync-btn:hover { background: #5568d3; }
        h2 { color: #1f2937; margin-bottom: 20px; }
        .info { background: #dbeafe; color: #0284c7; padding: 15px; border-radius: 8px; margin-bottom: 20px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🔄 <?= STORE_NAME ?> - Sync Customers</h1>
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
            <a href="/admin/sync.php">Sync Products</a>
            <a href="/admin/sync-customers.php" class="active">Sync Customers</a>
            <a href="/admin/settings.php">Settings</a>
        </div>
    </div>

    <div class="container">
        <?php if ($successMessage): ?>
            <div class="success">✅ <?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error">❌ <?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="value"><?= number_format($stats['total_customers'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>With Names</h3>
                <div class="value" style="color: #059669;"><?= number_format($stats['with_names'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Without Names</h3>
                <div class="value" style="color: #dc2626;"><?= number_format($stats['without_names'] ?? 0) ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Sync Customer Names from Brains ERP</h2>

            <div class="info">
                <strong>ℹ️ How it works:</strong><br>
                • Fetches customer accounts from Brains API<br>
                • Matches WhatsApp customers by phone number<br>
                • Updates customer names automatically<br>
                • When customers say "hi", bot will greet them by name!<br><br>
                <strong>API Endpoint:</strong> <?= BRAINS_API_BASE ?>/accounts?type=1&accocode=41110
            </div>

            <form method="POST">
                <button type="submit" name="sync_customers" class="sync-btn">
                    🔄 Sync Customer Names from Brains
                </button>
            </form>
        </div>

        <div class="card">
            <h2>📝 How Customer Names Work</h2>
            <p style="line-height: 1.8; color: #374151;">
                <strong>1. From Orders:</strong> When a customer places an order and enters their name, it's saved to the database.<br><br>
                <strong>2. From Brains Sync:</strong> This page syncs customer names from your Brains ERP by matching phone numbers.<br><br>
                <strong>3. Welcome Messages:</strong> When a customer says "hi" or "hello", the bot will greet them:<br>
                • <em>With name:</em> "Hello Mohamad el hajj! 👋"<br>
                • <em>Without name:</em> "Hello! 👋"<br><br>
                <strong>4. Multi-language:</strong> Works in English, Arabic, and French automatically!
            </p>
        </div>
    </div>
</body>
</html>
