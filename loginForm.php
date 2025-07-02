<?php

// loginForm.php
session_start();
require_once 'config.php';

// Récupération du message d'erreur (une seule fois)
$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion – SYGECOS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="loginForm_style.css">  
</head>
<body>
    <!-- WRAPPER POUR CENTRER -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-visual">
                <div class="decorative-elements">
                    <div class="floating-shape"></div>
                    <div class="floating-shape"></div>
                    <div class="floating-shape"></div>
                </div>
                <div class="logo-section">
                    <div class="logo-icon">
                        <img src="WhatsApp Image 2025-05-15 à 00.54.47_42b83ab0.jpg" alt="SYGECOS Logo">
                    </div>
                    <h1 class="brand-text">SYGECOS</h1>
                    <p class="brand-subtitle">
                        Plateforme de Gestion<br>
                        des Soutenances M2<br>
                        UFR Mathématiques et Informatique
                    </p>
                </div>
            </div>

            <div class="login-form-container">
                <div class="form-header">
                    <h2 class="form-title">Connexion</h2>
                    <p class="form-subtitle">Connectez-vous avec votre email ou identifiant</p>
                </div>


                <?php if ($login_error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" action="process_login.php" method="post">

              
                    <div class="form-group">
                        <label for="identifier" class="form-label">Email ou Identifiant</label>
                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            class="form-input"
                            placeholder="Entrez votre email ou identifiant"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="password-container">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="Entrez votre mot de passe"
                                required
                            >

                            <button type="button" class="password-toggle" onclick="togglePassword()">

                          
                                <i class="fas fa-eye" id="toggle-icon"></i>
                            </button>
                        </div>
                        <div class="forgot-password">
                            <a href="forgot_password.php">Mot de passe oublié ?</a>
                        </div>
                    </div>


                    <button type="submit" class="login-button">

                  
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                setTimeout(() => {
                    group.style.opacity = '1';
                }, 100 * (index + 1));
            });

            document.getElementById('identifier').focus();
        });    
    </script>
</body>
</html>
