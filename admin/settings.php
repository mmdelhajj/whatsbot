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

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All password fields are required');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match');
        }

        if (strlen($newPassword) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }

        // Verify current password
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM admin_users WHERE id = ?",
            [$_SESSION['admin_user_id']]
        );

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            throw new Exception('Current password is incorrect');
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $db->update('admin_users',
            ['password' => $hashedPassword],
            'id = :id',
            ['id' => $_SESSION['admin_user_id']]
        );

        $message = '✅ Password changed successfully!';
        $messageType = 'success';

    } catch (Exception $e) {
        $message = '❌ Error changing password: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $envFile = dirname(__DIR__) . '/.env';

        // Read current .env file
        $envContent = file_get_contents($envFile);

        // Update each setting
        $updates = [
            'BRAINS_API_BASE' => $_POST['brains_api_base'] ?? '',
            'WHATSAPP_ACCOUNT_ID' => $_POST['whatsapp_account_id'] ?? '',
            'WHATSAPP_SEND_SECRET' => $_POST['whatsapp_send_secret'] ?? '',
            'WEBHOOK_SECRET' => $_POST['webhook_secret'] ?? '',
            'TIMEZONE' => $_POST['timezone'] ?? 'Asia/Beirut',
            'CURRENCY' => $_POST['currency'] ?? 'LBP',
            'STORE_NAME' => $_POST['store_name'] ?? '',
            'STORE_LOCATION' => $_POST['store_location'] ?? '',
            'STORE_LATITUDE' => $_POST['store_latitude'] ?? '34.00951559789577',
            'STORE_LONGITUDE' => $_POST['store_longitude'] ?? '35.654434764102675',
            'STORE_PHONE' => $_POST['store_phone'] ?? '',
            'STORE_WEBSITE' => $_POST['store_website'] ?? '',
            'STORE_HOURS' => $_POST['store_hours'] ?? '',
            'SYNC_INTERVAL' => $_POST['sync_interval'] ?? '240'
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
            // Reload PHP-FPM in background after 2 seconds (so this response completes first)
            exec('(sleep 2 && sudo systemctl reload php8.1-fpm) > /dev/null 2>&1 &');

            $message = '✅ Settings saved successfully! Changes will take effect in a few seconds.';
            $messageType = 'success';
        } else {
            throw new Exception('Failed to write to .env file');
        }

    } catch (Exception $e) {
        $message = '❌ Error saving settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Load current settings directly from .env file (to avoid getenv cache issues)
function loadEnvSettings() {
    $envFile = dirname(__DIR__) . '/.env';
    $settings = [];

    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $settings[strtolower($key)] = $value;
        }
    }

    return $settings;
}

$envSettings = loadEnvSettings();

// Get license information
$licenseInfo = [
    'key' => $envSettings['license_key'] ?? '',
    'domain' => $envSettings['site_domain'] ?? '',
    'server_url' => $envSettings['license_server_url'] ?? 'https://lic.proxpanel.com',
    'enabled' => ($envSettings['license_check_enabled'] ?? 'true') === 'true'
];

// Fetch license status from server
$licenseStatus = null;
if (!empty($licenseInfo['key'])) {
    $licenseValidator = new LicenseValidator();
    $fingerprint = $licenseValidator->getLicenseInfo()['fingerprint'];

    $validateUrl = $licenseInfo['server_url'] . '/api/validate.php?' . http_build_query([
        'key' => $licenseInfo['key'],
        'domain' => $licenseInfo['domain'],
        'fingerprint' => $fingerprint
    ]);

    $ch = curl_init($validateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $licenseStatus = json_decode($response, true);
    }
}

$currentSettings = [
    'brains_api_base' => $envSettings['brains_api_base'] ?? '',
    'whatsapp_account_id' => $envSettings['whatsapp_account_id'] ?? '',
    'whatsapp_send_secret' => $envSettings['whatsapp_send_secret'] ?? '',
    'webhook_secret' => $envSettings['webhook_secret'] ?? '',
    'timezone' => $envSettings['timezone'] ?? 'Asia/Beirut',
    'currency' => $envSettings['currency'] ?? 'LBP',
    'store_name' => $envSettings['store_name'] ?? '',
    'store_location' => $envSettings['store_location'] ?? '',
    'store_latitude' => $envSettings['store_latitude'] ?? '34.00951559789577',
    'store_longitude' => $envSettings['store_longitude'] ?? '35.654434764102675',
    'store_phone' => $envSettings['store_phone'] ?? '',
    'store_website' => $envSettings['store_website'] ?? '',
    'store_hours' => $envSettings['store_hours'] ?? '',
    'sync_interval' => $envSettings['sync_interval'] ?? '240'
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
            <a href="/admin/custom-qa.php">Custom Q&A</a>
            
            
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

        <!-- Change Password Section -->
        <div class="section">
            <h2>🔐 Change Admin Password</h2>
            <form method="POST">
                <div class="settings-grid">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                        <small>Enter your current password</small>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                        <small>Re-enter new password</small>
                    </div>
                </div>
                <button type="submit" name="change_password" class="save-btn">🔒 Change Password</button>
            </form>
        </div>

        <!-- License Information Section (Read-Only) -->
        <div class="section">
            <h2>🔑 License Information</h2>
            <div class="settings-grid">
                <div class="form-group">
                    <label>License Key</label>
                    <input type="text" value="<?= htmlspecialchars($licenseInfo['key'] ?: 'Not configured') ?>" readonly style="background: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Domain</label>
                    <input type="text" value="<?= htmlspecialchars($licenseInfo['domain'] ?: 'Not configured') ?>" readonly style="background: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>License Server</label>
                    <input type="text" value="<?= htmlspecialchars($licenseInfo['server_url']) ?>" readonly style="background: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <?php if ($licenseStatus && $licenseStatus['success']): ?>
                        <input type="text" value="✅ Valid - <?= htmlspecialchars($licenseStatus['data']['installation_type'] ?? 'active') ?>" readonly style="background: #d1fae5; color: #059669; cursor: not-allowed; font-weight: bold;">
                    <?php elseif ($licenseStatus): ?>
                        <input type="text" value="❌ <?= htmlspecialchars($licenseStatus['message'] ?? 'Invalid') ?>" readonly style="background: #fee2e2; color: #dc2626; cursor: not-allowed; font-weight: bold;">
                    <?php else: ?>
                        <input type="text" value="⚠️ Cannot connect to license server" readonly style="background: #fef3c7; color: #d97706; cursor: not-allowed; font-weight: bold;">
                    <?php endif; ?>
                </div>
                <?php if ($licenseStatus && $licenseStatus['success']): ?>
                <div class="form-group">
                    <label>Customer</label>
                    <input type="text" value="<?= htmlspecialchars($licenseStatus['data']['customer'] ?? '-') ?>" readonly style="background: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Expires</label>
                    <input type="text" value="<?= htmlspecialchars($licenseStatus['data']['expires_at'] ?? '-') ?> (<?= $licenseStatus['data']['days_left'] ?? 0 ?> days left)" readonly style="background: #f3f4f6; cursor: not-allowed;">
                </div>
                <?php endif; ?>
            </div>
            <small style="color: #6b7280; display: block; margin-top: 10px;">
                ℹ️ License information is read-only and cannot be changed from this panel. Contact your administrator to modify license settings.
            </small>
        </div>

        <form method="POST">
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
                <h2>🏢 Store Information</h2>
                <div class="settings-grid">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" value="<?= htmlspecialchars($currentSettings['store_name']) ?>" required>
                        <small>Name of your store/business (shown to customers)</small>
                    </div>
                    <div class="form-group">
                        <label>Store Location</label>
                        <input type="text" name="store_location" value="<?= htmlspecialchars($currentSettings['store_location']) ?>">
                        <small>Physical address or city (e.g., Kfarhbab, Ghazir, Lebanon)</small>
                    </div>
                    <div class="form-group">
                        <label>Store Latitude</label>
                        <input type="text" name="store_latitude" value="<?= htmlspecialchars($currentSettings['store_latitude']) ?>" placeholder="34.00951559789577">
                        <small>GPS Latitude for Google Maps (e.g., 34.00951559789577)</small>
                    </div>
                    <div class="form-group">
                        <label>Store Longitude</label>
                        <input type="text" name="store_longitude" value="<?= htmlspecialchars($currentSettings['store_longitude']) ?>" placeholder="35.654434764102675">
                        <small>GPS Longitude for Google Maps (e.g., 35.654434764102675)</small>
                    </div>
                    <div class="form-group">
                        <label>Store Phone</label>
                        <input type="text" name="store_phone" value="<?= htmlspecialchars($currentSettings['store_phone']) ?>" placeholder="+961 9 123456">
                        <small>Main phone number (shown to customers)</small>
                    </div>
                    <div class="form-group">
                        <label>Store Website</label>
                        <input type="url" name="store_website" value="<?= htmlspecialchars($currentSettings['store_website']) ?>" placeholder="https://example.com">
                        <small>Your website URL (optional)</small>
                    </div>
                    <div class="form-group">
                        <label>Business Hours</label>
                        <input type="text" name="store_hours" value="<?= htmlspecialchars($currentSettings['store_hours']) ?>" placeholder="Monday-Saturday 9:00 AM - 7:00 PM">
                        <small>Opening hours (shown to customers)</small>
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

            <div class="section">
                <h2>🔄 Automatic Sync Settings</h2>
                <div class="form-group">
                    <label>Sync Interval (Products & Customers from Brains)</label>
                    <select name="sync_interval">
                        <option value="1" <?= $currentSettings['sync_interval'] == '1' ? 'selected' : '' ?>>Every 1 Minute</option>
                        <option value="5" <?= $currentSettings['sync_interval'] == '5' ? 'selected' : '' ?>>Every 5 Minutes</option>
                        <option value="15" <?= $currentSettings['sync_interval'] == '15' ? 'selected' : '' ?>>Every 15 Minutes</option>
                        <option value="30" <?= $currentSettings['sync_interval'] == '30' ? 'selected' : '' ?>>Every 30 Minutes</option>
                        <option value="60" <?= $currentSettings['sync_interval'] == '60' ? 'selected' : '' ?>>Every 1 Hour</option>
                        <option value="120" <?= $currentSettings['sync_interval'] == '120' ? 'selected' : '' ?>>Every 2 Hours</option>
                        <option value="240" <?= $currentSettings['sync_interval'] == '240' ? 'selected' : '' ?>>Every 4 Hours (Recommended)</option>
                        <option value="360" <?= $currentSettings['sync_interval'] == '360' ? 'selected' : '' ?>>Every 6 Hours</option>
                        <option value="720" <?= $currentSettings['sync_interval'] == '720' ? 'selected' : '' ?>>Every 12 Hours</option>
                        <option value="1440" <?= $currentSettings['sync_interval'] == '1440' ? 'selected' : '' ?>>Once Daily</option>
                    </select>
                    <small>How often to automatically sync products and customers from Brains ERP. Requires cron job to be set up.</small>
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
