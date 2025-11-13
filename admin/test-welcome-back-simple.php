#!/usr/bin/env php
<?php
/**
 * Simple test for "Welcome Back" greeting logic
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "🧪 Testing Welcome Back Greeting Logic\n";
echo "══════════════════════════════════════\n\n";

// Test the greeting templates directly
echo "📍 Test 1: First-time customer (Hello)\n";
$greeting1 = ResponseTemplates::welcome('en', 'M EL HAJJ', false);
if (strpos($greeting1, 'Hello M EL HAJJ') !== false) {
    echo "   ✅ PASS: 'Hello M EL HAJJ!'\n";
} else {
    echo "   ❌ FAIL\n";
}
echo "\n";

echo "📍 Test 2: Returning customer (Welcome back)\n";
$greeting2 = ResponseTemplates::welcome('en', 'M EL HAJJ', true);
if (strpos($greeting2, 'Welcome back M EL HAJJ') !== false) {
    echo "   ✅ PASS: 'Welcome back M EL HAJJ!'\n";
} else {
    echo "   ❌ FAIL\n";
    echo "   Got: " . substr($greeting2, 0, 100) . "\n";
}
echo "\n";

echo "📍 Test 3: Arabic greeting (returning)\n";
$greeting3 = ResponseTemplates::welcome('ar', 'محمد الحاج', true);
if (strpos($greeting3, 'أهلاً بعودتك محمد الحاج') !== false) {
    echo "   ✅ PASS: Arabic 'Welcome back' works!\n";
} else {
    echo "   ❌ FAIL\n";
}
echo "\n";

echo "📍 Test 4: French greeting (returning)\n";
$greeting4 = ResponseTemplates::welcome('fr', 'M EL HAJJ', true);
if (strpos($greeting4, 'Bon retour M EL HAJJ') !== false) {
    echo "   ✅ PASS: French 'Welcome back' works!\n";
} else {
    echo "   ❌ FAIL\n";
}
echo "\n";

echo "📍 Test 5: Customer with no name\n";
$greeting5 = ResponseTemplates::welcome('en', null, false);
if (strpos($greeting5, 'Hello!') !== false && strpos($greeting5, 'Welcome back') === false) {
    echo "   ✅ PASS: Generic 'Hello!' for no name\n";
} else {
    echo "   ❌ FAIL\n";
}
echo "\n";

echo "✅ All template tests complete!\n\n";

echo "💡 How it works in the bot:\n";
echo "   • Customer messages for the first time or within 24h: 'Hello [Name]!'\n";
echo "   • Customer returns after 24+ hours: 'Welcome back [Name]!'\n";
echo "   • Works in English, Arabic, and French!\n";
