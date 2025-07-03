<?php
// sidebar_etudiant.php
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="WhatsApp Image 2025-05-15 à 00.54.47_42b83ab0.jpg" alt="SYGECOS" id="sidebar-logo-img">
        </div>
        <span class="sidebar-title">SYGECOS</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Navigation</div>
            <div class="nav-items-wrapper">
                <div class="nav-item">
                    <a href="dashboard_etudiant.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <span class="nav-text">Tableau de bord</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Mon Espace</div>
            <div class="nav-items-wrapper">
               
                <div class="nav-item">
                    <a href="informations_personnelles.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <span class="nav-text">Mes Informations</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Évaluations</div>
            <div class="nav-items-wrapper">
                <div class="nav-item">
                    <a href="mes_evaluations.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <span class="nav-text">Mes Évaluations</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="resultats_evaluations.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="nav-text">Mes Résultats</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Rapports</div>
            <div class="nav-items-wrapper">
                <div class="nav-item">
                    <a href="mes_rapports.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="nav-text">Mes Rapports</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="depot_rapport.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-upload"></i>
                        </div>
                        <span class="nav-text">Déposer un rapport</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Communication</div>
            <div class="nav-items-wrapper">
                <div class="nav-item">
                    <a href="messagerie.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <span class="nav-text">Messagerie</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="annonces.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <span class="nav-text">Annonces</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
</aside>

<style>
    /* === VARIABLES CSS === */
    :root {
        /* Couleurs Primaires */
        --primary-50: #f8fafc;
        --primary-100: #f1f5f9;
        --primary-200: #e2e8f0;
        --primary-300: #cbd5e1;
        --primary-400: #94a3b8;
        --primary-500: #64748b;
        --primary-600: #475569;
        --primary-700: #334155;
        --primary-800: #1e293b;
        --primary-900: #0f172a;

        /* Couleurs d'Accent Bleu */
        --accent-50: #eff6ff;
        --accent-100: #dbeafe;
        --accent-200: #bfdbfe;
        --accent-300: #93c5fd;
        --accent-400: #60a5fa;
        --accent-500: #3b82f6;
        --accent-600: #2563eb;
        --accent-700: #1d4ed8;
        --accent-800: #1e40af;
        --accent-900: #1e3a8a;

        /* Couleurs Sémantiques */
        --success-500: #22c55e;
        --warning-500: #f59e0b;
        --error-500: #ef4444;
        --info-500: #3b82f6;
        --secondary-100: #dcfce7; /* Specific for success badge */
        --secondary-600: #16a34a; /* Specific for success badge text */


        /* Couleurs Neutres */
        --white: #ffffff;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;

        /* Layout */
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --topbar-height: 70px;

        /* Typographie */
        --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
        --text-xs: 0.75rem;
        --text-sm: 0.875rem;
        --text-base: 1rem;
        --text-lg: 1.125rem;
        --text-xl: 1.25rem;
        --text-2xl: 1.5rem;
        --text-3xl: 1.875rem;

        /* Espacement */
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.25rem;
        --space-6: 1.5rem;
        --space-8: 2rem;
        --space-10: 2.5rem;
        --space-12: 3rem;
        --space-16: 4rem;

        /* Bordures */
        --radius-sm: 0.25rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --radius-2xl: 1.5rem;
        --radius-3xl: 2rem;

        /* Ombres */
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.05);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);

        /* Transitions */
        --transition-fast: 150ms ease-in-out;
        --transition-normal: 250ms ease-in-out;
        --transition-slow: 350ms ease-in-out;
    }

    /* === SIDEBAR === */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        color: white;
        z-index: 1000;
        transition: all var(--transition-normal);
        overflow-y: scroll; /* Changed to scroll to hide scrollbar */
        overflow-x: hidden;
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;  /* Firefox */
    }

    /* Hide scrollbar for Webkit browsers (Chrome, Safari) */
    .sidebar::-webkit-scrollbar {
        display: none;
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    .sidebar-header {
        padding: var(--space-6) var(--space-6);
        border-bottom: 1px solid var(--primary-700);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .sidebar-logo {
        width: 60px; /* Augmentation de la largeur du conteneur */
        height: 60px; /* Augmentation de la hauteur du conteneur */
        background: var(--accent-500);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden; /* Important pour que l'image ne dépasse pas */
    }

    #sidebar-logo-img {
        display: block; /* Empêche les marges implicites autour de l'image */
        max-width: 100%; /* L'image ne dépassera pas la largeur du conteneur */
        max-height: 100%; /* L'image ne dépassera pas la hauteur du conteneur */
        height:100; /* Maintient le rapport hauteur/largeur */
        width: 100; /* Maintient le rapport hauteur/largeur */
        object-fit: contain; /* Assure que toute l'image est visible, avec des espaces si nécessaire */
        /* Si ton logo est sombre sur fond sombre de la sidebar, tu peux enlever ou commenter cette ligne: */
        filter: brightness(0) invert(1); /* Pour les logos SVG blancs sur fond coloré */
    }
    .sidebar-title {
        font-size: var(--text-xl);
        font-weight: 700;
        white-space: nowrap;
        opacity: 1;
        transition: opacity var(--transition-normal);
    }

    .sidebar.collapsed .sidebar-title {
        opacity: 0;
    }

    .sidebar-nav {
        padding: var(--space-4) 0;
    }

    .nav-section {
        margin-bottom: var(--space-2); /* Adjusted margin for collapsed sections */
    }

    .nav-section-title {
        padding: var(--space-2) var(--space-6);
        font-size: var(--text-xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--primary-400);
        white-space: nowrap;
        opacity: 1;
        transition: opacity var(--transition-normal);
        cursor: pointer; /* Make title clickable */
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .nav-section-title:hover { /* Hover effect for section titles */
        background-color: rgba(255, 255, 255, 0.05); /* Slightly lighter background on hover */
    }

    .sidebar.collapsed .nav-section-title {
        opacity: 0;
    }

    /* Remove caret icon styles as we are removing the icon itself */
    /* .nav-section-title .caret-icon {
        transition: transform var(--transition-normal);
    }

    .nav-section-title.active .caret-icon {
        transform: rotate(180deg);
    } */

    .nav-items-wrapper {
        max-height: 0;
        overflow: hidden;
        transition: max-height var(--transition-normal) ease-out;
    }

    .nav-section-title.active + .nav-items-wrapper {
        max-height: 500px; /* Adjust this value based on max content height */
        transition: max-height var(--transition-normal) ease-in;
    }

    .sidebar.collapsed .nav-items-wrapper {
        display: none; /* Hide content when sidebar is collapsed */
    }

    .nav-item {
        margin-bottom: var(--space-1);
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: var(--space-3) var(--space-6);
        color: var(--primary-200);
        text-decoration: none;
        transition: all var(--transition-fast);
        position: relative;
        gap: var(--space-3);
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .nav-link.active {
        background: var(--accent-600);
        color: white;
    }

    .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--accent-300);
    }

    .nav-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .nav-text {
        white-space: nowrap;
        opacity: 1;
        transition: opacity var(--transition-normal);
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
    }

    .nav-submenu {
        margin-left: var(--space-8);
        margin-top: var(--space-2);
        border-left: 2px solid var(--primary-700);
        padding-left: var(--space-4);
    }

    .sidebar.collapsed .nav-submenu {
        display: none;
    }

    .nav-submenu .nav-link {
        padding: var(--space-2) var(--space-4);
        font-size: var(--text-sm);
    }

    /* === TOPBAR === */
    .topbar {
        height: var(--topbar-height);
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        padding: 0 var(--space-6);
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .sidebar-toggle {
        width: 40px;
        height: 40px;
        border: none;
        background: var(--gray-100);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        color: var(--gray-600);
    }

    .sidebar-toggle:hover {
        background: var(--gray-200);
        color: var(--gray-800);
    }

    .page-title {
        font-size: var(--text-xl);
        font-weight: 600;
        color: var(--gray-800);
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .topbar-button {
        width: 40px;
        height: 40px;
        border: none;
        background: var(--gray-100);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        color: var(--gray-600);
        position: relative;
    }

    .topbar-button:hover {
        background: var(--gray-200);
        color: var(--gray-800);
    }

    .notification-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 18px;
        height: 18px;
        background: var(--error-500);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 600;
        color: white;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: background var(--transition-fast);
    }

    .user-menu:hover {
        background: var(--gray-100);
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--gray-800);
        line-height: 1.2;
    }

    .user-role {
        font-size: var(--text-xs);
        color: var(--gray-500);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const navSectionTitles = document.querySelectorAll('.nav-section-title');
        const navLinks = document.querySelectorAll('.nav-link');

        // Function to set the active link and expand its parent section
        function setActiveLink() {
            // Remove 'active' class from all nav-links first
            navLinks.forEach(link => {
                link.classList.remove('active');
            });

            // Get the current URL path
            const currentPath = window.location.pathname.split('/').pop();

            // Find the nav-link that matches the current path and add 'active' class
            navLinks.forEach(link => {
                const linkHref = link.getAttribute('href');
                if (linkHref && linkHref.includes(currentPath)) {
                    link.classList.add('active');

                    // If the active link is inside a collapsed section, expand that section
                    let parentWrapper = link.closest('.nav-items-wrapper');
                    if (parentWrapper) {
                        let parentSectionTitle = parentWrapper.previousElementSibling;
                        if (parentSectionTitle) {
                            parentSectionTitle.classList.add('active');
                            parentWrapper.style.maxHeight = parentWrapper.scrollHeight + "px";
                        }
                    }
                }
            });
        }

        // Call setActiveLink on page load
        setActiveLink();

        // Add click listener to each nav link to update active state immediately
        // (Optional: if you want immediate visual feedback before page navigates)
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                // This part ensures the active class updates immediately visually.
                // The browser will then navigate to the new page as normal.
                navLinks.forEach(item => item.classList.remove('active'));
                this.classList.add('active');

                // If the link is inside a collapsible section, ensure it expands
                let parentWrapper = this.closest('.nav-items-wrapper');
                if (parentWrapper) {
                    let parentSectionTitle = parentWrapper.previousElementSibling;
                    if (parentSectionTitle && !parentSectionTitle.classList.contains('active')) {
                        parentSectionTitle.classList.add('active');
                        parentWrapper.style.maxHeight = parentWrapper.scrollHeight + "px";
                    }
                }
            });
        });


        // Handle section title clicks for collapsing/expanding
        navSectionTitles.forEach(title => {
            title.addEventListener('click', function() {
                this.classList.toggle('active');

                const itemsWrapper = this.nextElementSibling;
                if (itemsWrapper) {
                    if (this.classList.contains('active')) {
                        itemsWrapper.style.maxHeight = itemsWrapper.scrollHeight + "px";
                    } else {
                        itemsWrapper.style.maxHeight = "0";
                    }
                }
            });
        });

        // Initialize all sections as expanded by default on load, then apply active link logic
        // This ensures all sections are open when the page loads, similar to sidebar.php behavior
        navSectionTitles.forEach(title => {
            title.classList.add('active');
            const itemsWrapper = title.nextElementSibling;
            if (itemsWrapper) {
                itemsWrapper.style.maxHeight = itemsWrapper.scrollHeight + "px";
            }
        });
        // Re-call setActiveLink after expanding all to ensure correct link is highlighted
        setActiveLink();

    });
</script>