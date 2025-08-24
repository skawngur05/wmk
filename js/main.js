// Lead Management System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize form validation
    initializeFormValidation();
    
    // Auto-refresh dashboard data every 5 minutes
    if (window.location.pathname.includes('dashboard.php')) {
        setInterval(function() {
            // You could implement auto-refresh here if needed
        }, 300000); // 5 minutes
    }
    
    // Set up search functionality
    initializeSearch();
    
    // Set up date picker defaults
    initializeDatePickers();
});

// Form validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// Search functionality
function initializeSearch() {
    const searchInputs = document.querySelectorAll('input[type="search"], input[name="search"]');
    searchInputs.forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                // Could implement live search here
            }, 500);
        });
    });
}

// Date picker initialization
function initializeDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Set min date to today for follow-up dates
        if (input.name === 'next_followup_date') {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('min', today);
        }
    });
}

// Quick actions for lead management
function quickUpdateLead(leadId, field, value) {
    const formData = new FormData();
    formData.append('lead_id', leadId);
    formData.append('field', field);
    formData.append('value', value);
    
    fetch('update_lead.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Lead updated successfully', 'success');
            // Refresh the page or update the UI
            location.reload();
        } else {
            showNotification('Error updating lead', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error', 'error');
    });
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Phone number formatting
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 6) {
        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
    } else if (value.length >= 3) {
        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
    }
    input.value = value;
}

// Add phone formatting to phone inputs
document.addEventListener('input', function(e) {
    if (e.target.type === 'tel' || e.target.name === 'phone') {
        formatPhoneNumber(e.target);
    }
});

// Confirmation dialogs for important actions
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Export functionality (if needed)
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N for new lead
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (document.querySelector('a[href="add_lead.php"]')) {
            window.location.href = 'add_lead.php';
        }
    }
    
    // Ctrl/Cmd + D for dashboard
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        if (document.querySelector('a[href="dashboard.php"]')) {
            window.location.href = 'dashboard.php';
        }
    }
});

// Local storage for form data (prevent data loss)
function saveFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    localStorage.setItem(formId + '_data', JSON.stringify(data));
}

function restoreFormData(formId) {
    const form = document.getElementById(formId);
    const savedData = localStorage.getItem(formId + '_data');
    
    if (!form || !savedData) return;
    
    try {
        const data = JSON.parse(savedData);
        Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = data[key];
            }
        });
    } catch (e) {
        console.error('Error restoring form data:', e);
    }
}

function clearSavedFormData(formId) {
    localStorage.removeItem(formId + '_data');
}

// Auto-save form data periodically
let autoSaveInterval;
function startAutoSave(formId) {
    autoSaveInterval = setInterval(() => {
        saveFormData(formId);
    }, 30000); // Save every 30 seconds
}

function stopAutoSave() {
    if (autoSaveInterval) {
        clearInterval(autoSaveInterval);
    }
}

// Initialize auto-save for forms
if (document.querySelector('form')) {
    const form = document.querySelector('form');
    if (form.id) {
        startAutoSave(form.id);
        
        // Clear saved data on successful submit
        form.addEventListener('submit', function() {
            setTimeout(() => {
                clearSavedFormData(form.id);
            }, 1000);
        });
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    stopAutoSave();
});

// Responsive table handling
function makeTablesResponsive() {
    const tables = document.querySelectorAll('.table');
    tables.forEach(table => {
        if (!table.closest('.table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
}

// Initialize responsive tables
makeTablesResponsive();
