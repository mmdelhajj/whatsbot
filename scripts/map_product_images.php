#!/usr/bin/env php
<?php
/**
 * Map Product Images Script
 * Scans the images folder and maps images to products by SKU
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting product image mapping...\n";

$db = Database::getInstance();
$startTime = microtime(true);

$imagesDir = dirname(__DIR__) . '/images/products/SfitemPicture/';
$webPath = '/images/products/SfitemPicture/';

if (!is_dir($imagesDir)) {
    die("ERROR: Images directory not found: $imagesDir\n");
}

// Get all products
$products = $db->fetchAll("SELECT id, item_code FROM product_info");
echo "Total products in database: " . count($products) . "\n";

$mapped = 0;
$notFound = 0;

foreach ($products as $product) {
    $sku = $product['item_code'];
    $imageUrl = null;

    // Try to find image file for this SKU (jpg or jpeg)
    $possibleImages = [
        $imagesDir . $sku . '.jpg',
        $imagesDir . $sku . '.jpeg',
        $imagesDir . $sku . '.JPG',
        $imagesDir . $sku . '.JPEG'
    ];

    foreach ($possibleImages as $imagePath) {
        if (file_exists($imagePath)) {
            // Convert to web-accessible path
            $imageUrl = $webPath . basename($imagePath);
            break;
        }
    }

    // Also check if there's a folder with images inside
    if (!$imageUrl && is_dir($imagesDir . $sku)) {
        $folderImages = glob($imagesDir . $sku . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
        if (!empty($folderImages)) {
            $imageUrl = $webPath . $sku . '/' . basename($folderImages[0]);
        }
    }

    if ($imageUrl) {
        // Update product with image URL
        $db->update('product_info',
            ['image_url' => $imageUrl],
            'id = :id',
            ['id' => $product['id']]
        );
        $mapped++;

        if ($mapped <= 5) {
            echo "Mapped: SKU=$sku -> $imageUrl\n";
        }
    } else {
        $notFound++;
    }
}

$duration = round(microtime(true) - $startTime, 2);
echo "\n[" . date('Y-m-d H:i:s') . "] Image mapping completed in {$duration} seconds\n";
echo "Mapped: $mapped products\n";
echo "Not found: $notFound products\n";
echo "Total: " . ($mapped + $notFound) . " products processed\n";
