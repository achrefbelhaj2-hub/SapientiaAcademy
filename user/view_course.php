<?php
session_start();


include "../connection.php"; 

if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id'];
$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$active_chapter_id = filter_input(INPUT_GET, 'chapter', FILTER_VALIDATE_INT);

$course = null;
$chapters = [];
$active_content = [
    'chapter' => null,
    'resource' => null,
    'exercises' => [], 
    'comments' => [],
    'section_title' => ''
];
$error_message = '';
$exercise_feedback_map = []; 
$comment_success_message = '';
$comment_error_message = '';

$display_full_name = htmlspecialchars(trim(($_SESSION['prenom'] ?? 'Utilisateur') . ' ' . ($_SESSION['nom'] ?? '')));
if (empty($display_full_name)) {
    $display_full_name = 'Utilisateur'; 
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_exercise']) && $active_chapter_id) {
    $user_answer = trim($_POST['user_answer'] ?? ''); 
    $id_exercice = filter_input(INPUT_POST, 'id_exercice', FILTER_VALIDATE_INT);

    if ($id_exercice && !empty($user_answer)) {
        $sql_answer = "SELECT correction FROM exercice WHERE id_exercice = ?"; 
        $stmt_answer = $conn->prepare($sql_answer);
        $stmt_answer->bind_param("i", $id_exercice);
        $stmt_answer->execute();
        $result_answer = $stmt_answer->get_result();
        $correct_answer = $result_answer->fetch_assoc()['correction'] ?? '';
        $stmt_answer->close();

        $is_correct = (strtolower($user_answer) === strtolower($correct_answer));

        if ($is_correct) {
            $status = 'termine';
            $feedback_msg = '<p class="success-msg">✅ Félicitations ! Votre réponse est correcte. Cet exercice est désormais validé.</p>';
        } else {
            $status = 'en_cours'; 
            $feedback_msg = "<p class='error-msg'>❌ Réponse incorrecte. Veuillez réessayer.</p>"; 
        }
        
        $exercise_feedback_map[$id_exercice] = $feedback_msg;

        //Insérer/Mettre à jour dans exercice_progression
        $sql_check = "SELECT id FROM exercice_progression WHERE id_utilisateur = ? AND id_exercice = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $user_id, $id_exercice);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Mise à jour si reponse correct
            if ($is_correct) {
                $sql_update = "UPDATE exercice_progression SET statut = ?, date_derniere_modif = NOW() WHERE id_utilisateur = ? AND id_exercice = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sii", $status, $user_id, $id_exercice);
                $stmt_update->execute();
                $stmt_update->close();
            }
        } else {
            $sql_insert = "INSERT INTO exercice_progression (id_utilisateur, id_exercice, statut, date_derniere_modif) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iis", $user_id, $id_exercice, $status);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();

        $sql_update_progress = "UPDATE progression SET dernier_acces = NOW() WHERE id_utilisateur = ? AND id_cours = ?";
        $stmt_progress = $conn->prepare($sql_update_progress);
        $stmt_progress->bind_param("ii", $user_id, $course_id);
        $stmt_progress->execute();
        $stmt_progress->close();
    }
}

//get des commantaires
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment'])) {
    $comment_text = trim($_POST['comment_text'] ?? '');
    $id_ressource = filter_input(INPUT_POST, 'id_ressource', FILTER_VALIDATE_INT);
    
    if (empty($comment_text)) {
        $comment_error_message = "Veuillez écrire votre commentaire avant de soumettre.";
    } elseif (!$id_ressource) {
        $comment_error_message = "Erreur: Ressource introuvable pour ce commentaire.";
    } else {
        $sql_insert_comment = "INSERT INTO commentaire (id_utilisateur, id_ressource, contenu) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert_comment);
        $stmt_insert->bind_param("iis", $user_id, $id_ressource, $comment_text);
        
        if ($stmt_insert->execute()) {
            $comment_success_message = "✅ Commentaire ajouté avec succès !";
            log_activity($conn, $user_id, "Ajout d'un commentaire sur Ressource ID: " . $id_ressource);
        } else {
            $comment_error_message = "Erreur lors de l'ajout du commentaire. Veuillez réessayer."; 
        }
        $stmt_insert->close();
    }
}



if (!$course_id) {
    $error_message = "ID de cours manquant ou invalide.";
} else {
    $sql_course = "SELECT * FROM cours WHERE id_cours = ?";
    $stmt_course = $conn->prepare($sql_course);
    $stmt_course->bind_param("i", $course_id);
    $stmt_course->execute();
    $course = $stmt_course->get_result()->fetch_assoc();
    $stmt_course->close();

    if (!$course) {
        $error_message = "Ce cours n'existe pas.";
    } else {
        $sql_check_start = "SELECT id_cours FROM progression WHERE id_utilisateur = ? AND id_cours = ?";
        $stmt_check_start = $conn->prepare($sql_check_start);
        $stmt_check_start->bind_param("ii", $user_id, $course_id);
        $stmt_check_start->execute();
        if ($stmt_check_start->get_result()->num_rows == 0) {
            $sql_insert_start = "INSERT INTO progression (id_utilisateur, id_cours, dernier_acces, est_termine) VALUES (?, ?, NOW(), 0)";
            $stmt_insert_start = $conn->prepare($sql_insert_start);
            $stmt_insert_start->bind_param("ii", $user_id, $course_id);
            $stmt_insert_start->execute();
            $stmt_insert_start->close();
        }
        $stmt_check_start->close();

        $sql_chapters = "
            SELECT 
                c.id_chapitre, c.titre, c.ordre,
                (SELECT COUNT(ep.id) FROM exercice_progression ep
                 LEFT JOIN exercice e ON e.id_exercice = ep.id_exercice
                 WHERE e.id_chapitre = c.id_chapitre AND ep.id_utilisateur = ? AND ep.statut = 'termine') AS exercises_completed,
                (SELECT COUNT(e2.id_exercice) FROM exercice e2 WHERE e2.id_chapitre = c.id_chapitre) AS total_exercises
            FROM chapitre c
            WHERE c.id_cours = ?
            ORDER BY c.ordre ASC";
        
        $stmt_chapters = $conn->prepare($sql_chapters);
        $stmt_chapters->bind_param("ii", $user_id, $course_id);
        $stmt_chapters->execute();
        $result_chapters = $stmt_chapters->get_result();

        while ($row = $result_chapters->fetch_assoc()) {
            // Calculer la progress du chapitre
            $row['progression_pourcentage'] = ($row['total_exercises'] > 0) 
                                             ? round(($row['exercises_completed'] / $row['total_exercises']) * 100)
                                             : 100; // 100% si pas d'exercice
            $chapters[] = $row;
        }
        $stmt_chapters->close();

        if (!$active_chapter_id && !empty($chapters)) {
            $active_chapter_id = $chapters[0]['id_chapitre'];
        }

        if ($active_chapter_id) {
            $active_content['chapter'] = array_filter($chapters, fn($c) => $c['id_chapitre'] == $active_chapter_id);
            $active_content['chapter'] = $active_content['chapter'] ? array_values($active_content['chapter'])[0] : null;

            $sql_resource = "SELECT id_ressource, contenu, type, fichier_url, titre FROM ressource WHERE id_chapitre = ?";
            $stmt_resource = $conn->prepare($sql_resource);
            $stmt_resource->bind_param("i", $active_chapter_id);
            $stmt_resource->execute();
            $active_content['resource'] = $stmt_resource->get_result()->fetch_assoc();
            $stmt_resource->close();
            
            $active_resource_id = $active_content['resource']['id_ressource'] ?? null; // Récupère l'ID
            $active_content['comments'] = []; // Initialiser le tableau de commentaires

            if ($active_resource_id) {
                $sql_comments = "
                    SELECT 
                        c.contenu AS comment_content,
                        c.date_commentaire,
                        u.prenom,
                        u.nom
                    FROM commentaire c
                    JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                    WHERE c.id_ressource = ?
                    ORDER BY c.date_commentaire DESC";
                
                $stmt_comments = $conn->prepare($sql_comments);
                $stmt_comments->bind_param("i", $active_resource_id);
                $stmt_comments->execute();
                $result_comments = $stmt_comments->get_result();

                while ($comment = $result_comments->fetch_assoc()) {
                    $active_content['comments'][] = $comment;
                }
                $stmt_comments->close();
            }


            $sql_exercises = "SELECT id_exercice, enonce AS question FROM exercice WHERE id_chapitre = ?"; 
            $stmt_exercises = $conn->prepare($sql_exercises);
            $stmt_exercises->bind_param("i", $active_chapter_id);
            $stmt_exercises->execute();
            $result_exercises = $stmt_exercises->get_result();
            $active_content['exercises'] = [];
            
            while ($exercise = $result_exercises->fetch_assoc()) {
                $id_exercice = $exercise['id_exercice'];
                
                $sql_status = "SELECT statut FROM exercice_progression WHERE id_utilisateur = ? AND id_exercice = ? AND statut = 'termine'";
                $stmt_status = $conn->prepare($sql_status);
                $stmt_status->bind_param("ii", $user_id, $id_exercice);
                $stmt_status->execute();
                $exercise['is_done'] = ($stmt_status->get_result()->num_rows > 0);
                $stmt_status->close();
                
                $active_content['exercises'][] = $exercise;
            }
            $stmt_exercises->close();
            
            if (!empty($active_content['exercises'])) {
                 $active_content['section_title'] = "Tests de Connaissances sur " . htmlspecialchars($active_content['chapter']['titre']);
            } else {
                 $active_content['section_title'] = "Aucun exercice disponible";
            }

            $sql_total_exercises = "SELECT COUNT(e.id_exercice) AS total FROM exercice e JOIN chapitre c ON e.id_chapitre = c.id_chapitre WHERE c.id_cours = ?";
            $stmt_total = $conn->prepare($sql_total_exercises);
            $stmt_total->bind_param("i", $course_id);
            $stmt_total->execute();
            $total_exercises = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt_total->close();

            $sql_completed_exercises = "SELECT COUNT(DISTINCT ep.id_exercice) AS completed FROM exercice_progression ep JOIN exercice e ON ep.id_exercice = e.id_exercice JOIN chapitre c ON e.id_chapitre = c.id_chapitre WHERE c.id_cours = ? AND ep.id_utilisateur = ? AND ep.statut = 'termine'";
            $stmt_completed = $conn->prepare($sql_completed_exercises);
            $stmt_completed->bind_param("ii", $course_id, $user_id);
            $stmt_completed->execute();
            $completed_exercises = $stmt_completed->get_result()->fetch_assoc()['completed'] ?? 0;
            $stmt_completed->close();

            if ($total_exercises > 0 && $completed_exercises >= $total_exercises) {
                 //MARQUER LA TERMINER D UN COURS
                $sql_mark_completed = "UPDATE progression SET est_termine = 1 WHERE id_utilisateur = ? AND id_cours = ?";
                $stmt_mark_completed = $conn->prepare($sql_mark_completed);
                $stmt_mark_completed->bind_param("ii", $user_id, $course_id);
                $stmt_mark_completed->execute();
                $stmt_mark_completed->close();
            } else {
                // CORRECTION DU BUG: Si de nouveaux exercices sont ajoutés, le cours n'est plus terminé.
                 $sql_mark_incomplete = "UPDATE progression SET est_termine = 0 WHERE id_utilisateur = ? AND id_cours = ? AND est_termine = 1";
                 $stmt_mark_incomplete = $conn->prepare($sql_mark_incomplete);
                 $stmt_mark_incomplete->bind_param("ii", $user_id, $course_id);
                 $stmt_mark_incomplete->execute();
                 $stmt_mark_incomplete->close();
            }

        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $course ? htmlspecialchars($course['titre']) : 'Cours Inconnu'; ?></title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/pages/view_course.css">
</head>
<body>
    <nav>
        <a href="user_dashboard.php" class="logo"> 
            <img src="../DDD-removebg-preview.png" alt="Sapientia Logo">
        </a>
        <div class="user-info">
            <span class="user-name"><?php echo $display_full_name; ?></span>
            <a href="../logout.php" class="logout-btn">Déconnexion</a>
            <button id="dark-toggle">🌓</button>
        </div>
    </nav>

    <a href="user_dashboard.php" class="btn-back-dashboard">
        ← Retour au Tableau de Bord
    </a>
    <?php if ($error_message): ?>
        <div style="max-width: 800px; margin: 30px auto; padding: 20px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px;">
            <p style="color: #ef4444; font-weight: bold;"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php else: ?>
        <div class="course-page-container">
            
            <div class="sidebar">
                <div class="sidebar-card course-details">
                    <h2><?php echo htmlspecialchars($course['titre'] ?? 'Cours'); ?></h2>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($course['description'] ?? 'N/A'); ?></p>
                </div>

                <div class="sidebar-card chapters-nav">
                    <h2>Chapitres</h2>
                    <ul class="chapter-list">
                        <?php if (empty($chapters)): ?>
                            <li><p>Aucun chapitre disponible pour l'instant.</p></li>
                        <?php else: ?>
                            <?php foreach ($chapters as $chapter): 
                                $is_active = $chapter['id_chapitre'] == $active_chapter_id;
                                $class = $is_active ? 'active-chapter' : '';
                            ?>
                                <li class="<?php echo $class; ?>">
                                    <a href="view_course.php?id=<?php echo htmlspecialchars($course_id); ?>&chapter=<?php echo htmlspecialchars($chapter['id_chapitre']); ?>">
                                        <?php echo htmlspecialchars($chapter['titre']); ?>
                                        <span class="chapter-progress">
                                            Progression : <?php echo $chapter['progression_pourcentage']; ?>%
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="content-area">
                <?php if ($active_content['chapter'] && $active_content['resource']): ?>
                    
                    <div class="chapter-content-card">
                        <h2><?php echo htmlspecialchars($active_content['chapter']['titre']); ?></h2>
                        
                        <div class="resource-content">
                            <?php if ($active_content['resource']): ?>
                                <?php 
                                    $resource_data = $active_content['resource'];
                                    $content = $resource_data['contenu'];
                                    $type = $resource_data['type'];
                                    $file_url = $resource_data['fichier_url'];

                                    $display_content = !empty($content) ? $content : $file_url;
                                    
                                    $is_valid_url = filter_var($display_content, FILTER_VALIDATE_URL);
                                    
                                    if ($type == 'video_url' && $is_valid_url):
                                        $embed_url = str_replace("watch?v=", "embed/", $display_content);
                                        echo "<iframe src=\"" . htmlspecialchars($embed_url) . "\" allowfullscreen></iframe>";
                                        
                                    elseif ($is_valid_url):
                                        echo "<p>Ressource téléchargeable ou lien externe :</p>";
                                        $link_text = !empty($resource_data['titre']) ? 'Télécharger/Accéder : ' . htmlspecialchars($resource_data['titre']) : 'Accéder à la ressource (URL)';
                                        echo "<p><a href=\"" . htmlspecialchars($display_content) . "\" target=\"_blank\" class=\"resource-link\">" . $link_text . "</a></p>";
                                    else:
                                        echo "<p>" . nl2br(htmlspecialchars($display_content)) . "</p>";
                                    endif; 
                                ?>
                            <?php else: ?>
                                <p>Aucune ressource disponible pour ce chapitre.</p>
                            <?php endif; ?>
                        </div>
                        
                        
                        <?php if (!empty($active_content['exercises'])): ?>
                            <div class="exercise-form">
                                <h3><?php echo htmlspecialchars($active_content['section_title']); ?></h3>
                                
                                <?php foreach ($active_content['exercises'] as $exercise): 
                                    $exercise_class = $exercise['is_done'] ? 'exercise-done' : '';
                                    $feedback = $exercise_feedback_map[$exercise['id_exercice']] ?? ''; // Afficher le feedback immédiat
                                ?>
                                    <div class="exercise-container <?php echo $exercise_class; ?>">
                                        
                                        <?php if ($exercise['is_done']): ?>
                                            <p class="success-msg">✅ Exercice terminé !</p>
                                        <?php endif; ?>

                                        <?php echo $feedback; ?>

                                        <form method="POST" action="view_course.php?id=<?php echo htmlspecialchars($course_id); ?>&chapter=<?php echo htmlspecialchars($active_chapter_id); ?>">
                                            <input type="hidden" name="id_exercice" value="<?php echo htmlspecialchars($exercise['id_exercice']); ?>">
                                            
                                            <label for="answer_<?php echo htmlspecialchars($exercise['id_exercice']); ?>">
                                                <strong>Question:</strong> <?php echo htmlspecialchars($exercise['question']); ?>
                                            </label>
                                            
                                            <?php if (!$exercise['is_done']): ?>
                                                <input type="text" id="answer_<?php echo htmlspecialchars($exercise['id_exercice']); ?>" name="user_answer" required placeholder="Votre réponse...">
                                                <button type="submit" name="submit_exercise">Valider la réponse</button>
                                            <?php else: ?>
                                                <p>Cet exercice est complété. Vous pouvez revoir le contenu ci-dessus.</p>
                                            <?php endif; ?>
                                            
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="comments-section">
                            <h3>Espace de Discussion et Questions</h3>

                            <form method="POST" class="comment-form" action="view_course.php?id=<?php echo htmlspecialchars($course_id); ?>&chapter=<?php echo htmlspecialchars($active_chapter_id); ?>">
                                <input type="hidden" name="id_ressource" value="<?php echo htmlspecialchars($active_content['resource']['id_ressource'] ?? ''); ?>">
                                
                                <?php if ($comment_success_message): ?>
                                    <p class="success-msg"><?php echo htmlspecialchars($comment_success_message); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($comment_error_message): ?>
                                    <p class="error-msg"><?php echo htmlspecialchars($comment_error_message); ?></p>
                                <?php endif; ?>
                                
                                <textarea name="comment_text" rows="4" placeholder="Tapez votre question ou commentaire ici..."></textarea>
                                <button type="submit" name="submit_comment">Poster le commentaire</button>
                            </form>

                            <div class="comment-list">
                                <?php if (!empty($active_content['comments'])): ?>
                                    <?php foreach ($active_content['comments'] as $comment): 
                                        $full_name = htmlspecialchars($comment['prenom'] . ' ' . $comment['nom']);
                                        $date_formatted = date('d/m/Y H:i', strtotime($comment['date_commentaire']));
                                    ?>
                                        <div class="comment-item">
                                            <p class="comment-meta">
                                                Posté par <strong><?php echo $full_name; ?></strong> le <?php echo $date_formatted; ?>
                                            </p>
                                            <p class="comment-content"><?php echo nl2br(htmlspecialchars($comment['comment_content'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                
                                <?php endif; ?>
                            </div>
                        </div>


                    </div>
                <?php else: ?>
                    <div class="chapter-content-card" style="text-align: center; border-left: 5px solid var(--accent);">
                        <h2>Sélectionnez un Chapitre</h2>
                        <p>Utilisez la barre latérale à gauche pour naviguer dans le contenu du cours.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    
<script src="../theme.js"></script>

    
</body>
</html>