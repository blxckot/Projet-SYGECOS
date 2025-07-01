<?php
// Inclure le fichier de configuration pour la connexion DB et les fonctions utilitaires
require_once 'config.php'; // Ce fichier démarre déjà la session.

// Obtenir l'adresse IP du client
$ip_address = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';

// Vérifier si le formulaire a été soumis via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifier si l'IP est bloquée AVANT de traiter les identifiants
    if (isIPBlocked($pdo, $ip_address)) {
        $_SESSION['login_error'] = "Accès temporairement bloqué en raison de trop de tentatives échouées.";
        redirect('loginForm.php'); // Rediriger vers le formulaire avec le message de blocage
    }

    // Récupérer et nettoyer les données du formulaire
    $identifier = cleanInput($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation basique des champs
    if (empty($identifier) || empty($password)) {
        $_SESSION['login_error'] = "Veuillez remplir tous les champs.";
        redirect('loginForm.php');
    }

    $user_found = false;
    $user_data = null;
    $user_type = null;

    try {
        // --- Processus de vérification de l'utilisateur dans les différentes tables ---

        // 1. VÉRIFICATION DANS LA TABLE PERSONNEL_ADMIN (email OU login)
        $sql_admin = "SELECT
                        u.id_util, u.login_util, u.mdp_util,
                        p.nom_pers AS nom, p.prenoms_pers AS prenom, p.email_pers AS email, p.poste,
                        gu.lib_GU AS role, gu.id_GU AS role_id
                      FROM utilisateur u
                      JOIN personnel_admin p ON u.id_util = p.fk_id_util
                      JOIN posseder pos ON u.id_util = pos.fk_id_util
                      JOIN groupe_utilisateur gu ON pos.fk_id_GU = gu.id_GU
                      WHERE p.email_pers = :identifier OR u.login_util = :identifier2";
        $stmt = $pdo->prepare($sql_admin);
        $stmt->bindParam(':identifier', $identifier, PDO::PARAM_STR);
        $stmt->bindParam(':identifier2', $identifier, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            $user_found = true;
            $user_data = $user;
            $user_type = 'personnel_admin';
        }

        // 2. SI PAS TROUVÉ, VÉRIFICATION DANS LA TABLE ENSEIGNANT (email OU login)
        if (!$user_found) {
            $sql_enseignant = "SELECT
                                u.id_util, u.login_util, u.mdp_util,
                                e.nom_ens AS nom, e.prenom_ens AS prenom, e.email,
                                gu.lib_GU AS role, gu.id_GU AS role_id
                              FROM utilisateur u
                              JOIN enseignant e ON u.id_util = e.fk_id_util
                              JOIN posseder pos ON u.id_util = pos.fk_id_util
                              JOIN groupe_utilisateur gu ON pos.fk_id_GU = gu.id_GU
                              WHERE e.email = :identifier OR u.login_util = :identifier2";
            $stmt = $pdo->prepare($sql_enseignant);
            $stmt->bindParam(':identifier', $identifier, PDO::PARAM_STR);
            $stmt->bindParam(':identifier2', $identifier, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                $user_found = true;
                $user_data = $user;
                $user_type = 'enseignant';
            }
        }

        // 3. SI PAS TROUVÉ, VÉRIFICATION DANS LA TABLE ETUDIANT (email OU login)
        if (!$user_found) {
            $sql_etudiant = "SELECT
                            u.id_util, u.login_util, u.mdp_util,
                            et.nom_etu AS nom, et.prenoms_etu AS prenom, et.email_etu AS email,
                            gu.lib_GU AS role, gu.id_GU AS role_id
                          FROM utilisateur u
                          JOIN etudiant et ON u.id_util = et.fk_id_util
                          JOIN posseder pos ON u.id_util = pos.fk_id_util
                          JOIN groupe_utilisateur gu ON pos.fk_id_GU = gu.id_GU
                          WHERE et.email_etu = :identifier OR u.login_util = :identifier2";
            $stmt = $pdo->prepare($sql_etudiant);
            $stmt->bindParam(':identifier', $identifier, PDO::PARAM_STR);
            $stmt->bindParam(':identifier2', $identifier, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                $user_found = true;
                $user_data = $user;
                $user_type = 'etudiant';
            }
        }

        // Si aucun utilisateur n'est trouvé avec l'identifiant
        if (!$user_found) {
            handleFailedLoginAttempt($pdo, $ip_address, $identifier); // Enregistrer la tentative échouée
            $_SESSION['login_error'] = "Identifiant/email ou mot de passe incorrect.";
            redirect('loginForm.php');
        }

        // --- Vérification du mot de passe ---
        $password_valid = false;

        // Priorité aux mots de passe hachés dans la BD
        if (!empty($user_data['mdp_util'])) {
            if (password_verify($password, $user_data['mdp_util'])) {
                $password_valid = true;
            } else {
                // Fallback pour compatibilité avec anciens hash SHA256 si utilisés
                $hashed_password_input = hash('sha256', $password);
                $password_valid = ($hashed_password_input === $user_data['mdp_util']);
            }
        } else {
            // MOTS DE PASSE DE TEST (À SUPPRIMER EN PRODUCTION)
            // Utilisé uniquement si mdp_util est vide, pour les comptes de test manuels
            $test_passwords = [
                'brouKoua2004@gmail.com' => 'enseignant123',
                'yahchrist@gmail.com' => 'secretaire123',
                'seriMar@gmail.com' => 'communication123'
            ];

            $test_email = $user_data['email'] ?? '';
            if (isset($test_passwords[$test_email]) && $test_passwords[$test_email] === $password) {
                $password_valid = true;
            }
        }

        // Si le mot de passe est invalide
        if (!$password_valid) {
            handleFailedLoginAttempt($pdo, $ip_address, $identifier); // Enregistrer la tentative échouée
            $_SESSION['login_error'] = "Mot de passe incorrect.";
            redirect('loginForm.php');
        }

        // --- Authentification réussie ---
        clearFailedAttempts($pdo, $ip_address); // Effacer les tentatives échouées pour cette IP
        unset($_SESSION['attempts_remaining']); // Nettoyer les compteurs de tentatives
        unset($_SESSION['account_blocked']); // S'assurer que le statut de blocage est effacé
        unset($_SESSION['block_time_remaining']); // S'assurer que le temps de blocage est effacé

        // Création des variables de session
        $_SESSION['loggedin'] = TRUE;
        $_SESSION['id_util'] = $user_data['id_util'];
        $_SESSION['login_util'] = $user_data['login_util'];
        $_SESSION['nom_prenom'] = $user_data['prenom'] . ' ' . $user_data['nom'];
        $_SESSION['role'] = $user_data['role'];
        $_SESSION['role_id'] = $user_data['role_id'];
        $_SESSION['user_type'] = $user_type;
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['login_time'] = time();

        // Informations spécifiques pour le personnel administratif
        if ($user_type === 'personnel_admin' && isset($user_data['poste'])) {
            $_SESSION['poste'] = $user_data['poste'];
        }

        // Mise à jour de la dernière activité de l'utilisateur dans la base de données
        $update_sql = "UPDATE utilisateur SET last_activity = NOW() WHERE id_util = :id_util";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':id_util', $user_data['id_util'], PDO::PARAM_INT);
        $update_stmt->execute();

        // --- Système de redirection basé sur le rôle ---
        $redirect_url = 'main.php'; // Page par défaut

        switch ($user_data['role']) {
            case 'Secrétaire':
                $redirect_url = 'dashboard_secretaire.php';
                break;
            case 'Responsable scolarité':
                $redirect_url = 'dashboard_scolarite.php';
                break;
            case 'Chargé de communication':
                $redirect_url = 'dashboard_communication.php';
                break;
            case 'Responsable de filière':
                $redirect_url = 'dashHome.php'; // Correction selon votre fichier précédent
                break;
            case 'Responsable de niveau':
                $redirect_url = 'dashboard_niveau.php';
                break;
            case 'Enseignant':
                $redirect_url = 'dashboard_enseignant.php';
                break;
            case 'Etudiant':
                $redirect_url = 'informations_personnelles.php';
                break;
            case 'Doyen':
                $redirect_url = 'dashboard_doyen.php';
                break;
            case 'Commission de validation':
                $redirect_url = 'dashboard_commission.php';
                break;
            default:
                $redirect_url = 'main.php';
        }

        // Message de succès pour l'utilisateur
        $_SESSION['success_message'] = "Connexion réussie ! Bienvenue " . $_SESSION['nom_prenom'];

        // Redirection finale
        redirect($redirect_url);

    } catch (\PDOException $e) {
        // Gérer les erreurs de base de données pendant le processus de login
        $_SESSION['login_error'] = "Une erreur technique s'est produite. Veuillez réessayer.";
        error_log("Erreur PDO dans process_login.php: " . $e->getMessage()); // Log l'erreur pour le débogage
        redirect('loginForm.php');
    }

} else {
    // Si le script est accédé directement sans soumission de formulaire POST
    $_SESSION['login_error'] = "Accès non autorisé.";
    redirect('loginForm.php');
}
?>