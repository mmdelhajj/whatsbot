<?php
/**
 * Admin Dashboard - School Books Management
 * Uses product_info table (is_school=1, school name in subgroup_name)
 */

require_once dirname(__DIR__) . '/config/config.php';
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin');
    exit;
}

$db = Database::getInstance();

$successMessage = '';
$errorMessage = '';

// Handle rename school
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_school'])) {
    $oldName = trim($_POST['old_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');

    if (!empty($oldName) && !empty($newName) && $oldName !== $newName) {
        try {
            $db->query("UPDATE product_info SET subgroup_name = ? WHERE subgroup_name = ? AND is_school = 1", [$newName, $oldName]);
            $successMessage = "School renamed from \"{$oldName}\" to \"{$newName}\" successfully.";
            // Update selected school to new name
            header("Location: /admin/schools.php?school=" . urlencode($newName));
            exit;
        } catch (Exception $e) {
            $errorMessage = "Error renaming school: " . $e->getMessage();
        }
    }
}

// Handle update book arrival date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_arrival'])) {
    $bookId = intval($_POST['book_id'] ?? 0);
    $arrivalDate = $_POST['arrival_date'] ?? '';

    if ($bookId > 0) {
        try {
            if (empty($arrivalDate)) {
                $db->query("UPDATE product_info SET expected_arrival = NULL WHERE id = ?", [$bookId]);
            } else {
                $db->query("UPDATE product_info SET expected_arrival = ? WHERE id = ?", [$arrivalDate, $bookId]);
            }
            $successMessage = "Arrival date updated successfully.";
        } catch (Exception $e) {
            $errorMessage = "Error updating arrival date: " . $e->getMessage();
        }
    }
}

// Get selected school
$selectedSchool = isset($_GET['school']) ? trim($_GET['school']) : '';

// Get all schools with book counts
$schools = $db->fetchAll("
    SELECT
        subgroup_name as school_name,
        COUNT(*) as total_books,
        COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as available_books,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as unavailable_books
    FROM product_info
    WHERE is_school = 1
      AND subgroup_name IS NOT NULL
      AND subgroup_name != ''
      AND subgroup_name != 'N / A'
    GROUP BY subgroup_name
    ORDER BY subgroup_name
");

// Get books for selected school
$books = [];
if (!empty($selectedSchool)) {
    $books = $db->fetchAll("
        SELECT id, item_code, item_name, price, stock_quantity, expected_arrival
        FROM product_info
        WHERE is_school = 1
          AND subgroup_name = ?
        ORDER BY item_name
    ", [$selectedSchool]);
}

// Stats
$totalSchools = count($schools);
$totalBooks = array_sum(array_column($schools, 'total_books'));
$unavailableBooks = array_sum(array_column($schools, 'unavailable_books'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schools - <?= STORE_NAME ?> Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: white; padding: 20px; border-bottom: 1px solid #e5e7eb; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; color: #1f2937; }
        .logout-btn { background: #ef4444; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        .nav { background: white; border-bottom: 1px solid #e5e7eb; padding: 0 20px; }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 20px; overflow-x: auto; }
        .nav a { display: inline-block; padding: 15px 20px; text-decoration: none; color: #374151; border-bottom: 3px solid transparent; transition: all 0.3s; white-space: nowrap; }
        .nav a:hover, .nav a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #6b7280; font-size: 0.9em; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .value { font-size: 2em; font-weight: bold; color: #1f2937; }

        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .layout { display: grid; grid-template-columns: 300px 1fr; gap: 30px; }
        @media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }

        .school-list { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; max-height: 600px; overflow-y: auto; }
        .school-list h2 { margin: 0; padding: 20px; background: #667eea; color: white; font-size: 1.1em; position: sticky; top: 0; }
        .school-item { padding: 15px 20px; border-bottom: 1px solid #e5e7eb; cursor: pointer; transition: background 0.2s; display: flex; justify-content: space-between; align-items: center; text-decoration: none; }
        .school-item:hover { background: #f9fafb; }
        .school-item.active { background: #eef2ff; border-left: 4px solid #667eea; }
        .school-item .name { font-weight: 500; color: #1f2937; font-size: 0.9em; }
        .school-item .count { font-size: 0.8em; color: #6b7280; }
        .school-item .badge { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; font-size: 0.7em; margin-left: 5px; }

        .books-panel { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .books-header { padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .books-header h2 { margin: 0; color: #1f2937; }

        .rename-form { display: flex; gap: 10px; align-items: center; }
        .rename-form input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9em; width: 200px; }
        .rename-form button { background: #667eea; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; }
        .rename-form button:hover { background: #5a67d8; }

        .search-box { padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95em; }

        .books-table-container { max-height: 500px; overflow-y: auto; }
        .books-table { width: 100%; border-collapse: collapse; }
        .books-table th { text-align: left; padding: 12px 15px; background: #f9fafb; color: #6b7280; font-weight: 600; font-size: 0.8em; text-transform: uppercase; position: sticky; top: 0; }
        .books-table td { padding: 10px 15px; border-bottom: 1px solid #e5e7eb; font-size: 0.9em; }
        .books-table tr:hover { background: #f9fafb; }

        .status-available { color: #059669; font-weight: 500; }
        .status-unavailable { color: #dc2626; font-weight: 500; }

        .arrival-form { display: flex; gap: 5px; align-items: center; }
        .arrival-form input[type="date"] { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.8em; }
        .arrival-form button { background: #10b981; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.75em; }
        .arrival-form button:hover { background: #059669; }

        .empty-state { padding: 60px 20px; text-align: center; color: #6b7280; }
        .empty-state h3 { margin-bottom: 10px; color: #374151; }

        .book-count { background: #e5e7eb; padding: 3px 10px; border-radius: 15px; font-size: 0.85em; color: #374151; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><?= STORE_NAME ?> Admin</h1>
            <a href="/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav">
        <div class="nav-content">
            <a href="/admin/dashboard.php">Dashboard</a>
            <a href="/admin/orders.php">Orders</a>
            <a href="/admin/products.php">Products</a>
            <a href="/admin/schools.php" class="active">Schools</a>
            <a href="/admin/customers.php">Customers</a>
            <a href="/admin/messages.php">Messages</a>
            <a href="/admin/custom-qa.php">Custom Q&A</a>
            <a href="/admin/settings.php">Settings</a>
        </div>
    </div>

    <div class="container">
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Schools</h3>
                <div class="value"><?= $totalSchools ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Books</h3>
                <div class="value"><?= number_format($totalBooks) ?></div>
            </div>
            <div class="stat-card">
                <h3>Out of Stock</h3>
                <div class="value" style="color: #dc2626;"><?= number_format($unavailableBooks) ?></div>
            </div>
        </div>

        <div class="layout">
            <!-- School List -->
            <div class="school-list">
                <h2>Schools</h2>
                <?php if (empty($schools)): ?>
                    <div class="empty-state">
                        <p>No schools found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($schools as $school): ?>
                        <a href="?school=<?= urlencode($school['school_name']) ?>"
                           class="school-item <?= $selectedSchool === $school['school_name'] ? 'active' : '' ?>">
                            <div>
                                <div class="name"><?= htmlspecialchars($school['school_name']) ?></div>
                                <div class="count"><?= $school['total_books'] ?> books</div>
                            </div>
                            <div>
                                <?php if ($school['unavailable_books'] > 0): ?>
                                    <span class="badge"><?= $school['unavailable_books'] ?> out</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Books Panel -->
            <div class="books-panel">
                <?php if (empty($selectedSchool)): ?>
                    <div class="empty-state">
                        <h3>Select a School</h3>
                        <p>Click on a school from the list to view and manage its books</p>
                    </div>
                <?php else: ?>
                    <div class="books-header">
                        <div>
                            <h2><?= htmlspecialchars($selectedSchool) ?></h2>
                            <span class="book-count"><?= count($books) ?> books</span>
                        </div>
                        <form method="POST" class="rename-form">
                            <input type="hidden" name="old_name" value="<?= htmlspecialchars($selectedSchool) ?>">
                            <input type="text" name="new_name" placeholder="New school name" required>
                            <button type="submit" name="rename_school">Rename School</button>
                        </form>
                    </div>

                    <!-- Search -->
                    <div class="search-box">
                        <input type="text" id="bookSearch" placeholder="Search books..." onkeyup="filterBooks()">
                    </div>

                    <!-- Books Table -->
                    <?php if (empty($books)): ?>
                        <div class="empty-state">
                            <p>No books found for this school</p>
                        </div>
                    <?php else: ?>
                        <div class="books-table-container">
                            <table class="books-table" id="booksTable">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Expected Arrival (if out of stock)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $book): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($book['item_name']) ?></td>
                                            <td><?= number_format($book['price'], 0) ?> <?= CURRENCY ?></td>
                                            <td>
                                                <?php if ($book['stock_quantity'] > 0): ?>
                                                    <span class="status-available"><?= $book['stock_quantity'] ?> in stock</span>
                                                <?php else: ?>
                                                    <span class="status-unavailable">Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="arrival-form">
                                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                    <input type="date" name="arrival_date"
                                                           value="<?= $book['expected_arrival'] ?? '' ?>"
                                                           min="<?= date('Y-m-d') ?>">
                                                    <button type="submit" name="update_arrival">Set</button>
                                                </form>
                                                <?php if (!empty($book['expected_arrival'])): ?>
                                                    <small style="color: #059669; display: block; margin-top: 3px;">
                                                        Arriving: <?= date('M d, Y', strtotime($book['expected_arrival'])) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterBooks() {
            const input = document.getElementById('bookSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('booksTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                rows[i].style.display = found ? '' : 'none';
            }
        }
    </script>
</body>
</html>
