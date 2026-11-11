<?php
session_start();

include "../connection.php"; 

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
$courses = []; // Liste des cours pour le form



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Récupération des données du formulaire
    $id_cours = filter_input(INPUT_POST, 'id_cours', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre'] ?? ''); 
    $description = trim($_POST['description'] ?? ''); 
    $ordre = filter_input(INPUT_POST, 'ordre', FILTER_VALIDATE_INT);
    
    // NOUVELLES VARIABLES POUR GÉRER LES TYPES DE CONTENU
    $type_ressource = trim($_POST['type_ressource'] ?? 'text'); // 'text' ou 'drive'
    $contenu_text = $_POST['contenu_text'] ?? null; // Texte du textarea
    $url_drive = trim($_POST['url_drive'] ?? null); // URL de Drive
    
    $ressource_title = $titre;

    $type = 'text'; 
    $contenu_col = null; 
    $fichier_url_col = null;

    if ($type_ressource === 'drive') {
        $type = 'drive';
        $fichier_url_col = $url_drive; 
    } else {
        $type = 'text';
        $contenu_col = $contenu_text;
    }
    
    if (!$id_cours || empty($titre) || !$ordre) {
        $error_message = "Tous les champs obligatoires (Cours, Titre, Ordre) doivent être remplis correctement.";
    } 
    
    if ($type === 'drive' && empty($fichier_url_col)) {
        $error_message = "L'URL Google Drive ne peut pas être vide.";
    } elseif ($type === 'text' && empty($contenu_col)) {
        $error_message = "Le contenu textuel ne peut pas être vide.";
    }
    
    if (empty($error_message)) {
        try {
            $conn->begin_transaction(); 

            $sql_chapitre = "INSERT INTO chapitre (id_cours, titre, description, ordre) VALUES (?, ?, ?, ?)";
            $stmt_chapitre = $conn->prepare($sql_chapitre);
            $stmt_chapitre->bind_param("isss", $id_cours, $titre, $description, $ordre);
            $stmt_chapitre->execute();

            if ($stmt_chapitre->affected_rows > 0) {
                $id_chapitre = $conn->insert_id;
                $stmt_chapitre->close();

                $sql_ressource = "INSERT INTO ressource (id_chapitre, type, titre, contenu, fichier_url) VALUES (?, ?, ?, ?, ?)";
                $stmt_ressource = $conn->prepare($sql_ressource);
                $stmt_ressource->bind_param("issss", $id_chapitre, $type, $ressource_title, $contenu_col, $fichier_url_col);
                $stmt_ressource->execute();
                
                if ($stmt_ressource->affected_rows > 0) {
                    $conn->commit(); 
                    $success_message = "Le chapitre **\"" . htmlspecialchars($titre) . "\"** et sa ressource ont été créés avec succès (Type: $type).";
                } else {
                    $conn->rollback(); 
                    $error_message = "Échec de l'insertion de la ressource. Vérifiez que les colonnes **`contenu` ET `fichier_url`** existent dans la table `ressource` !";
                }
                $stmt_ressource->close();

            } else {
                $conn->rollback();
                $error_message = "Échec de l'insertion du chapitre (chapitre table).";
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Erreur fatale lors de la création : " . $e->getMessage();
        }
    }
}



try {
    $result = $conn->query("SELECT id_cours, titre FROM cours ORDER BY titre ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message = "Impossible de charger la liste des cours: " . $e->getMessage();
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Chapitre - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_add_chapter.css">
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
            <a href="../logout.php" class="logout-btn">Déconnexion</a>
            <button id="dark-toggle">🌓</button>
        </div>
    </nav>

    <div class="content-container">
        <h1>Créer un Nouveau Chapitre <span class="role-badge">ADMIN</span></h1>
        <p>Ajoutez un nouveau chapitre et associez-lui un contenu textuel ou un lien Google.</p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            
            <div class="form-group">
                <label for="id_cours">Cours Parent <span style="color: red;">*</span></label>
                <select id="id_cours" name="id_cours" required>
                    <option value="">-- Sélectionnez un Cours --</option>
                    <?php if (!empty($courses)): ?>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['id_cours']); ?>">
                                <?php echo htmlspecialchars($course['titre']); ?> (ID: <?php echo htmlspecialchars($course['id_cours']); ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <option value="" disabled>Aucun cours trouvé. Créez-en un d'abord.</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="titre">Titre du Chapitre <span style="color: red;">*</span></label>
                <input type="text" id="titre" name="titre" required placeholder="Ex: Introduction aux Bases de Données">
            </div>

            <div class="form-group">
                <label for="ordre">Ordre du Chapitre (Numéro) <span style="color: red;">*</span></label>
                <input type="number" id="ordre" name="ordre" required min="1" placeholder="Ex: 1">
            </div>

            <div class="form-group">
                <label for="description">Courte Description (Chapitre)</label>
                <input type="text" id="description" name="description" placeholder="Un bref résumé du contenu.">
            </div>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">
            
            <div class="form-group">
                <label for="type_ressource">Type de Ressource <span style="color: red;">*</span></label>
                <select id="type_ressource" name="type_ressource" required>
                    <option value="text">Contenu Textuel (HTML/Texte)</option>
                    <option value="drive">Lien Google Drive (Document, Vidéo, etc.)</option>
                </select>
            </div>

            <div id="text-content-group" class="form-group">
                <label for="contenu_text">Contenu du Chapitre (Texte/HTML) <span style="color: red;">*</span></label>
                <textarea id="contenu_text" name="contenu_text" placeholder="Collez ici le contenu complet du chapitre (texte, HTML, etc.)." required></textarea>
            </div>

            <div id="drive-link-group" class="form-group" style="display:none;">
                <label for="url_drive">URL Google Drive (Lien de Partage) <span style="color: red;">*</span></label>
                <input type="text" id="url_drive" name="url_drive" placeholder="Collez l'URL de votre fichier Google Drive. Assurez-vous que le fichier est public." >
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px; align-items: center;">
                <button type="submit" class="btn-submit">Créer le Chapitre</button>
                <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours</a>
            </div>
        </form>

    </div>

    <script>
    const typeSelect = document.getElementById('type_ressource');
    const textGroup = document.getElementById('text-content-group');
    const driveGroup = document.getElementById('drive-link-group');
    const contenuTextarea = document.getElementById('contenu_text');
    const urlDriveInput = document.getElementById('url_drive');

    function toggleContentInputs() {
        if (typeSelect.value === 'drive') {
            driveGroup.style.display = 'block';
            urlDriveInput.setAttribute('required', 'required');
            urlDriveInput.focus();
            
            textGroup.style.display = 'none';
            contenuTextarea.removeAttribute('required');
        } else {
            textGroup.style.display = 'block';
            contenuTextarea.setAttribute('required', 'required');
            contenuTextarea.focus();

            driveGroup.style.display = 'none';
            urlDriveInput.removeAttribute('required');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        toggleContentInputs();
        typeSelect.addEventListener('change', toggleContentInputs);
    });
    </script>

<script src="../theme.js"></script>

</body>
</html>