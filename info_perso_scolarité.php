<?php
// informations_respo_scolarite.php ou info_perso_scolarité.php
require_once 'config.php'; // Assurez-vous que ce fichier inclut votre connexion PDO et les fonctions isLoggedIn/redirect

if (!isLoggedIn()) {
    redirect('loginForm.php'); // Redirige si l'utilisateur n'est pas connecté
}

// Vérifier si l'utilisateur est bien un responsable de scolarité
$userId = $_SESSION['id_util'] ?? null; // Utilisez 'id_util' tel que défini dans votre topbar.php
$userData = null;

// Définir des IDs de traitement pour la journalisation des actions
// Ces IDs devraient exister dans votre table 'traitement' (à créer/gérer)
// Pour l'instant, ce sont des constantes pour la simulation.
define('ID_TRAITEMENT_MODIF_PROFIL', 1); // Exemple: Assurez-vous que cet ID existe dans 'traitement' pour "Modification de profil"
define('ID_TRAITEMENT_CHANGE_MDP', 2);   // Exemple: Assurez-vous que cet ID existe dans 'traitement' pour "Changement de mot de passe"


if ($userId) {
    try {
        // Récupérer les informations du personnel administratif
        $stmt = $pdo->prepare("
            SELECT pa.id_pers, pa.nom_pers, pa.prenoms_pers, pa.email_pers, pa.telephone, pa.poste, u.login_util
            FROM personnel_admin pa
            JOIN utilisateur u ON pa.fk_id_util = u.id_util
            WHERE pa.fk_id_util = :userId
            LIMIT 1
        ");
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si ce n'est pas un personnel_admin (ou profil non trouvé), gérer l'erreur
        if (!$userData) {
            $error_message = "Votre profil de responsable de scolarité n'a pas été trouvé ou accès non autorisé.";
            // Optionnel: rediriger vers une page d'erreur ou le tableau de bord avec un message.
            // redirect('dashboard.php?error=profil_introuvable');
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des informations personnelles du responsable: " . $e->getMessage());
        $error_message = "Erreur de base de données lors du chargement de vos informations.";
    }
} else {
    $error_message = "ID utilisateur non trouvé en session. Veuillez vous reconnecter.";
}

// Fonction pour pister une action
function pisterAction($pdo, $userId, $idTraitement, $description = null) {
    // Vérifiez si la table 'pister' a un ID auto-incrémenté ou gérez l'ID manuellement
    // Dans votre SQL dump, id_pister est INT NOT NULL, mais pas AUTO_INCREMENT.
    // Il est fortement recommandé de changer 'id_pister' en AUTO_INCREMENT pour une table d'audit.
    // ALTER TABLE pister MODIFY COLUMN id_pister INT AUTO_INCREMENT;

    // Pour l'exemple, nous allons simuler l'insertion ou faire une insertion simple si id_pister peut être NULL ou si AUTO_INCREMENT est activé.
    // Si id_pister n'est PAS AUTO_INCREMENT et est NOT NULL, vous devez générer un ID unique ici.
    // Par exemple: $newPisterId = $pdo->query("SELECT COALESCE(MAX(id_pister), 0) + 1 FROM pister")->fetchColumn();
    // Mais cette approche est risquée en environnement multi-utilisateur.
    // Pour l'exercice, nous allons le laisser en commentaire avec une note sur la nécessité d'AUTO_INCREMENT.

    try {
        // --- IMPORTANT: Assurez-vous que id_pister est AUTO_INCREMENT dans votre BD pour utiliser NULL ici. ---
        // ALTER TABLE pister MODIFY COLUMN id_pister INT AUTO_INCREMENT;
        // Ou générez un ID unique si ce n'est pas AUTO_INCREMENT et NOT NULL.
        // ex: $newPisterId = getNextPisterId($pdo);
        // Puis utilisez : INSERT INTO pister (id_pister, fk_id_util, fk_id_trait, dte_acc, heure_pist, acceder) ... VALUES (:newPisterId, ...)

        $stmt = $pdo->prepare("
            INSERT INTO pister (fk_id_util, fk_id_trait, dte_acc, heure_pist, acceder)
            VALUES (:userId, :idTraitement, CURDATE(), CURTIME(), 'oui')
        ");
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':idTraitement', $idTraitement, PDO::PARAM_INT);
        $stmt->execute();
        // Optionnel: Vous pourriez aussi loguer la description dans une colonne 'details' si vous l'ajoutez à 'pister'
        // ALTER TABLE pister ADD COLUMN description_action TEXT;
        // Puis: $stmt->bindParam(':description', $description);
    } catch (PDOException $e) {
        error_log("Erreur lors de la journalisation de l'action pour utilisateur {$userId}, traitement {$idTraitement}: " . $e->getMessage());
        // Vous pouvez décider de ne pas afficher cette erreur à l'utilisateur final.
    }
}

// Traitement des requêtes AJAX (Mise à jour des informations)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if ($userId === null) {
        $response['message'] = 'Utilisateur non connecté ou ID manquant.';
        echo json_encode($response);
        exit;
    }

    try {
        switch ($_POST['action']) {
            case 'update_profile':
                $nom = trim($_POST['nom'] ?? '');
                $prenoms = trim($_POST['prenoms'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $telephone = trim($_POST['telephone'] ?? '');

                if (empty($nom) || empty($prenoms) || empty($email)) {
                    throw new Exception("Les champs Nom, Prénoms et Email sont obligatoires.");
                }

                // Valider l'email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Format d'email invalide.");
                }

                $stmt = $pdo->prepare("
                    UPDATE personnel_admin
                    SET nom_pers = :nom, prenoms_pers = :prenoms, email_pers = :email, telephone = :telephone
                    WHERE fk_id_util = :userId
                ");
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':prenoms', $prenoms);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':telephone', $telephone);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Vos informations ont été mises à jour avec succès.';
                    // Mettre à jour les informations en session si elles sont utilisées dans la topbar
                    $_SESSION['nom_prenom'] = $prenoms . ' ' . $nom; //
                    
                    // Retourner les nouvelles valeurs pour la mise à jour du DOM
                    $response['new_name_for_topbar'] = $prenoms . ' ' . $nom;
                    
                    // Journaliser l'action
                    pisterAction($pdo, $userId, ID_TRAITEMENT_MODIF_PROFIL, "Modification du profil personnel");
                } else {
                    $response['message'] = 'Aucune modification détectée ou erreur de mise à jour.';
                }
                break;

            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception("Tous les champs de mot de passe sont obligatoires.");
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception("Le nouveau mot de passe et sa confirmation ne correspondent pas.");
                }

                if (strlen($newPassword) < 8) {
                    throw new Exception("Le nouveau mot de passe doit contenir au moins 8 caractères.");
                }
                // Optionnel: Ajouter des contraintes de complexité (majuscules, chiffres, symboles)

                // Vérifier l'ancien mot de passe
                $stmt = $pdo->prepare("SELECT mdp_util FROM utilisateur WHERE id_util = :userId");
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $userPassHash = $stmt->fetchColumn();

                if (!password_verify($currentPassword, $userPassHash)) {
                    throw new Exception("L'ancien mot de passe est incorrect.");
                }

                // Mettre à jour le mot de passe
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateur SET mdp_util = :newPassHash, force_password_change = 0 WHERE id_util = :userId");
                $stmt->bindParam(':newPassHash', $newPasswordHash);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();

                $response['success'] = true;
                $response['message'] = 'Votre mot de passe a été mis à jour avec succès.';
                
                // Journaliser l'action
                pisterAction($pdo, $userId, ID_TRAITEMENT_CHANGE_MDP, "Changement du mot de passe");
                break;

            default:
                throw new Exception("Action non reconnue.");
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Mes Informations Personnelles</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Styles CSS inchangés pour cet exemple, car ils viennent de votre fichier */
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

        .profile-section {
            display: grid;
            grid-template-columns: 280px 1fr; /* Fixed sidebar-like left column, flexible right column */
            gap: var(--space-8);
            margin-bottom: var(--space-8);
        }

        .profile-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            align-items: center; /* Center content horizontally */
            text-align: center; /* Center text */
        }

        .profile-header {
            margin-bottom: var(--space-6);
            width: 100%; /* Take full width of card */
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-4);
            overflow: hidden;
            border: 4px solid var(--accent-300); /* Add a border */
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .profile-name { font-size: var(--text-xl); font-weight: 700; margin-bottom: var(--space-1); color: var(--gray-900);}
        .profile-role { font-size: var(--text-sm); color: var(--gray-600); margin-bottom: var(--space-4);}

        /* General form/info card styling */
        .info-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: var(--space-6); /* Space between info cards */
        }
        .info-card-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);
            border-bottom: 1px solid var(--gray-200); padding-bottom: var(--space-3);
        }
        .info-card-title { font-size: var(--text-lg); font-weight: 600; color: var(--gray-900); }
        .info-card-icon {
            width: 40px; height: 40px; background-color: var(--accent-100); border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center; color: var(--accent-600);
            font-size: var(--text-base);
        }

        /* Form fields styling */
        .form-group { margin-bottom: var(--space-4); }
        .form-group label {
            display: block;
            font-size: var(--text-sm);
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: var(--space-2);
        }
        .form-control {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            color: var(--gray-800);
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--accent-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .form-control:disabled {
            background-color: var(--gray-100);
            color: var(--gray-500);
            cursor: not-allowed;
        }
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .btn { /* Reusing btn styles from your provided CSS */
            padding: var(--space-3) var(--space-5); border-radius: var(--radius-md); font-size: var(--text-base); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover:not(:disabled) { background-color: var(--accent-700); }
        .btn-secondary { background-color: var(--gray-200); color: var(--gray-700); } .btn-secondary:hover:not(:disabled) { background-color: var(--gray-300); }
        .btn-outline { background-color: transparent; color: var(--accent-600); border: 1px solid var(--accent-600); } .btn-outline:hover { background-color: var(--accent-50); }

        .page-actions {
            display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-6);
        }

        /* Message Box / Toast */
        .message-box {
            position: fixed; /* Fixed position */
            top: 20px;
            right: 20px;
            background-color: #4CAF50; /* Green for success */
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none; /* Hidden by default */
            animation: fadeInOut 5s forwards; /* Animation */
        }

        .message-box.error { background-color: #f44336; } /* Red for error */
        .message-box.info { background-color: #2196F3; } /* Blue for info */
        .message-box.warning { background-color: #ff9800; } /* Orange for warning */

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none; /* Hidden by default */
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: var(--accent-600);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal for password change */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 10000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Center content vertically */
        }
        .modal-content {
            background-color: #fefefe;
            padding: var(--space-6);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: var(--space-4);
            margin-bottom: var(--space-6);
        }
        .modal-header h2 {
            font-size: var(--text-2xl);
            color: var(--gray-900);
            font-weight: 700;
        }
        .modal-close-btn {
            color: var(--gray-500);
            font-size: var(--text-3xl);
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .modal-close-btn:hover, .modal-close-btn:focus {
            color: var(--gray-800);
            text-decoration: none;
        }
        .modal-body .form-group {
            margin-bottom: var(--space-4);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3);
            margin-top: var(--space-6);
            border-top: 1px solid var(--gray-200);
            padding-top: var(--space-4);
        }


        /* Responsive adjustments */
        @media (max-width: 992px) {
            .profile-section { grid-template-columns: 1fr; } /* Stack columns on smaller screens */
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .page-header { flex-direction: column; align-items: flex-start; gap: var(--space-4); }
            .info-card-grid { grid-template-columns: 1fr; } /* Stack fields in info cards */
            
            /* Specific icon toggle for mobile sidebar */
            .sidebar-toggle .fa-bars { display: inline-block; }
            .sidebar-toggle .fa-times { display: none; }
            .sidebar.mobile-open + .main-content .sidebar-toggle .fa-bars { display: none; }
            .sidebar.mobile-open + .main-content .sidebar-toggle .fa-times { display: inline-block; }
        }
        @media (max-width: 480px) {
            .page-content { padding: var(--space-4); }
            .info-card { padding: var(--space-4); }
            .btn { width: 100%; justify-content: center; } /* Full width buttons */
            .page-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar_respo_scolarité.php'; // Inclure la sidebar spécifique du responsable ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; // Inclure la topbar ?>

            <div class="page-content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title-main">Mes Informations Personnelles</h1>
                        <p class="page-subtitle">Gérez vos informations de profil et votre mot de passe</p>
                    </div>
                </div>

                <?php if (isset($error_message)): /* Display error message if user data couldn't be loaded */ ?>
                    <div class="message-box error" style="display: block;">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($userData): /* Only show form if user data is available */ ?>
                <div class="profile-section">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fas fa-user-tie" style="font-size: 3rem; color: var(--gray-500);"></i>
                                </div>
                            <h2 class="profile-name"><span id="profileDisplayName"><?= htmlspecialchars($userData['prenoms_pers'] ?? '') . ' ' . htmlspecialchars($userData['nom_pers'] ?? '') ?></span></h2>
                            <p class="profile-role"><?= htmlspecialchars($userData['poste'] ?? 'Responsable de Scolarité') ?></p>
                            <button class="btn btn-outline" style="margin-top: var(--space-4);">
                                <i class="fas fa-camera"></i> Changer la photo
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="info-card">
                            <div class="info-card-header">
                                <h3 class="info-card-title">Informations Personnelles</h3>
                                <div class="info-card-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                            <form id="profileForm">
                                <div class="form-group">
                                    <label for="nom">Nom:</label>
                                    <input type="text" id="nom" name="nom" class="form-control" value="<?= htmlspecialchars($userData['nom_pers'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="prenoms">Prénoms:</label>
                                    <input type="text" id="prenoms" name="prenoms" class="form-control" value="<?= htmlspecialchars($userData['prenoms_pers'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($userData['email_pers'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="telephone">Téléphone:</label>
                                    <input type="tel" id="telephone" name="telephone" class="form-control" value="<?= htmlspecialchars($userData['telephone'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="poste">Poste:</label>
                                    <input type="text" id="poste" name="poste" class="form-control" value="<?= htmlspecialchars($userData['poste'] ?? '') ?>" disabled>
                                </div>
                                <div class="page-actions">
                                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                        <i class="fas fa-save"></i> Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="info-card">
                            <div class="info-card-header">
                                <h3 class="info-card-title">Informations de Connexion</h3>
                                <div class="info-card-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="login">Identifiant (Login):</label>
                                <input type="text" id="login" name="login" class="form-control" value="<?= htmlspecialchars($userData['login_util'] ?? '') ?>" disabled>
                            </div>
                            <div class="page-actions">
                                <button type="button" class="btn btn-secondary" id="changePasswordBtn">
                                    <i class="fas fa-lock"></i> Changer le mot de passe
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <p class="empty-state">
                        <i class="fas fa-exclamation-circle"></i><br>
                        Impossible de charger vos informations de profil.
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="message-box" id="messageBox"></div>

    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Changer le mot de passe</h2>
                <span class="modal-close-btn">&times;</span>
            </div>
            <form id="passwordChangeForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel:</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Enregistrer le nouveau mot de passe</button>
                    <button type="button" class="btn btn-secondary modal-close-btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Utility Functions
        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function showMessageBox(message, type = 'info') {
            const msgBox = document.getElementById('messageBox');
            msgBox.textContent = message;
            msgBox.className = 'message-box ' + type; // Set class based on type
            msgBox.style.display = 'block';
            setTimeout(() => {
                msgBox.style.display = 'none';
            }, 5000); // Hide after 5 seconds
        }

        async function makeAjaxRequest(data) {
            showLoading(true);
            try {
                // Ensure the path is correct for the AJAX endpoint
                const response = await fetch('info_perso_scolarité.php', { // Ensure this path is correct based on your file naming
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data).toString()
                });
                if (!response.ok) {
                    // Log the full response for debugging HTTP errors
                    const errorText = await response.text();
                    console.error('HTTP Error Details:', errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                return result;
            } catch (error) {
                console.error('AJAX Error:', error);
                showMessageBox('Erreur de connexion au serveur.', 'error');
                return { success: false, message: 'Erreur de connexion au serveur.' };
            } finally {
                showLoading(false);
            }
        }

        // Sidebar Management (adapted from your existing files)
        function initSidebar() {
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay'); // Make sure this element exists in your topbar.php or is added

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    // Logic for mobile sidebar
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('mobile-open');
                        mobileMenuOverlay.classList.toggle('active');
                        // Toggle icon
                        const barsIcon = sidebarToggle.querySelector('.fa-bars');
                        const timesIcon = sidebarToggle.querySelector('.fa-times');
                        if (sidebar.classList.contains('mobile-open')) {
                            if (barsIcon) barsIcon.style.display = 'none';
                            if (timesIcon) timesIcon.style.display = 'inline-block';
                        } else {
                            if (barsIcon) barsIcon.style.display = 'inline-block';
                            if (timesIcon) timesIcon.style.display = 'none';
                        }
                    } else { // Logic for desktop sidebar
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
                    const timesIcon = sidebarToggle.querySelector('.fa-times');
                    if (barsIcon) barsIcon.style.display = 'inline-block';
                    if (timesIcon) timesIcon.style.display = 'none';
                });
            }

            // Responsive: handle initial load and resize
            function handleResize() {
                if (window.innerWidth <= 768) {
                    if (sidebar) sidebar.classList.add('collapsed'); // Default to collapsed on mobile
                    if (mainContent) mainContent.classList.add('sidebar-collapsed');
                } else {
                    if (sidebar) {
                        sidebar.classList.remove('mobile-open'); // Close mobile sidebar if resized to desktop
                        mobileMenuOverlay.classList.remove('active');
                        sidebar.classList.remove('collapsed'); // Keep expanded on desktop by default
                    }
                    if (mainContent) mainContent.classList.remove('sidebar-collapsed');
                }
            }
            window.addEventListener('resize', handleResize);
            handleResize(); // Call on initial load
        }

        // Profile Form Management
        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update_profile');

            const data = Object.fromEntries(formData.entries()); // Convert FormData to plain object

            const result = await makeAjaxRequest(data);
            if (result.success) {
                showMessageBox(result.message, 'success');
                // Update display name in profile card
                document.getElementById('profileDisplayName').textContent = data.prenoms + ' ' + data.nom;
                // Update display name in top bar
                const topbarUserName = document.querySelector('.topbar-right .user-name');
                if (topbarUserName) {
                    topbarUserName.textContent = result.new_name_for_topbar; // Use the name returned from backend
                }
            } else {
                showMessageBox(result.message, 'error');
            }
        });

        // Password Change Modal Management
        const passwordModal = document.getElementById('passwordModal');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const modalCloseBtns = document.querySelectorAll('.modal-close-btn');

        changePasswordBtn.addEventListener('click', function() {
            passwordModal.style.display = 'flex';
        });

        modalCloseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                passwordModal.style.display = 'none';
                document.getElementById('passwordChangeForm').reset(); // Reset form when closing
            });
        });

        passwordModal.addEventListener('click', function(e) {
            if (e.target === passwordModal) {
                passwordModal.style.display = 'none';
                document.getElementById('passwordChangeForm').reset();
            }
        });

        document.getElementById('passwordChangeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'change_password');

            const data = Object.fromEntries(formData.entries()); // Convert FormData to plain object

            const result = await makeAjaxRequest(data);
            if (result.success) {
                showMessageBox(result.message, 'success');
                passwordModal.style.display = 'none'; // Close modal on success
                this.reset(); // Reset form fields
            } else {
                showMessageBox(result.message, 'error');
            }
        });

        // Initialization on DOM Load
        document.addEventListener('DOMContentLoaded', function() {
            initSidebar();
            // Optional: Show initial message if profile data wasn't loaded
            <?php if (isset($error_message) && $userData === null): ?>
                showMessageBox("<?= htmlspecialchars($error_message) ?>", "error");
            <?php endif; ?>
        });
    </script>
</body>
</html>