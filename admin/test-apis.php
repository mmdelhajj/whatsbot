<?php
/**
 * API Testing Page
 * Test all external API connections
 */

require_once dirname(__DIR__) . '/config/config.php';

session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}

$results = [];

// Test Brains API
if (isset($_GET['test']) && $_GET['test'] === 'brains') {
    $brainsAPI = new BrainsAPI();

    $results['brains_connection'] = $brainsAPI->testConnection();

    // Test items
    try {
        $items = $brainsAPI->fetchItems();
        $results['brains_items'] = [
            'success' => is_array($items),
            'count' => is_array($items) ? count($items) : 0,
            'sample' => is_array($items) && count($items) > 0 ? array_slice($items, 0, 3) : []
        ];
    } catch (Exception $e) {
        $results['brains_items'] = ['success' => false, 'error' => $e->getMessage()];
    }

    // Test accounts
    try {
        $accounts = $brainsAPI->fetchAccounts();
        $results['brains_accounts'] = [
            'success' => is_array($accounts),
            'count' => is_array($accounts) ? count($accounts) : 0,
            'sample' => is_array($accounts) && count($accounts) > 0 ? array_slice($accounts, 0, 3) : []
        ];
    } catch (Exception $e) {
        $results['brains_accounts'] = ['success' => false, 'error' => $e->getMessage()];
    }
}

// Test Claude AI
if (isset($_GET['test']) && $_GET['test'] === 'claude') {
    $claudeAI = new ClaudeAI();
    $results['claude'] = $claudeAI->testConnection();
}

// Test ProxSMS (only if phone provided)
if (isset($_GET['test']) && $_GET['test'] === 'proxsms' && !empty($_GET['phone'])) {
    $proxSMS = new ProxSMSService();
    $results['proxsms'] = $proxSMS->testConnection($_GET['phone']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Tests - Admin Dashboard</title>
    <link rel="stylesheet" href="../admin/assets/admin-style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .test-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            margin-bottom: 15px;
            color: #1f2937;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            background: #f9fafb;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
        }
        pre {
            background: #1f2937;
            color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            margin-top: 10px;
        }
        input {
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 API Testing Dashboard</h1>
            <p>Test all external API integrations</p>
            <a href="/admin" class="btn" style="margin-top: 10px;">← Back to Dashboard</a>
        </div>

        <!-- Brains ERP API -->
        <div class="test-section">
            <h2>🏢 Brains ERP API</h2>
            <p><strong>Base URL:</strong> <?= BRAINS_API_BASE ?></p>
            <a href="?test=brains" class="btn">Test Brains API</a>

            <?php if (isset($results['brains_connection'])): ?>
            <div class="result <?= $results['brains_connection']['success'] ? 'success' : 'error' ?>">
                <strong>Connection Test:</strong>
                <?php if ($results['brains_connection']['success']): ?>
                    ✅ Success - <?= $results['brains_connection']['item_count'] ?> items found
                <?php else: ?>
                    ❌ Failed - <?= htmlspecialchars($results['brains_connection']['error'] ?? 'Unknown error') ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($results['brains_items'])): ?>
            <div class="result <?= $results['brains_items']['success'] ? 'success' : 'error' ?>">
                <strong>Items API:</strong>
                <?php if ($results['brains_items']['success']): ?>
                    ✅ <?= $results['brains_items']['count'] ?> products fetched
                    <pre><?= json_encode($results['brains_items']['sample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                <?php else: ?>
                    ❌ Error: <?= htmlspecialchars($results['brains_items']['error'] ?? 'Unknown') ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($results['brains_accounts'])): ?>
            <div class="result <?= $results['brains_accounts']['success'] ? 'success' : 'error' ?>">
                <strong>Accounts API:</strong>
                <?php if ($results['brains_accounts']['success']): ?>
                    ✅ <?= $results['brains_accounts']['count'] ?> accounts fetched
                    <pre><?= json_encode($results['brains_accounts']['sample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                <?php else: ?>
                    ❌ Error: <?= htmlspecialchars($results['brains_accounts']['error'] ?? 'Unknown') ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Claude AI API -->
        <div class="test-section">
            <h2>🤖 Anthropic Claude AI</h2>
            <p><strong>Model:</strong> <?= ANTHROPIC_MODEL ?></p>
            <p><strong>API Key:</strong> <?= substr(ANTHROPIC_API_KEY, 0, 15) ?>...</p>
            <a href="?test=claude" class="btn">Test Claude AI</a>

            <?php if (isset($results['claude'])): ?>
            <div class="result <?= $results['claude']['success'] ? 'success' : 'error' ?>">
                <?php if ($results['claude']['success']): ?>
                    ✅ <?= htmlspecialchars($results['claude']['message']) ?>
                <?php else: ?>
                    ❌ Error: <?= htmlspecialchars($results['claude']['error'] ?? 'Unknown') ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ProxSMS WhatsApp API -->
        <div class="test-section">
            <h2>💬 ProxSMS WhatsApp API</h2>
            <p><strong>Account ID:</strong> <?= WHATSAPP_ACCOUNT_ID ?></p>
            <form method="GET" style="margin-top: 15px;">
                <input type="hidden" name="test" value="proxsms">
                <input type="text" name="phone" placeholder="+9613123456" value="<?= htmlspecialchars($_GET['phone'] ?? '') ?>">
                <button type="submit" class="btn">Test ProxSMS (Send Test Message)</button>
            </form>

            <?php if (isset($results['proxsms'])): ?>
            <div class="result <?= $results['proxsms']['success'] ? 'success' : 'error' ?>">
                <?php if ($results['proxsms']['success']): ?>
                    ✅ Test message sent successfully!
                    <pre><?= json_encode($results['proxsms']['response'], JSON_PRETTY_PRINT) ?></pre>
                <?php else: ?>
                    ❌ Error: <?= htmlspecialchars($results['proxsms']['error'] ?? 'Unknown') ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Database Connection -->
        <div class="test-section">
            <h2>🗄️ Database Connection</h2>
            <?php
            try {
                $db = Database::getInstance();
                $result = $db->fetchOne("SELECT COUNT(*) as count FROM customers");
                echo '<div class="result success">';
                echo "✅ Database connected successfully<br>";
                echo "Total customers: " . $result['count'];
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="result error">';
                echo "❌ Database error: " . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>
        </div>
    </div>
</body>
</html>
