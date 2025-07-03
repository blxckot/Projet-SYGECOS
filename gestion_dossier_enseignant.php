<?php
// gestion_dossier_enseignant.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('loginForm.php');
}

// Fonction pour générer un login unique pour un enseignant
function genererLoginEnseignant($nom, $prenom, $pdo) {
    // Nettoyer et préparer le login de base
    $nom = strtolower(trim($nom));
    $prenom = strtolower(trim($prenom));
    $loginBase = substr($prenom, 0, 1) . $nom;
    
    // Enlever les accents et caractères spéciaux
    $loginBase = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $loginBase);
    $loginBase = preg_replace('/[^a-z0-9]/', '', $loginBase);
    
    $login = $loginBase;
    $counter = 1;
    
    // Vérifier l'unicité
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE login_util = ?");
        $stmt->execute([$login]);
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        $login = $loginBase . $counter;
        $counter++;
    }
    
    return $login;
}

// Fonction pour générer un mot de passe temporaire
function genererMotDePasseTemporaire() {
    return 'Enseignant' . rand(1000, 9999);
}

// Traitement AJAX pour les opérations CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        switch ($action) {
            case 'create':
                $nom = trim($_POST['nom_ens']);
                $prenom = trim($_POST['prenom_ens']);
                $email = trim($_POST['email']);
                $grade = $_POST['grade'] ?? null;
                $fonction = $_POST['fonction'] ?? null;
                $groupeUtilisateur = $_POST['groupe_utilisateur'] ?? null;
                
                // Validation
                if (empty($nom) || empty($prenom) || empty($email) || empty($groupeUtilisateur)) {
                    throw new Exception("Tous les champs obligatoires (Nom, Prénom, Email, Groupe Utilisateur) doivent être remplis.");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide.");
                }
                
                // Vérifier si l'email existe déjà
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM enseignant WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Un enseignant avec cet email existe déjà.");
                }

                // Récupérer l'ID du groupe utilisateur sélectionné
                $stmtGetGroupId = $pdo->prepare("SELECT id_GU FROM groupe_utilisateur WHERE lib_GU = ?");
                $stmtGetGroupId->execute([$groupeUtilisateur]);
                $selectedGroupId = $stmtGetGroupId->fetchColumn();

                if (!$selectedGroupId) {
                    throw new Exception("Le groupe utilisateur sélectionné n'existe pas.");
                }
                
                // Générer login et mot de passe
                $login = genererLoginEnseignant($nom, $prenom, $pdo);
                $motDePasse = genererMotDePasseTemporaire();
                $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
                
                // Générer les IDs
                $stmtMaxUtil = $pdo->query("SELECT COALESCE(MAX(id_util), 0) + 1 FROM utilisateur");
                $idUtil = $stmtMaxUtil->fetchColumn();
                
                $stmtMaxEns = $pdo->query("SELECT COALESCE(MAX(id_ens), 0) + 1 FROM enseignant");
                $idEns = $stmtMaxEns->fetchColumn();
                
                // 1. Créer l'utilisateur
                $stmtUser = $pdo->prepare("INSERT INTO utilisateur (id_util, login_util, mdp_util, temp_password, last_activity) VALUES (?, ?, ?, ?, NOW())");
                $stmtUser->execute([$idUtil, $login, $motDePasseHash, $motDePasse]);
                
                // 2. Créer l'enseignant
                $stmtEns = $pdo->prepare("INSERT INTO enseignant (id_ens, fk_id_util, nom_ens, prenom_ens, email) VALUES (?, ?, ?, ?, ?)");
                $stmtEns->execute([$idEns, $idUtil, $nom, $prenom, $email]);
                
                // 3. Ajouter au groupe utilisateur sélectionné
                $stmtMaxPoss = $pdo->query("SELECT COALESCE(MAX(id_poss), 0) + 1 FROM posseder");
                $idPoss = $stmtMaxPoss->fetchColumn();
                
                $stmtPoss = $pdo->prepare("INSERT INTO posseder (id_poss, fk_id_util, fk_id_GU, dte_poss) VALUES (?, ?, ?, CURDATE())");
                $stmtPoss->execute([$idPoss, $idUtil, $selectedGroupId]);
                
                // 4. Ajouter grade si fourni
                if (!empty($grade)) {
                    $stmtGradeId = $pdo->prepare("SELECT id_grd FROM grade WHERE nom_grd = ?");
                    $stmtGradeId->execute([$grade]);
                    $gradeId = $stmtGradeId->fetchColumn();

                    if ($gradeId) {
                        $stmtMaxAvoir = $pdo->query("SELECT COALESCE(MAX(id_avoir), 0) + 1 FROM avoir");
                        $idAvoir = $stmtMaxAvoir->fetchColumn();
                        
                        $stmtAvoir = $pdo->prepare("INSERT INTO avoir (id_avoir, fk_id_grd, fk_id_ens, dte_grd) VALUES (?, ?, ?, CURDATE())");
                        $stmtAvoir->execute([$idAvoir, $gradeId, $idEns]);
                    } else {
                        error_log("Grade '$grade' non trouvé lors de la création de l'enseignant.");
                    }
                }
                
                // 5. Ajouter fonction si fournie
                if (!empty($fonction)) {
                    $stmtFonctionId = $pdo->prepare("SELECT id_fonction FROM fonction WHERE nom_fonction = ?");
                    $stmtFonctionId->execute([$fonction]);
                    $fonctionId = $stmtFonctionId->fetchColumn();

                    if ($fonctionId) {
                        $stmtMaxOccuper = $pdo->query("SELECT COALESCE(MAX(id_occuper), 0) + 1 FROM occuper");
                        $idOccuper = $stmtMaxOccuper->fetchColumn();
                        
                        $stmtOccuper = $pdo->prepare("INSERT INTO occuper (id_occuper, fk_id_fonc, fk_id_ens, dte_occup) VALUES (?, ?, ?, CURDATE())");
                        $stmtOccuper->execute([$idOccuper, $fonctionId, $idEns]);
                    } else {
                        error_log("Fonction '$fonction' non trouvée lors de la création de l'enseignant.");
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Enseignant créé avec succès.',
                    'data' => [
                        'id_ens' => $idEns,
                        'nom_ens' => $nom,
                        'prenom_ens' => $prenom,
                        'email' => $email,
                        'login_util' => $login,
                        'motdepasse_temp' => $motDePasse,
                        'grade' => $grade,
                        'fonction' => $fonction,
                        'groupe_utilisateur' => $groupeUtilisateur
                    ]
                ]);
                break;
                
            case 'update':
                $idEns = $_POST['id_ens'];
                $nom = trim($_POST['nom_ens']);
                $prenom = trim($_POST['prenom_ens']);
                $email = trim($_POST['email']);
                $grade = $_POST['grade'] ?? null;
                $fonction = $_POST['fonction'] ?? null;
                $groupeUtilisateur = $_POST['groupe_utilisateur'] ?? null;
                
                // Validation
                if (empty($nom) || empty($prenom) || empty($email) || empty($groupeUtilisateur)) {
                    throw new Exception("Les champs Nom, Prénom, Email et Groupe Utilisateur sont obligatoires.");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide.");
                }
                
                // Vérifier si l'email existe déjà pour un autre enseignant
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM enseignant WHERE email = ? AND id_ens != ?");
                $checkStmt->execute([$email, $idEns]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Un autre enseignant avec cet email existe déjà.");
                }
                
                // Mettre à jour l'enseignant
                $stmtUpdate = $pdo->prepare("UPDATE enseignant SET nom_ens = ?, prenom_ens = ?, email = ? WHERE id_ens = ?");
                $stmtUpdate->execute([$nom, $prenom, $email, $idEns]);
                
                // Récupérer l'ID de l'utilisateur associé pour mettre à jour le login et le groupe
                $stmtGetUserUtil = $pdo->prepare("SELECT fk_id_util FROM enseignant WHERE id_ens = ?");
                $stmtGetUserUtil->execute([$idEns]);
                $idUtilForUpdate = $stmtGetUserUtil->fetchColumn();

                if ($idUtilForUpdate) {
                    // Régénérer et mettre à jour le login si nécessaire
                    $currentLogin = $pdo->prepare("SELECT login_util FROM utilisateur WHERE id_util = ?");
                    $currentLogin->execute([$idUtilForUpdate]);
                    $oldLogin = $currentLogin->fetchColumn();
                    
                    $newPotentialLogin = genererLoginEnseignant($nom, $prenom, $pdo);
                    if ($oldLogin != $newPotentialLogin) { 
                        $stmtUpdateLogin = $pdo->prepare("UPDATE utilisateur SET login_util = ? WHERE id_util = ?");
                        $stmtUpdateLogin->execute([$newPotentialLogin, $idUtilForUpdate]);
                    }

                    // Mettre à jour le groupe utilisateur
                    $stmtGetSelectedGroupId = $pdo->prepare("SELECT id_GU FROM groupe_utilisateur WHERE lib_GU = ?");
                    $stmtGetSelectedGroupId->execute([$groupeUtilisateur]);
                    $newGroupId = $stmtGetSelectedGroupId->fetchColumn();

                    if ($newGroupId) {
                        // Supprimer l'ancienne affectation de groupe pour cet utilisateur
                        $pdo->prepare("DELETE FROM posseder WHERE fk_id_util = ?")->execute([$idUtilForUpdate]);
                        // Insérer la nouvelle affectation de groupe
                        $stmtMaxPoss = $pdo->query("SELECT COALESCE(MAX(id_poss), 0) + 1 FROM posseder");
                        $idPoss = $stmtMaxPoss->fetchColumn();
                        $stmtPoss = $pdo->prepare("INSERT INTO posseder (id_poss, fk_id_util, fk_id_GU, dte_poss) VALUES (?, ?, ?, CURDATE())");
                        $stmtPoss->execute([$idPoss, $idUtilForUpdate, $newGroupId]);
                    } else {
                        error_log("Groupe utilisateur '$groupeUtilisateur' non trouvé lors de la mise à jour de l'enseignant.");
                    }
                }
                // Gérer le grade
                // Supprimer l'ancien grade
                $pdo->prepare("DELETE FROM avoir WHERE fk_id_ens = ?")->execute([$idEns]);
                if (!empty($grade)) {
                    $stmtGradeId = $pdo->prepare("SELECT id_grd FROM grade WHERE nom_grd = ?");
                    $stmtGradeId->execute([$grade]);
                    $gradeId = $stmtGradeId->fetchColumn();
                    if ($gradeId) {
                        $stmtMaxAvoir = $pdo->query("SELECT COALESCE(MAX(id_avoir), 0) + 1 FROM avoir");
                        $idAvoir = $stmtMaxAvoir->fetchColumn();
                        $stmtAvoir = $pdo->prepare("INSERT INTO avoir (id_avoir, fk_id_grd, fk_id_ens, dte_grd) VALUES (?, ?, ?, CURDATE())");
                        $stmtAvoir->execute([$idAvoir, $gradeId, $idEns]);
                    }
                }
                
                // Gérer la fonction
                // Supprimer l'ancienne fonction
                $pdo->prepare("DELETE FROM occuper WHERE fk_id_ens = ?")->execute([$idEns]);
                if (!empty($fonction)) {
                    $stmtFonctionId = $pdo->prepare("SELECT id_fonction FROM fonction WHERE nom_fonction = ?");
                    $stmtFonctionId->execute([$fonction]);
                    $fonctionId = $stmtFonctionId->fetchColumn();
                    if ($fonctionId) {
                        $stmtMaxOccuper = $pdo->query("SELECT COALESCE(MAX(id_occuper), 0) + 1 FROM occuper");
                        $idOccuper = $stmtMaxOccuper->fetchColumn();
                        $stmtOccuper = $pdo->prepare("INSERT INTO occuper (id_occuper, fk_id_fonc, fk_id_ens, dte_occup) VALUES (?, ?, ?, CURDATE())");
                        $stmtOccuper->execute([$idOccuper, $fonctionId, $idEns]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Enseignant modifié avec succès.']);
                break;
                
            case 'delete':
                $idsEnseignants = json_decode($_POST['ids_enseignants'], true);
                
                foreach ($idsEnseignants as $idEns) {
                    // Récupérer l'ID utilisateur associé
                    $stmtGetUser = $pdo->prepare("SELECT fk_id_util FROM enseignant WHERE id_ens = ?");
                    $stmtGetUser->execute([$idEns]);
                    $idUtil = $stmtGetUser->fetchColumn();
                    
                    if ($idUtil) {
                        // Supprimer les relations (avoir, occuper, posseder)
                        $pdo->prepare("DELETE FROM avoir WHERE fk_id_ens = ?")->execute([$idEns]);
                        $pdo->prepare("DELETE FROM occuper WHERE fk_id_ens = ?")->execute([$idEns]);
                        $pdo->prepare("DELETE FROM posseder WHERE fk_id_util = ?")->execute([$idUtil]);
                        
                        // Supprimer l'enseignant
                        $pdo->prepare("DELETE FROM enseignant WHERE id_ens = ?")->execute([$idEns]);
                        
                        // Supprimer l'utilisateur
                        $pdo->prepare("DELETE FROM utilisateur WHERE id_util = ?")->execute([$idUtil]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Enseignant(s) supprimé(s) avec succès.']);
                break;
                
            case 'get_grades':
                $stmt = $pdo->query("SELECT id_grd, nom_grd FROM grade ORDER BY nom_grd");
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $grades]);
                break;
                
            case 'get_fonctions':
                $stmt = $pdo->query("SELECT id_fonction, nom_fonction FROM fonction ORDER BY nom_fonction");
                $fonctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $fonctions]);
                break;

            case 'get_user_groups':
                $stmt = $pdo->prepare("SELECT id_GU, lib_GU FROM groupe_utilisateur WHERE lib_GU IN ('Commission de validation', 'Responsable de filière') ORDER BY lib_GU");
                $stmt->execute();
                $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $groups]);
                break;
                
            case 'reset_password':
                $idEns = $_POST['id_ens'];
                
                // Récupérer l'ID utilisateur
                $stmtGetUser = $pdo->prepare("SELECT fk_id_util FROM enseignant WHERE id_ens = ?");
                $stmtGetUser->execute([$idEns]);
                $idUtil = $stmtGetUser->fetchColumn();
                
                if ($idUtil) {
                    $nouveauMdp = genererMotDePasseTemporaire();
                    $mdpHash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
                    
                    // Mettre à jour le mot de passe hashé et le mot de passe temporaire en clair
                    $stmtUpdate = $pdo->prepare("UPDATE utilisateur SET mdp_util = ?, temp_password = ? WHERE id_util = ?");
                    $stmtUpdate->execute([$mdpHash, $nouveauMdp, $idUtil]);
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Mot de passe réinitialisé avec succès.',
                        'nouveau_mdp' => $nouveauMdp
                    ]);
                } else {
                    throw new Exception("Enseignant non trouvé.");
                }
                break;

            case 'get_single_teacher':
                $idEns = $_POST['id_ens'];
                $stmtTeacher = $pdo->prepare("
                    SELECT 
                        e.id_ens,
                        e.nom_ens,
                        e.prenom_ens,
                        e.email,
                        u.login_util,
                        g.nom_grd as grade,
                        f.nom_fonction as fonction,
                        gu.lib_GU as groupe_utilisateur
                    FROM enseignant e
                    LEFT JOIN utilisateur u ON e.fk_id_util = u.id_util
                    LEFT JOIN avoir a ON e.id_ens = a.fk_id_ens
                    LEFT JOIN grade g ON a.fk_id_grd = g.id_grd
                    LEFT JOIN occuper o ON e.id_ens = o.fk_id_ens
                    LEFT JOIN fonction f ON o.fk_id_fonc = f.id_fonction
                    LEFT JOIN posseder p ON u.id_util = p.fk_id_util
                    LEFT JOIN groupe_utilisateur gu ON p.fk_id_GU = gu.id_GU
                    WHERE e.id_ens = ?
                ");
                $stmtTeacher->execute([$idEns]);
                $teacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC);

                if ($teacher) {
                    echo json_encode(['success' => true, 'data' => $teacher]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Enseignant non trouvé.']);
                }
                break;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Récupérer les enseignants avec leurs informations
$enseignants = [];
$grades = [];
$fonctions = [];
$userGroups = [];

try {
    $stmtEnseignants = $pdo->query("
        SELECT 
            e.id_ens,
            e.nom_ens,
            e.prenom_ens,
            e.email,
            u.login_util,
            g.nom_grd as grade,
            f.nom_fonction as fonction,
            gu.lib_GU as groupe_utilisateur
        FROM enseignant e
        LEFT JOIN utilisateur u ON e.fk_id_util = u.id_util
        LEFT JOIN avoir a ON e.id_ens = a.fk_id_ens
        LEFT JOIN grade g ON a.fk_id_grd = g.id_grd
        LEFT JOIN occuper o ON e.id_ens = o.fk_id_ens
        LEFT JOIN fonction f ON o.fk_id_fonc = f.id_fonction
        LEFT JOIN posseder p ON u.id_util = p.fk_id_util
        LEFT JOIN groupe_utilisateur gu ON p.fk_id_GU = gu.id_GU
        ORDER BY e.nom_ens, e.prenom_ens
    ");
    $enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtGrades = $pdo->query("SELECT nom_grd FROM grade ORDER BY nom_grd");
    $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtFonctions = $pdo->query("SELECT nom_fonction FROM fonction ORDER BY nom_fonction");
    $fonctions = $stmtFonctions->fetchAll(PDO::FETCH_ASSOC);

    $stmtGroups = $pdo->prepare("SELECT lib_GU FROM groupe_utilisateur WHERE lib_GU IN ('Commission de validation', 'Responsable de filière') ORDER BY lib_GU");
    $stmtGroups->execute();
    $userGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Dossier Enseignant</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* === VARIABLES CSS (maintenues car elles sont globales) === */
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

        /* === RESET (maintenus car ils sont essentiels) === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-primary);
            background-color: var(--gray-50);
            color: var(--gray-800);
            overflow-x: hidden;
        }

        /* === LAYOUT PRINCIPAL (maintenus car spécifiques à la structure globale de la page) === */
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-collapsed-width); }

        /* === TOPBAR (styles du topbar déjà dans topbar.php ou fichier CSS global si existant) === */
        /* Retiré d'ici pour éviter la duplication et les conflits */

        /* === PAGE SPECIFIC STYLES (Styles spécifiques à gestion_dossier_enseignant.php) === */
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
        .form-group input[type="text"], .form-group input[type="email"], .form-group select {
            padding: var(--space-3); border: 1px solid var(--gray-300); border-radius: var(--radius-md);
            font-size: var(--text-base); color: var(--gray-800); transition: all var(--transition-fast);
        }
        .form-group input[type="text"]:focus, .form-group input[type="email"]:focus, .form-group select:focus {
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
        .data-table {
            width: 100%; border-collapse: collapse; font-size: var(--text-sm); color: var(--gray-800);
            min-width: 800px;
        }
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
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 30px; min-height: 30px;
        }
        .action-button.view { background-color: var(--info-500); } .action-button.view:hover { background-color: #316be6; }
        .action-button.edit { background-color: var(--warning-500); } .action-button.edit:hover { background-color: #e68a00; }
        .action-button.delete { background-color: var(--error-500); } .action-button.delete:hover { background-color: #cc3131; }
        .action-button.reset { background-color: var(--secondary-500); } .action-button.reset:hover { background-color: var(--secondary-600); }

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

        /* Barre de recherche */
        .search-bar {
            background: var(--white); border-radius: var(--radius-xl); padding: var(--space-4) var(--space-6);
            box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: var(--space-6);
            display: flex; align-items: center; gap: var(--space-4);
        }
        .search-input-container { flex: 1; position: relative; }
        .search-input {
            width: 100%; padding: var(--space-3) var(--space-10); border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg); font-size: var(--text-base); color: var(--gray-800);
            transition: all var(--transition-fast);
        }
        .search-input:focus { outline: none; border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .search-icon { position: absolute; left: var(--space-3); top: 50%; transform: translateY(-50%); color: var(--gray-400); }
        .search-button {
            padding: var(--space-3) var(--space-5); border-radius: var(--radius-lg); font-weight: 600;
            cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex;
            align-items: center; gap: var(--space-2); background-color: var(--accent-600); color: white;
        }
        .search-button:hover { background-color: var(--accent-700); }
        .download-buttons { display: flex; gap: var(--space-3); }
        .download-button {
            padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-sm);
            font-weight: 500; cursor: pointer; transition: all var(--transition-fast);
            border: 1px solid var(--gray-300); background-color: var(--white); color: var(--gray-700);
            display: inline-flex; align-items: center; gap: var(--space-2);
        }
        .download-button:hover { background-color: var(--gray-100); border-color: var(--gray-400); }

        /* Filtre dropdown */
        .filter-dropdown { position: relative; display: inline-block; }
        .filter-button {
            padding: var(--space-3); border-radius: var(--radius-md); background-color: var(--gray-200);
            color: var(--gray-700); border: none; cursor: pointer; display: flex; align-items: center;
            gap: var(--space-2); transition: all var(--transition-fast);
        }
        .filter-button:hover { background-color: var(--gray-300); }
        .filter-dropdown-content {
            display: none; position: absolute; right: 0; background-color: var(--white);
            min-width: 200px; box-shadow: var(--shadow-md); border-radius: var(--radius-md);
            z-index: 100; padding: var(--space-2); border: 1px solid var(--gray-200);
        }
        .filter-dropdown-content.show { display: block; }
        .filter-option {
            padding: var(--space-3); cursor: pointer; display: flex; align-items: center;
            gap: var(--space-2); border-radius: var(--radius-sm); transition: background-color var(--transition-fast);
        }
        .filter-option:hover { background-color: var(--gray-100); }

        /* Modals */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;
        }
        .modal-content {
            background-color: var(--white); padding: var(--space-6); border-radius: var(--radius-xl);
            width: 90%; max-width: 500px; box-shadow: var(--shadow-xl); position: relative; box-sizing: border-box;
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
        .modal-body-item {
            display: flex; justify-content: space-between; align-items: center; padding: var(--space-3);
            background: var(--gray-50); border-radius: var(--radius-md); margin-bottom: var(--space-2);
        }
        .modal-body-item strong { color: var(--gray-700); }
        .modal-body-item span { color: var(--gray-800); text-align: right; flex-grow: 1; }
        .modal-group-info {
            background: var(--accent-50); border: 1px solid var(--accent-200); border-radius: var(--radius-md);
            padding: var(--space-3); margin-top: var(--space-4); display: flex;
            justify-content: space-between; align-items: center; color: var(--accent-700); font-weight: 600;
        }
        .modal-actions {
            display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-6);
        }

        /* Password reset specific styles */
        .password-reset-success {
            text-align: center; background: var(--success-50); border: 1px solid var(--success-200);
            border-radius: var(--radius-md); padding: var(--space-4); margin-bottom: var(--space-4);
            color: var(--success-700);
        }
        .password-display-box {
            background: var(--white); border: 2px solid var(--accent-300); border-radius: var(--radius-md);
            padding: var(--space-3); margin: var(--space-2) 0; text-align: center;
        }
        .password-display-box code { font-size: var(--text-lg); font-weight: bold; color: var(--accent-800); }
        .password-info-text { color: var(--accent-600); font-size: var(--text-sm); margin-top: var(--space-2); }

        /* Mobile menu overlay */
        .mobile-menu-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5);
            z-index: 999; display: none;
        }

        /* Responsive styles (maintenus et ajustés si nécessaire) */
        @media (max-width: 1024px) {
            .main-content { margin-left: var(--sidebar-collapsed-width); }
            /* sidebar styles for collapsed are in sidebar.php */
        }
        @media (max-width: 768px) {
            .admin-layout { position: relative; }
            .main-content { margin-left: 0; }
            /* sidebar mobile open styles are in sidebar.php */
            .form-grid { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; gap: var(--space-4); }
            .table-actions { width: 100%; justify-content: flex-end; margin-top: var(--space-4); }
            .search-bar { flex-direction: column; align-items: stretch; }
            .download-buttons { width: 100%; justify-content: flex-end; }
            .btn { padding: var(--space-2) var(--space-3); font-size: var(--text-sm); }
            .filter-dropdown-content { left: 0; right: auto; }
            .modal-content { margin: 10% auto; }
        }
        @media (max-width: 480px) {
            .page-content { padding: var(--space-4); }
            .form-card, .table-card, .search-bar { padding: var(--space-4); }
            .page-title-main { font-size: var(--text-2xl); }
            .page-subtitle { font-size: var(--text-base); }
            .form-actions { flex-direction: column; gap: var(--space-2); }
            .btn { width: 100%; justify-content: center; }
            .table-actions { flex-wrap: wrap; gap: var(--space-2); }
            .action-buttons { flex-wrap: wrap; }
            .search-button { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; ?>

            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title-main">Gestion des Dossiers Enseignants</h1>
                    <p class="page-subtitle">Gérez les informations et les comptes des enseignants.</p>
                </div>

                <div class="modal" id="messageModal">
                    <div class="modal-content">
                        <button class="close" id="messageClose">&times;</button>
                        <div style="text-align: center;">
                            <div class="confirmation-icon" id="messageIcon"></div>
                            <h3 class="modal-title" id="messageTitle" style="margin-top: var(--space-4);"></h3>
                            <p class="message-text" id="messageText" style="margin-bottom: var(--space-6);"></p>
                            <button class="btn btn-primary" id="messageButton">OK</button>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <h3 class="form-card-title">Ajouter un nouvel Enseignant</h3>
                    <form id="teacherForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nom_ens">Nom <span style="color: var(--error-500);">*</span></label>
                                <input type="text" id="nom_ens" name="nom_ens" placeholder="Ex: Dupont" required>
                            </div>
                            <div class="form-group">
                                <label for="prenom_ens">Prénom <span style="color: var(--error-500);">*</span></label>
                                <input type="text" id="prenom_ens" name="prenom_ens" placeholder="Ex: Jean" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span style="color: var(--error-500);">*</span></label>
                                <input type="email" id="email" name="email" placeholder="Ex: jean.dupont@univ.com" required>
                            </div>
                            <div class="form-group">
                                <label for="grade">Grade<span style="color: var(--error-500);">*</span></label>
                                <select id="grade" name="grade" required>
                                    <option value="">Sélectionner un grade</option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo htmlspecialchars($grade['nom_grd']); ?>">
                                            <?php echo htmlspecialchars($grade['nom_grd']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="fonction">Fonction<span style="color: var(--error-500);">*</span></label>
                                <select id="fonction" name="fonction" required>
                                    <option value="">Sélectionner une fonction</option>
                                    <?php foreach ($fonctions as $fonction): ?>
                                        <option value="<?php echo htmlspecialchars($fonction['nom_fonction']); ?>">
                                            <?php echo htmlspecialchars($fonction['nom_fonction']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="form-group">
                                <label for="groupe_utilisateur">Groupe Utilisateur <span style="color: var(--error-500);">*</span></label>
                                <select id="groupe_utilisateur" name="groupe_utilisateur" required>
                                    <option value="">Sélectionner un groupe</option>
                                    <?php foreach ($userGroups as $group): ?>
                                        <option value="<?php echo htmlspecialchars($group['lib_GU']); ?>">
                                            <?php echo htmlspecialchars($group['lib_GU']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-user-plus"></i> <span id="submitText">Ajouter Enseignant</span>
                            </button>
                            <button type="reset" class="btn btn-secondary" id="cancelBtn">
                                <i class="fas fa-redo"></i> Annuler
                            </button>
                        </div>
                    </form>
                </div>

                <div class="search-bar">
                    <div class="search-input-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Rechercher un enseignant...">
                    </div>
                    <button class="search-button" id="searchButton">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    <div class="download-buttons">
                        <button class="download-button" id="exportPdfBtn">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="download-button" id="exportExcelBtn">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="download-button" id="exportCsvBtn">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-header">
                        <h3 class="table-title">Liste des Enseignants</h3>
                        <div class="table-actions">
                             <div class="filter-dropdown">
                                <button class="filter-button" id="filterButton">
                                    <i class="fas fa-filter"></i> Filtres
                                </button>
                                <div class="filter-dropdown-content" id="filterDropdown">
                                    <div class="filter-option" data-filter="all">
                                        <i class="fas fa-list"></i> Tous les enseignants
                                    </div>
                                    <div class="filter-option" data-filter="name-asc">
                                        <i class="fas fa-sort-alpha-down"></i> Tri par Nom (A-Z)
                                    </div>
                                    <div class="filter-option" data-filter="name-desc">
                                        <i class="fas fa-sort-alpha-up"></i> Tri par Nom (Z-A)
                                    </div>
                                    <div class="filter-option" data-filter="grade-asc">
                                        <i class="fas fa-user-graduate"></i> Tri par Grade (A-Z)
                                    </div>
                                     <div class="filter-option" data-filter="fonction-asc">
                                        <i class="fas fa-briefcase"></i> Tri par Fonction (A-Z)
                                    </div>
                                    <div class="filter-option" data-filter="group-validation">
                                        <i class="fas fa-check-double"></i> Commission de Validation
                                    </div>
                                     <div class="filter-option" data-filter="group-filiere">
                                        <i class="fas fa-sitemap"></i> Responsable de Filière
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-secondary" id="modifierTeacherBtn" disabled>
                                <i class="fas fa-edit"></i> <span class="action-text">Modifier</span>
                            </button>
                            <button class="btn btn-secondary" id="supprimerTeacherBtn" disabled>
                                <i class="fas fa-trash-alt"></i> <span class="action-text">Supprimer</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="teacherTable">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="checkbox-container">
                                            <input type="checkbox" id="selectAllTeachers">
                                            <span class="checkmark"></span>
                                        </label>
                                    </th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Email</th>
                                    <th>Login</th>
                                    <th>Grade</th>
                                    <th>Fonction</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($enseignants)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--gray-500); padding: var(--space-8);">
                                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                        Aucun enseignant trouvé. Ajoutez votre premier enseignant en utilisant le formulaire ci-dessus.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($enseignants as $enseignant): ?>
                                    <tr data-id="<?php echo htmlspecialchars($enseignant['id_ens']); ?>"
                                        data-group="<?php echo htmlspecialchars($enseignant['groupe_utilisateur'] ?? 'N/A'); ?>">
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" value="<?php echo htmlspecialchars($enseignant['id_ens']); ?>">
                                                <span class="checkmark"></span>
                                            </label>
                                        </td>
                                        <td><?php echo htmlspecialchars($enseignant['nom_ens']); ?></td>
                                        <td><?php echo htmlspecialchars($enseignant['prenom_ens']); ?></td>
                                        <td><?php echo htmlspecialchars($enseignant['email']); ?></td>
                                        <td><?php echo htmlspecialchars($enseignant['login_util'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($enseignant['grade'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($enseignant['fonction'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-button view" title="Voir Dossier" onclick="voirDossierEnseignant('<?php echo htmlspecialchars($enseignant['id_ens']); ?>')">
                                                    <i class="fas fa-folder-open"></i>
                                                </button>
                                                <button class="action-button edit" title="Modifier" onclick="modifierEnseignant('<?php echo htmlspecialchars($enseignant['id_ens']); ?>')">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button class="action-button reset" title="Réinitialiser mot de passe" onclick="resetPassword('<?php echo htmlspecialchars($enseignant['id_ens']); ?>')">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="action-button delete" title="Supprimer" onclick="supprimerEnseignant('<?php echo htmlspecialchars($enseignant['id_ens']); ?>')">
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

    <div id="teacherDetailModal" class="modal">
        <div class="modal-content">
            <button class="close" id="closeTeacherDetailModal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">Dossier Enseignant</h2>
            </div>
            <div id="teacherDetailModalBody">
                </div>
            <div class="modal-actions">
                <button class="btn btn-primary" id="downloadTeacherDossierBtn">
                    <i class="fas fa-download"></i> Télécharger Dossier
                </button>
                <button class="btn btn-secondary" id="closeModalBtn">
                    <i class="fas fa-times"></i> Fermer
                </button>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <button class="close" id="closePasswordModal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">Nouveau Mot de Passe</h2>
            </div>
            <div id="passwordModalBody">
                </div>
            <div style="text-align: center; margin-top: var(--space-4);">
                <button class="btn btn-primary" id="okPasswordModalBtn">
                    <i class="fas fa-check"></i> Compris
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
        // Initialiser jsPDF
        window.jsPDF = window.jspdf.jsPDF;

        // Variables globales
        let selectedTeachers = new Set();
        let editingTeacher = null;
        let currentTeacherIdInModal = null; // Pour stocker l'ID de l'enseignant dont le dossier est ouvert
        const allGrades = <?php echo json_encode(array_column($grades, 'nom_grd')); ?>;
        const allFonctions = <?php echo json_encode(array_column($fonctions, 'nom_fonction')); ?>;
        const allUserGroups = <?php echo json_encode(array_column($userGroups, 'lib_GU')); ?>;

        // Éléments DOM
        const teacherForm = document.getElementById('teacherForm');
        const nomEnsInput = document.getElementById('nom_ens');
        const prenomEnsInput = document.getElementById('prenom_ens');
        const emailInput = document.getElementById('email');
        const gradeInput = document.getElementById('grade');
        const fonctionInput = document.getElementById('fonction');
        const groupeUtilisateurInput = document.getElementById('groupe_utilisateur');
        const teacherTableBody = document.querySelector('#teacherTable tbody');
        const modifierTeacherBtn = document.getElementById('modifierTeacherBtn');
        const supprimerTeacherBtn = document.getElementById('supprimerTeacherBtn');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const cancelBtn = document.getElementById('cancelBtn');
        
        // Modals
        const messageModal = document.getElementById('messageModal');
        const messageIcon = document.getElementById('messageIcon');
        const messageTitle = document.getElementById('messageTitle');
        const messageText = document.getElementById('messageText');
        const messageButton = document.getElementById('messageButton');
        const messageClose = document.getElementById('messageClose');

        const teacherDetailModal = document.getElementById('teacherDetailModal');
        const teacherDetailModalBody = document.getElementById('teacherDetailModalBody');
        const closeTeacherDetailModal = document.getElementById('closeTeacherDetailModal');
        const downloadTeacherDossierBtn = document.getElementById('downloadTeacherDossierBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');

        // Search & Filter
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const filterButton = document.getElementById('filterButton');
        const filterDropdown = document.getElementById('filterDropdown');
        const filterOptions = document.querySelectorAll('#filterDropdown .filter-option');
        const selectAllTeachersCheckbox = document.getElementById('selectAllTeachers');

        // Export Buttons
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const exportExcelBtn = document.getElementById('exportExcelBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn');

        // Sidebar & Main content
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');


        // --- Fonctions globales pour les modals de message ---
        function showMessageModal(message, type = 'success', title = null) {
            if (!title) {
                switch (type) {
                    case 'success': title = 'Succès'; break;
                    case 'error':   title = 'Erreur'; break;
                    case 'warning': title = 'Attention'; break;
                    case 'info':    title = 'Information'; break;
                    default:        title = 'Message';
                }
            }
            messageIcon.className = 'confirmation-icon ' + type;
            messageIcon.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'times-circle' : (type === 'warning' ? 'exclamation-triangle' : 'info-circle'))}"></i>`;
            messageTitle.textContent = title;
            messageText.textContent = message;
            messageModal.style.display = 'flex';
        }

        function closeMessageModal() {
            messageModal.style.display = 'none';
        }

        // Événements pour le modal de message
        messageButton.addEventListener('click', closeMessageModal);
        messageClose.addEventListener('click', closeMessageModal);
        messageModal.addEventListener('click', (e) => {
            if (e.target === messageModal) closeMessageModal();
        });


        // --- Fonctions AJAX ---
        async function makeAjaxRequest(data) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data)
                });
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Erreur HTTP: ${response.status} - ${errorText}`);
                }
                return await response.json();
            } catch (error) {
                console.error('Erreur AJAX:', error);
                showMessageModal('Erreur de communication avec le serveur: ' + error.message, 'error');
                throw error;
            }
        }

        // --- Gestion des états des boutons et sélections ---
        function updateActionButtons() {
            if (selectedTeachers.size === 1) {
                modifierTeacherBtn.disabled = false;
                supprimerTeacherBtn.disabled = false;
            } else if (selectedTeachers.size > 1) {
                modifierTeacherBtn.disabled = true; // Modifier désactivé pour plusieurs sélections
                supprimerTeacherBtn.disabled = false;
            } else {
                modifierTeacherBtn.disabled = true;
                supprimerTeacherBtn.disabled = true;
            }
        }

        function updateSelectAllCheckbox() {
            const checkboxes = teacherTableBody.querySelectorAll('input[type="checkbox"]');
            const checkedCount = teacherTableBody.querySelectorAll('input[type="checkbox"]:checked').length;
            
            if (checkboxes.length === 0) {
                selectAllTeachersCheckbox.indeterminate = false;
                selectAllTeachersCheckbox.checked = false;
            } else if (checkedCount === checkboxes.length) {
                selectAllTeachersCheckbox.indeterminate = false;
                selectAllTeachersCheckbox.checked = true;
            } else if (checkedCount > 0) {
                selectAllTeachersCheckbox.indeterminate = true;
                selectAllTeachersCheckbox.checked = false;
            } else {
                selectAllTeachersCheckbox.indeterminate = false;
                selectAllTeachersCheckbox.checked = false;
            }
        }

        // --- Fonctions de gestion du tableau (ajouter/modifier/supprimer ligne) ---
        function addRowToTable(enseignant) {
            const emptyRow = teacherTableBody.querySelector('td[colspan="8"]');
            if (emptyRow) {
                emptyRow.closest('tr').remove();
            }

            const newRow = teacherTableBody.insertRow();
            newRow.setAttribute('data-id', enseignant.id_ens);
            newRow.setAttribute('data-group', enseignant.groupe_utilisateur || 'N/A');
            newRow.innerHTML = `
                <td>
                    <label class="checkbox-container">
                        <input type="checkbox" value="${enseignant.id_ens}">
                        <span class="checkmark"></span>
                    </label>
                </td>
                <td>${enseignant.nom_ens}</td>
                <td>${enseignant.prenom_ens}</td>
                <td>${enseignant.email}</td>
                <td>${enseignant.login_util || 'N/A'}</td>
                <td>${enseignant.grade || 'N/A'}</td>
                <td>${enseignant.fonction || 'N/A'}</td>
                <td>
                    <div class="action-buttons">
                        <button class="action-button view" title="Voir Dossier" onclick="voirDossierEnseignant('${enseignant.id_ens}')">
                            <i class="fas fa-folder-open"></i>
                        </button>
                        <button class="action-button edit" title="Modifier" onclick="modifierEnseignant('${enseignant.id_ens}')">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="action-button reset" title="Réinitialiser mot de passe" onclick="resetPassword('${enseignant.id_ens}')">
                            <i class="fas fa-key"></i>
                        </button>
                        <button class="action-button delete" title="Supprimer" onclick="supprimerEnseignant('${enseignant.id_ens}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            attachEventListenersToRow(newRow);
        }

        function attachEventListenersToRow(row) {
            const checkbox = row.querySelector('input[type="checkbox"]');
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedTeachers.add(this.value);
                } else {
                    selectedTeachers.delete(this.value);
                }
                updateActionButtons();
                updateSelectAllCheckbox();
            });
        }

        // --- Fonctions de formulaire (ajout/modification) ---
        teacherForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                action: editingTeacher ? 'update' : 'create',
                nom_ens: formData.get('nom_ens'),
                prenom_ens: formData.get('prenom_ens'),
                email: formData.get('email'),
                grade: formData.get('grade'),
                fonction: formData.get('fonction'),
                groupe_utilisateur: formData.get('groupe_utilisateur')
            };

            if (editingTeacher) {
                data.id_ens = editingTeacher;
            }

            try {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;

                const result = await makeAjaxRequest(data);

                if (result.success) {
                    if (editingTeacher) {
                        showMessageModal(result.message, 'success');
                        window.location.reload(); 
                    } else {
                        let loginInfo = '';
                        if(result.data.login_util && result.data.motdepasse_temp) {
                            loginInfo = `<br><br> Login: <strong>${result.data.login_util}</strong><br> Mot de passe temporaire: <strong>${result.data.motdepasse_temp}</strong>`;
                        }
                        showMessageModal(`${result.message}${loginInfo}`, 'info');
                        window.location.reload(); 
                    }
                    resetForm();
                } else {
                    showMessageModal(result.message, 'error');
                }
            } catch (error) {
                // Erreur déjà gérée dans makeAjaxRequest
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });

        function resetForm() {
            editingTeacher = null;
            submitText.textContent = 'Ajouter Enseignant';
            submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Ajouter Enseignant';
            teacherForm.reset();
            selectedTeachers.clear();
            updateActionButtons();
            document.querySelectorAll('#teacherTable tbody input[type="checkbox"]').forEach(cb => cb.checked = false);
            updateSelectAllCheckbox();
        }

        cancelBtn.addEventListener('click', resetForm);

        // --- Fonctions d'actions individuelles et groupées ---
        function modifierEnseignant(idEns) {
            const row = document.querySelector(`tr[data-id="${idEns}"]`);
            if (row) {
                editingTeacher = idEns;
                nomEnsInput.value = row.cells[1].textContent;
                prenomEnsInput.value = row.cells[2].textContent;
                emailInput.value = row.cells[3].textContent;
                
                const gradeText = row.cells[5].textContent;
                const fonctionText = row.cells[6].textContent;
                const groupeUtilisateurText = row.getAttribute('data-group');
                
                gradeInput.value = (gradeText && gradeText !== 'N/A') ? gradeText : '';
                fonctionInput.value = (fonctionText && fonctionText !== 'N/A') ? fonctionText : '';
                groupeUtilisateurInput.value = (groupeUtilisateurText && groupeUtilisateurText !== 'N/A') ? groupeUtilisateurText : '';
                
                submitText.textContent = 'Mettre à jour';
                submitBtn.innerHTML = '<i class="fas fa-edit"></i> Mettre à jour';
                
                document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
            }
        }

        async function supprimerEnseignant(idEns) {
            const row = document.querySelector(`tr[data-id="${idEns}"]`);
            if (row) {
                const nomComplet = `${row.cells[2].textContent} ${row.cells[1].textContent}`;
                
                if (confirm(`Êtes-vous sûr de vouloir supprimer l'enseignant ${nomComplet} ?\n\nCette action supprimera également son compte utilisateur et ne peut être annulée.`)) {
                    try {
                        const result = await makeAjaxRequest({
                            action: 'delete',
                            ids_enseignants: JSON.stringify([idEns])
                        });

                        if (result.success) {
                            row.remove();
                            selectedTeachers.delete(idEns);
                            updateActionButtons();
                            showMessageModal(result.message, 'success');
                            
                            if (teacherTableBody.children.length === 0) {
                                teacherTableBody.innerHTML = `
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--gray-500); padding: var(--space-8);">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                            Aucun enseignant trouvé. Ajoutez votre premier enseignant en utilisant le formulaire ci-dessus.
                                        </td>
                                    </tr>
                                `;
                            }
                            updateSelectAllCheckbox();
                        } else {
                            showMessageModal(result.message, 'error');
                        }
                    } catch (error) {
                        // Erreur déjà gérée dans makeAjaxRequest
                    }
                }
            }
        }

        modifierTeacherBtn.addEventListener('click', function() {
            if (selectedTeachers.size === 1) {
                const idEns = Array.from(selectedTeachers)[0];
                modifierEnseignant(idEns);
            }
        });

        supprimerTeacherBtn.addEventListener('click', async function() {
            if (selectedTeachers.size === 0) {
                showMessageModal("Aucun enseignant sélectionné pour la suppression.", 'warning');
                return;
            }

            const idsArray = Array.from(selectedTeachers);
            
            if (confirm(`Êtes-vous sûr de vouloir supprimer ${idsArray.length} enseignant(s) sélectionné(s) ?\n\nCette action supprimera également leurs comptes utilisateur et ne peut être annulée.`)) {
                try {
                    const result = await makeAjaxRequest({
                        action: 'delete',
                        ids_enseignants: JSON.stringify(idsArray)
                    });

                    if (result.success) {
                        idsArray.forEach(id => {
                            const row = document.querySelector(`tr[data-id="${id}"]`);
                            if (row) row.remove();
                        });
                        selectedTeachers.clear();
                        updateActionButtons();
                        showMessageModal(result.message, 'success');
                        
                        if (teacherTableBody.children.length === 0) {
                            teacherTableBody.innerHTML = `
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--gray-500); padding: var(--space-8);">
                                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: var(--space-2);"></i><br>
                                        Aucun enseignant trouvé. Ajoutez votre premier enseignant en utilisant le formulaire ci-dessus.
                                    </td>
                                </tr>
                            `;
                        }
                        updateSelectAllCheckbox();
                    } else {
                        showMessageModal(result.message, 'error');
                    }
                } catch (error) {
                    // Erreur déjà gérée dans makeAjaxRequest
                }
            }
        });

        // --- Réinitialisation du mot de passe ---
        async function resetPassword(idEns) {
            const row = document.querySelector(`tr[data-id="${idEns}"]`);
            if (row) {
                const nomComplet = `${row.cells[2].textContent} ${row.cells[1].textContent}`;
                
                if (confirm(`Voulez-vous réinitialiser le mot de passe de ${nomComplet} ?\n\nUn nouveau mot de passe temporaire sera généré.`)) {
                    try {
                        const result = await makeAjaxRequest({
                            action: 'reset_password',
                            id_ens: idEns
                        });

                        if (result.success) {
                            const passwordModalBody = document.getElementById('passwordModalBody');
                            passwordModalBody.innerHTML = `
                                <div class="password-reset-success">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: var(--space-2);"></i>
                                    <h3 style="margin-bottom: var(--space-2);">Mot de passe réinitialisé</h3>
                                    <p>Le mot de passe de <strong>${nomComplet}</strong> a été réinitialisé avec succès.</p>
                                </div>
                                <div class="password-display-box">
                                    <h4>Nouveau mot de passe temporaire :</h4>
                                    <code>${result.nouveau_mdp}</code>
                                </div>
                                <p class="password-info-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Veuillez communiquer ce mot de passe à l'enseignant. Il devra le changer lors de sa première connexion.
                                </p>
                            `;
                            passwordModal.style.display = 'flex';
                        } else {
                            showMessageModal(result.message, 'error');
                        }
                    } catch (error) {
                        // Erreur déjà gérée dans makeAjaxRequest
                    }
                }
            }
        }

        // --- Affichage du dossier enseignant (modal) ---
        async function voirDossierEnseignant(idEns) {
            currentTeacherIdInModal = idEns;
            try {
                const result = await makeAjaxRequest({
                    action: 'get_single_teacher',
                    id_ens: idEns
                });

                if (result.success) {
                    const enseignantData = result.data;
                    teacherDetailModalBody.innerHTML = `
                        <div class="modal-body-item"><strong>ID Enseignant:</strong><span>${enseignantData.id_ens}</span></div>
                        <div class="modal-body-item"><strong>Nom:</strong><span>${enseignantData.nom_ens}</span></div>
                        <div class="modal-body-item"><strong>Prénom:</strong><span>${enseignantData.prenom_ens}</span></div>
                        <div class="modal-body-item"><strong>Email:</strong><span>${enseignantData.email}</span></div>
                        <div class="modal-body-item"><strong>Login:</strong><span>${enseignantData.login_util || 'N/A'}</span></div>
                        <div class="modal-body-item"><strong>Grade:</strong><span>${enseignantData.grade || 'N/A'}</span></div>
                        <div class="modal-body-item"><strong>Fonction:</strong><span>${enseignantData.fonction || 'N/A'}</span></div>
                        <div class="modal-group-info"><strong>Groupe utilisateur:</strong><span>${enseignantData.groupe_utilisateur || 'N/A'}</span></div>
                    `;
                    teacherDetailModal.style.display = 'flex';
                } else {
                    showMessageModal(result.message, 'error');
                }
            } catch (error) {
                // Erreur déjà gérée dans makeAjaxRequest
            }
        }

        // --- Fermeture des modals spécifiques ---
        closeTeacherDetailModal.addEventListener('click', () => teacherDetailModal.style.display = 'none');
        closeModalBtn.addEventListener('click', () => teacherDetailModal.style.display = 'none');
        teacherDetailModal.addEventListener('click', (e) => {
            if (e.target === teacherDetailModal) teacherDetailModal.style.display = 'none';
        });

        // --- Fonction Télécharger Dossier (dans le modal) ---
        downloadTeacherDossierBtn.addEventListener('click', function() {
            if (currentTeacherIdInModal) {
                alert(`Fonctionnalité à venir : Télécharger le dossier de l'enseignant ID ${currentTeacherIdInModal}. Cela pourrait générer un PDF récapitulatif.`);
            } else {
                showMessageModal("Aucun dossier d'enseignant sélectionné pour le téléchargement.", 'warning');
            }
        });
        
        // --- Fonctions de recherche et filtre ---
        searchButton.addEventListener('click', searchTeachers);
        searchInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') searchTeachers();
            else if (searchInput.value === '') searchTeachers();
        });

        function searchTeachers() {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = teacherTableBody.querySelectorAll('tr[data-id]');
            
            rows.forEach(row => {
                const nom = row.cells[1].textContent.toLowerCase();
                const prenom = row.cells[2].textContent.toLowerCase();
                const email = row.cells[3].textContent.toLowerCase();
                const login = row.cells[4].textContent.toLowerCase();
                const grade = row.cells[5].textContent.toLowerCase();
                const fonction = row.cells[6].textContent.toLowerCase();
                const groupe = row.getAttribute('data-group').toLowerCase();

                if (nom.includes(searchTerm) || prenom.includes(searchTerm) || email.includes(searchTerm) || 
                    login.includes(searchTerm) || grade.includes(searchTerm) || fonction.includes(searchTerm) ||
                    groupe.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        filterButton.addEventListener('click', () => filterDropdown.classList.toggle('show'));

        filterOptions.forEach(option => {
            option.addEventListener('click', function() {
                const filterType = this.getAttribute('data-filter');
                applyFilter(filterType);
                filterDropdown.classList.remove('show');
            });
        });

        function applyFilter(filterType) {
            let rows = Array.from(teacherTableBody.querySelectorAll('tr[data-id]'));
            
            rows.sort((a, b) => {
                const nomA = a.cells[1].textContent.toLowerCase();
                const nomB = b.cells[1].textContent.toLowerCase();
                const gradeA = a.cells[5].textContent.toLowerCase();
                const gradeB = b.cells[5].textContent.toLowerCase();
                const fonctionA = a.cells[6].textContent.toLowerCase();
                const fonctionB = b.cells[6].textContent.toLowerCase();
                
                switch (filterType) {
                    case 'name-asc': return nomA.localeCompare(nomB);
                    case 'name-desc': return nomB.localeCompare(nomA);
                    case 'grade-asc': return gradeA.localeCompare(gradeB);
                    case 'fonction-asc': return fonctionA.localeCompare(fonctionB);
                    default: return 0;
                }
            });
            
            rows.forEach(row => teacherTableBody.appendChild(row));

            rows.forEach(row => {
                const groupe = row.getAttribute('data-group');
                let shouldDisplay = true;

                switch (filterType) {
                    case 'group-validation':
                        if (groupe !== 'Commission de validation') {
                            shouldDisplay = false;
                        }
                        break;
                    case 'group-filiere':
                        if (groupe !== 'Responsable de filière') {
                            shouldDisplay = false;
                        }
                        break;
                }
                row.style.display = shouldDisplay ? '' : 'none';
            });

            searchTeachers();
        }

        window.addEventListener('click', function(e) {
            if (!e.target.matches('.filter-button') && !e.target.closest('.filter-dropdown')) {
                filterDropdown.classList.remove('show');
            }
        });

        // --- Fonctions d'exportation ---
        function getTableDataForExport() {
            const headers = ['Nom', 'Prénom', 'Email', 'Login', 'Grade', 'Fonction', 'Groupe Utilisateur']; 
            const data = [];
            
            document.querySelectorAll('#teacherTable tbody tr[data-id]').forEach(row => {
                if (row.style.display !== 'none') {
                    data.push([
                        row.cells[1].textContent,
                        row.cells[2].textContent,
                        row.cells[3].textContent,
                        row.cells[4].textContent,
                        row.cells[5].textContent,
                        row.cells[6].textContent,
                        row.getAttribute('data-group')
                    ]);
                }
            });
            return { headers, data };
        }

        exportPdfBtn.addEventListener('click', function() {
            const { headers, data } = getTableDataForExport();
            if (data.length === 0) {
                showMessageModal('Aucune donnée à exporter en PDF.', 'warning');
                return;
            }
            const doc = new jsPDF();
            doc.setFontSize(16);
            doc.text("Liste des Enseignants", 14, 20);
            doc.autoTable({
                startY: 30,
                head: [headers],
                body: data,
                styles: { fontSize: 9, cellPadding: 2, overflow: 'linebreak' },
                headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
                columnStyles: {
                    0: { cellWidth: 'auto' },
                    1: { cellWidth: 'auto' },
                    2: { cellWidth: 'auto' },
                    3: { cellWidth: 'auto' },
                    4: { cellWidth: 'auto' },
                    5: { cellWidth: 'auto' },
                    6: { cellWidth: 'auto' }
                }
            });
            doc.save(`enseignants_${new Date().toISOString().slice(0, 10)}.pdf`);
            showMessageModal("Export PDF réussi !", 'success');
        });

        exportExcelBtn.addEventListener('click', function() {
            const { headers, data } = getTableDataForExport();
            if (data.length === 0) {
                showMessageModal('Aucune donnée à exporter en Excel.', 'warning');
                return;
            }
            const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Enseignants");
            XLSX.writeFile(wb, `enseignants_${new Date().toISOString().slice(0, 10)}.xlsx`);
            showMessageModal("Export Excel réussi !", 'success');
        });

        exportCsvBtn.addEventListener('click', function() {
            const { headers, data } = getTableDataForExport();
            if (data.length === 0) {
                showMessageModal('Aucune donnée à exporter en CSV.', 'warning');
                return;
            }
            let csvContent = headers.map(h => `"${h}"`).join(";") + "\n";
            data.forEach(row => {
                csvContent += row.map(cell => `"${cell}"`).join(";") + "\n";
            });
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.setAttribute('href', URL.createObjectURL(blob));
            link.setAttribute('download', `enseignants_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showMessageModal("Export CSV réussi !", 'success');
        });

        // --- Initialisation et gestion du responsive ---
        selectAllTeachersCheckbox.addEventListener('change', function() {
            const checkboxes = teacherTableBody.querySelectorAll('input[type="checkbox"]');
            selectedTeachers.clear();
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                if (this.checked) {
                    selectedTeachers.add(checkbox.value);
                }
            });
            updateActionButtons();
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#teacherTable tbody tr').forEach(row => {
                attachEventListenersToRow(row);
            });
            updateActionButtons();
            updateSelectAllCheckbox();

            handleResize();
        });

        function handleResize() {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
                document.querySelectorAll('.action-text').forEach(span => {
                    span.style.display = 'none';
                });
                if (sidebarToggle) {
                    sidebarToggle.querySelector('.fa-bars').style.display = 'inline-block';
                    sidebarToggle.querySelector('.fa-times').style.display = 'none';
                }
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                document.querySelectorAll('.action-text').forEach(span => {
                    span.style.display = 'inline';
                });
                if (sidebarToggle) {
                    sidebarToggle.querySelector('.fa-bars').style.display = 'inline-block';
                    sidebarToggle.querySelector('.fa-times').style.display = 'none';
                }
            }
        }

        window.addEventListener('resize', handleResize);

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
                if (mobileMenuOverlay) mobileMenuOverlay.classList.toggle('active');
                
                const barsIcon = sidebarToggle.querySelector('.fa-bars');
                const timesIcon = sidebarToggle.querySelector('.fa-times');
                if (sidebar.classList.contains('mobile-open')) {
                    barsIcon.style.display = 'none';
                    timesIcon.style.display = 'inline-block';
                } else {
                    barsIcon.style.display = 'inline-block';
                    timesIcon.style.display = 'none';
                }
            });
        }
        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                mobileMenuOverlay.classList.remove('active');
                const barsIcon = sidebarToggle.querySelector('.fa-bars');
                const timesIcon = sidebarToggle.querySelector('.fa-times');
                barsIcon.style.display = 'inline-block';
                timesIcon.style.display = 'none';
            });
        }

        // Exposer les fonctions à la portée globale pour les onclicks inline
        window.voirDossierEnseignant = voirDossierEnseignant;
        window.modifierEnseignant = modifierEnseignant;
        window.resetPassword = resetPassword;
        window.supprimerEnseignant = supprimerEnseignant;
    </script>
</body>
</html>