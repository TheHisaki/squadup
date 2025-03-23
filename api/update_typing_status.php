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

// Récupérer les paramètres
$to_user_id = filter_var($_POST['to_user_id'], FILTER_SANITIZE_NUMBER_INT);
$is_typing = filter_var($_POST['is_typing'], FILTER_VALIDATE_BOOLEAN);

if (!$to_user_id) {
    echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
    exit;
}

try {
    if ($is_typing) {
        // Mettre à jour ou insérer l'état de frappe
        $stmt = $conn->prepare("
            INSERT INTO typing_status (user_id, typing_to_user_id, last_typed)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_typed = NOW()
        ");
        $stmt->execute([$_SESSION['user_id'], $to_user_id]);
    } else {
        // Supprimer l'état de frappe
        $stmt = $conn->prepare("
            DELETE FROM typing_status 
            WHERE user_id = ? AND typing_to_user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $to_user_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du statut']);
} 