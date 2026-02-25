<?php
// api/test_sms.php

require_once __DIR__ . '/db.php';

// Mock env if needed or just use the one from db.php
// db.php API: env($key, $default)

$SMS_CONFIG = [
    'provider' => 'beem',
    'api_key' => trim(env('BEEM_API_KEY', '')),
    'secret_key' => trim(env('BEEM_SECRET_KEY', '')),
    'sender_id' => 'RAPHAEL-TR', // Assuming this is set in api.php
    'base_url' => 'https://apisms.beem.africa/v1/send',
];

echo "Config Loaded:\n";
echo "API Key Length: " . strlen($SMS_CONFIG['api_key']) . "\n";
echo "Secret Key Length: " . strlen($SMS_CONFIG['secret_key']) . "\n";
echo "Sender ID: " . $SMS_CONFIG['sender_id'] . "\n";


function sendSMS($phone, $message)
{
    global $SMS_CONFIG;

    echo "Attempting to send to $phone...\n";

    // Normalization logic from api.php
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 9) {
        $phone = '255' . $phone;
    } elseif (substr($phone, 0, 1) == '0') {
        $phone = '255' . substr($phone, 1);
    }

    echo "Normalized Phone: $phone\n";

    if (!preg_match('/^255\d{9}$/', $phone)) {
        return ['success' => false, 'error' => 'Invalid phone number format'];
    }

    $postData = [
        'source_addr' => $SMS_CONFIG['sender_id'],
        'encoding' => 0,
        'schedule_time' => '',
        'message' => $message,
        'recipients' => [
            [
                'recipient_id' => '1',
                'dest_addr' => $phone
            ]
        ]
    ];

    $curl = curl_init($SMS_CONFIG['base_url']);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($SMS_CONFIG['api_key'] . ':' . $SMS_CONFIG['secret_key']),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    if ($curlError)
        echo "Curl Error: $curlError\n";

    if ($response === false) {
        return ['success' => false, 'error' => 'Connection failed: ' . $curlError];
    }

    $result = json_decode($response, true);

    // Logic from api.php
    $isSuccessful = $httpCode === 200 &&
        ((isset($result['successful']) && $result['successful'] == true) ||
            (isset($result['code']) && $result['code'] == 100));

    if ($isSuccessful) {
        return ['success' => true, 'message_id' => $result['request_id'] ?? null];
    }

    return ['success' => false, 'error' => json_encode($result)];
}

// Test with a dummy number that is valid format
$result = sendSMS('0712345678', 'Test Message from Debug Script');
print_r($result);
