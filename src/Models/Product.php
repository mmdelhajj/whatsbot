<?php
/**
 * Product Model
 * Handles product information from Brains ERP
 */

class Product {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Search products by name or code (OPTIMIZED with fulltext)
     * ONLY returns products IN STOCK
     */
    public function search($query, $limit = 10) {
        // Debug logging to track search queries
        logMessage("🔍 Product search called with query: '{$query}' (limit: {$limit})", 'DEBUG', WEBHOOK_LOG_FILE);

        // For short queries or exact codes, use LIKE (faster for exact matches)
        if (strlen($query) < 3 || preg_match('/^\d+$/', $query)) {
            $searchTerm = "%{$query}%";
            return $this->db->fetchAll(
                "SELECT * FROM product_info
                 WHERE (item_code LIKE ? OR item_name LIKE ?)
                   AND stock_quantity > 0
                 ORDER BY
                    CASE
                        WHEN item_code = ? THEN 1
                        WHEN item_name LIKE ? THEN 2
                        ELSE 3
                    END,
                    item_name
                 LIMIT ?",
                [$searchTerm, $searchTerm, $query, $query . '%', $limit]
            );
        }

        // For longer queries, use FULLTEXT search (much faster!)
        // For multi-word searches, require ALL words to be present using BOOLEAN MODE
        $words = explode(' ', trim($query));
        if (count($words) > 1) {
            // Multi-word query: FIRST try exact phrase in item_name only (strict)
            logMessage("🎯 Multi-word search: trying exact phrase match for '{$query}'", 'DEBUG', WEBHOOK_LOG_FILE);
            $phraseResults = $this->db->fetchAll(
                "SELECT * FROM product_info
                 WHERE item_name LIKE ?
                   AND stock_quantity > 0
                 ORDER BY item_name
                 LIMIT ?",
                ["%{$query}%", $limit]
            );

            // If we found good exact phrase matches (3+), return those
            if (count($phraseResults) >= 3) {
                logMessage("✅ Found " . count($phraseResults) . " exact phrase matches - returning strict results", 'DEBUG', WEBHOOK_LOG_FILE);
                return $phraseResults;
            }

            // If we found 1-2 exact matches, try flexible plural matching to find more
            if (count($phraseResults) > 0 && count($phraseResults) < 3) {
                logMessage("⚠️ Only " . count($phraseResults) . " exact matches - trying flexible plural match", 'DEBUG', WEBHOOK_LOG_FILE);
            } else {
                logMessage("⚠️ No exact phrase matches - trying flexible plural match", 'DEBUG', WEBHOOK_LOG_FILE);
            }

            // Build flexible pattern: "math book" → match "math" AND ("book" OR "books")
            // This handles singular/plural variations using LIKE
            $flexibleConditions = [];
            $flexibleParams = [];
            foreach ($words as $word) {
                // Match word OR word+'s' at word boundaries using LIKE
                // Patterns: "word ", " word ", " word", "word" for start/middle/end/standalone
                $escapedWord = str_replace(['%', '_'], ['\%', '\_'], $word);
                $flexibleConditions[] = "(item_name LIKE ? OR item_name LIKE ? OR item_name LIKE ? OR item_name LIKE ? OR item_name LIKE ? OR item_name LIKE ? OR item_name LIKE ? OR item_name LIKE ?)";
                // Match "word" or "words" at various positions
                $flexibleParams[] = "{$escapedWord} %";      // "Math " (start)
                $flexibleParams[] = "{$escapedWord}s %";     // "Maths " (start)
                $flexibleParams[] = "% {$escapedWord} %";    // " book " (middle)
                $flexibleParams[] = "% {$escapedWord}s %";   // " books " (middle)
                $flexibleParams[] = "% {$escapedWord}";      // " book" (end)
                $flexibleParams[] = "% {$escapedWord}s";     // " books" (end)
                $flexibleParams[] = $escapedWord;            // "math" (standalone - exact match)
                $flexibleParams[] = "{$escapedWord}s";       // "maths" (standalone - exact match)
            }
            $flexibleWhere = implode(' AND ', $flexibleConditions);

            // Debug logging
            logMessage("🔧 Flexible search WHERE clause: " . $flexibleWhere, 'DEBUG', WEBHOOK_LOG_FILE);
            logMessage("🔧 Flexible search params (" . count($flexibleParams) . "): " . json_encode($flexibleParams), 'DEBUG', WEBHOOK_LOG_FILE);
            logMessage("🔧 Total params with limit: " . count(array_merge($flexibleParams, [$limit])), 'DEBUG', WEBHOOK_LOG_FILE);

            try {
                $flexibleResults = $this->db->fetchAll(
                    "SELECT * FROM product_info
                     WHERE {$flexibleWhere}
                       AND stock_quantity > 0
                     ORDER BY item_name
                     LIMIT ?",
                    array_merge($flexibleParams, [$limit])
                );
                logMessage("🔧 Flexible search returned " . count($flexibleResults) . " results", 'DEBUG', WEBHOOK_LOG_FILE);
            } catch (Exception $e) {
                logMessage("❌ Flexible search error: " . $e->getMessage(), 'ERROR', WEBHOOK_LOG_FILE);
                $flexibleResults = [];
            }

            // If flexible match found results, return those
            if (!empty($flexibleResults)) {
                logMessage("✅ Found " . count($flexibleResults) . " flexible matches - returning those", 'DEBUG', WEBHOOK_LOG_FILE);
                return $flexibleResults;
            }

            logMessage("⚠️ No flexible matches - falling back to BOOLEAN MODE", 'DEBUG', WEBHOOK_LOG_FILE);

            // No exact phrase matches - fall back to BOOLEAN MODE (searches name + description)
            $booleanQuery = '+' . implode(' +', $words);
            return $this->db->fetchAll(
                "SELECT *, MATCH(item_name, description) AGAINST(? IN BOOLEAN MODE) as relevance
                 FROM product_info
                 WHERE (MATCH(item_name, description) AGAINST(? IN BOOLEAN MODE)
                    OR item_code LIKE ?)
                   AND stock_quantity > 0
                 ORDER BY relevance DESC, item_name
                 LIMIT ?",
                [$booleanQuery, $booleanQuery, "%{$query}%", $limit]
            );
        }

        // Single word query: use NATURAL LANGUAGE MODE
        return $this->db->fetchAll(
            "SELECT *, MATCH(item_name, description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
             FROM product_info
             WHERE (MATCH(item_name, description) AGAINST(? IN NATURAL LANGUAGE MODE)
                OR item_code LIKE ?)
               AND stock_quantity > 0
             ORDER BY relevance DESC, item_name
             LIMIT ?",
            [$query, $query, "%{$query}%", $limit]
        );
    }

    /**
     * Get product by item code
     */
    public function findByCode($itemCode) {
        return $this->db->fetchOne(
            "SELECT * FROM product_info WHERE item_code = ?",
            [$itemCode]
        );
    }

    /**
     * Get product by ID
     */
    public function findById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM product_info WHERE id = ?",
            [$id]
        );
    }

    /**
     * Insert or update product
     */
    public function upsert($productData) {
        $existing = $this->findByCode($productData['item_code']);

        if ($existing) {
            // Update existing
            $this->db->update('product_info', [
                'item_name' => $productData['item_name'],
                'price' => $productData['price'] ?? 0,
                'stock_quantity' => $productData['stock_quantity'] ?? 0,
                'category' => $productData['category'] ?? null,
                'description' => $productData['description'] ?? null
            ], 'item_code = :code', ['code' => $productData['item_code']]);

            return $existing['id'];
        } else {
            // Insert new
            return $this->db->insert('product_info', [
                'item_code' => $productData['item_code'],
                'item_name' => $productData['item_name'],
                'price' => $productData['price'] ?? 0,
                'stock_quantity' => $productData['stock_quantity'] ?? 0,
                'category' => $productData['category'] ?? null,
                'description' => $productData['description'] ?? null
            ]);
        }
    }

    /**
     * Bulk upsert products (for sync)
     * API fields: SKU, Name, Price, StockQuantity, Group, GroupCode, SubGroup, SubGroupCode, IsSchool, ShortDescription
     */
    public function bulkUpsert($products) {
        $added = 0;
        $updated = 0;

        foreach ($products as $product) {
            // Support both old format (ItemCode/ItemName) and new API format (SKU/Name)
            $itemCode = $product['SKU'] ?? $product['ItemCode'] ?? null;
            if (!$itemCode) continue;

            $existing = $this->findByCode($itemCode);

            $productData = [
                'item_code' => $itemCode,
                'item_name' => $product['Name'] ?? $product['ItemName'] ?? '',
                'price' => $product['Price'] ?? 0,
                'stock_quantity' => $product['StockQuantity'] ?? $product['StockQty'] ?? 0,
                'category' => $product['ItemCategory'] ?? $product['Category'] ?? null,
                'description' => $product['ShortDescription'] ?? $product['Description'] ?? null,
                'group_code' => $product['GroupCode'] ?? null,
                'group_name' => $product['Group'] ?? null,
                'subgroup_code' => $product['SubGroupCode'] ?? null,
                'subgroup_name' => $product['SubGroup'] ?? null,
                'is_school' => $product['IsSchool'] ?? 0
            ];

            if ($existing) {
                $this->db->update('product_info',
                    array_diff_key($productData, ['item_code' => '']),
                    'item_code = :code',
                    ['code' => $itemCode]
                );
                $updated++;
            } else {
                $this->db->insert('product_info', $productData);
                $added++;
            }
        }

        return ['added' => $added, 'updated' => $updated];
    }

    /**
     * Get all products with pagination
     */
    public function getAll($page = 1, $perPage = 50) {
        $offset = ($page - 1) * $perPage;

        return $this->db->fetchAll(
            "SELECT * FROM product_info
             ORDER BY item_name
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    /**
     * Get all products in stock (for catalog display)
     */
    public function getAllInStock() {
        return $this->db->fetchAll(
            "SELECT * FROM product_info
             WHERE stock_quantity > 0
             ORDER BY item_name"
        );
    }

    /**
     * Get products by category
     */
    public function getByCategory($category, $limit = 50) {
        return $this->db->fetchAll(
            "SELECT * FROM product_info
             WHERE category = ?
             ORDER BY item_name
             LIMIT ?",
            [$category, $limit]
        );
    }

    /**
     * Get product statistics
     */
    public function getStats() {
        $stats = $this->db->fetchOne(
            "SELECT
                COUNT(*) as total_products,
                COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as in_stock,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
                AVG(price) as avg_price,
                MAX(last_updated) as last_sync
             FROM product_info"
        );

        return $stats;
    }

    /**
     * Get popular categories
     */
    public function getCategories() {
        return $this->db->fetchAll(
            "SELECT
                category,
                COUNT(*) as product_count
             FROM product_info
             WHERE category IS NOT NULL
             GROUP BY category
             ORDER BY product_count DESC"
        );
    }

    /**
     * Format product for display
     */
    public function formatForDisplay($product) {
        if (!$product) return null;

        return [
            'code' => $product['item_code'],
            'name' => $product['item_name'],
            'price' => number_format($product['price'], 0, '.', ',') . ' ' . CURRENCY,
            'stock' => $product['stock_quantity'] > 0 ? 'متوفر' : 'غير متوفر',
            'stock_qty' => $product['stock_quantity'],
            'category' => $product['category'] ?? 'عام'
        ];
    }
}
