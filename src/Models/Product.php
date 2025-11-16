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
     */
    public function bulkUpsert($products) {
        $added = 0;
        $updated = 0;

        foreach ($products as $product) {
            $existing = $this->findByCode($product['ItemCode']);

            $productData = [
                'item_code' => $product['ItemCode'],
                'item_name' => $product['ItemName'] ?? '',
                'price' => $product['Price'] ?? 0,
                'stock_quantity' => $product['StockQty'] ?? 0,
                'category' => $product['Category'] ?? null,
                'description' => $product['Description'] ?? null
            ];

            if ($existing) {
                $this->db->update('product_info',
                    array_diff_key($productData, ['item_code' => '']),
                    'item_code = :code',
                    ['code' => $product['ItemCode']]
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
