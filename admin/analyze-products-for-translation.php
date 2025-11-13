#!/usr/bin/env php
<?php
/**
 * Analyze all products and identify words that need translation
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "📊 PRODUCT ANALYSIS FOR TRANSLATION\n";
echo "════════════════════════════════════\n\n";

$db = Database::getInstance();
$products = $db->fetchAll("SELECT item_name FROM product_info ORDER BY item_name");

echo "Total products: " . count($products) . "\n\n";

// Extract unique words from product names
$allWords = [];
foreach ($products as $product) {
    $name = $product['item_name'];
    // Split by spaces and common separators
    $words = preg_split('/[\s,\-\+\(\)\/]+/', strtolower($name));
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 2) { // Ignore very short words
            if (!isset($allWords[$word])) {
                $allWords[$word] = 0;
            }
            $allWords[$word]++;
        }
    }
}

// Sort by frequency
arsort($allWords);

echo "TOP 50 MOST COMMON WORDS:\n";
echo "═════════════════════════\n\n";

$count = 0;
foreach ($allWords as $word => $frequency) {
    if ($count >= 50) break;

    // Skip numbers and codes
    if (is_numeric($word) || preg_match('/^\d/', $word)) continue;

    echo sprintf("%-20s → %d products\n", $word, $frequency);
    $count++;
}

echo "\n\nPRODUCT CATEGORIES FOUND:\n";
echo "═════════════════════════\n\n";

// Common categories to look for
$categories = [
    'pen' => 0, 'pencil' => 0, 'crayon' => 0, 'marker' => 0,
    'notebook' => 0, 'cahier' => 0, 'book' => 0, 'livre' => 0,
    'bag' => 0, 'backpack' => 0, 'case' => 0,
    'tape' => 0, 'glue' => 0, 'scissors' => 0,
    'paint' => 0, 'color' => 0, 'brush' => 0,
    'toy' => 0, 'game' => 0, 'puzzle' => 0,
    'barbie' => 0, 'hotwheels' => 0, 'lego' => 0,
    'eraser' => 0, 'ruler' => 0, 'sharpener' => 0,
    'paper' => 0, 'folder' => 0, 'file' => 0,
];

foreach ($products as $product) {
    $name = strtolower($product['item_name']);
    foreach ($categories as $cat => $count) {
        if (strpos($name, $cat) !== false) {
            $categories[$cat]++;
        }
    }
}

arsort($categories);

foreach ($categories as $category => $count) {
    if ($count > 0) {
        echo sprintf("%-15s → %d products\n", strtoupper($category), $count);
    }
}

echo "\n\n✅ Analysis complete!\n";
echo "Use this data to create comprehensive translations.\n";
