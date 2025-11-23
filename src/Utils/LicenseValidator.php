<?php
/**
 * License Validator with Auto-Registration & Trial Support
 * Automatically registers new installations for 3-day trial
 */

class LicenseValidator {
    private $licenseServer;
    private $domain;
    private $cacheFile;
    private $licenseKeyFile;
    private $cacheExpiry = 3600; // Cache validation for 1 hour
    private $version = '1.0.0'; // Bot version
    private $cachedServerIp = null; // Cache for server IP

    public function __construct() {
        $this->licenseServer = LICENSE_SERVER_URL ?? 'https://lic.proxpanel.com';
        $this->domain = SITE_DOMAIN ?? $_SERVER['HTTP_HOST'] ?? 'unknown';
        $this->cacheFile = __DIR__ . '/../../storage/license_cache.json';
        $this->licenseKeyFile = __DIR__ . '/../../storage/license_key.txt';

        // Ensure storage directory exists
        $storageDir = dirname($this->cacheFile);
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
    }

    /**
     * Validate license - auto-registers if needed
     */
    public function validate() {
        // Get or generate license key
        $licenseKey = $this->getLicenseKey();

        if (!$licenseKey) {
            // First run - auto-register for trial
            $registration = $this->autoRegister();

            if (!$registration['success']) {
                logMessage("❌ LICENSE: Auto-registration failed - {$registration['message']}", 'ERROR', WEBHOOK_LOG_FILE);
                return [
                    'valid' => false,
                    'message' => $registration['message'],
                    'is_trial' => false,
                    'days_left' => 0
                ];
            }

            $licenseKey = $registration['license_key'];
            $this->saveLicenseKey($licenseKey);

            logMessage("✅ LICENSE: Auto-registered successfully - {$licenseKey} (Trial: {$registration['days_left']} days)", 'INFO', WEBHOOK_LOG_FILE);

            return [
                'valid' => true,
                'message' => 'Trial license activated',
                'is_trial' => $registration['installation_type'] === 'trial',
                'is_paid' => $registration['installation_type'] === 'paid',
                'days_left' => $registration['days_left'],
                'expires_at' => $registration['expires_at'],
                'data' => $registration
            ];
        }

        // Check cache first
        $cached = $this->getCachedValidation();
        if ($cached !== null) {
            // Send heartbeat in background (non-blocking)
            $this->sendHeartbeat($licenseKey);
            return $cached;
        }

        // Validate with remote server
        $result = $this->validateRemote($licenseKey);

        // Cache successful validation
        if ($result['valid']) {
            $this->cacheValidation($result);
        }

        // Send heartbeat
        $this->sendHeartbeat($licenseKey);

        return $result;
    }

    /**
     * Auto-register new installation for trial
     */
    private function autoRegister() {
        try {
            $fingerprint = $this->getServerFingerprint();
            $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['SERVER_ADDR'] ?? 'unknown';

            $url = $this->licenseServer . '/api/register.php';

            $postData = http_build_query([
                'domain' => $this->domain,
                'fingerprint' => $fingerprint,
                'ip' => $ip,
                'version' => $this->version
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For self-signed certs

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || !empty($error)) {
                return [
                    'success' => false,
                    'message' => "Cannot connect to license server (HTTP $httpCode): $error"
                ];
            }

            $data = json_decode($response, true);

            if (!$data || !$data['success']) {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Registration failed'
                ];
            }

            return [
                'success' => true,
                'license_key' => $data['data']['license_key'],
                'installation_type' => $data['data']['installation_type'],
                'days_left' => $data['data']['days_left'],
                'expires_at' => $data['data']['expires_at'],
                'is_trial' => $data['data']['installation_type'] === 'trial'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Registration error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate license with remote server
     */
    private function validateRemote($licenseKey) {
        try {
            $fingerprint = $this->getServerFingerprint();
            $serverIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            $url = $this->licenseServer . '/api/validate.php?' . http_build_query([
                'key' => $licenseKey,
                'domain' => $this->domain,
                'fingerprint' => $fingerprint,
                'server_ip' => $serverIp
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || !empty($error)) {
                logMessage("❌ LICENSE ERROR: Failed to connect to license server (HTTP $httpCode): $error", 'ERROR', WEBHOOK_LOG_FILE);

                // If we can't reach server, check if we have a recent cache
                $cached = $this->getCachedValidation(7200); // Allow 2-hour grace period
                if ($cached !== null) {
                    logMessage("⚠️ LICENSE: Using cached validation due to server connectivity issue", 'WARNING', WEBHOOK_LOG_FILE);
                    return $cached;
                }

                return [
                    'valid' => false,
                    'message' => 'Cannot connect to license server',
                    'is_trial' => false,
                    'days_left' => 0
                ];
            }

            $data = json_decode($response, true);

            if (!$data) {
                logMessage("❌ LICENSE ERROR: Invalid response from license server", 'ERROR', WEBHOOK_LOG_FILE);
                return [
                    'valid' => false,
                    'message' => 'Invalid server response',
                    'is_trial' => false,
                    'days_left' => 0
                ];
            }

            if ($data['success']) {
                $isTrial = isset($data['data']['installation_type']) && $data['data']['installation_type'] === 'trial';
                $daysLeft = isset($data['data']['days_left']) ? intval($data['data']['days_left']) : 0;

                logMessage("✅ LICENSE: Valid - Customer: {$data['data']['customer']}, Expires: {$data['data']['expires_at']}, Days Left: $daysLeft", 'INFO', WEBHOOK_LOG_FILE);

                return [
                    'valid' => true,
                    'message' => 'License valid',
                    'is_trial' => $isTrial,
                    'is_paid' => !$isTrial,
                    'days_left' => $daysLeft,
                    'expires_at' => $data['data']['expires_at'] ?? null,
                    'data' => $data['data']
                ];
            } else {
                logMessage("❌ LICENSE: Invalid - {$data['message']}", 'ERROR', WEBHOOK_LOG_FILE);
                return [
                    'valid' => false,
                    'message' => $data['message'],
                    'is_trial' => false,
                    'days_left' => 0
                ];
            }

        } catch (Exception $e) {
            logMessage("❌ LICENSE EXCEPTION: {$e->getMessage()}", 'ERROR', WEBHOOK_LOG_FILE);
            return [
                'valid' => false,
                'message' => 'License validation error',
                'is_trial' => false,
                'days_left' => 0
            ];
        }
    }

    /**
     * Send heartbeat to license server (non-blocking)
     */
    private function sendHeartbeat($licenseKey) {
        try {
            $ip = $this->getServerPublicIP();
            $url = $this->licenseServer . '/api/heartbeat.php';

            $postData = http_build_query([
                'license_key' => $licenseKey,
                'ip' => $ip,
                'version' => $this->version
            ]);

            // Non-blocking curl - don't wait for response
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Very short timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

            curl_exec($ch);
            curl_close($ch);

        } catch (Exception $e) {
            // Ignore heartbeat errors
        }
    }

    /**
     * Get server fingerprint for hardware binding
     */
    private function getServerFingerprint() {
        $factors = [
            php_uname('n'), // Hostname
            php_uname('m'), // Machine type
            @file_get_contents('/etc/machine-id'), // Linux machine ID
            @file_get_contents('/var/lib/dbus/machine-id'), // Alternative machine ID
        ];

        $fingerprint = md5(implode('|', array_filter($factors)));
        return $fingerprint;
    }

    /**
     * Get license key from storage or .env
     */
    private function getLicenseKey() {
        // Check storage file first
        if (file_exists($this->licenseKeyFile)) {
            $key = trim(file_get_contents($this->licenseKeyFile));
            if (!empty($key)) {
                return $key;
            }
        }

        // Check .env as fallback (for manually configured licenses)
        if (defined('LICENSE_KEY') && !empty(LICENSE_KEY)) {
            return LICENSE_KEY;
        }

        return null;
    }

    /**
     * Save license key to storage
     */
    private function saveLicenseKey($licenseKey) {
        @file_put_contents($this->licenseKeyFile, $licenseKey);
        @chmod($this->licenseKeyFile, 0600); // Secure permissions
    }

    /**
     * Get cached validation result
     */
    private function getCachedValidation($maxAge = null) {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents($this->cacheFile), true);
        if (!$cache || !isset($cache['timestamp']) || !isset($cache['result'])) {
            return null;
        }

        $age = time() - $cache['timestamp'];
        $maxAge = $maxAge ?? $this->cacheExpiry;

        if ($age > $maxAge) {
            return null;
        }

        return $cache['result'];
    }

    /**
     * Cache validation result
     */
    private function cacheValidation($result) {
        $cache = [
            'timestamp' => time(),
            'result' => $result
        ];

        @file_put_contents($this->cacheFile, json_encode($cache));
    }

    /**
     * Clear validation cache
     */
    public function clearCache() {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    /**
     * Get license info without validation
     */
    public function getLicenseInfo() {
        $licenseKey = $this->getLicenseKey();

        return [
            'license_key' => $licenseKey ? substr($licenseKey, 0, 12) . '...' : 'Not registered yet',
            'domain' => $this->domain,
            'fingerprint' => $this->getServerFingerprint(),
            'server' => $this->licenseServer
        ];
    }
}
