
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    if (!container) return;
    
    const bgColor = type === 'success' 
        ? 'bg-green-50 border-green-200 text-green-700' 
        : 'bg-red-50 border-red-200 text-red-700';
    
    container.innerHTML = `
        <div class="${bgColor} border px-4 py-3 rounded-lg flex items-center gap-2 animate-pulse">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            ${message}
        </div>
    `;
    
    setTimeout(() => container.innerHTML = '', 4000);
}


function getStatusColor(status) {
    const colors = {
        'en_cours': 'bg-blue-500',
        'termine': 'bg-green-500',
        'en_attente': 'bg-yellow-500'
    };
    return colors[status] || 'bg-slate-500';
}



function getStatusBadge(status) {
    const badges = {
        'en_cours': { color: 'bg-blue-100 text-blue-700', label: 'En cours' },
        'termine': { color: 'bg-green-100 text-green-700', label: 'Terminé' },
        'en_attente': { color: 'bg-yellow-100 text-yellow-700', label: 'En attente' }
    };
    
    const badge = badges[status] || badges['en_cours'];
    return `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${badge.color}">${badge.label}</span>`;
}




function getTaskStatusColor(status) {
    const colors = {
        'todo': 'bg-slate-500',
        'in_progress': 'bg-blue-500',
        'done': 'bg-green-500'
    };
    return colors[status] || 'bg-slate-500';
}




function getTaskStatusBadge(status) {
    const badges = {
        'todo': { color: 'bg-slate-100 text-slate-700', label: 'À faire' },
        'in_progress': { color: 'bg-blue-100 text-blue-700', label: 'En cours' },
        'done': { color: 'bg-green-100 text-green-700', label: 'Terminé' }
    };
    
    const badge = badges[status] || badges['todo'];
    return `<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${badge.color}">${badge.label}</span>`;
}


// Déconnexion de l'utilisateur

function logout() {
    if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/users/login-form';
    }
}


//Navigation vers la page des projets

function goToProjects() {
    window.location.href = '/projects';
}


// Vérifie l'authentification de l'utilisateur

function checkAuthentication() {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    
    if (!token || !user.id) {
        window.location.href = '/users/login-form';
        return null;
    }
    
    return { token, user };
}


//Formate une date au format 

function formatDate(dateString) {
    if (!dateString) return 'Not set';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}


// Formate une date au format court
function formatDateShort(dateString) {
    if (!dateString) return 'Not set';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

//Calcule le nombre de jours entre deux dates

function daysBetween(date1, date2) {
    const oneDay = 24 * 60 * 60 * 1000;
    const firstDate = new Date(date1);
    const secondDate = new Date(date2);
    
    return Math.round(Math.abs((firstDate - secondDate) / oneDay));
}


//Calcule le pourcentage d'avancement entre deux dates
function calculateProgress(startDate, endDate) {
    if (!startDate || !endDate) return 0;
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    const now = new Date();
    
    if (now < start) return 0;
    if (now > end) return 100;
    
    const total = end - start;
    const elapsed = now - start;
    
    return Math.round((elapsed / total) * 100);
}


//Requête API sécurisée avec gestion d'erreurs

async function secureAPICall(url, options = {}) {
    const token = localStorage.getItem('token');
    
    if (!token) {
        throw new Error('Non authentifié');
    }
    
    const defaultOptions = {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };
    
    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };
    
    try {
        const response = await fetch(url, mergedOptions);
        
        if (response.status === 401) {
            localStorage.clear();
            window.location.href = '/users/login-form';
            throw new Error('Session expirée');
        }
        
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `Erreur HTTP ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error(' Erreur API:', error);
        throw error;
    }
}



function escapeHTML(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}



function truncate(text, maxLength = 100) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}


//Debounce function pour optimiser les recherches

function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}


async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showMessage(' Copié dans le presse-papier', 'success');
    } catch (error) {
        console.error('Erreur copie:', error);
        showMessage(' Erreur lors de la copie', 'error');
    }
}


//Validation d'email

function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

//Toggle visibility d'un élément
function toggleElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.toggle('hidden');
    }
}


function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

//Génère un ID unique
function generateUniqueId() {
    return Date.now().toString(36) + Math.random().toString(36).substring(2);
}


function getLoadingSpinner(color = 'blue') {
    return `
        <div class="flex items-center justify-center py-8">
            <div class="w-12 h-12 border-4 border-${color}-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
    `;
}


function getEmptyState(icon, title, description, actionButton = '') {
    return `
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                ${icon}
            </div>
            <h3 class="text-xl font-semibold text-slate-700 mb-2">${title}</h3>
            <p class="text-slate-500 mb-6">${description}</p>
            ${actionButton}
        </div>
    `;
}


if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showMessage,
        getStatusColor,
        getStatusBadge,
        getTaskStatusColor,
        getTaskStatusBadge,
        logout,
        goToProjects,
        checkAuthentication,
        formatDate,
        formatDateShort,
        daysBetween,
        calculateProgress,
        secureAPICall,
        escapeHTML,
        truncate,
        debounce,
        copyToClipboard,
        isValidEmail,
        toggleElement,
        scrollToElement,
        generateUniqueId,
        getLoadingSpinner,
        getEmptyState
    };
}