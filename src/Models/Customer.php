<?php
/**
 * Customer Model
 * Handles customer data operations
 */

class Customer {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Find or create customer by phone number
     */
    public function findOrCreateByPhone($phone) {
        // Normalize phone number to local format (remove +961)
        $phone = $this->normalizePhone($phone);

        // First try exact match
        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE phone = ?",
            [$phone]
        );

        // If not found, try matching last 8 digits (Lebanese phone numbers)
        if (!$customer) {
            $localPhone = preg_replace('/[^0-9]/', '', $phone);
            $last8Digits = substr($localPhone, -8);

            $customer = $this->db->fetchOne(
                "SELECT * FROM customers
                 WHERE phone LIKE ?
                 LIMIT 1",
                ["%{$last8Digits}"]
            );
        }

        // If still not found, create new customer
        if (!$customer) {
            $customerId = $this->db->insert('customers', [
                'phone' => $phone
            ]);

            $customer = $this->db->fetchOne(
                "SELECT * FROM customers WHERE id = ?",
                [$customerId]
            );
        }

        return $customer;
    }

    /**
     * Get customer by ID
     */
    public function findById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE id = ?",
            [$id]
        );
    }

    /**
     * Update customer information
     */
    public function update($id, $data) {
        $this->db->update('customers', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Link customer to Brains account
     */
    public function linkBrainsAccount($customerId, $accountData) {
        $this->db->update('customers', [
            'brains_account_code' => $accountData['AccoCode'] ?? null,
            'name' => $accountData['AccoName'] ?? null,
            'email' => $accountData['Email'] ?? null,
            'balance' => $accountData['Balance'] ?? 0,
            'credit_limit' => $accountData['CreditLimit'] ?? 0,
            'address' => $accountData['Address'] ?? null
        ], 'id = :id', ['id' => $customerId]);
    }

    /**
     * Get customer with conversation history
     */
    public function getWithMessages($customerId, $limit = 10) {
        $customer = $this->findById($customerId);
        if (!$customer) return null;

        $messages = $this->db->fetchAll(
            "SELECT * FROM messages
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$customerId, $limit]
        );

        $customer['recent_messages'] = array_reverse($messages);
        return $customer;
    }

    /**
     * Get all customers with stats
     */
    public function getAllWithStats() {
        return $this->db->fetchAll(
            "SELECT
                c.*,
                COUNT(DISTINCT m.id) as message_count,
                COUNT(DISTINCT o.id) as order_count,
                MAX(m.created_at) as last_message_at
             FROM customers c
             LEFT JOIN messages m ON c.id = m.customer_id
             LEFT JOIN orders o ON c.id = o.customer_id
             GROUP BY c.id
             ORDER BY last_message_at DESC"
        );
    }

    /**
     * Search customers
     */
    public function search($query) {
        $searchTerm = "%{$query}%";
        return $this->db->fetchAll(
            "SELECT * FROM customers
             WHERE phone LIKE ?
                OR name LIKE ?
                OR brains_account_code LIKE ?
             LIMIT 50",
            [$searchTerm, $searchTerm, $searchTerm]
        );
    }

    /**
     * Normalize phone number to LOCAL format (remove +961, keep 0-prefix)
     */
    private function normalizePhone($phone) {
        // Remove spaces and special characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove +961 or 961 prefix - the remaining part already has the leading 0
        // +9613080203 or 9613080203 -> 3080203 (7 digits)
        // +96109851721 or 96109851721 -> 09851721 (8 digits)
        if (preg_match('/^\+?961(.+)$/', $phone, $matches)) {
            $localPart = $matches[1];

            // If local part doesn't start with 0, add it
            if (!preg_match('/^0/', $localPart)) {
                return '0' . $localPart;
            }

            // Already has leading 0
            return $localPart;
        }

        // If already local format (starts with 0), return as-is
        if (preg_match('/^0[0-9]{7,8}$/', $phone)) {
            // 03080203 or 070945227 -> return as-is
            return $phone;
        }

        // If 7-8 digits without 0, add it
        if (preg_match('/^[1-9][0-9]{6,7}$/', $phone)) {
            // 3080203 or 70945227 -> 03080203 or 070945227
            return '0' . $phone;
        }

        // Return as-is if doesn't match any pattern
        return $phone;
    }

    /**
     * Get customer statistics
     */
    public function getStats() {
        $stats = $this->db->fetchOne(
            "SELECT
                COUNT(*) as total_customers,
                COUNT(CASE WHEN brains_account_code IS NOT NULL THEN 1 END) as linked_customers,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
             FROM customers"
        );

        return $stats;
    }
}
