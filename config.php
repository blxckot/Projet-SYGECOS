<?php
// Démarrer la session si elle n'est pas déjà démarrée
// DOIT être la première chose dans le fichier, avant tout autre code PHP ou sortie.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'sygecos');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration de l'application
define('APP_NAME', 'SYGECOS');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/sygecos');

// Configuration des sessions
define('SESSION_TIMEOUT', 3600); // 1 heure en secondes
try {
    // Connexion à la base de données avec PDO
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Afficher les erreurs PDO
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Récupérer les résultats sous forme de tableau associatif
            PDO::ATTR_EMULATE_PREPARES => false, // Désactiver l'émulation des requêtes préparées pour une meilleure sécurité
        ]
    );
} catch (PDOException $e) {
    // En cas d'échec de connexion, arrêter le script et afficher un message d'erreur
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// --- Fonctions utilitaires ---

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === TRUE;
}

// Fonction pour vérifier le rôle de l'utilisateur
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Fonction pour rediriger vers une URL donnée
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Fonction pour nettoyer les données d'entrée (sécurité)
function cleanInput($data) {
    $data = trim($data); // Supprime les espaces en début et fin de chaîne
    $data = stripslashes($data); // Supprime les antislashs
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Convertit les caractères spéciaux en entités HTML
    return $data;
}

// Fonction pour générer un token CSRF (Cross-Site Request Forgery)
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token aléatoire
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier un token CSRF
function verifyCSRFToken($token) {
    // Utilise hash_equals pour une comparaison sécurisée des chaînes de caractères
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// --- Fonctions de gestion des tentatives de connexion ---

// Fonction pour enregistrer une tentative échouée et gérer le blocage
function handleFailedLoginAttempt($pdo, $ip_address, $identifier = null) {
    // Nettoyer les anciennes tentatives (plus d'une heure pour éviter l'accumulation inutile)
    $clean_sql = "DELETE FROM login_attempts WHERE ip_address = :ip AND attempt_time < (NOW() - INTERVAL 1 HOUR)";
    $clean_stmt = $pdo->prepare($clean_sql);
    $clean_stmt->bindParam(':ip', $ip_address);
    $clean_stmt->execute();

    // Enregistrer la tentative échouée actuelle
    $insert_sql = "INSERT INTO login_attempts (ip_address, identifier, attempt_time) VALUES (:ip, :identifier, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->bindParam(':ip', $ip_address);
    $insert_stmt->bindValue(':identifier', $identifier ?? 'unknown'); // Identifier peut être null
    $insert_stmt->execute();

    // Recompter les tentatives récentes pour cette IP
    $current_attempts = getRecentAttemptsCount($pdo, $ip_address, LOCKOUT_TIME);

    // Mettre à jour les variables de session pour le formulaire
    $_SESSION['attempts_remaining'] = MAX_LOGIN_ATTEMPTS - $current_attempts;

    if ($current_attempts >= MAX_LOGIN_ATTEMPTS) {
        // Obtenir la dernière tentative pour calculer le temps de blocage
        $last_attempt_time_sql = "SELECT MAX(attempt_time) as last_attempt FROM login_attempts WHERE ip_address = :ip";
        $last_attempt_time_stmt = $pdo->prepare($last_attempt_time_sql);
        $last_attempt_time_stmt->bindParam(':ip', $ip_address);
        $last_attempt_time_stmt->execute();
        $last_attempt_result = $last_attempt_time_stmt->fetch();
        $last_attempt_timestamp = strtotime($last_attempt_result['last_attempt']);

        $block_end_time = $last_attempt_timestamp + LOCKOUT_TIME;
        $time_remaining = $block_end_time - time();

        if ($time_remaining > 0) {
            $_SESSION['account_blocked'] = true;
            $_SESSION['block_time_remaining'] = $time_remaining;
        } else {
            // Le temps de blocage est écoulé, on réinitialise
            clearFailedAttempts($pdo, $ip_address);
            unset($_SESSION['account_blocked']);
            unset($_SESSION['block_time_remaining']);
            unset($_SESSION['attempts_remaining']);
        }
    }
}

// Fonction pour obtenir le nombre de tentatives récentes pour une IP
function getRecentAttemptsCount($pdo, $ip_address, $timeframe_seconds) {
    $count_sql = "SELECT COUNT(*) as attempt_count
                  FROM login_attempts
                  WHERE ip_address = :ip AND attempt_time > (NOW() - INTERVAL :timeframe SECOND)";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->bindParam(':ip', $ip_address);
    $count_stmt->bindParam(':timeframe', $timeframe_seconds, PDO::PARAM_INT);
    $count_stmt->execute();
    $result = $count_stmt->fetch();
    return $result['attempt_count'];
}


// Fonction pour effacer toutes les tentatives échouées pour une IP (après une connexion réussie ou expiration du blocage)
function clearFailedAttempts($pdo, $ip_address) {
    $clear_sql = "DELETE FROM login_attempts WHERE ip_address = :ip";
    $clear_stmt = $pdo->prepare($clear_sql);
    $clear_stmt->bindParam(':ip', $ip_address);
    $clear_stmt->execute();
}

// Fonction pour vérifier si une IP est actuellement bloquée
function isIPBlocked($pdo, $ip_address) {
    $current_attempts = getRecentAttemptsCount($pdo, $ip_address, LOCKOUT_TIME);

    if ($current_attempts >= MAX_LOGIN_ATTEMPTS) {
        // Obtenir la dernière tentative pour calculer le temps restant
        $last_attempt_time_sql = "SELECT MAX(attempt_time) as last_attempt FROM login_attempts WHERE ip_address = :ip";
        $last_attempt_time_stmt = $pdo->prepare($last_attempt_time_sql);
        $last_attempt_time_stmt->bindParam(':ip', $ip_address);
        $last_attempt_time_stmt->execute();
        $last_attempt_result = $last_attempt_time_stmt->fetch();
        $last_attempt_timestamp = strtotime($last_attempt_result['last_attempt']);

        $block_end_time = $last_attempt_timestamp + LOCKOUT_TIME;
        $time_remaining = $block_end_time - time();

        if ($time_remaining > 0) {
            $_SESSION['block_time_remaining'] = $time_remaining;
            $_SESSION['account_blocked'] = true;
            return true; // L'IP est bloquée
        } else {
            // Le temps de blocage est écoulé, effacer les tentatives et réinitialiser la session
            clearFailedAttempts($pdo, $ip_address);
            unset($_SESSION['account_blocked']);
            unset($_SESSION['block_time_remaining']);
            unset($_SESSION['attempts_remaining']);
            return false; // L'IP n'est plus bloquée
        }
    } else {
        $_SESSION['attempts_remaining'] = MAX_LOGIN_ATTEMPTS - $current_attempts;
        unset($_SESSION['account_blocked']); // S'assurer que le statut bloqué est faux
        unset($_SESSION['block_time_remaining']); // S'assurer que le temps est effacé
        return false; // L'IP n'est pas bloquée
    }
}


// Assurez-vous que la table `login_attempts` existe
// C'est mieux de l'exécuter ici pour garantir son existence à chaque chargement de config.
$create_table_sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    identifier VARCHAR(255),
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempt_time)
)";
$pdo->exec($create_table_sql);

?>