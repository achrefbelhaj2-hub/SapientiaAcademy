<?php
session_start();

// =================================================================
// 1. INCLUSION DE LA CONNEXION BDD ET VÉRIFICATION DE SÉCURITÉ
// =================================================================

// Inclure votre fichier de connexion (doit contenir la variable $conn)
include "../connection.php"; 

// VÉRIFICATION DE SÉCURITÉ CRITIQUE POUR L'ADMINISTRATION
if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// L'utilisateur est connecté et est ADMINISTRATEUR.
$display_name = htmlspecialchars(trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? 'Admin')));
if (empty($display_name)) {
    $display_name = 'Administrateur';
}

$logs = [];
$error_message = '';

// =================================================================
// 2. LOGIQUE DE RÉCUPÉRATION DES LOGS (MySQLi)
// =================================================================
if (!$conn->connect_error) {
    try {
        // CORRECTION CRITIQUE: Changement de l.date_heure à l.date_action (selon le schéma SQL)
        $sql = "SELECT 
                    l.id_log, 
                    l.action, 
                    l.date_action, -- CORRIGÉ
                    u.nom, 
                    u.prenom
                FROM logs l
                LEFT JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
                ORDER BY l.date_action DESC -- CORRIGÉ dans l'ORDER BY
                LIMIT 100";

        $result = $conn->query($sql);
        
        if ($result) {
            $logs = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        } else {
            throw new Exception("Erreur de requête SQL: " . $conn->error);
        }

    } catch (Exception $e) {
        error_log("DB Error fetching logs: " . $e->getMessage());
        $error_message = "Une erreur est survenue lors du chargement des logs: " . $e->getMessage();
    }
} else {
    $error_message = "Erreur de connexion à la base de données.";
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Logs - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_view_logs.css">
</head>
<body>

    <nav class="navbar">
        <div class="logo-area">
            <a href="admin_dashboard.php">
                <img src="../DDD-removebg-preview.png" onerror="this.src='https://placehold.co/35x35/FFFFFF/c24100?text=ADM';" alt="Logo Admin">
            </a>
            <span class="admin-title">ADMINISTRATION</span>
        </div>
        
        <div class="user-menu">
            <span class="user-name">
                <?php echo $display_name; ?>
            </span>
            
            <a href="../logout.php" class="logout-btn">
                Déconnexion
            </a>
            
            <button id="dark-toggle">🌓</button>
        </div>
    </nav>

    <div class="content-container">
        <h1>Rapports et Historique des Logs <span class="role-badge">ADMIN</span></h1>
        <p>Affiche les 100 dernières actions enregistrées dans le système par tous les utilisateurs.</p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">
        <div style="margin-bottom: 20px;">
            <a href="admin_dashboard.php" class="btn-dashboard-back"> ← Retour au Tableau de Bord</a>
        </div>
        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="log-table-wrapper">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>ID Log</th>
                        <th>Date & Heure</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['id_log']); ?></td>
                                <td><?php echo htmlspecialchars($log['date_action']); ?></td> 
                                <td>
                                    <?php 
                                        if (!empty($log['nom']) || !empty($log['prenom'])) {
                                            echo htmlspecialchars(trim($log['prenom'] . ' ' . $log['nom']));
                                        } else {
                                            echo "Utilisateur Inconnu"; 
                                        }
                                    ?>
                                </td>
                                <td class="action-cell"><?php echo htmlspecialchars($log['action']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">
                                <div class="alert-warning">
                                    Aucun log trouvé dans la base de données.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>