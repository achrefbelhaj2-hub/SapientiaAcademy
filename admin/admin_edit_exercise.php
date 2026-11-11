<?php
session_start();

// =================================================================
// 1. INCLUSION DE LA CONNEXION BDD ET VÉRIFICATION DE SÉCURITÉ
// =================================================================

// Assurez-vous que ce chemin est correct
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
$chapters = []; 

// =================================================================
// 2. CHARGEMENT DES DONNÉES DE L'EXERCICE (GET)
// =================================================================

// Récupérer l'ID de l'exercice depuis l'URL
$id_exercice = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirection si l'ID est manquant ou invalide
if (!$id_exercice) {
    header("Location: admin_manage_courses.php?error=" . urlencode("ID d'exercice manquant ou invalide."));
    exit();
}

// Récupérer les données de l'exercice
$sql_exercice = "SELECT id_chapitre, enonce, correction, difficulte FROM exercice WHERE id_exercice = ?";
$stmt_exo = $conn->prepare($sql_exercice);
$stmt_exo->bind_param("i", $id_exercice);
$stmt_exo->execute();
$result_exo = $stmt_exo->get_result();

if ($result_exo->num_rows === 0) {
    // Redirection si l'exercice n'est pas trouvé
    header("Location: admin_manage_courses.php?error=" . urlencode("Exercice non trouvé pour l'ID $id_exercice."));
    exit();
}

$exercise_data = $result_exo->fetch_assoc();
$stmt_exo->close();

// Initialisation des variables du formulaire avec les données existantes
$id_chapitre_selectionne = $exercise_data['id_chapitre'];
$question = $exercise_data['enonce']; // Mappage avec le champ 'enonce' de la BDD
$reponse_correcte = $exercise_data['correction']; // Mappage avec le champ 'correction' de la BDD
$difficulte = $exercise_data['difficulte'];

// =================================================================
// 3. LOGIQUE DE MISE À JOUR D'EXERCICE (POST)
// =================================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Récupération et validation des données du formulaire
    $id_exercice_post = filter_input(INPUT_POST, 'id_exercice', FILTER_VALIDATE_INT);
    $id_chapitre = filter_input(INPUT_POST, 'id_chapitre', FILTER_VALIDATE_INT);
    // Nettoyage et récupération des champs
    $question = trim(filter_input(INPUT_POST, 'question', FILTER_SANITIZE_SPECIAL_CHARS));
    $reponse_correcte = trim(filter_input(INPUT_POST, 'reponse_correcte', FILTER_SANITIZE_SPECIAL_CHARS));
    $difficulte = filter_input(INPUT_POST, 'difficulte', FILTER_SANITIZE_SPECIAL_CHARS);

    // Vérification de l'ID posté pour la sécurité
    if ($id_exercice_post !== $id_exercice) {
        $error_message = "Erreur de sécurité: ID de l'exercice non correspondant. Tentative de mise à jour échouée.";
    }

    // Validation
    if (!$id_chapitre || !$id_exercice_post) {
        $error_message = "Veuillez sélectionner un chapitre.";
    } elseif (empty($question)) {
        $error_message = "L'énoncé de l'exercice est requis.";
    } elseif (empty($reponse_correcte)) {
        $error_message = "La réponse correcte est requise.";
    } elseif (!in_array($difficulte, ['facile', 'moyen', 'difficile'])) {
        $error_message = "Niveau de difficulté invalide.";
    }

    if (empty($error_message)) {
        // Préparation de la requête de MISE À JOUR (UPDATE)
        // Les colonnes utilisées sont 'enonce' et 'correction' (confirmé par la BDD)
        $sql = "UPDATE exercice SET id_chapitre = ?, enonce = ?, correction = ?, difficulte = ? WHERE id_exercice = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Binding des paramètres: i(chapitre), s(enonce), s(correction), s(difficulte), i(id_exercice)
            $stmt->bind_param("isssi", $id_chapitre, $question, $reponse_correcte, $difficulte, $id_exercice_post);
            
            if ($stmt->execute()) {
                $success_message = "L'exercice (ID: $id_exercice_post) a été **mis à jour** avec succès!";
                // Mettre à jour l'ID du chapitre sélectionné pour le cas où l'admin l'a changé
                $id_chapitre_selectionne = $id_chapitre;

            } else {
                $error_message = "Erreur lors de la mise à jour de l'exercice : " . $stmt->error;
            }
            $stmt->close();
        } else {
             $error_message = "Erreur de préparation de la requête SQL: " . $conn->error;
        }
    }
}


// =================================================================
// 4. CHARGEMENT DE LA LISTE DES CHAPITRES (pour la sélection)
// =================================================================

try {
    // Récupère tous les chapitres avec le titre de leur cours parent pour une meilleure lisibilité
    $sql_chapters = "SELECT ch.id_chapitre, CONCAT(c.titre, ' > ', ch.ordre, '. ', ch.titre) AS full_title 
                     FROM chapitre ch 
                     JOIN cours c ON ch.id_cours = c.id_cours
                     ORDER BY c.titre, ch.ordre ASC";
    $result_chapters = $conn->query($sql_chapters);

    if ($result_chapters) {
        while ($row = $result_chapters->fetch_assoc()) {
            $chapters[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message .= " Erreur lors du chargement des chapitres : " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer Exercice (ID: <?php echo htmlspecialchars($id_exercice); ?>) - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_edit_exercise.css">
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
        <h1>Éditer l'Exercice (ID: <?php echo htmlspecialchars($id_exercice); ?>) <span class="role-badge">ADMIN</span></h1>
        <p>Utilisez ce formulaire pour modifier l'énoncé, la correction, la difficulté ou le chapitre de cet exercice.</p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                ❌ Erreur: <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success">
                ✅ Succès: <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <form action="admin_edit_exercise.php?id=<?php echo htmlspecialchars($id_exercice); ?>" method="POST">
            
            <input type="hidden" name="id_exercice" value="<?php echo htmlspecialchars($id_exercice); ?>">

            <div class="form-group">
                <label for="id_chapitre">Chapitre Parent <span style="color:red;">*</span></label>
                <select name="id_chapitre" id="id_chapitre" required>
                    <option value="">-- Sélectionner un Chapitre --</option>
                    <?php foreach ($chapters as $chap): ?>
                        <option value="<?php echo htmlspecialchars($chap['id_chapitre']); ?>"
                            <?php echo ($chap['id_chapitre'] == $id_chapitre_selectionne) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($chap['full_title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($chapters)): ?>
                    <small style="color: #991b1b;">⚠️ Aucun chapitre trouvé. Créez un chapitre avant d'ajouter un exercice.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="question">Énoncé de l'Exercice (Question) <span style="color:red;">*</span></label>
                <textarea name="question" id="question" required><?php echo htmlspecialchars($question); ?></textarea>
                <small>Ceci est l'énoncé ou la question qui sera présentée à l'utilisateur. (Stocké dans la colonne **`enonce`**)</small>
            </div>
            
            <div class="form-group">
                <label for="reponse_correcte">Réponse Correcte <span style="color:red;">*</span></label>
                <textarea name="reponse_correcte" id="reponse_correcte" required><?php echo htmlspecialchars($reponse_correcte); ?></textarea>
                <small>La réponse attendue pour valider l'exercice. (Stocké dans la colonne **`correction`**)</small>
            </div>

            <div class="form-group">
                <label for="difficulte">Niveau de Difficulté <span style="color:red;">*</span></label>
                <select name="difficulte" id="difficulte" required>
                    <option value="facile" <?php echo ($difficulte == 'facile') ? 'selected' : ''; ?>>Facile</option>
                    <option value="moyen" <?php echo ($difficulte == 'moyen') ? 'selected' : ''; ?>>Moyen</option>
                    <option value="difficile" <?php echo ($difficulte == 'difficile') ? 'selected' : ''; ?>>Difficile</option>
                </select>
                <small>Aide à classer l'exercice pour les utilisateurs.</small>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-submit">💾 Enregistrer les Modifications</button>
                <a href="admin_manage_courses.php" class="btn-back">← Annuler et Retourner à la Gestion</a>
            </div>

        </form>

    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>