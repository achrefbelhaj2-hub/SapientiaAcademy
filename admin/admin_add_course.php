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

$error_message = '';
$success_message = '';

// Initialisation des variables pour le formulaire et pour éviter les erreurs d'undefined
$titre = '';
$description = '';

// =================================================================
// 2. LOGIQUE D'AJOUT DE COURS (POST) - CORRIGÉE
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    // REMOVED: $date_creation = date('Y-m-d'); // Supprimé pour correspondre au schéma BDD
    
    if (empty($titre) || empty($description)) {
        $error_message = "Veuillez remplir tous les champs obligatoires (Titre et Description).";
    } else {
        try {
            // FIX: La requête n'inclut plus 'date_creation' pour s'aligner sur la table `cours`.
            // Colonnes utilisées: titre, description (niveau reste NULL par défaut)
            $sql_insert = "INSERT INTO cours (titre, description) VALUES (?, ?)";
            
            $stmt = $conn->prepare($sql_insert);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation de la requête: " . $conn->error);
            }

            // FIX: Changement de "sss" (trois strings) à "ss" (deux strings) pour le bind_param.
            $stmt->bind_param("ss", $titre, $description);
            
            if ($stmt->execute()) {
                $new_course_id = $conn->insert_id;
                $success_message = "Le nouveau cours **$titre** a été créé avec succès. ID du cours: $new_course_id.";
                
                // Réinitialiser les champs du formulaire après succès
                $titre = '';
                $description = '';
            } else {
                 throw new Exception("Erreur d'exécution de l'insertion: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error_message = "Échec de la création du cours : " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Cours - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_add_course.css">
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
        <h1>Créer un Nouveau Cours <span class="role-badge">ADMIN</span></h1>
        <p>Utilisez ce formulaire pour ajouter un nouveau cours de niveau supérieur au système. Vous pourrez ajouter des chapitres et des ressources par la suite.</p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label for="titre">Titre du Cours</label>
                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($titre); ?>" placeholder="Ex: Introduction au PHP" required>
            </div>

            <div class="form-group">
                <label for="description">Description Détaillée</label>
                <textarea id="description" name="description" placeholder="Décrivez le contenu, les objectifs et le public ciblé par ce cours." required><?php echo htmlspecialchars($description); ?></textarea>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-submit">Créer le Cours</button>
                <a href="admin_dashboard.php" class="btn-back">Retour au Tableau de Bord</a>
            </div>

        </form>

    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>