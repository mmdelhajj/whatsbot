<?php
/**
 * WhatsApp Webhook Endpoint
 * Receives incoming messages from ProxSMS
 *
 * ProxSMS Webhook Format:
 * [
 *     "type" => "whatsapp",
 *     "secret" => "YOUR_WEBHOOK_SECRET",
 *     "data" => [
 *         "id" => 2,
 *         "wid" => "+639760713666",
 *         "phone" => "+639760666713",
 *         "message" => "Hello World!",
 *         "attachment" => "http://imageurl.com/image.jpg",
 *         "timestamp" => 1645684231
 *     ]
 * ]
 */

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';

// Set headers
header('Content-Type: application/json');

// Get input (ProxSMS sends as POST form data)
$input = $_REQUEST;

// Log incoming webhook
logMessage("Webhook received: " . json_encode($input), 'DEBUG', WEBHOOK_LOG_FILE);

try {
    // Validate webhook secret (ProxSMS format)
    $webhookSecret = env('WEBHOOK_SECRET', '');

    if (!empty($webhookSecret)) {
        $providedSecret = $input['secret'] ?? '';

        if ($providedSecret !== $webhookSecret) {
            logMessage("Invalid webhook secret provided", 'WARNING', WEBHOOK_LOG_FILE);
            http_response_code(403);
            echo json_encode(['error' => 'Invalid secret']);
            exit;
        }
    }

    // Check payload type
    $payloadType = $input['type'] ?? null;

    if ($payloadType !== 'whatsapp') {
        logMessage("Ignoring non-WhatsApp payload type: {$payloadType}", 'INFO', WEBHOOK_LOG_FILE);
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'Not a WhatsApp message']);
        exit;
    }

    // Extract WhatsApp data (ProxSMS format)
    // ProxSMS sends data as nested array
    $data = is_array($input['data'] ?? null) ? $input['data'] : [];

    // ProxSMS WhatsApp format:
    // "phone" => sender phone number
    // "message" => message text
    // "attachment" => optional media URL
    $phone = $data['phone'] ?? null;
    $message = $data['message'] ?? null;
    $attachment = $data['attachment'] ?? null;

    // Validate required fields
    if (!$phone || !$message) {
        logMessage("Missing required fields in webhook data", 'ERROR', WEBHOOK_LOG_FILE);
        http_response_code(400);
        echo json_encode(['error' => 'Missing phone or message']);
        exit;
    }

    // Process the message
    $controller = new MessageController();
    $result = $controller->processIncomingMessage($phone, $message, $attachment);

    if ($result['success']) {
        logMessage("Message processed successfully for customer {$result['customer_id']}", 'INFO', WEBHOOK_LOG_FILE);
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'customer_id' => $result['customer_id']
        ]);
    } else {
        logMessage("Message processing failed: " . ($result['error'] ?? 'Unknown error'), 'ERROR', WEBHOOK_LOG_FILE);
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $result['error'] ?? 'Processing failed'
        ]);
    }

} catch (Exception $e) {
    logMessage("Webhook exception: " . $e->getMessage(), 'ERROR', WEBHOOK_LOG_FILE);
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR', WEBHOOK_LOG_FILE);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Internal server error'
    ]);
}
