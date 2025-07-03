// Gestion du toggle sidebar
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
    });
}

// Navigation corrigée - permettre la navigation normale
const navLinks = document.querySelectorAll('.nav-link');

navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
        // Ne pas empêcher la navigation normale
        // Juste marquer visuellement le lien comme actif
        
        // Retirer la classe active de tous les liens
        navLinks.forEach(l => l.classList.remove('active'));
        
        // Ajouter la classe active au lien cliqué
        this.classList.add('active');
        
        // Sauvegarder l'état dans localStorage pour la persistance
        const href = this.getAttribute('href');
        if (href) {
            localStorage.setItem('activeNavLink', href);
        }
    });
});

// Restaurer l'état actif après le chargement de la page
function restoreActiveNav() {
    const currentPage = window.location.pathname.split('/').pop();
    const savedActiveLink = localStorage.getItem('activeNavLink');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        
        // Vérifier si c'est la page actuelle
        if (linkHref === currentPage || linkHref === savedActiveLink) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// Restaurer l'état au chargement
window.addEventListener('load', restoreActiveNav);

// Responsive: Gestion mobile
function handleResize() {
    if (window.innerWidth <= 768) {
        if (sidebar) sidebar.classList.add('mobile');
    } else {
        if (sidebar) sidebar.classList.remove('mobile');
    }
}

window.addEventListener('resize', handleResize);
handleResize(); // Initial check

// Animation des stats au chargement
function animateStats() {
    const statValues = document.querySelectorAll('.stat-value');
    
    statValues.forEach(stat => {
        const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
        let currentValue = 0;
        const increment = finalValue / 50; // Calcule un pas pour que l'animation dure environ 1 seconde (50 * 20ms)
        
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                stat.textContent = finalValue.toLocaleString('fr-FR');
                clearInterval(timer);
            } else {
                stat.textContent = Math.floor(currentValue).toLocaleString('fr-FR');
            }
        }, 20); // Intervalle de 20ms pour une animation fluide
    });
}

// Démarrer l'animation après le chargement de la page
window.addEventListener('load', () => {
    // Un petit délai peut rendre l'animation plus agréable à l'œil
    setTimeout(animateStats, 300);
});