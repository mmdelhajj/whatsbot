#!/usr/bin/env php
<?php
/**
 * Show greeting examples for different scenarios
 */

require_once dirname(__DIR__) . '/config/config.php';

echo "📱 WhatsApp Bot Greeting Examples\n";
echo "═══════════════════════════════════\n\n";

$customerName = "M EL HAJJ";

echo "🔹 SCENARIO 1: Customer messages today (or within 24 hours)\n";
echo "   Customer: 'Hello'\n";
echo "   Bot Response:\n\n";
echo ResponseTemplates::welcome('en', $customerName, false);
echo "\n\n";
echo str_repeat("─", 60) . "\n\n";

echo "🔹 SCENARIO 2: Customer returns after 2 days\n";
echo "   Customer: 'Hi' (last message was 2 days ago)\n";
echo "   Bot Response:\n\n";
echo ResponseTemplates::welcome('en', $customerName, true);
echo "\n\n";
echo str_repeat("─", 60) . "\n\n";

echo "🔹 SCENARIO 3: Customer returns after 1 month\n";
echo "   Customer: 'Hello' (last message was 1 month ago)\n";
echo "   Bot Response:\n\n";
echo ResponseTemplates::welcome('en', $customerName, true);
echo "\n\n";
echo str_repeat("─", 60) . "\n\n";

echo "🔹 SCENARIO 4: Arabic greeting for returning customer\n";
echo "   Customer: 'مرحبا' (last message was 3 days ago)\n";
echo "   Bot Response:\n\n";
echo ResponseTemplates::welcome('ar', $customerName, true);
echo "\n\n";
echo str_repeat("─", 60) . "\n\n";

echo "🔹 SCENARIO 5: French greeting for returning customer\n";
echo "   Customer: 'Bonjour' (last message was 1 week ago)\n";
echo "   Bot Response:\n\n";
echo ResponseTemplates::welcome('fr', $customerName, true);
echo "\n\n";

echo "✅ The bot is now smart!\n";
echo "   • Tracks when customers last messaged\n";
echo "   • Says 'Hello' for recent/new customers\n";
echo "   • Says 'Welcome back' for returning customers (24+ hours)\n";
echo "   • Works in 3 languages: English, Arabic, French\n";
