<?php
// process_reset_password.php
session_start();
require_once 'config.php';  // instancie $pdo

// 1. Vérifications initiales
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['step'] ?? '') !== 'reset') {
    $_SESSION['reset_error'] = "Accès non autorisé.";
    header("Location: reset_password.php?token=" . urlencode($_POST['token'] ?? ''));
    exit;
}

$token            = trim($_POST['token']            ?? '');
$new_password     = trim($_POST['new_password']     ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($token === '' || $new_password === '' || $confirm_password === '') {
    $_SESSION['reset_error'] = "Veuillez remplir tous les champs.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['reset_error'] = "Les mots de passe ne correspondent pas.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit;
}

if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*\W).{8,}/', $new_password)) {
    $_SESSION['reset_error'] = "Le mot de passe doit faire au moins 8 caractères, contenir 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit;
}

try {
    // Démarrage de la transaction
    $pdo->beginTransaction();

    // 2. Vérifier et verrouiller le token valide
    $stmt = $pdo->prepare("
        SELECT fk_id_util
          FROM password_resets
         WHERE token = :token
           AND expires_at > NOW()
         FOR UPDATE
    ");
    $stmt->execute([':token' => $token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! $reset) {
        $pdo->rollBack();
        $_SESSION['reset_error'] = "Le lien de réinitialisation est invalide ou expiré.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    $user_id = $reset['fk_id_util'];

    // 3. Mettre à jour le mot de passe
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE utilisateur SET mdp_util = :hash WHERE id_util = :id");
    $upd->execute([
        ':hash' => $new_hash,
        ':id'   => $user_id
    ]);

    if ($upd->rowCount() === 0) {
        // Aucun mot de passe modifié = problème
        $pdo->rollBack();
        $_SESSION['reset_error'] = "Échec de la mise à jour du mot de passe.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    // 4. Supprimer le token pour qu’il ne soit plus réutilisable
    $del = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
    $del->execute([':token' => $token]);

    if ($del->rowCount() === 0) {
        // Impossible de supprimer le token
        $pdo->rollBack();
        $_SESSION['reset_error'] = "Échec de la suppression du token de réinitialisation.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    // Validation de la transaction
    $pdo->commit();

    // 5. Succès
    header("Location: loginForm.php");
    exit;

} catch (PDOException $e) {
    // En cas d’erreur SQL, on annule tout
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("process_reset_password error: " . $e->getMessage());
    $_SESSION['reset_error'] = "Une erreur est survenue. Veuillez réessayer.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit;
}
