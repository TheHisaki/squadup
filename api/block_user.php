<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

header('Content-Type: application/json');

if (!isset($_POST['user_id']) || !isset($_POST['action']) || !isset($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

// Vérifier le token CSRF
if (!verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

$user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
$action = $_POST['action'];

// Vérifier que l'utilisateur ne tente pas de se bloquer lui-même
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas vous bloquer vous-même']);
    exit;
}

try {
    if ($action === 'block') {
        // Vérifier si l'utilisateur n'est pas déjà bloqué
        $stmt = $conn->prepare("SELECT id FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet utilisateur est déjà bloqué']);
            exit;
        }

        // Vérifier si l'utilisateur à bloquer existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
            exit;
        }

        $conn->beginTransaction();
        try {
            // Bloquer l'utilisateur
            $stmt = $conn->prepare("INSERT INTO blocked_users (user_id, blocked_user_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $user_id]);

            // Supprimer toute relation d'amitié existante
            $stmt = $conn->prepare("DELETE FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);

            // Supprimer tous les messages entre les deux utilisateurs
            $stmt = $conn->prepare("DELETE FROM messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)");
            $stmt->execute([$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Utilisateur bloqué avec succès']);
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Erreur lors du blocage : " . $e->getMessage());
            throw $e;
        }
    } elseif ($action === 'unblock') {
        // Vérifier si l'utilisateur est bien bloqué
        $stmt = $conn->prepare("SELECT id FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet utilisateur n\'est pas bloqué']);
            exit;
        }

        // Débloquer l'utilisateur
        $stmt = $conn->prepare("DELETE FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);

        echo json_encode(['success' => true, 'message' => 'Utilisateur débloqué avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
    }
} catch (Exception $e) {
    error_log("Erreur dans block_user.php : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors du traitement de votre demande']);
} 