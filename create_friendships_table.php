<?php
require_once 'config/database.php';

try {
    // Supprimer la table si elle existe
    $conn->exec("DROP TABLE IF EXISTS friendships");

    // Créer la table avec la bonne structure
    $sql = "CREATE TABLE friendships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_friendship (sender_id, receiver_id)
    )";

    $conn->exec($sql);
    echo "Table friendships créée avec succès !";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?> 