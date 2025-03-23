<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Vérifier le token CSRF
verify_csrf_token($_POST['csrf_token']);

// Récupérer et nettoyer les données
$message = clean_input($_POST['message']);
$to_user_id = filter_var($_POST['to_user_id'], FILTER_SANITIZE_NUMBER_INT);

// Vérifier si les données sont valides
if (empty($message) || !$to_user_id) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit;
}

try {
    // Vérifier si l'utilisateur est bloqué
    $stmt = $conn->prepare("
        SELECT id FROM blocked_users 
        WHERE (user_id = ? AND blocked_user_id = ?)
        OR (user_id = ? AND blocked_user_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $to_user_id, $to_user_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Impossible d\'envoyer un message à cet utilisateur']);
        exit;
    }

    // Insérer le message
    $stmt = $conn->prepare("
        INSERT INTO messages (from_user_id, to_user_id, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $to_user_id, $message]);
    
    // Retourner la réponse
    echo json_encode([
        'success' => true,
        'message_id' => $conn->lastInsertId(),
        'time' => date('H:i')
    ]);

} catch (PDOException $e) {
    error_log('Erreur SQL: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'envoi du message']);
} 