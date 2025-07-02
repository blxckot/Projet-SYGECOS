<?php
// reset_password.php
session_start();
require_once 'config.php';

// Récupération du token depuis l’URL
$token   = $_GET['token'] ?? '';

// Récupération des messages
$message = $_SESSION['reset_message'] ?? '';
$error   = $_SESSION['reset_error']   ?? '';
unset($_SESSION['reset_message'], $_SESSION['reset_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SYGECOS – Choix du nouveau mot de passe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="forgot_password_style.css">
</head>
<body>
    <a href="loginForm.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Retour à la connexion
    </a>

    <div class="reset-container">
        <div class="header">
            <div class="logo-icon">
                <i class="fas fa-key"></i>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (empty($token)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                Lien de réinitialisation invalide ou manquant.
            </div>
        <?php else: ?>
            <form action="process_reset_password.php" method="POST">
                <input type="hidden" name="step"  value="reset">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="new_password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Nouveau mot de passe
                    </label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-input"
                        placeholder="Entrez votre nouveau mot de passe"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*\W).{8,}"
                        title="Min. 8 car., dont 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Confirmer le mot de passe
                    </label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-input"
                        placeholder="Confirmez votre nouveau mot de passe"
                        required
                    >
                </div>

                <button type="submit" class="submit-button">
                    <i class="fas fa-check"></i>
                    Définir le mot de passe
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
