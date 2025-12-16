<?php
header('Content-Type: application/json');

// Initialize storage
function initStorage() {
    if (!is_dir('data')) mkdir('data', 0755, true);
    
    $files = [
        'data/subscriptions.json' => '{"premium_codes": {}, "users": {}}',
        'data/payments.json' => '[]'
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
        }
    }
}

initStorage();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

switch ($input['action']) {
    case 'validate_code':
        validateSubscriptionCode($input['code'] ?? '');
        break;
    
    case 'create_payment':
        createPaymentRecord($input);
        break;
    
    case 'check_user_status':
        checkUserStatus($input['user'] ?? '');
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action']);
}

function validateSubscriptionCode($code) {
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    
    if (isset($subscriptions['premium_codes'][$code])) {
        $codeData = $subscriptions['premium_codes'][$code];
        
        if ($codeData['used'] === false) {
            echo json_encode([
                'valid' => true,
                'code' => $code,
                'message' => 'Code is valid and ready for activation'
            ]);
        } else {
            echo json_encode([
                'valid' => false,
                'error' => 'Code already used',
                'used_by' => $codeData['used_by'] ?? 'Unknown',
                'used_at' => $codeData['used_at'] ?? 'Unknown'
            ]);
        }
    } else {
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid subscription code'
        ]);
    }
}

function createPaymentRecord($data) {
    $payments = json_decode(file_get_contents('data/payments.json'), true);
    
    $payment = [
        'user' => $data['user'] ?? 'anonymous',
        'amount' => $data['amount'] ?? 0,
        'currency' => $data['currency'] ?? 'USD',
        'method' => $data['method'] ?? 'telegram',
        'code' => $data['code'] ?? null,
        'status' => $data['status'] ?? 'pending',
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $payments[] = $payment;
    file_put_contents('data/payments.json', json_encode($payments, JSON_PRETTY_PRINT));
    
    // Send notification to Telegram
    sendTelegramNotification(
        "💳 New Payment\n\n" .
        "👤 User: " . $payment['user'] . "\n" .
        "💰 Amount: $" . $payment['amount'] . "\n" .
        "📝 Method: " . $payment['method'] . "\n" .
        "📍 IP: " . $payment['ip'] . "\n" .
        "⏰ Time: " . $payment['timestamp']
    );
    
    echo json_encode([
        'success' => true,
        'payment_id' => uniqid('pay_'),
        'message' => 'Payment recorded successfully'
    ]);
}

function checkUserStatus($user) {
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    
    $isPremium = isset($subscriptions['users'][$user]) && 
                 $subscriptions['users'][$user]['premium'] === true;
    
    $status = [
        'premium' => $isPremium,
        'user' => $user,
        'activated_at' => $isPremium ? ($subscriptions['users'][$user]['activated_at'] ?? null) : null,
        'code_used' => $isPremium ? ($subscriptions['users'][$user]['code_used'] ?? null) : null
    ];
    
    echo json_encode($status);
}

function sendTelegramNotification($message) {
    $bot_token = "8507470118:AAGXiWwxQyWkdIToAZFZSta2GmLetxos2-A";
    $chat_id = "6808885369";
    
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
?>