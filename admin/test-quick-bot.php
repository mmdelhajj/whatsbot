<?php
/**
 * Quick Bot Flow Test
 * Tests language detection and basic responses
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Quick Bot Flow Test ===\n\n";

// Test 1: Language Detection
echo "1. Testing Language Detection:\n";
$tests = [
    'hello' => 'en',
    'مرحبا' => 'ar',
    'bonjour' => 'fr',
    'I need products' => 'en',
    'أريد منتجات' => 'ar',
    'Je veux des produits' => 'fr'
];

foreach ($tests as $message => $expectedLang) {
    $detected = LanguageDetector::detect($message);
    $status = ($detected === $expectedLang) ? '✅' : '❌';
    echo "   {$status} '{$message}' → {$detected} (expected: {$expectedLang})\n";
}

// Test 2: Response Templates
echo "\n2. Testing Response Templates:\n";
echo "   English Welcome:\n";
echo "   " . str_replace("\n", "\n   ", ResponseTemplates::welcome('en', 'John')) . "\n\n";

echo "   Arabic Welcome:\n";
echo "   " . str_replace("\n", "\n   ", ResponseTemplates::welcome('ar', 'أحمد')) . "\n\n";

echo "   French Welcome:\n";
echo "   " . str_replace("\n", "\n   ", ResponseTemplates::welcome('fr', 'Marie')) . "\n\n";

// Test 3: Conversation State
echo "3. Testing Conversation State:\n";
$state = new ConversationState();
$testCustomerId = 1;

// Set state
$state->set($testCustomerId, ConversationState::STATE_AWAITING_NAME, [
    'language' => 'en',
    'selected_product' => ['item_code' => 'TEST001', 'item_name' => 'Test Book']
]);

$currentState = $state->get($testCustomerId);
echo "   ✅ State saved: {$currentState['last_intent']}\n";
echo "   ✅ Data saved: " . json_encode($currentState['data']) . "\n";

// Clear state
$state->clear($testCustomerId);
$clearedState = $state->getState($testCustomerId);
echo "   ✅ State cleared: {$clearedState}\n";

// Test 4: Product List
echo "\n4. Testing Product List (first 3):\n";
$productModel = new Product();
$products = $productModel->getAllInStock();
$topProducts = array_slice($products, 0, 3);

foreach ($topProducts as $index => $product) {
    $num = $index + 1;
    echo "   {$num}. {$product['item_name']} - " . number_format($product['price'], 0) . " LBP\n";
}

echo "\n   Total products in stock: " . count($products) . "\n";

// Test 5: Simple message flow
echo "\n5. Testing Message Flow (without API calls):\n";
$controller = new MessageController();

echo "   Sending 'hello'...\n";
$result = $controller->processIncomingMessage('9613080203', 'hello');
echo "   " . ($result['success'] ? '✅ Success' : '❌ Failed') . "\n";

if (!$result['success']) {
    echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
}

echo "\n✅ All Quick Tests Complete!\n";
