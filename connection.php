<?php

/**
 * Enregistre une activité dans la table logs.
 * @param mysqli $conn La connexion à la base de données.
 * @param int $id_utilisateur L'ID de l'utilisateur qui a effectué l'action.
 * @param string $action Description de l'action (ex: "Connexion réussie", "Création du cours 'PHP Avancé'").
 */

function log_activity($conn, $id_utilisateur, $action) {
    if (!$conn || !is_numeric($id_utilisateur) || empty($action)) {
        return; 
    }
    
    $sql = "INSERT INTO logs (id_utilisateur, action) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("is", $id_utilisateur, $action); 
    
    $stmt->execute();
    $stmt->close();
}


$host = "localhost";
$user = "root";
$pass = ""; 
$dbname = "sapientia";

$conn = new mysqli($host, $user, $pass, $dbname) or die("Connection failed: " . mysqli_connect_error());
?>
