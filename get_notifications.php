<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$notifications = [];

// Récupérer les demandes d'ami non lues
$stmt = $conn->prepare("
    SELECT 
        f.sender_id as user_id,
        f.created_at,
        u.username,
        'friend_request' as type
    FROM friendships f
    JOIN users u ON f.sender_id = u.id
    WHERE f.receiver_id = ? AND f.status = 'pending'
");
$stmt->execute([$user_id]);
$friend_requests = $stmt->fetchAll();

foreach ($friend_requests as $request) {
    $notifications[] = [
        'id' => 'fr_' . $request['user_id'],
        'title' => 'Nouvelle demande d\'ami',
        'message' => $request['username'] . ' souhaite vous ajouter en ami',
        'created_at' => $request['created_at'],
        'type' => 'friend_request',
        'is_read' => false,
        'data' => [
            'user_id' => $request['user_id']
        ]
    ];
}

// Récupérer les messages non lus
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.from_user_id,
        m.content,
        m.created_at,
        u.username,
        'message' as type
    FROM messages m
    JOIN users u ON m.from_user_id = u.id
    WHERE m.to_user_id = ? AND m.is_read = 0
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchAll();

foreach ($unread_messages as $message) {
    $notifications[] = [
        'id' => 'msg_' . $message['id'],
        'title' => 'Nouveau message',
        'message' => 'Message de ' . $message['username'] . ': ' . substr($message['content'], 0, 50) . '...',
        'created_at' => $message['created_at'],
        'type' => 'message',
        'is_read' => false,
        'data' => [
            'user_id' => $message['from_user_id']
        ]
    ];
}

// Récupérer les notifications de la table notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$db_notifications = $stmt->fetchAll();

foreach ($db_notifications as $notif) {
    $notifications[] = [
        'id' => 'notif_' . $notif['id'],
        'title' => $notif['title'],
        'message' => $notif['message'],
        'created_at' => $notif['created_at'],
        'type' => $notif['type'],
        'is_read' => false
    ];
}

// Trier les notifications par date (les plus récentes en premier)
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

echo json_encode($notifications); 