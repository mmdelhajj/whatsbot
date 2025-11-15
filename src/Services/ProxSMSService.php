<?php
/**
 * ProxSMS WhatsApp Integration
 * Handles sending messages via ProxSMS API
 */

class ProxSMSService {
    private $apiUrl;
    private $accountId;
    private $secret;

    public function __construct() {
        // ProxSMS API endpoint for sending WhatsApp messages
        $this->apiUrl = WHATSAPP_API_URL;
        $this->accountId = WHATSAPP_ACCOUNT_ID;  // WhatsApp unique ID from /get/wa.accounts
        $this->secret = WHATSAPP_SEND_SECRET;    // API secret from Tools -> API Keys
    }

    /**
     * Send text message to WhatsApp user
     */
    public function sendMessage($phone, $message, $priority = 2) {
        $data = [
            'secret' => $this->secret,
            'account' => $this->accountId,
            'recipient' => $this->normalizePhone($phone),
            'type' => 'text',
            'message' => $message,
            'priority' => $priority // 1 = high priority (immediate), 2 = normal
        ];

        return $this->makeRequest($data);
    }

    /**
     * Send message with image
     */
    public function sendImage($phone, $imageUrl, $caption = null) {
        $data = [
            'secret' => $this->secret,
            'account' => $this->accountId,
            'recipient' => $this->normalizePhone($phone),
            'type' => 'media',
            'message' => $caption ?: 'Image',
            'media_url' => $imageUrl,
            'media_type' => 'image',
            'priority' => 2
        ];

        return $this->makeRequest($data);
    }

    /**
     * Send document/file
     */
    public function sendDocument($phone, $documentUrl, $filename = null) {
        // Determine document type from filename or URL
        $docType = 'pdf'; // default
        if ($filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'xml', 'xls', 'xlsx', 'doc', 'docx'])) {
                $docType = $ext;
            }
        }

        $data = [
            'secret' => $this->secret,
            'account' => $this->accountId,
            'recipient' => $this->normalizePhone($phone),
            'type' => 'document',
            'message' => 'Document',
            'document_url' => $documentUrl,
            'document_name' => $filename ?: 'document.' . $docType,
            'document_type' => $docType,
            'priority' => 2
        ];

        return $this->makeRequest($data);
    }

    /**
     * Send location
     */
    public function sendLocation($phone, $latitude, $longitude, $name = null) {
        $data = [
            'secret' => $this->secret,
            'account' => $this->accountId,
            'recipient' => $this->normalizePhone($phone),
            'type' => 'location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name
        ];

        return $this->makeRequest($data);
    }

    /**
     * Make API request to ProxSMS
     */
    private function makeRequest($data) {
        try {
            $ch = curl_init($this->apiUrl);

            // ProxSMS expects multipart/form-data, not JSON
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // Send as form data, not JSON
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            // Don't set Content-Type header, let curl handle it for multipart

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                logMessage("ProxSMS API Error: {$error}", 'ERROR');
                return [
                    'success' => false,
                    'error' => $error
                ];
            }

            if ($httpCode !== 200) {
                logMessage("ProxSMS HTTP Error {$httpCode}: {$response}", 'ERROR');
                return [
                    'success' => false,
                    'error' => "HTTP {$httpCode}"
                ];
            }

            $decoded = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                logMessage("ProxSMS JSON Error: " . json_last_error_msg(), 'ERROR');
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response'
                ];
            }

            // Check if request was successful
            $isSuccess = isset($decoded['status']) &&
                        (
                            $decoded['status'] === 'success' ||
                            $decoded['status'] === 200 ||
                            $decoded['status'] === '200'
                        );

            return [
                'success' => $isSuccess,
                'response' => $decoded,
                'error' => !$isSuccess ? ($decoded['message'] ?? $decoded['error'] ?? 'Unknown error') : null
            ];

        } catch (Exception $e) {
            logMessage("ProxSMS Exception: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Normalize phone number for ProxSMS
     */
    private function normalizePhone($phone) {
        $original = $phone;

        // Trim whitespace first
        $phone = trim($phone);

        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // If empty after cleaning, return error
        if (empty($phone)) {
            logMessage("Phone normalization failed - Original: '$original', After cleaning: '$phone'", 'ERROR');
            throw new Exception('Invalid phone number!');
        }

        // If starts with 0, replace with +961 (local format like 03080203)
        if (strpos($phone, '0') === 0) {
            $phone = '+961' . substr($phone, 1);
        }
        // If starts with 961 (already has country code), add +
        else if (strpos($phone, '961') === 0) {
            $phone = '+' . $phone;
        }
        // If doesn't start with +, add +961 (assume local number without 0)
        else if (strpos($phone, '+') !== 0) {
            $phone = '+961' . $phone;
        }

        logMessage("Phone normalized: '$original' → '$phone'", 'DEBUG');
        return $phone;
    }

    /**
     * Test ProxSMS API connection
     */
    public function testConnection($testPhone) {
        $testMessage = "✅ ProxSMS connection test successful!";

        $result = $this->sendMessage($testPhone, $testMessage);

        return $result;
    }

    /**
     * Format and send store location
     */
    public function sendStoreLocation($phone) {
        // Librarie Memoires coordinates (example - update with real coordinates)
        $latitude = 34.4369;  // Tripoli, Lebanon
        $longitude = 35.8335;

        return $this->sendLocation($phone, $latitude, $longitude, STORE_NAME);
    }

    /**
     * Send formatted order confirmation
     */
    public function sendOrderConfirmation($phone, $order) {
        $orderModel = new Order();
        $formatted = $orderModel->formatForDisplay($order);

        $message = "✅ تم استلام طلبك بنجاح!\n\n";
        $message .= "📋 رقم الطلب: {$formatted['order_number']}\n";
        $message .= "💰 المبلغ الإجمالي: {$formatted['total']}\n\n";
        $message .= "📦 المنتجات:\n{$formatted['items_text']}\n";
        $message .= "📅 التاريخ: {$formatted['created_at']}\n";
        $message .= "⏳ الحالة: {$formatted['status']}\n\n";
        $message .= "شكراً لتسوقك معنا! 🙏";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send welcome message
     */
    public function sendWelcome($phone, $customerName = null) {
        $greeting = $customerName ? "مرحباً {$customerName}!" : "مرحباً!";

        $message = "{$greeting} 👋\n\n";
        $message .= "أهلاً بك في *{STORE_NAME}* 📚\n\n";
        $message .= "كيف يمكنني مساعدتك اليوم؟\n\n";
        $message .= "يمكنك:\n";
        $message .= "• البحث عن الكتب 🔍\n";
        $message .= "• الاستفسار عن الأسعار 💰\n";
        $message .= "• طلب منتجات 🛒\n";
        $message .= "• الاستعلام عن رصيدك 💳\n\n";
        $message .= "أنا هنا لمساعدتك! 😊";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send error message
     */
    public function sendError($phone, $errorType = 'general') {
        $messages = [
            'general' => "⚠️ عذراً، حدث خطأ. الرجاء المحاولة مرة أخرى.",
            'product_not_found' => "❌ عذراً، لم أتمكن من إيجاد المنتج المطلوب.",
            'out_of_stock' => "📦 عذراً، هذا المنتج غير متوفر حالياً.",
            'credit_limit' => "💳 عذراً، تجاوزت الحد الائتماني المسموح.",
            'system_error' => "⚙️ خطأ في النظام. الرجاء المحاولة لاحقاً."
        ];

        $message = $messages[$errorType] ?? $messages['general'];

        return $this->sendMessage($phone, $message);
    }
}
