<?php
// forgot_password.php
session_start();

// Récupération des messages
$message = $_SESSION['reset_message'] ?? '';
$error   = $_SESSION['reset_error']   ?? '';
unset($_SESSION['reset_message'], $_SESSION['reset_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS – Réinitialisation du mot de passe</title>
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

        <form action="process_forgot_password.php" method="POST">
            <input type="hidden" name="step" value="identify">

            <div class="form-group">
                <label for="identifier" class="form-label">
                    <i class="fas fa-user"></i>
                    Email ou Identifiant
                </label>
                <input 
                    type="text" 
                    id="identifier" 
                    name="identifier" 
                    class="form-input" 
                    placeholder="Entrez votre email ou identifiant"
                    required
                >
            </div>

            <button type="submit" class="submit-button">
                <i class="fas fa-envelope"></i>
                Réinitialiser mon mot de passe
            </button>
        </form>
    </div>
</body>
</html>
