<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'YASIN_VIPXIT_2026');
define('ADMIN_TELEGRAM', '@YASIN_VIPXIT');

// Initialize storage
function initStorage() {
    if (!is_dir('data')) mkdir('data', 0755, true);
    if (!is_dir('logs')) mkdir('logs', 0755, true);
    
    $files = [
        'data/subscriptions.json' => '{"premium_codes": {}, "users": {}, "settings": {}}',
        'data/logins.json' => '[]',
        'data/files.json' => '[]',
        'data/payments.json' => '[]'
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
        }
    }
}

initStorage();

// Authentication
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['login_time'] = time();
        } else {
            $error = 'Invalid credentials';
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        showLoginForm($error ?? '');
        exit;
    }
}

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'generate_code':
            generatePremiumCode();
            break;
        case 'delete_code':
            deletePremiumCode($_GET['code'] ?? '');
            break;
        case 'view_user':
            showUserDetails($_GET['user'] ?? '');
            break;
        case 'logout':
            session_destroy();
            header('Location: dashboard.php');
            exit;
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_codes':
                generateMultipleCodes();
                break;
            case 'update_settings':
                updateSettings();
                break;
            case 'send_notification':
                sendNotification();
                break;
        }
    }
}

// Main Dashboard
function showDashboard() {
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    $logins = json_decode(file_get_contents('data/logins.json'), true);
    $files = json_decode(file_get_contents('data/files.json'), true);
    $payments = json_decode(file_get_contents('data/payments.json'), true);
    
    $premiumUsers = count(array_filter($subscriptions['users'] ?? [], function($user) {
        return isset($user['premium']) && $user['premium'] === true;
    }));
    
    $totalCodes = count($subscriptions['premium_codes'] ?? []);
    $usedCodes = count(array_filter($subscriptions['premium_codes'] ?? [], function($code) {
        return isset($code['used']) && $code['used'] === true;
    }));
    
    $revenue = array_sum(array_column($payments, 'amount'));
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>YASIN.PY Admin Dashboard</title>
        <style>
            :root {
                --primary: #6366f1;
                --primary-dark: #4f46e5;
                --secondary: #10b981;
                --dark: #1f2937;
                --darker: #111827;
                --light: #f9fafb;
                --gray: #6b7280;
                --danger: #ef4444;
                --warning: #f59e0b;
                --success: #10b981;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: var(--light);
                min-height: 100vh;
            }

            .container {
                max-width: 1400px;
                margin: 0 auto;
                padding: 20px;
            }

            .header {
                background: rgba(31, 41, 55, 0.95);
                backdrop-filter: blur(10px);
                padding: 1.5rem 2rem;
                border-radius: 15px;
                margin-bottom: 2rem;
                border: 1px solid rgba(99, 102, 241, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .logo {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 1.5rem;
                font-weight: 700;
            }

            .logo i {
                color: var(--secondary);
            }

            .user-info {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            .stat-card {
                background: rgba(31, 41, 55, 0.8);
                padding: 1.5rem;
                border-radius: 15px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                transition: all 0.3s;
            }

            .stat-card:hover {
                transform: translateY(-5px);
                border-color: var(--primary);
            }

            .stat-card h3 {
                color: var(--gray);
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .stat-card .value {
                font-size: 2.5rem;
                font-weight: 700;
                color: var(--primary);
            }

            .stat-card .change {
                font-size: 0.9rem;
                color: var(--success);
            }

            .tabs {
                display: flex;
                gap: 1rem;
                margin-bottom: 2rem;
                background: rgba(31, 41, 55, 0.8);
                padding: 1rem;
                border-radius: 15px;
            }

            .tab {
                padding: 0.75rem 1.5rem;
                background: transparent;
                border: none;
                color: var(--light);
                cursor: pointer;
                border-radius: 10px;
                font-weight: 500;
                transition: all 0.3s;
            }

            .tab.active {
                background: var(--primary);
                color: white;
            }

            .tab:hover:not(.active) {
                background: rgba(255, 255, 255, 0.1);
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
            }

            .card {
                background: rgba(31, 41, 55, 0.8);
                border-radius: 15px;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .btn {
                padding: 0.5rem 1.5rem;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }

            .btn-primary {
                background: var(--primary);
                color: white;
            }

            .btn-primary:hover {
                background: var(--primary-dark);
                transform: translateY(-2px);
            }

            .btn-success {
                background: var(--success);
                color: white;
            }

            .btn-danger {
                background: var(--danger);
                color: white;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 1rem;
            }

            th, td {
                padding: 1rem;
                text-align: left;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            th {
                background: rgba(0, 0, 0, 0.2);
                color: var(--gray);
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.8rem;
                letter-spacing: 1px;
            }

            tr:hover {
                background: rgba(255, 255, 255, 0.05);
            }

            .code {
                font-family: monospace;
                background: rgba(0, 0, 0, 0.3);
                padding: 0.5rem 1rem;
                border-radius: 5px;
                border-left: 3px solid var(--primary);
            }

            .badge {
                padding: 0.25rem 0.75rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
            }

            .badge-success {
                background: rgba(16, 185, 129, 0.2);
                color: var(--success);
                border: 1px solid rgba(16, 185, 129, 0.3);
            }

            .badge-danger {
                background: rgba(239, 68, 68, 0.2);
                color: var(--danger);
                border: 1px solid rgba(239, 68, 68, 0.3);
            }

            .badge-warning {
                background: rgba(245, 158, 11, 0.2);
                color: var(--warning);
                border: 1px solid rgba(245, 158, 11, 0.3);
            }

            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 1000;
                backdrop-filter: blur(5px);
            }

            .modal-content {
                background: rgba(31, 41, 55, 0.95);
                width: 90%;
                max-width: 500px;
                margin: 5% auto;
                padding: 2rem;
                border-radius: 20px;
                border: 1px solid rgba(99, 102, 241, 0.3);
                position: relative;
            }

            .close-modal {
                position: absolute;
                top: 1rem;
                right: 1rem;
                font-size: 1.5rem;
                cursor: pointer;
                color: var(--gray);
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 0.75rem;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                color: white;
                font-size: 1rem;
            }

            .alert {
                padding: 1rem;
                border-radius: 10px;
                margin-bottom: 1rem;
            }

            .alert-success {
                background: rgba(16, 185, 129, 0.2);
                border: 1px solid rgba(16, 185, 129, 0.3);
                color: var(--success);
            }

            .alert-danger {
                background: rgba(239, 68, 68, 0.2);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: var(--danger);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="logo">
                    <i class="fab fa-python"></i>
                    YASIN.PY Admin Dashboard
                </div>
                <div class="user-info">
                    <span>Welcome, Admin</span>
                    <span class="badge badge-success">Online</span>
                    <a href="?action=logout" class="btn btn-danger">Logout</a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="value"><?php echo count($logins); ?></div>
                    <div class="change">+12 today</div>
                </div>
                <div class="stat-card">
                    <h3>Premium Users</h3>
                    <div class="value"><?php echo $premiumUsers; ?></div>
                    <div class="change">+3 today</div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value">$<?php echo number_format($revenue, 2); ?></div>
                    <div class="change">+$45.00 today</div>
                </div>
                <div class="stat-card">
                    <h3>Active Codes</h3>
                    <div class="value"><?php echo $totalCodes - $usedCodes; ?></div>
                    <div class="change"><?php echo $usedCodes; ?> used</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="showTab('codes')">Premium Codes</button>
                <button class="tab" onclick="showTab('users')">Users</button>
                <button class="tab" onclick="showTab('files')">File Uploads</button>
                <button class="tab" onclick="showTab('logs')">Activity Logs</button>
                <button class="tab" onclick="showTab('settings')">Settings</button>
            </div>

            <!-- Premium Codes Tab -->
            <div id="codes" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3>Generate Premium Codes</h3>
                        <button class="btn btn-primary" onclick="showModal('generateModal')">
                            <i class="fas fa-plus"></i> Generate New Code
                        </button>
                    </div>
                    
                    <div class="alert alert-success" id="successMessage" style="display: none;"></div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Generated</th>
                                <th>Used By</th>
                                <th>Used Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions['premium_codes'] ?? [] as $code => $data): ?>
                            <tr>
                                <td><span class="code"><?php echo htmlspecialchars($code); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $data['used'] ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $data['used'] ? 'Used' : 'Available'; ?>
                                    </span>
                                </td>
                                <td><?php echo $data['generated'] ?? 'Unknown'; ?></td>
                                <td><?php echo $data['used_by'] ?? '—'; ?></td>
                                <td><?php echo $data['used_at'] ?? '—'; ?></td>
                                <td>
                                    <?php if (!$data['used']): ?>
                                    <a href="?action=delete_code&code=<?php echo $code; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Delete this code?')">
                                        Delete
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Registered Users</h3>
                        <span>Total: <?php echo count($logins); ?> users</span>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Email/Name</th>
                                <th>Provider</th>
                                <th>IP Address</th>
                                <th>Login Time</th>
                                <th>Premium</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logins as $login): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($login['name'] ?? 'Unknown'); ?></strong><br>
                                    <small><?php echo htmlspecialchars($login['email'] ?? 'No email'); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-warning">
                                        <?php echo htmlspecialchars($login['provider'] ?? 'unknown'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($login['ip'] ?? 'unknown'); ?></td>
                                <td><?php echo htmlspecialchars($login['timestamp'] ?? 'Unknown'); ?></td>
                                <td>
                                    <?php 
                                    $email = $login['email'] ?? '';
                                    $isPremium = isset($subscriptions['users'][$email]) && 
                                                $subscriptions['users'][$email]['premium'] === true;
                                    ?>
                                    <span class="badge <?php echo $isPremium ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $isPremium ? 'Premium' : 'Free'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=view_user&user=<?php echo urlencode($email); ?>" 
                                       class="btn btn-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- File Uploads Tab -->
            <div id="files" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>File Uploads</h3>
                        <span>Total: <?php echo count($files); ?> files</span>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Filename</th>
                                <th>Original Name</th>
                                <th>Upload Time</th>
                                <th>Analysis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['user']); ?></td>
                                <td><code><?php echo htmlspecialchars($file['filename']); ?></code></td>
                                <td><?php echo htmlspecialchars($file['original']); ?></td>
                                <td><?php echo htmlspecialchars($file['timestamp']); ?></td>
                                <td>
                                    <button class="btn btn-primary" 
                                            onclick="showAnalysis('<?php echo addslashes($file['analysis']); ?>')">
                                        View Analysis
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>System Logs</h3>
                        <button class="btn btn-primary" onclick="downloadLogs()">
                            <i class="fas fa-download"></i> Download Logs
                        </button>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <pre style="background: rgba(0, 0, 0, 0.3); padding: 1rem; border-radius: 10px; font-family: monospace;">
<?php
$logFiles = glob('logs/*.json');
foreach ($logFiles as $logFile) {
    echo "=== " . basename($logFile) . " ===\n";
    $content = file_get_contents($logFile);
    $data = json_decode($content, true);
    foreach ($data as $log) {
        echo "[" . ($log['timestamp'] ?? '') . "] ";
        echo ($log['user'] ?? 'Unknown') . ": ";
        echo substr($log['question'] ?? $log['message'] ?? '', 0, 100) . "\n";
    }
    echo "\n";
}
?>
                        </pre>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>System Settings</h3>
                        <button class="btn btn-success" onclick="saveSettings()">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                    
                    <form id="settingsForm">
                        <div class="form-group">
                            <label>Telegram Bot Token</label>
                            <input type="text" name="telegram_token" 
                                   value="8507470118:AAGXiWwxQyWkdIToAZFZSta2GmLetxos2-A">
                        </div>
                        
                        <div class="form-group">
                            <label>Admin Telegram ID</label>
                            <input type="text" name="telegram_admin" value="6808885369">
                        </div>
                        
                        <div class="form-group">
                            <label>Site Name</label>
                            <input type="text" name="site_name" value="YASIN.PY">
                        </div>
                        
                        <div class="form-group">
                            <label>Premium Price ($)</label>
                            <input type="number" name="premium_price" value="9.99" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label>Free Daily Limits</label>
                            <input type="number" name="free_requests" value="10" placeholder="Requests">
                            <input type="number" name="free_uploads" value="1" placeholder="Uploads" style="margin-top: 0.5rem;">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="card" style="margin-top: 2rem; text-align: center; color: var(--gray);">
                <p>
                    © 2023-2026 YASIN.PY. All rights reserved. | 
                    TELEGRAM: <?php echo ADMIN_TELEGRAM; ?> | 
                    Admin Dashboard v2.0
                </p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem;">
                    All trademarks and registered trademarks are the property of their respective owners.
                </p>
            </div>
        </div>

        <!-- Generate Code Modal -->
        <div id="generateModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeModal('generateModal')">&times;</span>
                <h3 style="margin-bottom: 1.5rem;">Generate Premium Codes</h3>
                
                <form method="POST" onsubmit="return generateCodes()">
                    <input type="hidden" name="action" value="generate_codes">
                    
                    <div class="form-group">
                        <label>Number of Codes</label>
                        <input type="number" name="count" value="1" min="1" max="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Code Prefix</label>
                        <input type="text" name="prefix" value="YASIN" placeholder="YASIN">
                    </div>
                    
                    <div class="form-group">
                        <label>Code Length (excluding prefix)</label>
                        <input type="number" name="length" value="8" min="4" max="20">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-magic"></i> Generate Codes
                    </button>
                </form>
            </div>
        </div>

        <!-- Analysis Modal -->
        <div id="analysisModal" class="modal">
            <div class="modal-content" style="max-width: 800px;">
                <span class="close-modal" onclick="closeModal('analysisModal')">&times;</span>
                <h3 style="margin-bottom: 1.5rem;">File Analysis</h3>
                <pre id="analysisContent" style="background: rgba(0, 0, 0, 0.3); padding: 1rem; border-radius: 10px; max-height: 400px; overflow-y: auto; font-family: monospace;"></pre>
            </div>
        </div>

        <script>
            // Tab switching
            function showTab(tabName) {
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                document.getElementById(tabName).classList.add('active');
                event.target.classList.add('active');
            }

            // Modal functions
            function showModal(modalId) {
                document.getElementById(modalId).style.display = 'block';
            }

            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
            }

            function showAnalysis(content) {
                document.getElementById('analysisContent').textContent = content;
                showModal('analysisModal');
            }

            // Generate codes via AJAX
            function generateCodes() {
                const form = event.target;
                const formData = new FormData(form);
                
                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('successMessage').textContent = 
                            'Generated ' + data.count + ' codes successfully!';
                        document.getElementById('successMessage').style.display = 'block';
                        closeModal('generateModal');
                        setTimeout(() => location.reload(), 2000);
                    }
                });
                
                return false;
            }

            // Save settings
            function saveSettings() {
                const form = document.getElementById('settingsForm');
                const formData = new FormData(form);
                formData.append('action', 'update_settings');
                
                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert('Settings saved successfully!');
                });
            }

            // Download logs
            function downloadLogs() {
                window.open('dashboard.php?action=download_logs', '_blank');
            }

            // Auto-refresh every 30 seconds
            setTimeout(() => {
                location.reload();
            }, 30000);
        </script>
    </body>
    </html>
    <?php
}

function showLoginForm($error = '') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>YASIN.PY Admin Login</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #f9fafb;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .login-container {
                background: rgba(31, 41, 55, 0.95);
                backdrop-filter: blur(10px);
                padding: 3rem;
                border-radius: 20px;
                width: 90%;
                max-width: 400px;
                border: 1px solid rgba(99, 102, 241, 0.3);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }

            .logo {
                text-align: center;
                margin-bottom: 2rem;
                font-size: 2rem;
                font-weight: 700;
                color: #6366f1;
            }

            .logo i {
                color: #10b981;
                margin-right: 10px;
            }

            .error {
                background: rgba(239, 68, 68, 0.2);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: #ef4444;
                padding: 1rem;
                border-radius: 10px;
                margin-bottom: 1.5rem;
                text-align: center;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #9ca3af;
            }

            input {
                width: 100%;
                padding: 0.75rem;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                color: white;
                font-size: 1rem;
                transition: border-color 0.3s;
            }

            input:focus {
                outline: none;
                border-color: #6366f1;
            }

            button {
                width: 100%;
                padding: 1rem;
                background: #6366f1;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }

            button:hover {
                background: #4f46e5;
                transform: translateY(-2px);
            }

            .footer {
                text-align: center;
                margin-top: 2rem;
                color: #6b7280;
                font-size: 0.9rem;
            }

            .footer a {
                color: #6366f1;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo">
                <i class="fab fa-python"></i>
                YASIN.PY Admin
            </div>
            
            <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter admin username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter admin password">
                </div>
                
                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                </button>
            </form>
            
            <div class="footer">
                <p>© 2023-2026 YASIN.PY. All rights reserved.</p>
                <p>TELEGRAM: @YASIN_VIPXIT</p>
                <p style="margin-top: 1rem; font-size: 0.8rem;">
                    Unauthorized access is prohibited. All activities are logged.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function generatePremiumCode() {
    $prefix = $_GET['prefix'] ?? 'YASIN';
    $length = $_GET['length'] ?? 8;
    
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = $prefix . '_';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    $subscriptions['premium_codes'][$code] = [
        'generated' => date('Y-m-d H:i:s'),
        'used' => false,
        'used_by' => null,
        'used_at' => null
    ];
    
    file_put_contents('data/subscriptions.json', json_encode($subscriptions, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'code' => $code]);
    exit;
}

function generateMultipleCodes() {
    $count = $_POST['count'] ?? 1;
    $prefix = $_POST['prefix'] ?? 'YASIN';
    $length = $_POST['length'] ?? 8;
    
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codes = [];
    
    for ($c = 0; $c < $count; $c++) {
        $code = $prefix . '_';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        $subscriptions['premium_codes'][$code] = [
            'generated' => date('Y-m-d H:i:s'),
            'used' => false,
            'used_by' => null,
            'used_at' => null
        ];
        
        $codes[] = $code;
    }
    
    file_put_contents('data/subscriptions.json', json_encode($subscriptions, JSON_PRETTY_PRINT));
    
    // Log the generation
    $payments = json_decode(file_get_contents('data/payments.json'), true);
    $payments[] = [
        'type' => 'code_generation',
        'count' => $count,
        'codes' => $codes,
        'timestamp' => date('Y-m-d H:i:s'),
        'generated_by' => 'admin'
    ];
    
    file_put_contents('data/payments.json', json_encode($payments, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'count' => $count, 'codes' => $codes]);
    exit;
}

function deletePremiumCode($code) {
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    
    if (isset($subscriptions['premium_codes'][$code])) {
        unset($subscriptions['premium_codes'][$code]);
        file_put_contents('data/subscriptions.json', json_encode($subscriptions, JSON_PRETTY_PRINT));
    }
    
    header('Location: dashboard.php');
    exit;
}

function showUserDetails($user) {
    $subscriptions = json_decode(file_get_contents('data/subscriptions.json'), true);
    $logins = json_decode(file_get_contents('data/logins.json'), true);
    $files = json_decode(file_get_contents('data/files.json'), true);
    
    $userLogins = array_filter($logins, function($login) use ($user) {
        return ($login['email'] ?? '') === $user;
    });
    
    $userFiles = array_filter($files, function($file) use ($user) {
        return $file['user'] === $user;
    });
    
    $isPremium = isset($subscriptions['users'][$user]) && 
                 $subscriptions['users'][$user]['premium'] === true;
    
    ?>
    <div style="padding: 2rem;">
        <h2>User Details: <?php echo htmlspecialchars($user); ?></h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            <div style="background: rgba(31, 41, 55, 0.8); padding: 1.5rem; border-radius: 10px;">
                <h3>Subscription Status</h3>
                <p>Premium: <?php echo $isPremium ? '✅ Active' : '❌ Inactive'; ?></p>
                <?php if ($isPremium): ?>
                <p>Activated: <?php echo $subscriptions['users'][$user]['activated_at'] ?? 'Unknown'; ?></p>
                <p>Code Used: <?php echo $subscriptions['users'][$user]['code_used'] ?? 'Unknown'; ?></p>
                <?php endif; ?>
            </div>
            
            <div style="background: rgba(31, 41, 55, 0.8); padding: 1.5rem; border-radius: 10px;">
                <h3>Login History</h3>
                <?php foreach (array_slice($userLogins, 0, 5) as $login): ?>
                <p>
                    <?php echo $login['timestamp'] ?? ''; ?> via 
                    <?php echo $login['provider'] ?? ''; ?> (IP: <?php echo $login['ip'] ?? ''; ?>)
                </p>
                <?php endforeach; ?>
            </div>
            
            <div style="background: rgba(31, 41, 55, 0.8); padding: 1.5rem; border-radius: 10px;">
                <h3>File Uploads</h3>
                <p>Total Files: <?php echo count($userFiles); ?></p>
                <?php foreach (array_slice($userFiles, 0, 3) as $file): ?>
                <p><?php echo $file['original']; ?> (<?php echo $file['timestamp']; ?>)</p>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
    <?php
    exit;
}

// Start the dashboard
showDashboard();
?>