<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration - YOUR API KEY IS HERE
define('DEEPSEEK_API_KEY', 'sk-b1d6821da84349488c7667ca4f67a902');
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');
define('TELEGRAM_BOT_TOKEN', '8507470118:AAGXiWwxQyWkdIToAZFZSta2GmLetxos2-A');
define('TELEGRAM_ADMIN_ID', '6808885369');

// Set PHP settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
set_time_limit(30);

// Initialize storage
function initStorage() {
    $dirs = ['data', 'uploads', 'logs'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: $dir");
            }
        }
    }
    
    $files = [
        'data/subscriptions.json' => '{"premium_codes": {}, "users": {}}',
        'data/logins.json' => '[]',
        'data/files.json' => '[]',
        'data/conversations.json' => '[]'
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            if (!file_put_contents($file, $content)) {
                error_log("Failed to create file: $file");
            }
        }
    }
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Try form data
    $data = $_POST;
}

// If still no data, try GET for testing
if (!$data && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $_GET;
    if (isset($data['message'])) {
        $data = ['type' => 'chat', 'message' => $data['message']];
    }
}

// Initialize storage
initStorage();

// Log all requests for debugging
error_log("Request received: " . print_r($data, true) . " | Method: " . $_SERVER['REQUEST_METHOD']);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'response' => 'No data received',
        'status' => 'error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Handle different request types
$type = $data['type'] ?? 'chat';

try {
    switch ($type) {
        case 'chat':
            handleChatRequest($data);
            break;
        
        case 'file_upload':
            handleFileUpload();
            break;
        
        case 'login':
            handleLogin($data);
            break;
        
        case 'activate_premium':
            handlePremiumActivation($data);
            break;
        
        case 'test':
            echo json_encode([
                'status' => 'success',
                'message' => 'API is working!',
                'timestamp' => date('Y-m-d H:i:s'),
                'api_key_set' => !empty(DEEPSEEK_API_KEY) && DEEPSEEK_API_KEY !== 'your-deepseek-api-key-here'
            ]);
            break;
        
        default:
            echo json_encode([
                'response' => 'Unknown request type. Available: chat, file_upload, login, activate_premium, test',
                'status' => 'error'
            ]);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'response' => 'Internal server error. Please try again later.',
        'status' => 'error',
        'debug' => 'Check error logs'
    ]);
}

function handleChatRequest($data) {
    $question = trim($data['message'] ?? '');
    $user = $data['user'] ?? 'anonymous';
    $subscription = $data['subscription'] ?? 'free';
    
    if (empty($question)) {
        echo json_encode([
            'response' => 'Please enter your question about Python programming.',
            'status' => 'error'
        ]);
        return;
    }
    
    // Log the question
    error_log("Chat request from $user: " . substr($question, 0, 100));
    
    // Call DeepSeek AI with retry mechanism
    $aiResponse = callDeepSeekAIWithRetry($question, $subscription);
    
    // Log conversation
    logConversation($user, $question, $aiResponse);
    
    echo json_encode([
        'response' => $aiResponse,
        'status' => 'success',
        'tokens_used' => strlen($aiResponse) / 4, // Estimate
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function callDeepSeekAIWithRetry($question, $subscription = 'free', $retryCount = 2) {
    for ($i = 0; $i <= $retryCount; $i++) {
        try {
            $response = callDeepSeekAI($question, $subscription);
            if ($response && !empty(trim($response))) {
                return $response;
            }
        } catch (Exception $e) {
            error_log("DeepSeek attempt $i failed: " . $e->getMessage());
            if ($i === $retryCount) {
                // Last attempt failed, use fallback
                return generateSmartResponse($question, $subscription);
            }
            // Wait before retry
            usleep(500000 * ($i + 1)); // 0.5s, 1s, etc.
        }
    }
    return generateSmartResponse($question, $subscription);
}

function callDeepSeekAI($question, $subscription = 'free') {
    $system_prompt = "You are YASIN.PY AI Assistant, an expert Python programming assistant created by @YASIN_VIPXIT. ";
    $system_prompt .= "You help users with Python code, debugging, projects, and programming concepts. ";
    $system_prompt .= "Always format code examples in markdown code blocks with python syntax. ";
    $system_prompt .= "Be helpful, concise, and professional. ";
    $system_prompt .= "Website: YASIN.PY | Developer: @YASIN_VIPXIT | Copyright 2023-2026";
    
    $max_tokens = $subscription === 'premium' ? 2000 : 1000;
    
    $messages = [
        [
            'role' => 'system',
            'content' => $system_prompt
        ],
        [
            'role' => 'user',
            'content' => $question
        ]
    ];
    
    $post_data = [
        'model' => 'deepseek-chat',
        'messages' => $messages,
        'max_tokens' => $max_tokens,
        'temperature' => 0.7,
        'stream' => false
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => DEEPSEEK_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($post_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . DEEPSEEK_API_KEY,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'YASIN.PY-AI-Assistant/1.0'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("DeepSeek API Response - HTTP: $http_code | Error: $curl_error");
    
    if ($response === false) {
        throw new Exception("CURL Error: $curl_error");
    }
    
    $data = json_decode($response, true);
    
    if ($http_code !== 200) {
        error_log("DeepSeek API Error Response: " . print_r($data, true));
        throw new Exception("API returned HTTP $http_code");
    }
    
    if (isset($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    } elseif (isset($data['error']['message'])) {
        throw new Exception("API Error: " . $data['error']['message']);
    } else {
        error_log("Unexpected API Response: " . print_r($data, true));
        throw new Exception("Unexpected API response format");
    }
}

function generateSmartResponse($question, $subscription) {
    $question_lower = strtolower($question);
    
    // Enhanced Python responses
    $python_responses = [
        "Here's a Python solution for you:\n\n```python\n# Python solution for: " . substr($question, 0, 40) . "\ndef solve():\n    \"\"\"Solution implementation\"\"\"\n    # Your code here\n    return \"Solution ready\"\n\nif __name__ == \"__main__\":\n    result = solve()\n    print(f\"Result: {result}\")\n```\n\nThis follows Python best practices with proper structure.",
        
        "For Python development, consider this approach:\n\n```python\nimport requests\nimport json\nfrom typing import Optional, Dict\n\ndef fetch_data(url: str) -> Optional[Dict]:\n    \"\"\"Fetch and parse JSON data from URL\"\"\"\n    try:\n        response = requests.get(url, timeout=10)\n        response.raise_for_status()\n        return response.json()\n    except requests.RequestException as e:\n        print(f\"Request failed: {e}\")\n        return None\n\n# Usage\ndata = fetch_data(\"https://api.example.com/data\")\nif data:\n    print(f\"Data received: {len(data)} items\")\n```\n\nIncludes error handling and type hints.",
        
        "Python code example with modern features:\n\n```python\n# Using f-strings and list comprehensions\nnames = [\"Alice\", \"Bob\", \"Charlie\"]\ngreetings = [f\"Hello, {name}!\" for name in names]\n\n# Dictionary comprehension\nname_lengths = {name: len(name) for name in names}\n\n# Enumerate for index\nfor idx, name in enumerate(names, 1):\n    print(f\"{idx}. {name} has {len(name)} letters\")\n\nprint(f\"All greetings: {greetings}\")\nprint(f\"Name lengths: {name_lengths}\")\n```\n\nUses Python 3.6+ features efficiently.",
        
        "Best practices for Python functions:\n\n```python\nfrom typing import List, Tuple\nimport logging\n\nlogging.basicConfig(level=logging.INFO)\nlogger = logging.getLogger(__name__)\n\ndef process_items(items: List[str]) -> Tuple[List[str], int]:\n    \"\"\"\n    Process list of items and return results.\n    \n    Args:\n        items: List of strings to process\n        \n    Returns:\n        Tuple of (processed_items, count)\n    \"\"\"\n    if not items:\n        logger.warning(\"Empty items list provided\")\n        return [], 0\n    \n    processed = [item.strip().upper() for item in items if item.strip()]\n    return processed, len(processed)\n\n# Example usage\nitems = [\" python \", \"\", \"code\", \"  AI  \"]\nresult, count = process_items(items)\nprint(f\"Processed {count} items: {result}\")\n```\n\nIncludes logging, type hints, and documentation."
    ];
    
    // Check for specific topics
    if (strpos($question_lower, 'error') !== false || strpos($question_lower, 'exception') !== false) {
        return "**Debugging Python Errors:**\n\n1. **Read the traceback** - Start from the bottom\n2. **Check line numbers** - The error usually points to the problem\n3. **Common errors:**\n   - `NameError`: Variable not defined\n   - `TypeError`: Wrong data type\n   - `SyntaxError`: Code structure issue\n   - `IndentationError`: Wrong indentation\n\nExample error handling:\n\n```python\ntry:\n    # Your code here\n    result = 10 / int(input(\"Enter number: \"))\nexcept ValueError:\n    print(\"Please enter a valid number\")\nexcept ZeroDivisionError:\n    print(\"Cannot divide by zero\")\nexcept Exception as e:\n    print(f\"Unexpected error: {e}\")\nelse:\n    print(f\"Result: {result}\")\nfinally:\n    print(\"Execution complete\")\n```";
    }
    
    if (strpos($question_lower, 'web') !== false || strpos($question_lower, 'flask') !== false || strpos($question_lower, 'django') !== false) {
        return "**Python Web Development:**\n\n```python\n# Flask web application\nfrom flask import Flask, render_template, request, jsonify\n\napp = Flask(__name__)\n\n@app.route('/')\ndef home():\n    return render_template('index.html', title='YASIN.PY')\n\n@app.route('/api/chat', methods=['POST'])\ndef chat_api():\n    data = request.get_json()\n    message = data.get('message', '')\n    \n    # Process message here\n    response = {\n        'status': 'success',\n        'reply': f'Received: {message}',\n        'timestamp': datetime.now().isoformat()\n    }\n    \n    return jsonify(response)\n\n@app.errorhandler(404)\ndef not_found(error):\n    return jsonify({'error': 'Not found'}), 404\n\nif __name__ == '__main__':\n    app.run(debug=True, host='0.0.0.0', port=5000)\n```\n\nFor Django, FastAPI, or specific requirements, ask for more details!";
    }
    
    if (strpos($question_lower, 'data') !== false || strpos($question_lower, 'pandas') !== false) {
        return "**Data Processing with Python:**\n\n```python\nimport pandas as pd\nimport numpy as np\n\n# Create sample data\ndata = {\n    'Name': ['Alice', 'Bob', 'Charlie', 'Diana'],\n    'Age': [25, 30, 35, 28],\n    'Score': [85.5, 92.0, 78.5, 95.0]\n}\n\ndf = pd.DataFrame(data)\nprint(\"DataFrame:\")\nprint(df)\nprint(\"\\nStatistics:\")\nprint(df.describe())\n\n# Filter data\nadults = df[df['Age'] >= 30]\nprint(f\"\\nAdults (Age >= 30):\\n{adults}\")\n\n# Add calculated column\ndf['Score_Adjusted'] = df['Score'] * 1.1\nprint(f\"\\nWith adjusted scores:\\n{df}\")\n```\n\nPandas is great for data analysis and manipulation.";
    }
    
    // Default response
    $response = $python_responses[array_rand($python_responses)];
    
    // Add branding and upgrade message
    $response .= "\n\n---\n";
    $response .= "**🤖 YASIN.PY AI Assistant** | ";
    $response .= "**💎 Premium:** Unlimited requests, file analysis, priority support | ";
    $response .= "**📞 Contact:** @YASIN_VIPXIT | ";
    $response .= "**© 2023-2026 All rights reserved**";
    
    if ($subscription === 'free') {
        $response .= "\n\n*✨ Upgrade to Premium for advanced features and dedicated support!*";
    }
    
    return $response;
}

function handleFileUpload() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_OK) {
        echo json_encode([
            'success' => false,
            'error' => 'No file uploaded or upload error: ' . $_FILES['file']['error'] ?? 'unknown'
        ]);
        return;
    }
    
    $file = $_FILES['file'];
    $user = $_POST['user'] ?? 'anonymous';
    $subscription = $_POST['subscription'] ?? 'free';
    
    // Check file size
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large (max 5MB)']);
        return;
    }
    
    // Check file type
    $allowed_extensions = ['py', 'txt', 'json', 'js', 'html', 'css', 'md', 'csv'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
        echo json_encode([
            'success' => false, 
            'error' => 'File type .' . $extension . ' not allowed. Allowed: ' . implode(', ', $allowed_extensions)
        ]);
        return;
    }
    
    // Create uploads directory if not exists
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }
    
    // Generate safe filename
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $filename = time() . '_' . $safe_name;
    $upload_path = 'uploads/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
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
        'message' => 'File uploaded and analyzed successfully!',
        'download_url' => 'uploads/' . $filename,
        'file_size' => $file['size'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function analyzeFile($filepath, $extension) {
    if (!file_exists($filepath)) {
        return "File not found for analysis.";
    }
    
    $content = file_get_contents($filepath);
    $filesize = filesize($filepath);
    $lines = count(explode("\n", $content));
    
    $analysis = "## 📊 File Analysis Report\n\n";
    $analysis .= "**📄 File:** " . basename($filepath) . "\n";
    $analysis .= "**📦 Size:** " . number_format($filesize) . " bytes\n";
    $analysis .= "**📝 Lines:** " . $lines . "\n";
    $analysis .= "**🔧 Type:** " . strtoupper($extension) . " file\n";
    $analysis .= "**⏰ Analyzed:** " . date('Y-m-d H:i:s') . "\n\n";
    
    switch ($extension) {
        case 'py':
            $analysis .= analyzePythonFile($content);
            break;
            
        case 'js':
            $analysis .= analyzeJavaScriptFile($content);
            break;
            
        case 'json':
            $analysis .= analyzeJsonFile($content);
            break;
            
        case 'html':
            $analysis .= analyzeHtmlFile($content);
            break;
            
        default:
            $analysis .= analyzeTextFile($content);
    }
    
    $analysis .= "\n---\n";
    $analysis .= "*Generated by YASIN.PY AI Assistant*\n";
    $analysis .= "*Premium users get detailed code reviews and optimization suggestions*";
    
    return $analysis;
}

function analyzePythonFile($content) {
    $analysis = "### 🐍 Python File Analysis\n\n";
    
    $function_count = preg_match_all('/\bdef\s+(\w+)\s*\(/i', $content);
    $class_count = preg_match_all('/\bclass\s+(\w+)/i', $content);
    $import_count = preg_match_all('/^(import|from)\s+/m', $content);
    $comment_count = preg_match_all('/#\s*(.+)$/m', $content);
    
    $analysis .= "• **Functions:** " . $function_count . "\n";
    $analysis .= "• **Classes:** " . $class_count . "\n";
    $analysis .= "• **Imports:** " . $import_count . "\n";
    $analysis .= "• **Comments:** " . $comment_count . "\n";
    
    // Check for common patterns
    $patterns = [
        'print(' => 'Has print statements',
        '# TODO' => 'Has TODO comments',
        '# FIXME' => 'Has FIXME comments',
        'try:' => 'Uses try-except blocks',
        '@decorator' => 'Uses decorators',
        'async def' => 'Has async functions',
        'type:' => 'Uses type hints'
    ];
    
    $analysis .= "\n**Features detected:**\n";
    foreach ($patterns as $pattern => $desc) {
        if (strpos($content, $pattern) !== false) {
            $analysis .= "• " . $desc . "\n";
        }
    }
    
    $analysis .= "\n**Recommendations:**\n";
    $analysis .= "1. Add docstrings to all functions and classes\n";
    $analysis .= "2. Use type hints for better code clarity\n";
    $analysis .= "3. Add comprehensive error handling\n";
    $analysis .= "4. Follow PEP 8 style guidelines\n";
    $analysis .= "5. Consider adding unit tests\n";
    
    return $analysis;
}

function analyzeJavaScriptFile($content) {
    $analysis = "### 📜 JavaScript File Analysis\n\n";
    
    $function_count = preg_match_all('/function\s+\w+|const\s+\w+\s*=|let\s+\w+\s*=|var\s+\w+\s*=/i', $content);
    $import_count = preg_match_all('/import\s+|require\(/i', $content);
    $export_count = preg_match_all('/export\s+/i', $content);
    
    $analysis .= "• **Functions/Variables:** " . $function_count . "\n";
    $analysis .= "• **Imports:** " . $import_count . "\n";
    $analysis .= "• **Exports:** " . $export_count . "\n";
    
    return $analysis;
}

function analyzeJsonFile($content) {
    $analysis = "### 🗂️ JSON File Analysis\n\n";
    
    $json_data = json_decode($content, true);
    if ($json_data && json_last_error() === JSON_ERROR_NONE) {
        $analysis .= "• **Valid JSON:** ✓\n";
        $analysis .= "• **Data type:** " . gettype($json_data) . "\n";
        
        if (is_array($json_data)) {
            $analysis .= "• **Array count:** " . count($json_data) . " items\n";
        } elseif (is_object($json_data)) {
            $analysis .= "• **Object properties:** " . count(get_object_vars($json_data)) . "\n";
        }
    } else {
        $analysis .= "• **Valid JSON:** ✗\n";
        $analysis .= "• **Error:** " . json_last_error_msg() . "\n";
    }
    
    return $analysis;
}

function analyzeHtmlFile($content) {
    $analysis = "### 🌐 HTML File Analysis\n\n";
    
    $tag_count = preg_match_all('/<(\w+)/', $content);
    $script_count = substr_count($content, '<script');
    $style_count = substr_count($content, '<style');
    $div_count = substr_count($content, '<div');
    
    $analysis .= "• **Total tags:** " . $tag_count . "\n";
    $analysis .= "• **Script tags:** " . $script_count . "\n";
    $analysis .= "• **Style tags:** " . $style_count . "\n";
    $analysis .= "• **Div elements:** " . $div_count . "\n";
    
    return $analysis;
}

function analyzeTextFile($content) {
    $analysis = "### 📝 Text File Analysis\n\n";
    
    $word_count = str_word_count($content);
    $char_count = strlen($content);
    $line_count = count(explode("\n", $content));
    $non_empty_lines = count(array_filter(explode("\n", $content), function($line) {
        return trim($line) !== '';
    }));
    
    $analysis .= "• **Words:** " . $word_count . "\n";
    $analysis .= "• **Characters:** " . $char_count . "\n";
    $analysis .= "• **Lines:** " . $line_count . "\n";
    $analysis .= "• **Non-empty lines:** " . $non_empty_lines . "\n";
    
    return $analysis;
}

function handleLogin($data) {
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? 'User';
    $provider = $data['provider'] ?? 'unknown';
    $ip = $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Save login info
    $logins = json_decode(file_get_contents('data/logins.json'), true);
    $logins[] = [
        'email' => $email,
        'name' => $name,
        'provider' => $provider,
        'ip' => $ip,
        'timestamp' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    file_put_contents('data/logins.json', json_encode($logins, JSON_PRETTY_PRINT));
    
    // Send to Telegram bot
    sendTelegramNotification("📱 New Login @ YASIN.PY\n\n👤 User: $name\n📧 Email: $email\n🌐 Provider: $provider\n📍 IP: $ip\n🕐 Time: " . date('Y-m-d H:i:s'));
    
    echo json_encode([
        'success' => true, 
        'message' => 'Login recorded successfully',
        'user' => $name,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handlePremiumActivation($data) {
    $code = $data['code'] ?? '';
    $user = $data['user'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'error' => 'No subscription code provided']);
        return;
    }
    
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    
    // Check if code exists
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
                'code_used' => $code,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
            ];
            
            file_put_contents('data/subscriptions.json', json_encode($subscriptions, JSON_PRETTY_PRINT));
            
            // Send notification
            sendTelegramNotification("🎉 Premium Activated!\n\n👤 User: $user\n🔑 Code: $code\n⏰ Time: " . date('Y-m-d H:i:s'));
            
            echo json_encode([
                'success' => true,
                'message' => 'Premium activated successfully!',
                'user' => $user,
                'subscription' => 'premium',
                'expires' => date('Y-m-d H:i:s', strtotime('+30 days'))
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Code already used',
                'used_by' => $codeData['used_by'] ?? 'Unknown',
                'used_at' => $codeData['used_at'] ?? 'Unknown'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription code']);
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
        'response_preview' => substr($response, 0, 200),
        'response_length' => strlen($response),
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
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
        'analysis_preview' => substr($analysis, 0, 200),
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}

function sendTelegramNotification($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_ADMIN_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
?>