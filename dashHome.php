<?php
// main.php
session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    header('Location: login.php');
    exit;
}

// Récupérer les informations utilisateur
$user_name = $_SESSION['nom_prenom'] ?? 'Utilisateur';
$user_role = $_SESSION['role'] ?? 'Utilisateur';
$user_type = $_SESSION['user_type'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashHome_style.css">
</head>
<body>
<div class="admin-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1 class="dashboard-title">Tableau de bord</h1>
                    <p class="dashboard-subtitle">Vue d'ensemble de la plateforme SYGECOS</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Utilisateurs actifs</div>
                            <div class="stat-icon users"><i class="fas fa-users"></i></div>
                        </div>
                        <div class="stat-value">248</div>
                        <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +12% ce mois</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Rapports déposés</div>
                            <div class="stat-icon reports"><i class="fas fa-file-alt"></i></div>
                        </div>
                        <div class="stat-value">342</div>
                        <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +8% ce mois</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">En attente</div>
                            <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                        </div>
                        <div class="stat-value">23</div>
                        <div class="stat-change negative"><i class="fas fa-arrow-down"></i> -15% ce mois</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Soutenances validées</div>
                            <div class="stat-icon completed"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="stat-value">98</div>
                        <div class="stat-change positive"><i class="fas fa-arrow-up"></i> +25% ce mois</div>
                    </div>
                </div>

                <div class="recent-activity">
                    <div class="activity-header">
                        <h3 class="activity-title">Activité récente</h3>
                        <a href="#" class="activity-view-all">Voir tout</a>
                    </div>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon upload"><i class="fas fa-upload"></i></div>
                            <div>
                                <div class="activity-description"><strong>Jean Dupont</strong> a déposé son rapport de stage</div>
                                <div class="activity-time">Il y a 2 heures</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon validated"><i class="fas fa-check"></i></div>
                            <div>
                                <div class="activity-description"><strong>Commission M2</strong> a validé 5 nouveaux rapports</div>
                                <div class="activity-time">Il y a 4 heures</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon message"><i class="fas fa-envelope"></i></div>
                            <div>
                                <div class="activity-description"><strong>Dr. Martin</strong> a envoyé un message à l'étudiant</div>
                                <div class="activity-time">Il y a 6 heures</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="dashHome.js"></script>
</body>
</html>