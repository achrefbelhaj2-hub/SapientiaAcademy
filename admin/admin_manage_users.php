<?php
session_start();

// =================================================================
// 1. INCLUSION DE LA CONNEXION BDD ET VÉRIFICATION DE SÉCURITÉ
// =================================================================

// Inclure votre fichier de connexion (doit contenir la variable $conn)
include "../connection.php"; 

// VERIFICATION DE SECURITE CRITIQUE POUR L'ADMINISTRATION
if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// L'utilisateur est connecté et est ADMINISTRATEUR.
$user_prenom = $_SESSION['prenom'] ?? ''; 
$user_nom = $_SESSION['nom'] ?? 'Admin';
$user_full_name = trim($user_prenom . ' ' . $user_nom);

if (empty($user_full_name)) {
    $user_full_name = 'Administrateur';
}

$display_name = htmlspecialchars($user_full_name);

// =================================================================
// 2. LOGIQUE DE RÉCUPÉRATION DES UTILISATEURS (MySQLi)
// =================================================================
$users = [];
$error_message = '';

if (!$conn->connect_error) {
    try {
        // Sélectionner tous les utilisateurs, sauf l'administrateur actuellement connecté
        $sql = "SELECT id_utilisateur, nom, prenom, email, role, date_inscription 
                FROM utilisateur 
                ORDER BY role DESC, nom ASC";
        
        $result = mysqli_query($conn, $sql);

        if ($result) {
            while($row = mysqli_fetch_assoc($result)) {
                // Formater la date d'inscription
                $row['date_inscription_formatted'] = date('d/m/Y', strtotime($row['date_inscription']));
                $users[] = $row;
            }
            mysqli_free_result($result);
        } else {
            $error_message = "Erreur de requête BDD: " . mysqli_error($conn);
        }

    } catch (Exception $e) {
        error_log("DB Error in admin manage users: " . $e->getMessage());
        $error_message = "Une erreur est survenue lors du chargement des données.";
    }
} else {
    $error_message = "Erreur de connexion à la base de données.";
}

// mysqli_close($conn); // Optionnel, si vous voulez fermer la connexion ici
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_manage_users.css">
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
        <h1>Gestion des Utilisateurs <span class="role-badge">ADMIN</span></h1>
        <p>Affichez, modifiez ou supprimez les comptes utilisateurs enregistrés dans le système.</p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">
        
        <div style="margin-bottom: 20px;">
            <a href="admin_dashboard.php" class="btn-dashboard-back"> ← Retour au Tableau de Bord</a>
        </div>
        <?php if (!empty($error_message)): ?>
            <div style="padding: 15px; background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 6px; margin-bottom: 20px;">
                Erreur: <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Inscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="ID"><?php echo htmlspecialchars($user['id_utilisateur']); ?></td>
                            <td data-label="Nom"><?php echo htmlspecialchars($user['nom']); ?></td>
                            <td data-label="Prénom"><?php echo htmlspecialchars($user['prenom']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Rôle">
                                <span class="user-role-<?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                </span>
                            </td>
                            <td data-label="Inscription"><?php echo htmlspecialchars($user['date_inscription_formatted']); ?></td>
                            <td data-label="Actions">
                                <a href="admin_edit_user.php?id=<?php echo $user['id_utilisateur']; ?>" class="action-btn btn-edit">Éditer</a>
                                
                                <?php if ($user['id_utilisateur'] != $_SESSION['id']): ?>
                                    <a href="admin_delete_user.php?id=<?php echo $user['id_utilisateur']; ?>" class="action-btn btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">Supprimer</a>
                                <?php else: ?>
                                    <span style="color: #6b7280; font-style: italic;">(Connecté)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Aucun utilisateur trouvé (à part vous, l'administrateur).</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>