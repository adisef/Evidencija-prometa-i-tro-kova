/**
 * Prikazuje obavijest korisniku
 * @param {string} message - Tekst poruke
 * @param {string} type - Tip poruke (success, danger, warning, info)
 * @param {number} duration - Trajanje prikaza u milisekundama (default: 3000)
 */
function showAlert(message, type = 'success', duration = 3000) {
    // Ukloni postojeće obavijesti
    const existingAlerts = document.querySelectorAll('.alert-floating');
    existingAlerts.forEach(alert => alert.remove());

    // Kreiraj novu obavijest
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-floating alert-dismissible fade show`;
    alert.role = 'alert';
    
    // Dodaj ikonu ovisno o tipu poruke
    let icon = '';
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle me-2"></i>';
            break;
        case 'danger':
            icon = '<i class="fas fa-exclamation-circle me-2"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
            break;
        case 'info':
            icon = '<i class="fas fa-info-circle me-2"></i>';
            break;
    }
    
    alert.innerHTML = `
        ${icon}${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Dodaj CSS za pozicioniranje
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.style.minWidth = '300px';
    alert.style.maxWidth = '500px';

    // Dodaj obavijest u dokument
    document.body.appendChild(alert);

    // Postavi timer za automatsko uklanjanje
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, duration);
}

/**
 * Formatira datum i vrijeme u lokalni format
 * @param {string} dateString - Datum u formatu YYYY-MM-DD HH:MM:SS
 * @param {boolean} includeTime - Da li uključiti vrijeme u rezultat
 * @returns {string} Formatirani datum i vrijeme
 */
function formatDateTime(dateString, includeTime = true) {
    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: includeTime ? '2-digit' : undefined,
        minute: includeTime ? '2-digit' : undefined
    };
    return date.toLocaleString('bs-BA', options);
}

/**
 * Validira formu i prikazuje greške
 * @param {HTMLFormElement} form - Form element
 * @returns {boolean} True ako je forma validna, false ako nije
 */
function validateForm(form) {
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    return true;
}