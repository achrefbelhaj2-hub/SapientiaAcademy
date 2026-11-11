<?php
session_start();
include "../connection.php"; 

// VÉRIFICATION DE SÉCURITÉ CRITIQUE POUR L'ADMINISTRATION
if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$display_name = htmlspecialchars(trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? 'Admin')));
if (empty($display_name)) {
    $display_name = 'Administrateur';
}

$comments = [];
$error_message = '';
$success_message = '';

// =================================================================
// 2. LOGIQUE DE SUPPRESSION DE COMMENTAIRE (GET)
// =================================================================
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $id_commentaire = intval($_GET['delete_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM commentaire WHERE id_commentaire = ?");
        $stmt->bind_param("i", $id_commentaire);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Le commentaire ID $id_commentaire a été supprimé.";
        } else {
            $error_message = "Erreur ou commentaire ID $id_commentaire introuvable.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Erreur BDD lors de la suppression: " . $e->getMessage();
    }
    // Redirection pour éviter la resoumission du formulaire
    header("Location: admin_manage_comments.php?status=" . (empty($error_message) ? 'success' : 'error'));
    exit();
}

// Gérer les messages de statut après redirection
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        // Le message est stocké dans la session ou défini manuellement après la suppression réussie
        $success_message = "Opération réussie."; 
    } elseif ($_GET['status'] == 'error') {
        $error_message = "Une erreur s'est produite lors de l'opération.";
    }
}


// =================================================================
// 3. LOGIQUE DE RÉCUPÉRATION DES COMMENTAIRES
// =================================================================
if (!$conn->connect_error) {
    try {
        // Jointure pour obtenir le nom de l'utilisateur, le titre de la ressource et le titre du chapitre/cours (si possible)
        $sql = "SELECT 
                    c.id_commentaire, 
                    c.contenu, 
                    c.date_commentaire,
                    u.nom AS auteur_nom, 
                    u.prenom AS auteur_prenom,
                    r.titre AS ressource_titre
                FROM commentaire c
                JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                JOIN ressource r ON c.id_ressource = r.id_ressource
                ORDER BY c.date_commentaire DESC";

        $result = $conn->query($sql);
        
        if ($result) {
            $comments = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        } else {
            throw new Exception("Erreur de requête SQL: " . $conn->error);
        }

    } catch (Exception $e) {
        error_log("DB Error fetching comments: " . $e->getMessage());
        $error_message = "Une erreur est survenue lors du chargement des commentaires: " . $e->getMessage();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commentaires - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_manage_comments.css">
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
        <h1>Modération et Gestion des Commentaires <span class="role-badge">ADMIN</span></h1>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">

        <?php if (!empty($error_message)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="comment-table-wrapper">
            <table class="comment-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Auteur</th>
                        <th>Ressource</th>
                        <th>Contenu</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($comments) > 0): ?>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comment['id_commentaire']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime(htmlspecialchars($comment['date_commentaire']))); ?></td> 
                                <td><?php echo htmlspecialchars(trim($comment['auteur_prenom'] . ' ' . $comment['auteur_nom'])); ?></td>
                                <td><?php echo htmlspecialchars($comment['ressource_titre']); ?></td>
                                <td class="comment-content" title="<?php echo htmlspecialchars($comment['contenu']); ?>"><?php echo htmlspecialchars($comment['contenu']); ?></td>
                                <td>
                                    <button class="action-btn" onclick="confirmDelete(<?php echo $comment['id_commentaire']; ?>)">Supprimer</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">
                                <div class="alert-warning">
                                    Aucun commentaire à modérer.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="admin_dashboard.php" class="btn-back">← Retour au Tableau de Bord</a>

    </div>

    <script>
    function confirmDelete(id) {
        if (confirm(`Êtes-vous sûr de vouloir supprimer le commentaire ID ${id} ? Cette action est irréversible.`)) {
            window.location.href = 'admin_manage_comments.php?delete_id=' + id;
        }
    }

    </script>

<script src="../theme.js"></script>

</body>
</html>