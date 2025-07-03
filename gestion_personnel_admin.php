<?php
// gestion_personnel_admin.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('loginForm.php');
}

// Mapping des groupes utilisateurs pour le personnel admin
$groupesAdmin = [
    'Responsable scolarité' => 3,
    'Secrétaire' => 1, 
    'Chargé de communication' => 2
];

// Traitement AJAX pour les opérations CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        switch ($action) {
            case 'create':
                $nom = trim($_POST['nom_pers']);
                $prenom = trim($_POST['prenoms_pers']);
                $email = trim($_POST['email_pers']);
                $telephone = trim($_POST['telephone']) ?: null;
                $poste = $_POST['poste'] ?? null;
                
                // Validation
                if (empty($nom) || empty($prenom) || empty($email) || empty($poste)) {
                    throw new Exception("Tous les champs obligatoires doivent être remplis");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide");
                }
                
                if (!array_key_exists($poste, $groupesAdmin)) {
                    throw new Exception("Poste invalide");
                }
                
                // Vérifier si l'email existe déjà
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM personnel_admin WHERE email_pers = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Un membre du personnel avec cet email existe déjà");
                }
                
                // Générer les IDs
                $stmtMaxUtil = $pdo->query("SELECT COALESCE(MAX(id_util), 0) + 1 FROM utilisateur");
                $idUtil = $stmtMaxUtil->fetchColumn();
                
                $stmtMaxPersonnel = $pdo->query("SELECT COALESCE(MAX(id_pers), 0) + 1 FROM personnel_admin");
                $idPersonnel = $stmtMaxPersonnel->fetchColumn();
                
                // 1. Créer l'utilisateur (pour la cohérence du système)
                $stmtUser = $pdo->prepare("INSERT INTO utilisateur (id_util, login_util, mdp_util, last_activity) VALUES (?, ?, ?, NOW())");
                $stmtUser->execute([$idUtil, null, null]);
                
                // 2. Créer le personnel admin
                $stmtPersonnel = $pdo->prepare("INSERT INTO personnel_admin (id_pers, fk_id_util, nom_pers, prenoms_pers, email_pers, telephone, poste) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtPersonnel->execute([$idPersonnel, $idUtil, $nom, $prenom, $email, $telephone, $poste]);
                
                // 3. Ajouter au groupe utilisateur correspondant
                $stmtMaxPoss = $pdo->query("SELECT COALESCE(MAX(id_poss), 0) + 1 FROM posseder");
                $idPoss = $stmtMaxPoss->fetchColumn();
                
                $stmtPoss = $pdo->prepare("INSERT INTO posseder (id_poss, fk_id_util, fk_id_GU, dte_poss) VALUES (?, ?, ?, CURDATE())");
                $stmtPoss->execute([$idPoss, $idUtil, $groupesAdmin[$poste]]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Personnel administratif créé avec succès',
                    'data' => [
                        'id_pers' => $idPersonnel,
                        'nom_pers' => $nom,
                        'prenoms_pers' => $prenom,
                        'email_pers' => $email,
                        'telephone' => $telephone,
                        'poste' => $poste
                    ]
                ]);
                break;
                
            case 'update':
                $idPersonnel = $_POST['id_pers'];
                $nom = trim($_POST['nom_pers']);
                $prenom = trim($_POST['prenoms_pers']);
                $email = trim($_POST['email_pers']);
                $telephone = trim($_POST['telephone']) ?: null;
                $poste = $_POST['poste'] ?? null;
                
                // Validation
                if (empty($nom) || empty($prenom) || empty($email) || empty($poste)) {
                    throw new Exception("Tous les champs obligatoires doivent être remplis");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide");
                }
                
                if (!array_key_exists($poste, $groupesAdmin)) {
                    throw new Exception("Poste invalide");
                }
                
                // Vérifier si l'email existe déjà pour un autre personnel
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM personnel_admin WHERE email_pers = ? AND id_pers != ?");
                $checkStmt->execute([$email, $idPersonnel]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Un autre membre du personnel avec cet email existe déjà");
                }
                
                // Récupérer l'ID utilisateur
                $stmtGetUser = $pdo->prepare("SELECT fk_id_util FROM personnel_admin WHERE id_pers = ?");
                $stmtGetUser->execute([$idPersonnel]);
                $idUtil = $stmtGetUser->fetchColumn();
                
                // Mettre à jour le personnel admin
                $stmtUpdate = $pdo->prepare("UPDATE personnel_admin SET nom_pers = ?, prenoms_pers = ?, email_pers = ?, telephone = ?, poste = ? WHERE id_pers = ?");
                $stmtUpdate->execute([$nom, $prenom, $email, $telephone, $poste, $idPersonnel]);
                
                // Mettre à jour le groupe utilisateur
                if ($idUtil) {
                    // Supprimer l'ancien groupe
                    $pdo->prepare("DELETE FROM posseder WHERE fk_id_util = ?")->execute([$idUtil]);
                    
                    // Ajouter le nouveau groupe
                    $stmtMaxPoss = $pdo->query("SELECT COALESCE(MAX(id_poss), 0) + 1 FROM posseder");
                    $idPoss = $stmtMaxPoss->fetchColumn();
                    
                    $stmtPoss = $pdo->prepare("INSERT INTO posseder (id_poss, fk_id_util, fk_id_GU, dte_poss) VALUES (?, ?, ?, CURDATE())");
                    $stmtPoss->execute([$idPoss, $idUtil, $groupesAdmin[$poste]]);
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Personnel administratif modifié avec succès']);
                break;
                
            case 'delete':
                $idsPersonnel = json_decode($_POST['ids_personnel'], true);
                
                foreach ($idsPersonnel as $idPersonnel) {
                    // Récupérer l'ID utilisateur associé
                    $stmtGetUser = $pdo->prepare("SELECT fk_id_util FROM personnel_admin WHERE id_pers = ?");
                    $stmtGetUser->execute([$idPersonnel]);
                    $idUtil = $stmtGetUser->fetchColumn();
                    
                    if ($idUtil) {
                        // Supprimer les relations
                        $pdo->prepare("DELETE FROM posseder WHERE fk_id_util = ?")->execute([$idUtil]);
                        
                        // Supprimer le personnel admin
                        $pdo->prepare("DELETE FROM personnel_admin WHERE id_pers = ?")->execute([$idPersonnel]);
                        
                        // Supprimer l'utilisateur
                        $pdo->prepare("DELETE FROM utilisateur WHERE id_util = ?")->execute([$idUtil]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Personnel administratif supprimé avec succès']);
                break;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Récupérer le personnel administratif avec leurs informations
$personnelAdmin = [];

try {
    // Récupérer le personnel admin avec leurs groupes
    $stmtPersonnel = $pdo->query("
        SELECT 
            pa.id_pers,
            pa.nom_pers,
            pa.prenoms_pers,
            pa.email_pers,
            pa.telephone,
            pa.poste,
            gu.lib_GU as groupe
        FROM personnel_admin pa
        LEFT JOIN utilisateur u ON pa.fk_id_util = u.id_util
        LEFT JOIN posseder p ON u.id_util = p.fk_id_util
        LEFT JOIN groupe_utilisateur gu ON p.fk_id_GU = gu.id_GU
        ORDER BY pa.nom_pers, pa.prenoms_pers
    ");
    $personnelAdmin = $stmtPersonnel->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Personnel Administratif</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* === VARIABLES CSS (Globales, devraient être dans un fichier séparé ou config.php) === */
        :root {
            --primary-50: #f8fafc; --primary-100: #f1f5f9; --primary-200: #e2e8f0; --primary-300: #cbd5e1; --primary-400: #94a3b8;
            --primary-500: #64748b; --primary-600: #475569; --primary-700: #334155; --primary-800: #1e293b; --primary-900: #0f172a;
            --accent-50: #eff6ff; --accent-100: #dbeafe; --accent-200: #bfdbfe; --accent-300: #93c5fd; --accent-400: #60a5fa;
            --accent-500: #3b82f6; --accent-600: #2563eb; --accent-700: #1d4ed8; --accent-800: #1e40af; --accent-900: #1e3a8a;
            --secondary-50: #f0fdf4; --secondary-100: #dcfce7; --secondary-500: #22c55e; --secondary-600: #16a34a;
            --success-500: #22c55e; --warning-500: #f59e0b; --error-500: #ef4444; --info-500: #3b82f6;
            --white: #ffffff; --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db;
            --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937;
            --gray-900: #111827;
            --sidebar-width: 280px; --sidebar-collapsed-width: 80px; --topbar-height: 70px;
            --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
            --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-lg: 1.125rem; --text-xl: 1.25rem;
            --text-2xl: 1.5rem; --text-3xl: 1.875rem;
            --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-5: 1.25rem;
            --space-6: 1.5rem; --space-8: 2rem; --space-10: 2.5rem; --space-12: 3rem; --space-16: 4rem;
            --radius-sm: 0.25rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem;
            --radius-2xl: 1.5rem; --radius-3xl: 2rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --transition-fast: 150ms ease-in-out; --transition-normal: 250ms ease-in-out; --transition-slow: 350ms ease-in-out;
        }

        /* === RESET (essentiels, conservés) === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-primary);
            background-color: var(--gray-50);
            color: var(--gray-800);
            overflow-x: hidden;
        }

        /* === LAYOUT PRINCIPAL (essentiels, conservés) === */
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-collapsed-width); }

        /* === TOPBAR (styles du topbar retirés car ils devraient être dans topbar.php ou un CSS global) === */
        /* === SIDEBAR (styles de la sidebar retirés car ils devraient être dans sidebar.php ou un CSS global) === */
        /* Les styles pour .sidebar, .sidebar.collapsed, .sidebar-header, .sidebar-logo, .sidebar-logo img,
           .sidebar-title, .sidebar-nav, .nav-section, .nav-section-title, .nav-items-wrapper,
           .nav-item, .nav-link, .nav-icon, .nav-text, .nav-submenu, .mobile-menu-overlay
           doivent être définis dans sidebar.php ou un fichier CSS global lié à toutes les pages.
           Je les ai retirés de ce fichier pour éviter les duplications et les conflits. */

        /* === PAGE SPECIFIC STYLES (Styles spécifiques à gestion_personnel_admin.php) === */
        .page-content { padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-2); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); }

        .form-card {
            background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6);
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: var(--space-8);
        }
        .form-card-title {
            font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); margin-bottom: var(--space-6);
            border-bottom: 1px solid var(--gray-200); padding-bottom: var(--space-4);
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-6); }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: var(--text-sm); font-weight: 500; color: var(--gray-700); margin-bottom: var(--space-2); }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="tel"], .form-group select {
            padding: var(--space-3); border: 1px solid var(--gray-300); border-radius: var(--radius-md);
            font-size: var(--text-base); color: var(--gray-800); transition: all var(--transition-fast);
        }
        .form-group input[type="text"]:focus, .form-group input[type="email"]:focus, .form-group input[type="tel"]:focus, .form-group select:focus {
            outline: none; border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .form-actions { display: flex; gap: var(--space-4); justify-content: flex-end; }
        .btn {
            padding: var(--space-3) var(--space-5); border-radius: var(--radius-md); font-size: var(--text-base);
            font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none;
            display: inline-flex; align-items: center; gap: var(--space-2);
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background-color: var(--accent-600); color: white; }
        .btn-primary:hover:not(:disabled) { background-color: var(--accent-700); }
        .btn-secondary { background-color: var(--gray-200); color: var(--gray-700); }
        .btn-secondary:hover:not(:disabled) { background-color: var(--gray-300); }

        .table-card {
            background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6);
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
        }
        .table-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6); }
        .table-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); }
        .table-actions { display: flex; gap: var(--space-3); }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); color: var(--gray-800); }
        .data-table th, .data-table td { padding: var(--space-4); border-bottom: 1px solid var(--gray-200); text-align: left; }
        .data-table th {
            background-color: var(--gray-50); font-weight: 600; color: var(--gray-700);
            text-transform: uppercase; font-size: var(--text-xs); letter-spacing: 0.05em;
        }
        .data-table tbody tr:hover { background-color: var(--gray-100); }
        .action-buttons { display: flex; gap: var(--space-2); }
        .action-button {
            padding: var(--space-2); border-radius: var(--radius-md); font-size: var(--text-sm);
            cursor: pointer; transition: all var(--transition-fast); border: none; color: white;
            display: inline-flex; align-items: center; justify-content: center; min-width: 30px; min-height: 30px;
        }
        .action-button.view { background-color: var(--info-500); } .action-button.view:hover { background-color: #316be6; }
        .action-button.edit { background-color: var(--warning-500); } .action-button.edit:hover { background-color: #e68a00; }
        .action-button.delete { background-color: var(--error-500); } .action-button.delete:hover { background-color: #cc3131; }

        /* Badges pour les postes */
        .badge {
            padding: var(--space-1) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-xs);
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .badge.responsable { background-color: #dbeafe; color: #1e40af; }
        .badge.secretaire { background-color: #fef3c7; color: #92400e; }
        .badge.communication { background-color: #d1fae5; color: #065f46; }

        /* Checkbox styling */
        .checkbox-container { display: block; position: relative; padding-left: 25px; cursor: pointer; user-select: none; }
        .checkbox-container input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
        .checkmark {
            position: absolute; top: 50%; left: 0; transform: translateY(-50%); height: 18px; width: 18px;
            background-color: var(--gray-200); border-radius: var(--radius-sm); transition: all var(--transition-fast);
            border: 1px solid var(--gray-300);
        }
        .checkbox-container input:checked ~ .checkmark { background-color: var(--accent-600); border-color: var(--accent-600); }
        .checkmark:after { content: ""; position: absolute; display: none; }
        .checkbox-container input:checked ~ .checkmark:after { display: block; }
        .checkbox-container .checkmark:after {
            left: 6px; top: 2px; width: 5px; height: 10px; border: solid white; border-width: 0 3px 3px 0;
            -webkit-transform: rotate(45deg); -ms-transform: rotate(45deg); transform: rotate(45deg);
        }

        /* Messages d'alerte (à remplacer par une modal globale ou un toast si souhaité) */
        .alert {
            padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: none;
        }
        .alert.success { background-color: var(--secondary-50); color: var(--secondary-600); border: 1px solid var(--secondary-100); }
        .alert.error { background-color: #fef2f2; color: var(--error-500); border: 1px solid #fecaca; }
        .alert.warning { background-color: #fffbeb; color: #92400e; border: 1px solid #fed7aa; }
        .alert.info { background-color: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-200); }

        /* Loading spinner */
        .loading { opacity: 0.6; pointer-events: none; }

        /* Modal pour affichage des informations */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: var(--white); margin: 5% auto; padding: var(--space-6); border-radius: var(--radius-xl);
            width: 90%; max-width: 500px; box-shadow: var(--shadow-xl); position: relative;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);
            padding-bottom: var(--space-4); border-bottom: 1px solid var(--gray-200);
        }
        .modal-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); }
        .close {
            color: var(--gray-400); font-size: 28px; font-weight: bold; cursor: pointer;
            transition: color var(--transition-fast); position: absolute; top: var(--space-4); right: var(--space-4);
        }
        .close:hover { color: var(--gray-600); }

        /* Responsive styles (retirés et gérés par les styles globaux ou un fichier CSS commun) */
        /* Les media queries pour le layout général sont retirées d'ici. */
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; ?>

            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title-main">Gestion du Personnel Administratif</h1>
                    <p class="page-subtitle">Gérez les informations du personnel administratif de l'établissement.</p>
                </div>

                <div id="alertMessage" class="alert"></div>

                <div class="form-card">
                    <h3 class="form-card-title">Ajouter un nouveau membre du personnel</h3>
                    <form id="personnelForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nom_pers">Nom <span style="color: var(--error-500);">*</span></label>
                                <input type="text" id="nom_pers" name="nom_pers" placeholder="Ex: Martin" required>
                            </div>
                            <div class="form-group">
                                <label for="prenoms_pers">Prénoms <span style="color: var(--error-500);">*</span></label>
                                <input type="text" id="prenoms_pers" name="prenoms_pers" placeholder="Ex: Sophie Marie" required>
                            </div>
                            <div class="form-group">
                                <label for="email_pers">Email <span style="color: var(--error-500);">*</span></label>
                                <input type="email" id="email_pers" name="email_pers" placeholder="Ex: sophie.martin@univ.com" required>
                            </div>
                            <div class="form-group">
                                <label for="telephone">Téléphone</label>
                                <input type="tel" id="telephone" name="telephone" placeholder="Ex: 01 23 45 67 89">
                            </div>
                            <div class="form-group">
                                <label for="poste">Poste <span style="color: var(--error-500);">*</span></label>
                                <select id="poste" name="poste" required>
                                    <option value="">Sélectionner un poste</option>
                                    <?php foreach ($groupesAdmin as $groupe => $id): ?>
                                        <option value="<?php echo htmlspecialchars($groupe); ?>">
                                            <?php echo htmlspecialchars($groupe); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-user-plus"></i> <span id="submitText">Ajouter Personnel</span>
                            </button>
                            <button type="reset" class="btn btn-secondary" id="cancelBtn">
                                <i class="fas fa-redo"></i> Annuler
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-header">
                        <h3 class="table-title">Liste du Personnel Administratif</h3>
                        <div class="table-actions">
                            <button class="btn btn-secondary" id="modifierPersonnelBtn" disabled>
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-secondary" id="supprimerPersonnelBtn" disabled>
                                <i class="fas fa-trash-alt"></i> Supprimer
                            </button>
                            <button class="btn btn-secondary" id="exporterPersonnelBtn">
                                <i class="fas fa-file-excel"></i> Exporter Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="personnelTable">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Nom</th>
                                    <th>Prénoms</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Poste</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($personnelAdmin)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--gray-500); padding: var(--space-8);">
                                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                        Aucun personnel administratif trouvé. Ajoutez votre premier membre en utilisant le formulaire ci-dessus.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($personnelAdmin as $personnel): ?>
                                    <tr data-id="<?php echo htmlspecialchars($personnel['id_pers']); ?>">
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" value="<?php echo htmlspecialchars($personnel['id_pers']); ?>">
                                                <span class="checkmark"></span>
                                            </label>
                                        </td>
                                        <td><?php echo htmlspecialchars($personnel['nom_pers']); ?></td>
                                        <td><?php echo htmlspecialchars($personnel['prenoms_pers']); ?></td>
                                        <td><?php echo htmlspecialchars($personnel['email_pers']); ?></td>
                                        <td><?php echo htmlspecialchars($personnel['telephone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = '';
                                            switch($personnel['poste']) {
                                                case 'Responsable scolarité': $badgeClass = 'responsable'; break;
                                                case 'Secrétaire': $badgeClass = 'secretaire'; break;
                                                case 'Chargé de communication': $badgeClass = 'communication'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($personnel['poste']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-button view" title="Voir Dossier" onclick="voirDossierPersonnel('<?php echo htmlspecialchars($personnel['id_pers']); ?>')">
                                                    <i class="fas fa-folder-open"></i>
                                                </button>
                                                <button class="action-button edit" title="Modifier" onclick="modifierPersonnel('<?php echo htmlspecialchars($personnel['id_pers']); ?>')">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button class="action-button delete" title="Supprimer" onclick="supprimerPersonnel('<?php echo htmlspecialchars($personnel['id_pers']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
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

    <div id="personnelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Dossier Personnel Administratif</h2>
                <span class="close" onclick="fermerModal()">&times;</span>
            </div>
            <div id="modalBody">
                </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        // Variables globales
        let selectedPersonnel = new Set();
        let editingPersonnel = null;

        // Éléments DOM
        const personnelForm = document.getElementById('personnelForm');
        const nomPersonnelInput = document.getElementById('nom_pers');
        const prenomsPersonnelInput = document.getElementById('prenoms_pers');
        const emailPersonnelInput = document.getElementById('email_pers');
        const telephoneInput = document.getElementById('telephone');
        const posteInput = document.getElementById('poste');
        const personnelTableBody = document.querySelector('#personnelTable tbody');
        const modifierPersonnelBtn = document.getElementById('modifierPersonnelBtn');
        const supprimerPersonnelBtn = document.getElementById('supprimerPersonnelBtn');
        const exporterPersonnelBtn = document.getElementById('exporterPersonnelBtn');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const cancelBtn = document.getElementById('cancelBtn');
        const alertMessage = document.getElementById('alertMessage'); // This is a simple alert, not a modal
        const personnelModal = document.getElementById('personnelModal');

        // Gestion du toggle sidebar (Ces fonctions sont déjà dans sidebar.php et topbar.php)
        // Les lignes suivantes sont commentées car elles sont gérées globalement par l'inclusion de sidebar.php et topbar.php
        /*
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            });
        }
        */

        // Fonction pour afficher les messages (simple alerte ici, non un modal)
        function showAlert(message, type = 'success') {
            alertMessage.textContent = message;
            alertMessage.className = `alert ${type}`;
            alertMessage.style.display = 'block';
            setTimeout(() => {
                alertMessage.style.display = 'none';
            }, 5000);
        }

        // Fonction pour faire une requête AJAX
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

        // Fonction pour mettre à jour l'état des boutons
        function updateActionButtons() {
            if (selectedPersonnel.size === 1) {
                modifierPersonnelBtn.disabled = false;
                supprimerPersonnelBtn.disabled = false;
            } else if (selectedPersonnel.size > 1) {
                modifierPersonnelBtn.disabled = true;
                supprimerPersonnelBtn.disabled = false;
            } else {
                modifierPersonnelBtn.disabled = true;
                supprimerPersonnelBtn.disabled = true;
            }
        }

        // Fonction pour obtenir la classe CSS du badge
        function getBadgeClass(poste) {
            switch(poste) {
                case 'Responsable scolarité': return 'responsable';
                case 'Secrétaire': return 'secretaire';
                case 'Chargé de communication': return 'communication';
                default: return '';
            }
        }

        // Fonction pour ajouter une ligne dans le tableau
        function addRowToTable(personnel) {
            const emptyRow = personnelTableBody.querySelector('td[colspan="7"]');
            if (emptyRow) {
                emptyRow.closest('tr').remove();
            }

            const badgeClass = getBadgeClass(personnel.poste);
            const newRow = personnelTableBody.insertRow();
            newRow.setAttribute('data-id', personnel.id_pers);
            newRow.innerHTML = `
                <td>
                    <label class="checkbox-container">
                        <input type="checkbox" value="${personnel.id_pers}">
                        <span class="checkmark"></span>
                    </label>
                </td>
                <td>${personnel.nom_pers}</td>
                <td>${personnel.prenoms_pers}</td>
                <td>${personnel.email_pers}</td>
                <td>${personnel.telephone || 'N/A'}</td>
                <td><span class="badge ${badgeClass}">${personnel.poste}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-button view" title="Voir Dossier" onclick="voirDossierPersonnel('${personnel.id_pers}')">
                            <i class="fas fa-folder-open"></i>
                        </button>
                        <button class="action-button edit" title="Modifier" onclick="modifierPersonnel('${personnel.id_pers}')">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="action-button delete" title="Supprimer" onclick="supprimerPersonnel('${personnel.id_pers}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            attachEventListenersToRow(newRow);
        }

        // Fonction pour attacher les événements aux lignes
        function attachEventListenersToRow(row) {
            const checkbox = row.querySelector('input[type="checkbox"]');
            
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedPersonnel.add(this.value);
                } else {
                    selectedPersonnel.delete(this.value);
                }
                updateActionButtons();
            });
        }

        // Soumission du formulaire
        personnelForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                action: editingPersonnel ? 'update' : 'create',
                nom_pers: formData.get('nom_pers'),
                prenoms_pers: formData.get('prenoms_pers'),
                email_pers: formData.get('email_pers'),
                telephone: formData.get('telephone'),
                poste: formData.get('poste')
            };

            if (editingPersonnel) {
                data.id_pers = editingPersonnel;
            }

            try {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;

                const result = await makeAjaxRequest(data);

                if (result.success) {
                    if (editingPersonnel) {
                        const row = document.querySelector(`tr[data-id="${editingPersonnel}"]`);
                        if (row) {
                            row.cells[1].textContent = data.nom_pers;
                            row.cells[2].textContent = data.prenoms_pers;
                            row.cells[3].textContent = data.email_pers;
                            row.cells[4].textContent = data.telephone || 'N/A';
                            
                            const badgeClass = getBadgeClass(data.poste);
                            row.cells[5].innerHTML = `<span class="badge ${badgeClass}">${data.poste}</span>`;
                        }
                        showAlert('Personnel administratif modifié avec succès');
                        resetForm();
                    } else {
                        addRowToTable(result.data);
                        showAlert(`Personnel "${data.prenoms_pers} ${data.nom_pers}" créé avec succès`);
                    }
                    this.reset();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Erreur lors de l\'enregistrement', 'error');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });

        // Fonction pour réinitialiser le formulaire
        function resetForm() {
            editingPersonnel = null;
            submitText.textContent = 'Ajouter Personnel';
            submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Ajouter Personnel';
            personnelForm.reset();
        }

        // Bouton Annuler
        cancelBtn.addEventListener('click', function() {
            resetForm();
        });

        // Fonction pour voir le dossier d'un personnel
        function voirDossierPersonnel(idPersonnel) {
            const row = document.querySelector(`tr[data-id="${idPersonnel}"]`);
            if (row) {
                const personnelData = {
                    id: idPersonnel,
                    nom: row.cells[1].textContent,
                    prenoms: row.cells[2].textContent,
                    email: row.cells[3].textContent,
                    telephone: row.cells[4].textContent,
                    poste: row.cells[5].querySelector('.badge').textContent
                };

                const modalBody = document.getElementById('modalBody');
                modalBody.innerHTML = `
                    <div style="display: grid; gap: var(--space-4);">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                            <strong>ID Personnel:</strong>
                            <span>${personnelData.id}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                            <strong>Nom complet:</strong>
                            <span>${personnelData.prenoms} ${personnelData.nom}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                            <strong>Email:</strong>
                            <span>${personnelData.email}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                            <strong>Téléphone:</strong>
                            <span>${personnelData.telephone}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3); background: var(--accent-50); border: 1px solid var(--accent-200); border-radius: var(--radius-md);">
                            <strong>Poste:</strong>
                            <span style="color: var(--accent-700);">${personnelData.poste}</span>
                        </div>
                    </div>
                `;
                
                personnelModal.style.display = 'flex'; // Use flex for centering
            }
        }

        // Fonction pour modifier un personnel
        function modifierPersonnel(idPersonnel) {
            const row = document.querySelector(`tr[data-id="${idPersonnel}"]`);
            if (row) {
                editingPersonnel = idPersonnel;
                nomPersonnelInput.value = row.cells[1].textContent;
                prenomsPersonnelInput.value = row.cells[2].textContent;
                emailPersonnelInput.value = row.cells[3].textContent;
                
                const telephone = row.cells[4].textContent;
                telephoneInput.value = telephone !== 'N/A' ? telephone : '';
                
                const poste = row.cells[5].querySelector('.badge').textContent;
                posteInput.value = poste;
                
                submitText.textContent = 'Mettre à jour';
                submitBtn.innerHTML = '<i class="fas fa-edit"></i> Mettre à jour';
                
                document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Fonction pour supprimer un personnel
        async function supprimerPersonnel(idPersonnel) {
            const row = document.querySelector(`tr[data-id="${idPersonnel}"]`);
            if (row) {
                const nomComplet = `${row.cells[2].textContent} ${row.cells[1].textContent}`;
                
                if (confirm(`Êtes-vous sûr de vouloir supprimer ${nomComplet} ?\n\nCette action ne peut être annulée.`)) {
                    try {
                        const result = await makeAjaxRequest({
                            action: 'delete',
                            ids_personnel: JSON.stringify([idPersonnel])
                        });

                        if (result.success) {
                            row.remove();
                            selectedPersonnel.delete(idPersonnel);
                            updateActionButtons();
                            showAlert('Personnel administratif supprimé avec succès');
                            
                            if (personnelTableBody.children.length === 0) {
                                personnelTableBody.innerHTML = `
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--gray-500); padding: var(--space-8);">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                            Aucun personnel administratif trouvé. Ajoutez votre premier membre en utilisant le formulaire ci-dessus.
                                        </td>
                                    </tr>
                                `;
                            }
                        } else {
                            showAlert(result.message, 'error');
                        }
                    } catch (error) {
                        showAlert('Erreur lors de la suppression', 'error');
                    }
                }
            }
        }

        // Bouton Modifier global
        modifierPersonnelBtn.addEventListener('click', function() {
            if (selectedPersonnel.size === 1) {
                const idPersonnel = Array.from(selectedPersonnel)[0];
                modifierPersonnel(idPersonnel);
            }
        });

        // Bouton Supprimer global
        supprimerPersonnelBtn.addEventListener('click', async function() {
            if (selectedPersonnel.size > 0) {
                const idsArray = Array.from(selectedPersonnel);
                
                if (confirm(`Êtes-vous sûr de vouloir supprimer ${idsArray.length} membre(s) du personnel sélectionné(s) ?\n\nCette action ne peut être annulée.`)) {
                    try {
                        const result = await makeAjaxRequest({
                            action: 'delete',
                            ids_personnel: JSON.stringify(idsArray)
                        });

                        if (result.success) {
                            idsArray.forEach(id => {
                                const row = document.querySelector(`tr[data-id="${id}"]`);
                                if (row) row.remove();
                            });
                            selectedPersonnel.clear();
                            updateActionButtons();
                            showAlert('Personnel administratif supprimé avec succès');
                            
                            if (personnelTableBody.children.length === 0) {
                                personnelTableBody.innerHTML = `
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--gray-500); padding: var(--space-8);">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                            Aucun personnel administratif trouvé. Ajoutez votre premier membre en utilisant le formulaire ci-dessus.
                                        </td>
                                    </tr>
                                `;
                            }
                        } else {
                            showAlert(result.message, 'error');
                        }
                    } catch (error) {
                        showAlert('Erreur lors de la suppression', 'error');
                    }
                }
            }
        });

        // Bouton Exporter Excel
        exporterPersonnelBtn.addEventListener('click', function() {
            const rows = document.querySelectorAll('#personnelTable tbody tr');
            if (rows.length === 1 && rows[0].querySelector('td[colspan="7"]')) {
                showAlert('Aucun personnel à exporter', 'warning');
                return;
            }

            const data = [['Nom', 'Prénoms', 'Email', 'Téléphone', 'Poste']];
            
            document.querySelectorAll('#personnelTable tbody tr').forEach(row => {
                if (!row.querySelector('td[colspan="7"]')) {
                    data.push([
                        row.cells[1].textContent,
                        row.cells[2].textContent,
                        row.cells[3].textContent,
                        row.cells[4].textContent,
                        row.cells[5].querySelector('.badge').textContent
                    ]);
                }
            });

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Personnel Administratif");
            
            XLSX.writeFile(wb, `personnel_admin_${new Date().toISOString().split('T')[0]}.xlsx`);
            
            showAlert('Exportation Excel terminée');
        });

        // Fonctions pour fermer les modals
        function fermerModal() {
            personnelModal.style.display = 'none';
        }

        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            if (event.target === personnelModal) {
                fermerModal();
            }
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#personnelTable tbody tr').forEach(row => {
                if (!row.querySelector('td[colspan="7"]')) {
                    attachEventListenersToRow(row);
                }
            });
            
            updateActionButtons();
        });

        // Responsive: Gestion mobile (Ces fonctions sont déjà dans sidebar.php et topbar.php)
        /*
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
        handleResize(); // Call on initial load
        */
    </script>
</body>
</html>