<?php
// dashboard_commission.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('loginForm.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Tableau de Bord Commission</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* === VARIABLES CSS === */
        :root {
            --primary-50: #f8fafc; --primary-100: #f1f5f9; --primary-200: #e2e8f0; --primary-300: #cbd5e1; --primary-400: #94a3b8; --primary-500: #64748b; --primary-600: #475569; --primary-700: #334155; --primary-800: #1e293b; --primary-900: #0f172a;
            --accent-50: #eff6ff; --accent-100: #dbeafe; --accent-200: #bfdbfe; --accent-300: #93c5fd; --accent-400: #60a5fa; --accent-500: #3b82f6; --accent-600: #2563eb; --accent-700: #1d4ed8; --accent-800: #1e40af; --accent-900: #1e3a8a;
            --secondary-50: #f0fdf4; --secondary-100: #dcfce7; --secondary-500: #22c55e; --secondary-600: #16a34a;
            --success-500: #22c55e; --warning-500: #f59e0b; --error-500: #ef4444; --info-500: #3b82f6;
            --white: #ffffff; --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db; --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
            --sidebar-width: 280px; --sidebar-collapsed-width: 80px; --topbar-height: 70px;
            --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
            --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-lg: 1.125rem; --text-xl: 1.25rem; --text-2xl: 1.5rem; --text-3xl: 1.875rem;
            --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-5: 1.25rem; --space-6: 1.5rem; --space-8: 2rem; --space-10: 2.5rem; --space-12: 3rem; --space-16: 4rem;
            --radius-sm: 0.25rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem; --radius-2xl: 1.5rem; --radius-3xl: 2rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.05); --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --transition-fast: 150ms ease-in-out; --transition-normal: 250ms ease-in-out; --transition-slow: 350ms ease-in-out;
        }

        /* === RESET === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-primary); background-color: var(--gray-50); color: var(--gray-800); overflow-x: hidden; }

        /* === LAYOUT PRINCIPAL === */
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-collapsed-width); }

        /* === SIDEBAR === */
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%); color: white; z-index: 1000; transition: all var(--transition-normal); overflow-y: auto; overflow-x: hidden; }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar::-webkit-scrollbar { width: 4px; } .sidebar::-webkit-scrollbar-track { background: var(--primary-900); } .sidebar::-webkit-scrollbar-thumb { background: var(--primary-600); border-radius: 2px; }
        .sidebar-header { padding: var(--space-6); border-bottom: 1px solid var(--primary-700); display: flex; align-items: center; gap: var(--space-3); }
        .sidebar-logo { width: 40px; height: 40px; background: var(--accent-500); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; } .sidebar-logo img { width: 28px; height: 28px; object-fit: contain; filter: brightness(0) invert(1); }
        .sidebar-title { font-size: var(--text-xl); font-weight: 700; white-space: nowrap; opacity: 1; transition: opacity var(--transition-normal); } .sidebar.collapsed .sidebar-title { opacity: 0; }
        .sidebar-nav { padding: var(--space-4) 0; }
        .nav-section { margin-bottom: var(--space-6); }
        .nav-section-title { padding: var(--space-2) var(--space-6); font-size: var(--text-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary-400); white-space: nowrap; opacity: 1; transition: opacity var(--transition-normal); } .sidebar.collapsed .nav-section-title { opacity: 0; }
        .nav-item { margin-bottom: var(--space-1); }
        .nav-link { display: flex; align-items: center; padding: var(--space-3) var(--space-6); color: var(--primary-200); text-decoration: none; transition: all var(--transition-fast); position: relative; gap: var(--space-3); }
        .nav-link:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .nav-link.active { background: var(--accent-600); color: white; }
        .nav-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--accent-300); }
        .nav-icon { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-text { white-space: nowrap; opacity: 1; transition: opacity var(--transition-normal); } .sidebar.collapsed .nav-text { opacity: 0; }
        .nav-submenu { margin-left: var(--space-8); margin-top: var(--space-2); border-left: 2px solid var(--primary-700); padding-left: var(--space-4); } .sidebar.collapsed .nav-submenu { display: none; }
        .nav-submenu .nav-link { padding: var(--space-2) var(--space-4); font-size: var(--text-sm); }

        /* === TOPBAR === */
        .topbar { height: var(--topbar-height); background: var(--white); border-bottom: 1px solid var(--gray-200); padding: 0 var(--space-6); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; }
        .topbar-left { display: flex; align-items: center; gap: var(--space-4); }
        .sidebar-toggle { width: 40px; height: 40px; border: none; background: var(--gray-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition-fast); color: var(--gray-600); } .sidebar-toggle:hover { background: var(--gray-200); color: var(--gray-800); }
        .page-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-800); }
        .topbar-right { display: flex; align-items: center; gap: var(--space-4); }
        .topbar-button { width: 40px; height: 40px; border: none; background: var(--gray-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition-fast); color: var(--gray-600); position: relative; } .topbar-button:hover { background: var(--gray-200); color: var(--gray-800); }
        .notification-badge { position: absolute; top: -2px; right: -2px; width: 18px; height: 18px; background: var(--error-500); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; color: white; }
        .user-menu { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-3); border-radius: var(--radius-lg); cursor: pointer; transition: background var(--transition-fast); } .user-menu:hover { background: var(--gray-100); }
        .user-info { text-align: right; } .user-name { font-size: var(--text-sm); font-weight: 600; color: var(--gray-800); line-height: 1.2; } .user-role { font-size: var(--text-xs); color: var(--gray-500); }

        /* === PAGE CONTENT === */
        .page-content { padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-8); display: flex; justify-content: space-between; align-items: center; }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); margin-top: var(--space-2); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-8); }
        .stat-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .stat-number { font-size: var(--text-3xl); font-weight: 700; color: var(--accent-600); }
        .stat-label { color: var(--gray-600); font-size: var(--text-sm); margin-top: var(--space-2); }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-8); }
        .card { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow: hidden; }
        .card-header { padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
        .card-title { font-size: var(--text-lg); font-weight: 600; color: var(--gray-900); }
        .card-body { padding: var(--space-6); }
        .card-footer { padding: var(--space-4) var(--space-6); border-top: 1px solid var(--gray-200); background: var(--gray-50); }

        .task-list { list-style: none; }
        .task-item { padding: var(--space-3) 0; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; gap: var(--space-3); }
        .task-item:last-child { border-bottom: none; }
        .task-checkbox { width: 16px; height: 16px; accent-color: var(--accent-500); }
        .task-content { flex: 1; }
        .task-title { font-weight: 600; color: var(--gray-800); margin-bottom: var(--space-1); }
        .task-meta { font-size: var(--text-xs); color: var(--gray-500); display: flex; gap: var(--space-3); }
        .task-priority { font-size: var(--text-xs); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #ecfdf5; color: #059669; }

        .recent-reports { width: 100%; border-collapse: collapse; }
        .recent-reports th, .recent-reports td { padding: var(--space-3) var(--space-4); text-align: left; border-bottom: 1px solid var(--gray-200); font-size: var(--text-sm); }
        .recent-reports th { background-color: var(--gray-50); font-weight: 600; color: var(--gray-700); }
        .recent-reports tbody tr:hover { background-color: var(--gray-50); }
        .report-status { padding: var(--space-1) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-xs); font-weight: 600; }
        .status-pending { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #ecfdf5; color: #059669; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }

        .btn { padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-sm); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover:not(:disabled) { background-color: var(--accent-700); }
        .btn-success { background-color: var(--success-500); color: white; } .btn-success:hover:not(:disabled) { background-color: var(--secondary-600); }
        .btn-outline { background-color: transparent; color: var(--accent-600); border: 1px solid var(--accent-600); } .btn-outline:hover { background-color: var(--accent-50); }
        .btn-sm { padding: var(--space-1) var(--space-2); font-size: var(--text-xs); }

        .empty-state { text-align: center; padding: var(--space-16); color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .card-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: var(--space-4); }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar pour les membres de la commission -->
        <?php include 'sidebar_commision.php'; ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; ?>

            <div class="page-content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title-main">Tableau de Bord Commission</h1>
                        <p class="page-subtitle">Vue d'ensemble des activités de la commission</p>
                    </div>
                    <div>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouvelle Réunion
                        </button>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="rapportsEnAttente">0</div>
                        <div class="stat-label">Rapports en attente</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="rapportsApprouves">0</div>
                        <div class="stat-label">Rapports approuvés</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="reunionsPlanifiees">0</div>
                        <div class="stat-label">Réunions planifiées</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="messagesNonLus">0</div>
                        <div class="stat-label">Messages non lus</div>
                    </div>
                </div>

                <!-- Cartes principales -->
                <div class="card-grid">
                    <!-- Carte Tâches à faire -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-tasks"></i> Tâches à faire
                            </h3>
                            <button class="btn btn-sm btn-outline">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                        </div>
                        <div class="card-body">
                            <ul class="task-list" id="taskList">
                                <li class="task-item">
                                    <input type="checkbox" class="task-checkbox">
                                    <div class="task-content">
                                        <div class="task-title">Examiner le rapport de stage de Dupont</div>
                                        <div class="task-meta">
                                            <span>Échéance: 15/06/2025</span>
                                            <span class="task-priority priority-high">Haute</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="task-item">
                                    <input type="checkbox" class="task-checkbox">
                                    <div class="task-content">
                                        <div class="task-title">Préparer l'ordre du jour pour la réunion</div>
                                        <div class="task-meta">
                                            <span>Échéance: 10/06/2025</span>
                                            <span class="task-priority priority-medium">Moyenne</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="task-item">
                                    <input type="checkbox" class="task-checkbox">
                                    <div class="task-content">
                                        <div class="task-title">Rédiger le compte rendu de la dernière réunion</div>
                                        <div class="task-meta">
                                            <span>Échéance: 05/06/2025</span>
                                            <span class="task-priority priority-low">Basse</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm btn-outline">Voir toutes les tâches</button>
                        </div>
                    </div>

                    <!-- Carte Rapports récents -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-file-alt"></i> Rapports récents
                            </h3>
                            <button class="btn btn-sm btn-outline">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </div>
                        <div class="card-body">
                            <table class="recent-reports">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Jean Dupont</td>
                                        <td>02/06/2025</td>
                                        <td><span class="report-status status-pending">En attente</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Marie Martin</td>
                                        <td>28/05/2025</td>
                                        <td><span class="report-status status-approved">Approuvé</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Pierre Durand</td>
                                        <td>25/05/2025</td>
                                        <td><span class="report-status status-rejected">Rejeté</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm btn-outline">Voir tous les rapports</button>
                        </div>
                    </div>
                </div>

                <!-- Prochaines réunions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-alt"></i> Prochaines réunions
                        </h3>
                        <button class="btn btn-sm btn-outline">
                            <i class="fas fa-plus"></i> Planifier
                        </button>
                    </div>
                    <div class="card-body">
                        <table class="recent-reports">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sujet</th>
                                    <th>Lieu</th>
                                    <th>Participants</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>10/06/2025 - 14h00</td>
                                    <td>Examen des rapports de stage</td>
                                    <td>Salle B12</td>
                                    <td>5/7</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline">
                                            <i class="fas fa-info-circle"></i> Détails
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>15/06/2025 - 10h30</td>
                                    <td>Validation des évaluations</td>
                                    <td>Salle A5</td>
                                    <td>4/7</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline">
                                            <i class="fas fa-info-circle"></i> Détails
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-outline">Voir toutes les réunions</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialisation du tableau de bord
        document.addEventListener('DOMContentLoaded', function() {
            // Charger les données statistiques
            loadStats();
            
            // Initialiser la sidebar
            initSidebar();
        });

        // Fonction pour charger les statistiques
        function loadStats() {
            // Simuler des données (à remplacer par un appel AJAX réel)
            document.getElementById('rapportsEnAttente').textContent = '12';
            document.getElementById('rapportsApprouves').textContent = '24';
            document.getElementById('reunionsPlanifiees').textContent = '3';
            document.getElementById('messagesNonLus').textContent = '5';
        }

        // Fonction pour initialiser la sidebar
        function initSidebar() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                });
            }

            // Responsive: Gestion mobile
            function handleResize() {
                if (window.innerWidth <= 768) {
                    if (sidebar) sidebar.classList.add('mobile');
                } else {
                    if (sidebar) {
                        sidebar.classList.remove('mobile');
                        sidebar.classList.remove('collapsed');
                    }
                    if (mainContent) mainContent.classList.remove('sidebar-collapsed');
                }
            }

            window.addEventListener('resize', handleResize);
            handleResize();
        }
    </script>
</body>
</html>