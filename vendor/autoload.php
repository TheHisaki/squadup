<?php
// Autoload simple pour PHPMailer
spl_autoload_register(function ($class) {
    // Namespace de base pour PHPMailer
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    // Vérifier si la classe utilise le namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtenir le nom relatif de la classe
    $relative_class = substr($class, $len);

    // Remplacer le namespace par le chemin du dossier
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si le fichier existe, le charger
    if (file_exists($file)) {
        require $file;
    }
}); 