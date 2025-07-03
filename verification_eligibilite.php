<?php
// verification_eligibilite.php
require_once 'config.php'; // Assurez-vous que ce fichier inclut votre connexion PDO et les fonctions isLoggedIn/redirect

if (!isLoggedIn()) {
    redirect('loginForm.php'); // Redirige si l'utilisateur n'est pas connecté
}

// Récupérer l'année académique active
$anneeAcademiqueActive = null;
try {
    $stmt = $pdo->query("SELECT id_Ac, CONCAT(YEAR(date_deb), '-', YEAR(date_fin)) as annee_libelle FROM année_academique WHERE est_courante = 1 LIMIT 1"); // Correction ici pour annee_libelle
    $anneeAcademiqueActive = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de récupération de l'année académique active: " . $e->getMessage());
    // Gérer l'erreur, par exemple, afficher un message à l'utilisateur
}

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'get_etudiant_eligibilite':
                $numEtu = $_POST['num_etu'] ?? '';

                if (empty($numEtu)) {
                    throw new Exception("Le numéro étudiant est requis.");
                }

                // Informations de base de l'étudiant
                // La jointure avec `aa.est_courante = 1` assure qu'on récupère l'inscription et le paiement pour l'année active
                $stmtEtu = $pdo->prepare("
                    SELECT
                        e.num_etu, e.nom_etu, e.prenoms_etu, e.email_etu, e.telephone,
                        f.lib_filiere, ne.lib_niv_etu, i.dte_insc,
                        CONCAT(YEAR(aa.date_deb), '-', YEAR(aa.date_fin)) AS annee_actuelle_libelle, aa.id_Ac AS annee_actuelle_id,
                        COALESCE(ps.montant_total, fnd.montant_scolarite_total) AS montant_total_scolarite,
                        COALESCE(ps.total_verse, 0) AS total_verse_scolarite,
                        ps.id_paiement IS NOT NULL as paiement_initialise,
                        (COALESCE(ps.montant_total, fnd.montant_scolarite_total) - COALESCE(ps.total_verse, 0)) AS reste_a_payer_scolarite,
                        r.statut AS rapport_statut
                    FROM
                        etudiant e
                    LEFT JOIN
                        inscrire i ON e.num_etu = i.fk_num_etu
                    LEFT JOIN
                        année_academique aa ON i.fk_id_Ac = aa.id_Ac AND aa.est_courante = 1
                    LEFT JOIN
                        filiere f ON e.fk_id_filiere = f.id_filiere
                    LEFT JOIN
                        niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
                    LEFT JOIN
                        paiement_scolarite ps ON e.num_etu = ps.fk_num_etu AND ps.fk_id_Ac = aa.id_Ac
                    LEFT JOIN
                        filiere_niveau_detail fnd ON f.id_filiere = fnd.fk_id_filiere AND ne.id_niv_etu = fnd.fk_id_niv_etu
                    LEFT JOIN
                        rapports r ON e.num_etu = r.fk_num_etu AND r.fk_id_Ac = aa.id_Ac
                    WHERE
                        e.num_etu = ?
                ");
                $stmtEtu->execute([$numEtu]);
                $etudiantInfo = $stmtEtu->fetch(PDO::FETCH_ASSOC);

                if (!$etudiantInfo) {
                    throw new Exception("Aucun étudiant trouvé avec ce numéro.");
                }

                // Calculer les crédits validés pour l'année académique courante
                $creditsValides = 0;
                $notesValidees = []; // Pour détailler quelles ECUEs sont validées
                if ($etudiantInfo['annee_actuelle_id']) {
                    $stmtCredits = $pdo->prepare("
                        SELECT ev.note, ec.lib_ECUE, ec.credit_ECUE
                        FROM evaluer ev
                        JOIN ecue ec ON ev.fk_id_ECUE = ec.id_ECUE
                        WHERE ev.fk_num_etu = ? AND ev.fk_id_Ac = ?
                        AND ev.note >= 10.00 -- Critère de validation: note >= 10
                    ");
                    $stmtCredits->execute([$numEtu, $etudiantInfo['annee_actuelle_id']]);
                    $ecuesValidees = $stmtCredits->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($ecuesValidees as $ecue) {
                        $creditsValides += $ecue['credit_ECUE'];
                        $notesValidees[] = "{$ecue['lib_ECUE']} (Crédits: {$ecue['credit_ECUE']}, Note: {$ecue['note']}/20)";
                    }
                }
                $etudiantInfo['credits_valides_annee_courante'] = $creditsValides;
                $etudiantInfo['ecues_validees_details'] = $notesValidees;


                // --- Logique d'éligibilité pour ÉDITER UN RAPPORT ---
                $eligibiliteRapport = [
                    'est_eligible' => true, // Supposons éligible par défaut
                    'raisons_non_eligibilite' => [],
                    'raisons_eligibilite' => []
                ];

                // Critère 1 : Être inscrit pour l'année académique courante
                if (empty($etudiantInfo['annee_actuelle_id'])) {
                    $eligibiliteRapport['est_eligible'] = false;
                    $eligibiliteRapport['raisons_non_eligibilite'][] = "L'étudiant n'est pas inscrit pour l'année académique courante.";
                } else {
                    $eligibiliteRapport['raisons_eligibilite'][] = "Inscrit pour l'année académique courante ({$etudiantInfo['annee_actuelle_libelle']}).";
                }

                // Critère 2 : Être à jour de la scolarité (reste à payer très proche de 0)
                if (!$etudiantInfo['paiement_initialise']) {
                     $eligibiliteRapport['est_eligible'] = false;
                     $eligibiliteRapport['raisons_non_eligibilite'][] = "Le dossier de scolarité n'est pas initialisé. Montant total prévu: " . number_format($etudiantInfo['montant_total_scolarite'], 2) . " XOF.";
                } elseif ($etudiantInfo['montant_total_scolarite'] === null || $etudiantInfo['montant_total_scolarite'] <= 0) {
                     // Si la scolarité est gratuite ou non définie (et initialisée)
                     $eligibiliteRapport['raisons_eligibilite'][] = "Scolarité initialisée et montant total est de 0 XOF (considéré à jour).";
                } elseif (abs($etudiantInfo['reste_a_payer_scolarite']) > 0.01) { // Tolérance de 0.01 pour les flottants
                    $eligibiliteRapport['est_eligible'] = false;
                    $eligibiliteRapport['raisons_non_eligibilite'][] = "Scolarité non à jour. Reste à payer: " . number_format($etudiantInfo['reste_a_payer_scolarite'], 2) . " XOF.";
                } else {
                    $eligibiliteRapport['raisons_eligibilite'][] = "Scolarité à jour (tout payé).";
                }


                // Critère 3 : Avoir validé ses 30 crédits
                $creditsMinimumRequis = 30;
                if ($etudiantInfo['credits_valides_annee_courante'] < $creditsMinimumRequis) {
                    $eligibiliteRapport['est_eligible'] = false;
                    $eligibiliteRapport['raisons_non_eligibilite'][] = "Crédits validés insuffisants pour l'année courante : {$etudiantInfo['credits_valides_annee_courante']}/{$creditsMinimumRequis} crédits requis.";
                } else {
                    $eligibiliteRapport['raisons_eligibilite'][] = "Crédits validés suffisants : {$etudiantInfo['credits_valides_annee_courante']}/{$creditsMinimumRequis} crédits requis.";
                }


                echo json_encode(['success' => true, 'etudiant' => $etudiantInfo, 'eligibilite_rapport' => $eligibiliteRapport]);
                break;

            case 'search_etudiants':
                $searchTerm = $_POST['search_term'] ?? '';
                $query = "
                    SELECT
                        e.num_etu, e.nom_etu, e.prenoms_etu, f.lib_filiere, ne.lib_niv_etu
                    FROM
                        etudiant e
                    JOIN filiere f ON e.fk_id_filiere = f.id_filiere
                    JOIN niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
                    WHERE
                        e.num_etu LIKE ? OR
                        e.nom_etu LIKE ? OR
                        e.prenoms_etu LIKE ?
                    LIMIT 10
                ";
                $stmt = $pdo->prepare($query);
                $likeSearchTerm = '%' . $searchTerm . '%';
                $stmt->execute([$likeSearchTerm, $likeSearchTerm, $likeSearchTerm]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $results]);
                break;

            default:
                throw new Exception("Action non reconnue.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Vérification Éligibilité</title>
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
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-2); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); }

        .form-section {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: var(--space-8);
        }
        .form-section-title {
            font-size: var(--text-xl);
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--space-6);
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: var(--space-4);
        }

        .search-box {
            display: flex;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
            align-items: center;
            position: relative;
        }
        .search-box input[type="text"] {
            flex-grow: 1;
            padding: var(--space-3);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
        }
        .search-box button {
            padding: var(--space-3) var(--space-5);
            background-color: var(--accent-600);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: background-color var(--transition-fast);
        }
        .search-box button:hover {
            background-color: var(--accent-700);
        }
        .search-results-dropdown {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none; /* Hidden by default */
        }
        .search-results-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .search-results-dropdown li {
            padding: var(--space-3);
            cursor: pointer;
            border-bottom: 1px solid var(--gray-100);
        }
        .search-results-dropdown li:last-child {
            border-bottom: none;
        }
        .search-results-dropdown li:hover {
            background-color: var(--gray-100);
        }

        .student-details-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-8);
        }
        .student-details-card h4 {
            font-size: var(--text-lg);
            color: var(--gray-800);
            margin-bottom: var(--space-4);
        }
        .student-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-4);
        }
        .detail-item strong {
            color: var(--gray-700);
        }

        .eligibility-section {
            padding-top: var(--space-6);
            border-top: 1px solid var(--gray-200);
            margin-top: var(--space-6);
        }
        .eligibility-section h4 {
            font-size: var(--text-lg);
            margin-bottom: var(--space-4);
        }
        .eligibility-status {
            font-size: var(--text-xl);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-4);
        }
        .eligibility-status.eligible { color: var(--success-500); }
        .eligibility-status.not-eligible { color: var(--error-500); }
        .reasons-list {
            list-style: none;
            padding-left: var(--space-4);
            color: var(--gray-700);
        }
        .reasons-list li {
            margin-bottom: var(--space-2);
            display: flex;
            align-items: flex-start;
            gap: var(--space-2);
        }
        .reasons-list li i {
            margin-top: 2px; /* Align icon with text */
        }
        .reasons-list li.fail i {
            color: var(--error-500);
        }
        .reasons-list li.pass i {
            color: var(--success-500);
        }


        .alert { padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: none; }
        .alert.success { background-color: var(--secondary-50); color: var(--secondary-600); border: 1px solid var(--secondary-100); }
        .alert.error { background-color: #fef2f2; color: var(--error-500); border: 1px solid #fecaca; }
        .alert.info { background-color: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-200); }

        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-spinner { width: 40px; height: 40px; border: 4px solid var(--gray-300); border-top-color: var(--accent-500); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state { text-align: center; padding: var(--space-16); color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .search-box { flex-direction: column; align-items: stretch; }
            .search-results-dropdown { top: unset; position: static; width: auto; }
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
                    <div>
                        <h1 class="page-title-main">Vérification d'Éligibilité</h1>
                        <p class="page-subtitle">Vérifier l'éligibilité d'un étudiant pour diverses actions</p>
                    </div>
                </div>

                <div id="alertMessage" class="alert"></div>

                <div class="form-section">
                    <h3 class="form-section-title">Rechercher un Étudiant</h3>
                    <div class="search-box">
                        <input type="text" id="studentSearchInput" placeholder="Rechercher par N° Étudiant, Nom, Prénom..." autocomplete="off">
                        <button type="button" id="searchButton">Rechercher</button>
                        <div id="searchResults" class="search-results-dropdown">
                            <ul id="searchResultsList"></ul>
                        </div>
                    </div>
                </div>

                <div id="studentDetailsContainer" style="display: none;">
                    <div class="form-section student-details-card">
                        <h3 class="form-section-title">Informations de l'Étudiant <span id="studentDetailsName"></span></h3>
                        <div class="student-details-grid">
                            <div class="detail-item"><strong>N° Étudiant:</strong> <span id="detailNumEtu"></span></div>
                            <div class="detail-item"><strong>Email:</strong> <span id="detailEmail"></span></div>
                            <div class="detail-item"><strong>Téléphone:</strong> <span id="detailTel"></span></div>
                            <div class="detail-item"><strong>Filière:</strong> <span id="detailFiliere"></span></div>
                            <div class="detail-item"><strong>Niveau:</strong> <span id="detailNiveau"></span></div>
                            <div class="detail-item"><strong>Année Inscription:</strong> <span id="detailAnneeInsc"></span></div>
                            <div class="detail-item"><strong>Année Académique Courante:</strong> <span id="detailAnneeAcademique"></span></div>
                            <div class="detail-item"><strong>Crédits Validés (Année Courante):</strong> <span id="detailCreditsValides"></span></div>
                            <div class="detail-item"><strong>Statut Rapport:</strong> <span id="detailRapportStatut"></span></div>
                        </div>

                        <div class="eligibility-section">
                            <h4>Statut de Scolarité</h4>
                            <div class="student-details-grid">
                                <div class="detail-item"><strong>Montant Total Scolarité:</strong> <span id="detailMontantTotal"></span></div>
                                <div class="detail-item"><strong>Total Versé:</strong> <span id="detailTotalVerse"></span></div>
                                <div class="detail-item"><strong>Reste à Payer:</strong> <span id="detailResteAPayer"></span></div>
                                <div class="detail-item"><strong>Statut Paiement:</strong> <span id="detailStatutPaiement"></span></div>
                            </div>
                        </div>

                        <div class="eligibility-section">
                            <h4>Éligibilité à l'édition d'un Rapport</h4>
                            <div id="eligibiliteRapportStatus" class="eligibility-status"></div>
                            <ul id="eligibiliteRapportReasons" class="reasons-list"></ul>
                            <h5>Détail des ECUEs validées:</h5>
                            <ul id="ecuesValideesList" class="reasons-list" style="margin-top: 10px; font-size: 0.9em; color: var(--gray-600);"></ul>
                        </div>
                    </div>
                </div>

                <div id="noStudentSelected" class="empty-state">
                    <i class="fas fa-user-check"></i>
                    <p>Recherchez un étudiant pour vérifier son éligibilité.</p>
                </div>

            </div>
        </main>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <script>
        const studentSearchInput = document.getElementById('studentSearchInput');
        const searchButton = document.getElementById('searchButton');
        const searchResults = document.getElementById('searchResults');
        const searchResultsList = document.getElementById('searchResultsList');
        const studentDetailsContainer = document.getElementById('studentDetailsContainer');
        const noStudentSelected = document.getElementById('noStudentSelected');
        const alertMessageDiv = document.getElementById('alertMessage'); // Déclaration de la variable alertMessageDiv
        const CURRENCY_SYMBOL = ' XOF';

        // Détails de l'étudiant
        const detailNumEtu = document.getElementById('detailNumEtu');
        const studentDetailsName = document.getElementById('studentDetailsName');
        const detailEmail = document.getElementById('detailEmail');
        const detailTel = document.getElementById('detailTel');
        const detailFiliere = document.getElementById('detailFiliere');
        const detailNiveau = document.getElementById('detailNiveau');
        const detailAnneeInsc = document.getElementById('detailAnneeInsc');
        const detailAnneeAcademique = document.getElementById('detailAnneeAcademique');
        const detailCreditsValides = document.getElementById('detailCreditsValides'); // Nouveau champ
        const detailRapportStatut = document.getElementById('detailRapportStatut');

        // Détails scolarité
        const detailMontantTotal = document.getElementById('detailMontantTotal');
        const detailTotalVerse = document.getElementById('detailTotalVerse');
        const detailResteAPayer = document.getElementById('detailResteAPayer');
        const detailStatutPaiement = document.getElementById('detailStatutPaiement');

        // Eligibilité pour édition rapport
        const eligibiliteRapportStatus = document.getElementById('eligibiliteRapportStatus');
        const eligibiliteRapportReasons = document.getElementById('eligibiliteRapportReasons');
        const ecuesValideesList = document.getElementById('ecuesValideesList'); // Détail des ECUEs validées

        document.addEventListener('DOMContentLoaded', function() {
            initSidebar();
        });

        // Fonction pour faire une requête AJAX
        async function makeAjaxRequest(data) {
            showLoading(true);
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data).toString()
                });
                return await response.json();
            } catch (error) {
                console.error('Erreur AJAX:', error);
                showAlert('Erreur réseau. Impossible de communiquer avec le serveur.', 'error');
                throw error;
            } finally {
                showLoading(false);
            }
        }

        // Recherche d'étudiant avec autocomplétion
        studentSearchInput.addEventListener('input', async function() {
            const searchTerm = this.value;
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            try {
                const result = await makeAjaxRequest({
                    action: 'search_etudiants',
                    search_term: searchTerm
                });

                if (result.success && result.data.length > 0) {
                    searchResultsList.innerHTML = '';
                    result.data.forEach(student => {
                        const li = document.createElement('li');
                        li.textContent = `${student.nom_etu} ${student.prenoms_etu} (${student.num_etu})`;
                        li.dataset.numEtu = student.num_etu;
                        li.addEventListener('click', () => selectStudent(student.num_etu));
                        searchResultsList.appendChild(li);
                    });
                    searchResults.style.display = 'block';
                } else {
                    searchResultsList.innerHTML = '<li>Aucun résultat trouvé.</li>';
                    searchResults.style.display = 'block';
                }
            } catch (error) {
                console.error('Erreur de recherche:', error);
                searchResultsList.innerHTML = '<li>Erreur lors de la recherche.</li>';
                searchResults.style.display = 'block';
            }
        });

        // Cacher la liste de résultats si on clique en dehors
        document.addEventListener('click', function(event) {
            if (!studentSearchInput.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.style.display = 'none';
            }
        });


        // Gérer le clic sur le bouton de recherche
        searchButton.addEventListener('click', () => {
            // Tente de récupérer le num_etu du format "Nom Prénom (NUM_ETU)"
            const match = studentSearchInput.value.match(/\(([^)]+)\)$/);
            const numEtu = match ? match[1] : studentSearchInput.value; // Utilise le contenu si pas de format (xxx)

            if (numEtu) {
                selectStudent(numEtu);
            } else {
                showAlert('Veuillez sélectionner un étudiant ou entrer un numéro étudiant valide.', 'warning');
            }
        });

        // Sélectionner un étudiant et afficher ses détails
        async function selectStudent(numEtu) {
            studentSearchInput.value = numEtu; // Mettre le num_etu dans le champ de recherche
            searchResults.style.display = 'none'; // Cacher les résultats de recherche

            try {
                const result = await makeAjaxRequest({
                    action: 'get_etudiant_eligibilite',
                    num_etu: numEtu
                });

                if (result.success) {
                    displayStudentDetails(result.etudiant, result.eligibilite_rapport);
                    noStudentSelected.style.display = 'none';
                    studentDetailsContainer.style.display = 'block';
                } else {
                    showAlert(result.message, 'error');
                    studentDetailsContainer.style.display = 'none';
                    noStudentSelected.style.display = 'block';
                }
            } catch (error) {
                showAlert('Erreur lors de la récupération des détails de l\'étudiant.', 'error');
                studentDetailsContainer.style.display = 'none';
                noStudentSelected.style.display = 'block';
            }
        }

        function displayStudentDetails(etudiant, eligibiliteRapport) {
            // Informations de base
            studentDetailsName.textContent = `${etudiant.prenoms_etu || ''} ${etudiant.nom_etu || 'N/A'}`;
            detailNumEtu.textContent = etudiant.num_etu || 'N/A';
            detailEmail.textContent = etudiant.email_etu || 'N/A';
            detailTel.textContent = etudiant.telephone || 'N/A';
            detailFiliere.textContent = etudiant.lib_filiere || 'N/A';
            detailNiveau.textContent = etudiant.lib_niv_etu || 'N/A';
            detailAnneeInsc.textContent = etudiant.dte_insc ? formatDate(etudiant.dte_insc) : 'N/A';
            detailAnneeAcademique.textContent = etudiant.annee_actuelle_libelle || 'N/A';
            detailCreditsValides.textContent = etudiant.credits_valides_annee_courante !== null ? `${etudiant.credits_valides_annee_courante} crédits` : 'N/A';
            detailRapportStatut.innerHTML = getRapportStatusBadge(etudiant.rapport_statut);

            // Informations de scolarité
            detailMontantTotal.textContent = formatCurrency(etudiant.montant_total_scolarite);
            detailTotalVerse.textContent = formatCurrency(etudiant.total_verse_scolarite);
            detailResteAPayer.textContent = formatCurrency(etudiant.reste_a_payer_scolarite);
            detailStatutPaiement.innerHTML = getPaiementStatusBadge(etudiant.paiement_initialise, etudiant.reste_a_payer_scolarite, etudiant.montant_total_scolarite);

            // Éligibilité pour édition rapport
            eligibiliteRapportReasons.innerHTML = ''; // Nettoyer les raisons précédentes
            eligibiliteRapportStatus.className = 'eligibility-status'; // Réinitialiser les classes

            if (eligibiliteRapport.est_eligible) {
                eligibiliteRapportStatus.classList.add('eligible');
                eligibiliteRapportStatus.innerHTML = '<i class="fas fa-check-circle"></i> Éligible';
            } else {
                eligibiliteRapportStatus.classList.add('not-eligible');
                eligibiliteRapportStatus.innerHTML = '<i class="fas fa-times-circle"></i> Non Éligible';
            }

            // Afficher les raisons (succès et échec)
            eligibiliteRapport.raisons_eligibilite.forEach(reason => {
                const li = document.createElement('li');
                li.className = 'pass';
                li.innerHTML = `<i class="fas fa-check"></i> ${reason}`;
                eligibiliteRapportReasons.appendChild(li);
            });
            eligibiliteRapport.raisons_non_eligibilite.forEach(reason => {
                const li = document.createElement('li');
                li.className = 'fail';
                li.innerHTML = `<i class="fas fa-times"></i> ${reason}`;
                eligibiliteRapportReasons.appendChild(li);
            });

            // Détail des ECUEs validées
            ecuesValideesList.innerHTML = '';
            if (etudiant.ecues_validees_details && etudiant.ecues_validees_details.length > 0) {
                etudiant.ecues_validees_details.forEach(detail => {
                    const li = document.createElement('li');
                    li.textContent = detail;
                    ecuesValideesList.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.textContent = 'Aucune ECUE validée pour l\'année courante.';
                ecuesValideesList.appendChild(li);
            }
        }

        function getRapportStatusBadge(status) {
            switch (status) {
                case 'brouillon': return `<span class="badge badge-info">Brouillon</span>`;
                case 'soumis': return `<span class="badge badge-warning">Soumis</span>`;
                case 'approuve': return `<span class="badge badge-success">Approuvé</span>`;
                case 'rejete': return `<span class="badge badge-danger">Rejeté</span>`;
                default: return `<span class="badge badge-secondary">Non disponible / Non créé</span>`;
            }
        }

        function getPaiementStatusBadge(isInitialise, resteAPayer, montantTotal) {
            if (!isInitialise) return `<span class="badge badge-danger">Non Initialisé</span>`;
            if (montantTotal === null || montantTotal === undefined) return `<span class="badge badge-secondary">Inconnu</span>`; // Should not happen if initialized
            if (parseFloat(montantTotal) <= 0.01 && parseFloat(resteAPayer) <= 0.01) return `<span class="badge badge-success">Gratuit / Payé</span>`; // Scolarité gratuite ou déjà à 0
            if (parseFloat(resteAPayer) <= 0.01) return `<span class="badge badge-success">Payé Intégralement</span>`; // Payé entièrement
            if (parseFloat(resteAPayer) < parseFloat(montantTotal)) return `<span class="badge badge-warning">Partiellement Payé</span>`; // Reste à payer > 0 mais < total
            return `<span class="badge badge-danger">Impayé</span>`; // Rien payé ou seulement l'initialisation à 0
        }


        function formatCurrency(amount) {
            if (amount === null || amount === undefined) return 'N/A';
            return parseFloat(amount).toLocaleString('fr-FR') + CURRENCY_SYMBOL;
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR');
        }

        // Fonctions pour gérer la sidebar
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

        // Fonction pour afficher les alertes
        function showAlert(message, type = 'info') {
            // alertMessageDiv est déjà déclaré en haut de manière globale.
            // Il ne faut pas le redéclarer ici, ni utiliser une variable non définie comme 'alertDiv'.
            alertMessageDiv.textContent = message;
            alertMessageDiv.className = `alert ${type}`;
            alertMessageDiv.style.display = 'block';
            setTimeout(() => {
                alertMessageDiv.style.display = 'none'; // CORRECTION APPLIQUÉE ICI
            }, 5000);
        }

        // Fonction pour afficher/cacher le loading
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = show ? 'flex' : 'none';
        }
    </script>
</body>
</html>