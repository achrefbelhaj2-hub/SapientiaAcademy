<?php
session_start();
include "../connection.php"; 

// =================================================================
// 1. VÉRIFICATION DE SÉCURITÉ
// =================================================================
if (!isset($_SESSION['id']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Nettoyage de l'ID à supprimer
$id_utilisateur = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$redirect_url = "admin_manage_users.php";

if (!$id_utilisateur) {
    header("Location: $redirect_url?error=" . urlencode("ID utilisateur manquant ou invalide."));
    exit();
}

// Empêcher l'administrateur de se supprimer lui-même
if ($id_utilisateur == $_SESSION['id']) {
    header("Location: $redirect_url?error=" . urlencode("Impossible de supprimer le compte administrateur actuellement connecté."));
    exit();
}

// =================================================================
// 2. LOGIQUE DE SUPPRESSION AVEC TRANSACTION
// =================================================================
try {
    // Démarrer la transaction pour garantir l'intégrité (tout ou rien)
    $conn->begin_transaction();

    // L'ordre des suppressions est CRUCIAL car les dépendances doivent être résolues
    
    // 1. Suppression des progressions d'exercices
    $stmt_exo_prog = $conn->prepare("DELETE FROM exercice_progression WHERE id_utilisateur = ?");
    $stmt_exo_prog->bind_param("i", $id_utilisateur);
    $stmt_exo_prog->execute();
    $stmt_exo_prog->close();

    // 2. Suppression des commentaires (même si c'est la seule FK, autant la traiter)
    $stmt_comments = $conn->prepare("DELETE FROM commentaire WHERE id_utilisateur = ?");
    $stmt_comments->bind_param("i", $id_utilisateur);
    $stmt_comments->execute();
    $stmt_comments->close();

    // 3. Suppression des enregistrements de progression (table 'progression')
    $stmt_progression = $conn->prepare("DELETE FROM progression WHERE id_utilisateur = ?");
    $stmt_progression->bind_param("i", $id_utilisateur);
    $stmt_progression->execute();
    $stmt_progression->close();
    
    // 4. Suppression des logs de l'utilisateur (table 'logs')
    $stmt_logs = $conn->prepare("DELETE FROM logs WHERE id_utilisateur = ?");
    $stmt_logs->bind_param("i", $id_utilisateur);
    $stmt_logs->execute();
    $stmt_logs->close();
    
    // 5. Suppression de l'utilisateur lui-même (table 'utilisateur')
    $sql_delete_user = "DELETE FROM utilisateur WHERE id_utilisateur = ?";
    $stmt_delete_user = $conn->prepare($sql_delete_user);
    $stmt_delete_user->bind_param("i", $id_utilisateur);
    $stmt_delete_user->execute();

    if ($stmt_delete_user->affected_rows > 0) {
        $conn->commit(); // Valider toutes les opérations
        // Optionnel : Enregistrer l'action dans les logs de l'administrateur courant
        // log_action($conn, $_SESSION['id'], 'USER_DELETED', "Suppression de l'utilisateur ID: $id_utilisateur");
        header("Location: $redirect_url?success=" . urlencode("Le compte utilisateur (ID: $id_utilisateur) et toutes ses données ont été supprimés."));
    } else {
        $conn->rollback(); // Annuler si l'utilisateur n'existe pas ou n'a pas pu être supprimé
        header("Location: $redirect_url?error=" . urlencode("Utilisateur introuvable ou aucune suppression effectuée."));
    }
    $stmt_delete_user->close();

} catch (Exception $e) {
    $conn->rollback(); // Annuler en cas d'erreur de base de données
    error_log("Erreur critique lors de la suppression du compte : " . $e->getMessage());
    header("Location: $redirect_url?error=" . urlencode("Erreur critique lors de la suppression du compte."));
}
exit();
?>