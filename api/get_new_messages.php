<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

header('Content-Type: application/json');

// Récupérer les paramètres
$last_id = filter_var($_GET['last_id'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
$other_user_id = filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT);

if (!$other_user_id) {
    echo json_encode(['error' => 'ID utilisateur invalide']);
    exit;
}

try {
    // Récupérer les nouveaux messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.avatar
        FROM messages m
        JOIN users u ON m.from_user_id = u.id
        WHERE m.id > ?
        AND (
            (m.from_user_id = ? AND m.to_user_id = ?)
            OR (m.from_user_id = ? AND m.to_user_id = ?)
        )
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([
        $last_id,
        $_SESSION['user_id'],
        $other_user_id,
        $other_user_id,
        $_SESSION['user_id']
    ]);
    $messages = $stmt->fetchAll();

    // Formater les messages pour la réponse
    $formatted_messages = array_map(function($message) {
        return [
            'id' => $message['id'],
            'content' => htmlspecialchars($message['content']),
            'from_user_id' => $message['from_user_id'],
            'username' => htmlspecialchars($message['username']),
            'avatar' => $message['avatar'] ?? 'assets/images/default-avatar.png',
            'time' => date('H:i', strtotime($message['created_at']))
        ];
    }, $messages);

    // Marquer les messages comme lus s'ils sont destinés à l'utilisateur actuel
    if (!empty($messages)) {
        $stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE id > ? 
            AND to_user_id = ? 
            AND from_user_id = ?
        ");
        $stmt->execute([$last_id, $_SESSION['user_id'], $other_user_id]);
    }

    echo json_encode(['success' => true, 'messages' => $formatted_messages]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des messages']);
} 