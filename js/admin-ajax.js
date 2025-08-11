// Admin AJAX functionality for smooth updates without page reloads

class AdminAjax {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupNotifications();
    }

    bindEvents() {
        // Service update forms
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('service-update-form')) {
                e.preventDefault();
                this.updateService(e.target);
            }
        });

        // Service visibility toggles
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('service-visibility-toggle')) {
                this.toggleServiceVisibility(e.target);
            }
        });

        // Import service form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('import-service-form')) {
                e.preventDefault();
                this.importService(e.target);
            }
        });

        // Bulk visibility form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('bulk-visibility-form')) {
                e.preventDefault();
                this.bulkVisibilityUpdate(e.target);
            }
        });

        // User update forms
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('user-update-form')) {
                e.preventDefault();
                this.updateUser(e.target);
            }
        });

        // User delete buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-user-btn')) {
                e.preventDefault();
                this.deleteUser(e.target);
            }
        });

        // Provider management forms and buttons
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('add-provider-form')) {
                e.preventDefault();
                this.addProvider(e.target);
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('sync-services-btn')) {
                e.preventDefault();
                this.syncProviderServices(e.target);
            }
            if (e.target.classList.contains('toggle-provider-btn')) {
                e.preventDefault();
                this.toggleProviderStatus(e.target);
            }
            if (e.target.classList.contains('delete-provider-btn')) {
                e.preventDefault();
                this.deleteProvider(e.target);
            }
            if (e.target.classList.contains('test-connection-btn')) {
                e.preventDefault();
                this.testProviderConnection(e.target);
            }
        });
    }

    setupNotifications() {
        // Create notification container if it doesn't exist
        if (!document.getElementById('ajax-notifications')) {
            const notificationContainer = document.createElement('div');
            notificationContainer.id = 'ajax-notifications';
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
        `;
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.getElementById('ajax-notifications').appendChild(notification);

        // Auto-remove after duration
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }

    showLoading(element) {
        const originalText = element.innerHTML;
        element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        element.disabled = true;
        return originalText;
    }

    hideLoading(element, originalText) {
        element.innerHTML = originalText;
        element.disabled = false;
    }

    async updateService(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn);

        const formData = new FormData(form);
        formData.append('action', 'update_service');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Update the row with new data if needed
                this.updateServiceRow(form, result.data);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while updating the service', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async toggleServiceVisibility(toggle) {
        const serviceId = toggle.dataset.serviceId;
        const visible = toggle.checked ? 1 : 0;

        const formData = new FormData();
        formData.append('action', 'toggle_service_visibility');
        formData.append('service_id', serviceId);
        formData.append('visible', visible);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success', 3000);
            } else {
                this.showNotification(result.message, 'danger');
                // Revert toggle state
                toggle.checked = !toggle.checked;
            }
        } catch (error) {
            this.showNotification('An error occurred while updating visibility', 'danger');
            toggle.checked = !toggle.checked;
            console.error('Error:', error);
        }
    }

    async importService(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn);

        const formData = new FormData(form);
        formData.append('action', 'import_service');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Optionally refresh the services table or add the new service row
                setTimeout(() => {
                    location.reload(); // For now, reload to show new service
                }, 1500);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while importing the service', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async bulkVisibilityUpdate(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn);

        const formData = new FormData(form);
        formData.append('action', 'bulk_visibility');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'info');
                // Update all visibility toggles
                this.updateAllVisibilityToggles(result.visible);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred during bulk update', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async updateUser(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn);

        const formData = new FormData(form);
        formData.append('action', 'update_user');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while updating the user', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async deleteUser(button) {
        const userId = button.dataset.userId;
        const userName = button.dataset.userName;

        if (!confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
            return;
        }

        const originalText = this.showLoading(button);

        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Remove the user row from the table
                const userRow = button.closest('tr');
                if (userRow) {
                    userRow.style.transition = 'opacity 0.3s ease';
                    userRow.style.opacity = '0';
                    setTimeout(() => userRow.remove(), 300);
                }
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while deleting the user', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(button, originalText);
        }
    }

    updateServiceRow(form, data) {
        // Update the form fields with the latest data
        const row = form.closest('tr');
        if (row) {
            // Add a subtle animation to show the update
            row.style.transition = 'background-color 0.3s ease';
            row.style.backgroundColor = 'rgba(40, 167, 69, 0.1)';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 2000);
        }
    }

    updateAllVisibilityToggles(visible) {
        const toggles = document.querySelectorAll('.service-visibility-toggle');
        toggles.forEach(toggle => {
            toggle.checked = visible === 1;
        });
    }

    async addProvider(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = this.showLoading(submitBtn);

        const formData = new FormData(form);
        formData.append('action', 'add_provider');

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                form.reset();
                // Refresh the page to show new provider
                setTimeout(() => location.reload(), 1500);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while adding the provider', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(submitBtn, originalText);
        }
    }

    async syncProviderServices(button) {
        const providerId = button.dataset.providerId;
        const originalText = this.showLoading(button);

        const formData = new FormData();
        formData.append('action', 'sync_provider_services');
        formData.append('provider_id', providerId);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success', 8000);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while syncing services', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(button, originalText);
        }
    }

    async toggleProviderStatus(button) {
        const providerId = button.dataset.providerId;
        const currentStatus = button.dataset.currentStatus;
        const originalText = this.showLoading(button);

        const formData = new FormData();
        formData.append('action', 'toggle_provider_status');
        formData.append('provider_id', providerId);
        formData.append('current_status', currentStatus);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Update button and status badge
                this.updateProviderStatusDisplay(providerId, result.data.new_status);
                button.dataset.currentStatus = result.data.new_status;
                button.textContent = result.data.new_status === 'active' ? 'Deactivate' : 'Activate';
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while updating provider status', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(button, originalText);
        }
    }

    async deleteProvider(button) {
        const providerId = button.dataset.providerId;
        const providerRow = button.closest('tr');
        const providerName = providerRow.querySelector('td:first-child').textContent;

        if (!confirm(`Are you sure you want to delete provider "${providerName}"? This action cannot be undone.`)) {
            return;
        }

        const originalText = this.showLoading(button);

        const formData = new FormData();
        formData.append('action', 'delete_provider');
        formData.append('provider_id', providerId);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Remove the provider row with animation
                providerRow.style.transition = 'opacity 0.3s ease';
                providerRow.style.opacity = '0';
                setTimeout(() => providerRow.remove(), 300);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while deleting the provider', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(button, originalText);
        }
    }

    async testProviderConnection(button) {
        const providerId = button.dataset.providerId;
        const originalText = this.showLoading(button);

        const formData = new FormData();
        formData.append('action', 'test_provider_connection');
        formData.append('provider_id', providerId);

        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                const message = `${result.message}. Response time: ${result.data.response_time}, Balance: ${result.data.balance}`;
                this.showNotification(message, 'success', 6000);
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('An error occurred while testing connection', 'danger');
            console.error('Error:', error);
        } finally {
            this.hideLoading(button, originalText);
        }
    }

    updateProviderStatusDisplay(providerId, newStatus) {
        const providerRow = document.querySelector(`tr[data-provider-id="${providerId}"]`);
        if (providerRow) {
            const statusBadge = providerRow.querySelector('.badge');
            if (statusBadge) {
                statusBadge.className = `badge bg-${newStatus === 'active' ? 'success' : 'danger'}`;
                statusBadge.textContent = newStatus;
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AdminAjax();
});

// Add some CSS for smooth animations
const style = document.createElement('style');
style.textContent = `
    .service-update-form {
        transition: all 0.3s ease;
    }
    
    .service-update-form:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }
    
    .btn:disabled {
        opacity: 0.6;
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
    
    .fade-out {
        animation: fadeOut 0.3s ease forwards;
    }
    
    @keyframes fadeOut {
        to {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
`;
document.head.appendChild(style);
