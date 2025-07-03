<?php
// Démarre la session pour utiliser les variables de session.
session_start();

// --- VÉRIFICATION DE LA CONNEXION ---
// On vérifie si l'utilisateur est bien connecté et si son rôle est 'etudiant'.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE || $_SESSION['user_type'] !== 'etudiant' || !isset($_SESSION['id_util'])) {
    // Si l'une de ces conditions n'est pas remplie, on le redirige vers la page de connexion.
    header('Location: loginForm.php'); // Assurez-vous que le nom du fichier de connexion est correct.
    exit;
}

// --- CONNEXION À LA BASE DE DONNÉES ---
// Remplacez ces informations par les vôtres si elles sont différentes.
$host = '127.0.0.1';
$db   = 'sygecos';
$user = 'root';
$pass = ''; // Mettez votre mot de passe ici si vous en avez un
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // En cas d'erreur de connexion, afficher un message et arrêter le script.
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- RÉCUPÉRATION DU NUMÉRO D'ÉTUDIANT (num_etu) ---
// Le numéro d'étudiant est nécessaire pour les requêtes suivantes.
// On le récupère depuis la table 'etudiant' en utilisant l'ID utilisateur stocké en session.
try {
    $stmt_num_etu = $pdo->prepare("SELECT num_etu FROM etudiant WHERE fk_id_util = ?");
    $stmt_num_etu->execute([$_SESSION['id_util']]);
    $result = $stmt_num_etu->fetch();

    if (!$result || !isset($result['num_etu'])) {
        // Si aucun numéro étudiant n'est trouvé pour cet utilisateur, c'est une erreur.
        // On déconnecte l'utilisateur et on le redirige.
        session_destroy();
        header('Location: loginForm.php?error=datainconsistency');
        exit;
    }
    $num_etu = $result['num_etu'];

} catch (\PDOException $e) {
    // Gérer l'erreur de requête
    die("Erreur lors de la récupération des informations de l'étudiant: " . $e->getMessage());
}


// --- REQUÊTES POUR RÉCUPÉRER LES DONNÉES DE L'ÉTUDIANT ---

// 1. Informations générales de l'étudiant (nom, filière, niveau)
$stmt_info = $pdo->prepare("
    SELECT
        e.nom_etu,
        e.prenoms_etu,
        f.lib_filiere,
        ne.lib_niv_etu
    FROM etudiant e
    JOIN filiere f ON e.fk_id_filiere = f.id_filiere
    JOIN niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
    WHERE e.num_etu = ?
");
$stmt_info->execute([$num_etu]);
$etudiant = $stmt_info->fetch();

// 2. Dernières évaluations (notes) de l'étudiant
$stmt_evals = $pdo->prepare("
    SELECT
        ec.lib_ECUE,
        ev.note,
        ev.dte_eval
    FROM evaluer ev
    JOIN ecue ec ON ev.fk_id_ECUE = ec.id_ECUE
    WHERE ev.fk_num_etu = ?
    ORDER BY ev.dte_eval DESC
    LIMIT 5
");
$stmt_evals->execute([$num_etu]);
$evaluations = $stmt_evals->fetchAll();

// 3. Calcul de la moyenne générale
$stmt_avg = $pdo->prepare("SELECT AVG(note) as moyenne FROM evaluer WHERE fk_num_etu = ?");
$stmt_avg->execute([$num_etu]);
$moyenne = $stmt_avg->fetch()['moyenne'];


// 4. Informations sur la scolarité en utilisant la vue existante
$stmt_scolarite = $pdo->prepare("
    SELECT
        total_verse,
        montant_total,
        reste_a_payer
    FROM vue_paiements_etudiants
    WHERE num_etu = ? AND id_Ac = (SELECT id_Ac FROM année_academique WHERE est_courante = 1)
");
$stmt_scolarite->execute([$num_etu]);
$scolarite = $stmt_scolarite->fetch();

// Calcul du pourcentage payé
$pourcentage_paye = 0;
if ($scolarite && $scolarite['montant_total'] > 0) {
    $pourcentage_paye = ($scolarite['total_verse'] / $scolarite['montant_total']) * 100;
}


// 5. Derniers rapports soumis
$stmt_rapports = $pdo->prepare("
    SELECT
        file_name,
        upload_date,
        description
    FROM rapports_etudiant_files
    WHERE fk_num_etu = ?
    ORDER BY upload_date DESC
    LIMIT 3
");
$stmt_rapports->execute([$num_etu]);
$rapports = $stmt_rapports->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Tableau de Bord</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
            --radius-sm: 0.25rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.05);
            --transition-normal: 250ms ease-in-out;
        }
        body { font-family: var(--font-primary); background-color: var(--gray-50); color: var(--gray-800); margin: 0; }
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .page-content { padding: var(--space-8); }
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); margin-top: var(--space-2); }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-8); }
        .info-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); display: flex; align-items: center; gap: var(--space-5); }
        .info-card-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: var(--text-xl); color: white; flex-shrink: 0; }
        .icon-filiere { background-color: var(--accent-500); }
        .icon-moyenne { background-color: var(--secondary-500); }
        .icon-rapports { background-color: var(--warning-500); }
        .info-card-content .label { font-size: var(--text-sm); color: var(--gray-500); margin-bottom: var(--space-1); }
        .info-card-content .value { font-size: var(--text-lg); font-weight: 600; color: var(--gray-800); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-6); }
        .dashboard-card { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); padding: var(--space-6); }
        .card-full { grid-column: 1 / -1; }
        .card-2-3 { grid-column: span 2 / span 2; }
        .card-1-3 { grid-column: span 1 / span 1; }
        .card-header { display: flex; align-items: center; gap: var(--space-3); font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); margin-bottom: var(--space-4); border-bottom: 1px solid var(--gray-200); padding-bottom: var(--space-4); }
        .card-header i { color: var(--accent-600); }

        /* Styles pour le tableau */
        .table-wrapper { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: var(--space-3); text-align: left; border-bottom: 1px solid var(--gray-200); font-size: var(--text-sm); }
        .data-table th { background-color: var(--gray-50); font-weight: 600; color: var(--gray-700); }
        .data-table tbody tr:hover { background-color: var(--gray-50); }

        /* Styles pour la scolarité */
        .scolarite-container { display: flex; align-items: center; gap: var(--space-8); }
        .scolarite-chart { max-width: 150px; max-height: 150px; position: relative; }
        .scolarite-details { flex: 1; }
        .scolarite-details p { margin-bottom: var(--space-3); font-size: var(--text-base); }
        .scolarite-details strong { font-weight: 600; color: var(--gray-800); }
        .scolarite-details .montant { font-size: var(--text-lg); }

        /* Styles pour les rapports */
        .rapport-list-item { display: flex; align-items: center; gap: var(--space-4); padding: var(--space-3) 0; border-bottom: 1px solid var(--gray-100); }
        .rapport-list-item:last-child { border-bottom: none; }
        .rapport-icon { font-size: var(--text-xl); color: var(--accent-500); }
        .rapport-info .name { font-weight: 600; color: var(--gray-800); }
        .rapport-info .date { font-size: var(--text-xs); color: var(--gray-500); }

        /* État vide */
        .empty-state { text-align: center; padding: var(--space-8); color: var(--gray-500); }
        .empty-state i { font-size: 2rem; margin-bottom: var(--space-4); }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .card-2-3, .card-1-3 { grid-column: span 1 / span 1; }
            .scolarite-container { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php
            // Inclusion de la sidebar. Assurez-vous que le chemin est correct.
            // Le lien "Tableau de bord" doit être marqué comme 'active'.
            include 'sidebar_etudiant.php';
        ?>

        <main class="main-content" id="mainContent">
            <?php
                // Inclusion de la topbar. Créez ce fichier si nécessaire.
                // include 'topbar.php';
            ?>

            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title-main">Tableau de Bord</h1>
                    <p class="page-subtitle">
                        Bonjour
                        <strong><?= htmlspecialchars($etudiant['prenoms_etu'] . ' ' . $etudiant['nom_etu']) ?></strong>,
                        bienvenue sur votre espace personnel.
                    </p>
                </div>

                <!-- Cartes d'informations rapides -->
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-icon icon-filiere"><i class="fas fa-graduation-cap"></i></div>
                        <div class="info-card-content">
                            <div class="label">Filière & Niveau</div>
                            <div class="value"><?= htmlspecialchars($etudiant['lib_filiere'] . ' - ' . $etudiant['lib_niv_etu']) ?></div>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon icon-moyenne"><i class="fas fa-star"></i></div>
                        <div class="info-card-content">
                            <div class="label">Moyenne Générale</div>
                            <div class="value"><?= $moyenne ? number_format($moyenne, 2) . '/20' : 'N/A' ?></div>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon icon-rapports"><i class="fas fa-file-alt"></i></div>
                        <div class="info-card-content">
                            <div class="label">Rapports Soumis</div>
                            <div class="value"><?= count($rapports) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Grille principale du dashboard -->
                <div class="dashboard-grid">

                    <!-- Carte Scolarité -->
                    <div class="dashboard-card card-1-3">
                        <div class="card-header">
                            <i class="fas fa-wallet"></i>
                            <h3>Ma Scolarité</h3>
                        </div>
                        <?php if ($scolarite): ?>
                            <div class="scolarite-container">
                                <div class="scolarite-chart">
                                    <canvas id="scolariteChart"></canvas>
                                </div>
                                <div class="scolarite-details">
                                    <p>
                                        <span class="montant"><strong><?= number_format($scolarite['total_verse'], 0, ',', ' ') ?> FCFA</strong></span><br>
                                        sur <?= number_format($scolarite['montant_total'], 0, ',', ' ') ?> FCFA
                                    </p>
                                    <p>
                                        Reste à payer : <br>
                                        <strong><?= number_format($scolarite['reste_a_payer'], 0, ',', ' ') ?> FCFA</strong>
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>Aucune information de scolarité disponible pour cette année.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Carte Évaluations -->
                    <div class="dashboard-card card-2-3">
                        <div class="card-header">
                            <i class="fas fa-clipboard-check"></i>
                            <h3>Mes Dernières Évaluations</h3>
                        </div>
                        <div class="table-wrapper">
                            <?php if (!empty($evaluations)): ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Matière (ECUE)</th>
                                            <th>Date</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($evaluations as $eval): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($eval['lib_ECUE']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($eval['dte_eval'])) ?></td>
                                                <td><strong><?= htmlspecialchars($eval['note']) ?> / 20</strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Aucune évaluation n'a été enregistrée pour le moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Carte Rapports -->
                    <div class="dashboard-card card-full">
                        <div class="card-header">
                            <i class="fas fa-upload"></i>
                            <h3>Mes Rapports Récents</h3>
                        </div>
                        <div>
                            <?php if (!empty($rapports)): ?>
                                <?php foreach ($rapports as $rapport): ?>
                                    <div class="rapport-list-item">
                                        <div class="rapport-icon"><i class="fas fa-file-pdf"></i></div>
                                        <div class="rapport-info">
                                            <div class="name"><?= htmlspecialchars($rapport['file_name']) ?></div>
                                            <div class="date">Déposé le <?= date('d/m/Y à H:i', strtotime($rapport['upload_date'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>Vous n'avez déposé aucun rapport pour le moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialisation du graphique de scolarité
            <?php if ($scolarite): ?>
            const ctx = document.getElementById('scolariteChart').getContext('2d');
            const scolariteChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Payé', 'Restant'],
                    datasets: [{
                        data: [<?= $scolarite['total_verse'] ?>, <?= $scolarite['reste_a_payer'] > 0 ? $scolarite['reste_a_payer'] : 0 ?>],
                        backgroundColor: [
                            '#16a34a', // --secondary-600
                            '#e5e7eb'  // --gray-200
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 4,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '75%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: false
                        }
                    }
                }
            });
            <?php endif; ?>

            // Logique pour la sidebar (si vous avez un bouton pour la réduire)
            // Assurez-vous d'avoir un bouton avec l'id 'sidebarToggle' dans votre topbar.php
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                });
            }
        });
    </script>
</body>
</html>
