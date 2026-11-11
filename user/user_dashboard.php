<?php
session_start();


include "../connection.php"; 

if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../admin/admin_dashboard.php");
    } else {
        header("Location: ../login.php");
    }
    exit();
}

$user_id = $_SESSION['id'];
$display_prenom = htmlspecialchars($_SESSION['prenom'] ?? 'Utilisateur');
$display_nom = htmlspecialchars($_SESSION['nom'] ?? '');

$display_full_name = trim($display_prenom . ' ' . $display_nom);
if (empty($display_full_name)) {
    $display_full_name = 'Utilisateur'; 
}

$stats = [
    'courses_completed' => 0,
    'total_courses_started' => 0,
    'total_exercises_completed' => 0,
    'completion_rate' => 0,
    'courses_list' => []
];



$sql_user_courses = "SELECT id_cours FROM progression WHERE id_utilisateur = ?";
$stmt_user_courses = $conn->prepare($sql_user_courses);
$stmt_user_courses->bind_param("i", $user_id);
$stmt_user_courses->execute();
$result_user_courses = $stmt_user_courses->get_result();

while ($row = $result_user_courses->fetch_assoc()) {
    $current_course_id = $row['id_cours'];
    
    $sql_total_exercises = "
        SELECT COUNT(e.id_exercice) AS total 
        FROM exercice e 
        JOIN chapitre c ON e.id_chapitre = c.id_chapitre 
        WHERE c.id_cours = ?";
    $stmt_total = $conn->prepare($sql_total_exercises);
    $stmt_total->bind_param("i", $current_course_id);
    $stmt_total->execute();
    $total_exercises = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_total->close();

    $sql_completed_exercises = "
        SELECT COUNT(DISTINCT ep.id_exercice) AS completed 
        FROM exercice_progression ep 
        JOIN exercice e ON ep.id_exercice = e.id_exercice 
        JOIN chapitre c ON e.id_chapitre = c.id_chapitre 
        WHERE c.id_cours = ? AND ep.id_utilisateur = ? AND ep.statut = 'termine'";
    $stmt_completed = $conn->prepare($sql_completed_exercises);
    $stmt_completed->bind_param("ii", $current_course_id, $user_id);
    $stmt_completed->execute();
    $completed_exercises = $stmt_completed->get_result()->fetch_assoc()['completed'] ?? 0;
    $stmt_completed->close();

  
    $new_status = ($total_exercises > 0 && $completed_exercises >= $total_exercises) ? 1 : 0;

    $sql_update_progress = "UPDATE progression SET est_termine = ? WHERE id_utilisateur = ? AND id_cours = ?";
    $stmt_update_progress = $conn->prepare($sql_update_progress);
    $stmt_update_progress->bind_param("iii", $new_status, $user_id, $current_course_id);
    $stmt_update_progress->execute();
    $stmt_update_progress->close();
}
$stmt_user_courses->close(); 


$sql_started = "SELECT COUNT(DISTINCT id_cours) AS total_started FROM progression WHERE id_utilisateur = ?";
$stmt_started = $conn->prepare($sql_started);
$stmt_started->bind_param("i", $user_id);
$stmt_started->execute();
$stats['total_courses_started'] = $stmt_started->get_result()->fetch_assoc()['total_started'] ?? 0;
$stmt_started->close();

$sql_completed = "SELECT COUNT(id_cours) AS total_completed FROM progression WHERE id_utilisateur = ? AND est_termine = 1";
$stmt_completed = $conn->prepare($sql_completed);
$stmt_completed->bind_param("i", $user_id);
$stmt_completed->execute();
$stats['courses_completed'] = $stmt_completed->get_result()->fetch_assoc()['total_completed'] ?? 0;
$stmt_completed->close();

if ($stats['total_courses_started'] > 0) {
    $stats['completion_rate'] = round(($stats['courses_completed'] / $stats['total_courses_started']) * 100);
} else {
    $stats['completion_rate'] = 0;
}

$sql_exercises = "SELECT COUNT(id) AS total_success FROM exercice_progression WHERE id_utilisateur = ? AND statut = 'termine'";
$stmt_exercises = $conn->prepare($sql_exercises);
$stmt_exercises->bind_param("i", $user_id);
$stmt_exercises->execute();
$stats['total_exercises_completed'] = $stmt_exercises->get_result()->fetch_assoc()['total_success'] ?? 0;
$stmt_exercises->close();



$sql_courses = "
    SELECT 
        c.id_cours, 
        c.titre, 
        c.description, 
        (SELECT COUNT(ch.id_chapitre) FROM chapitre ch WHERE ch.id_cours = c.id_cours) AS total_chapitres,
        p.est_termine
    FROM 
        cours c
    LEFT JOIN 
        progression p ON c.id_cours = p.id_cours AND p.id_utilisateur = ?
    ORDER BY 
        -- Utilise 'dernier_acces'
        p.dernier_acces DESC, c.titre ASC";

$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->bind_param("i", $user_id);
$stmt_courses->execute();
$result_courses = $stmt_courses->get_result();

while ($row = $result_courses->fetch_assoc()) {
    $row['progression_percentage'] = 0;
    
    if ($row['est_termine'] !== null) {
        
        
        $current_course_id = $row['id_cours'];
        
        $sql_total_exercises_check = "
            SELECT COUNT(e.id_exercice) AS total 
            FROM exercice e 
            JOIN chapitre c ON e.id_chapitre = c.id_chapitre 
            WHERE c.id_cours = ?";
        $stmt_total_check = $conn->prepare($sql_total_exercises_check);
        $stmt_total_check->bind_param("i", $current_course_id);
        $stmt_total_check->execute();
        $total_exercises = $stmt_total_check->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt_total_check->close();

        $sql_completed_exercises_check = "
            SELECT COUNT(DISTINCT ep.id_exercice) AS completed 
            FROM exercice_progression ep 
            JOIN exercice e ON ep.id_exercice = e.id_exercice 
            JOIN chapitre c ON e.id_chapitre = c.id_chapitre 
            WHERE c.id_cours = ? AND ep.id_utilisateur = ? AND ep.statut = 'termine'";
        $stmt_completed_check = $conn->prepare($sql_completed_exercises_check);
        $stmt_completed_check->bind_param("ii", $current_course_id, $user_id);
        $stmt_completed_check->execute();
        $completed_exercises = $stmt_completed_check->get_result()->fetch_assoc()['completed'] ?? 0;
        $stmt_completed_check->close();

        if ($total_exercises > 0) {
            $row['progression_percentage'] = round(($completed_exercises / $total_exercises) * 100);
        } elseif ($row['total_chapitres'] > 0) {
            $row['progression_percentage'] = 100;
        }

    }
    
    
    $stats['courses_list'][] = $row;
}

$stmt_courses->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tableau de Bord - <?php echo $display_prenom; ?></title> 
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/pages/user_dashboard.css">
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

    <div class="dashboard-container">
        <h1>Mon Tableau de Bord 🚀</h1>

        <div class="stat-grid">
            <div class="stat-card">
                <h3>Cours Commencés</h3>
                <p><?php echo htmlspecialchars($stats['total_courses_started']); ?></p>
            </div>
            <div class="stat-card">
                <h3>Cours Terminés</h3>
                <p><?php echo htmlspecialchars($stats['courses_completed']); ?></p>
            </div>
            <div class="stat-card rate">
                <h3>Progression Globale</h3>
                <p><?php echo htmlspecialchars($stats['completion_rate']); ?>%</p>
            </div>
            <div class="stat-card">
                <h3>Exercices Réussis</h3>
                <p><?php echo htmlspecialchars($stats['total_exercises_completed']); ?></p>
            </div>
        </div>

        <div class="course-list">
            <h2>Mes Cours et Progression</h2>

            <?php if (empty($stats['courses_list'])): ?>
                <p style="text-align: center; padding: 30px; background: white; border-radius: 10px;">
                    Vous n'avez pas encore de cours disponibles.
                </p>
            <?php else: ?>
                <?php foreach ($stats['courses_list'] as $course): 
                    $is_completed = ($course['est_termine'] == 1);
                    $btn_text = $is_completed ? "Voir le Cours ✅" : "Continuer →";
                    $status_class = $is_completed ? "completed" : "in-progress";
                ?>
                    <div class="course-card <?php echo $status_class; ?>">
                        <div class="details">
                            <h3><?php echo htmlspecialchars($course['titre']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($course['description'] ?? 'Pas de description.', 0, 100)); ?>...</p>
                            <small style="color:#94a3b8;">Total Chapitres: <?php echo htmlspecialchars($course['total_chapitres']); ?></small>
                        </div>
                        
                        <div class="progress-container">
                            <small>Progression : <?php echo htmlspecialchars($course['progression_percentage']); ?>%</small>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo htmlspecialchars($course['progression_percentage']); ?>%;"></div>
                            </div>
                        </div>
                        
                        <a href="view_course.php?id=<?php echo htmlspecialchars($course['id_cours']); ?>" class="btn-continue">
                            <?php echo $btn_text; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>