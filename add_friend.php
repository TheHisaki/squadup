<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier le token CSRF
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

// Récupérer l'ID de l'utilisateur à ajouter
$friend_id = filter_var($_POST['friend_id'], FILTER_SANITIZE_NUMBER_INT);

if (empty($friend_id)) {
    echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
    exit;
}

try {
    // Vérifier si une demande d'ami existe déjà
    $stmt = $conn->prepare("
        SELECT * FROM friendships 
        WHERE (user_id1 = ? AND user_id2 = ?) 
        OR (user_id1 = ? AND user_id2 = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Une demande d\'ami existe déjà']);
        exit;
    }

    // Créer la demande d'ami
    $stmt = $conn->prepare("
        INSERT INTO friendships (user_id1, user_id2, status, created_at) 
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $friend_id]);

    // Créer une notification pour l'utilisateur
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, content, related_user_id, created_at)
        VALUES (?, 'friend_request', 'Vous avez reçu une demande d\'ami', ?, NOW())
    ");
    $stmt->execute([$friend_id, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true, 
        'message' => 'Demande d\'ami envoyée avec succès'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'envoi de la demande d\'ami'
    ]);
} 