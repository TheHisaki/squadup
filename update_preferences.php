<?php
require_once 'config/database.php';
require_once 'config/security.php';

// Vérifier si l'utilisateur est connecté
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action.']);
    exit;
}

// Vérifier le jeton CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Récupérer les données du formulaire
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $platform = filter_input(INPUT_POST, 'platform', FILTER_SANITIZE_STRING);
    $availability = $_POST['availability'] ?? [];
    $games = $_POST['games'] ?? [];
    $skillLevels = $_POST['skill_levels'] ?? [];

    // Convertir le tableau de disponibilités en chaîne
    $playtimes = is_array($availability) ? implode(', ', $availability) : '';

    // Début de la transaction
    $conn->beginTransaction();

    // Mettre à jour les informations de l'utilisateur
    $stmt = $conn->prepare("UPDATE users SET location = ?, platform = ?, playtimes = ? WHERE id = ?");
    $stmt->execute([$location, $platform, $playtimes, $userId]);

    // Supprimer les anciennes préférences de jeux
    $stmt = $conn->prepare("DELETE FROM user_game_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Ajouter les nouvelles préférences de jeux
    if (!empty($games)) {
        $stmt = $conn->prepare("INSERT INTO user_game_preferences (user_id, game_id, skill_level) VALUES (?, ?, ?)");
        foreach ($games as $index => $gameId) {
            $skillLevel = $skillLevels[$index] ?? 'Débutant';
            $stmt->execute([$userId, $gameId, $skillLevel]);
        }
    }

    // Valider la transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollBack();
    error_log("Erreur de base de données: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour des préférences.']);
} 