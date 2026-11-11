<?php

session_start();

// Connexion à la base ($conn) et fonction log_activity()
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    //protège contre l'injection SQL
    $stmt = $conn->prepare(
        "SELECT id_utilisateur, prenom, nom, role, mot_de_passe
         FROM utilisateur
         WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // password verify car tous les mdps sont hachees
    if ($row && password_verify($password, $row['mot_de_passe'])) {

        if (password_needs_rehash($row['mot_de_passe'], PASSWORD_DEFAULT)) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?");
            $upd->bind_param("si", $new_hash, $row['id_utilisateur']);
            $upd->execute();
            $upd->close();
        }

        session_regenerate_id(true);

        $_SESSION['id']       = $row['id_utilisateur'];
        $_SESSION['nom']      = $row['nom'];
        $_SESSION['role']     = $row['role'];
        $_SESSION['prenom']   = $row['prenom'];
        $_SESSION['loggedin'] = true;

        log_activity($conn, $row['id_utilisateur'], "Connexion réussie");

        // Redirection selon le rôle
        if ($row['role'] == "admin") {
            header("Location: admin/admin_dashboard.php");
        } else {
            header("Location: user/user_dashboard.php");
        }
        exit();
    }

    // email ou mdp invalide
    header("Location: login.html?error=1");
    exit();
}
?>