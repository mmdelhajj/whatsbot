#!/usr/bin/env php
<?php
/**
 * Map local product images to database
 * Scans /images/products/SfitemPicture/ and updates product_info table
 */

require_once dirname(__DIR__) . '/config/config.php';

$db = Database::getInstance();
$imageDir = dirname(__DIR__) . '/images/products/SfitemPicture';

if (!is_dir($imageDir)) {
    echo "Error: Image directory not found: {$imageDir}\n";
    exit(1);
}

echo "Scanning images directory: {$imageDir}\n";

// Get all image files
$imageFiles = [];
$files = scandir($imageDir);
foreach ($files as $file) {
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
        // Extract item code from filename (remove extension)
        $itemCode = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $file);
        $imageFiles[$itemCode] = $file;
    }
}

echo "Found " . count($imageFiles) . " image files\n\n";

// Update products with matching images
$updated = 0;
$notFound = 0;

foreach ($imageFiles as $itemCode => $imageFile) {
    // Check if product exists
    $product = $db->fetchOne(
        "SELECT id, item_code FROM product_info WHERE item_code = ?",
        [$itemCode]
    );

    if ($product) {
        // Update image URL to local path
        $imageUrl = "/images/products/SfitemPicture/{$imageFile}";
        $db->update('product_info', [
            'image_url' => $imageUrl
        ], 'id = :id', ['id' => $product['id']]);

        $updated++;
        if ($updated % 100 == 0) {
            echo "Updated {$updated} products...\n";
        }
    } else {
        $notFound++;
    }
}

echo "\n";
echo "=================================\n";
echo "Image Mapping Complete\n";
echo "=================================\n";
echo "Products updated: {$updated}\n";
echo "Images without matching product: {$notFound}\n";
echo "\n";

// Show sample mapped products
echo "Sample products with images:\n";
$samples = $db->fetchAll("SELECT item_code, item_name, image_url FROM product_info WHERE image_url IS NOT NULL AND image_url != '' LIMIT 5");
foreach ($samples as $sample) {
    echo "- {$sample['item_code']}: {$sample['item_name']}\n";
    echo "  Image: {$sample['image_url']}\n";
}
