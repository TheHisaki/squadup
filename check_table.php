<?php
require_once 'config/database.php';

try {
    $stmt = $conn->query("SHOW CREATE TABLE friendships");
    $result = $stmt->fetch();
    var_dump($result);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?> 