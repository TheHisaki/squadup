<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test des permissions des dossiers...<br>";

// Test du dossier logs
if (is_writable('logs')) {
    echo "✅ Le dossier 'logs' est accessible en écriture<br>";
    $test_file = 'logs/test.txt';
    if (file_put_contents($test_file, 'Test') !== false) {
        echo "✅ Test d'écriture dans 'logs' réussi<br>";
        unlink($test_file);
    } else {
        echo "❌ Impossible d'écrire dans le dossier 'logs'<br>";
    }
} else {
    echo "❌ Le dossier 'logs' n'est pas accessible en écriture<br>";
}

// Test du dossier uploads/avatars
if (is_writable('uploads/avatars')) {
    echo "✅ Le dossier 'uploads/avatars' est accessible en écriture<br>";
    $test_file = 'uploads/avatars/test.txt';
    if (file_put_contents($test_file, 'Test') !== false) {
        echo "✅ Test d'écriture dans 'uploads/avatars' réussi<br>";
        unlink($test_file);
    } else {
        echo "❌ Impossible d'écrire dans le dossier 'uploads/avatars'<br>";
    }
} else {
    echo "❌ Le dossier 'uploads/avatars' n'est pas accessible en écriture<br>";
}

// Test de la configuration PHP
echo "<br>Configuration PHP :<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "display_errors: " . ini_get('display_errors') . "<br>";
echo "error_reporting: " . ini_get('error_reporting') . "<br>";
?> 