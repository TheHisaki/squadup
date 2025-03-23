<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

// Vérifier si un ID d'utilisateur est fourni
if (!isset($_GET['id'])) {
    header('Location: search.php');
    exit;
}

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_GET['id']]);
$profile_user = $stmt->fetch();

// Debug information
echo "<!-- Debug info:
Requested user ID: " . $_GET['id'] . "
Current user ID: " . $_SESSION['user_id'] . "
Fetched user info: " . print_r($profile_user, true) . "
-->";

if (!$profile_user) {
    header('Location: search.php');
    exit;
}

// Compter le nombre d'amis
$stmt = $conn->prepare("
    SELECT COUNT(*) as friends_count
    FROM friendships
    WHERE (sender_id = ? OR receiver_id = ?)
    AND status = 'accepted'
");
$stmt->execute([$profile_user['id'], $profile_user['id']]);
$friends = $stmt->fetch();
$profile_user['friends_count'] = $friends['friends_count'];

// Vérifier le statut d'amitié
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN status = 'pending' AND sender_id = ? THEN 'sent'
            WHEN status = 'pending' AND receiver_id = ? THEN 'received'
            WHEN status = 'accepted' THEN 'friends'
            ELSE 'none'
        END as friendship_status
    FROM friendships
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?)
    LIMIT 1
");
$stmt->execute([
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $profile_user['id'],
    $profile_user['id'],
    $_SESSION['user_id']
]);
$friendship = $stmt->fetch();
$profile_user['friendship_status'] = $friendship ? $friendship['friendship_status'] : 'none';

// Récupérer les préférences de jeu de l'utilisateur
$stmt = $conn->prepare("
    SELECT g.name, ugp.skill_level, ugp.preferred_playtime 
    FROM user_game_preferences ugp 
    JOIN games g ON ugp.game_id = g.id 
    WHERE ugp.user_id = ?
");
$stmt->execute([$profile_user['id']]);
$game_preferences = $stmt->fetchAll();

// Traitement des demandes d'ami
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token($_POST['csrf_token']);
    
    switch ($_POST['action']) {
        case 'send_request':
            $stmt = $conn->prepare("INSERT INTO friendships (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $_GET['id']]);
            header("Location: view_profile.php?id=" . $_GET['id']);
            exit;
            break;
            
        case 'accept_request':
            $stmt = $conn->prepare("UPDATE friendships SET status = 'accepted' WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
            header("Location: view_profile.php?id=" . $_GET['id']);
            exit;
            break;
            
        case 'reject_request':
            $stmt = $conn->prepare("DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
            header("Location: view_profile.php?id=" . $_GET['id']);
            exit;
            break;
            
        case 'remove_friend':
            $stmt = $conn->prepare("DELETE FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$_SESSION['user_id'], $_GET['id'], $_GET['id'], $_SESSION['user_id']]);
            header("Location: view_profile.php?id=" . $_GET['id']);
            exit;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de <?php echo htmlspecialchars($profile_user['username']); ?> - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 120px auto;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: grid;
            gap: 2rem;
        }

        .profile-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .section-title {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            font-size: 1rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }

        .friend-actions {
            margin-top: 1rem;
        }

        .friend-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .friend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }

        .friend-btn.pending {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .info-item {
            background: rgba(138, 43, 226, 0.1);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--primary-color);
        }

        .info-item h3 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .info-item p {
            color: var(--text-color);
        }

        .game-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .game-item {
            background: rgba(138, 43, 226, 0.1);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .game-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.2);
        }

        .game-item h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .game-item p {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .availability-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        .availability-item {
            flex: 0 1 auto;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1.2rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(138, 43, 226, 0.2);
        }

        .availability-item i {
            color: white;
            font-size: 1.1rem;
        }

        .friend-request-buttons {
            display: flex;
            gap: 1rem;
        }

        .accept-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .reject-btn {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
        }

        .friend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="profile-container">
        <div class="profile-section">
            <div class="profile-header">
                <div class="avatar-container">
                    <img src="<?php echo $profile_user['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="avatar">
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($profile_user['username']); ?></h1>
                    <p>Membre depuis le <?php echo date('d/m/Y', strtotime($profile_user['created_at'])); ?></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo ($profile_user['friends_count'] ?? 0); ?> amis</span>
                        </div>
                    </div>

                    <?php if ($profile_user['id'] !== $_SESSION['user_id']): ?>
                        <div class="friend-actions">
                            <input type="hidden" id="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <?php if ($profile_user['friendship_status'] === 'received'): ?>
                                <div class="friend-request-buttons">
                                    <button onclick="toggleFriendship(<?php echo $profile_user['id']; ?>, 'accept')" class="friend-btn accept-btn" id="friend-btn-<?php echo $profile_user['id']; ?>">
                                        <i class="fas fa-check"></i> Accepter
                                    </button>
                                    <button onclick="toggleFriendship(<?php echo $profile_user['id']; ?>, 'reject')" class="friend-btn reject-btn">
                                        <i class="fas fa-times"></i> Refuser
                                    </button>
                                </div>
                            <?php else: ?>
                                <button onclick="toggleFriendship(<?php echo $profile_user['id']; ?>)" class="friend-btn" id="friend-btn-<?php echo $profile_user['id']; ?>">
                                    <?php if ($profile_user['friendship_status'] === 'none'): ?>
                                        <i class="fas fa-user-plus"></i> Ajouter en ami
                                    <?php elseif ($profile_user['friendship_status'] === 'sent'): ?>
                                        <i class="fas fa-clock"></i> En attente
                                    <?php elseif ($profile_user['friendship_status'] === 'friends'): ?>
                                        <i class="fas fa-user-minus"></i> Retirer des amis
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <script>
                        async function toggleFriendship(friendId, action = null) {
                            try {
                                const btn = document.getElementById(`friend-btn-${friendId}`);
                                if (!btn) {
                                    throw new Error('Bouton non trouvé');
                                }

                                const csrfTokenInput = document.getElementById('csrf_token');
                                if (!csrfTokenInput) {
                                    throw new Error('Token de sécurité non trouvé');
                                }

                                const formData = new FormData();
                                
                                // Si une action est spécifiée (accept/reject), l'utiliser
                                // Sinon, déterminer l'action en fonction du texte du bouton
                                if (!action) {
                                    const currentText = btn.textContent.trim();
                                    if (currentText.includes('Retirer')) {
                                        action = 'remove';
                                    } else if (currentText.includes('En attente')) {
                                        action = 'add'; // Pour annuler une demande en attente
                                    } else if (currentText.includes('Ajouter')) {
                                        action = 'add';
                                    }
                                }
                                
                                formData.append('action', action);
                                formData.append('friend_id', friendId);
                                formData.append('csrf_token', csrfTokenInput.value);
                                
                                // Désactiver tous les boutons liés à cet utilisateur
                                const buttons = document.querySelectorAll('.friend-btn, .friend-request-buttons button');
                                buttons.forEach(button => {
                                    button.disabled = true;
                                    button.style.opacity = '0.7';
                                });
                                
                                const originalContent = btn.innerHTML;
                                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                                
                                const response = await fetch('friends.php', {
                                    method: 'POST',
                                    body: formData
                                });

                                if (!response.ok) {
                                    throw new Error('Erreur réseau');
                                }

                                const data = await response.json();
                                
                                if (data.success) {
                                    // Recharger la page pour mettre à jour l'interface
                                    location.reload();
                                } else {
                                    // Réactiver les boutons et restaurer leur état initial
                                    buttons.forEach(button => {
                                        button.disabled = false;
                                        button.style.opacity = '1';
                                    });
                                    btn.innerHTML = originalContent;
                                    showNotification(data.message || 'Une erreur est survenue', 'error');
                                }
                            } catch (error) {
                                console.error('Erreur:', error);
                                // En cas d'erreur, réactiver les boutons
                                const buttons = document.querySelectorAll('.friend-btn, .friend-request-buttons button');
                                buttons.forEach(button => {
                                    button.disabled = false;
                                    button.style.opacity = '1';
                                });
                                if (btn) {
                                    btn.innerHTML = originalContent;
                                }
                                showNotification('Une erreur est survenue lors de l\'envoi de la demande', 'error');
                            }
                        }

                        // Fonction pour afficher les notifications
                        function showNotification(message, type = 'success') {
                            const notification = document.createElement('div');
                            notification.className = `notification ${type}`;
                            notification.textContent = message;
                            document.body.appendChild(notification);
                            
                            setTimeout(() => {
                                notification.classList.add('show');
                            }, 100);
                            
                            setTimeout(() => {
                                notification.classList.remove('show');
                                setTimeout(() => {
                                    notification.remove();
                                }, 300);
                            }, 3000);
                        }
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-user"></i> Informations</h2>
            <div class="info-grid">
                <?php if ($profile_user['age']): ?>
                    <div class="info-item">
                        <h3><i class="fas fa-birthday-cake"></i> Âge</h3>
                        <p><?php echo htmlspecialchars($profile_user['age']); ?> ans</p>
                    </div>
                <?php endif; ?>

                <?php if ($profile_user['language']): ?>
                    <div class="info-item">
                        <h3><i class="fas fa-language"></i> Langue</h3>
                        <p><?php 
                            $languages = [
                                'fr' => 'Français',
                                'en' => 'Anglais',
                                'es' => 'Espagnol',
                                'de' => 'Allemand'
                            ];
                            echo $languages[$profile_user['language']] ?? $profile_user['language'];
                        ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($profile_user['platform']): ?>
                    <div class="info-item">
                        <h3><i class="fas fa-gamepad"></i> Plateforme</h3>
                        <p><?php 
                            $platforms = [
                                'pc' => 'PC',
                                'ps5' => 'PlayStation 5',
                                'ps4' => 'PlayStation 4',
                                'xbox' => 'Xbox Series X/S',
                                'switch' => 'Nintendo Switch'
                            ];
                            echo $platforms[$profile_user['platform']] ?? $profile_user['platform'];
                        ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($profile_user['discord_username']): ?>
                    <div class="info-item">
                        <h3><i class="fab fa-discord"></i> Discord</h3>
                        <p><?php echo htmlspecialchars($profile_user['discord_username']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($profile_user['bio']): ?>
                <div class="info-item" style="margin-top: 1.5rem;">
                    <h3><i class="fas fa-quote-left"></i> Bio</h3>
                    <p><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-clock"></i> Disponibilités</h2>
            <?php
            $availabilities = [
                'morning' => ['icon' => 'fa-sun', 'text' => 'Matin (6h-12h)'],
                'afternoon' => ['icon' => 'fa-cloud-sun', 'text' => 'Après-midi (12h-18h)'],
                'evening' => ['icon' => 'fa-moon', 'text' => 'Soir (18h-00h)'],
                'night' => ['icon' => 'fa-star', 'text' => 'Nuit (00h-6h)']
            ];

            $hasAvailabilities = false;
            foreach ($availabilities as $time => $info) {
                if ($profile_user['availability_' . $time]) {
                    $hasAvailabilities = true;
                    break;
                }
            }
            ?>
            <?php if ($hasAvailabilities): ?>
                <div class="availability-grid">
                    <?php foreach ($availabilities as $time => $info):
                        if ($profile_user['availability_' . $time]):
                    ?>
                        <div class="availability-item">
                            <i class="fas <?php echo $info['icon']; ?>"></i>
                            <span><?php echo $info['text']; ?></span>
                        </div>
                    <?php 
                        endif;
                    endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-availabilities">Aucune disponibilité renseignée</p>
            <?php endif; ?>
        </div>

        <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-gamepad"></i> Jeux préférés</h2>
            <?php if (empty($game_preferences)): ?>
                <div class="no-preferences">
                    <i class="fas fa-gamepad" style="font-size: 3rem; color: var(--primary-color);"></i>
                    <h2>Aucun jeu ajouté</h2>
                    <p>Cet utilisateur n'a pas encore ajouté de jeux à son profil</p>
                </div>
            <?php else: ?>
                <div class="game-list">
                    <?php foreach ($game_preferences as $pref): ?>
                        <div class="game-item">
                            <h3><?php echo htmlspecialchars($pref['name']); ?></h3>
                            <p><i class="fas fa-star"></i> Niveau: <?php echo htmlspecialchars($pref['skill_level']); ?></p>
                            <p><i class="fas fa-clock"></i> Horaires: <?php echo htmlspecialchars($pref['preferred_playtime']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 