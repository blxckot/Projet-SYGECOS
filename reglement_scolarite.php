<?php
// gestion_scolarite.php
require_once 'config.php'; // Assurez-vous que ce fichier inclut votre connexion PDO et les fonctions isLoggedIn/redirect

if (!isLoggedIn()) {
    redirect('loginForm.php'); // Redirige si l'utilisateur n'est pas connecté
}

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        switch ($action) {
            case 'get_paiements_etudiants':
                // Récupérer tous les paiements des étudiants avec leur statut
                // La vue `vue_paiements_etudiants` simplifie grandement cette requête
                $query = "
                    SELECT
                        num_etu, nom_etu, prenoms_etu, annee_libelle,
                        lib_filiere, lib_niv_etu, montant_scolarite_prevu,
                        total_verse, reste_a_payer, id_paiement, id_Ac, id_niv_etu
                    FROM vue_paiements_etudiants
                    ORDER BY annee_libelle DESC, nom_etu ASC, prenoms_etu ASC
                ";

                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $paiements]);
                break;

            case 'initialiser_scolarite':
                $numEtu = $_POST['num_etu'];
                $anneeAcademiqueId = $_POST['annee_academique_id'];
                $niveauId = $_POST['niveau_id'];

                // Vérifier si un enregistrement paiement_scolarite existe déjà pour cet étudiant et cette année
                $stmtCheck = $pdo->prepare("
                    SELECT id_paiement FROM paiement_scolarite
                    WHERE fk_num_etu = ? AND fk_id_Ac = ?
                ");
                $stmtCheck->execute([$numEtu, $anneeAcademiqueId]);
                if ($stmtCheck->fetchColumn()) {
                    throw new Exception("Le dossier de scolarité existe déjà pour cet étudiant et cette année académique.");
                }

                // Récupérer le montant total de scolarité prévu pour cette filière et ce niveau
                $stmtFiliereNiveau = $pdo->prepare("
                    SELECT f.id_filiere, fnd.montant_scolarite_total, fnd.versement_1
                    FROM etudiant e
                    JOIN filiere f ON e.fk_id_filiere = f.id_filiere
                    JOIN niveau_etude ne ON e.fk_id_niv_etu = ne.id_niv_etu
                    JOIN filiere_niveau_detail fnd ON f.id_filiere = fnd.fk_id_filiere AND ne.id_niv_etu = fnd.fk_id_niv_etu
                    WHERE e.num_etu = ? AND ne.id_niv_etu = ?
                ");
                $stmtFiliereNiveau->execute([$numEtu, $niveauId]);
                $detailScolarite = $stmtFiliereNiveau->fetch(PDO::FETCH_ASSOC);

                if (!$detailScolarite) {
                    throw new Exception("Montant de scolarité non défini pour la filière/niveau de cet étudiant. Veuillez configurer `filiere_niveau_detail`.");
                }

                $montantTotalScolarite = $detailScolarite['montant_scolarite_total'];
                $montantPremierVersementPrevu = $detailScolarite['versement_1'];


                // Récupérer le prochain id_paiement
                $stmtMaxPaiement = $pdo->query("SELECT COALESCE(MAX(id_paiement), 0) + 1 as next_id FROM paiement_scolarite");
                $idPaiement = $stmtMaxPaiement->fetch(PDO::FETCH_ASSOC)['next_id'];

                // Insérer l'enregistrement de paiement de scolarité
                $stmtInitialisePaiement = $pdo->prepare("
                    INSERT INTO paiement_scolarite (id_paiement, fk_num_etu, fk_id_Ac, montant_total, total_verse)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtInitialisePaiement->execute([$idPaiement, $numEtu, $anneeAcademiqueId, $montantTotalScolarite, 0.00]);

                // Enregistrer le premier versement si le montant prévu est > 0
                if ($montantPremierVersementPrevu > 0) {
                    $stmtMaxVersement = $pdo->query("SELECT COALESCE(MAX(id_versement), 0) + 1 as next_id FROM versement_scolarite");
                    $idVersement = $stmtMaxVersement->fetch(PDO::FETCH_ASSOC)['next_id'];

                    $stmtAddVersement = $pdo->prepare("
                        INSERT INTO versement_scolarite (id_versement, fk_id_paiement, numero_versement, montant_versement, date_versement, mode_paiement, reference_paiement, commentaire)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtAddVersement->execute([
                        $idVersement,
                        $idPaiement,
                        1, // Toujours le premier versement lors de l'initialisation
                        $montantPremierVersementPrevu,
                        date('Y-m-d'), // Date du jour
                        'especes', // Mode de paiement par défaut
                        null, // Référence par défaut
                        'Premier versement lors de l\'initialisation du dossier'
                    ]);

                    // Mettre à jour le total_verse dans paiement_scolarite
                    $stmtUpdateTotal = $pdo->prepare("
                        UPDATE paiement_scolarite
                        SET total_verse = total_verse + ?
                        WHERE id_paiement = ?
                    ");
                    $stmtUpdateTotal->execute([$montantPremierVersementPrevu, $idPaiement]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Dossier de scolarité initialisé avec succès !']);
                break;

            case 'add_versement':
                $idPaiement = $_POST['id_paiement'];
                $montantVersement = floatval($_POST['montant_versement']);
                $modePaiement = $_POST['mode_paiement'];
                $referencePaiement = trim($_POST['reference_paiement']);
                $commentaire = trim($_POST['commentaire']);

                if ($montantVersement <= 0) {
                    throw new Exception("Le montant du versement doit être positif.");
                }

                // Vérifier le montant restant à payer
                $stmtPaiement = $pdo->prepare("SELECT montant_total, total_verse FROM paiement_scolarite WHERE id_paiement = ?");
                $stmtPaiement->execute([$idPaiement]);
                $paiementInfo = $stmtPaiement->fetch(PDO::FETCH_ASSOC);

                if (!$paiementInfo) {
                    throw new Exception("Paiement de scolarité introuvable.");
                }

                $montantDu = $paiementInfo['montant_total'] - $paiementInfo['total_verse'];
                if ($montantVersement > $montantDu + 0.01) { // Petite tolérance pour les flottants
                    throw new Exception("Le montant du versement (".number_format($montantVersement, 2)." XOF) dépasse le reste à payer (".number_format($montantDu, 2)." XOF).");
                }

                // Récupérer le prochain numéro de versement
                $stmtNextNumVersement = $pdo->prepare("SELECT COALESCE(MAX(numero_versement), 0) + 1 FROM versement_scolarite WHERE fk_id_paiement = ?");
                $stmtNextNumVersement->execute([$idPaiement]);
                $numeroVersement = $stmtNextNumVersement->fetchColumn();

                // Récupérer le prochain id_versement
                $stmtMaxVersement = $pdo->query("SELECT COALESCE(MAX(id_versement), 0) + 1 as next_id FROM versement_scolarite");
                $idVersement = $stmtMaxVersement->fetch(PDO::FETCH_ASSOC)['next_id'];

                // Insérer le nouveau versement
                $stmtAddVersement = $pdo->prepare("
                    INSERT INTO versement_scolarite (id_versement, fk_id_paiement, numero_versement, montant_versement, date_versement, mode_paiement, reference_paiement, commentaire)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtAddVersement->execute([
                    $idVersement,
                    $idPaiement,
                    $numeroVersement,
                    $montantVersement,
                    date('Y-m-d'),
                    $modePaiement,
                    empty($referencePaiement) ? null : $referencePaiement,
                    empty($commentaire) ? null : $commentaire
                ]);

                // Mettre à jour le total_verse dans paiement_scolarite
                $stmtUpdateTotal = $pdo->prepare("
                    UPDATE paiement_scolarite
                    SET total_verse = total_verse + ?
                    WHERE id_paiement = ?
                ");
                $stmtUpdateTotal->execute([$montantVersement, $idPaiement]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Versement enregistré avec succès !']);
                break;

            case 'get_versements_by_paiement_id':
                $idPaiement = $_POST['id_paiement'];
                $stmt = $pdo->prepare("
                    SELECT id_versement, numero_versement, montant_versement, date_versement, mode_paiement, reference_paiement, commentaire
                    FROM versement_scolarite
                    WHERE fk_id_paiement = ?
                    ORDER BY numero_versement ASC
                ");
                $stmt->execute([$idPaiement]);
                $versements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $versements]);
                break;

            default:
                throw new Exception("Action non reconnue.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGECOS - Gestion Scolarité</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* === VARIABLES CSS === */
        :root {
            --primary-50: #f8fafc; --primary-100: #f1f5f9; --primary-200: #e2e8f0; --primary-300: #cbd5e1; --primary-400: #94a3b8; --primary-500: #64748b; --primary-600: #475569; --primary-700: #334155; --primary-800: #1e293b; --primary-900: #0f172a;
            --accent-50: #eff6ff; --accent-100: #dbeafe; --accent-200: #bfdbfe; --accent-300: #93c5fd; --accent-400: #60a5fa; --accent-500: #3b82f6; --accent-600: #2563eb; --accent-700: #1d4ed8; --accent-800: #1e40af; --accent-900: #1e3a8a;
            --secondary-50: #f0fdf4; --secondary-100: #dcfce7; --secondary-500: #22c55e; --secondary-600: #16a34a;
            --success-500: #22c55e; --warning-500: #f59e0b; --error-500: #ef4444; --info-500: #3b82f6;
            --white: #ffffff; --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db; --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
            --sidebar-width: 280px; --sidebar-collapsed-width: 80px; --topbar-height: 70px;
            --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
            --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-lg: 1.125rem; --text-xl: 1.25rem; --text-2xl: 1.5rem; --text-3xl: 1.875rem;
            --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-5: 1.25rem; --space-6: 1.5rem; --space-8: 2rem; --space-10: 2.5rem; --space-12: 3rem; --space-16: 4rem;
            --radius-sm: 0.25rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem; --radius-2xl: 1.5rem; --radius-3xl: 2rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05); --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.05); --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --transition-fast: 150ms ease-in-out; --transition-normal: 250ms ease-in-out; --transition-slow: 350ms ease-in-out;
        }

        /* === RESET === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-primary); background-color: var(--gray-50); color: var(--gray-800); overflow-x: hidden; }

        /* === LAYOUT PRINCIPAL === */
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); transition: margin-left var(--transition-normal); }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-collapsed-width); }

        /* === SIDEBAR === */
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%); color: white; z-index: 1000; transition: all var(--transition-normal); overflow-y: auto; overflow-x: hidden; }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar::-webkit-scrollbar { width: 4px; } .sidebar::-webkit-scrollbar-track { background: var(--primary-900); } .sidebar::-webkit-scrollbar-thumb { background: var(--primary-600); border-radius: 2px; }
        .sidebar-header { padding: var(--space-6); border-bottom: 1px solid var(--primary-700); display: flex; align-items: center; gap: var(--space-3); }
        .sidebar-logo { width: 40px; height: 40px; background: var(--accent-500); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; } .sidebar-logo img { width: 28px; height: 28px; object-fit: contain; filter: brightness(0) invert(1); }
        .sidebar-title { font-size: var(--text-xl); font-weight: 700; white-space: nowrap; opacity: 1; transition: opacity var(--transition-normal); } .sidebar.collapsed .sidebar-title { opacity: 0; }
        .sidebar-nav { padding: var(--space-4) 0; }
        .nav-section { margin-bottom: var(--space-6); }
        .nav-section-title { padding: var(--space-2) var(--space-6); font-size: var(--text-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary-400); white-space: nowrap; opacity: 1; transition: opacity var(--transition-normal); } .sidebar.collapsed .nav-section-title { opacity: 0; }
        .nav-item { margin-bottom: var(--space-1); }
        .nav-link { display: flex; align-items: center; padding: var(--space-3) var(--space-6); color: var(--primary-200); text-decoration: none; transition: all var(--transition-fast); position: relative; gap: var(--space-3); }
        .nav-link:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .nav-link.active { background: var(--accent-600); color: white; }
        .nav-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--accent-300); }
        .nav-icon { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-text { white-space: nowrap; opacity: 1; transition: opacity var(--transition-normal); } .sidebar.collapsed .nav-text { opacity: 0; }
        .nav-submenu { margin-left: var(--space-8); margin-top: var(--space-2); border-left: 2px solid var(--primary-700); padding-left: var(--space-4); } .sidebar.collapsed .nav-submenu { display: none; }
        .nav-submenu .nav-link { padding: var(--space-2) var(--space-4); font-size: var(--text-sm); }

        /* === TOPBAR === */
        .topbar { height: var(--topbar-height); background: var(--white); border-bottom: 1px solid var(--gray-200); padding: 0 var(--space-6); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 100; }
        .topbar-left { display: flex; align-items: center; gap: var(--space-4); }
        .sidebar-toggle { width: 40px; height: 40px; border: none; background: var(--gray-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition-fast); color: var(--gray-600); } .sidebar-toggle:hover { background: var(--gray-200); color: var(--gray-800); }
        .page-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-800); }
        .topbar-right { display: flex; align-items: center; gap: var(--space-4); }
        .topbar-button { width: 40px; height: 40px; border: none; background: var(--gray-100); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition-fast); color: var(--gray-600); position: relative; } .topbar-button:hover { background: var(--gray-200); color: var(--gray-800); }
        .notification-badge { position: absolute; top: -2px; right: -2px; width: 18px; height: 18px; background: var(--error-500); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; color: white; }
        .user-menu { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-3); border-radius: var(--radius-lg); cursor: pointer; transition: background var(--transition-fast); } .user-menu:hover { background: var(--gray-100); }
        .user-info { text-align: right; } .user-name { font-size: var(--text-sm); font-weight: 600; color: var(--gray-800); line-height: 1.2; } .user-role { font-size: var(--text-xs); color: var(--gray-500); }

        /* === PAGE CONTENT === */
        .page-content { padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-8); }
        .page-title-main { font-size: var(--text-3xl); font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-2); }
        .page-subtitle { color: var(--gray-600); font-size: var(--text-lg); }

        .table-container { background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow: hidden; margin-bottom: var(--space-8); }
        .table-header { padding: var(--space-6); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4); }
        .table-title { font-size: var(--text-xl); font-weight: 600; color: var(--gray-900); }
        .table-actions { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
        .search-container { position: relative; }
        .search-input { padding: var(--space-3) var(--space-10) var(--space-3) var(--space-3); border: 1px solid var(--gray-300); border-radius: var(--radius-md); font-size: var(--text-sm); width: 250px; }
        .search-input:focus { outline: none; border-color: var(--accent-500); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .search-icon { position: absolute; right: var(--space-3); top: 50%; transform: translateY(-50%); color: var(--gray-400); pointer-events: none; }

        .table-wrapper { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .data-table th, .data-table td { padding: var(--space-3); text-align: left; border-bottom: 1px solid var(--gray-200); font-size: var(--text-sm); }
        .data-table th { background-color: var(--gray-50); font-weight: 600; color: var(--gray-700); }
        .data-table tbody tr:hover { background-color: var(--gray-50); }
        .data-table td { color: var(--gray-800); }

        .col-num { width: 100px; }
        .col-nom { width: 200px; }
        .col-filiere { width: 120px; }
        .col-niveau { width: 80px; }
        .col-annee { width: 100px; }
        .col-montant { width: 120px; text-align: right; }
        .col-verse { width: 120px; text-align: right; }
        .col-reste { width: 120px; text-align: right; }
        .col-status { width: 100px; text-align: center; }
        .col-actions { width: 150px; text-align: center; }

        .badge { padding: var(--space-1) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-xs); font-weight: 600; display: inline-block; }
        .badge-success { background-color: var(--secondary-100); color: var(--secondary-600); } /* Payé */
        .badge-warning { background-color: #fef3c7; color: #d97706; } /* Partiel */
        .badge-danger { background-color: #fef2f2; color: var(--error-500); } /* Non Payé / Dû */

        .action-buttons { display: flex; gap: var(--space-1); justify-content: center; }
        .btn { padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); font-size: var(--text-sm); font-weight: 600; cursor: pointer; transition: all var(--transition-fast); border: none; display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background-color: var(--accent-600); color: white; } .btn-primary:hover:not(:disabled) { background-color: var(--accent-700); }
        .btn-success { background-color: var(--success-500); color: white; } .btn-success:hover:not(:disabled) { background-color: var(--secondary-600); }
        .btn-info { background-color: var(--info-500); color: white; } .btn-info:hover:not(:disabled) { background-color: var(--accent-700); }

        .alert { padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: none; }
        .alert.success { background-color: var(--secondary-50); color: var(--secondary-600); border: 1px solid var(--secondary-100); }
        .alert.error { background-color: #fef2f2; color: var(--error-500); border: 1px solid #fecaca; }
        .alert.info { background-color: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-200); }

        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-spinner { width: 40px; height: 40px; border: 4px solid var(--gray-300); border-top-color: var(--accent-500); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state { text-align: center; padding: var(--space-16); color: var(--gray-500); }
        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); }

        /* MODAL styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal {
            background: var(--white);
            padding: var(--space-6);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: var(--space-4);
            margin-bottom: var(--space-6);
        }
        .modal-title {
            font-size: var(--text-xl);
            font-weight: 600;
            color: var(--gray-900);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: var(--text-2xl);
            cursor: pointer;
            color: var(--gray-500);
        }
        .modal-body .form-group {
            margin-bottom: var(--space-4);
        }
        .modal-body label {
            display: block;
            margin-bottom: var(--space-2);
            font-weight: 500;
            color: var(--gray-700);
        }
        .modal-body input[type="number"],
        .modal-body input[type="text"],
        .modal-body select,
        .modal-body textarea {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            color: var(--gray-800);
            box-sizing: border-box; /* Important for width: 100% */
        }
        .modal-body input[type="number"]:focus,
        .modal-body input[type="text"]:focus,
        .modal-body select:focus,
        .modal-body textarea:focus {
            outline: none;
            border-color: var(--accent-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3);
            padding-top: var(--space-6);
            border-top: 1px solid var(--gray-200);
            margin-top: var(--space-6);
        }
        .modal-footer .btn {
            padding: var(--space-2) var(--space-4);
            font-size: var(--text-base);
        }

        /* Versement History Modal */
        #versementHistoryModal .modal {
            max-width: 800px;
        }
        #versementHistoryTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-4);
        }
        #versementHistoryTable th, #versementHistoryTable td {
            border: 1px solid var(--gray-200);
            padding: var(--space-2);
            text-align: left;
            font-size: var(--text-sm);
        }
        #versementHistoryTable th {
            background-color: var(--gray-50);
            font-weight: 600;
        }
        #versementHistoryTable tbody tr:nth-child(even) {
            background-color: var(--gray-50);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'sidebar_respo_scolarité.php'; // Assurez-vous que le chemin est correct ?>

        <main class="main-content" id="mainContent">
            <?php include 'topbar.php'; // Assurez-vous que le chemin est correct ?>

            <div class="page-content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title-main">Gestion Scolarité</h1>
                        <p class="page-subtitle">Suivi et gestion des paiements de scolarité des étudiants</p>
                    </div>
                </div>

                <div id="alertMessage" class="alert"></div>

                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-money-check-dollar"></i> Vue d'ensemble des Scolarités
                        </h3>
                        <div class="table-actions">
                            <div class="search-container">
                                <input type="text" id="searchInput" placeholder="Rechercher étudiant, filière..." class="search-input">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button onclick="loadPaiements()" class="btn btn-outline">
                                <i class="fas fa-refresh"></i> Actualiser
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-num">N° Étudiant</th>
                                    <th class="col-nom">Nom & Prénoms</th>
                                    <th class="col-filiere">Filière</th>
                                    <th class="col-niveau">Niveau</th>
                                    <th class="col-annee">Année</th>
                                    <th class="col-montant">Total Scolarité (XOF)</th>
                                    <th class="col-verse">Total Versé (XOF)</th>
                                    <th class="col-reste">Reste à Payer (XOF)</th>
                                    <th class="col-status">Statut</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="paiementsTableBody">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="modal-overlay" id="addVersementModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Versement</h3>
                <button class="modal-close" onclick="closeAddVersementModal()">&times;</button>
            </div>
            <form id="addVersementForm">
                <div class="modal-body">
                    <p>Pour : <strong id="modalStudentNameVersement"></strong> (<span id="modalStudentNumVersement"></span>)</p>
                    <p>Année : <span id="modalAnneeVersement"></span></p>
                    <p>Total dû : <strong id="modalMontantTotalDu"></strong></p>
                    <p>Déjà versé : <strong id="modalTotalVerse"></strong></p>
                    <p>Reste à payer : <strong id="modalResteAPayer"></strong></p>
                    <hr style="margin: var(--space-4) 0; border-color: var(--gray-200);">

                    <input type="hidden" id="modalPaiementId" name="id_paiement">
                    <input type="hidden" id="modalResteAPayerHidden" name="reste_a_payer_value">

                    <div class="form-group">
                        <label for="montant_versement">Montant du versement (XOF) <span style="color: var(--error-500);">*</span></label>
                        <input type="number" id="montant_versement" name="montant_versement" step="0.01" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="mode_paiement">Mode de paiement <span style="color: var(--error-500);">*</span></label>
                        <select id="mode_paiement" name="mode_paiement" required>
                            <option value="especes">Espèces</option>
                            <option value="virement">Virement bancaire</option>
                            <option value="cheque">Chèque</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="carte_bancaire">Carte Bancaire</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reference_paiement">Référence de paiement (Facultatif)</label>
                        <input type="text" id="reference_paiement" name="reference_paiement" placeholder="Ex: N° Chèque, Ref Transaction Mobile Money">
                    </div>
                    <div class="form-group">
                        <label for="commentaire">Commentaire (Facultatif)</label>
                        <textarea id="commentaire" name="commentaire" rows="3" placeholder="Informations additionnelles"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddVersementModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="submitVersementBtn">
                        <i class="fas fa-plus"></i> Enregistrer Versement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="versementHistoryModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Historique des Versements de <span id="historyModalStudentName"></span></h3>
                <button class="modal-close" onclick="closeVersementHistoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Année Académique : <strong id="historyModalAnnee"></strong></p>
                <p>Total Scolarité : <strong id="historyModalTotalScolarite"></strong></p>
                <p>Total Versé : <strong id="historyModalTotalVerse"></strong></p>
                <p>Reste à Payer : <strong id="historyModalResteAPayer"></strong></p>
                <hr style="margin: var(--space-4) 0; border-color: var(--gray-200);">

                <h4>Détails des Versements :</h4>
                <div class="table-wrapper" style="max-height: 300px; overflow-y: auto;">
                    <table id="versementHistoryTable">
                        <thead>
                            <tr>
                                <th>N° Versement</th>
                                <th>Montant (XOF)</th>
                                <th>Date</th>
                                <th>Mode</th>
                                <th>Référence</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody id="versementHistoryTableBody">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeVersementHistoryModal()">Fermer</button>
            </div>
        </div>
    </div>


    <script>
        let allPaiementsData = []; // Stocke toutes les données de scolarité
        const CURRENCY_SYMBOL = ' XOF'; // Symbole de devise

        document.addEventListener('DOMContentLoaded', function() {
            initSidebar();
            loadPaiements();
            initModalHandlers();
        });

        function initModalHandlers() {
            // Fermer les modales en cliquant en dehors
            document.getElementById('addVersementModal').addEventListener('click', function(e) {
                if (e.target === this) closeAddVersementModal();
            });
            document.getElementById('versementHistoryModal').addEventListener('click', function(e) {
                if (e.target === this) closeVersementHistoryModal();
            });

            // Gérer la soumission du formulaire d'ajout de versement
            document.getElementById('addVersementForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const paiementId = document.getElementById('modalPaiementId').value;
                const montantVersement = document.getElementById('montant_versement').value;
                const modePaiement = document.getElementById('mode_paiement').value;
                const referencePaiement = document.getElementById('reference_paiement').value;
                const commentaire = document.getElementById('commentaire').value;
                const resteAPayerValue = parseFloat(document.getElementById('modalResteAPayerHidden').value);

                if (parseFloat(montantVersement) <= 0) {
                    showAlert('Le montant du versement doit être positif.', 'error');
                    return;
                }
                if (parseFloat(montantVersement) > resteAPayerValue + 0.01) { // Tolérance pour les flottants
                     showAlert(`Le montant du versement (${formatCurrency(montantVersement)}) dépasse le reste à payer (${formatCurrency(resteAPayerValue)}).`, 'error');
                     return;
                }


                const submitButton = document.getElementById('submitVersementBtn');
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

                try {
                    const result = await makeAjaxRequest({
                        action: 'add_versement',
                        id_paiement: paiementId,
                        montant_versement: montantVersement,
                        mode_paiement: modePaiement,
                        reference_paiement: referencePaiement,
                        commentaire: commentaire
                    });

                    if (result.success) {
                        showAlert(result.message, 'success');
                        closeAddVersementModal();
                        loadPaiements(); // Recharger les données pour mettre à jour la table
                    } else {
                        showAlert(result.message, 'error');
                    }
                } catch (error) {
                    showAlert('Erreur de connexion lors de l\'enregistrement du versement.', 'error');
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-plus"></i> Enregistrer Versement';
                }
            });
        }

        async function loadPaiements() {
            showLoading(true);
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'get_paiements_etudiants' }).toString()
                });
                const result = await response.json();

                if (result.success) {
                    allPaiementsData = result.data;
                    displayPaiements(allPaiementsData);
                } else {
                    showAlert(result.message || 'Erreur lors du chargement des données de scolarité.', 'error');
                    displayEmptyState('paiementsTableBody', 'Erreur de chargement des données', 10);
                }
            } catch (error) {
                showAlert('Erreur réseau lors du chargement des données de scolarité.', 'error');
                displayEmptyState('paiementsTableBody', 'Erreur de connexion', 10);
            } finally {
                showLoading(false);
            }
        }

        function displayPaiements(paiements) {
            const tbody = document.getElementById('paiementsTableBody');
            tbody.innerHTML = ''; // Nettoyer le contenu existant

            if (paiements.length === 0) {
                displayEmptyState('paiementsTableBody', 'Aucune donnée de scolarité disponible.', 10);
                return;
            }

            paiements.forEach(p => {
                let statusBadge = '';
                let actionsHtml = '';
                let rowColor = '';

                if (p.id_paiement === null) {
                    // Scolarité non initialisée
                    statusBadge = `<span class="badge badge-danger">Non Initialisé</span>`;
                    actionsHtml = `
                        <button onclick="initialiserScolarite('${p.num_etu}', ${p.id_Ac}, ${p.id_niv_etu})" class="btn btn-sm btn-info" title="Initialiser le dossier de scolarité">
                            <i class="fas fa-hand-holding-dollar"></i> Initialiser
                        </button>
                    `;
                    rowColor = 'background-color: var(--gray-100);'; // Pour les différencier
                } else {
                    const resteAPayer = parseFloat(p.reste_a_payer);
                    if (resteAPayer <= 0.01) { // Tolérance pour les flottants
                        statusBadge = `<span class="badge badge-success">Payé</span>`;
                        actionsHtml = `
                            <button onclick="viewVersementHistory(${p.id_paiement}, '${p.nom_etu} ${p.prenoms_etu}', '${p.annee_libelle}', ${p.montant_scolarite_prevu}, ${p.total_verse}, ${p.reste_a_payer})" class="btn btn-sm btn-info" title="Voir l'historique des versements">
                                <i class="fas fa-history"></i> Historique
                            </button>
                        `;
                    } else if (p.total_verse > 0) {
                        statusBadge = `<span class="badge badge-warning">Partiel</span>`;
                        actionsHtml = `
                            <button onclick="addVersement(${p.id_paiement}, '${p.nom_etu} ${p.prenoms_etu}', '${p.annee_libelle}', ${p.montant_scolarite_prevu}, ${p.total_verse}, ${p.reste_a_payer})" class="btn btn-sm btn-primary" title="Ajouter un versement">
                                <i class="fas fa-dollar-sign"></i> Ajouter Versement
                            </button>
                            <button onclick="viewVersementHistory(${p.id_paiement}, '${p.nom_etu} ${p.prenoms_etu}', '${p.annee_libelle}', ${p.montant_scolarite_prevu}, ${p.total_verse}, ${p.reste_a_payer})" class="btn btn-sm btn-info" title="Voir l'historique des versements">
                                <i class="fas fa-history"></i>
                            </button>
                        `;
                    } else {
                        statusBadge = `<span class="badge badge-danger">Impayé</span>`;
                         actionsHtml = `
                            <button onclick="addVersement(${p.id_paiement}, '${p.nom_etu} ${p.prenoms_etu}', '${p.annee_libelle}', ${p.montant_scolarite_prevu}, ${p.total_verse}, ${p.reste_a_payer})" class="btn btn-sm btn-primary" title="Ajouter un versement">
                                <i class="fas fa-dollar-sign"></i> Ajouter Versement
                            </button>
                            <button onclick="viewVersementHistory(${p.id_paiement}, '${p.nom_etu} ${p.prenoms_etu}', '${p.annee_libelle}', ${p.montant_scolarite_prevu}, ${p.total_verse}, ${p.reste_a_payer})" class="btn btn-sm btn-info" title="Voir l'historique des versements">
                                <i class="fas fa-history"></i>
                            </button>
                        `;
                    }
                }

                const row = `
                    <tr style="${rowColor}">
                        <td><strong>${p.num_etu}</strong></td>
                        <td>${p.nom_etu} ${p.prenoms_etu}</td>
                        <td>${p.lib_filiere}</td>
                        <td>${p.lib_niv_etu}</td>
                        <td>${p.annee_libelle}</td>
                        <td class="col-montant">${formatCurrency(p.montant_scolarite_prevu)}</td>
                        <td class="col-verse">${formatCurrency(p.total_verse)}</td>
                        <td class="col-reste">${formatCurrency(p.reste_a_payer)}</td>
                        <td class="col-status">${statusBadge}</td>
                        <td class="col-actions">
                            <div class="action-buttons">
                                ${actionsHtml}
                            </div>
                        </td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        }

        function formatCurrency(amount) {
            if (amount === null || amount === undefined) return 'N/A';
            return parseFloat(amount).toLocaleString('fr-FR') + CURRENCY_SYMBOL;
        }

        async function initialiserScolarite(numEtu, anneeAcademiqueId, niveauId) {
            if (!confirm(`Voulez-vous initialiser le dossier de scolarité pour l'étudiant ${numEtu} pour l'année ${anneeAcademiqueId} ?`)) {
                return;
            }
            showLoading(true);
            try {
                const result = await makeAjaxRequest({
                    action: 'initialiser_scolarite',
                    num_etu: numEtu,
                    annee_academique_id: anneeAcademiqueId,
                    niveau_id: niveauId
                });
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadPaiements(); // Recharger les données
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Erreur de connexion lors de l\'initialisation.', 'error');
            } finally {
                showLoading(false);
            }
        }

        function addVersement(paiementId, studentName, anneeAcademique, montantTotalDu, totalVerse, resteAPayer) {
            document.getElementById('modalStudentNameVersement').textContent = studentName;
            document.getElementById('modalStudentNumVersement').textContent = allPaiementsData.find(p => p.id_paiement === paiementId)?.num_etu || 'N/A';
            document.getElementById('modalAnneeVersement').textContent = anneeAcademique;
            document.getElementById('modalMontantTotalDu').textContent = formatCurrency(montantTotalDu);
            document.getElementById('modalTotalVerse').textContent = formatCurrency(totalVerse);
            document.getElementById('modalResteAPayer').textContent = formatCurrency(resteAPayer);
            document.getElementById('modalResteAPayerHidden').value = resteAPayer; // Pour la validation côté client
            document.getElementById('modalPaiementId').value = paiementId;
            document.getElementById('montant_versement').value = ''; // Réinitialiser
            document.getElementById('montant_versement').max = resteAPayer; // Définir le max pour le champ numérique
            document.getElementById('mode_paiement').value = 'especes'; // Réinitialiser
            document.getElementById('reference_paiement').value = ''; // Réinitialiser
            document.getElementById('commentaire').value = ''; // Réinitialiser
            document.getElementById('addVersementModal').style.display = 'flex';
        }

        function closeAddVersementModal() {
            document.getElementById('addVersementModal').style.display = 'none';
        }

        async function viewVersementHistory(paiementId, studentName, anneeAcademique, montantTotalDu, totalVerse, resteAPayer) {
            document.getElementById('historyModalStudentName').textContent = studentName;
            document.getElementById('historyModalAnnee').textContent = anneeAcademique;
            document.getElementById('historyModalTotalScolarite').textContent = formatCurrency(montantTotalDu);
            document.getElementById('historyModalTotalVerse').textContent = formatCurrency(totalVerse);
            document.getElementById('historyModalResteAPayer').textContent = formatCurrency(resteAPayer);

            const tbody = document.getElementById('versementHistoryTableBody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Chargement de l\'historique...</td></tr>';
            showLoading(true);

            try {
                const result = await makeAjaxRequest({
                    action: 'get_versements_by_paiement_id',
                    id_paiement: paiementId
                });

                if (result.success) {
                    tbody.innerHTML = ''; // Nettoyer
                    if (result.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--gray-500);">Aucun versement enregistré pour ce dossier.</td></tr>';
                    } else {
                        result.data.forEach(v => {
                            const row = `
                                <tr>
                                    <td>${v.numero_versement}</td>
                                    <td style="text-align: right;">${formatCurrency(v.montant_versement)}</td>
                                    <td>${formatDate(v.date_versement)}</td>
                                    <td>${v.mode_paiement || 'N/A'}</td>
                                    <td>${v.reference_paiement || 'N/A'}</td>
                                    <td>${v.commentaire || 'Aucun'}</td>
                                </tr>
                            `;
                            tbody.insertAdjacentHTML('beforeend', row);
                        });
                    }
                } else {
                    showAlert(result.message || 'Erreur lors du chargement de l\'historique des versements.', 'error');
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--error-500);">Erreur de chargement.</td></tr>';
                }
            } catch (error) {
                showAlert('Erreur réseau lors du chargement de l\'historique.', 'error');
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--error-500);">Erreur réseau.</td></tr>';
            } finally {
                showLoading(false);
            }
            document.getElementById('versementHistoryModal').style.display = 'flex';
        }

        function closeVersementHistoryModal() {
            document.getElementById('versementHistoryModal').style.display = 'none';
        }

        // Fonction pour faire une requête AJAX
        async function makeAjaxRequest(data) {
            showLoading(true);
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data).toString()
                });
                return await response.json();
            } catch (error) {
                console.error('Erreur AJAX:', error);
                throw error;
            } finally {
                showLoading(false);
            }
        }

        // Fonctions pour gérer la sidebar
        function initSidebar() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                });
            }

            function handleResize() {
                if (window.innerWidth <= 768) {
                    if (sidebar) sidebar.classList.add('mobile');
                } else {
                    if (sidebar) {
                        sidebar.classList.remove('mobile');
                        sidebar.classList.remove('collapsed');
                    }
                    if (mainContent) mainContent.classList.remove('sidebar-collapsed');
                }
            }
            window.addEventListener('resize', handleResize);
            handleResize();
        }

        // Fonction pour afficher les alertes
        function showAlert(message, type = 'info') {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.textContent = message;
            alertDiv.className = `alert ${type}`;
            alertDiv.style.display = 'block';
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        // Fonction pour afficher/cacher le loading
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = show ? 'flex' : 'none';
        }

        // Fonction pour afficher un état vide dans le tableau
        function displayEmptyState(tableBodyId, message, colspan) {
            const tbody = document.getElementById(tableBodyId);
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="empty-state">
                        <i class="fas fa-money-bill-transfer"></i>
                        <p>${message}</p>
                    </td>
                </tr>
            `;
        }

        // Fonctions utilitaires pour formater les dates
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleDateString('fr-FR');
        }

        // Recherche dans le tableau
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const filteredData = allPaiementsData.filter(p => {
                return (
                    (p.nom_etu && p.nom_etu.toLowerCase().includes(searchTerm)) ||
                    (p.prenoms_etu && p.prenoms_etu.toLowerCase().includes(searchTerm)) ||
                    (p.num_etu && p.num_etu.toString().toLowerCase().includes(searchTerm)) ||
                    (p.lib_filiere && p.lib_filiere.toLowerCase().includes(searchTerm)) ||
                    (p.lib_niv_etu && p.lib_niv_etu.toLowerCase().includes(searchTerm)) ||
                    (p.annee_libelle && p.annee_libelle.toLowerCase().includes(searchTerm))
                );
            });
            displayPaiements(filteredData);
        });

    </script>
</body>
</html>