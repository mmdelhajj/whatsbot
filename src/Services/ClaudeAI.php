<?php
/**
 * Claude AI Integration
 * Handles AI-powered conversations using Anthropic Claude
 */

class ClaudeAI {
    private $apiKey;
    private $apiUrl;
    private $model;
    private $maxTokens;

    public function __construct() {
        $this->apiKey = ANTHROPIC_API_KEY;
        $this->apiUrl = ANTHROPIC_API_URL;
        $this->model = ANTHROPIC_MODEL;
        $this->maxTokens = ANTHROPIC_MAX_TOKENS;
    }

    /**
     * Process customer message and generate AI response
     */
    public function processMessage($customerId, $customerMessage, $customerData = []) {
        try {
            // Get conversation context
            $messageModel = new Message();
            $recentMessages = $messageModel->getRecentForContext($customerId, 5);

            // Build system prompt
            $systemPrompt = $this->buildSystemPrompt($customerData);

            // Build conversation history
            $messages = $this->buildConversationHistory($recentMessages, $customerMessage);

            // Make API call to Claude
            $response = $this->callClaudeAPI($systemPrompt, $messages);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => $response['message'],
                    'intent' => $this->detectIntent($response['message'])
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error']
                ];
            }

        } catch (Exception $e) {
            logMessage("Claude AI Error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Smart product search with AI interpretation
     */
    public function smartProductSearch($customerId, $customerMessage, $customerData = []) {
        try {
            // Get available products from database
            $productModel = new Product();
            $allProducts = $productModel->getAll(1, 50); // Get page 1, 50 products

            // Build product context for AI
            $productContext = "Available products in inventory:\n";
            foreach (array_slice($allProducts, 0, 30) as $product) {
                $status = floatval($product['quantity']) > 0 ? 'IN STOCK' : 'OUT OF STOCK';
                $productContext .= "- {$product['item_name']} ({$product['item_code']}) - " .
                                  number_format($product['price'], 0) . " LBP - {$status}\n";
            }

            // Build AI prompt for product search
            $systemPrompt = "You are a product search system for " . STORE_NAME . ".\n\n";
            $systemPrompt .= "IMPORTANT: You MUST respond ONLY with product codes in this exact format:\n";
            $systemPrompt .= "SEARCH:CODE1,CODE2,CODE3\n\n";
            $systemPrompt .= $productContext . "\n\n";
            $systemPrompt .= "Instructions:\n";
            $systemPrompt .= "1. Analyze the customer's request\n";
            $systemPrompt .= "2. Find matching products from the inventory list above\n";
            $systemPrompt .= "3. Return ONLY: SEARCH:CODE1,CODE2,CODE3 (nothing else!)\n";
            $systemPrompt .= "4. If NO match found, respond: NO_MATCH\n\n";
            $systemPrompt .= "Examples:\n";
            $systemPrompt .= "Customer: 'رخيص قلم' → SEARCH:CODE1,CODE2\n";
            $systemPrompt .= "Customer: 'rouleau' → SEARCH:ROUL001,ROUL002\n";
            $systemPrompt .= "Customer: 'best pen' → SEARCH:CODE5,CODE6\n";
            $systemPrompt .= "Customer: 'unicorn' → NO_MATCH\n\n";
            $systemPrompt .= "Remember: ONLY return the format 'SEARCH:codes' or 'NO_MATCH'. No explanations!";

            $messages = [
                ['role' => 'user', 'content' => $customerMessage]
            ];

            $response = $this->callClaudeAPI($systemPrompt, $messages);

            if ($response['success']) {
                $aiResponse = $response['message'];

                // Check if AI returned product codes
                if (preg_match('/SEARCH:([^\n]+)/', $aiResponse, $matches)) {
                    $productCodes = array_map('trim', explode(',', $matches[1]));
                    $foundProducts = [];

                    foreach ($productCodes as $code) {
                        $product = $productModel->findByCode($code);
                        if ($product) {
                            $foundProducts[] = $product;
                        }
                    }

                    if (!empty($foundProducts)) {
                        return [
                            'success' => true,
                            'type' => 'products',
                            'products' => $foundProducts
                        ];
                    }
                }

                // AI gave a text response
                return [
                    'success' => true,
                    'type' => 'message',
                    'message' => str_replace('SEARCH:', '', $aiResponse)
                ];
            }

            return ['success' => false, 'error' => $response['error']];

        } catch (Exception $e) {
            logMessage("Smart search error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build system prompt for Claude
     */
    private function buildSystemPrompt($customerData) {
        $prompt = "You are a helpful AI assistant for **" . STORE_NAME . "**, a bookstore in " . STORE_LOCATION . ".\n\n";

        $prompt .= "**Your Role:**\n";
        $prompt .= "- Help customers find books and educational materials\n";
        $prompt .= "- Provide product information, prices, and availability\n";
        $prompt .= "- Assist with placing orders\n";
        $prompt .= "- Check account balances and credit limits\n";
        $prompt .= "- Answer questions in a friendly, professional manner\n\n";

        $prompt .= "**Important Guidelines:**\n";
        $prompt .= "- Respond in the customer's language (Arabic, English, or French)\n";
        $prompt .= "- Be concise and clear (max 2-3 sentences unless asked for details)\n";
        $prompt .= "- Use emojis to make messages friendly 😊\n";
        $prompt .= "- For prices, use format: XX,XXX " . CURRENCY . "\n";
        $prompt .= "- If you don't know something, be honest and offer to check\n";
        $prompt .= "- Guide customers naturally to find what they need\n\n";

        // Add customer context if available
        if (!empty($customerData['name'])) {
            $prompt .= "**Customer Information:**\n";
            $prompt .= "- Name: {$customerData['name']}\n";

            if (isset($customerData['balance'])) {
                $prompt .= "- Account Balance: " . number_format($customerData['balance'], 0, '.', ',') . " " . CURRENCY . "\n";
            }

            if (isset($customerData['credit_limit'])) {
                $prompt .= "- Credit Limit: " . number_format($customerData['credit_limit'], 0, '.', ',') . " " . CURRENCY . "\n";
            }

            $prompt .= "\n";
        }

        $prompt .= "**Common Scenarios:**\n";
        $prompt .= "1. Product Search: When customer asks about a book, guide them to provide specific names or codes\n";
        $prompt .= "2. Ordering: Confirm product details before creating order\n";
        $prompt .= "3. Account Inquiry: Provide balance and credit info clearly\n";
        $prompt .= "4. General Questions: Answer helpfully about store, location, hours, etc.\n\n";

        $prompt .= "Be warm, helpful, and efficient! 🌟";

        return $prompt;
    }

    /**
     * Build conversation history for Claude
     */
    private function buildConversationHistory($recentMessages, $currentMessage) {
        $messages = [];

        // Add recent messages
        foreach ($recentMessages as $msg) {
            $role = $msg['direction'] === 'RECEIVED' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => $msg['message']
            ];
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $currentMessage
        ];

        return $messages;
    }

    /**
     * Call Claude API
     */
    private function callClaudeAPI($systemPrompt, $messages) {
        $data = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages
        ];

        $ch = curl_init($this->apiUrl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => "CURL Error: {$error}"
            ];
        }

        if ($httpCode !== 200) {
            logMessage("Claude API HTTP {$httpCode}: {$response}", 'ERROR');
            return [
                'success' => false,
                'error' => "HTTP Error {$httpCode}"
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response'
            ];
        }

        if (isset($decoded['content'][0]['text'])) {
            return [
                'success' => true,
                'message' => $decoded['content'][0]['text']
            ];
        }

        return [
            'success' => false,
            'error' => 'Invalid response format'
        ];
    }

    /**
     * Detect intent from AI response or user message
     */
    private function detectIntent($message) {
        $message = strtolower($message);

        // Product search intent
        if (preg_match('/(بحث|كتاب|search|book|find|product)/u', $message)) {
            return 'product_search';
        }

        // Order intent
        if (preg_match('/(طلب|order|buy|purchase|اطلب|بدي)/u', $message)) {
            return 'order';
        }

        // Balance inquiry intent
        if (preg_match('/(رصيد|balance|حساب|account|credit)/u', $message)) {
            return 'balance_inquiry';
        }

        // Greeting intent
        if (preg_match('/(مرحبا|hello|hi|السلام|صباح|مساء)/u', $message)) {
            return 'greeting';
        }

        // Help intent
        if (preg_match('/(مساعدة|help|ساعد)/u', $message)) {
            return 'help';
        }

        return 'general';
    }

    /**
     * Generate product search results message
     */
    public function formatProductResults($products) {
        if (empty($products)) {
            return "❌ عذراً، لم أجد أي منتجات مطابقة لبحثك.\n\nيمكنك المحاولة بكلمات مختلفة أو الاتصال بنا مباشرة.";
        }

        $message = "🔍 نتائج البحث:\n\n";

        foreach ($products as $index => $product) {
            $num = $index + 1;
            $message .= "{$num}. **{$product['item_name']}**\n";
            $message .= "   📦 الكود: {$product['item_code']}\n";
            $message .= "   💰 السعر: " . number_format($product['price'], 0, '.', ',') . " " . CURRENCY . "\n";

            $stockStatus = $product['stock_quantity'] > 0 ? "✅ متوفر" : "❌ غير متوفر";
            $message .= "   {$stockStatus}\n\n";
        }

        $message .= "لطلب أي منتج، اكتب رقمه أو اسمه.";

        return $message;
    }

    /**
     * Test Claude API connection
     */
    public function testConnection() {
        $testPrompt = "You are a test assistant.";
        $testMessages = [
            ['role' => 'user', 'content' => 'Say hello']
        ];

        $result = $this->callClaudeAPI($testPrompt, $testMessages);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Claude API connection successful' : 'Connection failed',
            'error' => $result['error'] ?? null
        ];
    }
}
