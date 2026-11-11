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

$error_message = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_STRING);
$success_message = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_STRING);

$chapter_id = filter_input(INPUT_GET, 'id_chapitre', FILTER_VALIDATE_INT);
$chapter_info = null;
$exercises = [];

// =================================================================
// 2. LOGIQUE DE SUPPRESSION D'EXERCICE
// =================================================================
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $id_exercice = intval($_GET['delete_id']);
    
    try {
        $conn->begin_transaction();
        
        // 1. Suppression des progressions des utilisateurs pour cet exercice (table 'exercice_progression')
        $stmt_progression = $conn->prepare("DELETE FROM exercice_progression WHERE id_exercice = ?");
        $stmt_progression->bind_param("i", $id_exercice);
        $stmt_progression->execute();
        $stmt_progression->close();
        
        // 2. Suppression de l'exercice lui-même
        $stmt_exercice = $conn->prepare("DELETE FROM exercice WHERE id_exercice = ?");
        $stmt_exercice->bind_param("i", $id_exercice);
        
        if ($stmt_exercice->execute()) {
            if ($stmt_exercice->affected_rows > 0) {
                $conn->commit();
                $success_message = "L'exercice ID $id_exercice a été supprimé avec succès.";
            } else {
                $conn->rollback();
                $error_message = "Exercice ID $id_exercice non trouvé ou non supprimé.";
            }
        } else {
            $conn->rollback();
            $error_message = "Erreur lors de la suppression de l'exercice : " . $stmt_exercice->error;
        }
        $stmt_exercice->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erreur fatale lors de la suppression : " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header("Location: admin_manage_exercises.php?id_chapitre=$chapter_id&" . (empty($error_message) ? 'success=' . urlencode($success_message) : 'error=' . urlencode($error_message)));
    exit();
}

// Si l'ID de chapitre est manquant ou invalide
if (!$chapter_id) {
    header("Location: admin_manage_courses.php?error=" . urlencode("ID de chapitre manquant ou invalide."));
    exit();
}

// =================================================================
// 3. RÉCUPÉRATION DES DONNÉES DU CHAPITRE ET DES EXERCICES
// =================================================================
if ($conn) {
    try {
        // Récupérer les informations du chapitre et du cours
        $stmt = $conn->prepare("SELECT c.titre AS chapitre_titre, c.ordre, co.titre AS cours_titre
                                FROM chapitre c
                                JOIN cours co ON c.id_cours = co.id_cours
                                WHERE c.id_chapitre = ?");
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = "Chapitre introuvable.";
            $stmt->close();
            // Rediriger si le chapitre n'existe pas
            header("Location: admin_manage_courses.php?error=" . urlencode($error_message));
            exit();
        }
        $chapter_info = $result->fetch_assoc();
        $stmt->close();

        // Récupérer les exercices du chapitre
        $stmt_ex = $conn->prepare("SELECT id_exercice, titre, question, reponse_correcte, ordre
                                  FROM exercice
                                  WHERE id_chapitre = ?
                                  ORDER BY ordre ASC, id_exercice ASC");
        $stmt_ex->bind_param("i", $chapter_id);
        $stmt_ex->execute();
        $exercises = $stmt_ex->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_ex->close();

    } catch (Exception $e) {
        $error_message = "Erreur BDD : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Exercices - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_manage_exercises.css">
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
        <?php if ($chapter_info): ?>
            <h1>Gestion des Exercices</h1>
            <h2>
                Chapitre <?php echo htmlspecialchars($chapter_info['ordre']); ?>: 
                **<?php echo htmlspecialchars($chapter_info['chapitre_titre']); ?>** (Cours: **<?php echo htmlspecialchars($chapter_info['cours_titre']); ?>**)
            </h2>
            <p>Liste des exercices pour ce chapitre (ID: <?php echo $chapter_id; ?>). </p>
        <?php else: ?>
            <h1>Erreur de Chapitre</h1>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($chapter_info): ?>
            <div style="margin-bottom: 20px;">
                <a href="admin_add_exercise.php?id_chapitre=<?php echo $chapter_id; ?>" class="btn-action btn-add">
                    + Ajouter un Nouvel Exercice
                </a>
            </div>

            <div class="table-container">
                <?php if (!empty($exercises)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ordre</th>
                            <th>Titre de l'Exercice</th>
                            <th>Début de Question</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exercises as $exercise): ?>
                        <tr>
                            <td><?php echo $exercise['id_exercice']; ?></td>
                            <td><?php echo $exercise['ordre'] ?? 'N/A'; ?></td>
                            <td>**<?php echo htmlspecialchars($exercise['titre']); ?>**</td>
                            <td><?php echo htmlspecialchars(substr($exercise['question'], 0, 70)) . '...'; ?></td>
                            <td class="actions-cell">
                                <a href="admin_edit_exercise.php?id=<?php echo $exercise['id_exercice']; ?>" class="btn-action btn-edit">Modifier</a>
                                <button onclick="confirmDeleteExercise(<?php echo $exercise['id_exercice']; ?>, '<?php echo htmlspecialchars($exercise['titre']); ?>')" class="btn-action btn-delete">Supprimer</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="text-align: center; margin: 0;">Aucun exercice trouvé pour ce chapitre.</p>
                <?php endif; ?>
            </div>
            
            <a href="admin_manage_courses.php" class="btn-back">← Retour à la Gestion des Cours & Chapitres</a>
        <?php endif; ?>

    </div>

    <script>
    // Fonction de confirmation de suppression (pour l'exercice)
    function confirmDeleteExercise(id, title) {
        const chapterId = <?php echo $chapter_id; ?>;
        const confirmation = confirm(`Êtes-vous sûr de vouloir supprimer l'exercice ID ${id} ("${title}") ?\n\nATTENTION : Cela supprimera ÉGALEMENT toutes les progressions des utilisateurs pour cet exercice !`);
        if (confirmation) {
            window.location.href = `admin_manage_exercises.php?id_chapitre=${chapterId}&delete_id=${id}`;
        }
    }

    </script>

<script src="../theme.js"></script>

</body>
</html>