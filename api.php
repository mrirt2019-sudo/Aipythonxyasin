<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'yasinpy_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('ADMIN_TELEGRAM', '@YASIN_VIPXIT');

// Telegram Bot Configuration
$telegram_bot_token = "8507470118:AAGXiWwxQyWkdIToAZFZSta2GmLetxos2-A";
$telegram_admin_id = "6808885369";

// Initialize database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            // Fallback to file-based storage if DB fails
            return null;
        }
    }
    return $db;
}

// Initialize file storage
function initFileStorage() {
    $dirs = ['data', 'uploads', 'logs'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }
    
    $files = [
        'data/subscriptions.json' => '{"premium_codes": {}, "users": {}}',
        'data/logins.json' => '[]',
        'data/files.json' => '[]'
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
        }
    }
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && isset($_POST['type'])) {
    $input = $_POST;
}

if (!$input || !isset($input['type'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

initFileStorage();

switch ($input['type']) {
    case 'chat':
        handleChatRequest($input);
        break;
    
    case 'file_upload':
        handleFileUpload();
        break;
    
    case 'login':
        handleLogin($input);
        break;
    
    case 'activate_premium':
        handlePremiumActivation($input);
        break;
    
    default:
        echo json_encode(['error' => 'Unknown request type']);
}

function handleChatRequest($data) {
    $question = $data['message'] ?? '';
    $user = $data['user'] ?? 'anonymous';
    $subscription = $data['subscription'] ?? 'free';
    
    if (empty($question)) {
        echo json_encode(['response' => 'Please enter a question.']);
        return;
    }
    
    // Integrate with DeepSeek AI
    $aiResponse = callDeepSeekAI($question, $subscription);
    
    // Log the conversation
    logConversation($user, $question, $aiResponse);
    
    echo json_encode(['response' => $aiResponse]);
}

function callDeepSeekAI($question, $subscription = 'sk-5628a7296d4f4a94befae15862a5fb11') {
    // This function integrates with DeepSeek AI API
    // You'll need to replace with actual API key and endpoint
    
    $prompt = "You are YASIN.PY, an expert Python AI assistant. ";
    
    if ($subscription === 'premium') {
        $prompt .= "Provide detailed, professional code solutions with explanations. ";
        $prompt .= "Include error handling, best practices, and optimization tips. ";
    } else {
        $prompt .= "Provide helpful but concise answers. ";
    }
    
    $prompt .= "Question: " . $question;
    
    // For now, return simulated responses
    // Replace this with actual API call to DeepSeek
    
    $responses = [
        "I'll help you with that Python question. " . 
        "Based on your query, here's a solution:\n\n" .
        "```python\n# Example solution\n" . 
        "def solve_problem():\n    print('Implement your solution here')\n```\n\n" .
        "This approach uses efficient algorithms and follows Python best practices.",
        
        "Great question about Python programming!\n\n" .
        "For optimal performance, consider:\n" .
        "1. Using list comprehensions\n" .
        "2. Implementing proper error handling\n" .
        "3. Following PEP 8 style guide\n\n" .
        "Here's a code example:\n```python\n# Optimized solution\nresult = [x*2 for x in range(10)]\n```",
        
        "As a Python expert, I recommend:\n\n" .
        "• Using virtual environments\n" .
        "• Writing unit tests\n" .
        "• Documenting your code\n\n" .
        "For premium users, I can provide more advanced solutions including:\n" .
        "- Machine learning integration\n" .
        "- Web scraping techniques\n" .
        "- API development best practices"
    ];
    
    return $responses[array_rand($responses)];
}

function handleFileUpload() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_OK) {
        echo json_encode(['error' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['file'];
    $user = $_POST['user'] ?? 'anonymous';
    $subscription = $_POST['subscription'] ?? 'free';
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'File too large (max 5MB)']);
        return;
    }
    
    // Check file type
    $allowed_types = ['text/x-python', 'text/plain', 'application/json'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['py', 'txt', 'json', 'js', 'html', 'css'];
    
    if (!in_array($extension, $allowed_extensions)) {
        echo json_encode(['error' => 'File type not allowed']);
        return;
    }
    
    // Save file
    $filename = uniqid() . '_' . $file['name'];
    $upload_path = 'uploads/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        echo json_encode(['error' => 'Failed to save file']);
        return;
    }
    
    // Analyze file
    $analysis = analyzeFile($upload_path, $extension);
    
    // Log file upload
    logFileUpload($user, $filename, $file['name'], $analysis);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'original_name' => $file['name'],
        'analysis' => $analysis,
        'message' => 'File analyzed successfully'
    ]);
}

function analyzeFile($filepath, $extension) {
    $content = file_get_contents($filepath);
    $analysis = "**File Analysis Report**\n\n";
    
    switch ($extension) {
        case 'py':
            $analysis .= "**Python File Detected**\n";
            $analysis .= "Lines of code: " . count(explode("\n", $content)) . "\n";
            
            // Basic Python analysis
            if (strpos($content, 'def ') !== false) {
                $analysis .= "Contains functions\n";
            }
            if (strpos($content, 'class ') !== false) {
                $analysis .= "Contains classes\n";
            }
            if (strpos($content, 'import ') !== false) {
                $analysis .= "Has imports\n";
            }
            
            $analysis .= "\n**Recommendations:**\n";
            $analysis .= "• Add docstrings to functions\n";
            $analysis .= "• Use type hints\n";
            $analysis .= "• Add error handling\n";
            break;
            
        case 'js':
            $analysis .= "**JavaScript File Detected**\n";
            $analysis .= "Lines of code: " . count(explode("\n", $content)) . "\n";
            break;
            
        case 'html':
            $analysis .= "**HTML File Detected**\n";
            $analysis .= "Contains " . substr_count($content, '<') . " HTML tags\n";
            break;
            
        default:
            $analysis .= "**Text File Analysis**\n";
            $analysis .= "File size: " . filesize($filepath) . " bytes\n";
            $analysis .= "Lines: " . count(explode("\n", $content)) . "\n";
            $analysis .= "Words: " . str_word_count($content) . "\n";
    }
    
    $analysis .= "\n---\n*Analysis provided by YASIN.PY AI*";
    
    return $analysis;
}

function handleLogin($data) {
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? 'User';
    $provider = $data['provider'] ?? 'unknown';
    $ip = $data['ip'] ?? 'unknown';
    
    // Save login info
    $logins = json_decode(file_get_contents('data/logins.json'), true);
    $logins[] = [
        'email' => $email,
        'name' => $name,
        'provider' => $provider,
        'ip' => $ip,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents('data/logins.json', json_encode($logins, JSON_PRETTY_PRINT));
    
    // Send to Telegram bot
    sendTelegramNotification("📱 New Login\n\n👤 User: $name\n📧 Email: $email\n🌐 Provider: $provider\n📍 IP: $ip\n⏰ Time: " . date('Y-m-d H:i:s'));
    
    echo json_encode(['success' => true, 'message' => 'Login recorded']);
}

function handlePremiumActivation($data) {
    $code = $data['code'] ?? '';
    $user = $data['user'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['error' => 'No code provided']);
        return;
    }
    
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    
    // Check if code exists and is valid
    if (isset($subscriptions['premium_codes'][$code])) {
        $codeData = $subscriptions['premium_codes'][$code];
        
        if ($codeData['used'] === false) {
            // Mark code as used
            $subscriptions['premium_codes'][$code]['used'] = true;
            $subscriptions['premium_codes'][$code]['used_by'] = $user;
            $subscriptions['premium_codes'][$code]['used_at'] = date('Y-m-d H:i:s');
            
            // Add user to premium users
            $subscriptions['users'][$user] = [
                'premium' => true,
                'activated_at' => date('Y-m-d H:i:s'),
                'code_used' => $code
            ];
            
            file_put_contents('data/subscriptions.json', json_encode($subscriptions, JSON_PRETTY_PRINT));
            
            // Send notification to Telegram
            sendTelegramNotification("🎉 Premium Activation\n\n👤 User: $user\n🔑 Code: $code\n⏰ Time: " . date('Y-m-d H:i:s'));
            
            echo json_encode([
                'success' => true,
                'message' => 'Premium activated successfully',
                'user' => $user,
                'subscription' => 'premium'
            ]);
        } else {
            echo json_encode(['error' => 'Code already used']);
        }
    } else {
        echo json_encode(['error' => 'Invalid subscription code']);
    }
}

function logConversation($user, $question, $response) {
    $logFile = 'logs/conversations_' . date('Y-m-d') . '.json';
    $logData = [];
    
    if (file_exists($logFile)) {
        $logData = json_decode(file_get_contents($logFile), true);
    }
    
    $logData[] = [
        'user' => $user,
        'question' => $question,
        'response' => substr($response, 0, 500), // Limit response length
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}

function logFileUpload($user, $filename, $original, $analysis) {
    $logFile = 'data/files.json';
    $logData = json_decode(file_get_contents($logFile), true);
    
    $logData[] = [
        'user' => $user,
        'filename' => $filename,
        'original' => $original,
        'analysis' => $analysis,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}

function sendTelegramNotification($message) {
    global $telegram_bot_token, $telegram_admin_id;
    
    $url = "https://api.telegram.org/bot$telegram_bot_token/sendMessage";
    $data = [
        'chat_id' => $telegram_admin_id,
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