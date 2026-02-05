// Dither background effect for hero section
class DitherBackground {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        
        this.ctx = this.canvas.getContext('2d');
        this.mouseX = 0;
        this.mouseY = 0;
        this.targetMouseX = 0;
        this.targetMouseY = 0;
        this.dots = [];
        this.dotSize = 2;
        this.spacing = 8;
        
        this.init();
        this.animate();
        this.setupEventListeners();
    }
    
    init() {
        this.resize();
        this.createDots();
    }
    
    resize() {
        const rect = this.canvas.parentElement.getBoundingClientRect();
        this.canvas.width = rect.width;
        this.canvas.height = rect.height;
        this.centerX = this.canvas.width / 2;
        this.centerY = this.canvas.height / 2;
    }
    
    createDots() {
        this.dots = [];
        const cols = Math.ceil(this.canvas.width / this.spacing);
        const rows = Math.ceil(this.canvas.height / this.spacing);
        
        for (let y = 0; y < rows; y++) {
            for (let x = 0; x < cols; x++) {
                this.dots.push({
                    x: x * this.spacing + this.spacing / 2,
                    y: y * this.spacing + this.spacing / 2,
                    baseX: x * this.spacing + this.spacing / 2,
                    baseY: y * this.spacing + this.spacing / 2,
                    size: this.dotSize
                });
            }
        }
    }
    
    setupEventListeners() {
        const heroSection = this.canvas.parentElement;
        
        heroSection.addEventListener('mousemove', (e) => {
            const rect = heroSection.getBoundingClientRect();
            this.targetMouseX = e.clientX - rect.left;
            this.targetMouseY = e.clientY - rect.top;
        });
        
        heroSection.addEventListener('mouseleave', () => {
            this.targetMouseX = this.centerX;
            this.targetMouseY = this.centerY;
        });
        
        window.addEventListener('resize', () => {
            this.resize();
            this.createDots();
        });
        
        // Initialize mouse position to center
        this.targetMouseX = this.centerX;
        this.targetMouseY = this.centerY;
        this.mouseX = this.centerX;
        this.mouseY = this.centerY;
    }
    
    animate() {
        // Smooth mouse following
        this.mouseX += (this.targetMouseX - this.mouseX) * 0.1;
        this.mouseY += (this.targetMouseY - this.mouseY) * 0.1;
        
        // Clear canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw dither pattern
        this.dots.forEach(dot => {
            const dx = this.mouseX - dot.x;
            const dy = this.mouseY - dot.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            const maxDistance = 150;
            
            let opacity = 1;
            let size = dot.size;
            
            if (distance < maxDistance) {
                const influence = 1 - (distance / maxDistance);
                size = dot.size + influence * 3;
                opacity = 0.3 + influence * 0.7;
                
                // Add some wave effect
                const wave = Math.sin(distance * 0.05 + Date.now() * 0.002) * 0.5 + 0.5;
                size += wave * influence * 2;
            } else {
                opacity = 0.1;
            }
            
            this.ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
            this.ctx.beginPath();
            this.ctx.arc(dot.x, dot.y, size, 0, Math.PI * 2);
            this.ctx.fill();
        });
        
        requestAnimationFrame(() => this.animate());
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new DitherBackground('ditherCanvas');
});
