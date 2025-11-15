#!/usr/bin/env php
<?php
require_once dirname(__DIR__) . '/config/config.php';

echo "Searching for tape/roll products...\n";
echo "═══════════════════════════════════\n\n";

$p = new Product();
$keywords = ['roll', 'tape', 'adhesive', 'scotch', 'ruban', 'bande'];

foreach($keywords as $kw) {
    $results = $p->search($kw, 5);
    if (!empty($results)) {
        echo strtoupper($kw) . " (" . count($results) . " found):\n";
        foreach($results as $r) {
            echo "  - {$r['item_name']} ({$r['item_code']})\n";
        }
        echo "\n";
    }
}

echo "If no products found, add 'rouleau' to translation dictionary\n";
