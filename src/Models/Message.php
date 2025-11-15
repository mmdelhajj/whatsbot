<?php
/**
 * Message Model
 * Handles message storage and retrieval
 */

class Message {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Save incoming message
     */
    public function saveReceived($customerId, $message, $mediaUrl = null) {
        return $this->db->insert('messages', [
            'customer_id' => $customerId,
            'direction' => 'RECEIVED',
            'message' => $message,
            'media_url' => $mediaUrl,
            'message_type' => $mediaUrl ? 'media' : 'text'
        ]);
    }

    /**
     * Save outgoing message
     */
    public function saveSent($customerId, $message, $mediaUrl = null) {
        return $this->db->insert('messages', [
            'customer_id' => $customerId,
            'direction' => 'SENT',
            'message' => $message,
            'media_url' => $mediaUrl,
            'message_type' => $mediaUrl ? 'media' : 'text'
        ]);
    }

    /**
     * Get conversation history for a customer
     */
    public function getConversation($customerId, $limit = 50) {
        return $this->db->fetchAll(
            "SELECT * FROM messages
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$customerId, $limit]
        );
    }

    /**
     * Get recent messages for AI context (last 10 messages)
     */
    public function getRecentForContext($customerId, $limit = 10) {
        $messages = $this->db->fetchAll(
            "SELECT direction, message, created_at
             FROM messages
             WHERE customer_id = ?
                AND message IS NOT NULL
             ORDER BY created_at DESC
             LIMIT ?",
            [$customerId, $limit]
        );

        return array_reverse($messages);
    }

    /**
     * Get all messages with customer info
     */
    public function getAllWithCustomers($limit = 100) {
        return $this->db->fetchAll(
            "SELECT
                m.*,
                c.phone,
                c.name as customer_name
             FROM messages m
             JOIN customers c ON m.customer_id = c.id
             ORDER BY m.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Get message statistics
     */
    public function getStats() {
        $stats = $this->db->fetchOne(
            "SELECT
                COUNT(*) as total_messages,
                COUNT(CASE WHEN direction = 'RECEIVED' THEN 1 END) as received,
                COUNT(CASE WHEN direction = 'SENT' THEN 1 END) as sent,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7d
             FROM messages"
        );

        return $stats;
    }

    /**
     * Delete old messages (cleanup)
     */
    public function deleteOlderThan($days = 90) {
        return $this->db->query(
            "DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }
}
