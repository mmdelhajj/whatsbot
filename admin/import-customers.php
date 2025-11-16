<?php
/**
 * Admin Dashboard - Import Customers from Brains
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_customers'])) {
    try {
        $startTime = microtime(true);

        // Fetch all customer accounts from Brains
        $accounts = $brainsAPI->fetchAccounts();

        if (!$accounts || !is_array($accounts)) {
            throw new Exception("Failed to fetch accounts from Brains API");
        }

        // Process each account
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($accounts as $account) {
            $accountCode = $account['AccountCode'] ?? null;
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

            // If no valid phone numbers found, skip
            if (empty($phoneNumbers)) {
                $skipped++;
                continue;
            }

            // Process each phone number
            foreach ($phoneNumbers as $phone) {
                // Check if customer already exists
                $existingCustomer = $db->fetchOne(
                    "SELECT * FROM customers WHERE phone = ?",
                    [$phone]
                );

                if ($existingCustomer) {
                    // Update existing customer
                    $updateData = [];

                    if (empty($existingCustomer['name']) || $existingCustomer['name'] !== $cleanName) {
                        $updateData['name'] = $cleanName;
                    }
                    if ($email && empty($existingCustomer['email'])) {
                        $updateData['email'] = $email;
                    }
                    if ($address && empty($existingCustomer['address'])) {
                        $updateData['address'] = $address;
                    }
                    if (!empty($accountCode) && empty($existingCustomer['brains_account_code'])) {
                        $updateData['brains_account_code'] = $accountCode;
                    }

                    if (!empty($updateData)) {
                        $db->update('customers', $updateData, 'id = :id', ['id' => $existingCustomer['id']]);
                        $updated++;
                    }
                } else {
                    // Create new customer
                    $db->insert('customers', [
                        'phone' => $phone,
                        'name' => $cleanName,
                        'email' => $email ?: null,
                        'address' => $address ?: null,
                        'brains_account_code' => $accountCode
                    ]);
                    $created++;
                }

                break; // Only use first valid phone number per account
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $syncResults = [
            'total_accounts' => count($accounts),
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

// Get current customer statistics
$stats = $db->fetchOne("
    SELECT
        COUNT(*) as total_customers,
        COUNT(CASE WHEN name IS NOT NULL AND TRIM(name) != '' THEN 1 END) as with_names,
        COUNT(CASE WHEN brains_account_code IS NOT NULL THEN 1 END) as linked_to_brains
    FROM customers
");

// Handle search
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sampleCustomers = [];

if (!empty($searchQuery)) {
    // Search by phone or name
    $searchTerm = "%{$searchQuery}%";
    $sampleCustomers = $db->fetchAll("
        SELECT phone, name, brains_account_code, email, address, created_at
        FROM customers
        WHERE phone LIKE ? OR name LIKE ?
        ORDER BY created_at DESC
        LIMIT 50
    ", [$searchTerm, $searchTerm]);
} else {
    // Get recent customers
    $sampleCustomers = $db->fetchAll("
        SELECT phone, name, brains_account_code, email, address, created_at
        FROM customers
        WHERE name IS NOT NULL AND TRIM(name) != ''
        ORDER BY created_at DESC
        LIMIT 20
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Customers - <?= STORE_NAME ?> Admin</title>
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
        .sample-list { list-style: none; padding: 0; }
        .sample-list li { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .sample-list li:last-child { border-bottom: none; }
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
            
            
            <a href="/admin/import-customers.php" class="active">Import Customers</a>
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
                <h3>Total Customers</h3>
                <div class="value"><?= number_format($stats['total_customers'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>With Names</h3>
                <div class="value"><?= number_format($stats['with_names'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Linked to Brains</h3>
                <div class="value"><?= number_format($stats['linked_to_brains'] ?? 0) ?></div>
            </div>
        </div>

        <div class="section">
            <h2>📥 Import All Customers from Brains ERP</h2>
            <p>This will import ALL customer accounts from Brains ERP system. Customers with valid phone numbers will be added to the database with their names.</p>

            <form method="POST" onsubmit="return confirm('⚠️ Import all customers from Brains? This may take a few seconds.');">
                <button type="submit" name="import_customers" class="import-btn">
                    📥 Import Customers from Brains
                </button>
            </form>

            <?php if ($syncResults): ?>
            <div class="results">
                <h3>📊 Import Results</h3>
                <div class="result-row">
                    <span>Total Brains Accounts:</span>
                    <strong><?= number_format($syncResults['total_accounts']) ?></strong>
                </div>
                <div class="result-row">
                    <span>New Customers Created:</span>
                    <strong style="color: #10b981;"><?= number_format($syncResults['created']) ?></strong>
                </div>
                <div class="result-row">
                    <span>Existing Updated:</span>
                    <strong style="color: #3b82f6;"><?= number_format($syncResults['updated']) ?></strong>
                </div>
                <div class="result-row">
                    <span>Skipped (no phone):</span>
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
            <h2>🔍 Search Customers</h2>

            <form method="GET" style="margin-bottom: 20px;">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>"
                           placeholder="Search by phone or name (e.g., 03080203 or Tony)"
                           style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 1em;">
                    <button type="submit" style="background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        🔍 Search
                    </button>
                    <?php if (!empty($searchQuery)): ?>
                    <a href="import-customers.php" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: flex; align-items: center;">
                        ✕ Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($searchQuery)): ?>
                <p style="color: #6b7280; margin-bottom: 15px;">
                    <?= count($sampleCustomers) ?> result(s) for "<?= htmlspecialchars($searchQuery) ?>"
                </p>
            <?php endif; ?>

            <?php if (empty($sampleCustomers)): ?>
                <p>No customers found. <?= empty($searchQuery) ? 'Click "Import Customers" above to import from Brains.' : 'Try a different search term.' ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Phone</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Brains Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sampleCustomers as $customer): ?>
                        <tr>
                            <td><?= htmlspecialchars($customer['phone']) ?></td>
                            <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                            <td><?= htmlspecialchars($customer['email'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($customer['brains_account_code'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (empty($searchQuery)): ?>
                <p style="color: #6b7280; margin-top: 15px; text-align: center;">
                    Showing recent 20 customers. Use search to find specific customers.
                </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
