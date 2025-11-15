<?php
/**
 * Admin Dashboard - Webhook Setup Instructions
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

// Get webhook URL
$webhookUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/webhook-whatsapp.php';

// Check recent webhook logs
$recentLogs = [];
$logFile = dirname(__DIR__) . '/logs/webhook.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLogs = array_slice($lines, -10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Setup - <?= STORE_NAME ?> Admin</title>
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
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .warning-box { background: #fef3c7; border: 1px solid #fcd34d; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .success-box { background: #d1fae5; border: 1px solid #6ee7b7; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .code-box { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; margin: 10px 0; font-family: monospace; }
        .copy-btn { background: #667eea; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin-left: 10px; }
        .copy-btn:hover { background: #5568d3; }
        .step { background: #f9fafb; padding: 20px; border-left: 4px solid #667eea; margin-bottom: 20px; }
        .step h3 { margin-top: 0; color: #667eea; }
        ol { line-height: 1.8; }
        .log-entry { font-family: monospace; font-size: 0.85em; padding: 5px; border-bottom: 1px solid #e5e7eb; }
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
            
            <a href="/admin/settings.php">Settings</a>
            <a href="/admin/webhook-setup.php" class="active">Webhook</a>
            <a href="/admin/test-apis.php">API Tests</a>
        </div>
    </div>

    <div class="container">
        <div class="section">
            <h2>🔗 WhatsApp Webhook Configuration</h2>

            <?php if (empty($recentLogs)): ?>
            <div class="warning-box">
                <strong>⚠️ No webhook activity detected!</strong><br>
                Your webhook hasn't received any messages yet. Follow the steps below to configure ProxSMS to send incoming messages to your bot.
            </div>
            <?php else: ?>
            <div class="success-box">
                <strong>✅ Webhook is active!</strong><br>
                Last activity: <?= count($recentLogs) ?> recent entries found.
            </div>
            <?php endif; ?>

            <div class="info-box">
                <strong>📍 Your Webhook URL:</strong>
                <div class="code-box">
                    <?= $webhookUrl ?>
                    <button class="copy-btn" onclick="copyToClipboard('<?= $webhookUrl ?>')">Copy</button>
                </div>
            </div>

            <div class="info-box">
                <strong>🔐 Webhook Secret:</strong>
                <div class="code-box">
                    <?= WHATSAPP_WEBHOOK_SECRET ?>
                    <button class="copy-btn" onclick="copyToClipboard('<?= WHATSAPP_WEBHOOK_SECRET ?>')">Copy</button>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>📋 Setup Instructions</h2>

            <div class="step">
                <h3>Step 1: Login to ProxSMS</h3>
                <ol>
                    <li>Go to <a href="https://proxsms.com" target="_blank">proxsms.com</a></li>
                    <li>Login to your account</li>
                </ol>
            </div>

            <div class="step">
                <h3>Step 2: Configure Webhook</h3>
                <ol>
                    <li>Go to <strong>Settings</strong> → <strong>Webhooks</strong></li>
                    <li>Click <strong>"Add New Webhook"</strong> or <strong>"Edit"</strong> if one exists</li>
                    <li>Set <strong>Webhook URL</strong> to:<br>
                        <div class="code-box" style="margin-top: 10px;"><?= $webhookUrl ?></div>
                    </li>
                    <li>Set <strong>Webhook Secret</strong> to:<br>
                        <div class="code-box" style="margin-top: 10px;"><?= WHATSAPP_WEBHOOK_SECRET ?></div>
                    </li>
                    <li>Enable <strong>"WhatsApp Messages"</strong> events</li>
                    <li>Make sure the webhook is <strong>Active/Enabled</strong></li>
                    <li>Click <strong>"Save"</strong></li>
                </ol>
            </div>

            <div class="step">
                <h3>Step 3: Test the Webhook</h3>
                <ol>
                    <li>Send a WhatsApp message to your bot number: <strong>9613080203</strong></li>
                    <li>Type: <strong>"hello"</strong></li>
                    <li>Wait a few seconds for the bot to respond</li>
                    <li>Refresh this page to see webhook activity below</li>
                </ol>
            </div>

            <div class="step">
                <h3>Step 4: Verify Configuration</h3>
                <ol>
                    <li>Go to <a href="/admin/test-apis.php">API Tests</a> page</li>
                    <li>Test the Claude AI connection</li>
                    <li>Check that your API key is working</li>
                </ol>
            </div>
        </div>

        <?php if (!empty($recentLogs)): ?>
        <div class="section">
            <h2>📊 Recent Webhook Activity</h2>
            <div style="max-height: 400px; overflow-y: auto; background: #1f2937; padding: 15px; border-radius: 6px;">
                <?php foreach (array_reverse($recentLogs) as $log): ?>
                    <div class="log-entry" style="color: #f3f4f6;"><?= htmlspecialchars($log) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>🔍 Troubleshooting</h2>

            <h3>Bot doesn't respond to messages:</h3>
            <ul>
                <li>✓ Check that webhook URL is correctly configured in ProxSMS</li>
                <li>✓ Verify webhook secret matches</li>
                <li>✓ Make sure webhook is enabled/active</li>
                <li>✓ Check that Claude AI API key is valid</li>
                <li>✓ Verify your server can be reached from internet (not blocked by firewall)</li>
            </ul>

            <h3>Check Server Accessibility:</h3>
            <p>Your webhook URL must be publicly accessible. Test it:</p>
            <div class="code-box">
                curl -X POST <?= $webhookUrl ?> -d "type=whatsapp&secret=<?= WHATSAPP_WEBHOOK_SECRET ?>&data[phone]=test&data[message]=test"
            </div>

            <h3>View Full Logs:</h3>
            <p>SSH into your server and check logs:</p>
            <div class="code-box">
                tail -f /var/www/whatsapp-bot/logs/webhook.log
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied to clipboard!');
            }, function(err) {
                alert('Failed to copy');
            });
        }
    </script>
</body>
</html>
