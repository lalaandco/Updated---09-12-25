// cart-handler.js - Universal cart handling for all pages
class UniversalCartHandler {
    constructor() {
        this.apiUrl = 'cart_api.php';
        this.init();
    }

    init() {
        // Update cart count on page load
        this.updateCartCount();
        
        // Bind event listeners
        this.bindEvents();
    }

    bindEvents() {
        // Auto-bind all add-to-cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart-btn') || e.target.closest('.add-to-cart-btn')) {
                e.preventDefault();
                e.stopPropagation();
                
                const button = e.target.classList.contains('add-to-cart-btn') ? e.target : e.target.closest('.add-to-cart-btn');
                const productId = button.getAttribute('data-product-id');
                const quantity = button.getAttribute('data-quantity') || 1;
                
                if (productId) {
                    this.addToCart(productId, quantity);
                }
            }
        });
    }

    async makeRequest(action, data = {}) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const text = await response.text();
            
            // Debug log
            console.log('Raw API Response:', text);
            
            try {
                return JSON.parse(text);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.log('Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
            
        } catch (error) {
            console.error('API Request Error:', error);
            return { success: false, message: error.message };
        }
    }

    async addToCart(productId, quantity = 1) {
        const result = await this.makeRequest('add_to_cart', {
            product_id: productId,
            quantity: quantity
        });

        if (result.success) {
            this.showNotification('Product added to cart!', 'success');
            this.updateCartCount();
        } else {
            this.showNotification(result.message || 'Failed to add product to cart', 'error');
        }

        return result;
    }

    async removeFromCart(productId) {
        const result = await this.makeRequest('remove_from_cart', {
            product_id: productId
        });

        if (result.success) {
            this.showNotification('Product removed from cart', 'success');
            this.updateCartCount();
        } else {
            this.showNotification(result.message || 'Failed to remove product', 'error');
        }

        return result;
    }

    async updateQuantity(productId, quantity) {
        const result = await this.makeRequest('update_quantity', {
            product_id: productId,
            quantity: quantity
        });

        if (result.success) {
            this.updateCartCount();
        } else {
            this.showNotification(result.message || 'Failed to update quantity', 'error');
        }

        return result;
    }

    async getCart() {
        return await this.makeRequest('get_cart');
    }

    async getCartCount() {
        return await this.makeRequest('get_cart_count');
    }

    async updateCartCount() {
        const result = await this.getCartCount();
        
        if (result.success) {
            const cartBadges = document.querySelectorAll('.cart-count, #cart-badge, [data-cart-count]');
            cartBadges.forEach(badge => {
                badge.textContent = result.count;
            });
        }
    }

    showNotification(message, type = 'info') {
        // Simple notification - you can enhance this
        const notification = document.createElement('div');
        notification.className = `cart-notification cart-notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            transition: all 0.3s ease;
            ${type === 'success' ? 'background-color: #4CAF50;' : ''}
            ${type === 'error' ? 'background-color: #f44336;' : ''}
            ${type === 'info' ? 'background-color: #2196F3;' : ''}
        `;

        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
}

// Global cart handler instance
let universalCart;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    universalCart = new UniversalCartHandler();
});

// Legacy functions for backwards compatibility
function addToCart(event, productId, quantity = 1) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    if (universalCart) {
        return universalCart.addToCart(productId, quantity);
    } else {
        console.error('Cart handler not initialized');
    }
}

function updateCartCount() {
    if (universalCart) {
        return universalCart.updateCartCount();
    }
}