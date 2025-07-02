<?php
session_start();

$step = $_GET['step'] ?? 'identify';
$message = '';
$error = '';

if (isset($_SESSION['reset_message'])) {
    $message = $_SESSION['reset_message'];
    unset($_SESSION['reset_message']);
}

if (isset($_SESSION['reset_error'])) {
    $error = $_SESSION['reset_error'];
    unset($_SESSION['reset_error']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Réinitialisation du mot de passe</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel = "stylesheet" href="forgot_password_style.css">
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

        <?php if ($step == 'identify'): ?>
            <!-- Étape 1: Identification -->
            <div class="step-indicator">
                <div class="step active">1</div>
                <div class="step inactive">2</div>
                <div class="step inactive">3</div>
            </div>

            <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="process_forgot_password.php" method="POST">
                <input type="hidden" name="step" value="identify">
                
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

                <button type="submit" class="submit-button">
                    <i class="fas fa-search"></i>
                    Rechercher mon compte
                </button>
            </form>

        <?php elseif ($step == 'security'): ?>
            <!-- Étape 2: Questions de sécurité -->
            <div class="step-indicator">
                <div class="step completed">1</div>
                <div class="step active">2</div>
                <div class="step inactive">3</div>
            </div>

            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="questions-container active">
                <form action="process_forgot_password.php" method="POST">
                    <input type="hidden" name="step" value="security">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['reset_user_id'] ?? ''); ?>">
                    
                    <p style="margin-bottom: var(--space-6); color: var(--primary-600); text-align: center; font-size: 0.9rem;">
                        Répondez aux questions de sécurité pour vérifier votre identité
                    </p>

                    <?php 
                    // Simuler les questions de sécurité pour la démo
                    $security_questions = [
                        ['id' => 1, 'question' => 'Quel est le nom de votre premier animal de compagnie ?'],
                        ['id' => 4, 'question' => 'Quel était le nom de votre école primaire ?']
                    ];
                    
                    foreach ($security_questions as $index => $question): 
                    ?>
                        <div class="question-item">
                            <div class="question-text">
                                <?php echo htmlspecialchars($question['question']); ?>
                            </div>
                            <input 
                                type="hidden" 
                                name="question_ids[]" 
                                value="<?php echo $question['id']; ?>"
                            >
                            <input 
                                type="text" 
                                name="answers[]" 
                                class="form-input" 
                                placeholder="Votre réponse..."
                                required
                                style="margin-top: var(--space-2);"
                            >
                        </div>
                    <?php endforeach; ?>

                    <div class="divider"></div>

                    <div class="form-group">
                        <label for="email_verification" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email de vérification
                        </label>
                        <input 
                            type="email" 
                            id="email_verification" 
                            name="email_verification" 
                            class="form-input" 
                            placeholder="Confirmez votre adresse email"
                            required
                        >
                        <small style="color: var(--primary-500); font-size: 0.8rem; margin-top: var(--space-1); display: block;">
                            Un email de vérification sera envoyé à cette adresse
                        </small>
                    </div>

                    <button type="submit" class="submit-button">
                        <i class="fas fa-check"></i>
                        Vérifier mes réponses
                    </button>
                </form>
            </div>

        <?php elseif ($step == 'verified'): ?>
            <!-- Étape 3: Compte vérifié, attente du nouveau mot de passe -->
            <div class="step-indicator">
                <div class="step completed">1</div>
                <div class="step completed">2</div>
                <div class="step active">3</div>
            </div>

            <div class="message success">
                <i class="fas fa-check-circle"></i>
                Vérification réussie ! Un email de confirmation a été envoyé.
            </div>

            <p style="text-align: center; color: var(--primary-600); margin-bottom: var(--space-6);">
                Cliquez sur le bouton ci-dessous pour définir votre nouveau mot de passe.
            </p>

            <button type="button" class="submit-button" onclick="showPasswordModal()">
                <i class="fas fa-key"></i>
                Définir un nouveau mot de passe
            </button>

        <?php endif; ?>
    </div>

    <!-- Modal pour le nouveau mot de passe -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Nouveau mot de passe</h3>
            
            <form id="newPasswordForm" action="process_forgot_password.php" method="POST">
                <input type="hidden" name="step" value="reset">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['reset_user_id'] ?? ''); ?>">
                
                <div class="form-group">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-input" 
                            placeholder="Entrez votre nouveau mot de passe"
                            required
                            minlength="8"
                            onkeyup="checkPasswordStrength()"
                        >
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password', 'toggle-icon-new')">
                            <i class="fas fa-eye" id="toggle-icon-new"></i>
                        </button>
                    </div>
                    <div id="password-strength" class="password-strength" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Confirmez votre nouveau mot de passe"
                            required
                            onkeyup="checkPasswordMatch()"
                        >
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', 'toggle-icon-confirm')">
                            <i class="fas fa-eye" id="toggle-icon-confirm"></i>
                        </button>
                    </div>
                    <div id="password-match" style="margin-top: var(--space-2); font-size: 0.8rem; display: none;"></div>
                </div>

                <button type="submit" class="submit-button" id="submitPasswordBtn" disabled>
                    <i class="fas fa-save"></i>
                    Enregistrer le nouveau mot de passe
                </button>
            </form>
        </div>
    </div>

    <script>
        // Fonction pour afficher le modal
        function showPasswordModal() {
            document.getElementById('passwordModal').classList.add('show');
            document.getElementById('new_password').focus();
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        // Fonction pour basculer la visibilité du mot de passe
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Vérification de la force du mot de passe
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('password-strength');
            const submitBtn = document.getElementById('submitPasswordBtn');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            strengthDiv.style.display = 'block';
            
            let score = 0;
            let feedback = [];
            
            // Critères de validation
            if (password.length >= 8) score++;
            else feedback.push('au moins 8 caractères');
            
            if (/[a-z]/.test(password)) score++;
            else feedback.push('une lettre minuscule');
            
            if (/[A-Z]/.test(password)) score++;
            else feedback.push('une lettre majuscule');
            
            if (/[0-9]/.test(password)) score++;
            else feedback.push('un chiffre');
            
            if (/[^a-zA-Z0-9]/.test(password)) score++;
            else feedback.push('un caractère spécial');
            
            // Affichage du niveau de sécurité
            if (score < 3) {
                strengthDiv.className = 'password-strength weak';
                strengthDiv.innerHTML = `<i class="fas fa-times-circle"></i> Faible - Ajoutez: ${feedback.join(', ')}`;
            } else if (score < 5) {
                strengthDiv.className = 'password-strength medium';
                strengthDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Moyen - Améliorez: ${feedback.join(', ')}`;
            } else {
                strengthDiv.className = 'password-strength strong';
                strengthDiv.innerHTML = '<i class="fas fa-check-circle"></i> Fort - Mot de passe sécurisé';
            }
            
            // Vérifier la correspondance des mots de passe
            checkPasswordMatch();
        }

        // Vérification de la correspondance des mots de passe
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            const submitBtn = document.getElementById('submitPasswordBtn');
            
            if (confirmPassword.length === 0) {
                matchDiv.style.display = 'none';
                submitBtn.disabled = true;
                return;
            }
            
            matchDiv.style.display = 'block';
            
            if (password === confirmPassword && password.length >= 8) {
                matchDiv.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-500);"></i> Les mots de passe correspondent';
                matchDiv.style.color = 'var(--success-500)';
                
                // Vérifier aussi la force du mot de passe
                const strengthDiv = document.getElementById('password-strength');
                if (strengthDiv.classList.contains('strong') || strengthDiv.classList.contains('medium')) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            } else {
                matchDiv.innerHTML = '<i class="fas fa-times-circle" style="color: var(--error-500);"></i> Les mots de passe ne correspondent pas';
                matchDiv.style.color = 'var(--error-500)';
                submitBtn.disabled = true;
            }
        }

        // Auto-focus sur le premier champ lors du chargement
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"], input[type="email"]');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Gestion de la soumission du formulaire de nouveau mot de passe
        document.getElementById('newPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Les mots de passe ne correspondent pas.');
                return;
            }
            
            if (password.length < 8) {
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return;
            }
            
            // Simuler la soumission réussie
            this.submit();
        });
    </script>
</body>
</html>
            <h1 class="title">Réinitialisation</h1>
            <p class="subtitle">Récupérez l'accès à votre compte SYGECOS</p>