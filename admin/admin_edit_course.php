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

$display_name = htmlspecialchars(trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? 'Admin')));
if (empty($display_name)) {
    $display_name = 'Administrateur';
}

$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$course_data = null;
$error_message = '';
$success_message = '';

// Si l'ID est manquant ou invalide, rediriger
if (!$course_id) {
    $error_message = "ID de cours manquant ou invalide.";
    header("Location: admin_manage_courses.php?error=" . urlencode($error_message));
    exit();
}

// =================================================================
// 2. LOGIQUE DE MISE À JOUR (POST)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $id_to_update = intval($_POST['course_id']);
    $new_titre = trim($_POST['titre']);
    $new_description = trim($_POST['description']);

    // Validation
    if (empty($new_titre) || empty($new_description)) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($id_to_update !== $course_id) {
        $error_message = "Erreur de sécurité: ID de cours incohérent.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE cours SET titre = ?, description = ? WHERE id_cours = ?");
            $stmt->bind_param("ssi", $new_titre, $new_description, $id_to_update);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_message = "Le cours **ID: $id_to_update** a été mis à jour avec succès.";
                } else {
                    $error_message = "Aucune modification n'a été effectuée pour le cours ID: $id_to_update.";
                }
            } else {
                throw new Exception("Erreur d'exécution de la mise à jour : " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

// =================================================================
// 3. LOGIQUE DE CHARGEMENT DES DONNÉES DU COURS (GET ou POST)
// =================================================================
try {
    // Si la requête POST a échoué, on utilise les valeurs POST pour pré-remplir le formulaire
    if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $course_data = [
            'id_cours' => $course_id,
            'titre' => $_POST['titre'],
            'description' => $_POST['description']
        ];
    } else {
        // Sinon, on récupère les données du cours depuis la BDD
        $stmt_fetch = $conn->prepare("SELECT id_cours, titre, description FROM cours WHERE id_cours = ?");
        $stmt_fetch->bind_param("i", $course_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        
        if ($result->num_rows > 0) {
            $course_data = $result->fetch_assoc();
        } else {
            $error_message = "Cours ID: $course_id non trouvé.";
        }
        $stmt_fetch->close();
    }

} catch (Exception $e) {
    $error_message = "Erreur lors du chargement des données : " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer Cours - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_edit_course.css">
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
        <h1>Éditer le Cours (ID: <?php echo htmlspecialchars($course_id); ?>) <span class="role-badge">ADMIN</span></h1>
        <p>Modifiez les informations du cours ci-dessous.</p>
        
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

        <?php if (!$course_data): ?>
            <div class="alert-warning">
                Le cours demandé est introuvable ou vous n'avez pas l'autorisation d'y accéder.
            </div>
            <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours</a>
        <?php else: 
            // Utiliser les données récupérées ou les données du POST en cas d'erreur
            $current_titre = htmlspecialchars($course_data['titre'] ?? '');
            $current_description = htmlspecialchars($course_data['description'] ?? '');
        ?>
            <form action="admin_edit_course.php?id=<?php echo htmlspecialchars($course_id); ?>" method="POST">
                
                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_id); ?>">

                <div class="form-group">
                    <label for="titre">Titre du Cours *</label>
                    <input type="text" id="titre" name="titre" class="form-control" value="<?php echo $current_titre; ?>" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required><?php echo $current_description; ?></textarea>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn-submit">Sauvegarder les Modifications</button>
                    <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours</a>
                </div>

            </form>
        <?php endif; ?>

    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>