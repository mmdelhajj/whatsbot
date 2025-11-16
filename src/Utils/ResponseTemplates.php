<?php
/**
 * Multilingual Response Templates
 * Provides predefined responses in Arabic, English, and French
 */

class ResponseTemplates {
    /**
     * Get welcome message
     */
    public static function welcome($lang, $customerName = null, $isReturning = false) {
        // If customer is returning (hasn't messaged in a while), say "Welcome back!"
        if ($isReturning && $customerName) {
            $greeting = [
                'ar' => "أهلاً بعودتك {$customerName}!",
                'en' => "Welcome back {$customerName}!",
                'fr' => "Bon retour {$customerName}!"
            ][$lang];
        } else {
            $greeting = $customerName ? [
                'ar' => "مرحباً {$customerName}!",
                'en' => "Hello {$customerName}!",
                'fr' => "Bonjour {$customerName}!"
            ][$lang] : [
                'ar' => "مرحباً!",
                'en' => "Hello!",
                'fr' => "Bonjour!"
            ][$lang];
        }

        $messages = [
            'ar' => "{$greeting} 👋\n\n" .
                    "أهلاً بك في *" . STORE_NAME . "* 📚\n\n" .
                    "كيف يمكنني مساعدتك اليوم؟\n\n" .
                    "• 📖 اكتب *منتجات* لرؤية الكتب المتاحة\n" .
                    "• 📦 اكتب *طلباتي* لرؤية طلباتك\n" .
                    "• 💰 اكتب *حساب* للاستعلام عن رصيدك\n" .
                    "• ❓ اكتب *مساعدة* لمزيد من المعلومات",

            'en' => "{$greeting} 👋\n\n" .
                    "Welcome to *" . STORE_NAME . "* 📚\n\n" .
                    "How can I help you today?\n\n" .
                    "• 📖 Type *products* to see available books\n" .
                    "• 📦 Type *my orders* to view your orders\n" .
                    "• 💰 Type *account* to check your balance\n" .
                    "• ❓ Type *help* for more information",

            'fr' => "{$greeting} 👋\n\n" .
                    "Bienvenue à *" . STORE_NAME . "* 📚\n\n" .
                    "Comment puis-je vous aider aujourd'hui?\n\n" .
                    "• 📖 Tapez *produits* pour voir les livres disponibles\n" .
                    "• 📦 Tapez *mes commandes* pour voir vos commandes\n" .
                    "• 💰 Tapez *compte* pour vérifier votre solde\n" .
                    "• ❓ Tapez *aide* pour plus d'informations"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Get help message
     */
    public static function help($lang) {
        $messages = [
            'ar' => "📚 *كيف يمكنني مساعدتك؟*\n\n" .
                    "🔍 *للبحث عن كتاب:*\n" .
                    "اكتب: \"منتجات\" لرؤية القائمة\n\n" .
                    "🛒 *لطلب منتج:*\n" .
                    "اختر رقم المنتج من القائمة\n\n" .
                    "📦 *لرؤية طلباتك:*\n" .
                    "اكتب: \"طلباتي\" أو \"طلبي\"\n\n" .
                    "💳 *للاستعلام عن حسابك:*\n" .
                    "اكتب: \"رصيدي\" أو \"حساب\"\n\n" .
                    "📞 *للتواصل:*\n" .
                    STORE_LOCATION,

            'en' => "📚 *How can I help you?*\n\n" .
                    "🔍 *To search for a book:*\n" .
                    "Type: \"products\" to see the list\n\n" .
                    "🛒 *To order a product:*\n" .
                    "Choose a product number from the list\n\n" .
                    "📦 *To view your orders:*\n" .
                    "Type: \"my orders\" or \"orders\"\n\n" .
                    "💳 *To check your account:*\n" .
                    "Type: \"account\" or \"balance\"\n\n" .
                    "📞 *To contact us:*\n" .
                    STORE_LOCATION,

            'fr' => "📚 *Comment puis-je vous aider?*\n\n" .
                    "🔍 *Pour chercher un livre:*\n" .
                    "Tapez: \"produits\" pour voir la liste\n\n" .
                    "🛒 *Pour commander un produit:*\n" .
                    "Choisissez un numéro de produit de la liste\n\n" .
                    "📦 *Pour voir vos commandes:*\n" .
                    "Tapez: \"mes commandes\" ou \"commande\"\n\n" .
                    "💳 *Pour vérifier votre compte:*\n" .
                    "Tapez: \"compte\" ou \"solde\"\n\n" .
                    "📞 *Pour nous contacter:*\n" .
                    STORE_LOCATION
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Get product list message with pagination
     */
    public static function productList($lang, $products, $currentPage, $totalPages) {
        $header = [
            'ar' => "📚 *قائمة المنتجات* (صفحة {$currentPage} من {$totalPages})\n\n",
            'en' => "📚 *Product List* (Page {$currentPage} of {$totalPages})\n\n",
            'fr' => "📚 *Liste des Produits* (Page {$currentPage} de {$totalPages})\n\n"
        ][$lang];

        $message = $header;

        foreach ($products as $index => $product) {
            $num = $index + 1;
            $name = $product['item_name'];
            $price = number_format($product['price'], 0);
            $stock = $product['stock_quantity'] > 0 ? '✅' : '❌';

            $message .= "*{$num}.* {$name}\n";
            $message .= "   💰 {$price} " . CURRENCY . " {$stock}\n\n";
        }

        $footer = [
            'ar' => "➡️ اكتب رقم المنتج للطلب (مثال: *1*)\n",
            'en' => "➡️ Type product number to order (example: *1*)\n",
            'fr' => "➡️ Tapez le numéro du produit pour commander (exemple: *1*)\n"
        ][$lang];

        if ($currentPage < $totalPages) {
            $footer .= [
                'ar' => "📄 اكتب *التالي* للصفحة التالية",
                'en' => "📄 Type *next* for next page",
                'fr' => "📄 Tapez *suivant* pour la page suivante"
            ][$lang];
        }

        $message .= "\n" . $footer;

        return $message;
    }

    /**
     * Ask for customer name
     */
    public static function askName($lang, $productName) {
        $messages = [
            'ar' => "✅ اخترت: *{$productName}*\n\n" .
                    "👤 الرجاء إدخال اسمك الكامل:",

            'en' => "✅ You selected: *{$productName}*\n\n" .
                    "👤 Please enter your full name:",

            'fr' => "✅ Vous avez sélectionné: *{$productName}*\n\n" .
                    "👤 Veuillez entrer votre nom complet:"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Ask for customer email
     */
    public static function askEmail($lang) {
        $messages = [
            'ar' => "📧 الرجاء إدخال بريدك الإلكتروني:",
            'en' => "📧 Please enter your email address:",
            'fr' => "📧 Veuillez entrer votre adresse email:"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Ask for customer address
     */
    public static function askAddress($lang) {
        $messages = [
            'ar' => "📍 الرجاء إدخال عنوانك الكامل للتوصيل:",
            'en' => "📍 Please enter your full delivery address:",
            'fr' => "📍 Veuillez entrer votre adresse de livraison complète:"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Order confirmation
     */
    public static function orderConfirmation($lang, $orderData) {
        $product = $orderData['product_name'];
        $name = $orderData['customer_name'];
        $email = $orderData['customer_email'];
        $address = $orderData['customer_address'];
        $quantity = $orderData['quantity'] ?? 1;
        $unitPrice = number_format($orderData['price'], 0);
        $totalPrice = number_format($orderData['price'] * $quantity, 0);

        $quantityText = $quantity > 1 ? " (x{$quantity})" : "";

        // Email line is optional - only show if provided
        $emailLineAr = !empty($email) ? "📧 *البريد:* {$email}\n" : "";
        $emailLineEn = !empty($email) ? "📧 *Email:* {$email}\n" : "";
        $emailLineFr = !empty($email) ? "📧 *Email:* {$email}\n" : "";

        $messages = [
            'ar' => "✅ *تم إنشاء طلبك بنجاح!*\n\n" .
                    "📦 *المنتج:* {$product}{$quantityText}\n" .
                    "👤 *الاسم:* {$name}\n" .
                    $emailLineAr .
                    "📍 *العنوان:* {$address}\n" .
                    ($quantity > 1 ? "💰 *السعر للقطعة:* {$unitPrice} " . CURRENCY . "\n" : "") .
                    "💰 *المبلغ الإجمالي:* {$totalPrice} " . CURRENCY . "\n\n" .
                    "سنتواصل معك قريباً لتأكيد التوصيل! 🙏",

            'en' => "✅ *Your order has been created successfully!*\n\n" .
                    "📦 *Product:* {$product}{$quantityText}\n" .
                    "👤 *Name:* {$name}\n" .
                    $emailLineEn .
                    "📍 *Address:* {$address}\n" .
                    ($quantity > 1 ? "💰 *Unit Price:* {$unitPrice} " . CURRENCY . "\n" : "") .
                    "💰 *Total:* {$totalPrice} " . CURRENCY . "\n\n" .
                    "We will contact you soon to confirm delivery! 🙏",

            'fr' => "✅ *Votre commande a été créée avec succès!*\n\n" .
                    "📦 *Produit:* {$product}{$quantityText}\n" .
                    "👤 *Nom:* {$name}\n" .
                    $emailLineFr .
                    "📍 *Adresse:* {$address}\n" .
                    ($quantity > 1 ? "💰 *Prix unitaire:* {$unitPrice} " . CURRENCY . "\n" : "") .
                    "💰 *Total:* {$totalPrice} " . CURRENCY . "\n\n" .
                    "Nous vous contacterons bientôt pour confirmer la livraison! 🙏"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Balance inquiry response
     */
    public static function balanceInfo($lang, $customer) {
        $name = $customer['name'] ?? 'N/A';
        $balance = number_format($customer['balance'] ?? 0, 0);
        $creditLimit = number_format($customer['credit_limit'] ?? 0, 0);
        $available = number_format(($customer['credit_limit'] ?? 0) - abs($customer['balance'] ?? 0), 0);

        $messages = [
            'ar' => "💳 *معلومات حسابك:*\n\n" .
                    "👤 الاسم: {$name}\n" .
                    "💰 الرصيد: {$balance} " . CURRENCY . "\n" .
                    "📊 الحد الائتماني: {$creditLimit} " . CURRENCY . "\n" .
                    "✅ المتاح: {$available} " . CURRENCY,

            'en' => "💳 *Your Account Information:*\n\n" .
                    "👤 Name: {$name}\n" .
                    "💰 Balance: {$balance} " . CURRENCY . "\n" .
                    "📊 Credit Limit: {$creditLimit} " . CURRENCY . "\n" .
                    "✅ Available: {$available} " . CURRENCY,

            'fr' => "💳 *Informations sur votre compte:*\n\n" .
                    "👤 Nom: {$name}\n" .
                    "💰 Solde: {$balance} " . CURRENCY . "\n" .
                    "📊 Limite de crédit: {$creditLimit} " . CURRENCY . "\n" .
                    "✅ Disponible: {$available} " . CURRENCY
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Ask for product confirmation
     */
    public static function askProductConfirmation($lang, $productName) {
        $messages = [
            'ar' => "✅ هل هذا ما تحتاجه؟\n\n*{$productName}*\n\n👉 اكتب *1* للتأكيد والمتابعة\n📝 أو ابحث عن منتج آخر",
            'en' => "✅ Is this what you need?\n\n*{$productName}*\n\n👉 Type *1* to confirm and continue\n📝 Or search for another product",
            'fr' => "✅ Est-ce que c'est ce dont vous avez besoin?\n\n*{$productName}*\n\n👉 Tapez *1* pour confirmer et continuer\n📝 Ou cherchez un autre produit"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Ask for quantity
     */
    public static function askQuantity($lang, $productName) {
        $messages = [
            'ar' => "📦 *{$productName}*\n\n" .
                    "كم قطعة تريد؟\n\n" .
                    "👉 اكتب الكمية (مثال: *5*)",
            'en' => "📦 *{$productName}*\n\n" .
                    "How many pieces do you want?\n\n" .
                    "👉 Type the quantity (example: *5*)",
            'fr' => "📦 *{$productName}*\n\n" .
                    "Combien de pièces voulez-vous?\n\n" .
                    "👉 Tapez la quantité (exemple: *5*)"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Not linked to Brains account
     */
    public static function accountNotLinked($lang) {
        $messages = [
            'ar' => "💳 عذراً، حسابك غير مرتبط بنظامنا بعد.\n\nالرجاء التواصل معنا لربط حسابك.",
            'en' => "💳 Sorry, your account is not linked to our system yet.\n\nPlease contact us to link your account.",
            'fr' => "💳 Désolé, votre compte n'est pas encore lié à notre système.\n\nVeuillez nous contacter pour lier votre compte."
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Invalid input
     */
    public static function invalidInput($lang) {
        $messages = [
            'ar' => "❌ عذراً، لم أفهم طلبك.\n\nاكتب *مساعدة* لرؤية الخيارات المتاحة.",
            'en' => "❌ Sorry, I didn't understand your request.\n\nType *help* to see available options.",
            'fr' => "❌ Désolé, je n'ai pas compris votre demande.\n\nTapez *aide* pour voir les options disponibles."
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Product not available
     */
    public static function productNotAvailable($lang) {
        $messages = [
            'ar' => "❌ عذراً، هذا المنتج غير متوفر حالياً.",
            'en' => "❌ Sorry, this product is currently unavailable.",
            'fr' => "❌ Désolé, ce produit est actuellement indisponible."
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Order status change notification
     */
    public static function orderStatusNotification($lang, $orderData, $newStatus) {
        $orderNumber = $orderData['order_number'];
        $totalAmount = number_format($orderData['total_amount'], 0);

        // Status emoji mapping
        $statusEmojis = [
            'pending' => '⏳',
            'confirmed' => '✅',
            'preparing' => '📦',
            'on_the_way' => '🚚',
            'delivered' => '✅',
            'out_of_stock' => '❌',
            'cancelled' => '🚫'
        ];

        $emoji = $statusEmojis[$newStatus] ?? '📋';

        // Status names by language
        $statusNames = [
            'ar' => [
                'pending' => 'قيد الانتظار',
                'confirmed' => 'تم التأكيد',
                'preparing' => 'قيد التحضير',
                'on_the_way' => 'في الطريق',
                'delivered' => 'تم التوصيل',
                'out_of_stock' => 'غير متوفر',
                'cancelled' => 'تم الإلغاء'
            ],
            'en' => [
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'preparing' => 'Preparing',
                'on_the_way' => 'On the Way',
                'delivered' => 'Delivered',
                'out_of_stock' => 'Out of Stock',
                'cancelled' => 'Cancelled'
            ],
            'fr' => [
                'pending' => 'En attente',
                'confirmed' => 'Confirmé',
                'preparing' => 'En préparation',
                'on_the_way' => 'En route',
                'delivered' => 'Livré',
                'out_of_stock' => 'Rupture de stock',
                'cancelled' => 'Annulé'
            ]
        ];

        $statusName = $statusNames[$lang][$newStatus] ?? ucwords(str_replace('_', ' ', $newStatus));

        // Build items list
        $itemsList = '';
        if (!empty($orderData['items'])) {
            foreach ($orderData['items'] as $item) {
                $itemsList .= "   • {$item['product_name']}";
                if ($item['quantity'] > 1) {
                    $itemsList .= " (x{$item['quantity']})";
                }
                $itemsList .= "\n";
            }
        }

        $messages = [
            'ar' => "{$emoji} *تحديث طلبك*\n\n" .
                    "رقم الطلب: *{$orderNumber}*\n" .
                    "الحالة الجديدة: *{$statusName}*\n\n" .
                    "📦 *المنتجات:*\n{$itemsList}\n" .
                    "💰 المبلغ الإجمالي: {$totalAmount} " . CURRENCY . "\n\n" .
                    self::getStatusMessage($newStatus, 'ar'),

            'en' => "{$emoji} *Order Update*\n\n" .
                    "Order Number: *{$orderNumber}*\n" .
                    "New Status: *{$statusName}*\n\n" .
                    "📦 *Products:*\n{$itemsList}\n" .
                    "💰 Total Amount: {$totalAmount} " . CURRENCY . "\n\n" .
                    self::getStatusMessage($newStatus, 'en'),

            'fr' => "{$emoji} *Mise à jour de commande*\n\n" .
                    "Numéro de commande: *{$orderNumber}*\n" .
                    "Nouveau statut: *{$statusName}*\n\n" .
                    "📦 *Produits:*\n{$itemsList}\n" .
                    "💰 Montant total: {$totalAmount} " . CURRENCY . "\n\n" .
                    self::getStatusMessage($newStatus, 'fr')
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    /**
     * Get specific message for each status
     */
    private static function getStatusMessage($status, $lang) {
        $messages = [
            'confirmed' => [
                'ar' => '✅ تم تأكيد طلبك! سنبدأ بتحضيره قريباً.',
                'en' => '✅ Your order has been confirmed! We will start preparing it soon.',
                'fr' => '✅ Votre commande a été confirmée! Nous allons bientôt la préparer.'
            ],
            'preparing' => [
                'ar' => '📦 جاري تحضير طلبك الآن!',
                'en' => '📦 Your order is being prepared now!',
                'fr' => '📦 Votre commande est en cours de préparation!'
            ],
            'on_the_way' => [
                'ar' => '🚚 طلبك في الطريق إليك! سيصل قريباً.',
                'en' => '🚚 Your order is on the way! It will arrive soon.',
                'fr' => '🚚 Votre commande est en route! Elle arrivera bientôt.'
            ],
            'delivered' => [
                'ar' => '✅ تم توصيل طلبك! نتمنى أن تستمتع بمشترياتك. شكراً لتسوقك معنا! 🙏',
                'en' => '✅ Your order has been delivered! We hope you enjoy your purchase. Thank you for shopping with us! 🙏',
                'fr' => '✅ Votre commande a été livrée! Nous espérons que vous apprécierez votre achat. Merci de faire vos achats avec nous! 🙏'
            ],
            'out_of_stock' => [
                'ar' => '❌ عذراً، المنتج غير متوفر حالياً. سنتواصل معك قريباً.',
                'en' => '❌ Sorry, the product is currently unavailable. We will contact you soon.',
                'fr' => '❌ Désolé, le produit est actuellement indisponible. Nous vous contacterons bientôt.'
            ],
            'cancelled' => [
                'ar' => '🚫 تم إلغاء طلبك.',
                'en' => '🚫 Your order has been cancelled.',
                'fr' => '🚫 Votre commande a été annulée.'
            ]
        ];

        return $messages[$status][$lang] ?? '';
    }
}
