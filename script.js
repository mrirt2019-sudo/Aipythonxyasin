// YASIN.PY Main JavaScript File
class YasinPyApp {
    constructor() {
        this.user = {
            loggedIn: false,
            subscription: 'free',
            tokens: 10,
            email: null,
            name: 'Guest',
            ip: null,
            filesUploaded: 0,
            lastUploadDate: null
        };
        
        this.chatHistory = [];
        this.apiEndpoint = 'api.php';
        this.init();
    }

    async init() {
        this.loadUserData();
        this.setupEventListeners();
        this.updateUI();
        
        // Get user IP
        this.user.ip = await this.getUserIP();
    }

    loadUserData() {
        const saved = localStorage.getItem('yasinpy_user');
        if (saved) {
            this.user = {...this.user, ...JSON.parse(saved)};
        }
    }

    saveUserData() {
        localStorage.setItem('yasinpy_user', JSON.stringify(this.user));
    }

    async getUserIP() {
        try {
            const response = await fetch('https://api.ipify.org?format=json');
            const data = await response.json();
            return data.ip;
        } catch (error) {
            return 'unknown';
        }
    }

    setupEventListeners() {
        // Message sending
        document.getElementById('sendBtn').addEventListener('click', () => this.sendMessage());
        
        // File upload
        document.getElementById('fileInput').addEventListener('change', (e) => {
            if (e.target.files[0]) this.uploadFile(e.target.files[0]);
        });
    }

    updateUI() {
        // Update user avatar
        const avatar = document.getElementById('userAvatar');
        avatar.textContent = this.user.name.charAt(0).toUpperCase();
        
        // Update subscription badge
        const badge = document.getElementById('subscriptionBadge');
        badge.textContent = this.user.subscription.toUpperCase();
        badge.style.background = this.user.subscription === 'premium' ? 'var(--premium)' : 'var(--gray)';
        
        // Update upload limit display
        const limitText = document.querySelector('.upload-limit');
        if (limitText) {
            limitText.textContent = this.user.subscription === 'premium' 
                ? 'Premium: Unlimited uploads' 
                : 'Free: 1 file/day • Premium: Unlimited';
        }
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Check limits for free users
        if (this.user.subscription === 'free' && this.user.tokens <= 0) {
            this.showNotification('Daily limit reached! Upgrade to Premium for unlimited requests.', 'warning');
            return;
        }
        
        // Add user message to chat
        this.addMessage('user', message);
        input.value = '';
        
        if (this.user.subscription === 'free') {
            this.user.tokens--;
            this.saveUserData();
        }
        
        // Show typing indicator
        this.showTypingIndicator();
        
        try {
            // Send to AI API
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    type: 'chat',
                    message: message,
                    user: this.user.email,
                    subscription: this.user.subscription
                })
            });
            
            const data = await response.json();
            
            // Remove typing indicator
            this.removeTypingIndicator();
            
            // Add AI response
            this.addMessage('bot', data.response || 'I apologize, but I encountered an error.');
            
        } catch (error) {
            this.removeTypingIndicator();
            this.addMessage('bot', 'Error connecting to AI service. Please try again.');
            console.error('API Error:', error);
        }
    }

    async uploadFile(file) {
        // Check file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            this.showNotification('File too large! Maximum size is 5MB.', 'error');
            return;
        }
        
        // Check upload limit for free users
        const today = new Date().toDateString();
        if (this.user.subscription === 'free') {
            if (this.user.lastUploadDate === today && this.user.filesUploaded >= 1) {
                this.showNotification('Free users can only upload 1 file per day. Upgrade to Premium!', 'warning');
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
        formData.append('user', this.user.email);
        formData.append('subscription', this.user.subscription);
        
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.user.filesUploaded++;
                this.saveUserData();
                
                this.showNotification('File uploaded successfully! AI is analyzing it...', 'success');
                
                // Add file analysis to chat
                this.addMessage('bot', `**File Analysis: ${file.name}**\n\n${data.analysis}`);
            } else {
                this.showNotification(data.error || 'Upload failed!', 'error');
            }
            
        } catch (error) {
            this.showNotification('Upload failed! Check your connection.', 'error');
        }
    }

    addMessage(sender, text) {
        const chatMessages = document.getElementById('chatMessages');
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar';
        avatar.innerHTML = sender === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
        
        const content = document.createElement('div');
        content.className = 'message-content';
        
        const senderName = document.createElement('div');
        senderName.className = 'message-sender';
        senderName.textContent = sender === 'user' ? this.user.name : 'YASIN.PY Assistant';
        
        const messageText = document.createElement('div');
        messageText.className = 'message-text';
        
        // Format code blocks
        if (text.includes('```')) {
            const parts = text.split('```');
            parts.forEach((part, index) => {
                if (index % 2 === 1) {
                    const codeBlock = document.createElement('pre');
                    codeBlock.className = 'message-code';
                    codeBlock.textContent = part;
                    messageText.appendChild(codeBlock);
                } else if (part.trim()) {
                    const textNode = document.createTextNode(part);
                    messageText.appendChild(textNode);
                }
            });
        } else {
            messageText.innerHTML = this.formatText(text);
        }
        
        content.appendChild(senderName);
        content.appendChild(messageText);
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(content);
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    formatText(text) {
        // Convert markdown-like formatting
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    showTypingIndicator() {
        const chatMessages = document.getElementById('chatMessages');
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot';
        typingDiv.id = 'typingIndicator';
        
        typingDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                <div class="message-sender">YASIN.PY Assistant</div>
                <div class="message-text">
                    <span class="typing-dots">
                        <span>.</span><span>.</span><span>.</span>
                    </span>
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

    async loginUser(userData) {
        this.user = {
            ...this.user,
            loggedIn: true,
            name: userData.name || userData.email.split('@')[0],
            email: userData.email,
            provider: userData.provider
        };
        
        this.saveUserData();
        this.updateUI();
        this.closeModal('loginModal');
        
        // Send login info to Telegram bot
        await this.sendLoginInfo(userData);
        
        this.showNotification(`Welcome ${this.user.name}!`, 'success');
    }

    async sendLoginInfo(userData) {
        try {
            await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    type: 'login',
                    email: userData.email,
                    name: userData.name,
                    provider: userData.provider,
                    ip: this.user.ip,
                    timestamp: new Date().toISOString()
                })
            });
        } catch (error) {
            console.error('Failed to send login info:', error);
        }
    }

    async activatePremium(code) {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    type: 'activate_premium',
                    code: code,
                    user: this.user.email
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.user.subscription = 'premium';
                this.user.tokens = 9999; // Unlimited
                this.saveUserData();
                this.updateUI();
                this.closeModal('subscribeModal');
                
                this.showNotification('Premium activated successfully! 🎉', 'success');
                this.addMessage('bot', '**Premium Activated!** 🎉\n\nWelcome to Premium! You now have:\n• Unlimited AI requests\n• Unlimited file uploads\n• Priority support\n• Advanced code generation\n• File analysis features');
            } else {
                this.showNotification(data.error || 'Invalid subscription code!', 'error');
            }
            
        } catch (error) {
            this.showNotification('Activation failed! Try again.', 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
        `;
        
        // Add styles if not present
        if (!document.querySelector('#notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    padding: 1rem 1.5rem;
                    border-radius: 10px;
                    color: white;
                    font-weight: 500;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                }
                .notification-success { background: rgba(16, 185, 129, 0.9); }
                .notification-error { background: rgba(239, 68, 68, 0.9); }
                .notification-warning { background: rgba(245, 158, 11, 0.9); }
                .notification-info { background: rgba(99, 102, 241, 0.9); }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
}

// Initialize app
let yasinApp;
document.addEventListener('DOMContentLoaded', () => {
    yasinApp = new YasinPyApp();
});

// Global functions for HTML onclick
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (yasinApp) yasinApp.closeModal(modalId);
}

function contactTelegram() {
    window.open('https://t.me/YASIN_VIPXIT', '_blank');
}