<?php
require_once __DIR__ . '/db.php';

// Use env() function from db.php to load credentials
$SMS_CONFIG = [
    'provider' => 'beem',
    'api_key' => env('BEEM_API_KEY', ''),
    'secret_key' => env('BEEM_SECRET_KEY', ''),
    'sender_id' => 'RAPHAEL-TR',
    'base_url' => 'https://apisms.beem.africa/v1/send',
];


// Check delivery status function
function checkDeliveryStatus($messageId)
{
    global $SMS_CONFIG;

    // Use the PUBLIC v1 endpoint for delivery reports
    // https://apisms.beem.africa/public/v1/delivery-reports?request_id=MESSAGE_ID
    $url = "https://apisms.beem.africa/public/v1/delivery-reports?request_id=" . urlencode($messageId);

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => 0, // Relax SSL for local/testing to avoid issues
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($SMS_CONFIG['api_key'] . ':' . $SMS_CONFIG['secret_key']),
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200 || !$response) {
        return ['error' => 'API Request Failed', 'http_code' => $httpCode];
    }

    return json_decode($response, true);
}

try {
    $db = getDB();

    // Select messages that are 'sent' (submitted) and created in the last 48 hours
    // We want to check if they have finalized to FAILED or DELIVERED
    $stmt = $db->query("SELECT id, message_id, status FROM sms_logs WHERE message_id IS NOT NULL AND status = 'sent' AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) ORDER BY id DESC LIMIT 50");
    $logs = $stmt->fetchAll();

    echo "Found " . count($logs) . " pending messages to check.\n";

    foreach ($logs as $log) {
        if (empty($log['message_id']) || strpos($log['message_id'], 'SIMULATED') !== false) {
            continue; // Skip simulated messages
        }

        echo "Checking Message ID: {$log['message_id']}... ";
        $result = checkDeliveryStatus($log['message_id']);

        // Beem Delivery Report Format:
        // { "delivery_report": [ { "dest_addr": "255...", "status": "DELIVERED", "request_id": "...", ... } ] }

        $status = null;

        if (isset($result['delivery_report']) && is_array($result['delivery_report']) && count($result['delivery_report']) > 0) {
            $report = $result['delivery_report'][0];
            $status = strtoupper($report['status'] ?? '');
        } elseif (isset($result['status'])) {
            // Fallback for some response variations
            $status = strtoupper($result['status']);
        }

        echo "Result: " . ($status ?: 'UNKNOWN') . "\n";

        if ($status) {
            $newDbStatus = null;

            // Map Beem Status to DB Status
            // Beem Statuses: DELIVERED, FAILED, REJECTED, SUBMITTED, BUFFERED, EXPIRED

            if (in_array($status, ['FAILED', 'REJECTED', 'EXPIRED'])) {
                $newDbStatus = 'failed';
            } elseif ($status === 'DELIVERED') {
                // Optional: We can update to 'delivered' if we add that enum, 
                // but for now user wants to know if it FAILED. 
                // If it's delivered, 'sent' (as in successfully sent) is technically accurate enough 
                // unless we want to change DB schema.
                // Let's stick to updating failures for now to match current schema constraints ('queued','sent','failed')
                // If we want to strictly say "sent means delivered", we rely on 'sent' being the success state.
                // But if it failed, we MUST mark it failed.

                // If user insisted "Only say sent if truly sent", well, we are here because it IS 'sent' in DB.
                // So we don't need to change it if it is delivered.
            }

            if ($newDbStatus && $newDbStatus !== $log['status']) {
                $upd = $db->prepare("UPDATE sms_logs SET status = ? WHERE id = ?");
                $upd->execute([$newDbStatus, $log['id']]);
                echo " -> Updated DB status to: $newDbStatus\n";
            }
        }

        // Rate limit protection for the loop
        usleep(200000); // 0.2s pause
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
