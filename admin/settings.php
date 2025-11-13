<?php
/**
 * Admin Dashboard - API & Settings Configuration
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$message = '';
$messageType = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $envFile = dirname(__DIR__) . '/.env';

        // Read current .env file
        $envContent = file_get_contents($envFile);

        // Update each setting
        $updates = [
            'DB_HOST' => $_POST['db_host'] ?? 'localhost',
            'DB_NAME' => $_POST['db_name'] ?? 'whatsapp_bot',
            'DB_USER' => $_POST['db_user'] ?? 'whatsapp_user',
            'DB_PASS' => $_POST['db_pass'] ?? '',
            'BRAINS_API_BASE' => $_POST['brains_api_base'] ?? '',
            'WHATSAPP_ACCOUNT_ID' => $_POST['whatsapp_account_id'] ?? '',
            'WHATSAPP_SEND_SECRET' => $_POST['whatsapp_send_secret'] ?? '',
            'WEBHOOK_SECRET' => $_POST['webhook_secret'] ?? '',
            'ANTHROPIC_API_KEY' => $_POST['anthropic_api_key'] ?? '',
            'TIMEZONE' => $_POST['timezone'] ?? 'Asia/Beirut',
            'CURRENCY' => $_POST['currency'] ?? 'LBP',
            'STORE_NAME' => $_POST['store_name'] ?? '',
            'STORE_LOCATION' => $_POST['store_location'] ?? ''
        ];

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                // Add the key if it doesn't exist
                $envContent .= "\n{$replacement}";
            }
        }

        // Write back to .env file
        if (file_put_contents($envFile, $envContent)) {
            $message = '✅ Settings saved successfully!';
            $messageType = 'success';
        } else {
            throw new Exception('Failed to write to .env file');
        }

    } catch (Exception $e) {
        $message = '❌ Error saving settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Load current settings
$currentSettings = [
    'db_host' => env('DB_HOST', 'localhost'),
    'db_name' => env('DB_NAME', 'whatsapp_bot'),
    'db_user' => env('DB_USER', 'whatsapp_user'),
    'db_pass' => env('DB_PASS', ''),
    'brains_api_base' => env('BRAINS_API_BASE', ''),
    'whatsapp_account_id' => env('WHATSAPP_ACCOUNT_ID', ''),
    'whatsapp_send_secret' => env('WHATSAPP_SEND_SECRET', ''),
    'webhook_secret' => env('WEBHOOK_SECRET', ''),
    'anthropic_api_key' => env('ANTHROPIC_API_KEY', ''),
    'timezone' => env('TIMEZONE', 'Asia/Beirut'),
    'currency' => env('CURRENCY', 'LBP'),
    'store_name' => env('STORE_NAME', ''),
    'store_location' => env('STORE_LOCATION', '')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= STORE_NAME ?> Admin</title>
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
        .section h2 { margin-bottom: 20px; color: #1f2937; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 1em; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .form-group small { color: #6b7280; font-size: 0.875em; }
        .save-btn { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 1em; font-weight: 600; cursor: pointer; }
        .save-btn:hover { background: #059669; }
        .message { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d1fae5; color: #059669; }
        .message.error { background: #fee2e2; color: #dc2626; }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
        .password-field { position: relative; }
        .password-field input { padding-right: 45px; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2em; }
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
            <a href="/admin/sync.php">Sync Products</a>
            <a href="/admin/sync-customers.php">Sync Customers</a>
            <a href="/admin/import-customers.php">Import Customers</a>
            <a href="/admin/settings.php" class="active">Settings</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="section">
                <h2>⚙️ Database Configuration</h2>
                <div class="settings-grid">
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" name="db_host" value="<?= htmlspecialchars($currentSettings['db_host']) ?>" required>
                        <small>Usually "localhost" or IP address</small>
                    </div>
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="db_name" value="<?= htmlspecialchars($currentSettings['db_name']) ?>" required>
                        <small>Name of your MySQL database</small>
                    </div>
                    <div class="form-group">
                        <label>Database User</label>
                        <input type="text" name="db_user" value="<?= htmlspecialchars($currentSettings['db_user']) ?>" required>
                        <small>MySQL username</small>
                    </div>
                    <div class="form-group password-field">
                        <label>Database Password</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($currentSettings['db_pass']) ?>">
                        <button type="button" class="toggle-password" onclick="togglePassword('db_pass')">👁️</button>
                        <small>MySQL password</small>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>🏪 Brains ERP API</h2>
                <div class="form-group">
                    <label>Brains API Base URL</label>
                    <input type="url" name="brains_api_base" value="<?= htmlspecialchars($currentSettings['brains_api_base']) ?>" placeholder="http://194.126.6.162:1980/Api">
                    <small>Base URL for Brains ERP API (e.g., http://194.126.6.162:1980/Api)</small>
                </div>
            </div>

            <div class="section">
                <h2>📱 WhatsApp API (ProxSMS)</h2>
                <div class="settings-grid">
                    <div class="form-group">
                        <label>WhatsApp Account ID</label>
                        <input type="text" name="whatsapp_account_id" value="<?= htmlspecialchars($currentSettings['whatsapp_account_id']) ?>" placeholder="175694056798f13708210194c475687be6106a3b8468b8c91786bda">
                        <small>Your ProxSMS account ID</small>
                    </div>
                    <div class="form-group password-field">
                        <label>WhatsApp Send Secret</label>
                        <input type="password" id="whatsapp_send_secret" name="whatsapp_send_secret" value="<?= htmlspecialchars($currentSettings['whatsapp_send_secret']) ?>" placeholder="05137479c63e44c87414fde1f24c8ccdd59e7925">
                        <button type="button" class="toggle-password" onclick="togglePassword('whatsapp_send_secret')">👁️</button>
                        <small>API secret for sending messages</small>
                    </div>
                    <div class="form-group password-field">
                        <label>Webhook Secret</label>
                        <input type="password" id="webhook_secret" name="webhook_secret" value="<?= htmlspecialchars($currentSettings['webhook_secret']) ?>" placeholder="4d1de32612f6f6cd9dc23ea4938c69ffb7e29725">
                        <button type="button" class="toggle-password" onclick="togglePassword('webhook_secret')">👁️</button>
                        <small>Secret for validating incoming webhooks</small>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>🤖 Anthropic Claude AI</h2>
                <div class="form-group password-field">
                    <label>Anthropic API Key</label>
                    <input type="password" id="anthropic_api_key" name="anthropic_api_key" value="<?= htmlspecialchars($currentSettings['anthropic_api_key']) ?>" placeholder="sk-ant-...">
                    <button type="button" class="toggle-password" onclick="togglePassword('anthropic_api_key')">👁️</button>
                    <small>Your Anthropic API key for Claude AI</small>
                </div>
            </div>

            <div class="section">
                <h2>🏢 Store Information</h2>
                <div class="settings-grid">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" value="<?= htmlspecialchars($currentSettings['store_name']) ?>" required>
                        <small>Name of your store/business</small>
                    </div>
                    <div class="form-group">
                        <label>Store Location</label>
                        <input type="text" name="store_location" value="<?= htmlspecialchars($currentSettings['store_location']) ?>">
                        <small>Physical location or city</small>
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone">
                            <option value="Asia/Beirut" <?= $currentSettings['timezone'] === 'Asia/Beirut' ? 'selected' : '' ?>>Asia/Beirut</option>
                            <option value="America/New_York" <?= $currentSettings['timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
                            <option value="Europe/London" <?= $currentSettings['timezone'] === 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
                            <option value="Asia/Dubai" <?= $currentSettings['timezone'] === 'Asia/Dubai' ? 'selected' : '' ?>>Asia/Dubai</option>
                            <option value="UTC" <?= $currentSettings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        </select>
                        <small>Timezone for timestamps and scheduling</small>
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <input type="text" name="currency" value="<?= htmlspecialchars($currentSettings['currency']) ?>" maxlength="10" required>
                        <small>Currency code (e.g., LBP, USD, EUR)</small>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" name="save_settings" class="save-btn">💾 Save All Settings</button>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
            } else {
                field.type = 'password';
            }
        }
    </script>
</body>
</html>
