<?php
// Inclure la configuration Vercel
require_once __DIR__ . '/vercel.php';

// Gérer les routes
$request_uri = $_SERVER['REQUEST_URI'];

// Si la page d'accueil est demandée
if ($request_uri == '/' || $request_uri == '/index.php') {
    // Inclure le fichier de configuration de la base de données
    require_once __DIR__ . '/config/database.php';
    
    // Vérifier si l'utilisateur est connecté
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit();
    }
    
    // Inclure la page d'accueil
    require_once __DIR__ . '/navbar.php';
    exit();
}

// Si le fichier existe, l'inclure
$file = __DIR__ . $request_uri;
if (file_exists($file) && is_file($file)) {
    require_once $file;
    exit();
}

// Sinon, rediriger vers la page d'accueil
header('Location: /');
exit(); 