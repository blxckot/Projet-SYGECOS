<?php
session_start();
// Inclut la connexion PDO et les constantes / fonctions de config
require_once 'config.php';

// Redirection si accès direct
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['login_error'] = "Accès non autorisé.";
    header("Location: loginForm.php");
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password   = trim($_POST['password']   ?? '');

// Validation basique
if ($identifier === '' || $password === '') {
    $_SESSION['login_error'] = "Veuillez remplir tous les champs.";
    header("Location: loginForm.php");
    exit;
}

$user_found     = false;
$user_data      = [];
$user_type      = '';
$password_valid = false;

try {
    // 1) Personnel administratif
    $sql_admin = "SELECT
                    u.id_util,
                    u.login_util,
                    u.mdp_util,
                    p.nom_pers      AS nom,
                    p.prenoms_pers  AS prenom,
                    p.email_pers    AS email,
                    p.poste,
                    gu.lib_GU       AS role,
                    gu.id_GU        AS role_id
                  FROM utilisateur u
                  JOIN personnel_admin p      ON u.id_util = p.fk_id_util
                  JOIN posseder pos           ON u.id_util = pos.fk_id_util
                  JOIN groupe_utilisateur gu  ON pos.fk_id_GU = gu.id_GU
                  WHERE p.email_pers  = :identifier
                     OR u.login_util  = :identifier2";
    $stmt = $pdo->prepare($sql_admin);
    $stmt->bindParam(':identifier',  $identifier, PDO::PARAM_STR);
    $stmt->bindParam(':identifier2', $identifier, PDO::PARAM_STR);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_found = true;
        $user_data  = $row;
        $user_type  = 'personnel_admin';
    }

    // 2) Enseignant
    if (! $user_found) {
        $sql_enseignant = "SELECT
                             u.id_util,
                             u.login_util,
                             u.mdp_util,
                             e.nom_ens     AS nom,
                             e.prenom_ens  AS prenom,
                             e.email_ens   AS email,
                             gu.lib_GU     AS role,
                             gu.id_GU      AS role_id
                           FROM utilisateur u
                           JOIN enseignant e         ON u.id_util = e.fk_id_util
                           JOIN posseder pos         ON u.id_util = pos.fk_id_util
                           JOIN groupe_utilisateur gu ON pos.fk_id_GU = gu.id_GU
                           WHERE e.email_ens  = :identifier
                              OR u.login_util = :identifier2";
        $stmt = $pdo->prepare($sql_enseignant);
        $stmt->bindParam(':identifier',  $identifier, PDO::PARAM_STR);
        $stmt->bindParam(':identifier2', $identifier, PDO::PARAM_STR);
        $stmt->execute();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_found = true;
            $user_data  = $row;
            $user_type  = 'enseignant';
        }
    }

    // 3) Étudiant
    if (! $user_found) {
        $sql_etudiant = "SELECT
                           u.id_util,
                           u.login_util,
                           u.mdp_util,
                           et.nom_etu     AS nom,
                           et.prenoms_etu AS prenom,
                           et.email_etu   AS email,
                           gu.lib_GU      AS role,
                           gu.id_GU       AS role_id
                         FROM utilisateur u
                         JOIN etudiant et          ON u.id_util = et.fk_id_util
                         JOIN posseder pos         ON u.id_util = pos.fk_id_util
                         JOIN groupe_utilisateur gu ON pos.fk_id_GU = gu.id_GU
                         WHERE et.email_etu  = :identifier
                            OR u.login_util = :identifier2";
        $stmt = $pdo->prepare($sql_etudiant);
        $stmt->bindParam(':identifier',  $identifier, PDO::PARAM_STR);
        $stmt->bindParam(':identifier2', $identifier, PDO::PARAM_STR);
        $stmt->execute();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_found = true;
            $user_data  = $row;
            $user_type  = 'etudiant';
        }
    }

    // Vérification du mot de passe
    if ($user_found && ! empty($user_data['mdp_util'])) {
        // Hash moderne
        if (password_verify($password, $user_data['mdp_util'])) {
            $password_valid = true;
        } else {
            // Fallback SHA256 si ancien format
            $password_valid = (hash('sha256', $password) === $user_data['mdp_util']);
        }
    } elseif ($user_found) {
        // Comptes sans hash : mots de passe de test
        $test_pw = [
            'brouKoua2004@gmail.com' => 'enseignant123',
            'yahchrist@gmail.com'     => 'secretaire123',
            'seriMar@gmail.com'       => 'communication123',
            'jamilL@gmail.com'           => 'chiepete'
        ];
        $mail = $user_data['email'] ?? '';
        if (isset($test_pw[$mail]) && $test_pw[$mail] === $password) {
            $password_valid = true;
        }
    }

    // Échec simple
    if (! $user_found || ! $password_valid) {
        $_SESSION['login_error'] = "Identifiant ou mot de passe incorrect.";
        header("Location: loginForm.php");
        exit;
    }

    // Succès : on bâtit la session
    $_SESSION['loggedin']    = true;
    $_SESSION['id_util']     = $user_data['id_util'];
    $_SESSION['login_util']  = $user_data['login_util'];
    $_SESSION['nom_prenom']  = $user_data['prenom'] . ' ' . $user_data['nom'];
    $_SESSION['role']        = $user_data['role'];
    $_SESSION['role_id']     = $user_data['role_id'];
    $_SESSION['user_type']   = $user_type;
    $_SESSION['email']       = $user_data['email'];
    $_SESSION['login_time']  = time();
    if ($user_type === 'personnel_admin' && isset($user_data['poste'])) {
        $_SESSION['poste'] = $user_data['poste'];
    }

    // Mise à jour dernière activité
    $upd = $pdo->prepare("UPDATE utilisateur SET last_activity = NOW() WHERE id_util = :id");
    $upd->bindParam(':id', $user_data['id_util'], PDO::PARAM_INT);
    $upd->execute();

    // Détermination de la redirection selon le rôle
    $redirect = 'main.php';
    switch ($user_data['role']) {
        case 'Secrétaire':               $redirect = 'dashboard_secretaire.php';     break;
        case 'Responsable scolarité':    $redirect = 'dashboard_scolarite.php';      break;
        case 'Chargé de communication':  $redirect = 'dashboard_communication.php';  break;
        case 'Responsable de filière':   $redirect = 'dashHome.php';                 break;
        case 'Responsable de niveau':    $redirect = 'dashboard_niveau.php';         break;
        case 'Enseignant':               $redirect = 'dashboard_enseignant.php';     break;
        case 'Etudiant':                 $redirect = 'informations_personnelles.php';break;
        case 'Doyen':                    $redirect = 'dashboard_doyen.php';          break;
        case 'Commission de validation': $redirect = 'dashboard_commission.php';     break;
    }

    $_SESSION['success_message'] = "Connexion réussie ! Bienvenue " . $_SESSION['nom_prenom'];
    header("Location: $redirect");
    exit;

} catch (PDOException $e) {
    error_log("Erreur login : " . $e->getMessage());
    $_SESSION['login_error'] = "Une erreur est survenue. Veuillez réessayer.";
    header("Location: loginForm.php");
    exit;
}
 