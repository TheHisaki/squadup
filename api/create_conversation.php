<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

// Vérifier si un ID d'utilisateur est fourni
if (!isset($_GET['user_id'])) {
    header('Location: messages.php');
    exit;
}

$other_user_id = filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT);
$current_user_id = $_SESSION['user_id'];

// Rediriger vers la conversation avec l'utilisateur
header("Location: messages.php?user=" . $other_user_id);
exit;
?> 