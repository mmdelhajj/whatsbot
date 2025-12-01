<?php
/**
 * School Book Controller
 * Handles the interactive school book ordering flow
 * 3-Level Flow: Schools -> Grades/Classrooms -> Books
 */

class SchoolBookController {
    private $db;
    private $schoolBookService;
    private $conversationState;
    private $customerModel;

    // Conversation states for school books
    const STATE_SCHOOL_BOOKS_START = 'school_books_start';
    const STATE_SELECTING_SCHOOL = 'selecting_school';
    const STATE_SELECTING_GRADE = 'selecting_grade';
    const STATE_VIEWING_BOOKS = 'viewing_books';
    const STATE_SELECTING_BOOKS = 'selecting_books';
    const STATE_CONFIRMING_ORDER = 'confirming_school_order';

    const BOOKS_PER_PAGE = 8;

    // Website URLs for each school (matching website names)
    private $schoolWebsiteUrls = [
        'Antonine Sisters School, Ghazir' => 'https://store.libmemoires.com/antonine-sisters-school-ghazir',
        'Central College Jounieh' => 'https://store.libmemoires.com/central-college-jounieh',
        'Ecole Saint Francois' => 'https://store.libmemoires.com/ecole-saint-francois',
        'Ecole Sja Besançon Kfour' => 'https://store.libmemoires.com/ecole-sja-besan%C3%A7on-kfour',
        'Lycee Franco Libanais Nahr Ibrahim' => 'https://store.libmemoires.com/lycee-franco-libanais-nahr-ibrahim',
        'Lycee Libano Allemand Jounieh' => 'https://store.libmemoires.com/lycee-libano-allemand-jounieh',
        'SSCC Kfarhbab' => 'https://store.libmemoires.com/college-saint-coeur-kfarhbab',
        'School Divers' => 'https://store.libmemoires.com/books',
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->schoolBookService = new SchoolBookService();
        $this->conversationState = new ConversationState();
        $this->customerModel = new Customer();
    }

    /**
     * Main entry point - handle school books flow
     */
    public function handleSchoolBooksFlow($customerId, $message, $lang) {
        $state = $this->conversationState->getState($customerId);
        $stateData = $this->conversationState->getData($customerId);

        // Convert Arabic numerals to Western
        $message = $this->convertArabicNumerals($message);
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Check for cancel/exit command
        if ($this->isCancelCommand($messageLower)) {
            $this->conversationState->clear($customerId);
            return $this->getHelpMessage($lang);
        }

        // Route based on current state
        switch ($state) {
            case self::STATE_SELECTING_SCHOOL:
                return $this->handleSchoolSelection($customerId, $message, $lang, $stateData);

            case self::STATE_SELECTING_GRADE:
                return $this->handleGradeSelection($customerId, $message, $lang, $stateData);

            case self::STATE_VIEWING_BOOKS:
            case self::STATE_SELECTING_BOOKS:
                return $this->handleBookSelection($customerId, $message, $lang, $stateData);

            case self::STATE_CONFIRMING_ORDER:
                return $this->handleOrderConfirmation($customerId, $message, $lang, $stateData);

            default:
                // Start the flow - show school list
                return $this->showSchoolList($customerId, $lang, $message);
        }
    }

    /**
     * Show list of all schools
     * Mode OFF: Just shows school names with website links (no interaction)
     * Mode ON: Full interactive flow (user selects school -> grades -> books)
     */
    private function showSchoolList($customerId, $lang, $originalMessage = null) {
        $schools = $this->schoolBookService->getAllSchools();

        if (empty($schools)) {
            return $this->getNoSchoolsMessage($lang);
        }

        // Check school books mode from settings
        $mode = $this->getSchoolBooksMode();

        if ($mode === 'on') {
            // Full interactive flow - save state and show numbered list
            $this->conversationState->set($customerId, self::STATE_SELECTING_SCHOOL, [
                'schools' => $schools
            ]);
            return $this->formatSchoolListInteractive($schools, $lang);
        } else {
            // Links only mode - clear state and show links
            $this->conversationState->clear($customerId);
            return $this->formatSchoolList($schools, $lang);
        }
    }

    /**
     * Get school books mode from settings
     */
    private function getSchoolBooksMode() {
        $result = $this->db->fetchOne("SELECT setting_value FROM bot_settings WHERE setting_key = 'school_books_mode'");
        return $result ? $result['setting_value'] : 'off';
    }

    /**
     * Handle school selection
     */
    private function handleSchoolSelection($customerId, $message, $lang, $stateData) {
        $schools = $stateData['schools'] ?? [];

        // Check if it's a number selection
        if (preg_match('/^\d+$/', trim($message))) {
            $selectedNum = (int)$message;

            if ($selectedNum < 1 || $selectedNum > count($schools)) {
                return $this->getInvalidSelectionMessage($lang, count($schools));
            }

            $selectedSchool = $schools[$selectedNum - 1]['school_name'];
            return $this->showGradeList($customerId, $lang, $selectedSchool);
        }

        // Try to match school name from text
        $schoolName = $this->schoolBookService->extractSchoolFromMessage($message);
        if ($schoolName) {
            return $this->showGradeList($customerId, $lang, $schoolName);
        }

        return $this->getInvalidSelectionMessage($lang, count($schools));
    }

    /**
     * Show grades/classrooms for selected school
     */
    private function showGradeList($customerId, $lang, $schoolName) {
        $grades = $this->schoolBookService->getGradesBySchool($schoolName);

        if (empty($grades)) {
            return $this->getNoGradesMessage($lang, $schoolName);
        }

        // Save state
        $this->conversationState->set($customerId, self::STATE_SELECTING_GRADE, [
            'school_name' => $schoolName,
            'grades' => $grades
        ]);

        return $this->formatGradeList($schoolName, $grades, $lang);
    }

    /**
     * Handle grade selection
     */
    private function handleGradeSelection($customerId, $message, $lang, $stateData) {
        $schoolName = $stateData['school_name'] ?? '';
        $grades = $stateData['grades'] ?? [];

        // Check for "back" to school list
        if ($this->isBackCommand($message)) {
            return $this->showSchoolList($customerId, $lang);
        }

        // Check if it's a number selection
        if (preg_match('/^\d+$/', trim($message))) {
            $selectedNum = (int)$message;

            if ($selectedNum < 1 || $selectedNum > count($grades)) {
                return $this->getInvalidSelectionMessage($lang, count($grades));
            }

            $selectedGrade = $grades[$selectedNum - 1]['grade_level'];
            return $this->showBookList($customerId, $lang, $schoolName, $selectedGrade);
        }

        return $this->getInvalidSelectionMessage($lang, count($grades));
    }

    /**
     * Show books for selected school and grade
     */
    private function showBookList($customerId, $lang, $schoolName, $gradeLevel, $page = 1) {
        $books = $this->schoolBookService->getBooksBySchoolAndGrade($schoolName, $gradeLevel);
        $totalInfo = $this->schoolBookService->getGradeTotalPrice($schoolName, $gradeLevel);

        if (empty($books)) {
            return $this->getNoBooksMessage($lang, $schoolName, $gradeLevel);
        }

        $totalBooks = count($books);
        $totalPages = ceil($totalBooks / self::BOOKS_PER_PAGE);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::BOOKS_PER_PAGE;
        $booksPage = array_slice($books, $offset, self::BOOKS_PER_PAGE);

        // Save state
        $this->conversationState->set($customerId, self::STATE_VIEWING_BOOKS, [
            'school_name' => $schoolName,
            'grade_level' => $gradeLevel,
            'books' => $books,
            'books_on_page' => $booksPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_price' => $totalInfo['total_price'],
            'selected_books' => []
        ]);

        return $this->formatBookList($schoolName, $gradeLevel, $booksPage, $page, $totalPages, $totalInfo, $lang);
    }

    /**
     * Handle book selection or navigation
     */
    private function handleBookSelection($customerId, $message, $lang, $stateData) {
        $schoolName = $stateData['school_name'] ?? '';
        $gradeLevel = $stateData['grade_level'] ?? '';
        $books = $stateData['books'] ?? [];
        $booksOnPage = $stateData['books_on_page'] ?? [];
        $currentPage = $stateData['current_page'] ?? 1;
        $totalPages = $stateData['total_pages'] ?? 1;

        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Check for "back" to grades
        if ($this->isBackCommand($messageLower)) {
            return $this->showGradeList($customerId, $lang, $schoolName);
        }

        // Check for "next" page
        if ($this->isNextCommand($messageLower)) {
            $nextPage = min($currentPage + 1, $totalPages);
            return $this->showBookList($customerId, $lang, $schoolName, $gradeLevel, $nextPage);
        }

        // Check for "prev" page
        if ($this->isPrevCommand($messageLower)) {
            $prevPage = max($currentPage - 1, 1);
            return $this->showBookList($customerId, $lang, $schoolName, $gradeLevel, $prevPage);
        }

        // Check for "all" - order complete list
        if ($this->isAllCommand($messageLower)) {
            return $this->confirmCompleteListOrder($customerId, $lang, $stateData);
        }

        // Check for number selection (individual book)
        if (preg_match('/^\d+$/', trim($message))) {
            $selectedNum = (int)$message;

            if ($selectedNum < 1 || $selectedNum > count($booksOnPage)) {
                return $this->getInvalidSelectionMessage($lang, count($booksOnPage));
            }

            $selectedBook = $booksOnPage[$selectedNum - 1];
            return $this->confirmSingleBookOrder($customerId, $lang, $selectedBook, $stateData);
        }

        // Check for multiple selections (e.g., "1,3,5" or "1 3 5")
        if (preg_match('/^[\d,\s]+$/', trim($message))) {
            $selections = preg_split('/[,\s]+/', trim($message));
            $validSelections = [];

            foreach ($selections as $sel) {
                $num = (int)$sel;
                if ($num >= 1 && $num <= count($booksOnPage)) {
                    $validSelections[] = $booksOnPage[$num - 1];
                }
            }

            if (!empty($validSelections)) {
                return $this->confirmMultipleBookOrder($customerId, $lang, $validSelections, $stateData);
            }
        }

        return $this->getInvalidSelectionMessage($lang, count($booksOnPage));
    }

    /**
     * Confirm order for complete list
     */
    private function confirmCompleteListOrder($customerId, $lang, $stateData) {
        $schoolName = $stateData['school_name'];
        $gradeLevel = $stateData['grade_level'];
        $books = $stateData['books'];
        $totalPrice = $stateData['total_price'];

        // Save state for confirmation
        $this->conversationState->set($customerId, self::STATE_CONFIRMING_ORDER, [
            'school_name' => $schoolName,
            'grade_level' => $gradeLevel,
            'books_to_order' => $books,
            'total_price' => $totalPrice,
            'order_type' => 'complete_list'
        ]);

        return $this->formatOrderConfirmation($schoolName, $gradeLevel, $books, $totalPrice, $lang, 'complete');
    }

    /**
     * Confirm single book order
     */
    private function confirmSingleBookOrder($customerId, $lang, $book, $stateData) {
        $schoolName = $stateData['school_name'];
        $gradeLevel = $stateData['grade_level'];

        $this->conversationState->set($customerId, self::STATE_CONFIRMING_ORDER, [
            'school_name' => $schoolName,
            'grade_level' => $gradeLevel,
            'books_to_order' => [$book],
            'total_price' => $book['book_price'],
            'order_type' => 'single'
        ]);

        return $this->formatOrderConfirmation($schoolName, $gradeLevel, [$book], $book['book_price'], $lang, 'single');
    }

    /**
     * Confirm multiple book order
     */
    private function confirmMultipleBookOrder($customerId, $lang, $books, $stateData) {
        $schoolName = $stateData['school_name'];
        $gradeLevel = $stateData['grade_level'];

        $totalPrice = array_sum(array_column($books, 'book_price'));

        $this->conversationState->set($customerId, self::STATE_CONFIRMING_ORDER, [
            'school_name' => $schoolName,
            'grade_level' => $gradeLevel,
            'books_to_order' => $books,
            'total_price' => $totalPrice,
            'order_type' => 'multiple'
        ]);

        return $this->formatOrderConfirmation($schoolName, $gradeLevel, $books, $totalPrice, $lang, 'multiple');
    }

    /**
     * Handle order confirmation
     */
    private function handleOrderConfirmation($customerId, $message, $lang, $stateData) {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');

        // Check for cancel
        if ($this->isCancelCommand($messageLower) || $this->isNoCommand($messageLower)) {
            // Return to book list
            return $this->showBookList(
                $customerId,
                $lang,
                $stateData['school_name'],
                $stateData['grade_level']
            );
        }

        // Check for confirm (1 or yes)
        if ($message === '1' || $this->isYesCommand($messageLower)) {
            return $this->createBookOrder($customerId, $lang, $stateData);
        }

        return $this->getConfirmPromptMessage($lang);
    }

    /**
     * Create the book order
     */
    private function createBookOrder($customerId, $lang, $stateData) {
        $schoolName = $stateData['school_name'];
        $gradeLevel = $stateData['grade_level'];
        $books = $stateData['books_to_order'];

        // Prepare items for order
        $items = [];
        foreach ($books as $book) {
            $items[] = [
                'product_id' => $book['product_id'] ?? $book['id'],
                'quantity' => 1,
                'unit_price' => $book['book_price']
            ];
        }

        // Get customer info
        $customer = $this->customerModel->findById($customerId);
        $notes = "WhatsApp Order - {$customer['name']} - {$schoolName} - {$gradeLevel}";

        // Create order
        $order = $this->schoolBookService->createBookOrder(
            $customerId,
            $schoolName,
            $gradeLevel,
            $items,
            $notes
        );

        // Clear conversation state
        $this->conversationState->clear($customerId);

        return $this->formatOrderSuccess($order, $customer, $lang);
    }

    // ============ FORMATTING METHODS ============

    /**
     * Format school list message - Shows only school names with website links (OFF mode)
     * No numbers, no book counts - just school name and link
     */
    private function formatSchoolList($schools, $lang) {
        $headers = [
            'ar' => "📚 *قائمة المدارس المتاحة:*\n\n",
            'en' => "📚 *Available Schools:*\n\n",
            'fr' => "📚 *Écoles disponibles:*\n\n"
        ];

        $message = $headers[$lang] ?? $headers['en'];

        foreach ($schools as $school) {
            $name = $school['school_name'];
            $url = $this->schoolWebsiteUrls[$name] ?? null;

            $message .= "🏫 *{$name}*\n";
            if ($url) {
                $message .= "🌐 {$url}\n";
            }
            $message .= "\n";
        }

        $footers = [
            'ar' => "👆 اضغط على الرابط لعرض الكتب على الموقع",
            'en' => "👆 Click the link to view books on our website",
            'fr' => "👆 Cliquez sur le lien pour voir les livres sur notre site"
        ];

        $message .= $footers[$lang] ?? $footers['en'];

        return $message;
    }

    /**
     * Format school list for interactive mode (ON mode) - numbered list to select (no links)
     */
    private function formatSchoolListInteractive($schools, $lang) {
        $headers = [
            'ar' => "📚 *قائمة المدارس المتاحة:*\n\n",
            'en' => "📚 *Available Schools:*\n\n",
            'fr' => "📚 *Écoles disponibles:*\n\n"
        ];

        $message = $headers[$lang] ?? $headers['en'];

        foreach ($schools as $index => $school) {
            $num = $index + 1;
            $name = $school['school_name'];
            $message .= "*{$num}.* {$name}\n";
        }

        $footers = [
            'ar' => "\n➡️ اكتب رقم المدرسة للاختيار (مثال: *1*)\n❌ اكتب *cancel* للإلغاء",
            'en' => "\n➡️ Type school number to select (example: *1*)\n❌ Type *cancel* to exit",
            'fr' => "\n➡️ Tapez le numéro de l'école (exemple: *1*)\n❌ Tapez *annuler* pour quitter"
        ];

        $message .= $footers[$lang] ?? $footers['en'];

        return $message;
    }

    /**
     * Format grade list message
     */
    private function formatGradeList($schoolName, $grades, $lang) {
        $headers = [
            'ar' => "🏫 *{$schoolName}*\n\n📊 *الصفوف المتاحة:*\n\n",
            'en' => "🏫 *{$schoolName}*\n\n📊 *Available Grades/Classes:*\n\n",
            'fr' => "🏫 *{$schoolName}*\n\n📊 *Niveaux/Classes disponibles:*\n\n"
        ];

        $message = $headers[$lang] ?? $headers['en'];

        foreach ($grades as $index => $grade) {
            $num = $index + 1;
            $gradeName = $grade['grade_level'];

            $message .= "*{$num}.* {$gradeName}\n";
        }

        $footers = [
            'ar' => "\n➡️ اكتب رقم الصف للاختيار (مثال: *1*)\n🔙 اكتب *back* للعودة للمدارس",
            'en' => "\n➡️ Type grade number to select (example: *1*)\n🔙 Type *back* to return to schools",
            'fr' => "\n➡️ Tapez le numéro du niveau (exemple: *1*)\n🔙 Tapez *retour* pour revenir aux écoles"
        ];

        $message .= $footers[$lang] ?? $footers['en'];

        return $message;
    }

    /**
     * Format book list message
     */
    private function formatBookList($schoolName, $gradeLevel, $books, $currentPage, $totalPages, $totalInfo, $lang) {
        $headers = [
            'ar' => "🏫 *{$schoolName}* - *{$gradeLevel}*\n\n📚 *قائمة الكتب:* (صفحة {$currentPage}/{$totalPages})\n\n",
            'en' => "🏫 *{$schoolName}* - *{$gradeLevel}*\n\n📚 *Book List:* (Page {$currentPage}/{$totalPages})\n\n",
            'fr' => "🏫 *{$schoolName}* - *{$gradeLevel}*\n\n📚 *Liste des livres:* (Page {$currentPage}/{$totalPages})\n\n"
        ];

        $message = $headers[$lang] ?? $headers['en'];

        foreach ($books as $index => $book) {
            $num = $index + 1;
            $title = $book['book_title'];
            $price = number_format($book['book_price'], 0);
            $stockQty = $book['stock_quantity'] ?? 0;
            $expectedArrival = $book['expected_arrival'] ?? null;

            $message .= "*{$num}.* {$title}\n";
            $message .= "   💰 {$price} " . CURRENCY;

            // Show stock status
            if ($stockQty > 0) {
                $message .= " ✅";
            } else if ($expectedArrival) {
                // Out of stock but has arrival info
                if ($expectedArrival === '1970-01-01') {
                    // "Coming Soon" - no specific date
                    $comingSoonText = [
                        'ar' => "❌ (قريباً)",
                        'en' => "❌ (coming soon)",
                        'fr' => "❌ (bientôt)"
                    ];
                    $message .= " " . ($comingSoonText[$lang] ?? $comingSoonText['en']);
                } else {
                    // Has specific arrival date
                    $arrivalDate = date('d/m/Y', strtotime($expectedArrival));
                    $arrivingText = [
                        'ar' => "❌ (قادم: {$arrivalDate})",
                        'en' => "❌ (arriving: {$arrivalDate})",
                        'fr' => "❌ (arrivée: {$arrivalDate})"
                    ];
                    $message .= " " . ($arrivingText[$lang] ?? $arrivingText['en']);
                }
            } else {
                $message .= " ❌";
            }
            $message .= "\n\n";
        }

        // Total summary
        $totalPrice = number_format($totalInfo['total_price'], 0);
        $totalBooks = $totalInfo['book_count'];

        $summaries = [
            'ar' => "\n📊 *الإجمالي:* {$totalBooks} كتاب = {$totalPrice} " . CURRENCY . "\n\n",
            'en' => "\n📊 *Total:* {$totalBooks} books = {$totalPrice} " . CURRENCY . "\n\n",
            'fr' => "\n📊 *Total:* {$totalBooks} livres = {$totalPrice} " . CURRENCY . "\n\n"
        ];

        $message .= $summaries[$lang] ?? $summaries['en'];

        // Instructions
        $instructions = [
            'ar' => "➡️ اكتب رقم الكتاب للطلب (مثال: *1*)\n" .
                    "➡️ اكتب أرقام متعددة (مثال: *1,3,5*)\n" .
                    "➡️ اكتب *all* لطلب القائمة كاملة\n",
            'en' => "➡️ Type book number to order (example: *1*)\n" .
                    "➡️ Type multiple numbers (example: *1,3,5*)\n" .
                    "➡️ Type *all* to order complete list\n",
            'fr' => "➡️ Tapez le numéro du livre (exemple: *1*)\n" .
                    "➡️ Tapez plusieurs numéros (exemple: *1,3,5*)\n" .
                    "➡️ Tapez *tout* pour commander la liste complète\n"
        ];

        $message .= $instructions[$lang] ?? $instructions['en'];

        // Pagination
        if ($totalPages > 1) {
            $pagination = [
                'ar' => "📄 *next* للتالي | *prev* للسابق\n",
                'en' => "📄 *next* for next | *prev* for previous\n",
                'fr' => "📄 *suivant* / *précédent*\n"
            ];
            $message .= $pagination[$lang] ?? $pagination['en'];
        }

        $back = [
            'ar' => "🔙 اكتب *back* للعودة للصفوف",
            'en' => "🔙 Type *back* to return to grades",
            'fr' => "🔙 Tapez *retour* pour revenir aux niveaux"
        ];
        $message .= $back[$lang] ?? $back['en'];

        return $message;
    }

    /**
     * Format order confirmation message
     */
    private function formatOrderConfirmation($schoolName, $gradeLevel, $books, $totalPrice, $lang, $type) {
        $headers = [
            'ar' => "✅ *تأكيد الطلب*\n\n🏫 {$schoolName} - {$gradeLevel}\n\n📚 *الكتب المختارة:*\n\n",
            'en' => "✅ *Order Confirmation*\n\n🏫 {$schoolName} - {$gradeLevel}\n\n📚 *Selected Books:*\n\n",
            'fr' => "✅ *Confirmation de commande*\n\n🏫 {$schoolName} - {$gradeLevel}\n\n📚 *Livres sélectionnés:*\n\n"
        ];

        $message = $headers[$lang] ?? $headers['en'];

        // Show up to 10 books, then summary
        $showCount = min(10, count($books));
        for ($i = 0; $i < $showCount; $i++) {
            $book = $books[$i];
            $num = $i + 1;
            $title = $book['book_title'];
            $price = number_format($book['book_price'], 0);
            $message .= "{$num}. {$title} - {$price} " . CURRENCY . "\n";
        }

        if (count($books) > 10) {
            $remaining = count($books) - 10;
            $message .= "... " . ($lang === 'ar' ? "و {$remaining} كتاب آخر" : ($lang === 'fr' ? "et {$remaining} autres livres" : "and {$remaining} more books")) . "\n";
        }

        $totalFormatted = number_format($totalPrice, 0);
        $totals = [
            'ar' => "\n💰 *المبلغ الإجمالي:* {$totalFormatted} " . CURRENCY . " (" . count($books) . " كتاب)\n\n",
            'en' => "\n💰 *Total Amount:* {$totalFormatted} " . CURRENCY . " (" . count($books) . " books)\n\n",
            'fr' => "\n💰 *Montant total:* {$totalFormatted} " . CURRENCY . " (" . count($books) . " livres)\n\n"
        ];

        $message .= $totals[$lang] ?? $totals['en'];

        $confirms = [
            'ar' => "👉 اكتب *1* للتأكيد\n❌ اكتب *no* للإلغاء",
            'en' => "👉 Type *1* to confirm\n❌ Type *no* to cancel",
            'fr' => "👉 Tapez *1* pour confirmer\n❌ Tapez *non* pour annuler"
        ];

        $message .= $confirms[$lang] ?? $confirms['en'];

        return $message;
    }

    /**
     * Format order success message
     */
    private function formatOrderSuccess($order, $customer, $lang) {
        $totalFormatted = number_format($order['total_amount'], 0);
        $bookCount = count($order['items']);

        $messages = [
            'ar' => "🎉 *تم إنشاء طلبك بنجاح!*\n\n" .
                    "🏫 المدرسة: {$order['school_name']}\n" .
                    "📊 الصف: {$order['grade_level']}\n" .
                    "📚 عدد الكتب: {$bookCount}\n" .
                    "💰 المبلغ الإجمالي: {$totalFormatted} " . CURRENCY . "\n\n" .
                    "👤 الاسم: {$customer['name']}\n" .
                    "📱 الهاتف: {$customer['phone']}\n\n" .
                    "سنتواصل معك قريباً لتأكيد التوصيل! 🙏\n\n" .
                    "اكتب *help* للعودة للقائمة الرئيسية",

            'en' => "🎉 *Your order has been created successfully!*\n\n" .
                    "🏫 School: {$order['school_name']}\n" .
                    "📊 Grade: {$order['grade_level']}\n" .
                    "📚 Number of books: {$bookCount}\n" .
                    "💰 Total Amount: {$totalFormatted} " . CURRENCY . "\n\n" .
                    "👤 Name: {$customer['name']}\n" .
                    "📱 Phone: {$customer['phone']}\n\n" .
                    "We will contact you soon to confirm delivery! 🙏\n\n" .
                    "Type *help* to return to main menu",

            'fr' => "🎉 *Votre commande a été créée avec succès!*\n\n" .
                    "🏫 École: {$order['school_name']}\n" .
                    "📊 Niveau: {$order['grade_level']}\n" .
                    "📚 Nombre de livres: {$bookCount}\n" .
                    "💰 Montant total: {$totalFormatted} " . CURRENCY . "\n\n" .
                    "👤 Nom: {$customer['name']}\n" .
                    "📱 Téléphone: {$customer['phone']}\n\n" .
                    "Nous vous contacterons bientôt pour confirmer la livraison! 🙏\n\n" .
                    "Tapez *aide* pour revenir au menu principal"
        ];

        return $messages[$lang] ?? $messages['en'];
    }

    // ============ HELPER METHODS ============

    private function getHelpMessage($lang) {
        return ResponseTemplates::help($lang);
    }

    private function getNoSchoolsMessage($lang) {
        $messages = [
            'ar' => "❌ عذراً، لا توجد مدارس متاحة حالياً.\n\nاكتب *help* للعودة للقائمة الرئيسية.",
            'en' => "❌ Sorry, no schools are currently available.\n\nType *help* to return to main menu.",
            'fr' => "❌ Désolé, aucune école n'est actuellement disponible.\n\nTapez *aide* pour revenir au menu principal."
        ];
        return $messages[$lang] ?? $messages['en'];
    }

    private function getNoGradesMessage($lang, $schoolName) {
        $messages = [
            'ar' => "❌ عذراً، لا توجد صفوف متاحة لـ {$schoolName}.\n\nاكتب *back* للعودة للمدارس.",
            'en' => "❌ Sorry, no grades available for {$schoolName}.\n\nType *back* to return to schools.",
            'fr' => "❌ Désolé, aucun niveau disponible pour {$schoolName}.\n\nTapez *retour* pour revenir aux écoles."
        ];
        return $messages[$lang] ?? $messages['en'];
    }

    private function getNoBooksMessage($lang, $schoolName, $gradeLevel) {
        $messages = [
            'ar' => "❌ عذراً، لا توجد كتب متاحة لـ {$schoolName} - {$gradeLevel}.\n\nاكتب *back* للعودة للصفوف.",
            'en' => "❌ Sorry, no books available for {$schoolName} - {$gradeLevel}.\n\nType *back* to return to grades.",
            'fr' => "❌ Désolé, aucun livre disponible pour {$schoolName} - {$gradeLevel}.\n\nTapez *retour* pour revenir aux niveaux."
        ];
        return $messages[$lang] ?? $messages['en'];
    }

    private function getInvalidSelectionMessage($lang, $maxNum) {
        $messages = [
            'ar' => "❌ اختيار غير صحيح. الرجاء إدخال رقم من 1 إلى {$maxNum}.",
            'en' => "❌ Invalid selection. Please enter a number from 1 to {$maxNum}.",
            'fr' => "❌ Sélection invalide. Veuillez entrer un numéro de 1 à {$maxNum}."
        ];
        return $messages[$lang] ?? $messages['en'];
    }

    private function getConfirmPromptMessage($lang) {
        $messages = [
            'ar' => "👉 اكتب *1* للتأكيد أو *no* للإلغاء",
            'en' => "👉 Type *1* to confirm or *no* to cancel",
            'fr' => "👉 Tapez *1* pour confirmer ou *non* pour annuler"
        ];
        return $messages[$lang] ?? $messages['en'];
    }

    // Command detection helpers
    private function isCancelCommand($message) {
        return preg_match('/^(cancel|الغاء|إلغاء|annuler|خروج|exit|quit)$/ui', $message);
    }

    private function isBackCommand($message) {
        return preg_match('/^(back|رجوع|retour|عودة|السابق)$/ui', $message);
    }

    private function isNextCommand($message) {
        return preg_match('/^(next|التالي|suivant|التالى|more)$/ui', $message);
    }

    private function isPrevCommand($message) {
        return preg_match('/^(prev|previous|السابق|précédent|السابقة)$/ui', $message);
    }

    private function isAllCommand($message) {
        return preg_match('/^(all|الكل|tout|كل|complete|كاملة)$/ui', $message);
    }

    private function isYesCommand($message) {
        return preg_match('/^(yes|نعم|oui|أيوا|ايوا|اه|ok|okay|تمام)$/ui', $message);
    }

    private function isNoCommand($message) {
        return preg_match('/^(no|لا|non|كلا)$/ui', $message);
    }

    private function convertArabicNumerals($text) {
        $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $westernNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($arabicNumerals, $westernNumerals, $text);
    }
}
