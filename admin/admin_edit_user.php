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

$user_id = $_GET['id'] ?? null;
$user_data = null;
$error_message = '';
$success_message = '';

// =================================================================
// 2. LOGIQUE DE MISE À JOUR (POST)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $id_to_update = intval($_POST['user_id']);
    $new_nom = trim($_POST['nom']);
    $new_prenom = trim($_POST['prenom']);
    $new_email = trim($_POST['email']);
    $new_role = $_POST['role'];
    $new_password = $_POST['new_password'] ?? '';
    
    try {
        // Début de la transaction pour garantir la cohérence
        $conn->begin_transaction();
        $params = [];
        $types = '';
        
        // 1. Mise à jour des informations de base (Nom, Prénom, Email, Rôle)
        $sql_update = "UPDATE utilisateur SET nom=?, prenom=?, email=?, role=?";
        $types .= 'ssss';
        $params[] = $new_nom;
        $params[] = $new_prenom;
        $params[] = $new_email;
        $params[] = $new_role;

        // 2. Mise à jour du mot de passe (si fourni)
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update .= ", mot_de_passe=?";
            $types .= 's';
            $params[] = $hashed_password;
        }

        // Finalisation de la requête
        $sql_update .= " WHERE id_utilisateur=?";
        $types .= 'i';
        $params[] = $id_to_update;

        $stmt = $conn->prepare($sql_update);
        
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête: " . $conn->error);
        }

        // Liaison dynamique des paramètres
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $success_message = "L'utilisateur (ID: $id_to_update) a été mis à jour avec succès.";
        
        // Après la mise à jour, nous réinitialisons le $user_id pour recharger les nouvelles données
        $user_id = $id_to_update; 
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Échec de la mise à jour : " . $e->getMessage();
    }
}

// =================================================================
// 3. LOGIQUE DE CHARGEMENT DES DONNÉES UTILISATEUR (GET/POST-redirect)
// =================================================================
if ($user_id) {
    $stmt = $conn->prepare("SELECT id_utilisateur, nom, prenom, email, role, date_inscription FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $user_data['date_inscription_formatted'] = date('d/m/Y', strtotime($user_data['date_inscription']));
    } else {
        $error_message = "Utilisateur non trouvé.";
        $user_id = null; 
    }
    $stmt->close();
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
     // Si c'est un GET sans ID, on redirige vers la liste pour éviter une page vide
     header("Location: admin_manage_users.php");
     exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer Utilisateur - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_edit_user.css">
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
        <h1>Éditer Utilisateur <span class="role-badge">ADMIN</span></h1>
        <p>Modification des informations de l'utilisateur ID: <?php echo htmlspecialchars($user_id); ?></p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php 
            // Recharger les données utilisateur après une mise à jour réussie
            $stmt = $conn->prepare("SELECT id_utilisateur, nom, prenom, email, role, date_inscription FROM utilisateur WHERE id_utilisateur = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $user_data['date_inscription_formatted'] = date('d/m/Y', strtotime($user_data['date_inscription']));
            $stmt->close();
            ?>

        <?php endif; ?>

        <?php if ($user_data): ?>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_data['id_utilisateur']); ?>">

                <div class="form-group">
                    <label>ID Utilisateur</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['id_utilisateur']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user_data['nom']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user_data['prenom']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="role">Rôle</label>
                    <select id="role" name="role" required>
                        <option value="eleve" <?php if ($user_data['role'] === 'eleve') echo 'selected'; ?>>Élève</option>
                        <option value="admin" <?php if ($user_data['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nouveau Mot de Passe (Laisser vide pour ne pas changer)</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Saisir un nouveau mot de passe">
                </div>
                
                <div class="form-group">
                    <label>Date d'Inscription</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['date_inscription_formatted']); ?>" disabled>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn-submit">Sauvegarder les Modifications</button>
                    <a href="admin_manage_users.php" class="btn-back">Retour à la Liste</a>
                </div>

            </form>
        <?php endif; ?>

    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>