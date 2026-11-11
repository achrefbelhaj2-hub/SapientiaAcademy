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

$error_message = '';
$success_message = '';
$courses = [];


// =================================================================
// GESTION DES MESSAGES DE SESSION APRÈS REDIRECTION (PRG)
// =================================================================
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // S'assurer qu'il est affiché une seule fois
}


// =================================================================
// 2. LOGIQUE DE SUPPRESSION DE COURS (CORRIGÉE : AJOUT DE progression)
// =================================================================
if (isset($_GET['delete_course_id']) && is_numeric($_GET['delete_course_id'])) {
    $id_cours = intval($_GET['delete_course_id']);
    
    try {
        $conn->begin_transaction();
        
        // 1. Suppression des Progressions d'Exercices (Dépend des exercices)
        $stmt_p = $conn->prepare("
            DELETE ep 
            FROM exercice_progression ep
            JOIN exercice e ON ep.id_exercice = e.id_exercice
            JOIN chapitre c ON e.id_chapitre = c.id_chapitre
            WHERE c.id_cours = ?
        ");
        $stmt_p->bind_param("i", $id_cours);
        $stmt_p->execute();
        $stmt_p->close();
        
        // 2. Suppression des Exercices (Dépend des chapitres)
        $stmt_e = $conn->prepare("DELETE e FROM exercice e
                                JOIN chapitre c ON e.id_chapitre = c.id_chapitre
                                WHERE c.id_cours = ?");
        $stmt_e->bind_param("i", $id_cours);
        $stmt_e->execute();
        $stmt_e->close();

        // 3. Suppression des Commentaires (Dépend des ressources)
        $stmt_com = $conn->prepare("
            DELETE com
            FROM commentaire com
            JOIN ressource r ON com.id_ressource = r.id_ressource
            JOIN chapitre c ON r.id_chapitre = c.id_chapitre
            WHERE c.id_cours = ?
        ");
        $stmt_com->bind_param("i", $id_cours);
        $stmt_com->execute();
        $stmt_com->close();

        // 4. Suppression des Ressources (Dépend des chapitres)
        $stmt_r = $conn->prepare("DELETE r FROM ressource r 
                                INNER JOIN chapitre c ON r.id_chapitre = c.id_chapitre 
                                WHERE c.id_cours = ?");
        $stmt_r->bind_param("i", $id_cours);
        $stmt_r->execute();
        $stmt_r->close();
        
        // 5. Suppression des Chapitres (Dépend du cours)
        $stmt_c = $conn->prepare("DELETE FROM chapitre WHERE id_cours = ?");
        $stmt_c->bind_param("i", $id_cours);
        $stmt_c->execute();
        $stmt_c->close();
        
        // 6. NOUVEAU : Suppression de la Progression des Utilisateurs pour ce Cours (Dépend directement du cours)
        $stmt_prog = $conn->prepare("DELETE FROM progression WHERE id_cours = ?");
        $stmt_prog->bind_param("i", $id_cours);
        $stmt_prog->execute();
        $stmt_prog->close();
        
        // 7. Suppression du Cours lui-même
        $stmt_course = $conn->prepare("DELETE FROM cours WHERE id_cours = ?");
        $stmt_course->bind_param("i", $id_cours);
        $stmt_course->execute();
        
        if ($stmt_course->affected_rows > 0) {
            $conn->commit();
            // STOCKAGE DU MESSAGE DANS LA SESSION
            $_SESSION['success_message'] = "Le cours (ID: $id_cours) et tout son contenu (progression, chapitres, commentaires, etc.) ont été **supprimés** avec succès.";
            
            // REDIRECTION (PRG)
            header("Location: admin_manage_courses.php");
            exit();
        } else {
            $conn->rollback();
            $error_message = "Échec de la suppression du cours (ID: $id_cours). Le cours n'existe peut-être pas.";
        }
        $stmt_course->close();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erreur lors de la suppression du cours : " . $e->getMessage();
    }
}


// =================================================================
// 3. LOGIQUE DE SUPPRESSION DE CHAPITRE (INCHANGÉE)
// =================================================================
if (isset($_GET['delete_chapter_id']) && is_numeric($_GET['delete_chapter_id'])) {
    $id_chapitre = intval($_GET['delete_chapter_id']);
    
    try {
        $conn->begin_transaction();
        
        // 1. Suppression des progressions liées aux exercices de ce chapitre
        $stmt_p = $conn->prepare("
            DELETE ep
            FROM exercice_progression ep
            JOIN exercice e ON ep.id_exercice = e.id_exercice
            WHERE e.id_chapitre = ?
        ");
        $stmt_p->bind_param("i", $id_chapitre);
        $stmt_p->execute();
        $stmt_p->close();
        
        // 2. Suppression des Exercices associés au Chapitre
        $stmt_e = $conn->prepare("DELETE FROM exercice WHERE id_chapitre = ?");
        $stmt_e->bind_param("i", $id_chapitre);
        $stmt_e->execute();
        $stmt_e->close();

        // 3. Suppression des Commentaires liés à la ressource du chapitre
        $stmt_com = $conn->prepare("
            DELETE com
            FROM commentaire com
            JOIN ressource r ON com.id_ressource = r.id_ressource
            WHERE r.id_chapitre = ?
        ");
        $stmt_com->bind_param("i", $id_chapitre);
        $stmt_com->execute();
        $stmt_com->close();
        
        // 4. Suppression des Ressources associées à ce Chapitre
        $stmt_r = $conn->prepare("DELETE FROM ressource WHERE id_chapitre = ?");
        $stmt_r->bind_param("i", $id_chapitre);
        $stmt_r->execute();
        $stmt_r->close();
        
        // 5. Suppression du Chapitre lui-même
        $stmt_c = $conn->prepare("DELETE FROM chapitre WHERE id_chapitre = ?");
        $stmt_c->bind_param("i", $id_chapitre);
        $stmt_c->execute();
        
        if ($stmt_c->affected_rows > 0) {
            $conn->commit();
            // STOCKAGE DU MESSAGE DANS LA SESSION
            $_SESSION['success_message'] = "Le chapitre (ID: $id_chapitre) et tout son contenu (progression, exercices, commentaires, ressource) ont été **supprimés** avec succès.";
            
            // REDIRECTION (PRG)
            header("Location: admin_manage_courses.php");
            exit();
        } else {
            $conn->rollback();
            $error_message = "Échec de la suppression du chapitre (ID: $id_chapitre). Le chapitre n'existe peut-être pas.";
        }
        $stmt_c->close();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erreur lors de la suppression du chapitre : " . $e->getMessage();
    }
}

// =================================================================
// 4. LOGIQUE DE SUPPRESSION D'EXERCICE (INCHANGÉE)
// =================================================================
if (isset($_GET['delete_exercise_id']) && is_numeric($_GET['delete_exercise_id'])) {
    $id_exercice = intval($_GET['delete_exercise_id']);
    
    try {
        $conn->begin_transaction();
        
        // Suppression des progressions d'exercice associées (IMPORTANT pour respecter la clé étrangère)
        $stmt_p = $conn->prepare("DELETE FROM exercice_progression WHERE id_exercice = ?");
        $stmt_p->bind_param("i", $id_exercice);
        $stmt_p->execute();
        $stmt_p->close();

        // Suppression de l'Exercice lui-même
        $stmt_e = $conn->prepare("DELETE FROM exercice WHERE id_exercice = ?");
        $stmt_e->bind_param("i", $id_exercice);
        $stmt_e->execute();
        
        if ($stmt_e->affected_rows > 0) {
            $conn->commit();
            // STOCKAGE DU MESSAGE DANS LA SESSION
            $_SESSION['success_message'] = "L'exercice (ID: $id_exercice) et la progression associée ont été **supprimés** avec succès.";
            
            // REDIRECTION (PRG)
            header("Location: admin_manage_courses.php");
            exit();
        } else {
            $conn->rollback();
            $error_message = "Échec de la suppression de l'exercice (ID: $id_exercice). L'exercice n'existe peut-être pas.";
        }
        $stmt_e->close();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erreur lors de la suppression de l'exercice : " . $e->getMessage();
    }
}


// =================================================================
// 5. LOGIQUE DE CHARGEMENT DES COURS ET CHAPITRES (INCHANGÉE)
// =================================================================
try {
    // Récupérer la liste des cours avec le nombre de chapitres
    $sql = "SELECT c.id_cours, c.titre, c.description, COUNT(ch.id_chapitre) AS chapter_count
            FROM cours c
            LEFT JOIN chapitre ch ON c.id_cours = ch.id_cours
            GROUP BY c.id_cours
            ORDER BY c.id_cours DESC";
            
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $course_id = $row['id_cours'];
            $courses[$course_id] = $row;
            $courses[$course_id]['chapters'] = [];
            
            // Récupérer les chapitres pour chaque cours AVEC LE COMPTE D'EXERCICES
            $stmt_chapters = $conn->prepare("
                SELECT 
                    ch.id_chapitre, 
                    ch.titre, 
                    ch.ordre,
                    COUNT(e.id_exercice) AS exercise_count
                FROM chapitre ch
                LEFT JOIN exercice e ON ch.id_chapitre = e.id_chapitre
                WHERE ch.id_cours = ?
                GROUP BY ch.id_chapitre, ch.titre, ch.ordre
                ORDER BY ch.ordre ASC
            ");
            $stmt_chapters->bind_param("i", $course_id);
            $stmt_chapters->execute();
            $result_chapters = $stmt_chapters->get_result();
            
            while ($chap = $result_chapters->fetch_assoc()) {
                $courses[$course_id]['chapters'][] = $chap;
                $chapter_id = $chap['id_chapitre'];

                // Trouver l'index du chapitre que nous venons d'ajouter
                $current_chapter_index = count($courses[$course_id]['chapters']) - 1;
                $courses[$course_id]['chapters'][$current_chapter_index]['exercises'] = [];

                // Récupérer les exercices pour chaque chapitre 
                $stmt_exercises = $conn->prepare("SELECT id_exercice, enonce, difficulte FROM exercice WHERE id_chapitre = ?");
                $stmt_exercises->bind_param("i", $chapter_id);
                $stmt_exercises->execute();
                $result_exercises = $stmt_exercises->get_result();
                
                while ($exo = $result_exercises->fetch_assoc()) {
                    $courses[$course_id]['chapters'][$current_chapter_index]['exercises'][] = $exo;
                }
                $stmt_exercises->close();
            }
            $stmt_chapters->close();
        }
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
    <title>Gérer Cours/Chapitres - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_manage_courses.css">
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
        <h1>Gérer Cours & Chapitres <span class="role-badge">ADMIN</span></h1>
        <p>Utilisez cette page pour visualiser, **éditer** ou **supprimer** les cours, chapitres et exercices existants. La suppression d'un cours entraîne la suppression de tout son contenu.</p>
        
        <div class="management-actions" style="margin-bottom: 20px;">
            <a href="admin_add_course.php" class="btn-create-course">➕ Créer un nouveau Cours</a>
            <a href="admin_add_chapter.php" class="btn-create-chapter">➕ Créer un nouveau Chapitre</a>
            <a href="admin_add_exercise.php" class="btn-create-exercise">➕ Créer un Exercice</a>
        </div>
        
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

        <?php if (empty($courses)): ?>
            <div class="alert-warning">
                Aucun cours n'a encore été créé. Veuillez utiliser le bouton **"Créer un nouveau Cours"** ci-dessus.
            </div>
        <?php else: ?>
            <div class="course-list">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-details">
                            <div>
                                <h3><?php echo htmlspecialchars($course['titre']); ?> (ID: <?php echo htmlspecialchars($course['id_cours']); ?>)</h3>
                                <p><?php echo htmlspecialchars($course['description']); ?></p>
                                <p style="margin-top: 5px; font-weight: bold;">
                                    <?php echo htmlspecialchars($course['chapter_count']); ?> Chapitre(s)
                                </p>
                            </div>
                            <div class="action-buttons">
                                <a href="admin_edit_course.php?id=<?php echo htmlspecialchars($course['id_cours']); ?>" 
                                    class="btn-edit-course">
                                    ✏️ Éditer le Cours
                                </a>
                                <a href="javascript:void(0);" 
                                    onclick="confirmDelete('cours', <?php echo htmlspecialchars($course['id_cours']); ?>, '<?php echo htmlspecialchars(addslashes($course['titre'])); ?>')"
                                    class="btn-delete-course">
                                    🗑️ Supprimer le Cours
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($course['chapters'])): ?>
                            <ul class="chapter-list">
                                <strong>Chapitres du Cours :</strong>
                                <?php foreach ($course['chapters'] as $chapter): ?>
                                    <li class="chapter-item">
                                        <div class="chapter-header">
                                            <span class="chapter-title">
                                                <?php echo htmlspecialchars($chapter['ordre']); ?>. 
                                                <?php echo htmlspecialchars($chapter['titre']); ?> (ID: <?php echo htmlspecialchars($chapter['id_chapitre']); ?>)
                                                - **<?php echo htmlspecialchars($chapter['exercise_count']); ?>** Exercice(s)
                                            </span>
                                            <div class="chapter-actions">
                                                <a href="admin_edit_chapter.php?id=<?php echo htmlspecialchars($chapter['id_chapitre']); ?>" 
                                                    class="btn-edit-chapter">
                                                    ✏️ Éditer Chapitre
                                                </a>
                                                <a href="admin_add_exercise.php?chapter_id=<?php echo htmlspecialchars($chapter['id_chapitre']); ?>" 
                                                    class="btn-edit-chapter" style="background-color: #059669;">
                                                    ➕ Ajouter Exercice
                                                </a>
                                                <a href="javascript:void(0);" 
                                                    onclick="confirmDelete('chapitre', <?php echo htmlspecialchars($chapter['id_chapitre']); ?>, '<?php echo htmlspecialchars(addslashes($chapter['titre'])); ?>')"
                                                    class="btn-delete-chapter">
                                                    🗑️ Supprimer Chapitre
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($chapter['exercises'])): ?>
                                            <div class="exercise-section">
                                                <p style="font-weight: 600; margin-bottom: 5px;">Liste des Exercices :</p>
                                                <ul class="exercise-list">
                                                    <?php foreach ($chapter['exercises'] as $exercise): ?>
                                                        <li class="exercise-item">
                                                            <div class="exercise-details">
                                                                <span class="exercise-text">
                                                                    **Difficulté** : <?php echo htmlspecialchars(ucfirst($exercise['difficulte'])); ?> 
                                                                    | **Énoncé (Début)** : <?php echo htmlspecialchars(substr($exercise['enonce'], 0, 50)) . (strlen($exercise['enonce']) > 50 ? '...' : ''); ?>
                                                                </span>
                                                                <span class="exercise-id">(ID: <?php echo htmlspecialchars($exercise['id_exercice']); ?>)</span>
                                                            </div>
                                                            <div class="exercise-actions">
                                                                <a href="admin_edit_exercise.php?id=<?php echo htmlspecialchars($exercise['id_exercice']); ?>" 
                                                                    class="btn-edit-exercise">
                                                                    ✏️ Éditer
                                                                </a>
                                                                <a href="javascript:void(0);" 
                                                                    onclick="confirmDelete('exercice', <?php echo htmlspecialchars($exercise['id_exercice']); ?>, '<?php echo htmlspecialchars(addslashes(substr($exercise['enonce'], 0, 30) . (strlen($exercise['enonce']) > 30 ? '...' : ''))); ?>')"
                                                                    class="btn-delete-exercise">
                                                                    🗑️ Supprimer
                                                                </a>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <p style="margin-top: 5px; font-size: 0.9em; color: #854d0e;">
                                                Aucun exercice dans ce chapitre. Utilisez le bouton "Ajouter Exercice" ci-dessus.
                                            </p>
                                        <?php endif; ?>
                                        </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                               <p style="margin-top: 15px; font-size: 0.9em; color: #854d0e;">Ce cours n'a pas encore de chapitres. <a href="admin_add_chapter.php?course_id=<?php echo htmlspecialchars($course['id_cours']); ?>" style="font-weight: bold;">Ajouter le premier</a>.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="admin_dashboard.php" class="btn-back">← Retour au Tableau de Bord</a>

    </div>

    <script>
    // Logique de confirmation avant suppression
    function confirmDelete(type, id, title) {
        if (type === 'cours') {
            const confirmation = confirm(`Êtes-vous sûr de vouloir supprimer le cours : "${title}" (ID: ${id}) ?\n\nATTENTION : Cela supprimera ÉGALEMENT tous les chapitres, ressources, commentaires, progressions d'exercices ET la progression globale du cours !`);
            if (confirmation) {
                // Utilise la redirection PHP via GET
                window.location.href = 'admin_manage_courses.php?delete_course_id=' + id;
            }
        } else if (type === 'chapitre') {
            const confirmation = confirm(`Êtes-vous sûr de vouloir supprimer le chapitre : "${title}" (ID: ${id}) ?\n\nATTENTION : Cela supprimera ÉGALEMENT sa ressource, tous ses commentaires, exercices et la progression associée !`);
            if (confirmation) {
                // Utilise la redirection PHP via GET
                window.location.href = 'admin_manage_courses.php?delete_chapter_id=' + id;
            }
        } else if (type === 'exercice') { 
            const confirmation = confirm(`Êtes-vous sûr de vouloir supprimer l'exercice : "${title}" (ID: ${id}) ?\n\nATTENTION : Cela supprimera ÉGALEMENT toutes les progressions d'utilisateurs liées à cet exercice !`);
            if (confirmation) {
                // Utilise la redirection PHP via GET
                window.location.href = 'admin_manage_courses.php?delete_exercise_id=' + id;
            }
        }
    }

    </script>

<script src="../theme.js"></script>

</body>
</html>