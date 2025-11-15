<?php
/**
 * Brains ERP API Integration
 * Handles all communication with Brains ERP system
 */

class BrainsAPI {
    private $baseUrl;
    private $timeout;
    private $retryAttempts;

    public function __construct() {
        $this->baseUrl = BRAINS_API_BASE;
        $this->timeout = API_TIMEOUT_SECONDS;
        $this->retryAttempts = API_RETRY_ATTEMPTS;
    }

    /**
     * Fetch all items/products from Brains
     */
    public function fetchItems() {
        $url = $this->baseUrl . '/items';
        return $this->makeRequest($url);
    }

    /**
     * Fetch customer accounts from Brains
     */
    public function fetchAccounts() {
        $url = $this->baseUrl . '/accounts?type=1&accocode=41110';
        $response = $this->makeRequest($url);

        // Extract Content array from response
        if (isset($response['Success']) && $response['Success'] && isset($response['Content'])) {
            return $response['Content'];
        }

        return [];
    }

    /**
     * Fetch a specific account by code
     */
    public function fetchAccountByCode($accountCode) {
        $accounts = $this->fetchAccounts();

        if (!$accounts || !is_array($accounts)) {
            return null;
        }

        foreach ($accounts as $account) {
            if (isset($account['AccountNumber']) && $account['AccountNumber'] == $accountCode) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Find account by phone number
     */
    public function findAccountByPhone($phone) {
        $accounts = $this->fetchAccounts();

        if (!$accounts || !is_array($accounts)) {
            return null;
        }

        // Normalize phone for comparison
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

        foreach ($accounts as $account) {
            if (isset($account['Telephone'])) {
                $accountPhone = preg_replace('/[^0-9]/', '', $account['Telephone']);

                // Compare last 8 digits (Lebanese mobile numbers)
                if (substr($normalizedPhone, -8) === substr($accountPhone, -8)) {
                    return $account;
                }
            }
        }

        return null;
    }

    /**
     * Fetch sales/invoices from Brains
     */
    public function fetchSales() {
        $url = $this->baseUrl . '/sales?type=1&accocode=41110';
        return $this->makeRequest($url);
    }

    /**
     * Create new sale/invoice in Brains
     */
    public function createSale($saleData) {
        $url = $this->baseUrl . '/sales';

        $data = [
            'CustomerCode' => $saleData['customer_code'],
            'InvoiceDate' => $saleData['invoice_date'] ?? date('Y-m-d'),
            'Items' => $saleData['items'],
            'Notes' => $saleData['notes'] ?? 'Created from WhatsApp Bot'
        ];

        return $this->makeRequest($url, 'POST', $data);
    }

    /**
     * Make HTTP request with retry logic
     */
    private function makeRequest($url, $method = 'GET', $data = null) {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $result = $this->doRequest($url, $method, $data);

                if ($result !== false) {
                    return $result;
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                logMessage("Brains API request failed (attempt " . ($attempt + 1) . "): " . $lastError, 'WARNING');
            }

            $attempt++;

            if ($attempt < $this->retryAttempts) {
                // Exponential backoff: 1s, 2s, 4s
                sleep(pow(2, $attempt - 1));
            }
        }

        logMessage("Brains API request failed after {$this->retryAttempts} attempts: {$lastError}", 'ERROR');
        return false;
    }

    /**
     * Perform actual HTTP request
     */
    private function doRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error {$httpCode}");
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Test connection to Brains API
     */
    public function testConnection() {
        try {
            $items = $this->fetchItems();
            return [
                'success' => is_array($items),
                'item_count' => is_array($items) ? count($items) : 0,
                'message' => is_array($items) ? 'Connection successful' : 'Invalid response'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync products to local database
     */
    public function syncProducts() {
        $startTime = microtime(true);

        try {
            $items = $this->fetchItems();

            if (!is_array($items) || empty($items)) {
                return [
                    'success' => false,
                    'error' => 'No items received from Brains API'
                ];
            }

            $productModel = new Product();
            $result = $productModel->bulkUpsert($items);

            $duration = round(microtime(true) - $startTime, 2);

            // Log sync
            $db = Database::getInstance();
            $db->insert('brains_sync_log', [
                'sync_type' => 'products',
                'records_count' => count($items),
                'records_added' => $result['added'],
                'records_updated' => $result['updated'],
                'status' => 'success',
                'duration_seconds' => $duration
            ]);

            // Update last sync timestamp
            $db->query(
                "INSERT INTO system_settings (setting_key, setting_value)
                 VALUES ('last_products_sync', NOW())
                 ON DUPLICATE KEY UPDATE setting_value = NOW()"
            );

            return [
                'success' => true,
                'total' => count($items),
                'added' => $result['added'],
                'updated' => $result['updated'],
                'duration' => $duration
            ];

        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            // Log failed sync
            $db = Database::getInstance();
            $db->insert('brains_sync_log', [
                'sync_type' => 'products',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_seconds' => $duration
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync customer accounts
     */
    public function syncAccounts() {
        $startTime = microtime(true);

        try {
            $accounts = $this->fetchAccounts();

            if (!is_array($accounts) || empty($accounts)) {
                return [
                    'success' => false,
                    'error' => 'No accounts received from Brains API'
                ];
            }

            $updated = 0;
            $customerModel = new Customer();

            foreach ($accounts as $account) {
                if (!isset($account['Phone']) || empty($account['Phone'])) {
                    continue;
                }

                // Find customer by phone
                $customer = $customerModel->findOrCreateByPhone($account['Phone']);

                if ($customer) {
                    $customerModel->linkBrainsAccount($customer['id'], $account);
                    $updated++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            // Log sync
            $db = Database::getInstance();
            $db->insert('brains_sync_log', [
                'sync_type' => 'accounts',
                'records_count' => count($accounts),
                'records_updated' => $updated,
                'status' => 'success',
                'duration_seconds' => $duration
            ]);

            return [
                'success' => true,
                'total' => count($accounts),
                'updated' => $updated,
                'duration' => $duration
            ];

        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $db = Database::getInstance();
            $db->insert('brains_sync_log', [
                'sync_type' => 'accounts',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_seconds' => $duration
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
