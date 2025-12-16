// YASIN.PY Main JavaScript - Fixed Version
class YasinPyApp {
    constructor() {
        this.user = {
            loggedIn: false,
            subscription: 'free',
            tokens: 15, // Increased free tokens
            email: null,
            name: 'Guest',
            ip: null,
            filesUploaded: 0,
            lastUploadDate: null,
            userId: this.generateUserId()
        };
        
        this.chatHistory = [];
        this.apiEndpoint = 'api.php';
        this.isTyping = false;
        this.retryCount = 0;
        this.maxRetries = 3;
        
        this.init();
    }

    async init() {
        console.log('YASIN.PY App Initializing...');
        
        this.loadUserData();
        this.setupEventListeners();
        this.updateUI();
        
        // Get user IP
        this.user.ip = await this.getUserIP();
        
        // Test API connection
        await this.testApiConnection();
        
        // Add welcome message
        setTimeout(() => {
            this.addMessage('bot', this.getWelcomeMessage());
        }, 500);
        
        console.log('YASIN.PY App Initialized Successfully');
    }

    generateUserId() {
        return 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    async getUserIP() {
        try {
            const response = await fetch('https://api.ipify.org?format=json', {
                timeout: 5000
            });
            const data = await response.json();
            return data.ip;
        } catch (error) {
            console.log('Using fallback IP detection');
            return '127.0.0.1';
        }
    }

    async testApiConnection() {
        try {
            const response = await fetch(this.apiEndpoint + '?type=test', {
                method: 'GET',
                timeout: 5000
            });
            
            const data = await response.json();
            console.log('API Test Result:', data);
            
            if (data.status === 'success') {
                this.showNotification('✅ Connected to AI Service', 'success', 3000);
            }
        } catch (error) {
            console.warn('API test failed, using fallback mode:', error);
            this.showNotification('⚠️ Using local AI mode', 'warning', 3000);
        }
    }

    loadUserData() {
        try {
            const saved = localStorage.getItem('yasinpy_user');
            if (saved) {
                const parsed = JSON.parse(saved);
                this.user = {...this.user, ...parsed};
                console.log('User data loaded:', this.user);
            }
        } catch (error) {
            console.error('Failed to load user data:', error);
            localStorage.removeItem('yasinpy_user');
        }
    }

    saveUserData() {
        try {
            localStorage.setItem('yasinpy_user', JSON.stringify(this.user));
        } catch (error) {
            console.error('Failed to save user data:', error);
        }
    }

    setupEventListeners() {
        // Message sending
        const sendBtn = document.getElementById('sendBtn');
        const messageInput = document.getElementById('messageInput');
        
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }
        
        if (messageInput) {
            messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        
        // File upload
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');
        
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files[0]) this.uploadFile(e.target.files[0]);
            });
        }
        
        if (uploadArea) {
            // Drag and drop
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = 'var(--primary)';
                uploadArea.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.style.borderColor = 'rgba(99, 102, 241, 0.3)';
                uploadArea.style.backgroundColor = '';
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = 'rgba(99, 102, 241, 0.3)';
                uploadArea.style.backgroundColor = '';
                
                if (e.dataTransfer.files[0]) {
                    this.uploadFile(e.dataTransfer.files[0]);
                }
            });
            
            uploadArea.addEventListener('click', () => {
                if (fileInput) fileInput.click();
            });
        }
        
        // Login buttons
        const emailLoginBtn = document.getElementById('emailLoginBtn');
        const fbLoginBtn = document.getElementById('fbLoginBtn');
        const activatePremiumBtn = document.getElementById('activatePremiumBtn');
        
        if (emailLoginBtn) {
            emailLoginBtn.addEventListener('click', () => this.handleEmailLogin());
        }
        
        if (fbLoginBtn) {
            fbLoginBtn.addEventListener('click', () => this.handleFacebookLogin());
        }
        
        if (activatePremiumBtn) {
            activatePremiumBtn.addEventListener('click', () => this.activatePremium());
        }
        
        // Log all clicks for debugging
        document.addEventListener('click', (e) => {
            console.log('Clicked:', e.target.id || e.target.className);
        });
    }

    updateUI() {
        // Update user avatar
        const avatar = document.getElementById('userAvatar');
        if (avatar) {
            const initials = this.user.name.charAt(0).toUpperCase();
            avatar.textContent = initials;
            avatar.title = this.user.name;
        }
        
        // Update subscription badge
        const badge = document.getElementById('subscriptionBadge');
        if (badge) {
            badge.textContent = this.user.subscription.toUpperCase();
            badge.style.background = this.user.subscription === 'premium' 
                ? 'linear-gradient(135deg, var(--premium) 0%, #f59e0b 100%)' 
                : 'var(--gray)';
            
            // Add tooltip
            badge.title = this.user.subscription === 'premium' 
                ? 'Premium User - Unlimited Access' 
                : 'Free User - Limited Features';
        }
        
        // Update upload limit display
        const limitText = document.querySelector('.upload-limit');
        if (limitText) {
            limitText.innerHTML = this.user.subscription === 'premium' 
                ? '✨ <strong>Premium:</strong> Unlimited uploads' 
                : '🎯 <strong>Free:</strong> 1 file/day • <strong>Premium:</strong> Unlimited';
        }
        
        // Update token count
        this.updateTokenCount();
        
        // Update login button
        const loginBtn = document.getElementById('loginBtn');
        if (loginBtn) {
            if (this.user.loggedIn) {
                loginBtn.innerHTML = `<i class="fas fa-user"></i> ${this.user.name}`;
                loginBtn.style.background = 'var(--success)';
            } else {
                loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
                loginBtn.style.background = 'var(--primary)';
            }
        }
    }

    updateTokenCount() {
        const tokenCount = document.getElementById('tokenCount');
        if (tokenCount) {
            if (this.user.subscription === 'premium') {
                tokenCount.innerHTML = '<i class="fas fa-infinity"></i> Unlimited Requests';
                tokenCount.style.color = 'var(--premium)';
            } else {
                tokenCount.innerHTML = `<i class="fas fa-coins"></i> ${this.user.tokens} Requests Left`;
                tokenCount.style.color = this.user.tokens < 5 ? 'var(--danger)' : 'var(--success)';
            }
        }
    }

    getWelcomeMessage() {
        const welcomeMessages = [
            "**👋 Welcome to YASIN.PY AI Assistant!**\n\nI'm your expert Python programming helper. I can:\n• Write and debug Python code\n• Explain programming concepts\n• Analyze your Python files\n• Help with projects and scripts\n\n*Try asking me anything about Python!*",
            
            "**🚀 Ready to code?**\n\nI'm here to help you with all things Python! Whether you're:\n• Learning Python basics\n• Building web applications\n• Working with data science\n• Creating automation scripts\n\n*What would you like to work on today?*",
            
            "**🐍 Python Power at Your Fingertips!**\n\nAs your AI assistant, I can provide:\n• Code examples with explanations\n• Debugging help and error solutions\n• Best practices and optimization tips\n• File analysis and code reviews\n\n*How can I assist your Python journey?*"
        ];
        
        return welcomeMessages[Math.floor(Math.random() * welcomeMessages.length)];
    }

    async sendMessage() {
        if (this.isTyping) {
            this.showNotification('Please wait for current response...', 'warning');
            return;
        }
        
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) {
            this.showNotification('Please enter a message', 'warning');
            return;
        }
        
        // Check limits for free users
        if (this.user.subscription === 'free' && this.user.tokens <= 0) {
            this.showNotification('Daily limit reached! Upgrade to Premium for unlimited requests.', 'warning');
            this.showUpgradePrompt();
            return;
        }
        
        // Add user message to chat
        this.addMessage('user', message);
        input.value = '';
        
        // Reset textarea height
        input.style.height = 'auto';
        
        // Update tokens for free users
        if (this.user.subscription === 'free') {
            this.user.tokens = Math.max(0, this.user.tokens - 1);
            this.saveUserData();
            this.updateTokenCount();
        }
        
        // Show typing indicator
        this.showTypingIndicator();
        this.isTyping = true;
        
        try {
            // Prepare request data
            const requestData = {
                type: 'chat',
                message: message,
                user: this.user.email || this.user.userId,
                subscription: this.user.subscription,
                timestamp: new Date().toISOString()
            };
            
            console.log('Sending request:', requestData);
            
            // Send to API with timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData),
                signal: controller.signal,
                mode: 'cors'
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('API Response:', data);
            
            // Remove typing indicator
            this.removeTypingIndicator();
            this.isTyping = false;
            this.retryCount = 0;
            
            // Add AI response
            if (data.response) {
                this.addMessage('bot', data.response);
            } else {
                throw new Error('No response from AI');
            }
            
        } catch (error) {
            console.error('Chat error:', error);
            
            // Remove typing indicator
            this.removeTypingIndicator();
            this.isTyping = false;
            
            // Handle retry
            if (this.retryCount < this.maxRetries && !error.message.includes('abort')) {
                this.retryCount++;
                this.showNotification(`Retrying... (${this.retryCount}/${this.maxRetries})`, 'warning', 2000);
                setTimeout(() => this.sendMessage(), 1000 * this.retryCount);
                return;
            }
            
            // Reset retry count
            this.retryCount = 0;
            
            // Show error message
            const errorMessage = this.getErrorMessage(error);
            this.addMessage('bot', errorMessage);
            
            // Show notification
            this.showNotification('Using local AI responses', 'info', 3000);
        }
    }

    getErrorMessage(error) {
        const errorMessages = {
            'timeout': "**⚠️ Request Timeout**\n\nThe AI service is taking too long to respond. This might be due to:\n• High server load\n• Network issues\n• Complex query\n\nPlease try again with a simpler question or contact @YASIN_VIPXIT for premium support.",
            
            'network': "**🌐 Network Error**\n\nUnable to connect to the AI service. Please:\n1. Check your internet connection\n2. Refresh the page\n3. Try again in a moment\n\n*Working offline with local responses...*",
            
            'default': "**🤖 YASIN.PY Local Assistant**\n\nI'm currently working in offline mode. Here's a Python tip:\n\n```python\n# Always use virtual environments\npython -m venv myenv\nsource myenv/bin/activate  # On Unix/macOS\nmyenv\\Scripts\\activate    # On Windows\n\n# Install packages in virtual environment\npip install package-name\n```\n\n*For real-time AI responses, please check your connection or contact @YASIN_VIPXIT*"
        };
        
        if (error.name === 'AbortError' || error.message.includes('timeout')) {
            return errorMessages.timeout;
        } else if (error.message.includes('network') || error.message.includes('fetch')) {
            return errorMessages.network;
        } else {
            return errorMessages.default;
        }
    }

    async uploadFile(file) {
        // Check file size
        if (file.size > 5 * 1024 * 1024) {
            this.showNotification('File too large! Maximum size is 5MB.', 'error');
            return;
        }
        
        // Check upload limit for free users
        const today = new Date().toDateString();
        if (this.user.subscription === 'free') {
            if (this.user.lastUploadDate === today && this.user.filesUploaded >= 1) {
                this.showNotification('Free users can only upload 1 file per day. Upgrade to Premium!', 'warning');
                this.showUpgradePrompt();
                return;
            }
            
            if (this.user.lastUploadDate !== today) {
                this.user.filesUploaded = 0;
                this.user.lastUploadDate = today;
            }
        }
        
        // Show uploading indicator
        this.showNotification(`Uploading ${file.name}...`, 'info');
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'file_upload');
        formData.append('user', this.user.email || this.user.userId);
        formData.append('subscription', this.user.subscription);
        
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData,
                timeout: 60000
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.user.filesUploaded++;
                this.saveUserData();
                
                this.showNotification('✅ File uploaded successfully!', 'success', 3000);
                
                // Add file analysis to chat
                this.addMessage('bot', `**📁 File Analysis: ${file.name}**\n\n${data.analysis}`);
                
                // Show download link
                if (data.download_url) {
                    this.addMessage('bot', `*Download link:* ${window.location.origin}/${data.download_url}`);
                }
            } else {
                this.showNotification(data.error || 'Upload failed!', 'error');
            }
            
        } catch (error) {
            console.error('Upload error:', error);
            this.showNotification('Upload failed! Check your connection.', 'error');
            
            // Provide local analysis as fallback
            this.analyzeFileLocally(file);
        }
    }

    analyzeFileLocally(file) {
        const reader = new FileReader();
        
        reader.onload = (e) => {
            const content = e.target.result;
            const extension = file.name.split('.').pop().toLowerCase();
            
            let analysis = `**📁 Local File Analysis: ${file.name}**\n\n`;
            analysis += `**Size:** ${this.formatFileSize(file.size)}\n`;
            analysis += `**Type:** ${extension.toUpperCase()} file\n`;
            analysis += `**Last Modified:** ${new Date(file.lastModified).toLocaleString()}\n\n`;
            
            if (extension === 'py') {
                const lines = content.split('\n').length;
                analysis += `**Lines of code:** ${lines}\n`;
                analysis += `**Python file detected**\n\n`;
                analysis += "*For detailed analysis, upgrade to Premium or check your connection.*";
            } else {
                analysis += "*File content loaded locally.*\n";
                analysis += "*For AI-powered analysis, please check your connection.*";
            }
            
            this.addMessage('bot', analysis);
        };
        
        reader.readAsText(file);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    addMessage(sender, text) {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender} fade-in`;
        
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = sender === 'user' 
            ? '<i class="fas fa-user"></i>' 
            : '<i class="fas fa-robot"></i>';
        
        const content = document.createElement('div');
        content.className = 'message-content';
        
        const senderName = document.createElement('div');
        senderName.className = 'message-sender';
        senderName.textContent = sender === 'user' 
            ? (this.user.name || 'You') 
            : 'YASIN.PY Assistant';
        
        if (sender === 'bot') {
            senderName.innerHTML += ' <i class="fas fa-check-circle" style="color: var(--success); font-size: 0.8rem;"></i>';
        }
        
        const messageText = document.createElement('div');
        messageText.className = 'message-text';
        
        // Format the text (handle code blocks, links, etc.)
        messageText.innerHTML = this.formatMessageText(text);
        
        // Add copy button for code blocks
        messageText.querySelectorAll('pre').forEach(pre => {
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-code-btn';
            copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
            copyBtn.title = 'Copy code';
            copyBtn.onclick = () => this.copyToClipboard(pre.textContent);
            pre.appendChild(copyBtn);
        });
        
        const messageTime = document.createElement('div');
        messageTime.className = 'message-time';
        messageTime.textContent = new Date().toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        content.appendChild(senderName);
        content.appendChild(messageText);
        content.appendChild(messageTime);
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(content);
        
        chatMessages.appendChild(messageDiv);
        
        // Scroll to bottom with smooth animation
        setTimeout(() => {
            chatMessages.scrollTo({
                top: chatMessages.scrollHeight,
                behavior: 'smooth'
            });
        }, 100);
    }

    formatMessageText(text) {
        // Replace markdown code blocks
        let formatted = text.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, lang, code) => {
            const language = lang || 'python';
            return `<pre class="code-block language-${language}"><code>${this.escapeHtml(code)}</code></pre>`;
        });
        
        // Replace inline code
        formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Replace bold text
        formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        
        // Replace italic text
        formatted = formatted.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        
        // Replace links
        formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // Replace newlines with breaks
        formatted = formatted.replace(/\n/g, '<br>');
        
        // Add syntax highlighting classes
        formatted = formatted.replace(/<pre>/g, '<pre class="syntax-highlight">');
        
        return formatted;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.showNotification('Code copied to clipboard!', 'success', 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
            this.showNotification('Failed to copy code', 'error');
        });
    }

    showTypingIndicator() {
        const chatMessages = document.getElementById('chatMessages');
        if (!chatMessages) return;
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot typing-indicator';
        typingDiv.id = 'typingIndicator';
        
        typingDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <div class="message-sender">YASIN.PY Assistant</div>
                <div class="message-text">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    removeTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    async handleEmailLogin() {
        const emailInput = document.getElementById('loginEmail');
        const passwordInput = document.getElementById('loginPassword');
        
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value.trim() : '';
        
        if (!email || !password) {
            this.showNotification('Please enter both email and password', 'warning');
            return;
        }
        
        // Simple validation
        if (!this.isValidEmail(email)) {
            this.showNotification('Please enter a valid email address', 'warning');
            return;
        }
        
        this.showNotification('Logging in...', 'info');
        
        try {
            const loginData = {
                type: 'login',
                email: email,
                name: email.split('@')[0],
                provider: 'email',
                ip: this.user.ip
            };
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(loginData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.user.loggedIn = true;
                this.user.email = email;
                this.user.name = loginData.name;
                this.user.provider = 'email';
                
                this.saveUserData();
                this.updateUI();
                this.closeModal('loginModal');
                
                this.showNotification(`Welcome ${this.user.name}!`, 'success', 3000);
                this.addMessage('bot', `**🎉 Welcome to YASIN.PY, ${this.user.name}!**\n\nYou're now logged in and ready to use all features.\n\n*Your IP (${this.user.ip}) has been recorded for security.*`);
            } else {
                throw new Error(data.error || 'Login failed');
            }
            
        } catch (error) {
            console.error('Login error:', error);
            this.showNotification('Login failed. Please try again.', 'error');
        }
    }

    async handleFacebookLogin() {
        this.showNotification('Facebook login is being set up. Please use email login for now.', 'info');
        // Facebook SDK integration would go here
    }

    async activatePremium() {
        const codeInput = document.getElementById('subscriptionCode');
        const code = codeInput ? codeInput.value.trim() : '';
        
        if (!code) {
            this.showNotification('Please enter a subscription code', 'warning');
            return;
        }
        
        this.showNotification('Activating premium...', 'info');
        
        try {
            const activationData = {
                type: 'activate_premium',
                code: code,
                user: this.user.email || this.user.userId
            };
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(activationData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.user.subscription = 'premium';
                this.user.tokens = 9999; // Unlimited
                this.saveUserData();
                this.updateUI();
                this.closeModal('subscribeModal');
                
                this.showNotification('🎉 Premium activated successfully!', 'success', 5000);
                
                // Welcome message for premium users
                this.addMessage('bot', `
**✨ WELCOME TO PREMIUM! ✨**

Congratulations on upgrading to YASIN.PY Premium! 🎊

**Your new benefits:**
✅ **Unlimited AI requests** - No daily limits
✅ **Unlimited file uploads** - Upload as many files as you need
✅ **Advanced file analysis** - Detailed code reviews
✅ **Priority support** - Faster responses
✅ **Code generation** - Generate complete projects
✅ **Custom scripts** - Tailored solutions

**Premium Features:**
• Deep code analysis with suggestions
• Project structure recommendations
• Performance optimization tips
• Security audit for your code
• Database design help
• API development guidance

*Thank you for supporting YASIN.PY!*
*Contact @YASIN_VIPXIT for any premium support.*
                `);
                
                // Clear the code input
                if (codeInput) codeInput.value = '';
                
            } else {
                throw new Error(data.error || 'Activation failed');
            }
            
        } catch (error) {
            console.error('Premium activation error:', error);
            this.showNotification(error.message || 'Invalid subscription code', 'error');
        }
    }

    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    showNotification(message, type = 'info', duration = 5000) {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = type === 'success' ? 'check-circle' :
                    type === 'error' ? 'exclamation-circle' :
                    type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to body
        document.body.appendChild(notification);
        
        // Show with animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, duration);
        }
    }

    showUpgradePrompt() {
        this.addMessage('bot', `
**💎 Upgrade to Premium!**

You've reached the free plan limits. Upgrade to unlock:

**✨ Premium Benefits:**
• **Unlimited AI requests** - No daily limits
• **Unlimited file uploads** - Upload any number of files
• **Priority support** - Faster responses from our team
• **Advanced features** - Code generation, file analysis, and more

**🎯 How to Upgrade:**
1. Contact **@YASIN_VIPXIT** on Telegram
2. Choose your plan (Monthly/Yearly)
3. Receive your unique activation code
4. Enter code in the Premium section

**💰 Pricing:**
• **Monthly:** $9.99/month
• **Yearly:** $99.99/year (Save 16%)

*All payments are secure and handled via Telegram.*
*Get your code and start using Premium features immediately!*

👉 Click "Subscribe with Code" button to get started!
        `);
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Create global app instance
    window.yasinApp = new YasinPyApp();
    
    // Add notification styles if not present
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-width: 300px;
                max-width: 400px;
                transform: translateX(150%);
                transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }
            
            .notification.show {
                transform: translateX(0);
            }
            
            .notification-success {
                background: rgba(16, 185, 129, 0.9);
            }
            
            .notification-error {
                background: rgba(239, 68, 68, 0.9);
            }
            
            .notification-warning {
                background: rgba(245, 158, 11, 0.9);
                color: #1f2937;
            }
            
            .notification-info {
                background: rgba(99, 102, 241, 0.9);
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1;
            }
            
            .notification-close {
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                opacity: 0.7;
                transition: opacity 0.2s;
                padding: 5px;
                margin-left: 10px;
            }
            
            .notification-close:hover {
                opacity: 1;
            }
            
            .typing-indicator .typing-dots {
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            
            .typing-indicator .typing-dots span {
                width: 8px;
                height: 8px;
                background: var(--primary);
                border-radius: 50%;
                animation: typing 1.4s infinite ease-in-out;
            }
            
            .typing-indicator .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
            .typing-indicator .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
            
            @keyframes typing {
                0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
                40% { transform: scale(1); opacity: 1; }
            }
            
            .code-block {
                position: relative;
                background: rgba(0, 0, 0, 0.3);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 15px;
                margin: 10px 0;
                overflow-x: auto;
                font-family: 'Roboto Mono', monospace;
                font-size: 0.9em;
                line-height: 1.5;
            }
            
            .copy-code-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.8em;
                transition: all 0.2s;
            }
            
            .copy-code-btn:hover {
                background: rgba(255, 255, 255, 0.2);
                transform: translateY(-1px);
            }
            
            .fade-in {
                animation: fadeIn 0.3s ease forwards;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Add global functions for HTML onclick
    window.showModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
        }
    };
    
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    };
    
    window.contactTelegram = function() {
        window.open('https://t.me/YASIN_VIPXIT', '_blank');
    };
    
    // Add click handler for close buttons
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Prevent form submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    });
    
    console.log('YASIN.PY App Initialized Successfully!');
});