<?php
/**
 * Admin Dashboard - Custom Q&A Management
 * Manage bot's custom questions and answers
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->query("DELETE FROM custom_qa WHERE id = ?", [$_POST['delete_id']]);
    $successMessage = "Q&A deleted successfully! ✅";
}

// Handle toggle active/inactive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $db->query("UPDATE custom_qa SET is_active = NOT is_active WHERE id = ?", [$_POST['toggle_id']]);
    $successMessage = "Q&A status updated! ✅";
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_qa'])) {
    $id = $_POST['qa_id'] ?? null;
    $patternAr = trim($_POST['pattern_ar'] ?? '');
    $patternEn = trim($_POST['pattern_en'] ?? '');
    $patternFr = trim($_POST['pattern_fr'] ?? '');
    $patternLb = trim($_POST['pattern_lb'] ?? '');
    $answerAr = trim($_POST['answer_ar']);
    $answerEn = trim($_POST['answer_en']);
    $answerFr = trim($_POST['answer_fr']);
    $answerLb = trim($_POST['answer_lb'] ?? '');

    // Validate: at least one pattern is required
    if (empty($patternAr) && empty($patternEn) && empty($patternFr) && empty($patternLb)) {
        $errorMessage = "At least one question pattern is required!";
    } elseif (empty($answerAr) && empty($answerEn) && empty($answerFr) && empty($answerLb)) {
        $errorMessage = "At least one answer is required!";
    } else {
        // Combine patterns into one regex pattern
        $patterns = [];
        if (!empty($patternAr)) $patterns[] = $patternAr;
        if (!empty($patternEn)) $patterns[] = $patternEn;
        if (!empty($patternFr)) $patterns[] = $patternFr;
        if (!empty($patternLb)) $patterns[] = $patternLb;
        $questionPattern = '(' . implode('|', $patterns) . ')';

        if ($id) {
            // Update existing
            $db->query(
                "UPDATE custom_qa SET question_pattern = ?, pattern_ar = ?, pattern_en = ?, pattern_fr = ?, pattern_lb = ?, answer_ar = ?, answer_en = ?, answer_fr = ?, answer_lb = ? WHERE id = ?",
                [$questionPattern, $patternAr, $patternEn, $patternFr, $patternLb, $answerAr, $answerEn, $answerFr, $answerLb, $id]
            );
            $successMessage = "Q&A updated successfully! ✅";
        } else {
            // Insert new
            $db->query(
                "INSERT INTO custom_qa (question_pattern, pattern_ar, pattern_en, pattern_fr, pattern_lb, answer_ar, answer_en, answer_fr, answer_lb) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$questionPattern, $patternAr, $patternEn, $patternFr, $patternLb, $answerAr, $answerEn, $answerFr, $answerLb]
            );
            $successMessage = "Q&A added successfully! ✅";
        }
    }
}

// Get all Q&A entries
$qaEntries = $db->fetchAll("SELECT * FROM custom_qa ORDER BY created_at DESC");

// Get single entry for editing
$editEntry = null;
if (isset($_GET['edit'])) {
    $editEntry = $db->fetchOne("SELECT * FROM custom_qa WHERE id = ?", [$_GET['edit']]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Q&A - <?= STORE_NAME ?> Admin</title>
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
        .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .section h2 { margin-top: 0; color: #1f2937; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #374151; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .form-group textarea { min-height: 80px; font-family: inherit; }
        .form-group small { color: #6b7280; font-size: 0.85em; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.85em; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 500; }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .success { background: #d1fae5; color: #059669; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .actions { display: flex; gap: 5px; }
        .pattern-preview { font-family: monospace; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
        .help-text { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .help-text h4 { margin-top: 0; color: #1e40af; }
        .help-text code { background: #dbeafe; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
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
            <a href="/admin/custom-qa.php" class="active">Custom Q&A</a>
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

        <?php if (isset($errorMessage)): ?>
            <div class="error">❌ <?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="section">
            <h2><?= $editEntry ? 'Edit Q&A' : 'Add New Q&A' ?></h2>

            <div class="help-text">
                <h4>💡 How to use Custom Q&A:</h4>
                <p><strong>Question Keywords:</strong> Enter keywords or phrases that customers might ask in each language.</p>
                <p><strong>Examples:</strong></p>
                <ul>
                    <li><strong>Arabic:</strong> <code>بطاقة هدية|كرت هدية</code></li>
                    <li><strong>English:</strong> <code>gift card|gift voucher</code></li>
                    <li><strong>French:</strong> <code>carte cadeau|bon cadeau</code></li>
                    <li><strong>Lebanese (Franco-Arabic):</strong> <code>fi kteb|3andak kteb|carte cadeau</code></li>
                </ul>
                <p><strong>Answers:</strong> Provide answers in Arabic, English, or French. The bot will automatically show the answer in the customer's language.</p>
                <p><small>Note: At least one question pattern and one answer are required. Use | to separate alternatives.</small></p>
            </div>

            <form method="POST">
                <?php if ($editEntry): ?>
                    <input type="hidden" name="qa_id" value="<?= $editEntry['id'] ?>">
                <?php endif; ?>

                <h3 style="margin-top: 0; color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">📝 Question Keywords</h3>

                <div class="form-group">
                    <label>🇸🇦 Arabic Keywords</label>
                    <input type="text" name="pattern_ar"
                           value="<?= htmlspecialchars($editEntry['pattern_ar'] ?? '') ?>"
                           placeholder="بطاقة هدية|كرت هدية">
                    <small>Keywords customers might use in Arabic (use | to separate alternatives)</small>
                </div>

                <div class="form-group">
                    <label>🇬🇧 English Keywords</label>
                    <input type="text" name="pattern_en"
                           value="<?= htmlspecialchars($editEntry['pattern_en'] ?? '') ?>"
                           placeholder="gift card|gift voucher">
                    <small>Keywords customers might use in English (use | to separate alternatives)</small>
                </div>

                <div class="form-group">
                    <label>🇫🇷 French Keywords</label>
                    <input type="text" name="pattern_fr"
                           value="<?= htmlspecialchars($editEntry['pattern_fr'] ?? '') ?>"
                           placeholder="carte cadeau|bon cadeau">
                    <small>Keywords customers might use in French (use | to separate alternatives)</small>
                </div>

                <div class="form-group">
                    <label>🇱🇧 Lebanese Keywords (Franco-Arabic)</label>
                    <input type="text" name="pattern_lb"
                           value="<?= htmlspecialchars($editEntry['pattern_lb'] ?? '') ?>"
                           placeholder="fi kteb|3andak kteb|shu bedak">
                    <small>Keywords customers might use in Lebanese/Arabizi (use | to separate alternatives)</small>
                </div>

                <h3 style="margin-top: 30px; color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">💬 Answers</h3>

                <div class="form-group">
                    <label>🇸🇦 Answer (Arabic)</label>
                    <textarea name="answer_ar" placeholder="الإجابة بالعربية..."><?= htmlspecialchars($editEntry['answer_ar'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>🇬🇧 Answer (English)</label>
                    <textarea name="answer_en" placeholder="Answer in English..."><?= htmlspecialchars($editEntry['answer_en'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>🇫🇷 Answer (French)</label>
                    <textarea name="answer_fr" placeholder="Réponse en français..."><?= htmlspecialchars($editEntry['answer_fr'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>🇱🇧 Answer (Lebanese) - Optional</label>
                    <textarea name="answer_lb" placeholder="Jaweb bel lebnene... (example: khalas habibi, rouh 3al settings...)"><?= htmlspecialchars($editEntry['answer_lb'] ?? '') ?></textarea>
                    <small>Casual/friendly response in Lebanese/Franco-Arabic (optional)</small>
                </div>

                <button type="submit" name="save_qa" class="btn btn-primary">
                    <?= $editEntry ? '💾 Update Q&A' : '➕ Add Q&A' ?>
                </button>
                <?php if ($editEntry): ?>
                    <a href="/admin/custom-qa.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="section">
            <h2>📋 Custom Q&A List (<?= count($qaEntries) ?>)</h2>

            <?php if (empty($qaEntries)): ?>
                <p>No custom Q&A entries yet. Add your first one above! 👆</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Question Keywords</th>
                            <th>Answer Languages</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($qaEntries as $qa): ?>
                        <tr>
                            <td style="font-size: 0.9em;">
                                <?php if (!empty($qa['pattern_ar'])): ?>
                                    <div><strong>🇸🇦 AR:</strong> <span class="pattern-preview"><?= htmlspecialchars($qa['pattern_ar']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($qa['pattern_en'])): ?>
                                    <div><strong>🇬🇧 EN:</strong> <span class="pattern-preview"><?= htmlspecialchars($qa['pattern_en']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($qa['pattern_fr'])): ?>
                                    <div><strong>🇫🇷 FR:</strong> <span class="pattern-preview"><?= htmlspecialchars($qa['pattern_fr']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($qa['pattern_lb'])): ?>
                                    <div><strong>🇱🇧 LB:</strong> <span class="pattern-preview"><?= htmlspecialchars($qa['pattern_lb']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($qa['answer_ar']) ? '🇸🇦 AR ' : '' ?>
                                <?= !empty($qa['answer_en']) ? '🇬🇧 EN ' : '' ?>
                                <?= !empty($qa['answer_fr']) ? '🇫🇷 FR ' : '' ?>
                                <?= !empty($qa['answer_lb']) ? '🇱🇧 LB' : '' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $qa['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $qa['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= formatDateTime($qa['created_at'], 'M d, Y') ?></td>
                            <td class="actions">
                                <a href="?edit=<?= $qa['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="toggle_id" value="<?= $qa['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <?= $qa['is_active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;"
                                      onsubmit="return confirm('Are you sure you want to delete this Q&A?');">
                                    <input type="hidden" name="delete_id" value="<?= $qa['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
