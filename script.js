document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const alertBox = document.getElementById('loginAlert');
    const messageMap = {
        missing_fields: 'Please fill in all fields.',
        invalid_credentials: 'Invalid email or password.',
        inactive_account: 'Your account is inactive. Please contact admin.',
        session_expired: 'Your session expired. Please sign in again.',
        server: 'Server error. Please check database settings and try again.',
    };

    const errorKey = params.get('error');
    if (alertBox && errorKey && messageMap[errorKey]) {
        alertBox.textContent = messageMap[errorKey];
        alertBox.classList.add('is-visible');
    }
});
