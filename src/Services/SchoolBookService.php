<?php
/**
 * School Book Service
 * Handles school books catalog and ordering functionality
 * 3-Level Structure: Schools -> Grades/Classrooms -> Books
 * Reads from product_info table (synced from Brains API)
 * School books: is_school=1, school name in subgroup_name
 * Grades are extracted from book names (EB1, KG1, PS, GS, CP, etc.)
 */

class SchoolBookService {
    private $db;

    // Grade pattern for extraction from book names
    private $gradePatterns = [
        // Lebanese/French system
        'PS' => '/\b(PS[123]?|Petite Section)\b/i',
        'MS' => '/\b(MS|Moyenne Section)\b/i',
        'GS' => '/\b(GS|Grande Section)\b/i',
        'KG1' => '/\b(KG1|KG 1)\b/i',
        'KG2' => '/\b(KG2|KG 2)\b/i',
        'KG3' => '/\b(KG3|KG 3)\b/i',
        'EB1' => '/\b(EB1|EB 1|Grade 1|Grade1)\b/i',
        'EB2' => '/\b(EB2|EB 2|Grade 2|Grade2)\b/i',
        'EB3' => '/\b(EB3|EB 3|Grade 3|Grade3)\b/i',
        'EB4' => '/\b(EB4|EB 4|Grade 4|Grade4)\b/i',
        'EB5' => '/\b(EB5|EB 5|Grade 5|Grade5)\b/i',
        'EB6' => '/\b(EB6|EB 6|Grade 6|Grade6)\b/i',
        'EB7' => '/\b(EB7|EB 7|Grade 7|Grade7)\b/i',
        'EB8' => '/\b(EB8|EB 8|Grade 8|Grade8)\b/i',
        'EB9' => '/\b(EB9|EB 9|Grade 9|Grade9)\b/i',
        // French system
        'CP' => '/\bCP\b/i',
        'CE1' => '/\bCE1\b/i',
        'CE2' => '/\bCE2\b/i',
        'CM1' => '/\bCM1\b/i',
        'CM2' => '/\bCM2\b/i',
        '6eme' => '/\b(6eme|6ème|6e)\b/i',
        '5eme' => '/\b(5eme|5ème|5e)\b/i',
        '4eme' => '/\b(4eme|4ème|4e)\b/i',
        '3eme' => '/\b(3eme|3ème|3e)\b/i',
        '2nde' => '/\b(2nde|Seconde|2de)\b/i',
        '1ere' => '/\b(1ere|1ère|Premiere|Première)\b/i',
        'Terminale' => '/\b(Terminale|TermS|TermL|TermES|Term)\b/i',
        // Technical
        'BT1' => '/\bBT1\b/i',
        'BT2' => '/\bBT2\b/i',
        'BT3' => '/\bBT3\b/i',
        // Secondary
        'SE' => '/\bSE[0-9]?\b/i',
        'LS' => '/\bLS\b/i',
        // Level-based
        'Level 1' => '/\b(Level 1|Level1)\b/i',
        'Level 2' => '/\b(Level 2|Level2)\b/i',
        'Level 3' => '/\b(Level 3|Level3)\b/i',
        'Level 4' => '/\b(Level 4|Level4)\b/i',
        'Level 5' => '/\b(Level 5|Level5)\b/i',
    ];

    // Grade order for sorting (lower = shown first)
    private $gradeOrder = [
        'PS' => 1, 'MS' => 2, 'GS' => 3,
        'KG1' => 4, 'KG2' => 5, 'KG3' => 6,
        'EB1' => 10, 'EB2' => 11, 'EB3' => 12, 'EB4' => 13, 'EB5' => 14,
        'EB6' => 15, 'EB7' => 16, 'EB8' => 17, 'EB9' => 18,
        'CP' => 20, 'CE1' => 21, 'CE2' => 22, 'CM1' => 23, 'CM2' => 24,
        '6eme' => 30, '5eme' => 31, '4eme' => 32, '3eme' => 33,
        '2nde' => 40, '1ere' => 41, 'Terminale' => 42,
        'BT1' => 50, 'BT2' => 51, 'BT3' => 52,
        'SE' => 60, 'LS' => 61,
        'Level 1' => 70, 'Level 2' => 71, 'Level 3' => 72, 'Level 4' => 73, 'Level 5' => 74,
        'General' => 100
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Extract grade from book name
     */
    public function extractGradeFromName($bookName) {
        foreach ($this->gradePatterns as $grade => $pattern) {
            if (preg_match($pattern, $bookName)) {
                return $grade;
            }
        }
        return 'General'; // Books without clear grade
    }

    /**
     * Get all schools with book counts
     */
    public function getAllSchools() {
        return $this->db->fetchAll(
            "SELECT subgroup_name as school_name,
                    COUNT(*) as book_count
             FROM product_info
             WHERE is_school = 1
               AND subgroup_name IS NOT NULL
               AND subgroup_name != ''
               AND subgroup_name != 'N / A'
               AND subgroup_name != 'Book Covers'
               AND stock_quantity > 0
             GROUP BY subgroup_name
             ORDER BY subgroup_name ASC"
        );
    }

    /**
     * Get grades/classrooms for a specific school
     * Extracts grades from book names
     */
    public function getGradesBySchool($schoolName) {
        // Get all books for this school
        $books = $this->db->fetchAll(
            "SELECT id, item_name, price
             FROM product_info
             WHERE is_school = 1
               AND subgroup_name = ?
               AND stock_quantity > 0",
            [$schoolName]
        );

        // Group by extracted grade
        $grades = [];
        foreach ($books as $book) {
            $grade = $this->extractGradeFromName($book['item_name']);
            if (!isset($grades[$grade])) {
                $grades[$grade] = [
                    'grade_level' => $grade,
                    'book_count' => 0,
                    'total_price' => 0
                ];
            }
            $grades[$grade]['book_count']++;
            $grades[$grade]['total_price'] += $book['price'];
        }

        // Sort by grade order
        usort($grades, function($a, $b) {
            $orderA = $this->gradeOrder[$a['grade_level']] ?? 99;
            $orderB = $this->gradeOrder[$b['grade_level']] ?? 99;
            return $orderA - $orderB;
        });

        return array_values($grades);
    }

    /**
     * Get books for a specific school and grade
     */
    public function getBooksBySchoolAndGrade($schoolName, $gradeLevel) {
        // Get all books for this school
        $allBooks = $this->db->fetchAll(
            "SELECT id,
                    id as product_id,
                    item_name as book_title,
                    price as book_price,
                    item_code as isbn,
                    stock_quantity
             FROM product_info
             WHERE is_school = 1
               AND subgroup_name = ?
               AND stock_quantity > 0
             ORDER BY item_name ASC",
            [$schoolName]
        );

        // Filter by grade
        $filteredBooks = [];
        foreach ($allBooks as $book) {
            $bookGrade = $this->extractGradeFromName($book['book_title']);
            if ($bookGrade === $gradeLevel) {
                $filteredBooks[] = $book;
            }
        }

        return $filteredBooks;
    }

    /**
     * Get total price for a school+grade combination
     */
    public function getGradeTotalPrice($schoolName, $gradeLevel) {
        $books = $this->getBooksBySchoolAndGrade($schoolName, $gradeLevel);

        $totalPrice = 0;
        foreach ($books as $book) {
            $totalPrice += $book['book_price'];
        }

        return [
            'total_price' => $totalPrice,
            'book_count' => count($books)
        ];
    }

    /**
     * Get all books for a school (without grade filter)
     */
    public function getBooksBySchool($schoolName) {
        return $this->db->fetchAll(
            "SELECT id,
                    id as product_id,
                    item_name as book_title,
                    price as book_price,
                    item_code as isbn,
                    stock_quantity
             FROM product_info
             WHERE is_school = 1
               AND subgroup_name = ?
               AND stock_quantity > 0
             ORDER BY item_name ASC",
            [$schoolName]
        );
    }

    /**
     * Get total price for all books of a school
     */
    public function getSchoolTotalPrice($schoolName) {
        $result = $this->db->fetchOne(
            "SELECT SUM(price) as total_price, COUNT(*) as book_count
             FROM product_info
             WHERE is_school = 1
               AND subgroup_name = ?
               AND stock_quantity > 0",
            [$schoolName]
        );
        return $result;
    }

    /**
     * Get a single book by ID (product_info id)
     */
    public function getBookById($bookId) {
        return $this->db->fetchOne(
            "SELECT id,
                    id as product_id,
                    item_name as book_title,
                    price as book_price,
                    item_code,
                    subgroup_name as school_name,
                    stock_quantity
             FROM product_info
             WHERE id = ? AND is_school = 1",
            [$bookId]
        );
    }

    /**
     * Find school by partial name match
     */
    public function findSchoolByName($searchTerm) {
        $searchTerm = '%' . $searchTerm . '%';
        return $this->db->fetchOne(
            "SELECT subgroup_name as school_name, COUNT(*) as book_count
             FROM product_info
             WHERE is_school = 1
               AND subgroup_name LIKE ?
               AND subgroup_name != 'N / A'
               AND subgroup_name != 'Book Covers'
               AND stock_quantity > 0
             GROUP BY subgroup_name
             LIMIT 1",
            [$searchTerm]
        );
    }

    /**
     * Create a book order
     */
    public function createBookOrder($customerId, $schoolName, $gradeLevel, $items, $notes = null) {
        // Calculate total
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['unit_price'] * $item['quantity'];
        }

        // Create order
        $orderId = $this->db->insert('book_orders', [
            'customer_id' => $customerId,
            'school_name' => $schoolName,
            'grade_level' => $gradeLevel ?: 'All',
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'notes' => $notes
        ]);

        // Add order items (using product_id directly from product_info)
        foreach ($items as $item) {
            $this->db->insert('book_order_items', [
                'book_order_id' => $orderId,
                'school_book_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price']
            ]);
        }

        return $this->getBookOrderById($orderId);
    }

    /**
     * Get book order by ID with items
     */
    public function getBookOrderById($orderId) {
        $order = $this->db->fetchOne(
            "SELECT bo.*, c.name as customer_name, c.phone as customer_phone
             FROM book_orders bo
             JOIN customers c ON bo.customer_id = c.id
             WHERE bo.id = ?",
            [$orderId]
        );

        if ($order) {
            $order['items'] = $this->db->fetchAll(
                "SELECT boi.*, pi.item_name as book_title, pi.item_code as isbn
                 FROM book_order_items boi
                 JOIN product_info pi ON boi.school_book_id = pi.id
                 WHERE boi.book_order_id = ?",
                [$orderId]
            );
        }

        return $order;
    }

    /**
     * Get customer's book orders
     */
    public function getCustomerBookOrders($customerId, $limit = 10) {
        $orders = $this->db->fetchAll(
            "SELECT * FROM book_orders
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$customerId, $limit]
        );

        foreach ($orders as &$order) {
            $order['items'] = $this->db->fetchAll(
                "SELECT boi.*, pi.item_name as book_title
                 FROM book_order_items boi
                 JOIN product_info pi ON boi.school_book_id = pi.id
                 WHERE boi.book_order_id = ?",
                [$order['id']]
            );
        }

        return $orders;
    }

    /**
     * Check if message triggers school books flow
     */
    public function isSchoolBookTrigger($message) {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // School book trigger keywords
        $triggers = [
            // English - all variations
            'school books', 'school book', 'schoolbooks', 'schoolbook',
            'school list', 'school lists', 'schoollist', 'schoollists',
            'book list', 'book lists', 'booklist', 'booklists',
            'books list', 'books lists', 'bookslist',
            'school supplies', 'school order', 'school orders',
            // French
            'livres scolaires', 'livre scolaire', 'livres scolaire',
            'liste scolaire', 'listes scolaires', 'liste des livres',
            'liste ecole', 'liste école', 'fournitures scolaires',
            // Arabic
            'كتب مدرسية', 'كتب المدرسة', 'قائمة الكتب', 'لائحة الكتب',
            'الكتب المدرسية', 'لائحة المدرسة', 'كتب المدارس',
            // Simple triggers
            'liste', 'school'
        ];

        // School name triggers (partial match) - matches actual school names from Brains
        $schoolNames = [
            'sscc', 'saints coeurs', 'coeurs',
            'antonine',
            'besancon', 'besançon',
            'lycee', 'lycée', 'nahr ibrahim', 'libano',
            'saint francois', 'saint-francois', 'st francois',
            'ccj', 'francais', 'anglais', 'technique',
            'divers',
            'franco', 'kfarhbab', 'sisters', 'central',
            'sja', 'collège', 'college',
            'ecole', 'école', 'madrasa', 'مدرسة'
        ];

        // Check exact triggers
        foreach ($triggers as $trigger) {
            if (strpos($messageLower, $trigger) !== false) {
                return true;
            }
        }

        // Check school names
        foreach ($schoolNames as $school) {
            if (strpos($messageLower, $school) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract school name from message if present
     * Returns null for generic trigger phrases like "school books"
     */
    public function extractSchoolFromMessage($message) {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Don't extract school from generic trigger phrases
        $genericTriggers = [
            'school books', 'school book', 'schoolbooks', 'schoolbook',
            'school list', 'school lists', 'schoollist',
            'book list', 'book lists', 'booklist',
            'liste', 'livres scolaires', 'liste scolaire',
            'كتب مدرسية', 'قائمة الكتب'
        ];

        foreach ($genericTriggers as $trigger) {
            if ($messageLower === $trigger || $messageLower === $trigger . 's') {
                return null; // Generic trigger, show school list
            }
        }

        // Get all school names from database
        $schools = $this->getAllSchools();

        foreach ($schools as $school) {
            $schoolLower = mb_strtolower($school['school_name'], 'UTF-8');

            // Skip "School Divers" for generic "school" keyword
            if ($schoolLower === 'school divers' && $messageLower === 'school') {
                continue;
            }

            // Check if full school name appears in message
            if (strpos($messageLower, $schoolLower) !== false) {
                return $school['school_name'];
            }

            // Check partial match (first word of school name, but not generic words)
            $firstWord = explode(' ', $schoolLower)[0];
            $genericWords = ['school', 'ecole', 'école', 'lycee', 'lycée', 'college', 'collège'];

            if (strlen($firstWord) >= 4 && !in_array($firstWord, $genericWords)) {
                if (strpos($messageLower, $firstWord) !== false) {
                    return $school['school_name'];
                }
            }
        }

        return null;
    }

    /**
     * Search products from product_info (for admin to add books)
     */
    public function searchProducts($query, $limit = 30) {
        return $this->db->fetchAll(
            "SELECT id, item_name, item_code, price
             FROM product_info
             WHERE item_name LIKE ?
             ORDER BY item_name
             LIMIT ?",
            ['%' . $query . '%', $limit]
        );
    }

    /**
     * Get statistics for admin dashboard
     */
    public function getStatistics() {
        return [
            'total_books' => $this->db->fetchOne("SELECT COUNT(*) as count FROM product_info WHERE is_school = 1 AND stock_quantity > 0")['count'] ?? 0,
            'total_schools' => $this->db->fetchOne("SELECT COUNT(DISTINCT subgroup_name) as count FROM product_info WHERE is_school = 1 AND subgroup_name IS NOT NULL AND subgroup_name != '' AND subgroup_name != 'N / A'")['count'] ?? 0,
            'total_orders' => $this->db->fetchOne("SELECT COUNT(*) as count FROM book_orders")['count'] ?? 0,
            'pending_orders' => $this->db->fetchOne("SELECT COUNT(*) as count FROM book_orders WHERE status = 'pending'")['count'] ?? 0
        ];
    }
}
