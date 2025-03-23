<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

header('Content-Type: application/json');

if (!isset($_POST['action']) || !isset($_POST['friend_id']) || !isset($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

// Vérifier le token CSRF
if (!verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

$action = $_POST['action'];
$friend_id = filter_var($_POST['friend_id'], FILTER_SANITIZE_NUMBER_INT);

try {
    // Vérifier si l'un des utilisateurs a bloqué l'autre
    $stmt = $conn->prepare("
        SELECT id FROM blocked_users 
        WHERE (user_id = ? AND blocked_user_id = ?)
        OR (user_id = ? AND blocked_user_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Impossible d\'ajouter cet utilisateur']);
        exit;
    }

    switch ($action) {
        case 'add':
            // Vérifier si une relation d'amitié existe déjà
            $stmt = $conn->prepare("
                SELECT status FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?)
                OR (sender_id = ? AND receiver_id = ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Une relation d\'amitié existe déjà avec cet utilisateur']);
                exit;
            }

            // Créer la demande d'ami
            $stmt = $conn->prepare("INSERT INTO friendships (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $stmt->execute([$_SESSION['user_id'], $friend_id]);
            echo json_encode(['success' => true, 'message' => 'Demande d\'ami envoyée']);
            break;

        case 'accept':
            $stmt = $conn->prepare("
                UPDATE friendships 
                SET status = 'accepted' 
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
            ");
            $stmt->execute([$friend_id, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Demande d\'ami acceptée']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Demande d\'ami introuvable']);
            }
            break;

        case 'reject':
            $stmt = $conn->prepare("
                DELETE FROM friendships 
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
            ");
            $stmt->execute([$friend_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'message' => 'Demande d\'ami refusée']);
            break;

        case 'remove':
            $stmt = $conn->prepare("
                DELETE FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?)
                OR (sender_id = ? AND receiver_id = ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'message' => 'Ami retiré avec succès']);
            break;

        case 'check':
            $stmt = $conn->prepare("SELECT * FROM friendships WHERE 
                (sender_id = ? AND receiver_id = ?) OR 
                (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                $friendship = $stmt->fetch();
                echo json_encode([
                    'success' => true,
                    'status' => $friendship['status'],
                    'is_sender' => $friendship['sender_id'] == $_SESSION['user_id']
                ]);
            } else {
                echo json_encode(['success' => true, 'status' => 'none']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non valide']);
    }
} catch (Exception $e) {
    error_log("Erreur dans friends.php : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
} 