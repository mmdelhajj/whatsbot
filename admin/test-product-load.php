#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "Testing product loading...\n";
echo "══════════════════════════\n\n";

$productModel = new Product();
$allProducts = $productModel->getAll(1, 50); // page 1, 50 items

echo "Found " . count($allProducts) . " products\n\n";

if (!empty($allProducts)) {
    echo "First 5 products:\n";
    foreach (array_slice($allProducts, 0, 5) as $product) {
        $status = floatval($product['quantity']) > 0 ? 'IN STOCK' : 'OUT OF STOCK';
        echo "- {$product['item_name']} ({$product['item_code']}) - " .
             number_format($product['price'], 0) . " LBP - {$status}\n";
    }
} else {
    echo "❌ No products loaded!\n";
}

echo "\n\nNow testing smart AI search function directly...\n";
echo "═══════════════════════════════════════════════════\n\n";

$ai = new ClaudeAI();
$result = $ai->smartProductSearch(1, 'rouleau', []);

echo "Result: " . print_r($result, true) . "\n";
