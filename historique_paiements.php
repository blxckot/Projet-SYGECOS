<?php
// historique_paiements.php
require_once 'config.php'; // Connexion PDO et fonctions de sécurité

if (!isLoggedIn()) {
    redirect('loginForm.php');
}

// Les variables pour les dropdowns de filtres ne sont pas nécessaires en PHP car les filtres sont supprimés du HTML.
$niveauxEtudeJson = json_encode([]); 

// === TRAITEMENT AJAX ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'rechercher_historique':
                // La requête récupérera tout l'historique disponible.
                
                $checkView = $pdo->query("SHOW TABLES LIKE 'vue_paiements_etudiants'");
                if ($checkView->rowCount() == 0) {
                    throw new Exception("La vue 'vue_paiements_etudiants' n'existe pas. Veuillez d'abord exécuter le script de correction de la base de données.");
                }

                // **CORRECTION ICI**: Utiliser 'montant_scolarite_prevu' de la vue
                // et 'montant_total' (qui est déjà un COALESCE dans la vue)
                $sql = "SELECT num_etu, nom_etu, prenoms_etu, annee_libelle, lib_filiere, lib_niv_etu,
                               montant_total,       -- Cette colonne est déjà COALESCE(ps.montant_total, fnd.montant_scolarite_total) dans la vue
                               montant_scolarite_prevu, -- La colonne avec le montant prévu direct de fnd
                               total_verse,         -- Colonne 'total_verse' de la vue
                               reste_a_payer        -- Colonne 'reste_a_payer' de la vue
                        FROM vue_paiements_etudiants 
                        ORDER BY annee_libelle DESC, nom_etu, prenoms_etu";

                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $resultats]);
                break;

            default:
                throw new Exception("Action non reconnue");
        }

    } catch (Exception $e) {
        error_log("Erreur AJAX: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Historique des Paiements</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS unifié basé sur liste_etudiants.php pour un meilleur visuel */
        /* === VARIABLES CSS === */
        :root {
            /* Couleurs Primaires */
            --primary-50: #f8fafc; --primary-100: #f1f5f9; --primary-200: #e2e8f0; --primary-300: #cbd5e1; --primary-400: #94a3b8; --primary-500: #64748b; --primary-600: #475569; --primary-700: #334155; --primary-800: #1e293b; --primary-900: #0f172a;
            /* Couleurs d'Accent Bleu */
            --accent-50: #eff6ff; --accent-100: #dbeafe; --accent-200: #bfdbfe; --accent-300: #93c5fd; --accent-400: #60a5fa; --accent-500: #3b82f6; --accent-600: #2563eb; --accent-700: #1d4ed8; --accent-800: #1e40af; --accent-900: #1e3a8a;
            /* Couleurs Sémantiques */
            --success-500: #22c55e; --warning-500: #f59e0b; --error-500: #ef4444; --info-500: #3b82f6;
            --secondary-100: #dcfce7; /* Specific for success badge */
            --secondary-600: #16a34a; /* Specific for success badge text */
            /* Couleurs Neutres */
            --white: #ffffff; --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db; --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
            /* Layout */
            --sidebar-width: 280px; --sidebar-collapsed-width: 80px; --topbar-height: 70px;
            /* Typographie */
            --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
            --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-lg: 1.125rem; --text-xl: 1.25rem; --text-2xl: 1.5rem; --text-3xl: 1.875rem;
            /* Espacement */
            --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-5: 1.25rem; --space-6: 1.5rem; --space-8: 2rem; --space-10: 2.5rem; --space-12: 3rem; --space-16: 4rem;
            /* Bordures */
            --radius-sm: 0.25rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem; --radius-2xl: 1.5rem; --radius-3xl: 2rem;
            /* Ombres */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.05); --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            /* Transitions */
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

        /* === TOPBAR === */
        .topbar { height: var(--topbar-height); background: var(--white); border-bottom: 1px solid var(--gray-200); padding: 0 var(--space-6); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; }
        .topbar-left { display: flex; align-items: center; gap: var(--space-4); }
        .sidebar-toggle { width: 40px; height: 40px; border: none; background: var(--gray-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition-fast); color: var(--gray-600); } .sidebar-toggle:hover { background: var(--gray-200); color: var(--gray-800); }
        .page-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-800); }

        /* === PAGE SPECIFIC STYLES === */
        .page-content { padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-2); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); }

        .form-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: var(--space-8); }
        .form-card-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); margin-bottom: var(--space-6); border-bottom: 1px solid var(--gray-200); padding-bottom: var(--space-4); }
        .form-actions { display: flex; gap: var(--space-4); justify-content: flex-end; }
        .btn { padding: var(--space-3) var(--space-5); border-radius: var(--radius-md); font-size: var(--text-base); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { background-color: var(--gray-200); color: var(--gray-700); } .btn-secondary:hover:not(:disabled) { background-color: var(--gray-300); }

        /* === TABLE STYLES === */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden; /* Important for table-wrapper */
        }
        .table-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-4);
        }
        .table-title {
            font-size: var(--text-xl);
            font-weight: 600;
            color: var(--gray-900);
        }
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* Ensure table is wide enough for its content */
        }
        .data-table th, .data-table td {
            padding: var(--space-4);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        .data-table th {
            background-color: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .data-table tbody tr:hover { background-color: var(--gray-50); }
        .data-table td { font-size: var(--text-sm); color: var(--gray-800); }

        /* Alert messages */
        .alert { padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: none; }
        .alert.success { background-color: var(--secondary-50); color: var(--secondary-600); border: 1px solid var(--secondary-100); }
        .alert.error { background-color: #fef2f2; color: var(--error-500); border: 1px solid #fecaca; }
        .alert.info { background-color: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-200); }

        .no-results { text-align: center; padding: var(--space-8); color: var(--gray-500); }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none; /* Hidden by default */
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--accent-500);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .data-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar_respo_scolarité.php'; ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; ?>

            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title-main">Historique des Paiements de Scolarité</h1>
                    <p class="page-subtitle">Consulter l'historique de tous les règlements de scolarité des étudiants</p>
                </div>

                <div id="alertMessage" class="alert"></div>

                <div class="table-container" id="historyResultsCard">
                    <div class="table-header">
                        <h3 class="table-title">Tous les Paiements (<span id="historyResultCount">0</span>)</h3>
                        <div class="form-actions">
                            <button class="btn btn-secondary" id="exportHistoryBtn">
                                <i class="fas fa-download"></i> Exporter en CSV
                            </button>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Nom & Prénom(s)</th>
                                    <th>Année Académique</th>
                                    <th>Filière</th>
                                    <th>Niveau</th>
                                    <th>Total à Payer (Prévu)</th>
                                    <th>Total Versé</th>
                                    <th>Reste à Payer</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                </tbody>
                        </table>
                    </div>
                    <div class="no-results" id="noHistoryResults" style="display: none;">
                        Aucun historique de paiement trouvé.
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <script>
        const loadingOverlay = document.getElementById('loadingOverlay');

        function showAlert(message, type = 'info') {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.textContent = message;
            alertDiv.className = `alert ${type}`;
            alertDiv.style.display = 'block';
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        async function makeAjaxRequest(data) {
            loadingOverlay.style.display = 'flex'; // Show loading spinner
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                });
                return await response.json();
            } catch (error) {
                console.error('Erreur AJAX:', error);
                throw error;
            } finally {
                loadingOverlay.style.display = 'none'; // Hide loading spinner
            }
        }

        function formatMontant(montant) {
            if (montant === null || montant === undefined || isNaN(montant) || montant === '') return '-';
            return new Intl.NumberFormat('fr-FR').format(parseFloat(montant)) + ' FCFA';
        }

        async function loadAllHistoryPayments() {
            try {
                const result = await makeAjaxRequest({
                    action: 'rechercher_historique' // Cette action ne prend pas de paramètres de filtre
                });

                if (result.success) {
                    displayHistoryResults(result.data);
                } else {
                    showAlert(result.message || 'Erreur lors du chargement de l\'historique.', 'error');
                }
            } catch (error) {
                showAlert('Erreur de connexion au serveur. Impossible de charger l\'historique.', 'error');
            }
        }

        function displayHistoryResults(results) {
            const tableBody = document.getElementById('historyTableBody');
            const resultCountSpan = document.getElementById('historyResultCount');
            const noResultsDiv = document.getElementById('noHistoryResults');
            tableBody.innerHTML = ''; // Clear previous results

            resultCountSpan.textContent = results.length;

            if (results.length === 0) {
                noResultsDiv.style.display = 'block';
            } else {
                noResultsDiv.style.display = 'none';
                results.forEach(student => {
                    const row = tableBody.insertRow();
                    row.insertCell().textContent = student.num_etu;
                    row.insertCell().textContent = `${student.nom_etu} ${student.prenoms_etu}`;
                    row.insertCell().textContent = student.annee_libelle;
                    row.insertCell().textContent = student.lib_filiere || '-';
                    row.insertCell().textContent = student.lib_niv_etu || '-';
                    // **CORRECTION ICI**: Utiliser 'montant_total' qui est déjà le COALESCE dans la vue
                    row.insertCell().textContent = formatMontant(student.montant_total);
                    row.insertCell().textContent = formatMontant(student.total_verse);
                    // Utilisez reste_a_payer qui est déjà calculé dans la vue
                    row.insertCell().textContent = formatMontant(student.reste_a_payer);
                });
            }
        }

        document.getElementById('exportHistoryBtn').addEventListener('click', function() {
            const table = document.querySelector('.data-table');
            if (!table || table.rows.length <= 1) { // Only header row exists
                showAlert('Aucune donnée à exporter.', 'warning');
                return;
            }

            let csv = [];
            // Get headers from the table directly
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());
            csv.push(headers.map(h => `"${h.replace(/"/g, '""')}"`).join(';'));

            // Get data from the table body
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(rowElement => {
                let row = [];
                const cols = rowElement.querySelectorAll('td');
                cols.forEach(col => {
                    let data = col.innerText.trim().replace(/"/g, '""');
                    row.push(`"${data}"`);
                });
                csv.push(row.join(';'));
            });

            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const downloadLink = document.createElement('a');
            const url = URL.createObjectURL(csvFile);

            downloadLink.setAttribute("href", url);
            downloadLink.setAttribute("download", `historique_paiements_${new Date().toISOString().slice(0,10)}.csv`);
            downloadLink.style.visibility = 'hidden';

            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            showAlert('Historique exporté avec succès en CSV.', 'success');
        });

        // Sidebar responsive toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            });
        }

        // Initialisation - IMPORTANT: Lancer la fonction de chargement de l'historique dès le chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            loadAllHistoryPayments(); // Charge tout l'historique dès l'ouverture de la page
        });
    </script>
</body>
</html>