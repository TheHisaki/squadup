<?php
// Activer l'affichage des erreurs en mode debug
if (getenv('NOW_PHP_DEBUG')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Configuration des en-têtes
header('X-Powered-By: Vercel PHP');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Charger l'autoloader de Composer s'il existe
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Inclure les fichiers de configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php'; 