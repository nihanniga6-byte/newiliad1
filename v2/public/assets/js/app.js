/**
 * Dr. Zohrabi Nutrition Clinic - Main JavaScript
 * Version: 2.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize mobile sidebar toggle
    initSidebar();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize confirm dialogs
    initConfirmDialogs();
});

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize mobile sidebar toggle
 */
function initSidebar() {
    const toggler = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggler && sidebar) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (!sidebar.contains(event.target) && !toggler.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
}

/**
 * Initialize form validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

/**
 * Initialize confirm dialogs for delete actions
 */
function initConfirmDialogs() {
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(event) {
            const message = this.getAttribute('data-confirm') || 'آیا از انجام این عملیات مطمئن هستید؟';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
}

/**
 * Show toast notification
 * 
 * @param {string} type Toast type (success, error)
 * @param {string} message Toast message
 * @param {number} duration Duration in milliseconds
 */
function showToast(type, message, duration = 5000) {
    // Create toast container if not exists
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<i class="bi bi-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i>' +
                      '<span>' + message + '</span>';
    
    container.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(function() {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, duration);
}

/**
 * Format file size
 * 
 * @param {number} bytes File size in bytes
 * @returns {string} Formatted file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    
    return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + units[i];
}

/**
 * Debounce function
 * 
 * @param {Function} func Function to debounce
 * @param {number} wait Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * Search users (AJAX)
 */
function searchUsers(query) {
    if (query.length < 2) {
        return;
    }
    
    fetch('/admin/users/search?q=' + encodeURIComponent(query))
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('Search results:', data.data);
            }
        })
        .catch(function(error) {
            console.error('Search error:', error);
        });
}

/**
 * Toggle user status
 * 
 * @param {number} userId User ID
 * @param {string} action Action (ban/unban)
 */
function toggleUserStatus(userId, action) {
    if (!confirm('آیا از انجام این عملیات مطمئن هستید؟')) {
        return;
    }
    
    fetch('/admin/users/' + userId + '/toggle-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            showToast('success', 'User status updated');
            setTimeout(function() {
                location.reload();
            }, 1000);
        } else {
            showToast('error', data.error || 'Failed to update status');
        }
    })
    .catch(function(error) {
        showToast('error', 'An error occurred');
        console.error('Toggle status error:', error);
    });
}

/**
 * Delete user
 * 
 * @param {number} userId User ID
 */
function deleteUser(userId) {
    if (!confirm('آیا از حذف این کاربر مطمئن هستید؟')) {
        return;
    }
    
    fetch('/admin/users/' + userId + '/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            showToast('success', 'User deleted');
            setTimeout(function() {
                location.reload();
            }, 1000);
        } else {
            showToast('error', data.error || 'Failed to delete user');
        }
    })
    .catch(function(error) {
        showToast('error', 'An error occurred');
        console.error('Delete user error:', error);
    });
}

/**
 * Preview avatar before upload
 */
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.querySelector('.avatar-img') || document.querySelector('.avatar-placeholder');
            if (preview) {
                if (preview.classList.contains('avatar-placeholder')) {
                    const img = document.createElement('img');
                    img.className = 'avatar-img';
                    img.src = e.target.result;
                    img.alt = 'آواتار';
                    preview.parentNode.replaceChild(img, preview);
                } else {
                    preview.src = e.target.result;
                }
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Auto-submit form on select change (for filters)
 */
document.addEventListener('change', function(event) {
    if (event.target.matches('.auto-submit')) {
        event.target.closest('form').submit();
    }
});
