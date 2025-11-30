<?php
/**
 * Admin Dashboard - School Books Management
 * Hierarchy: Schools → Grades/Classrooms → Books
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

// Handle update book grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    $bookId = intval($_POST['book_id'] ?? 0);
    $gradeLevel = trim($_POST['grade_level'] ?? '');

    if ($bookId > 0) {
        try {
            $db->query("UPDATE product_info SET grade_level = ? WHERE id = ?", [$gradeLevel ?: null, $bookId]);
            $successMessage = "Grade updated successfully.";
        } catch (Exception $e) {
            $errorMessage = "Error updating grade: " . $e->getMessage();
        }
    }
}

// Get selected school and grade
$selectedSchool = isset($_GET['school']) ? trim($_GET['school']) : '';
$selectedGrade = isset($_GET['grade']) ? trim($_GET['grade']) : '';

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

// Get grades for selected school
$grades = [];
if (!empty($selectedSchool)) {
    $grades = $db->fetchAll("
        SELECT
            COALESCE(grade_level, 'Other') as grade_name,
            COUNT(*) as total_books,
            COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as available_books,
            COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as unavailable_books
        FROM product_info
        WHERE is_school = 1
          AND subgroup_name = ?
        GROUP BY grade_level
        ORDER BY
            CASE
                WHEN grade_level LIKE 'KG%' THEN 1
                WHEN grade_level = 'GS' THEN 2
                WHEN grade_level = 'CP' THEN 3
                WHEN grade_level LIKE 'CE%' THEN 4
                WHEN grade_level LIKE 'CM%' THEN 5
                WHEN grade_level LIKE 'EB%' THEN 6
                WHEN grade_level LIKE 'Level%' THEN 7
                WHEN grade_level LIKE 'Sec%' THEN 8
                WHEN grade_level = 'Terminal' THEN 9
                ELSE 10
            END,
            grade_level
    ", [$selectedSchool]);
}

// Get books for selected school and grade
$books = [];
if (!empty($selectedSchool) && !empty($selectedGrade)) {
    if ($selectedGrade === 'Other') {
        $books = $db->fetchAll("
            SELECT id, item_code, item_name, price, stock_quantity, expected_arrival, grade_level
            FROM product_info
            WHERE is_school = 1
              AND subgroup_name = ?
              AND grade_level IS NULL
            ORDER BY item_name
        ", [$selectedSchool]);
    } else {
        $books = $db->fetchAll("
            SELECT id, item_code, item_name, price, stock_quantity, expected_arrival, grade_level
            FROM product_info
            WHERE is_school = 1
              AND subgroup_name = ?
              AND grade_level = ?
            ORDER BY item_name
        ", [$selectedSchool, $selectedGrade]);
    }
}

// Get list of all grade levels for dropdown
$allGrades = $db->fetchAll("
    SELECT DISTINCT grade_level
    FROM product_info
    WHERE grade_level IS NOT NULL
    ORDER BY grade_level
");

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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 20px; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header a { color: rgba(255,255,255,0.8); text-decoration: none; font-size: 14px; }
        .header a:hover { color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .breadcrumb { background: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6b7280; margin: 0 8px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #6b7280; font-size: 14px; margin-bottom: 5px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #1e40af; }
        .alert { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .layout { display: grid; grid-template-columns: 280px 280px 1fr; gap: 20px; }
        @media (max-width: 1200px) { .layout { grid-template-columns: 250px 1fr; } }
        @media (max-width: 800px) { .layout { grid-template-columns: 1fr; } }
        .panel { background: white; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .panel h2 { padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 16px; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background 0.15s; text-decoration: none; color: inherit; }
        .list-item:hover { background: #f9fafb; }
        .list-item.active { background: #eff6ff; border-left: 3px solid #3b82f6; }
        .list-item .name { font-weight: 500; color: #1f2937; }
        .list-item .count { font-size: 13px; color: #6b7280; }
        .badge { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
        .badge.out { background: #fee2e2; color: #991b1b; }
        .empty-state { padding: 40px 20px; text-align: center; color: #6b7280; }
        .empty-state h3 { margin-bottom: 10px; color: #374151; }
        .books-header { padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .books-header h2 { font-size: 16px; margin: 0; }
        .rename-form { display: flex; gap: 8px; }
        .rename-form input { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; width: 180px; }
        .rename-form button { padding: 6px 14px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .rename-form button:hover { background: #2563eb; }
        .search-box { padding: 15px 20px; border-bottom: 1px solid #e5e7eb; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        .books-table-container { overflow-x: auto; }
        .books-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .books-table th, .books-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f3f4f6; }
        .books-table th { background: #f9fafb; font-weight: 600; color: #374151; }
        .books-table tr:hover { background: #f9fafb; }
        .status-available { color: #059669; font-weight: 500; }
        .status-unavailable { color: #dc2626; font-weight: 500; }
        .arrival-form { display: flex; gap: 5px; align-items: center; }
        .arrival-form input[type="date"] { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
        .arrival-form button { padding: 4px 10px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .arrival-form button:hover { background: #059669; }
        .grade-form { display: flex; gap: 5px; align-items: center; }
        .grade-form select { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
        .grade-form button { padding: 4px 10px; background: #6366f1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .grade-form button:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>School Books Management</h1>
        <a href="/admin/">← Back to Dashboard</a>
    </div>

    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="/admin/schools.php">Schools</a>
            <?php if (!empty($selectedSchool)): ?>
                <span>→</span>
                <a href="/admin/schools.php?school=<?= urlencode($selectedSchool) ?>"><?= htmlspecialchars($selectedSchool) ?></a>
            <?php endif; ?>
            <?php if (!empty($selectedGrade)): ?>
                <span>→</span>
                <strong><?= htmlspecialchars($selectedGrade) ?></strong>
            <?php endif; ?>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
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
            <!-- Schools Panel -->
            <div class="panel">
                <h2>Schools</h2>
                <?php if (empty($schools)): ?>
                    <div class="empty-state">
                        <p>No schools found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($schools as $school): ?>
                        <a href="?school=<?= urlencode($school['school_name']) ?>"
                           class="list-item <?= $selectedSchool === $school['school_name'] ? 'active' : '' ?>">
                            <div>
                                <div class="name"><?= htmlspecialchars($school['school_name']) ?></div>
                                <div class="count"><?= $school['total_books'] ?> books</div>
                            </div>
                            <?php if ($school['unavailable_books'] > 0): ?>
                                <span class="badge out"><?= $school['unavailable_books'] ?> out</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Grades Panel (show when school is selected) -->
            <?php if (!empty($selectedSchool)): ?>
            <div class="panel">
                <h2>Grades / Classrooms</h2>
                <?php if (empty($grades)): ?>
                    <div class="empty-state">
                        <p>No grades found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grades as $grade): ?>
                        <a href="?school=<?= urlencode($selectedSchool) ?>&grade=<?= urlencode($grade['grade_name']) ?>"
                           class="list-item <?= $selectedGrade === $grade['grade_name'] ? 'active' : '' ?>">
                            <div>
                                <div class="name"><?= htmlspecialchars($grade['grade_name']) ?></div>
                                <div class="count"><?= $grade['total_books'] ?> books</div>
                            </div>
                            <?php if ($grade['unavailable_books'] > 0): ?>
                                <span class="badge out"><?= $grade['unavailable_books'] ?> out</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Books Panel -->
            <div class="panel">
                <?php if (empty($selectedSchool)): ?>
                    <div class="empty-state">
                        <h3>Select a School</h3>
                        <p>Click on a school from the list to view its grades</p>
                    </div>
                <?php elseif (empty($selectedGrade)): ?>
                    <div class="books-header">
                        <div>
                            <h2><?= htmlspecialchars($selectedSchool) ?></h2>
                        </div>
                        <form method="POST" class="rename-form">
                            <input type="hidden" name="old_name" value="<?= htmlspecialchars($selectedSchool) ?>">
                            <input type="text" name="new_name" placeholder="New school name" required>
                            <button type="submit" name="rename_school">Rename</button>
                        </form>
                    </div>
                    <div class="empty-state">
                        <h3>Select a Grade</h3>
                        <p>Click on a grade/classroom to view its books</p>
                    </div>
                <?php else: ?>
                    <div class="books-header">
                        <div>
                            <h2><?= htmlspecialchars($selectedSchool) ?> - <?= htmlspecialchars($selectedGrade) ?></h2>
                            <span style="color: #6b7280; font-size: 14px;"><?= count($books) ?> books</span>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="search-box">
                        <input type="text" id="bookSearch" placeholder="Search books..." onkeyup="filterBooks()">
                    </div>

                    <!-- Books Table -->
                    <?php if (empty($books)): ?>
                        <div class="empty-state">
                            <p>No books found for this grade</p>
                        </div>
                    <?php else: ?>
                        <div class="books-table-container">
                            <table class="books-table" id="booksTable">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Grade</th>
                                        <th>Expected Arrival</th>
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
                                                <form method="POST" class="grade-form">
                                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                    <select name="grade_level">
                                                        <option value="">-- None --</option>
                                                        <?php foreach ($allGrades as $g): ?>
                                                            <option value="<?= htmlspecialchars($g['grade_level']) ?>"
                                                                <?= $book['grade_level'] === $g['grade_level'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($g['grade_level']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="update_grade">Set</button>
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($book['stock_quantity'] <= 0): ?>
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
                                                <?php else: ?>
                                                    <span style="color: #9ca3af;">-</span>
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
            if (!table) return;
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }
    </script>
</body>
</html>
