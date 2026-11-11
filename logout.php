<?php
session_start();

include "connection.php"; 


if (isset($_SESSION['id']) && $_SESSION['loggedin'] === true) {
    
    $user_id = $_SESSION['id'];
    log_activity($conn, $user_id, "Déconnexion manuelle réussie"); 
}


$_SESSION = array();


session_destroy();

// back to login
header("Location: login.html"); 
exit();
?>