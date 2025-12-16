// 3D Animated Background for YASIN.PY
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('bg-canvas');
    const ctx = canvas.getContext('2d');
    
    // Set canvas size
    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    
    // Particle system
    class Particle {
        constructor() {
            this.reset();
            this.z = Math.random() * 1000;
        }
        
        reset() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.z = Math.random() * 1000;
            this.size = Math.random() * 2 + 0.5;
            this.speedZ = Math.random() * 0.3 + 0.1;
            this.color = this.getColor();
        }
        
        getColor() {
            const colors = [
                'rgba(99, 102, 241, 0.8)',    // Primary
                'rgba(16, 185, 129, 0.6)',    // Secondary
                'rgba(245, 158, 11, 0.4)',    // Premium
                'rgba(239, 68, 68, 0.3)'      // Danger
            ];
            return colors[Math.floor(Math.random() * colors.length)];
        }
        
        update() {
            this.z -= this.speedZ;
            
            if (this.z <= 0) {
                this.reset();
                this.z = 1000;
            }
        }
        
        draw() {
            const perspective = 1000;
            const scale = perspective / (perspective + this.z);
            const x2d = (this.x - canvas.width / 2) * scale + canvas.width / 2;
            const y2d = (this.y - canvas.height / 2) * scale + canvas.height / 2;
            
            ctx.beginPath();
            ctx.fillStyle = this.color;
            ctx.arc(x2d, y2d, this.size * scale, 0, Math.PI * 2);
            ctx.fill();
            
            // Draw connections
            for (let particle of particles) {
                if (particle !== this) {
                    const dx = this.x - particle.x;
                    const dy = this.y - particle.y;
                    const dz = this.z - particle.z;
                    const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);
                    
                    if (distance < 100) {
                        const scale1 = perspective / (perspective + this.z);
                        const scale2 = perspective / (perspective + particle.z);
                        const x1 = (this.x - canvas.width / 2) * scale1 + canvas.width / 2;
                        const y1 = (this.y - canvas.height / 2) * scale1 + canvas.height / 2;
                        const x2 = (particle.x - canvas.width / 2) * scale2 + canvas.width / 2;
                        const y2 = (particle.y - canvas.height / 2) * scale2 + canvas.height / 2;
                        
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(99, 102, 241, ${0.1 * (1 - distance / 100)})`;
                        ctx.lineWidth = 0.3;
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.stroke();
                    }
                }
            }
        }
    }
    
    // Create particles
    const particles = [];
    const particleCount = 80;
    
    for (let i = 0; i < particleCount; i++) {
        particles.push(new Particle());
    }
    
    // Animation loop
    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw gradient background
        const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
        gradient.addColorStop(0, 'rgba(15, 23, 42, 0.1)');
        gradient.addColorStop(1, 'rgba(30, 41, 59, 0.1)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Update and draw particles
        particles.forEach(particle => {
            particle.update();
            particle.draw();
        });
        
        // Add floating Python logo
        drawPythonLogo();
        
        requestAnimationFrame(animate);
    }
    
    function drawPythonLogo() {
        const time = Date.now() * 0.001;
        const x = canvas.width * 0.8 + Math.cos(time) * 50;
        const y = canvas.height * 0.2 + Math.sin(time * 1.5) * 30;
        const size = 60 + Math.sin(time * 2) * 10;
        
        ctx.save();
        ctx.translate(x, y);
        
        // Python logo shape
        ctx.beginPath();
        ctx.arc(0, 0, size * 0.4, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255, 215, 0, 0.1)';
        ctx.fill();
        
        ctx.beginPath();
        ctx.arc(size * 0.2, -size * 0.1, size * 0.15, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(99, 102, 241, 0.3)';
        ctx.fill();
        
        ctx.restore();
    }
    
    // Start animation
    animate();
});