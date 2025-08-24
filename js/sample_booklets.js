// Sample Booklets Management JavaScript
const SampleBookletsManager = {
    config: {
        orderModalId: 'orderModal',
        shippingModalId: 'shippingModal',
        orderFormId: 'orderForm',
        shippingFormId: 'shippingForm',
        orderHandlerUrl: 'handlers/sample_booklets_handler.php',
        shippingHandlerUrl: 'handlers/shipping_handler.php',
        toastDuration: 3000
    },

    init() {
        this.setupFormHandlers();
        this.setupModalEvents();
        this.setupEditButtons();
        this.setDefaultDate();
    },

    setupEditButtons() {
        // Setup event listeners for edit buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.edit-btn')) {
                const btn = e.target.closest('.edit-btn');
                const data = btn.dataset;
                
                this.openEditModal(
                    data.orderId,
                    data.orderNumber,
                    data.customerName,
                    data.address,
                    data.email,
                    data.phone,
                    data.productType,
                    data.status,
                    data.trackingNumber,
                    data.dateOrdered,
                    data.notes
                );
            }
        });
    },

    setDefaultDate() {
        // Set today's date as default for new orders
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateOrdered').value = today;
    },

    openAddModal() {
        // Reset form and set title for adding
        document.getElementById(this.config.orderFormId).reset();
        document.getElementById('orderModalLabel').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Order';
        document.getElementById('orderId').value = '';
        
        // Set default date
        this.setDefaultDate();
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById(this.config.orderModalId));
        modal.show();
    },

    openEditModal(id, orderNumber, customerName, address, email, phone, productType, status, trackingNumber, dateOrdered, notes) {
        console.log('Opening edit modal for order:', id);
        console.log('Data:', {id, orderNumber, customerName, address, email, phone, productType, status, trackingNumber, dateOrdered, notes});
        
        // Populate form fields
        const fields = {
            'orderId': id,
            'orderNumber': orderNumber,
            'customerName': customerName,
            'address': address,
            'email': email,
            'phone': phone,
            'productType': productType,
            'orderStatus': status,
            'trackingNumberEdit': trackingNumber || '',
            'dateOrdered': dateOrdered,
            'notes': notes || ''
        };

        Object.entries(fields).forEach(([fieldId, value]) => {
            const element = document.getElementById(fieldId);
            if (element) {
                element.value = value;
                console.log(`Set ${fieldId} to:`, value);
            } else {
                console.error(`Element with ID ${fieldId} not found`);
            }
        });

        // Set modal title for editing
        document.getElementById('orderModalLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Order';
        
        // Show modal
        try {
            const modalElement = document.getElementById(this.config.orderModalId);
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                console.log('Modal shown successfully');
            } else {
                console.error('Modal element not found:', this.config.orderModalId);
            }
        } catch (error) {
            console.error('Error showing modal:', error);
        }
    },

    openShippingModal(orderId, customerName, customerEmail) {
        // Set order info
        document.getElementById('shippingOrderId').value = orderId;
        document.getElementById('shippingCustomerName').textContent = customerName;
        document.getElementById('shippingCustomerEmail').textContent = customerEmail;
        
        // Reset form
        document.getElementById(this.config.shippingFormId).reset();
        document.getElementById('shippingOrderId').value = orderId;
        
        // Set today's date as default ship date
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateShipped').value = today;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById(this.config.shippingModalId));
        modal.show();
    },

    setupFormHandlers() {
        // Order form handler
        const orderForm = document.getElementById(this.config.orderFormId);
        if (orderForm) {
            orderForm.addEventListener('submit', (e) => this.handleOrderSubmit(e));
        }

        // Shipping form handler  
        const shippingForm = document.getElementById(this.config.shippingFormId);
        if (shippingForm) {
            shippingForm.addEventListener('submit', (e) => this.handleShippingSubmit(e));
        }
    },

    setupModalEvents() {
        // Reset forms when modals are hidden
        const orderModal = document.getElementById(this.config.orderModalId);
        if (orderModal) {
            orderModal.addEventListener('hidden.bs.modal', () => {
                document.getElementById(this.config.orderFormId).reset();
            });
        }

        const shippingModal = document.getElementById(this.config.shippingModalId);
        if (shippingModal) {
            shippingModal.addEventListener('hidden.bs.modal', () => {
                document.getElementById(this.config.shippingFormId).reset();
            });
        }
    },

    async handleOrderSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            this.setButtonState(submitBtn, '<i class="fas fa-spinner fa-spin me-2"></i>Saving...', true);
            
            const response = await fetch(this.config.orderHandlerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form)
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Server returned invalid JSON. Check console for details.');
            }
            
            if (data.success) {
                this.showToast('success', data.message || 'Order saved successfully!');
                this.closeModalAndRefresh(this.config.orderModalId);
            } else {
                throw new Error(data.message || 'Failed to save order');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showToast('error', error.message);
        } finally {
            this.setButtonState(submitBtn, originalText, false);
        }
    },

    async handleShippingSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            this.setButtonState(submitBtn, '<i class="fas fa-spinner fa-spin me-2"></i>Shipping...', true);
            
            const response = await fetch(this.config.shippingHandlerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form)
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Server returned invalid JSON. Check console for details.');
            }
            
            if (data.success) {
                this.showToast('success', data.message || 'Order shipped and customer notified!');
                this.closeModalAndRefresh(this.config.shippingModalId);
            } else {
                throw new Error(data.message || 'Failed to ship order');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showToast('error', error.message);
        } finally {
            this.setButtonState(submitBtn, originalText, false);
        }
    },

    setButtonState(button, text, disabled) {
        button.innerHTML = text;
        button.disabled = disabled;
    },

    showToast(type, message) {
        const isError = type === 'error';
        const bgClass = isError ? 'bg-danger' : 'bg-success';
        const icon = isError ? 'exclamation-circle' : 'check-circle';
        const title = isError ? 'Error' : 'Success';

        const toast = document.createElement('div');
        toast.className = 'toast show position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast-header ${bgClass} text-white">
                <i class="fas fa-${icon} me-2"></i>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;

        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), this.config.toastDuration);
    },

    closeModalAndRefresh(modalId) {
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById(modalId));
        if (modalInstance) {
            modalInstance.hide();
        }
        
        // Force immediate refresh
        setTimeout(() => {
            console.log('Refreshing page to show updated data...');
            window.location.reload(true); // Force reload from server
        }, 500);
    },

    filterOrders(status) {
        const table = document.getElementById('ordersTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const show = status === 'all' || rowStatus === status;
            row.style.display = show ? '' : 'none';
        });

        // Update button states
        const filterButtons = document.querySelectorAll('.btn-group button');
        filterButtons.forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
    },

    async checkDeliveryStatus() {
        const checkButton = document.querySelector('button[onclick="checkDeliveryStatus()"]');
        if (!checkButton) return;
        
        const originalText = checkButton.innerHTML;
        
        try {
            // Show loading state
            this.setButtonState(checkButton, '<i class="fas fa-spinner fa-spin me-2"></i>Checking...', true);
            
            const response = await fetch('handlers/delivery_check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ manual_check: true })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            console.log('Delivery check response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Server returned invalid response. Check console for details.');
            }
            
            if (data.success) {
                this.showToast('success', data.message || 'Delivery status check completed');
                // Reload page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                throw new Error(data.message || 'Failed to check delivery status');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showToast('error', error.message);
        } finally {
            this.setButtonState(checkButton, originalText, false);
        }
    },

    async deleteOrder(orderId, orderNumber, customerName) {
        // Show confirmation dialog
        const confirmMessage = `Are you sure you want to delete this order?\n\nOrder: ${orderNumber}\nCustomer: ${customerName}\n\nThis action cannot be undone.`;
        
        if (!confirm(confirmMessage)) {
            return;
        }

        try {
            const response = await fetch(this.config.orderHandlerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'delete',
                    order_id: orderId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.showToast('success', `Order ${orderNumber} has been deleted successfully`);
                // Reload page to show updated list
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to delete order');
            }
        } catch (error) {
            console.error('Error deleting order:', error);
            this.showToast('error', 'Error deleting order: ' + error.message);
        }
    }
};

// Global functions for backward compatibility
function openAddModal() {
    SampleBookletsManager.openAddModal();
}

function openEditModal(id, orderNumber, customerName, address, email, phone, productType, status, trackingNumber, dateOrdered, notes) {
    SampleBookletsManager.openEditModal(id, orderNumber, customerName, address, email, phone, productType, status, trackingNumber, dateOrdered, notes);
}

function openShippingModal(orderId, customerName, customerEmail) {
    SampleBookletsManager.openShippingModal(orderId, customerName, customerEmail);
}

function filterOrders(status) {
    SampleBookletsManager.filterOrders(status);
}

function checkDeliveryStatus() {
    SampleBookletsManager.checkDeliveryStatus();
}

function deleteOrder(orderId, orderNumber, customerName) {
    SampleBookletsManager.deleteOrder(orderId, orderNumber, customerName);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing SampleBookletsManager...');
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    console.log('Modal constructor available:', typeof bootstrap?.Modal !== 'undefined');
    SampleBookletsManager.init();
    console.log('SampleBookletsManager initialized');
});
