<?php
// mes_evaluations.php
require_once 'config.php'; // Assurez-vous que config.php gère les sessions et la connexion PDO

if (!isLoggedIn()) { // Vérifie si l'utilisateur est connecté
    redirect('loginForm.php');
}

// Assurez-vous que l'ID utilisateur est stocké en session après connexion
// Exemple : $_SESSION['user_id']
$current_user_id = $_SESSION['user_id'] ?? null;
$current_num_etu = null; // Nous allons récupérer le num_etu de l'étudiant

if (!$current_user_id) {
    // Gérer l'erreur si l'ID utilisateur n'est pas en session
    redirect('loginForm.php'); // Ou gérer l'erreur différemment
}

// Récupérer le numéro d'étudiant (num_etu) basé sur l'ID utilisateur connecté
try {
    $stmtEtu = $pdo->prepare("SELECT num_etu FROM etudiant WHERE fk_id_util = ?");
    $stmtEtu->execute([$current_user_id]);
    $etudiantInfo = $stmtEtu->fetch(PDO::FETCH_ASSOC);

    if ($etudiantInfo) {
        $current_num_etu = $etudiantInfo['num_etu'];
    } else {
        // L'utilisateur connecté n'est pas associé à un étudiant
        error_log("Utilisateur ID " . $current_user_id . " non associé à un étudiant.");
        echo "Vous n'êtes pas reconnu comme un étudiant. Veuillez contacter l'administration.";
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur de récupération du numéro étudiant: " . $e->getMessage());
    echo "Une erreur est survenue lors de la récupération de vos informations.";
    exit;
}

// Récupérer l'année académique active ou la plus récente
$anneeAcademiqueActiveId = null;
$anneeAcademiqueLibelle = 'N/A';
try {
    // On essaie de récupérer l'année active, sinon la plus récente
    $stmtAnnee = $pdo->query("SELECT id_Ac, CONCAT(YEAR(date_deb), '-', YEAR(date_fin)) as annee_libelle FROM année_academique WHERE statut = 'active' OR est_courante = 1 ORDER BY date_fin DESC LIMIT 1");
    $activeYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    if ($activeYear) {
        $anneeAcademiqueActiveId = $activeYear['id_Ac'];
        $anneeAcademiqueLibelle = $activeYear['annee_libelle'];
    }
} catch (PDOException $e) {
    error_log("Erreur récupération année académique: " . $e->getMessage());
}

$evaluations = [];
if ($current_num_etu && $anneeAcademiqueActiveId) {
    try {
        // Récupérer les évaluations de l'étudiant pour l'année académique active
        $stmtEvaluations = $pdo->prepare("
            SELECT
                ue.lib_UE,
                ecue.lib_ECUE,
                ecue.credit_ECUE,
                evaluer.note,
                evaluer.dte_eval
            FROM
                evaluer
            INNER JOIN
                ecue ON evaluer.fk_id_ECUE = ecue.id_ECUE
            INNER JOIN
                ue ON ecue.fk_id_UE = ue.id_UE
            WHERE
                evaluer.fk_num_etu = ? AND evaluer.fk_id_Ac = ?
            ORDER BY
                ue.lib_UE, ecue.lib_ECUE
        ");
        $stmtEvaluations->execute([$current_num_etu, $anneeAcademiqueActiveId]);
        $evaluations = $stmtEvaluations->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erreur de récupération des évaluations: " . $e->getMessage());
    }
}

// Fonctions utilitaires pour l'affichage (si non déjà dans config.php ou ailleurs)
function formatDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    $date = new DateTime($dateStr);
    return $date->format('d/m/Y');
}

function getAppreciation($note) {
    if ($note === null || $note === '') return ['text' => '-', 'class' => ''];
    $note = (float)$note;
    if ($note >= 16) return ['text' => 'Excellent', 'class' => 'badge-success'];
    if ($note >= 14) return ['text' => 'Très Bien', 'class' => 'badge-info'];
    if ($note >= 12) return ['text' => 'Bien', 'class' => 'badge-info'];
    if ($note >= 10) return ['text' => 'Passable', 'class' => 'badge-warning'];
    return ['text' => 'Insuffisant', 'class' => 'badge-danger'];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Mes Évaluations</title>
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

        /* === PAGE SPECIFIC STYLES === */
        .page-content { padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-8); display: flex; justify-content: space-between; align-items: center; }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); margin-top: var(--space-2); }

        .student-profile { display: grid; grid-template-columns: 280px 1fr; gap: var(--space-8); margin-bottom: var(--space-8); }
        .profile-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .profile-header { display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: var(--space-6); }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background-color: var(--gray-200); display: flex; align-items: center; justify-content: center; margin-bottom: var(--space-4); overflow: hidden; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-name { font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1); }
        .profile-id { color: var(--gray-600); font-size: var(--text-sm); margin-bottom: var(--space-4); }
        .profile-badge { display: inline-block; padding: var(--space-1) var(--space-3); background-color: var(--secondary-100); color: var(--secondary-600); border-radius: var(--radius-md); font-size: var(--text-sm); font-weight: 600; }
        .profile-details { width: 100%; }
        .detail-item { display: flex; justify-content: space-between; padding: var(--space-3) 0; border-bottom: 1px solid var(--gray-200); }
        .detail-label { color: var(--gray-600); font-weight: 600; }
        .detail-value { color: var(--gray-800); text-align: right; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6); }
        .info-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .info-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); }
        .info-card-title { font-size: var(--text-lg); font-weight: 600; color: var(--gray-900); }
        .info-card-icon { width: 40px; height: 40px; background-color: var(--accent-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: var(--accent-600); }

        .table-container { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow: hidden; margin-bottom: var(--space-8); }
        .table-header { padding: var(--space-6); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); }
        .table-actions { display: flex; gap: var(--space-3); align-items: center; }

        .table-wrapper { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: var(--space-3); text-align: left; border-bottom: 1px solid var(--gray-200); font-size: var(--text-sm); }
        .data-table th { background-color: var(--gray-50); font-weight: 600; color: var(--gray-700); }
        .data-table tbody tr:hover { background-color: var(--gray-50); }
        .data-table td { color: var(--gray-800); }

        .badge { padding: var(--space-1) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-xs); font-weight: 600; }
        .badge-success { background-color: var(--secondary-100); color: var(--secondary-600); }
        .badge-warning { background-color: #fef3c7; color: #d97706; }
        .badge-info { background-color: var(--accent-100); color: var(--accent-600); }
        .badge-danger { background-color: #fecaca; color: #dc2626; } /* Ajouté pour appréciation "Insuffisant" */


        .action-buttons { display: flex; gap: var(--space-1); }
        .btn { padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-sm); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover:not(:disabled) { background-color: var(--accent-700); }
        .btn-success { background-color: var(--success-500); color: white; } .btn-success:hover:not(:disabled) { background-color: var(--secondary-600); }
        .btn-warning { background-color: var(--warning-500); color: white; } .btn-warning:hover:not(:disabled) { background-color: #f59e0b; }
        .btn-danger { background-color: var(--error-500); color: white; } .btn-danger:hover:not(:disabled) { background-color: #dc2626; }
        .btn-outline { background-color: transparent; color: var(--accent-600); border: 1px solid var(--accent-600); } .btn-outline:hover { background-color: var(--accent-50); }
        .btn-sm { padding: var(--space-1) var(--space-2); font-size: var(--text-xs); }

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
        @media (max-width: 992px) {
            .student-profile { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); } /* Corrected class for mobile open */
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .page-header { flex-direction: column; align-items: flex-start; gap: var(--space-4); }
            .info-grid { grid-template-columns: 1fr; }
        }

        /* Styles pour le modal de bulletin */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            padding: var(--space-8);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-xl);
            width: 95%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-sizing: border-box;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .modal-header h3 {
            font-size: var(--text-2xl);
            color: var(--gray-900);
            font-weight: 700;
        }

        .modal-close { /* Renamed from .close for clarity, but original .close still works */
            color: var(--gray-500);
            font-size: var(--text-3xl);
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
            line-height: 1;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: var(--gray-800);
            text-decoration: none;
        }

        /* Styles spécifiques au bulletin */
        .bulletin {
            padding: var(--space-4);
            font-family: 'Segoe UI', sans-serif;
            color: var(--gray-800);
        }
        .bulletin-header {
            text-align: center;
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-3);
            border-bottom: 2px solid var(--accent-600);
        }
        .bulletin-title {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--accent-800);
            margin-bottom: var(--space-1);
        }
        .bulletin-subtitle {
            font-size: var(--text-base);
            color: var(--gray-600);
        }
        .bulletin-student-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
            margin-bottom: var(--space-6);
        }
        .info-section {
            background: var(--gray-50);
            padding: var(--space-4);
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
        }
        .info-section h4 {
            font-size: var(--text-lg);
            color: var(--primary-700);
            margin-bottom: var(--space-3);
            border-bottom: 1px dashed var(--gray-300);
            padding-bottom: var(--space-2);
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            font-size: var(--text-sm);
            padding: var(--space-1) 0;
        }
        .info-label {
            font-weight: 600;
            color: var(--gray-700);
        }
        .bulletin-notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: var(--space-6);
        }
        .bulletin-notes-table th, .bulletin-notes-table td {
            border: 1px solid var(--gray-300);
            padding: var(--space-2);
            text-align: left;
            font-size: var(--text-sm);
        }
        .bulletin-notes-table th {
            background-color: var(--gray-100);
            color: var(--gray-700);
            font-weight: 600;
        }
        .bulletin-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
            margin-top: var(--space-6);
        }
        .bulletin-summary .info-section {
            text-align: center;
        }
        .bulletin-summary .info-item {
            justify-content: center;
        }
        .bulletin-summary .info-label {
            margin-right: var(--space-2);
        }
        @media print {
            body * { visibility: hidden; }
            .modal-content, .modal-content * { visibility: visible; }
            .modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .modal-header, .btn { display: none !important; }
            .bulletin { padding: 1cm !important; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar_etudiant.php'; // Inclure la nouvelle sidebar_etudiant.php?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; ?>

            <div class="page-content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title-main">Mes Évaluations</h1>
                        <p class="page-subtitle">Consultez vos évaluations pour l'année académique <span id="annee_academique_display"><?php echo htmlspecialchars($anneeAcademiqueLibelle); ?></span></p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="voirBulletin('<?php echo htmlspecialchars($current_num_etu); ?>')">
                            <i class="fas fa-download"></i> Télécharger Bulletin
                        </button>
                    </div>
                </div>

                <div class="info-card" style="margin-bottom: var(--space-6);">
                    <div style="display: flex; gap: var(--space-4); align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <label for="filterSemestre" style="display: block; margin-bottom: var(--space-2); color: var(--gray-600); font-weight: 600;">Semestre</label>
                            <select id="filterSemestre" class="form-control">
                                <option value="">Tous</option>
                                <option value="1">Semestre 1</option>
                                <option value="2">Semestre 2</option>
                                </select>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label for="filterType" style="display: block; margin-bottom: var(--space-2); color: var(--gray-600); font-weight: 600;">Type d'évaluation</label>
                            <select id="filterType" class="form-control">
                                <option value="">Tous</option>
                                <option value="Examen">Examen</option>
                                <option value="Projet">Projet</option>
                                <option value="Devoir">Devoir</option>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label for="filterStatut" style="display: block; margin-bottom: var(--space-2); color: var(--gray-600); font-weight: 600;">Statut</label>
                            <select id="filterStatut" class="form-control">
                                <option value="">Tous</option>
                                <option value="Terminé">Terminé</option>
                                <option value="À venir">À venir</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" id="applyFiltersBtn" style="align-self: flex-end;">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-clipboard-list"></i> Liste de mes Évaluations
                        </h3>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Matière (UE)</th>
                                    <th>Élément Constitutif (ECUE)</th>
                                    <th>Crédits</th>
                                    <th>Date Évaluation</th>
                                    <th>Note (/20)</th>
                                    <th>Appréciation</th>
                                </tr>
                            </thead>
                            <tbody id="evaluationsTableBody">
                                <?php if (empty($evaluations)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <p>Aucune évaluation trouvée pour cette année académique.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($evaluations as $eval): ?>
                                        <?php $appreciation = getAppreciation($eval['note']); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($eval['lib_UE']); ?></td>
                                            <td><?php echo htmlspecialchars($eval['lib_ECUE']); ?></td>
                                            <td><?php echo htmlspecialchars($eval['credit_ECUE']); ?></td>
                                            <td><?php echo formatDate($eval['dte_eval']); ?></td>
                                            <td><?php echo ($eval['note'] !== null) ? number_format($eval['note'], 2, ',', '.') : '-'; ?></td>
                                            <td><span class="badge <?php echo $appreciation['class']; ?>"><?php echo $appreciation['text']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="bulletinModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Bulletin de l'étudiant</h3>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="imprimerBulletin()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <span class="modal-close" onclick="closeModal('bulletinModal')">&times;</span>
                </div>
            </div>
            <div id="bulletinContent" class="bulletin">
                </div>
        </div>
    </div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script>
        // Expose jsPDF globally
        window.jsPDF = window.jspdf.jsPDF;

        // Variables PHP passées à JavaScript
        const currentStudentNumEtu = "<?php echo htmlspecialchars($current_num_etu); ?>";
        const currentActiveAnneeId = "<?php echo htmlspecialchars($anneeAcademiqueActiveId); ?>";
        const initialEvaluations = <?php echo json_encode($evaluations); ?>; // Passer les données initiales

        let displayedEvaluations = [...initialEvaluations]; // Copie pour les filtres

        // Fonctions utilitaires
        function formatDate(dateStr) {
            if (!dateStr || dateStr === '0000-00-00') return '-';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleDateString('fr-FR');
        }

        function getAppreciation(note) {
            if (note === null || note === '') return { text: '-', class: '' };
            const n = parseFloat(note);
            if (isNaN(n)) return { text: '-', class: '' };
            
            if (n >= 16) return { text: 'Excellent', class: 'badge-success' };
            if (n >= 14) return { text: 'Très Bien', class: 'badge-info' };
            if (n >= 12) return { text: 'Bien', class: 'badge-info' };
            if (n >= 10) return { text: 'Passable', class: 'badge-warning' };
            return { text: 'Insuffisant', class: 'badge-danger' };
        }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = show ? 'flex' : 'none';
            }
        }

        // --- Fonctions d'affichage des évaluations ---
        function renderEvaluations(evalsToRender) {
            const tbody = document.getElementById('evaluationsTableBody');
            tbody.innerHTML = ''; // Nettoyer le contenu existant

            if (evalsToRender.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Aucune évaluation trouvée pour cette année académique.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            evalsToRender.forEach(eval => {
                const appreciation = getAppreciation(eval.note);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${eval.lib_UE ? htmlspecialchars(eval.lib_UE) : '-'}</td>
                    <td>${eval.lib_ECUE ? htmlspecialchars(eval.lib_ECUE) : '-'}</td>
                    <td>${eval.credit_ECUE !== null ? htmlspecialchars(eval.credit_ECUE) : '-'}</td>
                    <td>${formatDate(eval.dte_eval)}</td>
                    <td>${eval.note !== null ? parseFloat(eval.note).toFixed(2).replace('.', ',') : '-'}</td>
                    <td><span class="badge ${appreciation.class}">${appreciation.text}</span></td>
                `;
                tbody.appendChild(row);
            });
        }

        // Fonction pour gérer les filtres (implémentation JavaScript simple)
        document.getElementById('applyFiltersBtn').addEventListener('click', function() {
            const filterSemestre = document.getElementById('filterSemestre').value;
            const filterType = document.getElementById('filterType').value;
            const filterStatut = document.getElementById('filterStatut').value;

            let filteredEvaluations = [...initialEvaluations]; // Commencer avec toutes les évaluations

            // Appliquer le filtre par Semestre (nécessite d'ajouter le semestre à vos données)
            // if (filterSemestre) {
            //     filteredEvaluations = filteredEvaluations.filter(eval => eval.semestre == filterSemestre);
            // }

            // Appliquer le filtre par Type (nécessite d'ajouter le type d'évaluation à vos données)
            // if (filterType) {
            //     filteredEvaluations = filteredEvaluations.filter(eval => eval.type_evaluation === filterType);
            // }

            // Appliquer le filtre par Statut (nécessite d'ajouter le statut ou de le déduire de la date)
            // if (filterStatut) {
            //     const now = new Date();
            //     filteredEvaluations = filteredEvaluations.filter(eval => {
            //         const evalDate = new Date(eval.dte_eval);
            //         if (filterStatut === 'Terminé') {
            //             return evalDate < now;
            //         } else if (filterStatut === 'À venir') {
            //             return evalDate >= now;
            //         }
            //         return true; // Tous
            //     });
            // }

            displayedEvaluations = filteredEvaluations;
            renderEvaluations(displayedEvaluations);
        });
        
        // --- Fonctionnalité du bulletin (tirée de gestion_evaluations.php) ---
        async function voirBulletin(numEtu) {
            if (!numEtu || !currentActiveAnneeId) {
                console.error("Numéro étudiant ou année académique manquants pour le bulletin.");
                return;
            }
            
            showLoading(true);
            try {
                const response = await fetch('gestion_evaluations.php', { // Requete vers la page de gestion des notes
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        action: 'obtenir_bulletin', // Action spécifique pour obtenir le bulletin
                        num_etu: numEtu,
                        annee_id: currentActiveAnneeId
                    })
                });
                const result = await response.json();
                
                if (result.success && result.etudiant && result.notes) {
                    afficherBulletin(result.etudiant, result.notes);
                } else {
                    alert(result.message || 'Erreur lors du chargement du bulletin.');
                }
            } catch (error) {
                console.error('Erreur AJAX pour le bulletin:', error);
                alert('Erreur réseau ou serveur lors du chargement du bulletin.');
            } finally {
                showLoading(false);
            }
        }

        function afficherBulletin(etudiant, notes) {
            const content = document.getElementById('bulletinContent');
            
            let totalNotesPonderees = 0;
            let totalCreditsValides = 0;
            let totalCreditsPourMoyenne = 0; // Crédits pour les ECUE avec note
            
            notes.forEach(note => {
                if (note.note !== null && note.note !== '') {
                    const n = parseFloat(note.note);
                    const c = parseFloat(note.credit_ECUE);
                    if (!isNaN(n) && !isNaN(c)) {
                        totalNotesPonderees += n * c;
                        totalCreditsPourMoyenne += c;
                        if (n >= 10) { // Hypothèse: validation si note >= 10
                            totalCreditsValides += c;
                        }
                    }
                }
            });
            
            const moyenne = totalCreditsPourMoyenne > 0 ? (totalNotesPonderees / totalCreditsPourMoyenne).toFixed(2) : '0.00';
            const appreciationMoyenne = getAppreciation(parseFloat(moyenne));

            content.innerHTML = `
                <div class="bulletin-header">
                    <h1 class="bulletin-title">BULLETIN DE NOTES</h1>
                    <p class="bulletin-subtitle">Année académique ${etudiant.annee_libelle}</p>
                    <p class="bulletin-subtitle">Semestre actuel: Tous</p> </div>
                
                <div class="bulletin-student-info">
                    <div class="info-section">
                        <h4>Informations étudiant</h4>
                        <div class="info-item">
                            <span class="info-label">Matricule :</span>
                            <span>${etudiant.num_etu}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nom :</span>
                            <span>${etudiant.nom_etu}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Prénom(s) :</span>
                            <span>${etudiant.prenoms_etu}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date naissance :</span>
                            <span>${formatDate(etudiant.dte_naiss_etu)}</span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h4>Informations académiques</h4>
                        <div class="info-item">
                            <span class="info-label">Niveau :</span>
                            <span>${etudiant.lib_niv_etu}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Filière :</span>
                            <span>${etudiant.lib_filiere || '-'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email :</span>
                            <span>${etudiant.email_etu}</span>
                        </div>
                    </div>
                </div>
                
                <table class="bulletin-notes-table">
                    <thead>
                        <tr>
                            <th>Unité d'Enseignement</th>
                            <th>ECUE</th>
                            <th>Crédits</th>
                            <th>Note (/20)</th>
                            <th>Date évaluation</th>
                            <th>Appréciation</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${notes.map(note => {
                            const appreciation = getAppreciation(note.note);
                            return `
                                <tr>
                                    <td><strong>${htmlspecialchars(note.lib_UE)}</strong></td>
                                    <td>${htmlspecialchars(note.lib_ECUE)}</td>
                                    <td>${htmlspecialchars(note.credit_ECUE)}</td>
                                    <td>${note.note !== null ? parseFloat(note.note).toFixed(2).replace('.', ',') : '-'}</td>
                                    <td>${formatDate(note.dte_eval)}</td>
                                    <td><span class="badge ${appreciation.class}">${appreciation.text}</span></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
                
                <div class="bulletin-summary">
                    <div class="info-section">
                        <h4>Résumé des crédits</h4>
                        <div class="info-item">
                            <span class="info-label">Crédits de l'année :</span>
                            <span>${totalCreditsPourMoyenne}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Crédits validés :</span>
                            <span>${totalCreditsValides}</span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h4>Moyenne générale</h4>
                        <div style="text-align: center; padding: var(--space-4);">
                            <div style="font-size: var(--text-3xl); font-weight: bold; color: var(--accent-600);">
                                ${moyenne.replace('.', ',')}/20
                            </div>
                            <div style="margin-top: var(--space-2);">
                                <span class="badge ${appreciationMoyenne.class}">
                                    ${appreciationMoyenne.text}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: var(--space-8); text-align: right; font-size: var(--text-sm); color: var(--gray-600);">
                    <p>Document généré le ${new Date().toLocaleDateString('fr-FR')}</p>
                </div>
            `;
            
            document.getElementById('bulletinModal').style.display = 'block';
        }

        // Fermer modal (générique)
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Imprimer bulletin
        function imprimerBulletin() {
            window.print();
        }

        // HTML special chars equivalent for JS
        function htmlspecialchars(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // Initialisation de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Appelle la fonction de rendu avec les évaluations initiales chargées par PHP
            renderEvaluations(initialEvaluations);
            initSidebar(); // Assurez-vous que cette fonction est bien définie pour la sidebar
        });

        // Gestion responsive de la sidebar
        function initSidebar() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

            if (sidebarToggle && sidebar && mainContent) {
                // Initial state based on window width
                handleResponsiveLayout(); // Appel à handleResponsiveLayout ici aussi

                sidebarToggle.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('mobile-open');
                        if (mobileMenuOverlay) mobileMenuOverlay.classList.toggle('active');
                        // Toggle icon
                        const barsIcon = sidebarToggle.querySelector('.fa-bars');
                        const timesIcon = sidebarToggle.querySelector('.fa-times'); // CORRIGÉ
                        if (sidebar.classList.contains('mobile-open')) {
                            if (barsIcon) barsIcon.style.display = 'none';
                            if (timesIcon) timesIcon.style.display = 'inline-block';
                        } else {
                            if (barsIcon) barsIcon.style.display = 'inline-block';
                            if (timesIcon) timesIcon.style.display = 'none';
                        }
                    } else {
                        sidebar.classList.toggle('collapsed');
                        mainContent.classList.toggle('sidebar-collapsed');
                    }
                });
            }

            if (mobileMenuOverlay) {
                mobileMenuOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    mobileMenuOverlay.classList.remove('active');
                    // Reset icon
                    const barsIcon = sidebarToggle.querySelector('.fa-bars');
                    const timesIcon = sidebarToggle.querySelector('.fa-times'); // CORRIGÉ
                    if (barsIcon) barsIcon.style.display = 'inline-block';
                    if (timesIcon) timesIcon.style.display = 'none';
                });
            }

            window.addEventListener('resize', handleResponsiveLayout);
        }

        // Responsive layout adjustments
        function handleResponsiveLayout() {
            const isMobile = window.innerWidth < 768;

            // Adjust sidebar state
            if (isMobile) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
                sidebar.classList.remove('mobile-open'); // Ensure it's closed on resize to mobile
                if (mobileMenuOverlay) mobileMenuOverlay.classList.remove('active'); // Hide overlay
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                sidebar.classList.remove('mobile-open'); // Ensure it's closed if was open on mobile and resized to desktop
                if (mobileMenuOverlay) mobileMenuOverlay.classList.remove('active'); // Hide overlay
            }

            // Adjust sidebar toggle icon for mobile
            if (sidebarToggle) {
                const barsIcon = sidebarToggle.querySelector('.fa-bars');
                const timesIcon = sidebarToggle.querySelector('.fa-times'); // CORRIGÉ
                
                // Set default display based on desktop/mobile
                if (barsIcon) barsIcon.style.display = 'inline-block';
                if (timesIcon) timesIcon.style.display = 'none';

                // Override if sidebar is actually open (mobile-open class)
                if (sidebar.classList.contains('mobile-open')) {
                    if (barsIcon) barsIcon.style.display = 'none';
                    if (timesIcon) timesIcon.style.display = 'inline-block';
                }
            }
        }

        // Add a mobile menu overlay if you use it (optional, based on your topbar/sidebar structure)
        const mobileMenuOverlay = document.createElement('div');
        mobileMenuOverlay.id = 'mobileMenuOverlay';
        mobileMenuOverlay.className = 'mobile-menu-overlay';
        document.body.appendChild(mobileMenuOverlay);

    </script>
</body>
</html>