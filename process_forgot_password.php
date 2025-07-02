<?php
// process_forgot_password.php
session_start();
require_once 'config.php';           // pour $pdo
require 'vendor/autoload.php';      // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['step'] ?? '') !== 'identify') {
    $_SESSION['reset_error'] = "Accès non autorisé.";
    header("Location: forgot_password.php");
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
if ($identifier === '') {
    $_SESSION['reset_error'] = "Veuillez renseigner votre email ou identifiant.";
    header("Location: forgot_password.php");
    exit;
}

// Même fonction findUserByIdentifier() qu’avant...
function findUserByIdentifier(PDO $pdo, string $ident): ?array {
    $sqls = [
        "SELECT u.id_util, u.login_util, p.email_pers AS email, CONCAT(p.prenoms_pers,' ',p.nom_pers) AS name
         FROM utilisateur u
         JOIN personnel_admin p ON u.id_util = p.fk_id_util
         WHERE p.email_pers = :id OR u.login_util = :id2",
        "SELECT u.id_util, u.login_util, e.email_ens AS email, CONCAT(e.prenom_ens,' ',e.nom_ens) AS name
         FROM utilisateur u
         JOIN enseignant e ON u.id_util = e.fk_id_util
         WHERE e.email_ens = :id OR u.login_util = :id2",
        "SELECT u.id_util, u.login_util, et.email_etu AS email, CONCAT(et.prenoms_etu,' ',et.nom_etu) AS name
         FROM utilisateur u
         JOIN etudiant et ON u.id_util = et.fk_id_util
         WHERE et.email_etu = :id OR u.login_util = :id2",
    ];
    foreach ($sqls as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $ident, ':id2' => $ident]);
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $user;
        }
    }
    return null;
}

$user = findUserByIdentifier($pdo, $identifier);
if (!$user) {
    $_SESSION['reset_error'] = "Aucun compte trouvé pour cet identifiant.";
    header("Location: forgot_password.php");
    exit;
}

// Génération du token
$token   = bin2hex(random_bytes(16));
$expires = date('Y-m-d H:i:s', time() + 3600);
$sql     = "INSERT INTO password_resets (fk_id_util, token, expires_at)
            VALUES (:uid, :t, :exp)";
$stmt    = $pdo->prepare($sql);
$stmt->execute([
    ':uid' => $user['id_util'],
    ':t'   => $token,
    ':exp' => $expires
]);

// Envoi avec PHPMailer
$mail = new PHPMailer(true);
try {
    // ===== CONFIG SMTP =====
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sokotyothniel@gmail.com';
    $mail->Password   = 'ttgb gfju sfvs gntu';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // ===== DÉSACTIVER LA VÉRIFICATION SSL EN LOCAL =====
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true
        ]
    ];

    // ===== DESTINATAIRE & CONTENU =====
    $mail->setFrom('sokotyothniel@gmail.com', 'SYGECOS');
    $mail->addAddress($user['email'], $user['name']);
    $mail->isHTML(false);
    $mail->Subject = 'Reinitialisation de votre mot de passe SYGECOS';
    $link = "http://localhost/SYGECOS/reset_password.php?token=" . urlencode($token);
    $mail->Body = <<<TXT
Bonjour {$user['name']},

Vous avez demandé la réinitialisation de votre mot de passe.  
Cliquez sur ce lien (valable 1 heure) pour choisir un nouveau mot de passe :

$link

Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e‐mail.

Cordialement,  
L’équipe SYGECOS
TXT;

    $mail->send();
    $_SESSION['reset_message'] = "Un email de réinitialisation a été envoyé à " . htmlspecialchars($user['email']);
} catch (Exception $e) {
    error_log("Erreur mail reset: " . $mail->ErrorInfo);
    $_SESSION['reset_error'] = "Échec lors de l'envoi de l'email. Veuillez réessayer plus tard.";
}

header("Location: forgot_password.php");
exit;
