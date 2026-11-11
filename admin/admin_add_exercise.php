<?php
session_start();

// =================================================================
// 1. INCLUSION DE LA CONNEXION BDD ET VÉRIFICATION DE SÉCURITÉ
// =================================================================

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

$error_message = '';
$success_message = '';
$chapters = []; // Liste des chapitres pour le formulaire

// Valeurs par défaut pour le formulaire
$titre = '';
$question = '';
$reponse_correcte = '';
$id_chapitre_selectionne = filter_input(INPUT_GET, 'id_chapitre', FILTER_VALIDATE_INT) ?? null;

// =================================================================
// 2. LOGIQUE DE CRÉATION D'EXERCICE (POST)
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Récupération des données du formulaire
    $id_chapitre = filter_input(INPUT_POST, 'id_chapitre', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre'] ?? ''); 
    $question = trim($_POST['question'] ?? ''); 
    $reponse_correcte = trim($_POST['reponse_correcte'] ?? ''); 

    // Validation
    if (!$id_chapitre) {
        $error_message = "Veuillez sélectionner un chapitre valide.";
    } elseif (empty($titre) || empty($question) || empty($reponse_correcte)) {
        $error_message = "Tous les champs (Titre, Question, Réponse Correcte) sont obligatoires.";
    }

    if (empty($error_message)) {
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO exercice (id_chapitre, enonce, correction) VALUES (?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iss", $id_chapitre, $question, $reponse_correcte);
            
            if ($stmt->execute()) {
                $success_message = "L'exercice '**" . htmlspecialchars($titre) . "**' a été ajouté avec succès.";
                // Réinitialiser les champs après succès
                $titre = '';
                $question = '';
                $reponse_correcte = '';
                $id_chapitre_selectionne = $id_chapitre; // Garder le chapitre sélectionné
            } else {
                $error_message = "Erreur lors de l'ajout de l'exercice : " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Erreur de préparation de la requête : " . $conn->error;
        }
    }
}

// =================================================================
// 3. RÉCUPÉRATION DES CHAPITRES (pour le sélecteur)
// =================================================================
if ($conn) {
    try {
        // Sélectionner tous les chapitres, groupés par cours pour une meilleure lisibilité dans le sélecteur
        $sql = "SELECT c.id_chapitre, c.titre AS chapitre_titre, c.ordre, co.titre AS cours_titre
                FROM chapitre c
                JOIN cours co ON c.id_cours = co.id_cours
                ORDER BY co.titre ASC, c.ordre ASC";
        
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $chapters[] = $row;
            }
        }
    } catch (Exception $e) {
        $error_message .= " Erreur BDD (Récupération des chapitres) : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Exercice - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_add_exercise.css">
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
        <h1>Ajouter un Nouvel Exercice<span class="role-badge">ADMIN</span></h1>
        <p>Créez un nouvel exercice et liez-le à un chapitre existant.</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="admin_add_exercise.php">
            
            <div class="form-group">
                <label for="id_chapitre">Chapitre Parent <span style="color: red;">*</span></label>
                <select id="id_chapitre" name="id_chapitre" required>
                    <option value="">-- Sélectionnez un Chapitre --</option>
                    <?php 
                    $current_course_titre = '';
                    foreach ($chapters as $chapter): 
                        if ($chapter['cours_titre'] !== $current_course_titre):
                            if ($current_course_titre !== ''):
                                echo '</optgroup>';
                            endif;
                            $current_course_titre = $chapter['cours_titre'];
                            echo '<optgroup label="' . htmlspecialchars($current_course_titre) . '">';
                        endif;
                    ?>
                        <option value="<?php echo $chapter['id_chapitre']; ?>" 
                            <?php echo ($id_chapitre_selectionne == $chapter['id_chapitre']) ? 'selected' : ''; ?>>
                            Chapitre <?php echo htmlspecialchars($chapter['ordre']); ?>: <?php echo htmlspecialchars($chapter['chapitre_titre']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_course_titre !== ''): echo '</optgroup>'; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="titre">Titre de l'Exercice <span style="color: red;">*</span></label>
                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($titre); ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="question">Question / Énoncé de l'Exercice <span style="color: red;">*</span></label>
                <textarea id="question" name="question" required><?php echo htmlspecialchars($question); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="reponse_correcte">Réponse Correcte (Texte) <span style="color: red;">*</span></label>
                <input type="text" id="reponse_correcte" name="reponse_correcte" value="<?php echo htmlspecialchars($reponse_correcte); ?>" required>
                <small style="display: block; margin-top: 5px; color: #6b7280;">Pour un exercice simple de type question-réponse.</small>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-submit">Créer l'Exercice</button>
                <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours</a>
            </div>

        </form>

    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>