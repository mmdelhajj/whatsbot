<?php
/**
 * Application Configuration
 * Loads environment variables and defines constants
 */

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    die('.env file not found. Please create it from .env.example');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos($line, '#') === 0) continue;
    if (strpos($line, '=') === false) continue;

    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    if (!getenv($key)) {
        putenv("$key=$value");
    }
}

// Define application constants
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'whatsapp_bot');
define('DB_USER', getenv('DB_USER') ?: 'whatsapp_user');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('BRAINS_API_BASE', getenv('BRAINS_API_BASE') ?: '');
define('API_TIMEOUT_SECONDS', 30);
define('API_RETRY_ATTEMPTS', 3);
define('BRAINS_ITEMS_ENDPOINT', '/items');
define('BRAINS_ACCOUNTS_ENDPOINT', '/accounts');

define('WHATSAPP_API_URL', 'http://proxsms.com/api/send/whatsapp');
define('WHATSAPP_ACCOUNT_ID', getenv('WHATSAPP_ACCOUNT_ID') ?: '');
define('WHATSAPP_SEND_SECRET', getenv('WHATSAPP_SEND_SECRET') ?: '');
define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: '');

define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
define('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');
// Try multiple models in order of preference
define('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'); // Claude 3 Haiku (fastest, most accessible)
define('ANTHROPIC_MAX_TOKENS', 512); // Reduced from 4096 for faster responses
define('AI_ENABLED', !empty(getenv('ANTHROPIC_API_KEY'))); // Enable/disable AI features

define('TIMEZONE', getenv('TIMEZONE') ?: 'Asia/Beirut');
define('CURRENCY', getenv('CURRENCY') ?: 'LBP');
define('STORE_NAME', getenv('STORE_NAME') ?: 'Librarie Memoires');
define('STORE_LOCATION', getenv('STORE_LOCATION') ?: 'Kfarhbab, Ghazir, Lebanon');
define('STORE_LATITUDE', getenv('STORE_LATITUDE') ?: '34.00951559789577');
define('STORE_LONGITUDE', getenv('STORE_LONGITUDE') ?: '35.654434764102675');
define('STORE_PHONE', getenv('STORE_PHONE') ?: '+961 9 123456');
define('STORE_WEBSITE', getenv('STORE_WEBSITE') ?: '');
define('STORE_HOURS', getenv('STORE_HOURS') ?: 'Monday-Saturday 9:00 AM - 7:00 PM');
define('SYNC_INTERVAL_HOURS', 4); // Sync interval in hours

// License Configuration
define('LICENSE_SERVER_URL', getenv('LICENSE_SERVER_URL') ?: 'https://lic.proxpanel.com');
define('LICENSE_KEY', getenv('LICENSE_KEY') ?: '');
define('SITE_DOMAIN', getenv('SITE_DOMAIN') ?: $_SERVER['HTTP_HOST'] ?? 'unknown');
define('LICENSE_CHECK_ENABLED', filter_var(getenv('LICENSE_CHECK_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// Log files
define('WEBHOOK_LOG_FILE', dirname(__DIR__) . '/logs/webhook.log');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Helper function to get environment variables
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

// Helper function to log messages
if (!function_exists('logMessage')) {
    function logMessage($message, $level = 'INFO') {
        $logFile = dirname(__DIR__) . '/logs/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
        error_log($logEntry, 3, $logFile);
    }
}

// Helper function to format dates in configured timezone
if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
        if (empty($datetime)) return '';

        try {
            $dt = new DateTime($datetime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(TIMEZONE));
            return $dt->format($format);
        } catch (Exception $e) {
            // Fallback to regular date formatting
            return date($format, strtotime($datetime));
        }
    }
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php-error.log');

// Autoload classes
spl_autoload_register(function ($class) {
    $directories = [
        dirname(__DIR__) . '/src/Controllers/',
        dirname(__DIR__) . '/src/Models/',
        dirname(__DIR__) . '/src/Services/',
        dirname(__DIR__) . '/src/Utils/',
        dirname(__DIR__) . '/config/'
    ];

    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
