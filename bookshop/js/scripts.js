// Form validation functions
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePassword(password) {
    const passwordRegex = /^(?=.*[0-9])(?=.*[!@#$%^&*])[a-zA-Z0-9!@#$%^&*]{6,}$/;
    return passwordRegex.test(password);
}

function validatePhone(phone) {
    const phoneRegex = /^9\d{9}$/;
    return phoneRegex.test(phone);
}

// Customer registration validation
function validateCustomerRegistration() {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    let isValid = true;
    
    // Clear previous errors
    document.querySelectorAll('.error').forEach(el => el.textContent = '');
    
    // Name validation
    if (name.length < 2) {
        document.getElementById('name_error').textContent = 'Name must be at least 2 characters';
        isValid = false;
    }
    
    // Email validation
    if (!validateEmail(email)) {
        document.getElementById('email_error').textContent = 'Please enter a valid email address';
        isValid = false;
    }
    
    // Phone validation
    if (!validatePhone(phone)) {
        document.getElementById('phone_error').textContent = 'Phone must be 10 digits starting with 9';
        isValid = false;
    }
    
    // Password validation
    if (!validatePassword(password)) {
        document.getElementById('password_error').textContent = 'Password must be 6+ chars with 1 number and 1 special character';
        isValid = false;
    }
    
    // Confirm password validation
    if (password !== confirmPassword) {
        document.getElementById('confirm_password_error').textContent = 'Passwords do not match';
        isValid = false;
    }
    
    return isValid;
}

// Confirmation dialogs
function confirmDelete(bookTitle) {
    return confirm(`Are you sure you want to delete "${bookTitle}"?`);
}

function confirmLogout() {
    return confirm('Are you sure you want to logout?');
}

function confirmCancelOrder(orderId) {
    return confirm(`Are you sure you want to cancel order #${orderId}?`);
}

// Clear form fields
function clearForm(formId) {
    document.getElementById(formId).reset();
}

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    // Email validation on blur
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            const errorDiv = document.getElementById('email_error');
            if (email && !validateEmail(email)) {
                errorDiv.textContent = 'Please enter a valid email address';
            } else {
                errorDiv.textContent = '';
            }
        });
    }
    
    // Phone validation on input
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            const phone = this.value.trim();
            const errorDiv = document.getElementById('phone_error');
            if (phone && !validatePhone(phone)) {
                errorDiv.textContent = 'Phone must be 10 digits starting with 9';
            } else {
                errorDiv.textContent = '';
            }
        });
    }
    
    // Password validation on input
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const errorDiv = document.getElementById('password_error');
            if (password && !validatePassword(password)) {
                errorDiv.textContent = 'Password must be 6+ chars with 1 number and 1 special character';
            } else {
                errorDiv.textContent = '';
            }
        });
    }
});

function togglePassword(fieldId) {
    // Toggles between 'password' and 'text' input types
    // Updates icon and tooltip accordingly
}
