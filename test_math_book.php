<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database/Database.php';
require_once __DIR__ . '/src/Models/Product.php';

echo "Testing 'math book' search:\n\n";

$product = new Product();
$results = $product->search('math book', 10);

echo "Results: " . count($results) . "\n\n";

if (!empty($results)) {
    foreach ($results as $idx => $p) {
        echo ($idx + 1) . ". " . $p['item_name'] . "\n";
    }
} else {
    echo "No results found.\n";
}
