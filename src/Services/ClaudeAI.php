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
            // Get conversation context (reduced to 3 for faster responses)
            $messageModel = new Message();
            $recentMessages = $messageModel->getRecentForContext($customerId, 3);

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
        $prompt = "⚠️ CRITICAL INSTRUCTION - READ THIS FIRST ⚠️\n\n";
        $prompt .= "You are NOT Claude. You are NOT an AI assistant created by Anthropic.\n";
        $prompt .= "You are a customer service representative working for " . STORE_NAME . ".\n";
        $prompt .= "NEVER EVER mention Anthropic, Claude, or being an AI assistant.\n";
        $prompt .= "NEVER say you don't have products or services - you DO sell real products!\n\n";

        $prompt .= "=== YOUR ACTUAL IDENTITY ===\n";
        $prompt .= "You are: The WhatsApp assistant for " . STORE_NAME . "\n";
        $prompt .= "You work at: " . STORE_LOCATION . "\n";
        $prompt .= "Your job: Help customers buy books, stationery, and educational materials\n\n";

        $prompt .= "=== STORE INFORMATION (MEMORIZE THIS) ===\n";
        $prompt .= "Store Name: " . STORE_NAME . "\n";
        $prompt .= "Location: " . STORE_LOCATION . "\n";
        $prompt .= "Phone: " . STORE_PHONE . "\n";
        if (!empty(STORE_WEBSITE)) {
            $prompt .= "Website: " . STORE_WEBSITE . " (YES, we HAVE a website!)\n";
        }
        $prompt .= "Hours: " . STORE_HOURS . "\n";
        $prompt .= "Products: Books, stationery, educational materials, toys, office supplies\n";
        $prompt .= "Contact: WhatsApp (this chat), Phone: " . STORE_PHONE . "\n\n";

        $prompt .= "=== HOW TO ANSWER COMMON QUESTIONS ===\n";
        $prompt .= "Q: 'What's your website?' → A: 'Our website is " . STORE_WEBSITE . " 🌐'\n";
        $prompt .= "Q: 'Who are you?' → A: 'I'm the WhatsApp assistant for " . STORE_NAME . " 😊'\n";
        $prompt .= "Q: 'Do you have a store?' → A: 'Yes! We're located in " . STORE_LOCATION . " 📍'\n";
        $prompt .= "Q: 'What do you sell?' → A: 'We sell books, stationery, educational materials, and more! 📚'\n\n";

        $prompt .= "=== RESPONSE RULES ===\n";
        $prompt .= "- Respond in customer's language (Arabic/English/French)\n";
        $prompt .= "- Be VERY brief (1-2 sentences max)\n";
        $prompt .= "- Use emojis 😊\n";
        $prompt .= "- Prices: XX,XXX " . CURRENCY . "\n\n";

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

        }

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

        // Log request for debugging
        logMessage("Claude API Request to: {$this->apiUrl}", 'DEBUG');

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

            // Try to decode error response
            $errorData = json_decode($response, true);
            $errorMsg = "HTTP Error {$httpCode}";
            if ($errorData && isset($errorData['error']['message'])) {
                $errorMsg .= ": " . $errorData['error']['message'];
            }

            return [
                'success' => false,
                'error' => $errorMsg,
                'raw_response' => $response
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
     * Analyze image and search for matching products
     */
    public function analyzeImageAndSearch($imageUrl, $lang = 'en') {
        try {
            logMessage("Starting image analysis for URL: $imageUrl", 'INFO');

            // Download image and convert to base64 (Claude requires HTTPS or base64)
            $imageData = @file_get_contents($imageUrl);

            if ($imageData === false) {
                logMessage("Failed to download image from URL: $imageUrl", 'ERROR');
                return [
                    'success' => false,
                    'error' => 'Failed to download image'
                ];
            }

            // Get image MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);

            // Convert to base64
            $base64Image = base64_encode($imageData);
            logMessage("Image downloaded and encoded. MIME type: $mimeType, Size: " . strlen($imageData) . " bytes", 'INFO');

            // Map MIME type to Claude's media_type
            $mediaTypeMap = [
                'image/jpeg' => 'image/jpeg',
                'image/jpg' => 'image/jpeg',
                'image/png' => 'image/png',
                'image/gif' => 'image/gif',
                'image/webp' => 'image/webp'
            ];

            $mediaType = $mediaTypeMap[$mimeType] ?? 'image/jpeg';

            // Use Claude's vision API to analyze the image
            $systemPrompt = "You are a product recognition assistant for a bookstore and stationery shop in Lebanon. When you see text in the image (especially Arabic, French, or English), extract it EXACTLY as written. Then identify what product it is.";

            $messages = [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mediaType,
                                'data' => $base64Image
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => 'IMPORTANT: If you see ANY text on the product (titles, labels, brand names), write the EXACT text you see in its original language (Arabic عربي, French, or English).

Then describe:
1. Product type (book, pen, notebook, etc.)
2. Any visible attributes (color, size, material)
3. Brand name if visible

Format:
TEXT ON PRODUCT: [exact text you see]
PRODUCT TYPE: [what it is]
DESCRIPTION: [brief description]'
                        ]
                    ]
                ]
            ];

            $response = $this->callClaudeAPI($systemPrompt, $messages);

            if (!$response['success']) {
                logMessage("Claude API error: " . ($response['error'] ?? 'Unknown'), 'ERROR');
                return [
                    'success' => false,
                    'error' => $response['error']
                ];
            }

            $description = $response['message'];
            logMessage("Image analyzed: {$description}", 'INFO');

            // Extract exact text from "TEXT ON PRODUCT:" if present
            $exactText = '';
            if (preg_match('/TEXT ON PRODUCT:\s*(.+?)(?:\n|$)/i', $description, $matches)) {
                $exactText = trim($matches[1]);
                logMessage("Exact text extracted from image: {$exactText}", 'INFO');
            }

            // Extract attributes (colors, materials, types)
            $attributes = $this->extractAttributes($description);
            logMessage("Extracted attributes: " . json_encode($attributes), 'INFO');

            // Extract keywords first (this filters out "image", "shows", etc.)
            $keywords = $this->extractKeywords($description);
            logMessage("Extracted keywords: " . implode(', ', $keywords), 'INFO');

            // Search for products using keywords
            $productModel = new Product();
            $products = [];

            // FIRST: Try searching with exact text (if we extracted it)
            if (!empty($exactText)) {
                logMessage("Searching for exact text: {$exactText}", 'INFO');
                logMessage("========== STARTING TEXT NORMALIZATION BLOCK ==========", 'INFO');

                // Normalize exact text (remove accents)
                $normalizedExact = $this->normalizeText($exactText);
                logMessage("Normalized exact text: {$normalizedExact}", 'INFO');

                // Extract meaningful words from normalized text
                $exactWords = preg_split('/\s+/', $normalizedExact);
                $meaningfulWords = [];
                $stopWords = ['de', 'du', 'la', 'le', 'les', 'et', 'the', 'a', 'an', 'and', 'or', 'of', 'in'];

                foreach ($exactWords as $word) {
                    $word = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]/ui', '', $word);
                    if (mb_strlen($word) >= 3 && !in_array($word, $stopWords)) {
                        $meaningfulWords[] = $word;
                    }
                }

                logMessage("Meaningful words extracted: " . implode(', ', $meaningfulWords), 'INFO');

                // Search for products containing multiple meaningful words
                if (!empty($meaningfulWords)) {
                    // Try first meaningful word
                    $allResults = $productModel->search($meaningfulWords[0], 200);
                    logMessage("Searching with first word '{$meaningfulWords[0]}': found " . count($allResults) . " results", 'INFO');

                    // Filter to products containing multiple meaningful words
                    foreach ($allResults as $product) {
                        $productNameNormalized = $this->normalizeText($product['item_name']);
                        $matchCount = 0;
                        $matchedWords = [];

                        foreach ($meaningfulWords as $word) {
                            if (stripos($productNameNormalized, $word) !== false) {
                                $matchCount++;
                                $matchedWords[] = $word;
                            }
                        }

                        // Keep products that match at least 2 words (or 1 if only 1-2 total words)
                        $requiredMatches = count($meaningfulWords) >= 3 ? 2 : 1;
                        if ($matchCount >= $requiredMatches) {
                            $product['match_score'] = $matchCount;
                            $product['matched_words'] = implode(', ', $matchedWords);
                            $products[] = $product;
                        }
                    }

                    if (!empty($products)) {
                        // Sort by match score
                        usort($products, function($a, $b) {
                            return ($b['match_score'] ?? 0) - ($a['match_score'] ?? 0);
                        });

                        logMessage("Found " . count($products) . " products matching words from exact text (top match: {$products[0]['item_name']})", 'INFO');
                    } else {
                        logMessage("No products matched multiple words, will try keywords", 'INFO');
                    }
                }
            }

            // If exact text didn't work, try each keyword
            if (empty($products)) {
                foreach ($keywords as $keyword) {
                    $products = $productModel->search($keyword, 100);
                    if (!empty($products)) {
                        logMessage("Found " . count($products) . " products with keyword: $keyword", 'INFO');

                        // ALWAYS filter by attributes (to exclude labels, holders, etc.)
                        $filteredProducts = $this->filterByAttributes($products, $attributes);
                        if (!empty($filteredProducts)) {
                            logMessage("Filtered to " . count($filteredProducts) . " products after exclusions", 'INFO');
                            $products = $filteredProducts;
                        }

                        break;
                    }
                }
            }

            // If still no results, try the full description as last resort
            if (empty($products)) {
                logMessage("No keyword matches, trying full description", 'INFO');
                $products = $productModel->search($description, 10);
            }

            // Limit to 50 products
            $products = array_slice($products, 0, 50);
            logMessage("Final result: " . count($products) . " products found", 'INFO');

            return [
                'success' => true,
                'description' => $description,
                'products' => $products
            ];

        } catch (Exception $e) {
            logMessage("Image analysis error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract attributes like colors, materials, types from description
     */
    private function extractAttributes($text) {
        $textLower = strtolower($text);

        $attributes = [
            'colors' => [],
            'materials' => [],
            'types' => []
        ];

        // Common colors
        $colors = [
            'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'black',
            'white', 'brown', 'gray', 'grey', 'gold', 'silver', 'bronze', 'violet',
            'cyan', 'magenta', 'turquoise', 'navy', 'maroon', 'beige', 'cream',
            'rouge', 'bleu', 'vert', 'jaune', 'orange', 'rose', 'noir', 'blanc',
            'أحمر', 'أزرق', 'أخضر', 'أصفر', 'برتقالي', 'وردي', 'أسود', 'أبيض'
        ];

        // Common materials
        $materials = [
            'plastic', 'metal', 'wood', 'wooden', 'paper', 'cardboard', 'leather',
            'fabric', 'cotton', 'polyester', 'rubber', 'silicon', 'glass', 'ceramic',
            'transparent', 'clear', 'plast', 'métal', 'bois', 'papier', 'cuir'
        ];

        // Product types
        $types = [
            'ballpoint', 'gel', 'fountain', 'mechanical', 'automatic', 'erasable',
            'refillable', 'retractable', 'clickable', 'twist'
        ];

        // Extract colors
        foreach ($colors as $color) {
            if (preg_match('/\b' . preg_quote($color, '/') . '\b/ui', $textLower)) {
                $attributes['colors'][] = $color;
            }
        }

        // Extract materials
        foreach ($materials as $material) {
            if (preg_match('/\b' . preg_quote($material, '/') . '\b/i', $textLower)) {
                $attributes['materials'][] = $material;
            }
        }

        // Extract types
        foreach ($types as $type) {
            if (preg_match('/\b' . preg_quote($type, '/') . '\b/i', $textLower)) {
                $attributes['types'][] = $type;
            }
        }

        return $attributes;
    }

    /**
     * Filter products by attributes (colors, materials)
     */
    private function filterByAttributes($products, $attributes) {
        // Exclusion keywords - products to SKIP when they appear with main product name
        $exclusions = [
            'holder', 'case', 'box', 'container', 'organizer', 'stand', 'rack',
            'support', 'tray', 'storage', 'pouch', 'sleeve',
            'label', 'labels', 'sticker', 'stickers', 'clear book', 'clearbook',
            'cover', 'protector', 'wrapper', 'bag', 'pocket', 'file', 'folder',
            // Non-stationery items
            'pump', 'intex', 'pool', 'inflatable', 'mattress', 'floatie', 'swim',
            'ball', 'toy gun', 'water gun', 'spray', 'hose'
        ];

        $filtered = [];

        foreach ($products as $product) {
            $productName = strtolower($product['item_name']);

            // SKIP if product name contains exclusion keywords
            $shouldExclude = false;
            foreach ($exclusions as $exclude) {
                if (stripos($productName, $exclude) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue; // Skip this product
            }

            $score = 0;

            // Check colors
            foreach ($attributes['colors'] as $color) {
                if (stripos($productName, $color) !== false) {
                    $score += 10; // High priority for color match
                }
            }

            // Check materials
            foreach ($attributes['materials'] as $material) {
                if (stripos($productName, $material) !== false) {
                    $score += 5;
                }
            }

            // Check types
            foreach ($attributes['types'] as $type) {
                if (stripos($productName, $type) !== false) {
                    $score += 3;
                }
            }

            // Add to filtered list if has any matching attributes
            if ($score > 0) {
                $product['match_score'] = $score;
                $filtered[] = $product;
            }
        }

        // Sort by score (highest first)
        usort($filtered, function($a, $b) {
            return ($b['match_score'] ?? 0) - ($a['match_score'] ?? 0);
        });

        // If we got good matches, return them. Otherwise return all products (excluding holders)
        if (!empty($filtered)) {
            return $filtered;
        }

        // Return products without exclusions
        $nonExcluded = [];
        foreach ($products as $product) {
            $productName = strtolower($product['item_name']);
            $shouldExclude = false;
            foreach ($exclusions as $exclude) {
                if (stripos($productName, $exclude) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }
            if (!$shouldExclude) {
                $nonExcluded[] = $product;
            }
        }

        return !empty($nonExcluded) ? $nonExcluded : $products;
    }

    /**
     * Normalize text for better matching (remove accents, lowercase, etc.)
     */
    private function normalizeText($text) {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Remove French/Spanish accents
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];

        $text = strtr($text, $accents);

        return $text;
    }

    /**
     * Extract keywords from description - SMART VERSION
     * Only extracts actual product-related keywords
     */
    private function extractKeywords($text) {
        // Comprehensive list of words to ignore
        $stopWords = [
            // Common words
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
            'is', 'are', 'was', 'were', 'this', 'that', 'these', 'those', 'from', 'by',
            // Image/description words
            'image', 'shows', 'depicts', 'features', 'picture', 'photo', 'photograph',
            'displayed', 'shown', 'includes', 'contains', 'has', 'have', 'also', 'comes',
            'packaging', 'branding', 'showcases', 'setting', 'depicted', 'visible',
            // Physical attributes (unless product-specific)
            'wearing', 'styled', 'design', 'pattern', 'featuring', 'vibrant', 'long',
            'decorated', 'color', 'scheme', 'accents', 'sparkly', 'ruffled',
            // Generic descriptors
            'holiday', 'collection', 'signature', 'line', 'edition', 'series'
        ];

        // Product type keywords (prioritize these!)
        $productTypes = [
            'barbie', 'doll', 'book', 'pen', 'pencil', 'notebook', 'ruler', 'eraser',
            'backpack', 'bag', 'toy', 'game', 'puzzle', 'sticker', 'coloring',
            'crayon', 'marker', 'paint', 'brush', 'glue', 'scissors', 'calculator',
            'compass', 'protractor', 'folder', 'binder', 'paper', 'card', 'poster',
            'album', 'diary', 'planner', 'calendar', 'agenda', 'atlas', 'dictionary',
            'novel', 'comic', 'magazine', 'workbook', 'textbook', 'guide', 'manual',
            'mattel', 'lego', 'disney', 'marvel', 'hasbro', 'crayola', 'staedtler'
        ];

        $priorityKeywords = [];

        // Extract capitalized words (likely brand names)
        if (preg_match_all('/\b([A-Z][a-z]{2,})\b/', $text, $matches)) {
            foreach ($matches[1] as $brand) {
                $brandLower = strtolower($brand);
                // Only add if it's a product type OR not a stopword
                if (in_array($brandLower, $productTypes) || !in_array($brandLower, $stopWords)) {
                    $priorityKeywords[] = $brandLower;
                }
            }
        }

        // Extract all words and check if they're product types
        $words = preg_split('/\s+/', strtolower($text));
        $keywords = [];

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word); // Remove numbers entirely

            // Only add if:
            // 1. It's in our product types list (HIGHEST PRIORITY)
            // 2. OR it's not a stopword, not numeric, and length > 3
            if (in_array($word, $productTypes)) {
                // Product type found - add to priority!
                array_unshift($priorityKeywords, $word);
            } elseif (strlen($word) > 3 && !in_array($word, $stopWords) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }

        // Combine: product types first, then brand names, then other keywords
        $allKeywords = array_merge(array_unique($priorityKeywords), $keywords);

        // Remove duplicates and limit to top 5 keywords
        return array_values(array_unique(array_slice($allKeywords, 0, 5)));
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
