// User-facing AJAX functionality for smooth updates without page reloads

class UserAjax {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupNotifications();
    }

    bindEvents() {
        // Profile update form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('profile-update-form')) {
                e.preventDefault();
                this.updateProfile(e.target);
            }
        });

        // Order placement form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('order-form')) {
                e.preventDefault();
                this.placeOrder(e.target);
            }
        });

        // Order status refresh buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('refresh-order-btn')) {
                e.preventDefault();
                this.refreshOrderStatus(e.target);
            }
        });

        // Add funds form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('add-funds-form')) {
                e.preventDefault();
                this.addFunds(e.target);
            }
        });

        // Support ticket form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('support-ticket-form')) {
                e.preventDefault();
                this.submitSupportTicket(e.target);
            }
        });

        // Support reply form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('support-reply-form')) {
                e.preventDefault();
                this.replySupportTicket(e.target);
            }
        });

        // Service selection change
        document.addEventListener('change', (e) => {
            if (e.target.id === 'service') {
                this.updateServiceInfo(e.target);
            }
        });

        // Currency toggle buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('currency-toggle-btn')) {
                e.preventDefault();
                this.toggleCurrency(e.target);
            }
        });

        // Language toggle buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('language-toggle-btn')) {
                e.preventDefault();
                this.toggleLanguage(e.target);
            }
        });
    }

    setupNotifications() {
        // Create notification container if it doesn't exist
        if (!document.getElementById('user-notifications')) {
            const notificationContainer = document.createElement('div');
            notificationContainer.id = 'user-notifications';
            notificationContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(notificationContainer);
        }
    }

    showNotification(message, type = 'success', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.style.cssText = `
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            background: ${type === 'success' ? 'rgba(40, 167, 69, 0.9)' : 
                        type === 'danger' ? 'rgba(220, 53, 69, 0.9)' : 
                        type === 'warning' ? 'rgba(255, 193, 7, 0.9)' : 
                        'rgba(13, 202, 240, 0.9)'};
        `;
        
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                              type === 'danger' ? 'exclamation-circle' : 
                              type === 'warning' ? 'exclamation-triangle' : 
                              'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.getElementById('user-notifications').appendChild(notification);

        // Auto-remove after duration
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    }

    showLoading(element, text = 'Loading...') {
        const originalText = element.innerHTML;
        element.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${text}`;
        element.disabled = true;
        return originalText;
    }

    hideLoading(element, originalText) {
        element.innerHTML = originalText;
        element.disabled = false;
    }

    async updateProfile(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn, 'Updating...');

        const formData = new FormData(form);
        formData.append('action', 'update_profile');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Clear password fields
                const passwordFields = form.querySelectorAll('input[type="password"]');
                passwordFields.forEach(field => field.value = '');
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while updating your profile', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async placeOrder(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn, 'Placing Order...');

        const formData = new FormData(form);
        formData.append('action', 'place_order');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Update balance display if exists
                this.updateBalanceDisplay(result.data.new_balance);
                // Reset form
                form.reset();
                // Optionally redirect to dashboard after a delay
                setTimeout(() => {
                    if (confirm('Order placed successfully! Would you like to view your orders?')) {
                        window.location.href = 'dashboard.php';
                    }
                }, 2000);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while placing your order', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async refreshOrderStatus(button) {
        const orderId = button.dataset.orderId;
        const originalText = this.showLoading(button, 'Refreshing...');

        const formData = new FormData();
        formData.append('action', 'refresh_order_status');
        formData.append('order_id', orderId);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success', 3000);
                // Update status in the table
                this.updateOrderStatus(orderId, result.data.new_status);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while refreshing order status', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(button, originalText);
        }
    }

    async addFunds(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn, 'Processing...');

        const formData = new FormData(form);
        formData.append('action', 'add_funds');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Update balance display
                this.updateBalanceDisplay(result.data.new_balance);
                // Reset form
                form.reset();
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while adding funds', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async submitSupportTicket(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn, 'Submitting...');

        const formData = new FormData(form);
        formData.append('action', 'submit_support_ticket');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Reset form
                form.reset();
                // Optionally refresh the page to show new ticket
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while submitting your ticket', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async replySupportTicket(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn, 'Sending...');

        const formData = new FormData(form);
        formData.append('action', 'reply_support_ticket');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Reset form
                form.reset();
                // Optionally refresh to show new reply
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while sending your reply', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    updateServiceInfo(selectElement) {
        const serviceId = selectElement.value;
        const serviceInfoDiv = document.getElementById('service-info');
        
        if (!serviceId || !serviceInfoDiv) return;

        // Find service data (assuming it's available in a global variable or data attribute)
        const serviceData = this.getServiceData(serviceId);
        
        if (serviceData) {
            serviceInfoDiv.innerHTML = `
                <div class="service-details">
                    <h5>${serviceData.name}</h5>
                    <p>${serviceData.description}</p>
                    <div class="price-info">
                        <span class="price">Price: $${serviceData.price}</span>
                        <span class="min-max">Min: ${serviceData.min} | Max: ${serviceData.max}</span>
                    </div>
                </div>
            `;
            serviceInfoDiv.style.display = 'block';
        }
    }

    getServiceData(serviceId) {
        // This would typically come from a global variable set by PHP
        // For now, return null - this should be implemented based on your data structure
        return window.servicesData ? window.servicesData[serviceId] : null;
    }

    toggleCurrency(button) {
        const currency = button.dataset.currency;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('currency', currency);
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Redirect with new currency
        window.location.href = currentUrl.toString();
    }

    toggleLanguage(button) {
        const language = button.dataset.language;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('lang', language);
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Redirect with new language
        window.location.href = currentUrl.toString();
    }

    updateBalanceDisplay(newBalance) {
        const balanceElements = document.querySelectorAll('.balance-display, .user-balance');
        balanceElements.forEach(element => {
            // Add animation
            element.style.transition = 'all 0.3s ease';
            element.style.transform = 'scale(1.1)';
            element.style.color = '#28a745';
            
            // Update value
            element.textContent = `$${parseFloat(newBalance).toFixed(2)}`;
            
            // Reset animation
            setTimeout(() => {
                element.style.transform = 'scale(1)';
                element.style.color = '';
            }, 300);
        });
    }

    updateOrderStatus(orderId, newStatus) {
        const statusElement = document.querySelector(`[data-order-id="${orderId}"] .order-status`);
        if (statusElement) {
            // Add animation
            statusElement.style.transition = 'all 0.3s ease';
            statusElement.style.transform = 'scale(1.1)';
            
            // Update status
            statusElement.textContent = newStatus;
            statusElement.className = `order-status status-${newStatus.toLowerCase().replace(/[^a-z0-9]/g, '-')}`;
            
            // Reset animation
            setTimeout(() => {
                statusElement.style.transform = 'scale(1)';
            }, 300);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new UserAjax();
});

// Add CSS for smooth animations and notifications
const style = document.createElement('style');
style.textContent = `
    .user-ajax-form {
        transition: all 0.3s ease;
    }
    
    .user-ajax-form:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .alert {
        animation: slideInRight 0.3s ease;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .service-details {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .price-info {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        font-size: 0.9em;
    }
    
    .status-completed { color: #28a745; }
    .status-pending { color: #ffc107; }
    .status-in-progress { color: #17a2b8; }
    .status-failed { color: #dc3545; }
    
    .balance-display, .user-balance {
        font-weight: bold;
    }
    
    .order-status {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        font-weight: bold;
        text-transform: uppercase;
    }
`;
document.head.appendChild(style);
