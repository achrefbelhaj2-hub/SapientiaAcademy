<?php
session_start();

// =================================================================
// 1. INCLUSION DE LA CONNEXION BDD ET VÉRIFICATION DE SÉCURITÉ
// =================================================================

// Inclure votre fichier de connexion (doit contenir la variable $conn)
include "../connection.php"; 

// VERIFICATION DE SECURITE CRITIQUE POUR L'ADMINISTRATION
if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// L'utilisateur est connecté et est ADMINISTRATEUR.
$user_prenom = $_SESSION['prenom'] ?? ''; 
$user_nom = $_SESSION['nom'] ?? 'Admin';
$user_full_name = trim($user_prenom . ' ' . $user_nom);

if (empty($user_full_name)) {
    $user_full_name = 'Administrateur';
}

$display_name = htmlspecialchars($user_full_name);


// =================================================================
// 2. LOGIQUE DE RÉCUPÉRATION DES STATISTIQUES (MySQLi)
// =================================================================
$stats = [
    'users' => 'N/A',
    'courses' => 'N/A',
    'chapters' => 'N/A',
    'exercises' => 'N/A', 
    'today_signups' => 'N/A',
    'users_in_progress' => 'N/A' // NOUVEAU : Utilisateurs uniques ayant un cours non terminé
];

if ($conn) {
    try {
        // Total Users
        $result = $conn->query("SELECT COUNT(*) AS total_users FROM utilisateur");
        $stats['users'] = $result ? $result->fetch_assoc()['total_users'] : '0';

        // Total Courses
        $result = $conn->query("SELECT COUNT(*) AS total_courses FROM cours");
        $stats['courses'] = $result ? $result->fetch_assoc()['total_courses'] : '0';
        
        // Total Chapters
        $result = $conn->query("SELECT COUNT(*) AS total_chapters FROM chapitre");
        $stats['chapters'] = $result ? $result->fetch_assoc()['total_chapters'] : '0';
        
        // Total Exercises
        $result = $conn->query("SELECT COUNT(*) AS total_exercises FROM exercice");
        $stats['exercises'] = $result ? $result->fetch_assoc()['total_exercises'] : '0';
        
        // Inscriptions Aujourd'hui
        $result = $conn->query("SELECT COUNT(*) AS total_today_signups FROM utilisateur WHERE DATE(date_inscription) = CURDATE()");
        $stats['today_signups'] = $result ? $result->fetch_assoc()['total_today_signups'] : '0';
        
        // OPTIMISÉ: Utilisateurs en Cours d'Apprentissage
        // Compte le nombre d'utilisateurs uniques qui ont au moins un enregistrement 'progression' non terminé (est_termine = 0)
        $result = $conn->query("SELECT COUNT(DISTINCT id_utilisateur) AS users_in_progress FROM progression WHERE est_termine = 0");
        $stats['users_in_progress'] = $result ? $result->fetch_assoc()['users_in_progress'] : '0';
        
    } catch (Exception $e) {
        error_log("Erreur BDD Stats: " . $e->getMessage());
        // Les stats resteront 'N/A'
    }
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Administration</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/pages/admin_dashboard.css">
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
        <h1>Tableau de Bord <span class="role-badge">ADMIN</span></h1>
        <p>Bonjour **<?php echo $display_name; ?>**. Utilisez ce panneau pour gérer les utilisateurs, les cours et les statistiques du système.</p>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ccc;">

        <h2>Statistiques Globales</h2>
        
        <div class="admin-stat-grid">
            <div class="stat-card">
                <h3>Utilisateurs Totaux</h3>
                <p><?php echo $stats['users']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Cours Actifs</h3>
                <p><?php echo $stats['courses']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Chapitres Totaux</h3>
                <p><?php echo $stats['chapters']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Exercices Totaux</h3>
                <p><?php echo $stats['exercises']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Inscriptions Aujourd'hui</h3>
                <p><?php echo $stats['today_signups']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Utilisateurs en Cours</h3>
                <p><?php echo $stats['users_in_progress']; ?></p>
            </div>
        </div>
        
        <h2 style="margin-top: 30px;">Actions Administratives</h2>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <a href="admin_manage_users.php" class="action-link btn-users">Gérer Utilisateurs</a>
            
            <a href="admin_manage_courses.php" class="action-link btn-course">Gérer Cours & Chapitres</a>
            
            <a href="admin_view_logs.php" class="action-link btn-logs">Rapports & Logs</a>
        </div>
    </div>

    

<script src="../theme.js"></script>

    

</body>
</html>