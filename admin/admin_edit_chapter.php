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

$chapter_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$chapter_data = null;
$resource_data = null;
$courses = []; // Liste des cours pour le formulaire
$error_message = '';
$success_message = '';

// Si l'ID est manquant ou invalide, rediriger
if (!$chapter_id) {
    $error_message = "ID de chapitre manquant ou invalide.";
    header("Location: admin_manage_courses.php?error=" . urlencode($error_message));
    exit();
}

// =================================================================
// 2. LOGIQUE DE MISE À JOUR (POST)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chapter_id'])) {
    
    $id_to_update = intval($_POST['chapter_id']);
    $id_cours = filter_input(INPUT_POST, 'id_cours', FILTER_VALIDATE_INT);
    $titre = trim($_POST['titre'] ?? ''); 
    $ordre = filter_input(INPUT_POST, 'ordre', FILTER_VALIDATE_INT);
    $type_ressource = trim($_POST['type_ressource'] ?? '');
    $contenu_text = ($type_ressource === 'text') ? trim($_POST['contenu_text'] ?? '') : null;
    $url_drive = ($type_ressource === 'drive') ? trim($_POST['url_drive'] ?? '') : null;
    $resource_id = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT); // ID de ressource existant

    // Re-validation
    if (empty($titre) || !$id_cours || !$ordre || empty($type_ressource) || ($type_ressource === 'text' && empty($contenu_text)) || ($type_ressource === 'drive' && empty($url_drive))) {
        $error_message = "Veuillez remplir tous les champs obligatoires et choisir un type de ressource valide.";
    } elseif ($id_to_update !== $chapter_id) {
        $error_message = "Erreur de sécurité: ID de chapitre incohérent.";
    } else {
        try {
            $conn->begin_transaction();

            // 1. Mise à jour du Chapitre
            $stmt_chapitre = $conn->prepare("UPDATE chapitre SET id_cours = ?, titre = ?, ordre = ? WHERE id_chapitre = ?");
            $stmt_chapitre->bind_param("isii", $id_cours, $titre, $ordre, $id_to_update);
            $stmt_chapitre->execute();
            $stmt_chapitre->close();

            // 2. Mise à jour/Création/Suppression de la Ressource
            $resource_action = "";
            $contenu = ($type_ressource === 'text') ? $contenu_text : $url_drive;

            if ($resource_id) {
                // Ressource existante : Mise à jour
                $stmt_resource = $conn->prepare("UPDATE ressource SET type = ?, contenu = ? WHERE id_ressource = ? AND id_chapitre = ?");
                $stmt_resource->bind_param("ssii", $type_ressource, $contenu, $resource_id, $id_to_update);
                $stmt_resource->execute();
                $stmt_resource->close();
                $resource_action = "mise à jour";
            } else {
                // Aucune ressource existante, on en crée une
                $stmt_resource = $conn->prepare("INSERT INTO ressource (id_chapitre, type, contenu) VALUES (?, ?, ?)");
                $stmt_resource->bind_param("iss", $id_to_update, $type_ressource, $contenu);
                $stmt_resource->execute();
                $stmt_resource->close();
                $resource_action = "création";
            }

            $conn->commit();
            $success_message = "Le chapitre **ID: $id_to_update** et sa ressource ont été mis à jour avec succès.";

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Erreur lors de la mise à jour du chapitre/ressource : " . $e->getMessage();
            
            // Pour ré-afficher les données POST en cas d'erreur
            $chapter_data = ['id_cours' => $id_cours, 'titre' => $titre, 'ordre' => $ordre];
            $resource_data = ['type' => $type_ressource, 'contenu' => $contenu];
        }
    }
}

// =================================================================
// 3. LOGIQUE DE CHARGEMENT DES DONNÉES
// =================================================================
try {
    // A. Récupérer la liste des cours (pour le sélecteur)
    $sql_courses = "SELECT id_cours, titre FROM cours ORDER BY titre ASC";
    $result_courses = $conn->query($sql_courses);
    if ($result_courses) {
        while ($row = $result_courses->fetch_assoc()) {
            $courses[] = $row;
        }
    }

    // B. Récupérer les données du chapitre et de la ressource
    if (!isset($chapter_data)) { // On ne récupère que si les données ne viennent pas d'un POST échoué
        $stmt_chapitre = $conn->prepare("SELECT id_cours, titre, ordre FROM chapitre WHERE id_chapitre = ?");
        $stmt_chapitre->bind_param("i", $chapter_id);
        $stmt_chapitre->execute();
        $result_chapitre = $stmt_chapitre->get_result();
        
        if ($result_chapitre->num_rows > 0) {
            $chapter_data = $result_chapitre->fetch_assoc();

            // Récupérer la ressource associée
            $stmt_resource = $conn->prepare("SELECT id_ressource, type, contenu FROM ressource WHERE id_chapitre = ? LIMIT 1");
            $stmt_resource->bind_param("i", $chapter_id);
            $stmt_resource->execute();
            $result_resource = $stmt_resource->get_result();
            if ($result_resource->num_rows > 0) {
                $resource_data = $result_resource->fetch_assoc();
            }
            $stmt_resource->close();

        } else {
            $error_message = "Chapitre ID: $chapter_id non trouvé.";
        }
        $stmt_chapitre->close();
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
    <title>Éditer Chapitre - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_edit_chapter.css">
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
        <h1>Éditer le Chapitre (ID: <?php echo htmlspecialchars($chapter_id); ?>) <span class="role-badge">ADMIN</span></h1>
        <p>Modifiez les informations du chapitre et de sa ressource associée ci-dessous.</p>
        
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

        <?php if (!$chapter_data): ?>
            <div class="alert-warning">
                Le chapitre demandé est introuvable.
            </div>
            <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours</a>
        <?php else: 
            // Utiliser les données récupérées ou les données du POST en cas d'erreur
            $current_course_id = htmlspecialchars($chapter_data['id_cours'] ?? '');
            $current_titre = htmlspecialchars($chapter_data['titre'] ?? '');
            $current_ordre = htmlspecialchars($chapter_data['ordre'] ?? '');
            $current_resource_id = htmlspecialchars($resource_data['id_ressource'] ?? '');
            $current_type = htmlspecialchars($resource_data['type'] ?? 'text');
            $current_contenu = htmlspecialchars($resource_data['contenu'] ?? '');
        ?>
            <form action="admin_edit_chapter.php?id=<?php echo htmlspecialchars($chapter_id); ?>" method="POST">
                
                <input type="hidden" name="chapter_id" value="<?php echo htmlspecialchars($chapter_id); ?>">
                <input type="hidden" name="resource_id" value="<?php echo $current_resource_id; ?>">

                <h2>Détails du Chapitre</h2>
                <div class="form-group">
                    <label for="id_cours">Cours Parent *</label>
                    <select id="id_cours" name="id_cours" class="form-select" required>
                        <option value="">Sélectionner un Cours</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['id_cours']); ?>" 
                                <?php echo ($current_course_id == $course['id_cours']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['titre']); ?> (ID: <?php echo htmlspecialchars($course['id_cours']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="titre">Titre du Chapitre *</label>
                    <input type="text" id="titre" name="titre" class="form-control" value="<?php echo $current_titre; ?>" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="ordre">Ordre du Chapitre (Numéro) *</label>
                    <input type="number" id="ordre" name="ordre" class="form-control" value="<?php echo $current_ordre; ?>" required min="1">
                </div>

                <hr style="margin: 30px 0; border: 0; border-top: 1px dashed #ccc;">

                <h2>Ressource Associée</h2>

                <div class="form-group">
                    <label for="type_ressource">Type de Ressource *</label>
                    <select id="type_ressource" name="type_ressource" class="form-select" required>
                        <option value="text" <?php echo ($current_type == 'text') ? 'selected' : ''; ?>>Contenu Texte/HTML</option>
                        <option value="drive" <?php echo ($current_type == 'drive') ? 'selected' : ''; ?>>Lien Google Drive/Fichier Externe</option>
                        </select>
                </div>

                <div class="form-group" id="text-content-group" style="<?php echo ($current_type == 'text') ? 'display: block;' : 'display: none;'; ?>">
                    <label for="contenu_text">Contenu du Chapitre (Texte/HTML) *</label>
                    <textarea id="contenu_text" name="contenu_text" class="form-control" rows="10"><?php echo ($current_type == 'text') ? $current_contenu : ''; ?></textarea>
                </div>

                <div class="form-group" id="drive-url-group" style="<?php echo ($current_type == 'drive') ? 'display: block;' : 'display: none;'; ?>">
                    <label for="url_drive">URL Google Drive ou Externe *</label>
                    <input type="url" id="url_drive" name="url_drive" class="form-control" value="<?php echo ($current_type == 'drive') ? $current_contenu : ''; ?>">
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn-submit">Sauvegarder les Modifications</button>
                    <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours</a>
                </div>

            </form>
        <?php endif; ?>

    </div>

    <script>
    // Logique pour basculer entre les champs de contenu (Texte vs URL Drive)
    const typeSelect = document.getElementById('type_ressource');
    const textGroup = document.getElementById('text-content-group');
    const driveGroup = document.getElementById('drive-url-group');
    const contenuTextarea = document.getElementById('contenu_text');
    const urlDriveInput = document.getElementById('url_drive');

    function toggleContentInputs() {
        const selectedType = typeSelect.value;
        
        // Réinitialiser tous les champs non affichés
        contenuTextarea.removeAttribute('required');
        urlDriveInput.removeAttribute('required');

        if (selectedType === 'drive') {
            // Afficher le champ URL Drive et le rendre requis
            driveGroup.style.display = 'block';
            urlDriveInput.setAttribute('required', 'required');
            urlDriveInput.focus();

            // Cacher le textarea et retirer son statut requis
            textGroup.style.display = 'none';
            contenuTextarea.value = ''; // Vider le champ texte pour éviter les doubles soumissions de données
        } else if (selectedType === 'text') {
            // Afficher le textarea et le rendre requis
            textGroup.style.display = 'block';
            contenuTextarea.setAttribute('required', 'required');
            contenuTextarea.focus();

            // Cacher le champ URL Drive et retirer son statut requis
            driveGroup.style.display = 'none';
            urlDriveInput.value = ''; // Vider le champ URL pour éviter les doubles soumissions de données
        } else {
            // Aucun
            driveGroup.style.display = 'none';
            textGroup.style.display = 'none';
            contenuTextarea.value = '';
            urlDriveInput.value = '';
        }
    }
    
    // Écouter le changement du sélecteur de type
    typeSelect.addEventListener('change', toggleContentInputs);

    </script>

<script src="../theme.js"></script>

</body>
</html>