<?php
// resultats_evaluations.php
require_once 'config.php'; // Connexion PDO et fonctions de sécurité

if (!isLoggedIn()) {
    redirect('loginForm.php');
}

// Récupération de l'année académique active
$anneeActive = null;
try {
    $stmt = $pdo->query("SELECT id_Ac, CONCAT(YEAR(date_deb), '-', YEAR(date_fin)) as annee_libelle FROM année_academique WHERE statut = 'active' OR est_courante = 1 LIMIT 1");
    $anneeActive = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération année active: " . $e->getMessage());
}

// Récupération des filières
$filieres = [];
try {
    $stmt = $pdo->query("SELECT id_filiere, lib_filiere FROM filiere ORDER BY lib_filiere");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération filières: " . $e->getMessage());
}

// === TRAITEMENT AJAX ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'charger_niveaux_par_filiere':
                $filiereId = intval($_POST['filiere_id'] ?? 0);
                if ($filiereId <= 0) {
                    throw new Exception("ID de filière manquant");
                }
                $stmt = $pdo->prepare("SELECT id_niv_etu, lib_niv_etu FROM niveau_etude WHERE fk_id_filiere = ? ORDER BY lib_niv_etu");
                $stmt->execute([$filiereId]);
                $niveauxData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $niveauxData]);
                break;

            case 'obtenir_statistiques_niveau':
                $anneeId = intval($_POST['annee_id'] ?? 0);
                $niveauId = intval($_POST['niveau_id'] ?? 0);
                $filiereId = intval($_POST['filiere_id'] ?? 0); 
                
                if ($anneeId <= 0 || $niveauId <= 0 || $filiereId <= 0) {
                    throw new Exception("Paramètres manquants (Année, Niveau ou Filière)");
                }
                
                // Statistiques générales du niveau
                $stmtStats = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT e.num_etu) as total_etudiants,
                        COUNT(DISTINCT ev.fk_id_ECUE) as ecues_evaluees,
                        COUNT(ev.note) as total_notes,
                        AVG(ev.note) as moyenne_generale,
                        MIN(ev.note) as note_min,
                        MAX(ev.note) as note_max
                    FROM etudiant e
                    INNER JOIN inscrire i ON e.num_etu = i.fk_num_etu
                    LEFT JOIN evaluer ev ON e.num_etu = ev.fk_num_etu AND ev.fk_id_Ac = ?
                    WHERE i.fk_id_Ac = ? AND e.fk_id_niv_etu = ? AND e.fk_id_filiere = ?
                ");
                $stmtStats->execute([$anneeId, $anneeId, $niveauId, $filiereId]); // MODIFIÉ ICI: $anneeId ajouté
                $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
                
                // Répartition des notes
                $stmtRepartition = $pdo->prepare("
                    SELECT 
                        CASE 
                            WHEN ev.note >= 16 THEN 'excellent'
                            WHEN ev.note >= 14 THEN 'bien'
                            WHEN ev.note >= 10 THEN 'passable'
                            ELSE 'insuffisant'
                        END as categorie,
                        COUNT(*) as nombre
                    FROM evaluer ev
                    INNER JOIN etudiant e ON ev.fk_num_etu = e.num_etu
                    WHERE ev.fk_id_Ac = ? AND e.fk_id_niv_etu = ? AND e.fk_id_filiere = ?
                    GROUP BY categorie
                ");
                $stmtRepartition->execute([$anneeId, $niveauId, $filiereId]);
                $repartition = $stmtRepartition->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $repartition]); // Changed to repartition, not full stats
                break;
                
            case 'obtenir_resultats_detailles':
                $anneeId = intval($_POST['annee_id'] ?? 0);
                $niveauId = intval($_POST['niveau_id'] ?? 0);
                $filiereId = intval($_POST['filiere_id'] ?? 0); 
                
                if ($anneeId <= 0 || $niveauId <= 0 || $filiereId <= 0) {
                    throw new Exception("Paramètres manquants (Année, Niveau ou Filière)");
                }
                
                $whereEcue = ""; 
                $params = [$anneeId, $anneeId, $niveauId, $filiereId]; // MODIFIÉ ICI: $anneeId ajouté
                
                $stmt = $pdo->prepare("
                    SELECT 
                        e.num_etu,
                        e.nom_etu,
                        e.prenoms_etu,
                        u.lib_UE,
                        ec.lib_ECUE,
                        ec.credit_ECUE,
                        ev.note,
                        ev.dte_eval,
                        CASE 
                            WHEN ev.note >= 16 THEN 'Excellent'
                            WHEN ev.note >= 14 THEN 'Bien'
                            WHEN ev.note >= 10 THEN 'Passable'
                            WHEN ev.note IS NOT NULL THEN 'Insuffisant'
                            ELSE 'Non évalué'
                        END as appreciation
                    FROM etudiant e
                    INNER JOIN inscrire i ON e.num_etu = i.fk_num_etu
                    LEFT JOIN evaluer ev ON e.num_etu = ev.fk_num_etu AND ev.fk_id_Ac = ?
                    LEFT JOIN ecue ec ON ev.fk_id_ECUE = ec.id_ECUE
                    LEFT JOIN ue u ON ec.id_UE = u.id_UE 
                    WHERE i.fk_id_Ac = ? AND e.fk_id_niv_etu = ? AND e.fk_id_filiere = ? {$whereEcue}
                    ORDER BY e.nom_etu, e.prenoms_etu, u.lib_UE, ec.lib_ECUE
                ");
                $stmt->execute($params);
                $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $resultats]);
                break;
                
            case 'exporter_resultats':
                $anneeId = intval($_POST['annee_id'] ?? 0);
                $niveauId = intval($_POST['niveau_id'] ?? 0);
                $filiereId = intval($_POST['filiere_id'] ?? 0); 
                $format = $_POST['format'] ?? 'excel';
                
                if ($anneeId <= 0 || $niveauId <= 0 || $filiereId <= 0) {
                    echo json_encode(['success' => false, 'message' => "Paramètres d'export manquants."]);
                    exit;
                }
                
                // Récupérer les données pour l'export
                $stmtExport = $pdo->prepare("
                    SELECT 
                        e.num_etu,
                        e.nom_etu,
                        e.prenoms_etu,
                        u.lib_UE,
                        ec.lib_ECUE,
                        ec.credit_ECUE,
                        ev.note,
                        ev.dte_eval,
                        CASE 
                            WHEN ev.note >= 16 THEN 'Excellent'
                            WHEN ev.note >= 14 THEN 'Bien'
                            WHEN ev.note >= 10 THEN 'Passable'
                            WHEN ev.note IS NOT NULL THEN 'Insuffisant'
                            ELSE 'Non évalué'
                        END as appreciation
                    FROM etudiant e
                    INNER JOIN inscrire i ON e.num_etu = i.fk_num_etu
                    LEFT JOIN evaluer ev ON e.num_etu = ev.fk_num_etu AND ev.fk_id_Ac = ?
                    LEFT JOIN ecue ec ON ev.fk_id_ECUE = ec.id_ECUE
                    LEFT JOIN ue u ON ec.id_UE = u.id_UE 
                    WHERE i.fk_id_Ac = ? AND e.fk_id_niv_etu = ? AND e.fk_id_filiere = ?
                    ORDER BY e.nom_etu, e.prenoms_etu, u.lib_UE, ec.lib_ECUE
                ");
                $stmtExport->execute([$anneeId, $anneeId, $niveauId, $filiereId]); // MODIFIÉ ICI: $anneeId ajouté
                $exportData = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

                if (empty($exportData)) {
                    echo json_encode(['success' => false, 'message' => 'Aucune donnée à exporter pour les critères sélectionnés.']);
                    exit;
                }

                // Génération du fichier (simplifiée pour l'exemple)
                $filename_base = "resultats_annee_{$anneeId}_niveau_{$niveauId}_filiere_{$filiereId}";
                $output = '';

                if ($format === 'excel') {
                    header('Content-Type: application/vnd.ms-excel');
                    header("Content-Disposition: attachment; filename=\"{$filename_base}.xls\"");
                    $output = "Matricule\tNom\tPrénom\tUE\tECUE\tCrédits ECUE\tNote\tDate évaluation\tAppréciation\n"; 
                    foreach ($exportData as $row) {
                        $output .= implode("\t", array_values($row)) . "\n";
                    }
                } elseif ($format === 'pdf') {
                    echo json_encode([
                        'success' => true, 
                        'message' => "Génération PDF en cours (fonctionnalité complète à implémenter).",
                        'download_url' => "#" 
                    ]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => "Format d'export non supporté."]);
                    exit;
                }

                echo $output;
                exit; 
                
            default:
                throw new Exception("Action non reconnue");
        }
        
    } catch (Exception $e) {
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
    <title>SYGECOS - Résultats d'évaluations</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Reprise des styles de base */
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-primary); background-color: var(--gray-50); color: var(--gray-800); overflow-x: hidden; }
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-collapsed-width); }

        /* Styles sidebar et topbar identiques */
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%); color: white; z-index: 1000; transition: all var(--transition-normal); overflow-y: auto; overflow-x: hidden; }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .topbar { height: var(--topbar-height); background: var(--white); border-bottom: 1px solid var(--gray-200); padding: 0 var(--space-6); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; }

        .page-content { padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-2); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); }

        .form-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: var(--space-6); }
        .form-card-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); margin-bottom: var(--space-6); border-bottom: 1px solid var(--gray-200); padding-bottom: var(--space-4); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6); }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: var(--text-sm); font-weight: 500; color: var(--gray-700); margin-bottom: var(--space-2); }
        .form-group input, .form-group select { padding: var(--space-3); border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: var(--text-base); color: var(--gray-800); transition: all var(--transition-fast); }
        .form-group input:disabled { background-color: var(--gray-100); color: var(--gray-500); cursor: not-allowed; }

        .btn { padding: var(--space-3) var(--space-5); border-radius: var(--radius-md); font-size: var(--text-base); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover { background-color: var(--accent-700); }
        .btn-secondary { background-color: var(--gray-200); color: var(--gray-700); } .btn-secondary:hover { background-color: var(--gray-300); }
        .btn-success { background-color: var(--success-500); color: white; }
        .btn-sm { padding: var(--space-2) var(--space-3); font-size: var(--text-sm); }

        /* === DASHBOARD CARDS === */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-8); }
        .stat-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); text-align: center; }
        .stat-icon { font-size: var(--text-2xl); margin-bottom: var(--space-3); }
        .stat-value { font-size: var(--text-3xl); font-weight: 700; margin: var(--space-2) 0; }
        .stat-label { color: var(--gray-600); font-size: var(--text-base); }

        .stat-card.primary { border-left: 4px solid var(--accent-500); }
        .stat-card.primary .stat-icon { color: var(--accent-500); }
        .stat-card.primary .stat-value { color: var(--accent-600); }

        .stat-card.success { border-left: 4px solid var(--success-500); }
        .stat-card.success .stat-icon { color: var(--success-500); }
        .stat-card.success .stat-value { color: var(--success-500); }

        .stat-card.warning { border-left: 4px solid var(--warning-500); }
        .stat-card.warning .stat-icon { color: var(--warning-500); }
        .stat-card.warning .stat-value { color: var(--warning-500); }

        .stat-card.error { border-left: 4px solid var(--error-500); }
        .stat-card.error .stat-icon { color: var(--error-500); }
        .stat-card.error .stat-value { color: var(--error-500); }

        /* === GRAPHIQUES === */
        .chart-container { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: var(--space-6); }
        .chart-title { font-size: var(--text-lg); font-weight: 600; color: var(--gray-900); margin-bottom: var(--space-4); }
        .chart-placeholder { height: 300px; background: var(--gray-50); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: var(--gray-500); }

        /* === TABLEAU RESULTATS === */
        .results-table { width: 100%; border-collapse: collapse; margin-top: var(--space-4); }
        .results-table th, .results-table td { padding: var(--space-3); border-bottom: 1px solid var(--gray-200); text-align: left; }
        .results-table th { background-color: var(--gray-50); font-weight: 600; color: var(--gray-700); font-size: var(--text-sm); }
        .results-table tbody tr:hover { background-color: var(--gray-50); }

        .appreciation-badge { padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: var(--text-xs); font-weight: 600; text-align: center; }
        .appreciation-badge.excellent { background: var(--secondary-100); color: var(--secondary-800); }
        .appreciation-badge.bien { background: var(--accent-100); color: var(--accent-800); }
        .appreciation-badge.passable { background: #fef3c7; color: #92400e; }
        .appreciation-badge.insuffisant { background: #fecaca; color: #dc2626; }
        .appreciation-badge.non-evalue { background: var(--gray-100); color: var(--gray-600); }

        /* Messages d'alerte */
        .alert { padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: none; }
        .alert.success { background-color: var(--secondary-50); color: var(--secondary-600); border: 1px solid var(--secondary-100); }
        .alert.error { background-color: #fef2f2; color: var(--error-500); border: 1px solid #fecaca; }
        .alert.info { background-color: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-200); }

        .loading { opacity: 0.6; pointer-events: none; }
        .spinner { width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid var(--accent-500); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
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
                    <h1 class="page-title-main">Résultats d'évaluations</h1>
                    <p class="page-subtitle">Analyse et consultation des résultats académiques</p>
                </div>

                <div id="alertMessage" class="alert"></div>

                <div class="form-card">
                    <h3 class="form-card-title">Sélection des critères</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="annee_id">Année académique</label>
                            <input type="text" id="annee_display" value="<?php echo htmlspecialchars($anneeActive['annee_libelle'] ?? 'Non définie'); ?>" disabled>
                            <input type="hidden" id="annee_id" value="<?php echo $anneeActive['id_Ac'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="filiere_id">Filière <span style="color: var(--error-500);">*</span></label>
                            <select id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionner une filière</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id_filiere']; ?>">
                                        <?php echo htmlspecialchars($filiere['lib_filiere']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="niveau_id">Niveau d'étude <span style="color: var(--error-500);">*</span></label>
                            <select id="niveau_id" name="niveau_id" required disabled>
                                <option value="">Sélectionner un niveau</option>
                                </select>
                        </div>
                        <div class="form-group">
                            <label for="vue_type">Type de vue</label>
                            <select id="vue_type" name="vue_type">
                                <option value="synthese">Vue synthèse</option>
                                <option value="detaillee">Vue détaillée</option>
                                </select>
                        </div>
                        <div class="form-group" style="display: flex; align-items: end;">
                            <button type="button" class="btn btn-primary" id="analyserBtn">
                                <i class="fas fa-chart-bar"></i> Analyser
                            </button>
                        </div>
                    </div>
                </div>

                <div id="dashboardSection" style="display: none;">
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-value" id="totalEtudiants">0</div>
                            <div class="stat-label">Étudiants inscrits</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-value" id="moyenneGenerale">0.00</div>
                            <div class="stat-label">Moyenne générale</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                            <div class="stat-value" id="totalNotes">0</div>
                            <div class="stat-label">Notes saisies</div>
                        </div>
                        <div class="stat-card error">
                            <div class="stat-icon"><i class="fas fa-book"></i></div>
                            <div class="stat-value" id="ecuesEvaluees">0</div>
                            <div class="stat-label">ECUE évaluées</div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3 class="chart-title">Répartition des appréciations</h3>
                        <div class="chart-placeholder" id="chartRepartition">
                            <i class="fas fa-chart-pie" style="font-size: 3rem; color: var(--gray-400);"></i>
                            <span style="margin-left: var(--space-4);">Graphique de répartition des notes</span>
                        </div>
                    </div>
                </div>

                <div class="form-card" id="resultsSection" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
                        <h3>Résultats détaillés</h3>
                        <div>
                            <button class="btn btn-success btn-sm" id="exportExcelBtn">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button class="btn btn-secondary btn-sm" id="exportPdfBtn">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="results-table" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>UE</th>
                                    <th>ECUE</th>
                                    <th>Crédits ECUE</th> <th>Note</th>
                                    <th>Date évaluation</th>
                                    <th>Appréciation</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Variables globales
        let currentAnneeId = 0;
        let currentFiliereId = 0;
        let currentNiveauId = 0;

        // Fonctions utilitaires
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
            }
        }

        // Événement pour le changement de filière
        document.getElementById('filiere_id').addEventListener('change', async function() {
            const filiereId = this.value;
            const niveauSelect = document.getElementById('niveau_id');
            
            // --- Sauvegarder la valeur actuellement sélectionnée du niveau ---
            const selectedNiveauBeforeChange = niveauSelect.value;
            
            // Réinitialiser les niveaux et désactiver le champ
            niveauSelect.innerHTML = '<option value="">Sélectionner un niveau</option>';
            niveauSelect.disabled = true;
            
            // Masquer les sections de résultats et de statistiques
            document.getElementById('dashboardSection').style.display = 'none';
            document.getElementById('resultsSection').style.display = 'none';

            if (filiereId) {
                currentFiliereId = filiereId; 
                try {
                    const result = await makeAjaxRequest({
                        action: 'charger_niveaux_par_filiere',
                        filiere_id: filiereId
                    });
                    
                    if (result.success) {
                        result.data.forEach(niveau => {
                            const option = document.createElement('option');
                            option.value = niveau.id_niv_etu;
                            option.textContent = htmlspecialchars(niveau.lib_niv_etu);
                            niveauSelect.appendChild(option);
                        });
                        
                        // --- Tenter de réappliquer la sélection précédente du niveau ---
                        if (selectedNiveauBeforeChange && Array.from(niveauSelect.options).some(opt => opt.value === selectedNiveauBeforeChange)) {
                            niveauSelect.value = selectedNiveauBeforeChange;
                            currentNiveauId = selectedNiveauBeforeChange; // Mettre à jour la variable globale si la sélection est rétablie
                        } else {
                            currentNiveauId = 0; // Réinitialiser si l'ancienne sélection n'est plus valide
                        }

                        niveauSelect.disabled = false; // Activer le champ niveau
                    } else {
                        showAlert(result.message, 'error');
                    }
                } catch (error) {
                    showAlert('Erreur lors du chargement des niveaux d\'étude', 'error');
                }
            } else {
                currentFiliereId = 0; 
                currentNiveauId = 0; 
            }
            // Déclencher l'événement 'change' sur le niveau si une valeur a été sélectionnée (pour rafraîchir l'affichage des résultats)
            if (currentNiveauId) {
                niveauSelect.dispatchEvent(new Event('change'));
            }
        });

        // Événement pour le changement de niveau (pour réinitialiser l'affichage)
        document.getElementById('niveau_id').addEventListener('change', function() {
            const niveauId = this.value;
            if (niveauId) {
                currentNiveauId = niveauId; // Mettre à jour la variable globale
            } else {
                currentNiveauId = 0; // Réinitialiser
            }
            // Masquer les sections de résultats et de statistiques lors du changement de niveau
            document.getElementById('dashboardSection').style.display = 'none';
            document.getElementById('resultsSection').style.display = 'none';
        });

        // Analyser les résultats
        document.getElementById('analyserBtn').addEventListener('click', async function() {
            const anneeId = document.getElementById('annee_id').value;
            const filiereId = document.getElementById('filiere_id').value; 
            const niveauId = document.getElementById('niveau_id').value;
            const vueType = document.getElementById('vue_type').value;
            
            if (!anneeId || !filiereId || !niveauId) { 
                showAlert('Veuillez sélectionner une année, une filière et un niveau', 'error');
                return;
            }
            
            currentAnneeId = anneeId;
            currentFiliereId = filiereId; 
            currentNiveauId = niveauId;
            
            const btn = this;
            const originalText = btn.innerHTML;
            
            try {
                btn.innerHTML = '<div class="spinner"></div> Analyse...';
                btn.disabled = true;
                
                // Masquer les sections existantes avant de recharger
                document.getElementById('dashboardSection').style.display = 'none';
                document.getElementById('resultsSection').style.display = 'none';

                // Charger les statistiques si la vue est 'synthese'
                if (vueType === 'synthese') {
                    await chargerStatistiques(anneeId, filiereId, niveauId); 
                } else {
                    document.getElementById('dashboardSection').style.display = 'none';
                }
                
                if (vueType === 'detaillee') {
                    await chargerResultatsDetailles(anneeId, filiereId, niveauId); 
                }
                
            } catch (error) {
                showAlert('Erreur lors de l\'analyse: ' + error.message, 'error'); 
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

        // Charger les statistiques
        async function chargerStatistiques(anneeId, filiereId, niveauId) { 
            try {
                const result = await makeAjaxRequest({
                    action: 'obtenir_statistiques_niveau',
                    annee_id: anneeId,
                    filiere_id: filiereId, 
                    niveau_id: niveauId
                });
                
                if (result.success) {
                    const stats = result.stats;
                    
                    document.getElementById('totalEtudiants').textContent = stats.total_etudiants || '0';
                    document.getElementById('moyenneGenerale').textContent = stats.moyenne_generale ? parseFloat(stats.moyenne_generale).toFixed(2) : '0.00';
                    document.getElementById('totalNotes').textContent = stats.total_notes || '0';
                    document.getElementById('ecuesEvaluees').textContent = stats.ecues_evaluees || '0';
                    
                    // Afficher le dashboard
                    document.getElementById('dashboardSection').style.display = 'block';
                    
                    // Afficher la répartition (simulation)
                    if (result.repartition && result.repartition.length > 0) {
                        afficherRepartition(result.repartition);
                    } else {
                        // Si pas de données de répartition, afficher un message par défaut
                        document.getElementById('chartRepartition').innerHTML = `
                            <i class="fas fa-chart-pie" style="font-size: 3rem; color: var(--gray-400);"></i>
                            <span style="margin-left: var(--space-4);">Aucune donnée de répartition disponible.</span>
                        `;
                    }
                    
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Erreur lors du chargement des statistiques', 'error');
            }
        }

        // Afficher la répartition (version simplifiée)
        function afficherRepartition(repartition) {
            const container = document.getElementById('chartRepartition');
            let total = 0;
            repartition.forEach(item => total += parseInt(item.nombre));
            
            let html = '<div style="display: flex; flex-wrap: wrap; justify-content: space-around; align-items: center; height: 100%;">';
            
            repartition.forEach(item => {
                const pourcentage = total > 0 ? ((item.nombre / total) * 100).toFixed(1) : 0;
                const couleur = {
                    'excellent': 'var(--success-500)',
                    'bien': 'var(--accent-500)', 
                    'passable': 'var(--warning-500)',
                    'insuffisant': 'var(--error-500)'
                }[item.categorie] || 'var(--gray-500)';
                
                html += `
                    <div style="text-align: center; margin: var(--space-3);">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: ${couleur}; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem; margin: 0 auto var(--space-2);">
                            ${item.nombre}
                        </div>
                        <div style="font-weight: 600; text-transform: capitalize;">${item.categorie}</div>
                        <div style="color: var(--gray-600); font-size: var(--text-sm);">${pourcentage}%</div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        // Charger les résultats détaillés
        async function chargerResultatsDetailles(anneeId, filiereId, niveauId) { 
            try {
                const result = await makeAjaxRequest({
                    action: 'obtenir_resultats_detailles',
                    annee_id: anneeId,
                    filiere_id: filiereId, 
                    niveau_id: niveauId
                });
                
                if (result.success) {
                    afficherTableauResultats(result.data);
                    document.getElementById('resultsSection').style.display = 'block';
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Erreur lors du chargement des résultats détaillés', 'error');
            }
        }

        // Afficher le tableau des résultats
        function afficherTableauResultats(resultats) {
            const tbody = document.querySelector('#resultsTable tbody');
            tbody.innerHTML = '';
            
            if (resultats.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: var(--space-8); color: var(--gray-500);">
                                        <i class="fas fa-search-minus" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                        Aucun résultat trouvé pour cette sélection.
                                   </td></tr>`;
                return;
            }

            resultats.forEach(resultat => {
                const row = document.createElement('tr');
                const appreciationClass = resultat.appreciation.toLowerCase().replace(' ', '-');
                
                row.innerHTML = `
                    <td><strong>${htmlspecialchars(resultat.num_etu)}</strong></td>
                    <td>${htmlspecialchars(resultat.nom_etu)}</td>
                    <td>${htmlspecialchars(resultat.prenoms_etu)}</td>
                    <td>${htmlspecialchars(resultat.lib_UE || '-')}</td>
                    <td>${htmlspecialchars(resultat.lib_ECUE || '-')}</td>
                    <td>${htmlspecialchars(resultat.credit_ECUE || '-')}</td> <td>${resultat.note ? parseFloat(resultat.note).toFixed(2) : '-'}</td>
                    <td>${resultat.dte_eval ? new Date(resultat.dte_eval).toLocaleDateString('fr-FR') : '-'}</td>
                    <td><span class="appreciation-badge ${appreciationClass}">${htmlspecialchars(resultat.appreciation)}</span></td>
                `;
                tbody.appendChild(row);
            });
        }

        // Export fonctions
        document.getElementById('exportExcelBtn').addEventListener('click', async function() {
            if (!currentAnneeId || !currentNiveauId || !currentFiliereId) { 
                showAlert('Veuillez d\'abord effectuer une analyse complète (année, filière, niveau)', 'warning');
                return;
            }
            
            const headers = ['Matricule', 'Nom', 'Prénom', 'UE', 'ECUE', 'Crédits ECUE', 'Note', 'Date évaluation', 'Appréciation'];
            const data = [];
            document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
                const rowData = [];
                row.querySelectorAll('td').forEach((cell, index) => {
                    if (index === 7) { 
                        rowData.push(cell.querySelector('.appreciation-badge')?.textContent || cell.textContent);
                    } else {
                        rowData.push(cell.textContent);
                    }
                });
                data.push(rowData);
            });

            if (data.length === 0) {
                showAlert('Aucune donnée à exporter.', 'warning');
                return;
            }

            const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Résultats Evaluations");
            const anneeLib = document.getElementById('annee_display').value.replace(' (Active)', '');
            const filiereLib = document.getElementById('filiere_id').selectedOptions[0]?.textContent || 'Tous';
            const niveauLib = document.getElementById('niveau_id').selectedOptions[0]?.textContent || 'Tous';
            const filename = `Resultats_Eval_${filiereLib}_${niveauLib}_${anneeLib}.xlsx`;
            XLSX.writeFile(wb, filename);
            showAlert('Exportation Excel réussie!', 'success');
        });


        document.getElementById('exportPdfBtn').addEventListener('click', async function() {
            if (!currentAnneeId || !currentNiveauId || !currentFiliereId) { 
                showAlert('Veuillez d\'abord effectuer une analyse complète (année, filière, niveau)', 'warning');
                return;
            }
            
            const headers = [['Matricule', 'Nom', 'Prénom', 'UE', 'ECUE', 'Crédits', 'Note', 'Date Eval', 'Appréciation']];
            const data = [];
            document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
                 const rowData = [];
                row.querySelectorAll('td').forEach((cell, index) => {
                    if (index === 7) { 
                        rowData.push(cell.querySelector('.appreciation-badge')?.textContent || cell.textContent);
                    } else {
                        rowData.push(cell.textContent);
                    }
                });
                data.push(rowData);
            });

            if (data.length === 0) {
                showAlert('Aucune donnée à exporter.', 'warning');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(18);
            doc.text("Résultats d'Évaluations", 14, 20);
            
            const anneeLib = document.getElementById('annee_display').value.replace(' (Active)', '');
            const filiereLib = document.getElementById('filiere_id').selectedOptions[0]?.textContent || 'Tous';
            const niveauLib = document.getElementById('niveau_id').selectedOptions[0]?.textContent || 'Tous';
            
            doc.setFontSize(10);
            doc.text(`Année Académique: ${anneeLib}`, 14, 30);
            doc.text(`Filière: ${filiereLib}`, 14, 35);
            doc.text(`Niveau: ${niveauLib}`, 14, 40);
            doc.text(`Date d'export: ${new Date().toLocaleDateString('fr-FR')}`, 14, 45);

            doc.autoTable({
                head: headers,
                body: data,
                startY: 50,
                styles: { fontSize: 8, cellPadding: 2, valign: 'middle' },
                headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [241, 245, 249] }
            });

            const filename = `Resultats_Eval_${filiereLib}_${niveauLib}_${anneeLib}.pdf`;
            doc.save(filename);
            showAlert('Exportation PDF réussie!', 'success');
        });

        // HTML Escape Function for Security
        function htmlspecialchars(str) {
            if (str === null || typeof str === 'undefined') {
                return '';
            }
            str = String(str);
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function(m) { return map[m]; });
        }


        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            const anneeId = document.getElementById('annee_id').value;
            if (!anneeId) {
                showAlert('Aucune année académique active trouvée.', 'warning');
            }
            // Initialiser les niveaux si une filière est déjà sélectionnée au chargement (peu probable si vide par défaut)
            const filiereSelect = document.getElementById('filiere_id');
            if (filiereSelect.value) {
                filiereSelect.dispatchEvent(new Event('change')); 
            }
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</body>
</html>