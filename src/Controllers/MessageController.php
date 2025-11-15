<?php
/**
 * Message Controller - NEW STATE-BASED VERSION
 * Handles incoming messages with multi-step flows and language detection
 */

class MessageController {
    private $customerModel;
    private $messageModel;
    private $productModel;
    private $orderModel;
    private $conversationState;
    private $claudeAI;
    private $proxSMS;
    private $brainsAPI;

    const PRODUCTS_PER_PAGE = 10;

    public function __construct() {
        $this->customerModel = new Customer();
        $this->messageModel = new Message();
        $this->productModel = new Product();
        $this->orderModel = new Order();
        $this->conversationState = new ConversationState();
        $this->claudeAI = new ClaudeAI();
        $this->proxSMS = new ProxSMSService();
        $this->brainsAPI = new BrainsAPI();
    }

    /**
     * Main message processing entry point
     */
    public function processIncomingMessage($phone, $message, $attachment = null) {
        try {
            // Log incoming message
            $logMsg = "Incoming message from {$phone}: {$message}";
            if ($attachment) {
                $logMsg .= " [Attachment: {$attachment}]";
            }
            logMessage($logMsg, 'INFO', WEBHOOK_LOG_FILE);

            // Find or create customer
            $customer = $this->customerModel->findOrCreateByPhone($phone);

            // Save incoming message
            $this->messageModel->saveReceived($customer['id'], $message, $attachment);

            // Try to link customer with Brains account if not linked
            if (empty($customer['brains_account_code'])) {
                $this->tryLinkBrainsAccount($customer['id'], $phone);
                $customer = $this->customerModel->findById($customer['id']);
            }

            // Detect language from message
            $lang = LanguageDetector::detect($message);

            // Get conversation state
            $state = $this->conversationState->getState($customer['id']);

            // Save detected language to conversation state and customer record
            $this->conversationState->updateData($customer['id'], ['language' => $lang]);
            $this->customerModel->update($customer['id'], ['preferred_language' => $lang]);

            // If customer sent an image, analyze it first
            if ($attachment && !empty($attachment)) {
                $response = $this->handleImageMessage($customer['id'], $attachment, $lang);
            } else {
                // Process text message based on state
                $response = $this->routeMessage($customer, $message, $lang, $state);
            }

            // Send response
            if ($response) {
                $sendResult = $this->proxSMS->sendMessage($phone, $response);

                if ($sendResult['success']) {
                    $this->messageModel->saveSent($customer['id'], $response);
                    logMessage("Response sent to {$phone}", 'INFO', WEBHOOK_LOG_FILE);
                } else {
                    logMessage("Failed to send response: " . ($sendResult['error'] ?? 'Unknown'), 'ERROR', WEBHOOK_LOG_FILE);
                }
            }

            return [
                'success' => true,
                'customer_id' => $customer['id']
            ];

        } catch (Exception $e) {
            logMessage("Error processing message: " . $e->getMessage(), 'ERROR', WEBHOOK_LOG_FILE);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Route message based on conversation state
     */
    private function routeMessage($customer, $message, $lang, $state) {
        // Convert Arabic numerals to Western numerals first
        $message = $this->convertArabicNumerals($message);
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Check for explicit commands first (resets state)
        if ($this->isGreeting($messageLower)) {
            $this->conversationState->clear($customer['id']);
            // Trim name and use null if empty
            $customerName = !empty(trim($customer['name'])) ? trim($customer['name']) : null;

            // Check if customer is returning (hasn't messaged in more than 24 hours)
            $isReturning = false;
            if ($customerName) {
                // Get the last message from this customer (before this current one)
                $db = Database::getInstance();
                $lastMessage = $db->fetchOne(
                    "SELECT created_at FROM messages
                     WHERE customer_id = ?
                     AND direction = 'received'
                     ORDER BY created_at DESC
                     LIMIT 1 OFFSET 1",
                    [$customer['id']]
                );

                if ($lastMessage) {
                    $lastMessageTime = strtotime($lastMessage['created_at']);
                    $hoursSinceLastMessage = (time() - $lastMessageTime) / 3600;

                    // If customer hasn't messaged in more than 24 hours, they're "returning"
                    if ($hoursSinceLastMessage >= 24) {
                        $isReturning = true;
                    }
                }
            }

            return ResponseTemplates::welcome($lang, $customerName, $isReturning);
        }

        if ($this->isHelpRequest($messageLower)) {
            return ResponseTemplates::help($lang);
        }

        if ($this->isProductListRequest($messageLower)) {
            return $this->showProductList($customer['id'], $lang, 1);
        }

        if ($this->isBalanceInquiry($messageLower)) {
            return $this->handleBalanceInquiry($customer, $lang);
        }

        if ($this->isOrdersRequest($messageLower)) {
            return $this->showCustomerOrders($customer['id'], $lang);
        }

        // FAQ auto-responses (based on chat analysis)
        if ($this->isHowAreYou($messageLower)) {
            $responses = [
                'en' => "I'm doing great, thank you! 😊 How can I help you today?",
                'fr' => "Je vais bien, merci! 😊 Comment puis-je vous aider aujourd'hui?",
                'ar' => "أنا بخير، شكراً! 😊 كيف يمكنني مساعدتك اليوم؟"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        if ($this->isHoursQuery($messageLower)) {
            $responses = [
                'en' => "📅 *Opening Hours:*\n\n" .
                        "Mon-Fri: 7:00 AM - 8:00 PM\n" .
                        "Saturday: 8:00 AM - 7:00 PM\n" .
                        "Sunday: Open\n\n" .
                        "Visit our website: store.libmemoires.com",
                'fr' => "📅 *Horaires d'ouverture:*\n\n" .
                        "Lun-Ven: 7h00 - 20h00\n" .
                        "Samedi: 8h00 - 19h00\n" .
                        "Dimanche: Ouvert\n\n" .
                        "Visitez notre site: store.libmemoires.com",
                'ar' => "📅 *ساعات العمل:*\n\n" .
                        "الإثنين-الجمعة: 7:00 صباحاً - 8:00 مساءً\n" .
                        "السبت: 8:00 صباحاً - 7:00 مساءً\n" .
                        "الأحد: مفتوح\n\n" .
                        "زوروا موقعنا: store.libmemoires.com"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        if ($this->isLocationQuery($messageLower)) {
            $mapsLink = "https://maps.google.com/?q=34.00951559789577,35.654434764102675";
            $responses = [
                'en' => "📍 *Location:*\n\n" .
                        "Librairie Memoires\n" .
                        "Kfarhbab, Ghazir, Lebanon 🇱🇧\n\n" .
                        "🗺️ Google Maps: {$mapsLink}\n\n" .
                        "📞 Phone: +961 81 425 673\n" .
                        "🌐 Website: store.libmemoires.com",
                'fr' => "📍 *Localisation:*\n\n" .
                        "Librairie Memoires\n" .
                        "Kfarhbab, Ghazir, Liban 🇱🇧\n\n" .
                        "🗺️ Google Maps: {$mapsLink}\n\n" .
                        "📞 Téléphone: +961 81 425 673\n" .
                        "🌐 Site web: store.libmemoires.com",
                'ar' => "📍 *الموقع:*\n\n" .
                        "Librairie Memoires\n" .
                        "كفرحباب، غزير، لبنان 🇱🇧\n\n" .
                        "🗺️ خرائط جوجل: {$mapsLink}\n\n" .
                        "📞 هاتف: +961 81 425 673\n" .
                        "🌐 موقع: store.libmemoires.com"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        if ($this->isDeliveryQuery($messageLower)) {
            $responses = [
                'en' => "🚚 *Delivery Information:*\n\n" .
                        "We deliver through Aramex to any area in Lebanon!\n\n" .
                        "⏱️ Delivery time: Approximately 3 days\n" .
                        "📦 We'll notify you when ready for pickup\n\n" .
                        "Type *products* to browse our items! 😊",
                'fr' => "🚚 *Information de livraison:*\n\n" .
                        "Nous livrons via Aramex dans toutes les régions du Liban!\n\n" .
                        "⏱️ Délai: Environ 3 jours\n" .
                        "📦 Nous vous informerons quand c'est prêt\n\n" .
                        "Tapez *produits* pour voir nos articles! 😊",
                'ar' => "🚚 *معلومات التوصيل:*\n\n" .
                        "نوصل عبر أرامكس لأي منطقة في لبنان!\n\n" .
                        "⏱️ وقت التوصيل: حوالي 3 أيام\n" .
                        "📦 سنخبرك عندما يكون جاهزاً للاستلام\n\n" .
                        "اكتب *منتجات* لتصفح منتجاتنا! 😊"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        // State-based routing
        switch ($state) {
            case ConversationState::STATE_BROWSING_PRODUCTS:
            case ConversationState::STATE_AWAITING_PRODUCT_SELECTION:
                return $this->handleProductSelection($customer['id'], $message, $lang);

            case ConversationState::STATE_CONFIRMING_PRODUCT:
                return $this->handleProductConfirmation($customer['id'], $message, $lang);

            case ConversationState::STATE_AWAITING_QUANTITY:
                return $this->handleQuantityInput($customer['id'], $message, $lang);

            case ConversationState::STATE_AWAITING_NAME:
                return $this->handleNameInput($customer['id'], $message, $lang);

            case ConversationState::STATE_AWAITING_EMAIL:
                return $this->handleEmailInput($customer['id'], $message, $lang);

            case ConversationState::STATE_AWAITING_ADDRESS:
                return $this->handleAddressInput($customer['id'], $message, $lang);

            case ConversationState::STATE_AWAITING_ORDER_CANCEL:
                return $this->handleOrderCancellation($customer['id'], $message, $lang);

            case ConversationState::STATE_IDLE:
            default:
                // FIRST: Check if asking about store info (website, location, etc.) - answer directly!
                $storeInfoResponse = $this->checkStoreInfoQuestions($message, $lang);
                if ($storeInfoResponse !== null) {
                    return $storeInfoResponse;
                }

                // SECOND: Try quick search (FAST!)
                $searchResults = $this->quickProductSearch($customer['id'], $message, $lang);

                if ($searchResults !== null) {
                    return $searchResults;
                }

                // THIRD: Use AI to understand and search intelligently
                $aiSearch = $this->claudeAI->smartProductSearch($customer['id'], $message, $customer);

                if ($aiSearch['success']) {
                    if ($aiSearch['type'] === 'products' && !empty($aiSearch['products'])) {
                        // AI found products! Show them
                        return $this->displayAIFoundProducts($customer['id'], $aiSearch['products'], $lang);
                    } else if ($aiSearch['type'] === 'message') {
                        // Check if AI said NO_MATCH
                        if (trim($aiSearch['message']) === 'NO_MATCH') {
                            // Show friendly "no products found" message
                            $messages = [
                                'ar' => "❌ عذراً، لم أجد هذا المنتج في المخزون.\n\nاكتب *منتجات* لرؤية كل المنتجات المتاحة.",
                                'en' => "❌ Sorry, I couldn't find that product in our inventory.\n\nType *products* to see all available items.",
                                'fr' => "❌ Désolé, je n'ai pas trouvé ce produit dans notre inventaire.\n\nTapez *produits* pour voir tous les articles disponibles."
                            ];
                            return $messages[$lang] ?? $messages['en'];
                        }
                        // AI gave other helpful response
                        return $aiSearch['message'];
                    }
                }

                // Last resort: general AI
                return $this->handleWithAI($customer, $message, $lang);
        }
    }

    /**
     * Check if customer is asking about store info (website, location, hours, etc.)
     * Answer directly without using AI to prevent wrong responses
     */
    private function checkStoreInfoQuestions($message, $lang) {
        $messageLower = mb_strtolower($message, 'UTF-8');

        // Website questions
        if (preg_match('/\b(website|site|موقع|موقعكم|موقع الكتروني|site web|votre site)\b/ui', $messageLower)) {
            $responses = [
                'ar' => "📱 موقعنا الإلكتروني: " . STORE_WEBSITE . "\n\nيمكنك زيارتنا على الموقع أو التواصل معنا هنا على الواتساب! 😊",
                'en' => "🌐 Our website: " . STORE_WEBSITE . "\n\nYou can visit our website or chat with us here on WhatsApp! 😊",
                'fr' => "🌐 Notre site web: " . STORE_WEBSITE . "\n\nVous pouvez visiter notre site ou nous contacter ici sur WhatsApp! 😊"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        // Location questions
        if (preg_match('/\b(where|location|address|وين|أين|عنوان|موقع المحل|où|adresse|localisation)\b/ui', $messageLower)) {
            $responses = [
                'ar' => "📍 موقعنا: " . STORE_LOCATION . "\n\n📞 للتواصل: " . STORE_PHONE . "\n\nنحن هنا لخدمتك! 😊",
                'en' => "📍 Our location: " . STORE_LOCATION . "\n\n📞 Phone: " . STORE_PHONE . "\n\nWe're here to help! 😊",
                'fr' => "📍 Notre adresse: " . STORE_LOCATION . "\n\n📞 Téléphone: " . STORE_PHONE . "\n\nNous sommes là pour vous aider! 😊"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        // Hours/opening questions
        if (preg_match('/\b(hours|open|opening|schedule|وقت|ساعات|متى|timing|horaires|ouvert|heures d\'ouverture)\b/ui', $messageLower)) {
            $responses = [
                'ar' => "🕐 أوقات العمل: " . STORE_HOURS . "\n\n📞 للاستفسار: " . STORE_PHONE . "\n\nأهلاً وسهلاً بك! 😊",
                'en' => "🕐 Business hours: " . STORE_HOURS . "\n\n📞 Contact: " . STORE_PHONE . "\n\nWelcome! 😊",
                'fr' => "🕐 Heures d'ouverture: " . STORE_HOURS . "\n\n📞 Contact: " . STORE_PHONE . "\n\nBienvenue! 😊"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        // Who are you / identity questions
        if (preg_match('/\b(who are you|what are you|من أنت|مين أنت|شو إنت|qui êtes-vous|anthropic|claude)\b/ui', $messageLower)) {
            $responses = [
                'ar' => "👋 أنا مساعد الواتساب لـ " . STORE_NAME . "!\n\nأساعدك في إيجاد الكتب والقرطاسية وتقديم الطلبات. كيف يمكنني مساعدتك اليوم؟ 😊",
                'en' => "👋 I'm the WhatsApp assistant for " . STORE_NAME . "!\n\nI help you find books, stationery, and place orders. How can I help you today? 😊",
                'fr' => "👋 Je suis l'assistant WhatsApp de " . STORE_NAME . "!\n\nJe vous aide à trouver des livres, de la papeterie et à passer des commandes. Comment puis-je vous aider aujourd'hui? 😊"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        // Contact/phone questions
        if (preg_match('/\b(phone|contact|call|رقم|هاتف|اتصال|téléphone|appeler|numéro)\b/ui', $messageLower)) {
            $responses = [
                'ar' => "📞 رقم الهاتف: " . STORE_PHONE . "\n📍 الموقع: " . STORE_LOCATION . "\n🌐 الموقع الإلكتروني: " . STORE_WEBSITE . "\n\nتواصل معنا بأي وقت! 😊",
                'en' => "📞 Phone: " . STORE_PHONE . "\n📍 Location: " . STORE_LOCATION . "\n🌐 Website: " . STORE_WEBSITE . "\n\nContact us anytime! 😊",
                'fr' => "📞 Téléphone: " . STORE_PHONE . "\n📍 Adresse: " . STORE_LOCATION . "\n🌐 Site web: " . STORE_WEBSITE . "\n\nContactez-nous à tout moment! 😊"
            ];
            return $responses[$lang] ?? $responses['en'];
        }

        // No store info question detected
        return null;
    }

    /**
     * Quick product search (NO AI, direct database search)
     */
    private function quickProductSearch($customerId, $message, $lang) {
        // Extract search keywords (remove common words - use word boundaries to avoid partial matches)
        // First, remove multi-word phrases
        $cleanMessage = preg_replace(
            '/\b(do you have|are there|is there|what do you have|looking for|i want|show me|' .
            'je cherche|avez-vous|y a-t-il|je veux|qu\'avez-vous|est-ce que|vous avez|de la|de l\'|' .
            'هل لديك|هل عندك|هل يوجد|هل تملك|ها لديك|ها عندك|ها يوجد|' .
            'ماذا يوجد|ماذا لديك|ماذا عندك|شو عندك|شو فيه|ابحث عن|' .
            'what|whats|whats)\b/ui',
            ' ',
            $message
        );

        // Then remove single words (with word boundaries to avoid matching inside words like "rouleau")
        $cleanMessage = preg_replace(
            '/\b(need|want|' .
            'ها|هل|لديك|عندك|اديك|عندكم|لديكم|بدي|بدك|بدنا|موجود|يوجد|فيه|أريد|اريد|بحاجة|شو|' .
            'cherche|' .
            'des|les|le|la|un|une|du|l\'|d\'|' .
            'kifak|keefak|kefak|shu|shou|3andak|3andek|3andik|3endak|3endek|fi|fee|fih|feeh|baddi|badde|bade|badi)\b/ui',
            ' ',
            $cleanMessage
        );
        // Clean up multiple spaces and trim
        $cleanMessage = preg_replace('/\s+/', ' ', $cleanMessage);
        $cleanMessage = trim($cleanMessage);

        // If message is too short, don't search
        if (strlen($cleanMessage) < 2) {
            return null;
        }

        // Translate common Arabic/French/Lebanese product names to match inventory
        $translations = [
            // Popular toys (Arabic)
            'بربي' => 'Barbie',
            'باربي' => 'Barbie',
            'هوتويلز' => 'Hotwheels',
            'هوت ويلز' => 'Hotwheels',
            'ديزني' => 'Disney',
            'ليغو' => 'Lego',
            'ليجو' => 'Lego',
            'دراغون بول' => 'Dragon Ball',
            'سبايدرمان' => 'Spiderman',
            'سبايدر مان' => 'Spiderman',
            // School supplies (Arabic) - use French since inventory is in French
            'قلم' => 'pen',
            'أقلام' => 'pen',
            'كراس' => 'cahier',
            'دفتر' => 'cahier',
            'كتاب' => 'livre',
            'محاية' => 'eraser',
            'ممحاة' => 'eraser',
            'مسطرة' => 'ruler',
            'حقيبة' => 'bag',
            // Colors (Arabic)
            'أحمر' => 'red',
            'أزرق' => 'blue',
            'أصفر' => 'yellow',
            'أخضر' => 'green',
            'أسود' => 'black',
            'أبيض' => 'white',
            // Lebanese transliteration (Franco-Arabic/Arabizi)
            'daftar' => 'cahier',
            'defter' => 'cahier',
            'deftar' => 'cahier',
            '2alam' => 'pen',
            'alam' => 'pen',
            'alem' => 'pen',
            'kteb' => 'livre',
            'kitab' => 'livre',
            'ktab' => 'livre',
            'kras' => 'cahier',
            'krass' => 'cahier',
            'ma7aya' => 'eraser',
            'ma7aye' => 'eraser',
            'mastura' => 'ruler',
            'mastara' => 'ruler',
            'cha2ta' => 'bag',
            'sha2ta' => 'bag',
            'shakta' => 'bag',
            // French office supplies
            'rouleau' => 'tape',
            'ruban' => 'tape',
            'scotch' => 'tape',
            'adhesif' => 'tape',
            'bande' => 'tape',
            // Toys (Lebanese transliteration)
            'barbee' => 'Barbie',
            'hotwheels' => 'Hotwheels',
            'hot wheels' => 'Hotwheels',
            'lego' => 'Lego',
            'disney' => 'Disney',
            'spiderman' => 'Spiderman',
            // Colors (Lebanese transliteration)
            'a7mar' => 'red',
            'ahmar' => 'red',
            'azra2' => 'blue',
            'azrak' => 'blue',
            'asfar' => 'yellow',
            'a9far' => 'yellow',
            'akhdar' => 'green',
            'a5dar' => 'green',
            'aswad' => 'black',
            'esswad' => 'black',
            'abyad' => 'white',
            'abyed' => 'white',
            // Descriptive adjectives (Arabic)
            'رخيص' => 'cheap',
            'رخيصة' => 'cheap',
            'غالي' => 'expensive',
            'غالية' => 'expensive',
            'صغير' => 'small',
            'صغيرة' => 'small',
            'كبير' => 'large',
            'كبيرة' => 'large',
            'جديد' => 'new',
            'جديدة' => 'new',
            'جيد' => 'good',
            'جيدة' => 'good',
            'أفضل' => 'best',
            'أحسن' => 'best',
            // More school supplies (Arabic)
            'مبراة' => 'sharpener',
            'مقص' => 'scissors',
            'مقلمة' => 'pencil case',
            'فرشاة' => 'brush',
            'ألوان' => 'colors',
            'صبغ' => 'paint',
            'صمغ' => 'glue',
            'ورق' => 'paper',
            'ملف' => 'file',
            'مجلد' => 'folder',
            'شنطة' => 'backpack',
            'كتف' => 'bag',
            'يومية' => 'diary',
            'دفتر ملاحظات' => 'notebook',
            'لعبة' => 'toy',
            'لعب' => 'game',
            // Lebanese transliteration - school supplies
            'mabra' => 'sharpener',
            'mabra2a' => 'sharpener',
            'ma2ass' => 'scissors',
            'mi2ass' => 'scissors',
            'farsha' => 'brush',
            'farsheh' => 'brush',
            'alwan' => 'colors',
            'loon' => 'color',
            'sabgh' => 'paint',
            'sam3' => 'glue',
            'samgh' => 'glue',
            'wara2' => 'paper',
            'waraק' => 'paper',
            'malaf' => 'file',
            'mujallad' => 'folder',
            'shanta' => 'backpack',
            'shante' => 'backpack',
            'yawmiyeh' => 'diary',
            'lo3ba' => 'toy',
            'lo3beh' => 'toy',
            // French school supplies
            'sac' => 'bag',
            'cartable' => 'backpack',
            'trousse' => 'pencil case',
            'taille' => 'sharpener',
            'ciseaux' => 'scissors',
            'pinceau' => 'brush',
            'couleur' => 'color',
            'couleurs' => 'colors',
            'peinture' => 'paint',
            'colle' => 'glue',
            'papier' => 'paper',
            'dossier' => 'folder',
            'classeur' => 'file',
            'agenda' => 'diary',
            'jouet' => 'toy',
            'jeu' => 'game',
            'marqueur' => 'marker',
            'feutre' => 'marker',
            'gomme' => 'eraser',
            'règle' => 'ruler',
            // More colors (French)
            'rouge' => 'red',
            'bleu' => 'blue',
            'jaune' => 'yellow',
            'vert' => 'green',
            'noir' => 'black',
            'blanc' => 'white',
            'rose' => 'pink',
            'violet' => 'purple',
            'orange' => 'orange',
            'gris' => 'gray',
            'marron' => 'brown',
            // More colors (Arabic)
            'وردي' => 'pink',
            'بنفسجي' => 'purple',
            'برتقالي' => 'orange',
            'رمادي' => 'gray',
            'بني' => 'brown',
            // Colors (Lebanese)
            'wardi' => 'pink',
            'banafseji' => 'purple',
            'borto2ali' => 'orange',
            'ramadi' => 'gray',
            'bonne' => 'brown',
            // Size descriptors (French)
            'grand' => 'large',
            'grande' => 'large',
            'petit' => 'small',
            'petite' => 'small',
            // Common brands (variations)
            'kipling' => 'Kipling',
            'eastpak' => 'Eastpak',
            'maped' => 'Maped',
            'pebeo' => 'Pebeo',
            'genova' => 'Genova',
            // Lebanese Arabic common words (from chat analysis)
            'kteb' => 'book',
            'ktab' => 'book',
            'kdeh' => 'notebook',
            'mesta3mal' => 'used',
            'jdid' => 'new',
            'jdide' => 'new',
        ];

        // Translate Arabic/Lebanese/French words to match inventory
        $searchTerm = $cleanMessage;
        foreach ($translations as $foreign => $english) {
            if (stripos($searchTerm, $foreign) !== false) {
                $searchTerm = str_ireplace($foreign, $english, $searchTerm);
            }
        }

        // Check if search contains descriptive words that need smart sorting
        $sortPreference = null;
        $baseSearchTerm = $searchTerm;

        if (stripos($searchTerm, 'cheap') !== false || stripos($searchTerm, 'رخيص') !== false) {
            $sortPreference = 'price_asc';
            $baseSearchTerm = trim(str_ireplace(['cheap', 'رخيص', 'رخيصة'], '', $searchTerm));
        } elseif (stripos($searchTerm, 'expensive') !== false || stripos($searchTerm, 'غالي') !== false) {
            $sortPreference = 'price_desc';
            $baseSearchTerm = trim(str_ireplace(['expensive', 'غالي', 'غالية'], '', $searchTerm));
        } elseif (stripos($searchTerm, 'best') !== false || stripos($searchTerm, 'أفضل') !== false || stripos($searchTerm, 'أحسن') !== false) {
            $sortPreference = 'best';
            $baseSearchTerm = trim(str_ireplace(['best', 'أفضل', 'أحسن'], '', $searchTerm));
        }

        // Search products with base term if we found a sort preference
        if ($sortPreference && !empty($baseSearchTerm)) {
            $products = $this->productModel->search($baseSearchTerm, 100);
        } else {
            $products = $this->productModel->search($searchTerm, 100);
        }

        if (empty($products)) {
            // No products found - return null to let AI handle it
            return null;
        }

        // Sort products based on preference
        if ($sortPreference === 'price_asc') {
            usort($products, function($a, $b) {
                return floatval($a['price']) - floatval($b['price']);
            });
        } elseif ($sortPreference === 'price_desc') {
            usort($products, function($a, $b) {
                return floatval($b['price']) - floatval($a['price']);
            });
        } elseif ($sortPreference === 'best') {
            // Sort by stock availability first, then by price
            usort($products, function($a, $b) {
                $aStock = floatval($a['quantity']) > 0 ? 1 : 0;
                $bStock = floatval($b['quantity']) > 0 ? 1 : 0;
                if ($aStock !== $bStock) {
                    return $bStock - $aStock; // In stock first
                }
                return floatval($b['price']) - floatval($a['price']); // Then by price (higher = better quality)
            });
        }

        // Show found products with pagination
        $totalProducts = count($products);
        $totalPages = ceil($totalProducts / self::PRODUCTS_PER_PAGE);
        $page = 1;
        $productsPage = array_slice($products, 0, self::PRODUCTS_PER_PAGE);

        // Save state
        $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_PRODUCT_SELECTION, [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'products_on_page' => $productsPage,
            'search_query' => $cleanMessage,
            'all_search_results' => $products
        ]);

        return ResponseTemplates::productList($lang, $productsPage, $page, $totalPages);
    }

    /**
     * Show paginated product list (all products)
     */
    private function showProductList($customerId, $lang, $page = 1) {
        // Get all products
        $allProducts = $this->productModel->getAllInStock();
        $totalProducts = count($allProducts);
        $totalPages = ceil($totalProducts / self::PRODUCTS_PER_PAGE);

        // Ensure page is valid
        $page = max(1, min($page, $totalPages));

        // Get products for current page
        $offset = ($page - 1) * self::PRODUCTS_PER_PAGE;
        $products = array_slice($allProducts, $offset, self::PRODUCTS_PER_PAGE);

        // Save state
        $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_PRODUCT_SELECTION, [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'products_on_page' => $products,
            'all_products' => $allProducts
        ]);

        return ResponseTemplates::productList($lang, $products, $page, $totalPages);
    }

    /**
     * Handle product selection or pagination
     */
    private function handleProductSelection($customerId, $message, $lang) {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Check for "next" command
        if (preg_match('/(next|التالي|suivant)/u', $messageLower)) {
            $data = $this->conversationState->getData($customerId);
            $currentPage = $data['current_page'] ?? 1;
            $totalPages = $data['total_pages'] ?? 1;
            $nextPage = min($currentPage + 1, $totalPages);

            // Check if it's a search result or full catalog
            if (isset($data['all_search_results'])) {
                // Paginate search results
                $allProducts = $data['all_search_results'];
                $offset = ($nextPage - 1) * self::PRODUCTS_PER_PAGE;
                $productsPage = array_slice($allProducts, $offset, self::PRODUCTS_PER_PAGE);

                $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_PRODUCT_SELECTION, [
                    'current_page' => $nextPage,
                    'total_pages' => $totalPages,
                    'products_on_page' => $productsPage,
                    'all_search_results' => $allProducts
                ]);

                return ResponseTemplates::productList($lang, $productsPage, $nextPage, $totalPages);
            } else {
                // Paginate full catalog
                return $this->showProductList($customerId, $lang, $nextPage);
            }
        }

        // Check for product number selection
        if (preg_match('/^\d+$/', trim($message))) {
            logMessage("Product selection detected: $message", 'DEBUG');
            $selectedNum = (int)$message;
            $data = $this->conversationState->getData($customerId);
            $products = $data['products_on_page'] ?? [];

            logMessage("Products on page: " . count($products), 'DEBUG');

            // Validate selection
            if ($selectedNum < 1 || $selectedNum > count($products)) {
                logMessage("Invalid product selection: $selectedNum (max: " . count($products) . ")", 'DEBUG');
                return ResponseTemplates::invalidInput($lang);
            }

            $selectedProduct = $products[$selectedNum - 1];
            logMessage("Selected product: {$selectedProduct['item_name']}", 'DEBUG');

            // Check stock
            if ($selectedProduct['stock_quantity'] <= 0) {
                logMessage("Product out of stock", 'DEBUG');
                return ResponseTemplates::productNotAvailable($lang);
            }

            // Get customer for phone number
            $customer = $this->customerModel->findById($customerId);

            // Send product image if available
            if (!empty($selectedProduct['image_url'])) {
                try {
                    logMessage("Sending product image: {$selectedProduct['image_url']}", 'DEBUG');
                    $this->sendProductImage($customer['phone'], $selectedProduct, $lang, $customerId);
                    logMessage("Product image sent successfully (outer)", 'DEBUG');
                } catch (Exception $e) {
                    logMessage("Error sending product image: " . $e->getMessage(), 'ERROR');
                    // Continue anyway - don't fail the whole flow if image fails
                }
            }

            logMessage("After image sending block", 'DEBUG');

            // Save selected product and ask for confirmation
            logMessage("Setting state to CONFIRMING_PRODUCT", 'DEBUG');
            $this->conversationState->set($customerId, ConversationState::STATE_CONFIRMING_PRODUCT, [
                'selected_product' => $selectedProduct
            ]);

            $confirmMessage = ResponseTemplates::askProductConfirmation($lang, $selectedProduct['item_name']);
            logMessage("Confirmation message: $confirmMessage", 'DEBUG');
            return $confirmMessage;
        }

        // If it's not a number or "next", treat it as a new product search
        // This allows users to search for a new product while browsing
        $searchResult = $this->quickProductSearch($customerId, $message, $lang);

        if ($searchResult !== null) {
            return $searchResult;
        }

        return ResponseTemplates::invalidInput($lang);
    }

    /**
     * Handle product confirmation (when customer types "1" to confirm)
     */
    private function handleProductConfirmation($customerId, $message, $lang) {
        $messageTrimmed = trim($message);

        // Check if customer confirmed with "1"
        if ($messageTrimmed === '1') {
            // Get selected product from state
            $data = $this->conversationState->getData($customerId);
            $selectedProduct = $data['selected_product'] ?? null;

            if (!$selectedProduct) {
                return ResponseTemplates::invalidInput($lang);
            }

            // Move to quantity input state
            $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_QUANTITY, [
                'selected_product' => $selectedProduct
            ]);

            return ResponseTemplates::askQuantity($lang, $selectedProduct['item_name']);
        }

        // If not "1", treat as new search
        $searchResult = $this->quickProductSearch($customerId, $message, $lang);

        if ($searchResult !== null) {
            return $searchResult;
        }

        return ResponseTemplates::invalidInput($lang);
    }

    /**
     * Handle quantity input
     */
    private function handleQuantityInput($customerId, $message, $lang) {
        $quantity = (int)trim($message);

        // Validate quantity
        if ($quantity < 1 || $quantity > 1000) {
            $messages = [
                'ar' => "❌ الرجاء إدخال كمية صحيحة (من 1 إلى 1000)",
                'en' => "❌ Please enter a valid quantity (1 to 1000)",
                'fr' => "❌ Veuillez entrer une quantité valide (1 à 1000)"
            ];
            return $messages[$lang] ?? $messages['en'];
        }

        // Get selected product from state
        $data = $this->conversationState->getData($customerId);
        $selectedProduct = $data['selected_product'] ?? null;

        if (!$selectedProduct) {
            return ResponseTemplates::invalidInput($lang);
        }

        // Check if quantity is available in stock
        if ($quantity > $selectedProduct['stock_quantity']) {
            $available = $selectedProduct['stock_quantity'];
            $messages = [
                'ar' => "❌ عذراً، الكمية المتوفرة هي {$available} قطعة فقط.\n\nالرجاء إدخال كمية أقل.",
                'en' => "❌ Sorry, only {$available} pieces available in stock.\n\nPlease enter a lower quantity.",
                'fr' => "❌ Désolé, seulement {$available} pièces disponibles en stock.\n\nVeuillez entrer une quantité inférieure."
            ];
            return $messages[$lang] ?? $messages['en'];
        }

        // Save quantity and proceed with order flow
        $this->conversationState->updateData($customerId, ['quantity' => $quantity]);

        // Check if customer has complete information
        $customer = $this->customerModel->findById($customerId);

        if (!empty($customer['name']) && !empty($customer['email']) && !empty($customer['address'])) {
            // Customer has all info, create order directly
            return $this->createOrderDirectly($customerId, $selectedProduct, $customer, $lang);
        } else {
            // Need to collect customer information, start with name
            $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_NAME, [
                'selected_product' => $selectedProduct,
                'quantity' => $quantity
            ]);

            return ResponseTemplates::askName($lang, $selectedProduct['item_name']);
        }
    }

    /**
     * Handle customer name input
     */
    private function handleNameInput($customerId, $message, $lang) {
        $name = trim($message);

        if (empty($name) || strlen($name) < 2) {
            return ResponseTemplates::invalidInput($lang);
        }

        // Save name and move to email
        $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_EMAIL, [
            'customer_name' => $name
        ]);

        return ResponseTemplates::askEmail($lang);
    }

    /**
     * Handle customer email input
     */
    private function handleEmailInput($customerId, $message, $lang) {
        $email = trim($message);

        // Basic email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResponseTemplates::invalidInput($lang);
        }

        // Save email and move to address
        $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_ADDRESS, [
            'customer_email' => $email
        ]);

        return ResponseTemplates::askAddress($lang);
    }

    /**
     * Handle customer address input and create order
     */
    private function handleAddressInput($customerId, $message, $lang) {
        $address = trim($message);

        if (empty($address) || strlen($address) < 5) {
            return ResponseTemplates::invalidInput($lang);
        }

        // Get all collected data
        $data = $this->conversationState->getData($customerId);
        $product = $data['selected_product'];
        $name = $data['customer_name'];
        $email = $data['customer_email'];
        $quantity = $data['quantity'] ?? 1;

        // Update customer information
        $this->customerModel->update($customerId, [
            'name' => $name,
            'email' => $email,
            'address' => $address
        ]);

        // Create order
        try {
            $order = $this->orderModel->create($customerId, [
                [
                    'product_sku' => $product['item_code'],
                    'product_name' => $product['item_name'],
                    'quantity' => $quantity,
                    'unit_price' => $product['price']
                ]
            ], "WhatsApp Order - {$name}");

            // Try to create in Brains
            $customer = $this->customerModel->findById($customerId);
            $this->tryCreateBrainsInvoice($order, $customer);

            // Clear conversation state
            $this->conversationState->clear($customerId);

            // Send confirmation
            return ResponseTemplates::orderConfirmation($lang, [
                'product_name' => $product['item_name'],
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_address' => $address,
                'quantity' => $quantity,
                'price' => $product['price']
            ]);

        } catch (Exception $e) {
            logMessage("Order creation failed: " . $e->getMessage(), 'ERROR');
            $this->conversationState->clear($customerId);
            return ResponseTemplates::invalidInput($lang);
        }
    }

    /**
     * Create order directly when customer already has complete information
     */
    private function createOrderDirectly($customerId, $product, $customer, $lang) {
        // Get quantity from conversation state
        $data = $this->conversationState->getData($customerId);
        $quantity = $data['quantity'] ?? 1;

        try {
            // Create order using existing customer information
            $order = $this->orderModel->create($customerId, [
                [
                    'product_sku' => $product['item_code'],
                    'product_name' => $product['item_name'],
                    'quantity' => $quantity,
                    'unit_price' => $product['price']
                ]
            ], "WhatsApp Order - {$customer['name']}");

            // Try to create in Brains
            $this->tryCreateBrainsInvoice($order, $customer);

            // Clear conversation state
            $this->conversationState->clear($customerId);

            // Send confirmation
            return ResponseTemplates::orderConfirmation($lang, [
                'product_name' => $product['item_name'],
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_address' => $customer['address'],
                'quantity' => $quantity,
                'price' => $product['price']
            ]);

        } catch (Exception $e) {
            logMessage("Order creation failed: " . $e->getMessage(), 'ERROR');
            $this->conversationState->clear($customerId);
            return ResponseTemplates::invalidInput($lang);
        }
    }

    /**
     * Handle balance inquiry
     */
    private function handleBalanceInquiry($customer, $lang) {
        if (empty($customer['brains_account_code'])) {
            return ResponseTemplates::accountNotLinked($lang);
        }

        return ResponseTemplates::balanceInfo($lang, $customer);
    }

    /**
     * Handle with AI (only as last resort)
     */
    private function handleWithAI($customer, $message, $lang) {
        $result = $this->claudeAI->processMessage(
            $customer['id'],
            $message,
            $customer
        );

        if ($result['success']) {
            return $result['message'];
        } else {
            return ResponseTemplates::invalidInput($lang);
        }
    }

    /**
     * Try to link customer with Brains account
     */
    private function tryLinkBrainsAccount($customerId, $phone) {
        try {
            $account = $this->brainsAPI->findAccountByPhone($phone);

            if ($account) {
                $this->customerModel->linkBrainsAccount($customerId, $account);
                logMessage("Customer {$customerId} linked to Brains account {$account['AccoCode']}", 'INFO');
            }
        } catch (Exception $e) {
            logMessage("Failed to link Brains account: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Try to create invoice in Brains
     */
    private function tryCreateBrainsInvoice($order, $customer) {
        if (empty($customer['brains_account_code'])) {
            return false;
        }

        try {
            $items = [];
            foreach ($order['items'] as $item) {
                $items[] = [
                    'ItemCode' => $item['product_sku'],
                    'Quantity' => $item['quantity'],
                    'UnitPrice' => $item['unit_price']
                ];
            }

            $result = $this->brainsAPI->createSale([
                'customer_code' => $customer['brains_account_code'],
                'invoice_date' => date('Y-m-d'),
                'items' => $items,
                'notes' => "WhatsApp Order: {$order['order_number']}"
            ]);

            if ($result && isset($result['InvoiceNo'])) {
                $this->orderModel->linkBrainsInvoice($order['id'], $result['InvoiceNo']);
                return true;
            }

        } catch (Exception $e) {
            logMessage("Failed to create Brains invoice: " . $e->getMessage(), 'ERROR');
        }

        return false;
    }

    // Intent detection helpers
    private function isGreeting($message) {
        // Match common greetings in all languages (based on chat analysis)
        return preg_match('/(^hello!?$|^hi+$|^hey$|^hii+$|^مرحبا$|^مرحبا$|^هلا$|^السلام عليكم$|^السلام$|^bonjour!?$|^salut$|^bonsoir$|^yalla$|^akid$)/ui', $message);
    }

    private function isHelpRequest($message) {
        return preg_match('/(^help$|^مساعدة$|^ساعدني$|^aide$)/u', $message);
    }

    private function isProductListRequest($message) {
        return preg_match('/(products|منتجات|كتب|produits|catalogue|catalog)/u', $message);
    }

    private function isBalanceInquiry($message) {
        return preg_match('/(balance|account|رصيد|حساب|solde|compte)/u', $message);
    }

    private function isOrdersRequest($message) {
        return preg_match('/(my orders|show orders|order|orders|طلباتي|طلبي|mes commandes|commande)/u', $message);
    }

    private function isHoursQuery($message) {
        return preg_match('/(hours|open|opening|close|closing|schedule|sa3et|sa3at|maftouh|ma2foul|horaire|heures|ouvert|fermé)/ui', $message);
    }

    private function isLocationQuery($message) {
        return preg_match('/(location|address|where|wen|wein|fein|位置|adresse|localisation|kfarhbab|ghazir)/ui', $message);
    }

    private function isDeliveryQuery($message) {
        return preg_match('/(delivery|shipping|ship|deliver|tewsil|tousil|boussal|livraison|expédition)/ui', $message);
    }

    private function isHowAreYou($message) {
        return preg_match('/(how are you|cava|ca va|ça va|kifak|keefak|kefak|comment allez|kayf 7alak|كيف حالك|كيفك)/ui', $message);
    }

    /**
     * Show customer's orders
     */
    private function showCustomerOrders($customerId, $lang) {
        $orders = $this->orderModel->getByCustomer($customerId, 10);

        if (empty($orders)) {
            $messages = [
                'ar' => "📦 ليس لديك أي طلبات بعد.\n\nاكتب *منتجات* لتصفح المنتجات وإنشاء طلب.",
                'en' => "📦 You don't have any orders yet.\n\nType *products* to browse products and create an order.",
                'fr' => "📦 Vous n'avez pas encore de commandes.\n\nTapez *produits* pour parcourir les produits et créer une commande."
            ];
            return $messages[$lang] ?? $messages['en'];
        }

        // Build orders list
        $header = [
            'ar' => "📦 *طلباتك:*\n\n",
            'en' => "📦 *Your Orders:*\n\n",
            'fr' => "📦 *Vos Commandes:*\n\n"
        ][$lang];

        $message = $header;

        foreach ($orders as $index => $order) {
            $num = $index + 1;
            $orderNum = $order['order_number'];
            $status = $order['status'];
            $total = number_format($order['total_amount'], 0);
            $date = date('Y-m-d', strtotime($order['created_at']));

            // Status emoji (like DHL tracking)
            $statusMap = [
                'pending' => '⏳',
                'confirmed' => '✅',
                'preparing' => '📦',
                'on_the_way' => '🚚',
                'delivered' => '✅',
                'cancelled' => '❌',
                'out_of_stock' => '🚫'
            ];
            $statusEmoji = $statusMap[$status] ?? '📋';

            $message .= "*{$num}.* #{$orderNum}\n";

            // Show items in the order
            if (!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $itemName = $item['product_name'];
                    $qty = $item['quantity'];
                    $message .= "   📦 {$itemName}";
                    if ($qty > 1) {
                        $message .= " (x{$qty})";
                    }
                    $message .= "\n";
                }
            }

            $message .= "   {$statusEmoji} {$status} • {$total} " . CURRENCY . "\n";
            $message .= "   📅 {$date}\n\n";
        }

        $footer = [
            'ar' => "\n➡️ اكتب رقم الطلب لإلغائه (مثال: *1*)\n",
            'en' => "\n➡️ Type order number to cancel it (example: *1*)\n",
            'fr' => "\n➡️ Tapez le numéro de commande pour l'annuler (exemple: *1*)\n"
        ][$lang];

        $message .= $footer;

        // Save state for cancellation
        $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_ORDER_CANCEL, [
            'customer_orders' => $orders
        ]);

        return $message;
    }

    /**
     * Handle order cancellation
     */
    private function handleOrderCancellation($customerId, $message, $lang) {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Check if user wants to cancel
        if (preg_match('/(cancel|no|back|رجوع|لا|annuler|non)/u', $messageLower)) {
            $this->conversationState->clear($customerId);
            return ResponseTemplates::help($lang);
        }

        // Check for order number selection
        if (preg_match('/^\d+$/', trim($message))) {
            $selectedNum = (int)$message;
            $data = $this->conversationState->getData($customerId);
            $orders = $data['customer_orders'] ?? [];

            // Validate selection
            if ($selectedNum < 1 || $selectedNum > count($orders)) {
                return ResponseTemplates::invalidInput($lang);
            }

            $selectedOrder = $orders[$selectedNum - 1];

            // Check if order can be cancelled
            if ($selectedOrder['status'] === 'cancelled') {
                $messages = [
                    'ar' => "❌ هذا الطلب تم إلغاؤه بالفعل.",
                    'en' => "❌ This order is already cancelled.",
                    'fr' => "❌ Cette commande est déjà annulée."
                ];
                $this->conversationState->clear($customerId);
                return $messages[$lang] ?? $messages['en'];
            }

            if ($selectedOrder['status'] === 'delivered' || $selectedOrder['status'] === 'shipped') {
                $messages = [
                    'ar' => "❌ لا يمكن إلغاء هذا الطلب لأنه قيد التوصيل أو تم توصيله.",
                    'en' => "❌ Cannot cancel this order as it's being delivered or already delivered.",
                    'fr' => "❌ Impossible d'annuler cette commande car elle est en cours de livraison ou déjà livrée."
                ];
                $this->conversationState->clear($customerId);
                return $messages[$lang] ?? $messages['en'];
            }

            // Cancel the order
            try {
                $this->orderModel->updateStatus($selectedOrder['id'], 'cancelled');

                $messages = [
                    'ar' => "✅ تم إلغاء الطلب #{$selectedOrder['order_number']} بنجاح!\n\n💰 المبلغ: " . number_format($selectedOrder['total_amount'], 0) . " " . CURRENCY,
                    'en' => "✅ Order #{$selectedOrder['order_number']} cancelled successfully!\n\n💰 Amount: " . number_format($selectedOrder['total_amount'], 0) . " " . CURRENCY,
                    'fr' => "✅ Commande #{$selectedOrder['order_number']} annulée avec succès!\n\n💰 Montant: " . number_format($selectedOrder['total_amount'], 0) . " " . CURRENCY
                ];

                $this->conversationState->clear($customerId);
                return $messages[$lang] ?? $messages['en'];

            } catch (Exception $e) {
                logMessage("Order cancellation failed: " . $e->getMessage(), 'ERROR');
                $this->conversationState->clear($customerId);

                $messages = [
                    'ar' => "⚠️ حدث خطأ أثناء إلغاء الطلب. الرجاء المحاولة مرة أخرى.",
                    'en' => "⚠️ Error cancelling order. Please try again.",
                    'fr' => "⚠️ Erreur lors de l'annulation de la commande. Veuillez réessayer."
                ];
                return $messages[$lang] ?? $messages['en'];
            }
        }

        return ResponseTemplates::invalidInput($lang);
    }

    /**
     * Display products found by AI
     */
    private function displayAIFoundProducts($customerId, $products, $lang) {
        $totalProducts = count($products);
        $totalPages = ceil($totalProducts / self::PRODUCTS_PER_PAGE);
        $page = 1;
        $productsPage = array_slice($products, 0, self::PRODUCTS_PER_PAGE);

        // Save state
        $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_PRODUCT_SELECTION, [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'products_on_page' => $productsPage,
            'all_search_results' => $products
        ]);

        // Add AI indicator
        $aiIndicator = [
            'ar' => "🤖 وجدت هذه المنتجات لك:\n\n",
            'en' => "🤖 I found these products for you:\n\n",
            'fr' => "🤖 J'ai trouvé ces produits pour vous:\n\n"
        ];

        return ($aiIndicator[$lang] ?? $aiIndicator['en']) .
               ResponseTemplates::productList($lang, $productsPage, $page, $totalPages);
    }

    /**
     * Convert Arabic numerals to Western numerals
     * ٠١٢٣٤٥٦٧٨٩ -> 0123456789
     */
    private function convertArabicNumerals($text) {
        $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $westernNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($arabicNumerals, $westernNumerals, $text);
    }

    /**
     * Send product image with details to customer
     */
    private function sendProductImage($phone, $product, $lang, $customerId) {
        try {
            logMessage("sendProductImage: Starting for product {$product['item_code']}", 'DEBUG');

            // Build full image URL
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $imageUrl = 'http://' . $host . $product['image_url'];
            logMessage("sendProductImage: Image URL: $imageUrl", 'DEBUG');

            // Create caption with product details
            $price = number_format($product['price'], 0);
            $stock = $product['stock_quantity'] > 0 ? '✅' : '❌';

            $captions = [
                'ar' => "*{$product['item_name']}*\n\n" .
                        "💰 السعر: {$price} " . CURRENCY . "\n" .
                        "📦 المخزون: {$stock}",
                'en' => "*{$product['item_name']}*\n\n" .
                        "💰 Price: {$price} " . CURRENCY . "\n" .
                        "📦 Stock: {$stock}",
                'fr' => "*{$product['item_name']}*\n\n" .
                        "💰 Prix: {$price} " . CURRENCY . "\n" .
                        "📦 Stock: {$stock}"
            ];

            $caption = $captions[$lang] ?? $captions['en'];
            logMessage("sendProductImage: About to call ProxSMS sendImage", 'DEBUG');

            // Send image with caption
            $result = $this->proxSMS->sendImage($phone, $imageUrl, $caption);
            logMessage("sendProductImage: ProxSMS returned", 'DEBUG');

            if ($result['success']) {
                logMessage("Product image sent successfully", 'INFO');
                // Save sent message using customer ID directly
                logMessage("About to save sent message for customer $customerId", 'DEBUG');
                $this->messageModel->saveSent($customerId,
                                              "[Image: {$product['item_name']}]",
                                              $imageUrl);
                logMessage("Message saved successfully", 'DEBUG');
            } else {
                logMessage("Failed to send product image: " . ($result['error'] ?? 'Unknown'), 'WARNING');
            }

            logMessage("sendProductImage: Exiting function", 'DEBUG');

        } catch (Exception $e) {
            logMessage("Error sending product image: " . $e->getMessage(), 'ERROR');
        }

        logMessage("sendProductImage: After catch block", 'DEBUG');
    }

    /**
     * Handle customer image - analyze and search for matching products
     */
    private function handleImageMessage($customerId, $imageUrl, $lang) {
        try {
            // Use Claude AI to analyze the image
            $result = $this->claudeAI->analyzeImageAndSearch($imageUrl, $lang);

            if (!$result['success']) {
                // If image analysis fails, return friendly message
                $messages = [
                    'ar' => "شكراً على الصورة! 📸\n\nعذراً، لم أتمكن من تحليل الصورة. يرجى إرسال اسم المنتج أو اكتب *منتجات* لرؤية القائمة.",
                    'en' => "Thanks for the image! 📸\n\nSorry, I couldn't analyze the image. Please send the product name or type *products* to see the list.",
                    'fr' => "Merci pour l'image! 📸\n\nDésolé, je n'ai pas pu analyser l'image. Veuillez envoyer le nom du produit ou tapez *produits* pour voir la liste."
                ];
                return $messages[$lang] ?? $messages['en'];
            }

            $description = $result['description'];
            $products = $result['products'];

            if (empty($products)) {
                // No matching products found
                $messages = [
                    'ar' => "📸 لقد رأيت: *{$description}*\n\n❌ عذراً، لم أجد منتجات مطابقة في المخزون.\n\nاكتب *منتجات* لرؤية جميع المنتجات المتاحة.",
                    'en' => "📸 I see: *{$description}*\n\n❌ Sorry, I couldn't find matching products in stock.\n\nType *products* to see all available items.",
                    'fr' => "📸 Je vois: *{$description}*\n\n❌ Désolé, je n'ai pas trouvé de produits correspondants en stock.\n\nTapez *produits* pour voir tous les articles disponibles."
                ];
                return $messages[$lang] ?? $messages['en'];
            }

            // Found matching products!
            $totalProducts = count($products);
            $totalPages = ceil($totalProducts / self::PRODUCTS_PER_PAGE);
            $page = 1;
            $productsPage = array_slice($products, 0, self::PRODUCTS_PER_PAGE);

            // Save state for product selection
            $this->conversationState->set($customerId, ConversationState::STATE_AWAITING_PRODUCT_SELECTION, [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'products_on_page' => $productsPage,
                'all_search_results' => $products,
                'search_query' => $description
            ]);

            // Build response with found products
            $header = [
                'ar' => "📸 لقد رأيت: *{$description}*\n\n✅ وجدت {$totalProducts} منتج(ات) مطابقة:\n\n",
                'en' => "📸 I see: *{$description}*\n\n✅ Found {$totalProducts} matching product(s):\n\n",
                'fr' => "📸 Je vois: *{$description}*\n\n✅ Trouvé {$totalProducts} produit(s) correspondant(s):\n\n"
            ];

            return ($header[$lang] ?? $header['en']) .
                   ResponseTemplates::productList($lang, $productsPage, $page, $totalPages);

        } catch (Exception $e) {
            logMessage("Error handling image message: " . $e->getMessage(), 'ERROR', WEBHOOK_LOG_FILE);
            $messages = [
                'ar' => "شكراً على الصورة! 📸\n\nحدث خطأ في التحليل. يرجى المحاولة مرة أخرى أو اكتب *منتجات* لرؤية القائمة.",
                'en' => "Thanks for the image! 📸\n\nAn error occurred during analysis. Please try again or type *products* to see the list.",
                'fr' => "Merci pour l'image! 📸\n\nUne erreur s'est produite lors de l'analyse. Veuillez réessayer ou tapez *produits* pour voir la liste."
            ];
            return $messages[$lang] ?? $messages['en'];
        }
    }
}
