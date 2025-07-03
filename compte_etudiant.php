<?php
// compte_etudiant.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('loginForm.php');
}

// Traitement AJAX pour récupérer les étudiants sans identifiants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_etudiants_sans_identifiants') {
    header('Content-Type: application/json');
    
    try {
        $query = "
            SELECT 
                e.num_etu,
                e.nom_etu,
                e.prenoms_etu,
                e.dte_naiss_etu,
                e.email_etu,
                ne.lib_niv_etu,
                f.lib_filiere,
                aa.date_deb,
                aa.date_fin,
                CONCAT(YEAR(aa.date_deb), '-', YEAR(aa.date_fin)) as annee_academique,
                i.dte_insc
            FROM etudiant e
            INNER JOIN utilisateur u ON e.fk_id_util = u.id_util
            LEFT JOIN inscrire i ON e.num_etu = i.fk_num_etu
            LEFT JOIN niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
            LEFT JOIN filiere f ON e.fk_id_filiere = f.id_filiere
            LEFT JOIN année_academique aa ON i.fk_id_Ac = aa.id_Ac
            WHERE (u.login_util IS NULL OR u.login_util = '' OR u.mdp_util IS NULL OR u.mdp_util = '')
            ORDER BY e.num_etu DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $etudiants]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données : ' . $e->getMessage()]);
    }
    exit;
}

// Traitement AJAX pour récupérer les étudiants avec identifiants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_etudiants_avec_identifiants') {
    header('Content-Type: application/json');
    
    try {
        $query = "
            SELECT 
                e.num_etu,
                e.nom_etu,
                e.prenoms_etu,
                e.dte_naiss_etu,
                e.email_etu,
                u.login_util,
                u.temp_password,
                ne.lib_niv_etu,
                f.lib_filiere,
                aa.date_deb,
                aa.date_fin,
                CONCAT(YEAR(aa.date_deb), '-', YEAR(aa.date_fin)) as annee_academique,
                i.dte_insc,
                u.last_activity,
                u.last_activity as date_creation_compte
            FROM etudiant e
            INNER JOIN utilisateur u ON e.fk_id_util = u.id_util
            LEFT JOIN inscrire i ON e.num_etu = i.fk_num_etu
            LEFT JOIN niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
            LEFT JOIN filiere f ON e.fk_id_filiere = f.id_filiere
            LEFT JOIN année_academique aa ON i.fk_id_Ac = aa.id_Ac
            WHERE u.login_util IS NOT NULL AND u.login_util != '' AND u.mdp_util IS NOT NULL AND u.mdp_util != ''
            ORDER BY e.num_etu DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $etudiants]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données : ' . $e->getMessage()]);
    }
    exit;
}

// NOUVEAU : Action pour récupérer un mot de passe spécifique
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_password') {
    header('Content-Type: application/json');
    
    $numEtu = $_POST['num_etu'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT e.nom_etu, e.prenoms_etu, u.login_util, u.temp_password
            FROM etudiant e 
            INNER JOIN utilisateur u ON e.fk_id_util = u.id_util
            WHERE e.num_etu = ?
        ");
        $stmt->execute([$numEtu]);
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            throw new Exception("Étudiant introuvable.");
        }
        
        if (!$etudiant['temp_password']) {
            throw new Exception("Mot de passe temporaire non disponible. Veuillez le régénérer.");
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'login' => $etudiant['login_util'],
                'motdepasse' => $etudiant['temp_password'],
                'nom_complet' => $etudiant['prenoms_etu'] . ' ' . $etudiant['nom_etu']
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Traitement AJAX pour générer les identifiants d'un étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generer_identifiants') {
    header('Content-Type: application/json');
    
    $numEtu = $_POST['num_etu'];
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer les informations de l'étudiant et son utilisateur associé
        $stmtEtu = $pdo->prepare("
            SELECT e.nom_etu, e.prenoms_etu, e.email_etu, e.fk_id_util 
            FROM etudiant e 
            WHERE e.num_etu = ?
        ");
        $stmtEtu->execute([$numEtu]);
        $etudiant = $stmtEtu->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            throw new Exception("Étudiant introuvable.");
        }
        
        // Générer le login (première lettre du prénom + nom + les 3 derniers chiffres du numéro étudiant)
        $prenom = strtolower(substr($etudiant['prenoms_etu'], 0, 1));
        $nom = strtolower(str_replace(' ', '', $etudiant['nom_etu']));
        $suffixe = substr($numEtu, -3);
        $login = $prenom . $nom . $suffixe;
        
        // Vérifier si le login existe déjà, si oui, ajouter un numéro
        $counter = 1;
        $originalLogin = $login;
        while (true) {
            $checkLoginStmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE login_util = ? AND id_util != ?");
            $checkLoginStmt->execute([$login, $etudiant['fk_id_util']]);
            if ($checkLoginStmt->fetchColumn() == 0) {
                break;
            }
            $login = $originalLogin . $counter;
            $counter++;
        }
        
        // Générer un mot de passe temporaire (8 caractères alphanumériques)
        $motDePasse = generateRandomPassword(8);
        $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
        
        // Mettre à jour l'utilisateur (qui doit déjà exister avec fk_id_util NOT NULL)
        if ($etudiant['fk_id_util']) {
            $stmtUpdateUser = $pdo->prepare("UPDATE utilisateur SET login_util = ?, mdp_util = ?, temp_password = ?, last_activity = NOW() WHERE id_util = ?");
            $stmtUpdateUser->execute([$login, $motDePasseHash, $motDePasse, $etudiant['fk_id_util']]);
            
            // Vérifier que la mise à jour a bien eu lieu
            if ($stmtUpdateUser->rowCount() === 0) {
                throw new Exception("Erreur lors de la mise à jour des identifiants.");
            }
        } else {
            throw new Exception("Erreur système : L'étudiant n'a pas d'utilisateur associé. Contactez l'administrateur.");
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Identifiants générés avec succès pour {$etudiant['prenoms_etu']} {$etudiant['nom_etu']}",
            'data' => [
                'login' => $login,
                'motdepasse' => $motDePasse,
                'nom_complet' => $etudiant['prenoms_etu'] . ' ' . $etudiant['nom_etu']
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération : ' . $e->getMessage()]);
    }
    exit;
}

// Traitement AJAX pour régénérer le mot de passe d'un étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerer_motdepasse') {
    header('Content-Type: application/json');
    
    $numEtu = $_POST['num_etu'];
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer les informations de l'étudiant et son utilisateur associé
        $stmtEtu = $pdo->prepare("
            SELECT e.nom_etu, e.prenoms_etu, e.fk_id_util, u.login_util
            FROM etudiant e 
            INNER JOIN utilisateur u ON e.fk_id_util = u.id_util
            WHERE e.num_etu = ?
        ");
        $stmtEtu->execute([$numEtu]);
        $etudiant = $stmtEtu->fetch(PDO::FETCH_ASSOC);
        
        if (!$etudiant) {
            throw new Exception("Étudiant introuvable.");
        }
        
        if (!$etudiant['fk_id_util'] || !$etudiant['login_util']) {
            throw new Exception("L'étudiant n'a pas d'identifiants générés.");
        }
        
        // Générer un nouveau mot de passe temporaire
        $nouveauMotDePasse = generateRandomPassword(8);
        $motDePasseHash = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
        
        // Mettre à jour la table utilisateur
        $stmtUpdateUser = $pdo->prepare("UPDATE utilisateur SET mdp_util = ?, temp_password = ?, last_activity = NOW() WHERE id_util = ?");
        $stmtUpdateUser->execute([$motDePasseHash, $nouveauMotDePasse, $etudiant['fk_id_util']]);
        
        // Vérifier que la mise à jour a bien eu lieu
        if ($stmtUpdateUser->rowCount() === 0) {
            throw new Exception("Erreur lors de la mise à jour du mot de passe.");
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Nouveau mot de passe généré pour {$etudiant['prenoms_etu']} {$etudiant['nom_etu']}",
            'data' => [
                'login' => $etudiant['login_util'],
                'motdepasse' => $nouveauMotDePasse,
                'nom_complet' => $etudiant['prenoms_etu'] . ' ' . $etudiant['nom_etu']
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la régénération : ' . $e->getMessage()]);
    }
    exit;
}

// NOUVEAU : Action pour réparer les mots de passe manquants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reparer_mdp_jamil') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // Générer un nouveau mot de passe pour Jamil
        $nouveauMotDePasse = generateRandomPassword(8);
        $motDePasseHash = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
        
        // Mettre à jour directement l'utilisateur de Jamil
        $stmt = $pdo->prepare("UPDATE utilisateur SET mdp_util = ?, temp_password = ?, last_activity = NOW() WHERE id_util = 5");
        $stmt->execute([$motDePasseHash, $nouveauMotDePasse]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Aucune mise à jour effectuée.");
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Mot de passe réparé pour Jamil Logbo",
            'data' => [
                'login' => 'jlogbo307',
                'motdepasse' => $nouveauMotDePasse,
                'nom_complet' => 'Jamil Logbo'
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la réparation : ' . $e->getMessage()]);
    }
    exit;
}

// Traitement AJAX pour supprimer des étudiants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer_etudiants') {
    header('Content-Type: application/json');
    
    $numEtudiants = json_decode($_POST['num_etudiants'], true);
    
    try {
        $pdo->beginTransaction();
        
        $supprimerCount = 0;
        foreach ($numEtudiants as $numEtu) {
            // Récupérer l'ID utilisateur associé
            $stmtUser = $pdo->prepare("SELECT fk_id_util FROM etudiant WHERE num_etu = ?");
            $stmtUser->execute([$numEtu]);
            $etudiant = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            // Supprimer les inscriptions
            $stmtDelInsc = $pdo->prepare("DELETE FROM inscrire WHERE fk_num_etu = ?");
            $stmtDelInsc->execute([$numEtu]);
            
            // Supprimer l'étudiant
            $stmtDelEtu = $pdo->prepare("DELETE FROM etudiant WHERE num_etu = ?");
            $stmtDelEtu->execute([$numEtu]);
            
            // Supprimer l'utilisateur associé si il existe
            if ($etudiant && $etudiant['fk_id_util']) {
                $stmtDelUser = $pdo->prepare("DELETE FROM utilisateur WHERE id_util = ?");
                $stmtDelUser->execute([$etudiant['fk_id_util']]);
            }
            
            $supprimerCount++;
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "$supprimerCount étudiant(s) supprimé(s) avec succès"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
    }
    exit;
}

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Gestion des Comptes Étudiants</title>
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

        .table-container { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow: hidden; margin-bottom: var(--space-8); }
        .table-header { padding: var(--space-6); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); }
        .table-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); }
        .table-actions { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
        .search-container { position: relative; }
        .search-input { padding: var(--space-3) var(--space-10) var(--space-3) var(--space-3); border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: var(--text-sm); width: 250px; }
        .search-input:focus { outline: none; border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .search-icon { position: absolute; right: var(--space-3); top: 50%; transform: translateY(-50%); color: var(--gray-400); pointer-events: none; }

        .bulk-actions { padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--gray-200); background: var(--gray-50); display: none; align-items: center; gap: var(--space-4); }
        .bulk-actions.show { display: flex; }
        .selected-count { font-weight: 600; color: var(--gray-700); }

        .table-wrapper { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        .data-table th, .data-table td { padding: var(--space-3); text-align: left; border-bottom: 1px solid var(--gray-200); font-size: var(--text-sm); }
        .data-table th { background-color: var(--gray-50); font-weight: 600; color: var(--gray-700); }
        .data-table tbody tr:hover { background-color: var(--gray-50); }
        .data-table td { color: var(--gray-800); }

        /* Colonnes spécifiques */
        .col-checkbox { width: 40px; }
        .col-num { width: 100px; }
        .col-nom { width: 180px; }
        .col-email { width: 160px; }
        .col-login { width: 100px; }
        .col-password { width: 120px; }
        .col-niveau { width: 100px; }
        .col-filiere { width: 120px; }
        .col-annee { width: 100px; }
        .col-date { width: 100px; }
        .col-actions { width: 150px; }

        .checkbox-cell { width: 40px; }
        .checkbox-cell input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent-500); }

        .badge { padding: var(--space-1) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-xs); font-weight: 600; }
        .badge-success { background-color: var(--secondary-100); color: var(--secondary-600); }
        .badge-warning { background-color: #fef3c7; color: #d97706; }
        .badge-info { background-color: var(--accent-100); color: var(--accent-600); }

        .action-buttons { display: flex; gap: var(--space-1); }
        .btn { padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-sm); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover:not(:disabled) { background-color: var(--accent-700); }
        .btn-success { background-color: var(--success-500); color: white; } .btn-success:hover:not(:disabled) { background-color: var(--secondary-600); }
        .btn-warning { background-color: var(--warning-500); color: white; } .btn-warning:hover:not(:disabled) { background-color: #f59e0b; }
        .btn-danger { background-color: var(--error-500); color: white; } .btn-danger:hover:not(:disabled) { background-color: #dc2626; }
        .btn-outline { background-color: transparent; color: var(--accent-600); border: 1px solid var(--accent-600); } .btn-outline:hover { background-color: var(--accent-50); }
        .btn-sm { padding: var(--space-1) var(--space-2); font-size: var(--text-xs); }

        .password-display { display: flex; align-items: center; gap: var(--space-2); }
        .password-value { font-family: monospace; font-size: 11px; min-width: 60px; }
        .password-masked { color: var(--gray-400); }

        .alert { padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: none; }
        .alert.success { background-color: var(--secondary-50); color: var(--secondary-600); border: 1px solid var(--secondary-100); }
        .alert.error { background-color: #fef2f2; color: var(--error-500); border: 1px solid #fecaca; }
        .alert.info { background-color: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-200); }

        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-spinner { width: 40px; height: 40px; border: 4px solid var(--gray-300); border-top-color: var(--accent-500); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state { text-align: center; padding: var(--space-16); color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); }

        /* === MODAL === */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); display: none; align-items: center; justify-content: center; z-index: 10000; }
        .modal { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6); }
        .modal-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); }
        .modal-close { background: none; border: none; font-size: var(--text-xl); cursor: pointer; color: var(--gray-400); }
        .modal-content { margin-bottom: var(--space-6); }
        .modal-actions { display: flex; gap: var(--space-4); justify-content: flex-end; }

        .credentials-display { background: var(--gray-50); padding: var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--gray-200); }
        .credentials-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-3); }
        .credentials-label { font-weight: 600; color: var(--gray-700); }
        .credentials-value { font-family: monospace; background: var(--white); padding: var(--space-2); border-radius: var(--radius-sm); border: 1px solid var(--gray-300); word-break: break-all; }

        /* Responsive */
        @media (max-width: 1200px) {
            .data-table { min-width: 1400px; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: var(--space-4); }
            .table-actions { width: 100%; flex-direction: column; }
            .search-input { width: 100%; }
            .data-table { min-width: 1000px; }
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
                        <h1 class="page-title-main">Gestion des Comptes Étudiants</h1>
                        <p class="page-subtitle">Génération et gestion des identifiants de connexion</p>
                    </div>
                    <div>
                        <button onclick="repairJamilPassword()" class="btn btn-warning">
                            <i class="fas fa-wrench"></i> Réparer MDP Jamil
                        </button>
                        <a href="liste_etudiant.php" class="btn btn-outline">
                            <i class="fas fa-list"></i> Liste Complète
                        </a>
                        <a href="inscription_etudiant.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouvel Étudiant
                        </a>
                    </div>
                </div>

                <!-- Message d'alerte -->
                <div id="alertMessage" class="alert"></div>

                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="sansIdentifiants">0</div>
                        <div class="stat-label">Sans Identifiants</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="avecIdentifiants">0</div>
                        <div class="stat-label">Avec Identifiants</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="totalEtudiants">0</div>
                        <div class="stat-label">Total Étudiants</div>
                    </div>
                </div>

                <!-- Tableau des étudiants sans identifiants -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-user-plus"></i> Étudiants sans Identifiants
                        </h3>
                        <div class="table-actions">
                            <div class="search-container">
                                <input type="text" id="searchInputSans" placeholder="Rechercher un étudiant..." class="search-input">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button onclick="refreshSansIdentifiants()" class="btn btn-outline">
                                <i class="fas fa-refresh"></i> Actualiser
                            </button>
                            <button onclick="genererTousIdentifiants()" class="btn btn-success">
                                <i class="fas fa-key"></i> Générer Tous
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-num">N° Étudiant</th>
                                    <th class="col-nom">Nom & Prénoms</th>
                                    <th class="col-email">Email</th>
                                    <th class="col-niveau">Niveau</th>
                                    <th class="col-filiere">Filière</th>
                                    <th class="col-annee">Année</th>
                                    <th class="col-date">Date Insc.</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sansIdentifiantsTableBody">
                                <!-- Les données seront chargées dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tableau des étudiants avec identifiants -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-users"></i> Étudiants avec Identifiants
                        </h3>
                        <div class="table-actions">
                            <div class="search-container">
                                <input type="text" id="searchInputAvec" placeholder="Rechercher un étudiant..." class="search-input">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button onclick="refreshAvecIdentifiants()" class="btn btn-outline">
                                <i class="fas fa-refresh"></i> Actualiser
                            </button>
                            <button onclick="exportCredentials()" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Export
                            </button>
                        </div>
                    </div>

                    <!-- Actions en masse -->
                    <div class="bulk-actions" id="bulkActions">
                        <span class="selected-count" id="selectedCount">0 étudiant(s) sélectionné(s)</span>
                        <button onclick="voirDossierSelection()" class="btn btn-primary">
                            <i class="fas fa-folder-open"></i> Voir Dossiers
                        </button>
                        <button onclick="modifierSelection()" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button onclick="supprimerSelection()" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                        <button onclick="exportSelection()" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export Sélection
                        </button>
                    </div>
                    
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-checkbox">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th class="col-num">N° Étudiant</th>
                                    <th class="col-nom">Nom & Prénoms</th>
                                    <th class="col-email">Email</th>
                                    <th class="col-login">Login</th>
                                    <th class="col-password">Mot de passe</th>
                                    <th class="col-niveau">Niveau</th>
                                    <th class="col-filiere">Filière</th>
                                    <th class="col-annee">Année</th>
                                    <th class="col-date">Créé le</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="avecIdentifiantsTableBody">
                                <!-- Les données seront chargées dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Modal pour afficher les identifiants générés -->
    <div class="modal-overlay" id="credentialsModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Identifiants Générés</h3>
                <button class="modal-close" onclick="closeCredentialsModal()">&times;</button>
            </div>
            <div class="modal-content">
                <p>Identifiants générés avec succès pour :</p>
                <h4 id="modalStudentName" style="margin: var(--space-4) 0;"></h4>
                
                <div class="credentials-display">
                    <div class="credentials-row">
                        <span class="credentials-label">Login :</span>
                        <span class="credentials-value" id="modalLogin"></span>
                    </div>
                    <div class="credentials-row">
                        <span class="credentials-label">Mot de passe :</span>
                        <span class="credentials-value" id="modalPassword"></span>
                    </div>
                </div>
                
                <p style="margin-top: var(--space-4); font-size: var(--text-sm); color: var(--gray-600);">
                    <i class="fas fa-info-circle"></i> 
                    Assurez-vous de communiquer ces identifiants à l'étudiant de manière sécurisée.
                </p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="copyCredentials()">
                    <i class="fas fa-copy"></i> Copier
                </button>
                <button class="btn btn-primary" onclick="closeCredentialsModal()">Fermer</button>
            </div>
        </div>
    </div>

    <script>
        let sansIdentifiantsData = [];
        let avecIdentifiantsData = [];
        let selectedEtudiants = [];

        // Chargement des données au démarrage
        document.addEventListener('DOMContentLoaded', function() {
            loadSansIdentifiants();
            loadAvecIdentifiants();
            initSidebar();
            initModalEvents();
        });

        // NOUVEAU : Initialiser les événements de la modal
        function initModalEvents() {
            // Fermer la modal en cliquant à l'extérieur
            const modal = document.getElementById('credentialsModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeCredentialsModal();
                    }
                });
            }
            
            // Fermer la modal avec la touche Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeCredentialsModal();
                }
            });
        }

        // NOUVEAU : Fonction pour réparer le mot de passe de Jamil
        async function repairJamilPassword() {
            if (!confirm('Réparer le mot de passe de Jamil Logbo ?')) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'reparer_mdp_jamil'
                    })
                });

                const result = await response.json();
                console.log('Résultat réparation Jamil:', result);
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    
                    // Afficher la modal avec les identifiants
                    showCredentialsModal(result.data);
                    
                    // Recharger les données
                    await loadAvecIdentifiants();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la réparation du mot de passe', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Fonction corrigée pour voir le mot de passe
        async function voirMotDePasse(numEtu) {
            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_password',
                        num_etu: numEtu
                    })
                });

                const result = await response.json();
                console.log('Résultat get_password:', result);
                
                if (result.success) {
                    showCredentialsModal(result.data);
                } else {
                    showAlert(result.message, 'warning');
                    // Si le mot de passe n'est pas disponible, proposer de le régénérer
                    if (confirm('Mot de passe non disponible. Voulez-vous le régénérer ?')) {
                        regenererMotDePasse(numEtu);
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la récupération du mot de passe', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Fonction corrigée pour afficher la modal avec les identifiants
        function showCredentialsModal(data) {
            console.log('Affichage de la modal avec les données:', data);
            
            const modal = document.getElementById('credentialsModal');
            const studentName = document.getElementById('modalStudentName');
            const loginField = document.getElementById('modalLogin');
            const passwordField = document.getElementById('modalPassword');
            
            if (!modal || !studentName || !loginField || !passwordField) {
                console.error('Éléments de la modal introuvables');
                showAlert('Erreur d\'affichage de la modal', 'error');
                return;
            }
            
            studentName.textContent = data.nom_complet;
            loginField.textContent = data.login;
            passwordField.textContent = data.motdepasse;
            
            // Forcer l'affichage de la modal
            modal.style.display = 'flex';
            modal.style.zIndex = '10000';
            
            console.log('Modal affichée');
        }

        // Fonction corrigée pour fermer la modal
        function closeCredentialsModal() {
            const modal = document.getElementById('credentialsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Fonction corrigée pour régénérer le mot de passe
        async function regenererMotDePasse(numEtu) {
            if (!confirm('Êtes-vous sûr de vouloir générer un nouveau mot de passe pour cet étudiant ?')) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'regenerer_motdepasse',
                        num_etu: numEtu
                    })
                });

                const result = await response.json();
                console.log('Résultat de la régénération:', result);
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    
                    // Afficher la modal avec les nouveaux identifiants
                    showCredentialsModal(result.data);
                    
                    // Recharger les données pour mettre à jour l'affichage
                    await loadAvecIdentifiants();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                console.error('Erreur lors de la régénération:', error);
                showAlert('Erreur lors de la régénération du mot de passe', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Fonction pour charger les étudiants sans identifiants
        async function loadSansIdentifiants() {
            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_etudiants_sans_identifiants'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    sansIdentifiantsData = result.data;
                    displaySansIdentifiants(sansIdentifiantsData);
                    updateStats();
                } else {
                    console.error('Erreur:', result.message);
                    showEmptyState('sansIdentifiantsTableBody', 'Erreur lors du chargement des données', 8);
                }
            } catch (error) {
                console.error('Erreur AJAX:', error);
                showEmptyState('sansIdentifiantsTableBody', 'Erreur de connexion', 8);
            } finally {
                showLoading(false);
            }
        }

        // Fonction pour charger les étudiants avec identifiants
        async function loadAvecIdentifiants() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_etudiants_avec_identifiants'
                    })
                });

                const result = await response.json();
                console.log('Données étudiants avec identifiants:', result);
                
                if (result.success) {
                    avecIdentifiantsData = result.data;
                    displayAvecIdentifiants(avecIdentifiantsData);
                    updateStats();
                } else {
                    console.error('Erreur:', result.message);
                    showEmptyState('avecIdentifiantsTableBody', 'Erreur lors du chargement des données', 11);
                }
            } catch (error) {
                console.error('Erreur AJAX:', error);
                showEmptyState('avecIdentifiantsTableBody', 'Erreur de connexion', 11);
            }
        }

        // Fonction pour afficher les étudiants sans identifiants
        function displaySansIdentifiants(etudiants) {
            const tbody = document.getElementById('sansIdentifiantsTableBody');
            
            if (etudiants.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Tous les étudiants ont des identifiants !</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = etudiants.map(etudiant => `
                <tr>
                    <td><strong>${etudiant.num_etu || 'N/A'}</strong></td>
                    <td>
                        <div style="line-height: 1.3;">
                            <strong>${(etudiant.nom_etu || '') + ' ' + (etudiant.prenoms_etu || '')}</strong>
                        </div>
                    </td>
                    <td>${etudiant.email_etu || 'N/A'}</td>
                    <td>${etudiant.lib_niv_etu || 'N/A'}</td>
                    <td>${etudiant.lib_filiere || 'N/A'}</td>
                    <td>${etudiant.annee_academique || 'N/A'}</td>
                    <td>${etudiant.dte_insc ? formatDate(etudiant.dte_insc) : 'N/A'}</td>
                    <td>
                        <div class="action-buttons">
                            <button onclick="genererIdentifiants('${etudiant.num_etu}')" class="btn btn-sm btn-success" title="Générer les identifiants">
                                <i class="fas fa-key"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        // Fonction pour afficher les étudiants avec identifiants (CORRIGÉE)
        function displayAvecIdentifiants(etudiants) {
            const tbody = document.getElementById('avecIdentifiantsTableBody');
            
            if (etudiants.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Aucun étudiant avec identifiants</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = etudiants.map(etudiant => {
                const motDePasse = etudiant.temp_password || '••••••••';
                const hasPassword = etudiant.temp_password ? true : false;

                return `
                    <tr>
                        <td class="checkbox-cell">
                            <input type="checkbox" class="etudiant-checkbox" value="${etudiant.num_etu}" onchange="updateSelection()">
                        </td>
                        <td><strong>${etudiant.num_etu || 'N/A'}</strong></td>
                        <td>
                            <div style="line-height: 1.3;">
                                <strong>${(etudiant.nom_etu || '') + ' ' + (etudiant.prenoms_etu || '')}</strong>
                            </div>
                        </td>
                        <td>${etudiant.email_etu || 'N/A'}</td>
                        <td><code style="font-size: 11px;">${etudiant.login_util || 'N/A'}</code></td>
                        <td>
                            <div class="password-display">
                                <span class="password-value ${hasPassword ? '' : 'password-masked'}">${motDePasse}</span>
                                <button onclick="voirMotDePasse('${etudiant.num_etu}')" class="btn btn-sm btn-outline" title="Voir le mot de passe">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                        <td>${etudiant.lib_niv_etu || 'N/A'}</td>
                        <td>${etudiant.lib_filiere || 'N/A'}</td>
                        <td>${etudiant.annee_academique || 'N/A'}</td>
                        <td>${etudiant.date_creation_compte ? formatDate(etudiant.date_creation_compte) : 'N/A'}</td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="voirDossier('${etudiant.num_etu}')" class="btn btn-sm btn-outline" title="Voir le dossier">
                                    <i class="fas fa-folder-open"></i>
                                </button>
                                <button onclick="modifierEtudiant('${etudiant.num_etu}')" class="btn btn-sm btn-warning" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="regenererMotDePasse('${etudiant.num_etu}')" class="btn btn-sm btn-primary" title="Régénérer le mot de passe">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button onclick="supprimerEtudiant('${etudiant.num_etu}', '${etudiant.nom_etu} ${etudiant.prenoms_etu}')" class="btn btn-sm btn-danger" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Gestion de la sélection
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.etudiant-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.etudiant-checkbox:checked');
            selectedEtudiants = Array.from(checkboxes).map(cb => cb.value);
            
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');
            
            if (selectedEtudiants.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = `${selectedEtudiants.length} étudiant(s) sélectionné(s)`;
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Mettre à jour l'état du checkbox "Tout sélectionner"
            const totalCheckboxes = document.querySelectorAll('.etudiant-checkbox').length;
            selectAll.indeterminate = selectedEtudiants.length > 0 && selectedEtudiants.length < totalCheckboxes;
            selectAll.checked = selectedEtudiants.length === totalCheckboxes && totalCheckboxes > 0;
        }

        // Actions individuelles
        function voirDossier(numEtu) {
            window.location.href = `dossier_etudiant.php?etudiant=${numEtu}`;
        }

        function modifierEtudiant(numEtu) {
            window.location.href = `modifier_etudiant.php?etudiant=${numEtu}`;
        }

        // Actions en masse
        function voirDossierSelection() {
            if (selectedEtudiants.length === 0) {
                showAlert('Aucun étudiant sélectionné', 'warning');
                return;
            }
            if (selectedEtudiants.length === 1) {
                voirDossier(selectedEtudiants[0]);
            } else {
                showAlert('Veuillez sélectionner un seul étudiant pour voir le dossier', 'warning');
            }
        }

        function modifierSelection() {
            if (selectedEtudiants.length === 0) {
                showAlert('Aucun étudiant sélectionné', 'warning');
                return;
            }
            if (selectedEtudiants.length === 1) {
                modifierEtudiant(selectedEtudiants[0]);
            } else {
                showAlert('Veuillez sélectionner un seul étudiant pour le modifier', 'warning');
            }
        }

        async function supprimerSelection() {
            if (selectedEtudiants.length === 0) {
                showAlert('Aucun étudiant sélectionné', 'warning');
                return;
            }

            if (!confirm(`Êtes-vous sûr de vouloir supprimer ${selectedEtudiants.length} étudiant(s) ?\n\nCette action est irréversible.`)) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'supprimer_etudiants',
                        num_etudiants: JSON.stringify(selectedEtudiants)
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    selectedEtudiants = [];
                    document.getElementById('selectAll').checked = false;
                    loadSansIdentifiants();
                    loadAvecIdentifiants();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la suppression', 'error');
            } finally {
                showLoading(false);
            }
        }

        async function supprimerEtudiant(numEtu, nomComplet) {
            if (!confirm(`Êtes-vous sûr de vouloir supprimer l'étudiant ${nomComplet} ?\n\nCette action est irréversible.`)) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'supprimer_etudiants',
                        num_etudiants: JSON.stringify([numEtu])
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadSansIdentifiants();
                    loadAvecIdentifiants();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la suppression', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Fonction pour générer les identifiants
        async function genererIdentifiants(numEtu) {
            if (!confirm('Êtes-vous sûr de vouloir générer les identifiants pour cet étudiant ?')) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'generer_identifiants',
                        num_etu: numEtu
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    
                    // Afficher la modal avec les identifiants
                    showCredentialsModal(result.data);
                    
                    // Recharger les données
                    loadSansIdentifiants();
                    loadAvecIdentifiants();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la génération des identifiants', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Fonction pour générer tous les identifiants
        async function genererTousIdentifiants() {
            if (sansIdentifiantsData.length === 0) {
                showAlert('Aucun étudiant sans identifiants', 'warning');
                return;
            }

            if (!confirm(`Générer les identifiants pour ${sansIdentifiantsData.length} étudiant(s) ?`)) {
                return;
            }

            try {
                showLoading(true);
                let success = 0;
                let errors = 0;

                for (const etudiant of sansIdentifiantsData) {
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'generer_identifiants',
                                num_etu: etudiant.num_etu
                            })
                        });

                        const result = await response.json();
                        if (result.success) {
                            success++;
                        } else {
                            errors++;
                        }
                    } catch (error) {
                        errors++;
                    }
                }

                showAlert(`${success} identifiants générés avec succès. ${errors} erreurs.`, success > 0 ? 'success' : 'error');
                
                // Recharger les données
                loadSansIdentifiants();
                loadAvecIdentifiants();
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la génération en masse', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Fonction corrigée pour copier les identifiants
        function copyCredentials() {
            const login = document.getElementById('modalLogin').textContent;
            const password = document.getElementById('modalPassword').textContent;
            const text = `Login: ${login}\nMot de passe: ${password}`;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showAlert('Identifiants copiés dans le presse-papiers', 'success');
                }).catch((err) => {
                    console.error('Erreur lors de la copie:', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }

        // Fonction de fallback pour la copie
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showAlert('Identifiants copiés dans le presse-papiers', 'success');
                } else {
                    showAlert('Erreur lors de la copie', 'error');
                }
            } catch (err) {
                console.error('Fallback: Erreur lors de la copie', err);
                showAlert('Impossible de copier automatiquement', 'error');
            }

            document.body.removeChild(textArea);
        }

        // Fonctions d'export
        function exportCredentials() {
            if (avecIdentifiantsData.length === 0) {
                showAlert('Aucune donnée à exporter', 'warning');
                return;
            }

            const data = avecIdentifiantsData.map(etudiant => ({
                'N° Étudiant': etudiant.num_etu,
                'Nom': etudiant.nom_etu,
                'Prénoms': etudiant.prenoms_etu,
                'Email': etudiant.email_etu,
                'Login': etudiant.login_util,
                'Mot de passe': etudiant.temp_password || 'Non disponible',
                'Niveau': etudiant.lib_niv_etu,
                'Filière': etudiant.lib_filiere,
                'Année académique': etudiant.annee_academique,
                'Date création compte': etudiant.date_creation_compte
            }));

            exportToCSV(data, 'comptes_etudiants');
        }

        function exportSelection() {
            if (selectedEtudiants.length === 0) {
                showAlert('Aucun étudiant sélectionné', 'warning');
                return;
            }

            const selectedData = avecIdentifiantsData
                .filter(e => selectedEtudiants.includes(e.num_etu))
                .map(etudiant => ({
                    'N° Étudiant': etudiant.num_etu,
                    'Nom': etudiant.nom_etu,
                    'Prénoms': etudiant.prenoms_etu,
                    'Email': etudiant.email_etu,
                    'Login': etudiant.login_util,
                    'Mot de passe': etudiant.temp_password || 'Non disponible',
                    'Niveau': etudiant.lib_niv_etu,
                    'Filière': etudiant.lib_filiere,
                    'Année académique': etudiant.annee_academique,
                    'Date création compte': etudiant.date_creation_compte
                }));

            exportToCSV(selectedData, 'etudiants_selection');
        }

        function exportToCSV(data, filename) {
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => headers.map(header => `"${row[header] || ''}"`).join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Fonction pour mettre à jour les statistiques
        function updateStats() {
            const total = sansIdentifiantsData.length + avecIdentifiantsData.length;
            document.getElementById('sansIdentifiants').textContent = sansIdentifiantsData.length;
            document.getElementById('avecIdentifiants').textContent = avecIdentifiantsData.length;
            document.getElementById('totalEtudiants').textContent = total;
        }

        // Fonctions de recherche
        document.getElementById('searchInputSans').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const filteredData = sansIdentifiantsData.filter(etudiant => {
                return (
                    (etudiant.nom_etu && etudiant.nom_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.prenoms_etu && etudiant.prenoms_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.email_etu && etudiant.email_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.num_etu && etudiant.num_etu.toString().includes(searchTerm)) ||
                    (etudiant.lib_niv_etu && etudiant.lib_niv_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.lib_filiere && etudiant.lib_filiere.toLowerCase().includes(searchTerm))
                );
            });
            displaySansIdentifiants(filteredData);
        });

        document.getElementById('searchInputAvec').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const filteredData = avecIdentifiantsData.filter(etudiant => {
                return (
                    (etudiant.nom_etu && etudiant.nom_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.prenoms_etu && etudiant.prenoms_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.email_etu && etudiant.email_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.login_util && etudiant.login_util.toLowerCase().includes(searchTerm)) ||
                    (etudiant.num_etu && etudiant.num_etu.toString().includes(searchTerm)) ||
                    (etudiant.lib_niv_etu && etudiant.lib_niv_etu.toLowerCase().includes(searchTerm)) ||
                    (etudiant.lib_filiere && etudiant.lib_filiere.toLowerCase().includes(searchTerm))
                );
            });
            displayAvecIdentifiants(filteredData);
        });

        // Fonctions pour actualiser les données
        function refreshSansIdentifiants() {
            loadSansIdentifiants();
        }

        function refreshAvecIdentifiants() {
            loadAvecIdentifiants();
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

        // Fonction pour afficher les alertes
        function showAlert(message, type = 'info') {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.textContent = message;
            alertDiv.className = `alert ${type}`;
            alertDiv.style.display = 'block';
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        // Fonction pour afficher/cacher le loading
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = show ? 'flex' : 'none';
        }

        // Fonction pour afficher un état vide
        function showEmptyState(tableBodyId, message, colspan) {
            const tbody = document.getElementById(tableBodyId);
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>${message}</p>
                    </td>
                </tr>
            `;
        }

        // Fonctions utilitaires pour formater les dates
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR');
        }

        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return 'N/A';
            const date = new Date(dateTimeStr);
            return date.toLocaleString('fr-FR');
        }
    </script>
</body>
</html>