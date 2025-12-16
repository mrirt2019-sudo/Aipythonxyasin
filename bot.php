<?php
$telegram_token = "8507470118:AAGXiWwxQyWkdIToAZFZSta2GmLetxos2-A";
$admin_id = "6808885369";

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

function sendMessage($chat_id, $text, $keyboard = null) {
    global $telegram_token;
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $url = "https://api.telegram.org/bot$telegram_token/sendMessage";
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function loadSubscriptions() {
    if (!file_exists('data/subscriptions.json')) {
        return ['premium_codes' => [], 'users' => []];
    }
    return json_decode(file_get_contents('data/subscriptions.json'), true);
}

function saveSubscriptions($data) {
    file_put_contents('data/subscriptions.json', json_encode($data, JSON_PRETTY_PRINT));
}

if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $text = $message["text"] ?? '';
    $user_id = $message["from"]["id"];
    $username = $message["from"]["username"] ?? "User" . $user_id;
    
    if ($text === "/start") {
        $welcome = "👋 Welcome to <b>YASIN.PY Bot</b>!\n\n";
        $welcome .= "I'm here to help you manage your YASIN.PY subscription.\n\n";
        $welcome .= "<b>Available Commands:</b>\n";
        $welcome .= "/subscribe - Get a premium subscription\n";
        $welcome .= "/mycode - Check your subscription code\n";
        $welcome .= "/help - Show this help message\n\n";
        $welcome .= "Contact @YASIN_VIPXIT for support.";
        
        sendMessage($chat_id, $welcome);
    }
    
    elseif ($text === "/subscribe") {
        if ($user_id == $admin_id) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎫 Generate 1 Code', 'callback_data' => 'gen_1'],
                        ['text' => '🎫 Generate 5 Codes', 'callback_data' => 'gen_5']
                    ],
                    [
                        ['text' => '👥 View All Codes', 'callback_data' => 'view_codes'],
                        ['text' => '📊 Statistics', 'callback_data' => 'stats']
                    ]
                ]
            ];
            
            sendMessage($chat_id, "🛠️ <b>Admin Subscription Panel</b>\n\nSelect an option:", $keyboard);
        } else {
            $instructions = "💰 <b>Get Premium Subscription</b>\n\n";
            $instructions .= "To get YASIN.PY Premium:\n\n";
            $instructions .= "1. Contact @YASIN_VIPXIT\n";
            $instructions .= "2. Make payment ($9.99/month)\n";
            $instructions .= "3. Receive your unique code\n";
            $instructions .= "4. Enter code on the website\n\n";
            $instructions .= "Premium includes:\n";
            $instructions .= "✓ Unlimited AI requests\n";
            $instructions .= "✓ File uploads & analysis\n";
            $instructions .= "✓ Priority support\n";
            $instructions .= "✓ Code generation\n\n";
            $instructions .= "<i>Contact now to upgrade!</i>";
            
            sendMessage($chat_id, $instructions);
        }
    }
    
    elseif ($text === "/mycode" && $user_id == $admin_id) {
        $subscriptions = loadSubscriptions();
        $codes = [];
        
        foreach ($subscriptions['premium_codes'] as $code => $data) {
            if (!$data['used']) {
                $codes[] = $code;
            }
        }
        
        if (empty($codes)) {
            sendMessage($chat_id, "❌ No available codes. Generate some first.");
        } else {
            $message = "🔑 <b>Available Codes:</b>\n\n";
            foreach ($codes as $code) {
                $message .= "<code>$code</code>\n";
            }
            $message .= "\nTotal: " . count($codes) . " codes available";
            
            sendMessage($chat_id, $message);
        }
    }
    
    elseif ($text === "/help") {
        $help = "🆘 <b>YASIN.PY Bot Help</b>\n\n";
        $help .= "<b>For Users:</b>\n";
        $help .= "/subscribe - Get premium instructions\n";
        $help .= "/help - Show this message\n\n";
        $help .= "<b>For Admin (@YASIN_VIPXIT):</b>\n";
        $help .= "/subscribe - Access admin panel\n";
        $help .= "/mycode - View available codes\n\n";
        $help .= "<b>Support:</b> @YASIN_VIPXIT\n";
        $help .= "<b>Website:</b> Your YASIN.PY URL";
        
        sendMessage($chat_id, $help);
    }
}

if (isset($update["callback_query"])) {
    $callback = $update["callback_query"];
    $data = $callback["data"];
    $chat_id = $callback["message"]["chat"]["id"];
    $user_id = $callback["from"]["id"];
    
    if ($user_id == $admin_id) {
        if (strpos($data, 'gen_') === 0) {
            $count = intval(str_replace('gen_', '', $data));
            $subscriptions = loadSubscriptions();
            
            $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $new_codes = [];
            
            for ($i = 0; $i < $count; $i++) {
                $code = 'YASIN_';
                for ($j = 0; $j < 8; $j++) {
                    $code .= $characters[rand(0, strlen($characters) - 1)];
                }
                
                $subscriptions['premium_codes'][$code] = [
                    'generated' => date('Y-m-d H:i:s'),
                    'used' => false,
                    'used_by' => null,
                    'used_at' => null
                ];
                
                $new_codes[] = $code;
            }
            
            saveSubscriptions($subscriptions);
            
            $message = "✅ <b>Generated $count Codes</b>\n\n";
            foreach ($new_codes as $code) {
                $message .= "<code>$code</code>\n";
            }
            $message .= "\nThese codes are now available for use.";
            
            sendMessage($chat_id, $message);
        }
        
        elseif ($data === 'view_codes') {
            $subscriptions = loadSubscriptions();
            $available = [];
            $used = [];
            
            foreach ($subscriptions['premium_codes'] as $code => $codeData) {
                if ($codeData['used']) {
                    $used[] = $code;
                } else {
                    $available[] = $code;
                }
            }
            
            $message = "📊 <b>Subscription Codes</b>\n\n";
            $message .= "✅ <b>Available:</b> " . count($available) . "\n";
            $message .= "❌ <b>Used:</b> " . count($used) . "\n\n";
            
            if (!empty($available)) {
                $message .= "<b>Available Codes:</b>\n";
                foreach (array_slice($available, 0, 10) as $code) {
                    $message .= "<code>$code</code>\n";
                }
                if (count($available) > 10) {
                    $message .= "... and " . (count($available) - 10) . " more";
                }
            }
            
            sendMessage($chat_id, $message);
        }
        
        elseif ($data === 'stats') {
            $subscriptions = loadSubscriptions();
            
            $total_codes = count($subscriptions['premium_codes']);
            $used_codes = count(array_filter($subscriptions['premium_codes'], function($code) {
                return $code['used'];
            }));
            
            $premium_users = count(array_filter($subscriptions['users'], function($user) {
                return $user['premium'] === true;
            }));
            
            $message = "📈 <b>YASIN.PY Statistics</b>\n\n";
            $message .= "🔑 <b>Codes:</b>\n";
            $message .= "• Total: $total_codes\n";
            $message .= "• Used: $used_codes\n";
            $message .= "• Available: " . ($total_codes - $used_codes) . "\n\n";
            $message .= "👥 <b>Users:</b>\n";
            $message .= "• Premium: $premium_users\n";
            $message .= "• Free: " . (count($subscriptions['users']) - $premium_users) . "\n\n";
            $message .= "💎 <b>Revenue:</b>\n";
            $message .= "• Estimated: $" . ($premium_users * 9.99) . "/month\n\n";
            $message .= "<i>Last updated: " . date('Y-m-d H:i:s') . "</i>";
            
            sendMessage($chat_id, $message);
        }
    }
    
    // Answer callback query
    $url = "https://api.telegram.org/bot$telegram_token/answerCallbackQuery";
    $callback_data = ['callback_query_id' => $callback['id']];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($callback_data)
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}
?>