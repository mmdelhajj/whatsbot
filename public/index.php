<?php
/**
 * Main index page
 * Shows basic system information
 */

require_once dirname(__DIR__) . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= STORE_NAME ?> - WhatsApp Bot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .status {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            border-radius: 8px;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-label {
            font-weight: 600;
            color: #374151;
        }
        .status-value {
            color: #059669;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            margin: 10px;
            transition: background 0.3s;
        }
        .button:hover {
            background: #5568d3;
        }
        .emoji {
            font-size: 3em;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">📚💬</div>
        <h1><?= STORE_NAME ?></h1>
        <p class="subtitle">AI-Powered WhatsApp Bot</p>

        <div class="status">
            <div class="status-item">
                <span class="status-label">System Status:</span>
                <span class="status-value">✅ Online</span>
            </div>
            <div class="status-item">
                <span class="status-label">Location:</span>
                <span class="status-value"><?= STORE_LOCATION ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">AI Model:</span>
                <span class="status-value"><?= ANTHROPIC_MODEL ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Timezone:</span>
                <span class="status-value"><?= TIMEZONE ?></span>
            </div>
        </div>

        <a href="/admin" class="button">🔐 Admin Dashboard</a>
        <a href="/admin/test-apis.php" class="button">🧪 Test APIs</a>

        <p style="margin-top: 30px; color: #999; font-size: 0.9em;">
            Webhook Endpoint: <code><?= $_SERVER['HTTP_HOST'] ?>/webhook-whatsapp</code>
        </p>
    </div>
</body>
</html>
