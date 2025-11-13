<?php
/**
 * Order Model
 * Handles order creation and management
 */

class Order {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new order
     */
    public function create($customerId, $items, $notes = null) {
        // Generate unique order number
        $orderNumber = $this->generateOrderNumber();

        // Calculate total
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += ($item['unit_price'] * $item['quantity']);
        }

        // Insert order
        $orderId = $this->db->insert('orders', [
            'customer_id' => $customerId,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'total_amount' => $totalAmount,
            'notes' => $notes
        ]);

        // Insert order items
        foreach ($items as $item) {
            $this->db->insert('order_items', [
                'order_id' => $orderId,
                'product_sku' => $item['product_sku'],
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['unit_price'] * $item['quantity']
            ]);
        }

        return $this->findById($orderId);
    }

    /**
     * Get order by ID
     */
    public function findById($id) {
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE id = ?",
            [$id]
        );

        if ($order) {
            $order['items'] = $this->getOrderItems($id);
        }

        return $order;
    }

    /**
     * Get order by order number
     */
    public function findByNumber($orderNumber) {
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE order_number = ?",
            [$orderNumber]
        );

        if ($order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $order;
    }

    /**
     * Get order items
     */
    public function getOrderItems($orderId) {
        return $this->db->fetchAll(
            "SELECT * FROM order_items WHERE order_id = ?",
            [$orderId]
        );
    }

    /**
     * Update order status
     */
    public function updateStatus($orderId, $status) {
        $this->db->update('orders',
            ['status' => $status],
            'id = :id',
            ['id' => $orderId]
        );
    }

    /**
     * Link order to Brains invoice
     */
    public function linkBrainsInvoice($orderId, $invoiceId) {
        $this->db->update('orders',
            ['brains_invoice_id' => $invoiceId, 'status' => 'confirmed'],
            'id = :id',
            ['id' => $orderId]
        );
    }

    /**
     * Get customer orders
     */
    public function getByCustomer($customerId, $limit = 20) {
        $orders = $this->db->fetchAll(
            "SELECT * FROM orders
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$customerId, $limit]
        );

        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }

        return $orders;
    }

    /**
     * Get all orders with customer info
     */
    public function getAllWithCustomers($limit = 100) {
        return $this->db->fetchAll(
            "SELECT
                o.*,
                c.phone,
                c.name as customer_name,
                COUNT(oi.id) as item_count
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             LEFT JOIN order_items oi ON o.id = oi.order_id
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Get orders by status
     */
    public function getByStatus($status, $limit = 50) {
        return $this->db->fetchAll(
            "SELECT
                o.*,
                c.phone,
                c.name as customer_name
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             WHERE o.status = ?
             ORDER BY o.created_at DESC
             LIMIT ?",
            [$status, $limit]
        );
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber() {
        $prefix = 'WA';
        $date = date('Ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $orderNumber = "{$prefix}-{$date}-{$random}";

        // Check if exists (very unlikely)
        $exists = $this->findByNumber($orderNumber);
        if ($exists) {
            return $this->generateOrderNumber(); // Recursive retry
        }

        return $orderNumber;
    }

    /**
     * Get order statistics
     */
    public function getStats() {
        $stats = $this->db->fetchOne(
            "SELECT
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as orders_24h,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as orders_7d
             FROM orders"
        );

        return $stats;
    }

    /**
     * Cancel order
     */
    public function cancel($orderId, $reason = null) {
        $notes = $reason ? "Cancelled: {$reason}" : 'Cancelled';

        $this->db->update('orders',
            ['status' => 'cancelled', 'notes' => $notes],
            'id = :id',
            ['id' => $orderId]
        );
    }

    /**
     * Format order for display
     */
    public function formatForDisplay($order) {
        if (!$order) return null;

        $itemList = '';
        if (isset($order['items'])) {
            foreach ($order['items'] as $item) {
                $itemList .= "• {$item['product_name']} x{$item['quantity']} = " .
                             number_format($item['total_price'], 0, '.', ',') . " " . CURRENCY . "\n";
            }
        }

        return [
            'order_number' => $order['order_number'],
            'status' => $this->translateStatus($order['status']),
            'total' => number_format($order['total_amount'], 0, '.', ',') . ' ' . CURRENCY,
            'items_text' => $itemList,
            'created_at' => date('Y-m-d H:i', strtotime($order['created_at']))
        ];
    }

    /**
     * Translate status to Arabic
     */
    private function translateStatus($status) {
        $translations = [
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد المعالجة',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التسليم',
            'cancelled' => 'ملغي'
        ];

        return $translations[$status] ?? $status;
    }
}
