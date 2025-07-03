<?php
// Démarre la session pour utiliser les variables de session.
session_start();

// --- VÉRIFICATION DE LA CONNEXION ---
// On vérifie si l'utilisateur est bien connecté et si son rôle est 'etudiant'.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE || $_SESSION['user_type'] !== 'etudiant' || !isset($_SESSION['id_util'])) {
    // Si l'une de ces conditions n'est pas remplie, on le redirige vers la page de connexion.
    header('Location: loginForm.php');
    exit;
}

// --- CONNEXION À LA BASE DE DONNÉES ---
$host = '127.0.0.1';
$db   = 'sygecos';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- RÉCUPÉRATION DU NUMÉRO D'ÉTUDIANT (num_etu) ---
try {
    $stmt_num_etu = $pdo->prepare("SELECT num_etu FROM etudiant WHERE fk_id_util = ?");
    $stmt_num_etu->execute([$_SESSION['id_util']]);
    $result = $stmt_num_etu->fetch();

    if (!$result || !isset($result['num_etu'])) {
        session_destroy();
        header('Location: loginForm.php?error=datainconsistency');
        exit;
    }
    $num_etu = $result['num_etu'];

} catch (\PDOException $e) {
    die("Erreur lors de la récupération des informations de l'étudiant: " . $e->getMessage());
}

// --- TRAITEMENT DU FORMULAIRE DE MODIFICATION (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire
    $nom = trim($_POST['nom_etu'] ?? '');
    $prenoms = trim($_POST['prenoms_etu'] ?? '');
    $email = trim($_POST['email_etu'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
    $dte_naiss_etu = trim($_POST['dte_naiss_etu'] ?? '');

    // Validation (simple pour l'exemple, à renforcer si nécessaire)
    $errors = [];
    if (empty($nom)) $errors[] = "Le nom est requis.";
    if (empty($prenoms)) $errors[] = "Le prénom est requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'adresse email n'est pas valide.";
    if (empty($dte_naiss_etu)) $errors[] = "La date de naissance est requise.";

    if (empty($errors)) {
        try {
            // Préparer la requête de mise à jour
            $sql = "UPDATE etudiant SET 
                        nom_etu = ?, 
                        prenoms_etu = ?, 
                        email_etu = ?, 
                        telephone = ?, 
                        lieu_naissance = ?, 
                        dte_naiss_etu = ?
                    WHERE num_etu = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $prenoms, $email, $telephone, $lieu_naissance, $dte_naiss_etu, $num_etu]);

            // Mettre à jour le nom dans la session pour qu'il soit affiché correctement partout
            $_SESSION['nom_prenom'] = $prenoms . ' ' . $nom;

            // Message de succès
            $_SESSION['message'] = "Vos informations ont été mises à jour avec succès.";
            $_SESSION['message_type'] = "success";

        } catch (\PDOException $e) {
            $_SESSION['message'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }

    // Rediriger pour éviter la resoumission du formulaire
    header('Location: informations_personnelles.php');
    exit;
}

// --- RÉCUPÉRATION DES DONNÉES ACTUELLES DE L'ÉTUDIANT POUR AFFICHAGE ---
$stmt_etudiant = $pdo->prepare("
    SELECT nom_etu, prenoms_etu, dte_naiss_etu, email_etu, lieu_naissance, telephone, lib_filiere, lib_niv_etu
    FROM etudiant e
    JOIN filiere f ON e.fk_id_filiere = f.id_filiere
    JOIN niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
    WHERE num_etu = ?
");
$stmt_etudiant->execute([$num_etu]);
$etudiant = $stmt_etudiant->fetch();

// Récupérer et effacer les messages de la session
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Mes Informations</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-800: #1e293b; --primary-900: #0f172a;
            --accent-50: #eff6ff; --accent-100: #dbeafe; --accent-600: #2563eb; --accent-700: #1d4ed8;
            --secondary-50: #f0fdf4; --secondary-100: #dcfce7; --secondary-600: #16a34a;
            --error-50: #fef2f2; --error-500: #ef4444; --error-600: #dc2626;
            --white: #ffffff; --gray-50: #f9fafb; --gray-200: #e5e7eb; --gray-300: #d1d5db; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
            --sidebar-width: 280px;
            --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
            --text-sm: 0.875rem; --text-base: 1rem; --text-lg: 1.125rem; --text-xl: 1.25rem; --text-3xl: 1.875rem;
            --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem;
            --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --transition-normal: 250ms ease-in-out;
        }
        body { font-family: var(--font-primary); background-color: var(--gray-50); color: var(--gray-800); margin: 0; }
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .page-content { padding: var(--space-8); max-width: 1000px; margin: 0 auto; }
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); margin-top: var(--space-2); }

        .profile-card { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-8); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); }
        .profile-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--gray-200); padding-bottom: var(--space-4); margin-bottom: var(--space-6); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-weight: 600; color: var(--gray-700); margin-bottom: var(--space-2); font-size: var(--text-sm); }
        .form-group input, .form-group .static-value {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            font-size: var(--text-base);
            transition: all var(--transition-normal);
        }
        .form-group input:read-only, .form-group .static-value { background-color: var(--gray-50); color: var(--gray-600); border-color: var(--gray-200); cursor: not-allowed; }
        .form-group input:not(:read-only):focus { outline: none; border-color: var(--accent-600); box-shadow: 0 0 0 3px var(--accent-100); }
        .form-group .static-value { line-height: 1.5; }

        .form-actions { margin-top: var(--space-8); display: flex; justify-content: flex-end; gap: var(--space-4); }
        .btn { padding: var(--space-3) var(--space-6); border-radius: var(--radius-lg); font-size: var(--text-base); font-weight: 600; cursor: pointer; transition: all var(--transition-normal); border: none; display: inline-flex; align-items: center; gap: var(--space-2); }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover { background-color: var(--accent-700); }
        .btn-secondary { background-color: var(--gray-200); color: var(--gray-800); } .btn-secondary:hover { background-color: var(--gray-300); }

        .alert { padding: var(--space-4); margin-bottom: var(--space-6); border-radius: var(--radius-md); border: 1px solid transparent; font-weight: 500; }
        .alert-success { background-color: var(--secondary-50); color: var(--secondary-600); border-color: var(--secondary-100); }
        .alert-error { background-color: var(--error-50); color: var(--error-600); border-color: #fecaca; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar_etudiant.php'; ?>

        <main class="main-content" id="mainContent">
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title-main">Mes Informations Personnelles</h1>
                    <p class="page-subtitle">Consultez et modifiez vos informations personnelles ici.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <div class="profile-card">
                    <form id="profileForm" method="POST">
                        <div class="profile-header">
                            <h2 style="font-size: var(--text-xl); font-weight:600;">Détails du profil</h2>
                            <div class="form-actions" id="initialActions">
                                <button type="button" class="btn btn-primary" id="editBtn"><i class="fas fa-pencil-alt"></i> Modifier</button>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nom_etu">Nom</label>
                                <input type="text" id="nom_etu" name="nom_etu" value="<?= htmlspecialchars($etudiant['nom_etu']) ?>" readonly required>
                            </div>
                            <div class="form-group">
                                <label for="prenoms_etu">Prénoms</label>
                                <input type="text" id="prenoms_etu" name="prenoms_etu" value="<?= htmlspecialchars($etudiant['prenoms_etu']) ?>" readonly required>
                            </div>
                            <div class="form-group">
                                <label for="email_etu">Adresse Email</label>
                                <input type="email" id="email_etu" name="email_etu" value="<?= htmlspecialchars($etudiant['email_etu']) ?>" readonly required>
                            </div>
                            <div class="form-group">
                                <label for="telephone">Téléphone</label>
                                <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($etudiant['telephone'] ?? '') ?>" readonly>
                            </div>
                             <div class="form-group">
                                <label for="dte_naiss_etu">Date de Naissance</label>
                                <input type="date" id="dte_naiss_etu" name="dte_naiss_etu" value="<?= htmlspecialchars($etudiant['dte_naiss_etu']) ?>" readonly required>
                            </div>
                            <div class="form-group">
                                <label for="lieu_naissance">Lieu de Naissance</label>
                                <input type="text" id="lieu_naissance" name="lieu_naissance" value="<?= htmlspecialchars($etudiant['lieu_naissance'] ?? '') ?>" readonly>
                            </div>
                            <div class="form-group full-width">
                                <label>Filière et Niveau</label>
                                <div class="static-value"><?= htmlspecialchars($etudiant['lib_filiere'] . ' - ' . $etudiant['lib_niv_etu']) ?></div>
                            </div>
                        </div>

                        <div class="form-actions" id="editActions" style="display: none;">
                            <button type="button" class="btn btn-secondary" id="cancelBtn">Annuler</button>
                            <button type="submit" class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profileForm');
            const inputs = form.querySelectorAll('input[name]');
            const editBtn = document.getElementById('editBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const saveBtn = document.getElementById('saveBtn');
            const initialActions = document.getElementById('initialActions');
            const editActions = document.getElementById('editActions');

            let originalValues = {};

            // Store original values
            inputs.forEach(input => {
                originalValues[input.name] = input.value;
            });

            function toggleEditMode(isEditing) {
                inputs.forEach(input => {
                    // Ne pas rendre modifiable les champs qui ne doivent pas l'être
                    if (input.name !== 'filiere_niveau') {
                         input.readOnly = !isEditing;
                    }
                });
                initialActions.style.display = isEditing ? 'none' : 'flex';
                editActions.style.display = isEditing ? 'flex' : 'none';
            }

            editBtn.addEventListener('click', function() {
                toggleEditMode(true);
            });

            cancelBtn.addEventListener('click', function() {
                // Restore original values
                inputs.forEach(input => {
                    input.value = originalValues[input.name];
                });
                toggleEditMode(false);
            });
        });
    </script>
</body>
</html>
