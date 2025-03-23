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
$user_id = filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
    exit;
}

try {
    // Supprimer les états de frappe expirés (plus de 5 secondes)
    $stmt = $conn->prepare("
        DELETE FROM typing_status 
        WHERE last_typed < DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ");
    $stmt->execute();

    // Vérifier si l'utilisateur est en train d'écrire
    $stmt = $conn->prepare("
        SELECT u.username 
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.id
        WHERE ts.user_id = ? 
        AND ts.typing_to_user_id = ?
        AND ts.last_typed >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ");
    $stmt->execute([$user_id, $_SESSION['user_id']]);
    $typing_user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'is_typing' => $typing_user !== false,
        'username' => $typing_user ? htmlspecialchars($typing_user['username']) : null
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la vérification du statut']);
} 