<?php
/**
 * Refresh License Status
 * Clears license cache and forces fresh validation
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    if (!LICENSE_CHECK_ENABLED) {
        echo json_encode([
            'success' => false,
            'message' => 'License checking is disabled'
        ]);
        exit;
    }

    // Create validator instance
    $validator = new LicenseValidator();

    // Clear cache
    $validator->clearCache();

    // Force fresh validation
    $result = $validator->validate();

    echo json_encode([
        'success' => true,
        'message' => 'License status refreshed successfully',
        'data' => [
            'valid' => $result['valid'],
            'is_trial' => $result['is_trial'] ?? false,
            'is_paid' => $result['is_paid'] ?? false,
            'days_left' => $result['days_left'] ?? 0,
            'expires_at' => $result['expires_at'] ?? 'N/A'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
