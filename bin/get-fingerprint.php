#!/usr/bin/env php
<?php
/**
 * Get Server Fingerprint
 * Run this script to get your server's unique fingerprint for license registration
 *
 * Usage: php bin/get-fingerprint.php
 */

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          WhatsApp Bot - License Fingerprint Tool            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Create temporary instance to get fingerprint
$licenseValidator = new LicenseValidator();
$info = $licenseValidator->getLicenseInfo();

echo "Server Information for License Registration:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "📍 Domain:       " . $info['domain'] . "\n";
echo "🔑 Fingerprint:  " . $info['fingerprint'] . "\n";
echo "🌐 License Server: " . $info['server'] . "\n";
echo "\n";

// Show license status
echo "Current License Status:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

if (empty(LICENSE_KEY)) {
    echo "⚠️  Status: NO LICENSE KEY CONFIGURED\n";
    echo "\n";
    echo "Steps to activate your license:\n";
    echo "1. Send the above Domain and Fingerprint to your license provider\n";
    echo "2. You will receive a LICENSE_KEY\n";
    echo "3. Add the key to your .env file:\n";
    echo "   LICENSE_KEY=your-license-key-here\n";
    echo "4. Enable license checking:\n";
    echo "   LICENSE_CHECK_ENABLED=true\n";
    echo "\n";
} else {
    echo "✅ License Key: " . $info['license_key'] . "\n";

    // Try to validate
    $validation = $licenseValidator->validate();

    if ($validation['valid']) {
        echo "✅ Status: VALID\n";
        if (isset($validation['data']['customer'])) {
            echo "👤 Customer: " . $validation['data']['customer'] . "\n";
        }
        if (isset($validation['data']['expires_at'])) {
            echo "📅 Expires: " . $validation['data']['expires_at'] . "\n";
        }
    } else {
        echo "❌ Status: INVALID\n";
        echo "❌ Error: " . $validation['message'] . "\n";
        echo "\n";
        echo "Please contact your license provider with the above information.\n";
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
