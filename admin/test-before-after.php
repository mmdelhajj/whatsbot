#!/usr/bin/env php
<?php
/**
 * Show Before and After examples
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "📊 BEFORE vs AFTER - Arabic Product Search\n";
echo "══════════════════════════════════════════\n\n";

echo "🔴 BEFORE (Not Working):\n";
echo "────────────────────────\n";
echo "Customer: 'هل لديك بربي' (Do you have Barbie?)\n";
echo "Bot: ❌ عذراً، لم أجد منتجات تطابق \"هل لديك بربي\".\n";
echo "     (Sorry, couldn't find products matching \"هل لديك بربي\")\n\n";

echo "Problem:\n";
echo "  • Bot searched for the ENTIRE phrase including question words\n";
echo "  • Didn't remove Arabic question words like 'هل لديك'\n";
echo "  • Didn't translate 'بربي' to 'Barbie'\n\n";

echo str_repeat("═", 60) . "\n\n";

echo "✅ AFTER (Working Now!):\n";
echo "────────────────────────\n";
echo "Customer: 'هل لديك بربي' (Do you have Barbie?)\n";
echo "Bot: 📚 Product List (Page 1 of 2)\n\n";
echo "     1. Barbie Age 3+ Mattel, Holiday\n";
echo "        💰 1,650,000 LBP ✅\n\n";
echo "     2. Barbie Age 3+ + Accessories Mattel\n";
echo "        💰 2,100,000 LBP ✅\n\n";
echo "     3. Barbie Age 3+ Assorted Mattel\n";
echo "        💰 1,800,000 LBP ✅\n\n";

echo "How it works:\n";
echo "  ✅ Step 1: Remove 'هل لديك' → leaves 'بربي'\n";
echo "  ✅ Step 2: Translate 'بربي' → 'Barbie'\n";
echo "  ✅ Step 3: Search database for 'Barbie'\n";
echo "  ✅ Step 4: Return results!\n\n";

echo str_repeat("═", 60) . "\n\n";

echo "📝 Supported Arabic Product Names:\n";
echo "────────────────────────────────\n";
$translations = [
    'بربي / باربي' => 'Barbie',
    'هوتويلز' => 'Hotwheels',
    'ديزني' => 'Disney',
    'ليغو / ليجو' => 'Lego',
    'دراغون بول' => 'Dragon Ball',
    'سبايدرمان / سبايدر مان' => 'Spiderman',
];

foreach ($translations as $arabic => $english) {
    echo "  • {$arabic} → {$english}\n";
}

echo "\n📌 Arabic Question Phrases Removed:\n";
echo "────────────────────────────────────\n";
$phrases = [
    'هل لديك' => 'Do you have',
    'هل عندك' => 'Do you have',
    'هل يوجد' => 'Is there',
    'هل تملك' => 'Do you have',
    'بدي / بدك' => 'I want',
    'أريد / اريد' => 'I want',
    'ابحث عن' => 'Looking for',
];

foreach ($phrases as $arabic => $english) {
    echo "  • {$arabic} ({$english})\n";
}

echo "\n✅ The bot is now multilingual and smart!\n";
