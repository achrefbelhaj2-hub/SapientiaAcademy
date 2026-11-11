<?php
session_start(); 
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: home.html"); 
    exit();
}


$nom = $_POST["nom"] ?? '';
$prenom = $_POST["prenom"] ?? '';
$numero = $_POST["tel"] ?? '';
$email = $_POST["email"] ?? '';
$password_plain = $_POST["password"] ?? '';


$email = trim($email);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password_plain) < 8) {
    echo "Données invalides (email incorrect ou mot de passe de moins de 8 caractères). <a href='signup.html'>Retour</a>";
    exit();
}

// Hash sécurisé du mdp cryptee
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
$role = 'eleve';


$sql_check = "SELECT id_utilisateur FROM utilisateur WHERE email = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if($result_check->num_rows > 0) {
    echo "Email déjà utilisé. <a href='login.html'>Se connecter</a>";
    $stmt_check->close();
}
else {
    $stmt_check->close(); 

    
    $sql_insert = "INSERT INTO utilisateur (nom, prenom, numero, email, mot_de_passe, role) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ssssss", $nom, $prenom, $numero, $email, $password_hashed, $role);
    
    if($stmt_insert->execute()) {
        
    
        $new_user_id = $conn->insert_id; 
        
        // Connexion automatique si linscription reussit
        session_regenerate_id(true);
        $_SESSION['id'] = $new_user_id;
        $_SESSION['nom'] = $nom;
        $_SESSION['prenom'] = $prenom;
        $_SESSION['role'] = $role;
        $_SESSION['loggedin'] = true; 

        log_activity($conn, $new_user_id, "Nouvelle inscription et connexion automatique (Élève): " . $email);
        
        $stmt_insert->close();

        // vers tableau de bord user
        header("Location: user/user_dashboard.php"); 
        exit(); 
        
    } else {
        echo "Erreur lors de l'inscription: " . $stmt_insert->error;
        $stmt_insert->close();
    }
}

mysqli_close($conn);

?>