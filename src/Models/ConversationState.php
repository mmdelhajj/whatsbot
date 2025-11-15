<?php
/**
 * Conversation State Model
 * Manages conversation context and multi-step flows
 */

class ConversationState {
    private $db;

    // State types
    const STATE_IDLE = 'idle';
    const STATE_BROWSING_PRODUCTS = 'browsing_products';
    const STATE_AWAITING_PRODUCT_SELECTION = 'awaiting_product_selection';
    const STATE_CONFIRMING_PRODUCT = 'confirming_product';
    const STATE_AWAITING_QUANTITY = 'awaiting_quantity';
    const STATE_AWAITING_NAME = 'awaiting_name';
    const STATE_AWAITING_EMAIL = 'awaiting_email';
    const STATE_AWAITING_ADDRESS = 'awaiting_address';
    const STATE_CONFIRMING_ORDER = 'confirming_order';
    const STATE_AWAITING_ORDER_CANCEL = 'awaiting_order_cancel';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get customer's current state
     */
    public function get($customerId) {
        // Clean up expired states first
        $this->db->query(
            "DELETE FROM conversation_state WHERE expires_at < NOW()"
        );

        $context = $this->db->fetchOne(
            "SELECT * FROM conversation_state
             WHERE customer_id = ? AND expires_at > NOW()
             ORDER BY updated_at DESC
             LIMIT 1",
            [$customerId]
        );

        if (!$context) {
            return $this->createDefault($customerId);
        }

        // Decode JSON context_data
        $context['data'] = json_decode($context['context_data'], true) ?? [];

        return $context;
    }

    /**
     * Set/Update customer state
     */
    public function set($customerId, $state, $data = []) {
        // Get existing context
        $existing = $this->get($customerId);

        // Merge data
        $mergedData = array_merge($existing['data'] ?? [], $data);

        // Set expiration (30 minutes from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        if (isset($existing['id'])) {
            // Update existing
            $this->db->update('conversation_state', [
                'last_intent' => $state,
                'context_data' => json_encode($mergedData),
                'expires_at' => $expiresAt
            ], 'id = :id', ['id' => $existing['id']]);

            return $existing['id'];
        } else {
            // Create new
            return $this->db->insert('conversation_state', [
                'customer_id' => $customerId,
                'last_intent' => $state,
                'context_data' => json_encode($mergedData),
                'session_id' => uniqid('sess_', true),
                'expires_at' => $expiresAt
            ]);
        }
    }

    /**
     * Clear customer state
     */
    public function clear($customerId) {
        $this->db->query(
            "DELETE FROM conversation_state WHERE customer_id = ?",
            [$customerId]
        );
    }

    /**
     * Update only data without changing state
     */
    public function updateData($customerId, $data) {
        $current = $this->get($customerId);
        $this->set($customerId, $current['last_intent'], $data);
    }

    /**
     * Get state value
     */
    public function getState($customerId) {
        $context = $this->get($customerId);
        return $context['last_intent'] ?? self::STATE_IDLE;
    }

    /**
     * Get data value
     */
    public function getData($customerId, $key = null) {
        $context = $this->get($customerId);
        $data = $context['data'] ?? [];

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? null;
    }

    /**
     * Create default state
     */
    private function createDefault($customerId) {
        return [
            'id' => null,
            'customer_id' => $customerId,
            'last_intent' => self::STATE_IDLE,
            'data' => [],
            'session_id' => null,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes'))
        ];
    }
}
