<?php
// Créer les dossiers nécessaires
$baseDir = __DIR__ . '/vendor/phpmailer/phpmailer/src';
if (!file_exists($baseDir)) {
    mkdir($baseDir, 0777, true);
}

// URL des fichiers nécessaires de PHPMailer
$files = [
    'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
    'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
    'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
];

// Télécharger chaque fichier
foreach ($files as $filename => $url) {
    $content = file_get_contents($url);
    if ($content === false) {
        die("Erreur lors du téléchargement de $filename\n");
    }
    
    $filepath = $baseDir . '/' . $filename;
    if (file_put_contents($filepath, $content) === false) {
        die("Erreur lors de l'écriture de $filename\n");
    }
    echo "Fichier $filename téléchargé avec succès\n";
}

echo "Installation terminée !\n"; 