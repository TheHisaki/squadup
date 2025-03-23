<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

// Récupérer la liste des amis
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.username,
        u.avatar,
        u.platform,
        u.discord_username,
        u.created_at,
        CASE 
            WHEN u.id = f.sender_id THEN f.receiver_id
            ELSE f.sender_id
        END as friend_id
    FROM friendships f
    JOIN users u ON (
        CASE 
            WHEN f.sender_id = ? THEN f.receiver_id = u.id
            WHEN f.receiver_id = ? THEN f.sender_id = u.id
        END
    )
    WHERE (f.sender_id = ? OR f.receiver_id = ?)
    AND f.status = 'accepted'
    ORDER BY u.username
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$friends = $stmt->fetchAll();

// Récupérer les demandes d'amis en attente
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.username,
        u.avatar,
        u.platform,
        u.discord_username,
        f.created_at
    FROM friendships f
    JOIN users u ON f.sender_id = u.id
    WHERE f.receiver_id = ? 
    AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$pending_requests = $stmt->fetchAll();

// Récupérer la liste des utilisateurs bloqués
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.avatar, bu.created_at as blocked_at
    FROM blocked_users bu
    JOIN users u ON bu.blocked_user_id = u.id
    WHERE bu.user_id = ?
    ORDER BY bu.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$blocked_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes amis - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        .friends-container {
            max-width: 1200px;
            margin: 120px auto;
            padding: 2rem;
        }

        .friends-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .friends-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .friends-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .friend-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .friend-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .friend-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .friend-info {
            flex: 1;
        }

        .friend-info h3 {
            margin: 0;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .friend-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .friend-stat {
            background: rgba(138, 43, 226, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .friend-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .friend-btn {
            text-decoration: none !important;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .view-profile {
            background: rgba(138, 43, 226, 0.1);
            color: var(--text-color);
            text-decoration: none !important;
        }

        .message {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-decoration: none !important;
        }

        .remove-friend, .block-user {
            background: #ff4444;
            color: white;
        }

        .friend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .friend-options {
            position: absolute;
            top: 1rem;
            right: 1rem;
            cursor: pointer;
        }

        .options-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.5rem;
            display: none;
            z-index: 100;
            min-width: 150px;
        }

        .options-menu.show {
            display: block;
        }

        .option-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
            transition: background-color 0.3s ease;
        }

        .option-item:hover {
            background: rgba(138, 43, 226, 0.1);
        }

        .option-item.danger {
            color: #ff4444;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 2rem;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            z-index: 1000;
            display: none;
        }

        .notification.success {
            border-left: 4px solid #4CAF50;
        }

        .notification.error {
            border-left: 4px solid #ff4444;
        }

        .pending-requests {
            margin-bottom: 3rem;
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pending-requests h2 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .pending-requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .request-card {
            background: rgba(138, 43, 226, 0.1);
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .request-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .request-info {
            flex: 1;
        }

        .request-info h3 {
            margin: 0;
            margin-bottom: 0.5rem;
        }

        .request-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .request-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .accept-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .reject-btn {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
        }

        .request-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .action-btn {
            text-decoration: none !important;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .blocked-since {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.25rem;
        }

        .unblock-btn {
            background-color: #666;
            color: white;
        }

        .unblock-btn:hover {
            background-color: #777;
        }

        .friends-section {
            margin-top: 3rem;
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .friends-section h2 {
            font-size: 1.8rem;
            margin-bottom: 2rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .friends-section h2 i {
            font-size: 1.5rem;
        }

        .blocked-users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .blocked-user-card {
            background: rgba(255, 68, 68, 0.05);
            border: 1px solid rgba(255, 68, 68, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .blocked-user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 68, 68, 0.1);
        }

        .blocked-user-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .blocked-user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 68, 68, 0.3);
        }

        .blocked-user-info {
            flex: 1;
        }

        .blocked-user-info h3 {
            margin: 0;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .blocked-since {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .unblock-btn {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .unblock-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .no-blocked-users {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .no-blocked-users i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <input type="hidden" id="csrf_token" value="<?php echo generate_csrf_token(); ?>">

    <div class="friends-container">
        <div class="friends-header">
            <h1>Mes amis</h1>
            <p>Gérez vos amitiés et restez connecté avec vos compagnons de jeu</p>
        </div>

        <?php if (!empty($pending_requests)): ?>
            <div class="pending-requests">
                <h2>Demandes d'amis en attente</h2>
                <div class="pending-requests-grid">
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="request-card">
                            <img src="<?php echo $request['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="request-avatar">
                            <div class="request-info">
                                <h3><?php echo htmlspecialchars($request['username']); ?></h3>
                                <div class="request-actions">
                                    <button onclick="acceptFriendRequest(<?php echo $request['id']; ?>)" class="request-btn accept-btn">
                                        <i class="fas fa-check"></i> Accepter
                                    </button>
                                    <button onclick="rejectFriendRequest(<?php echo $request['id']; ?>)" class="request-btn reject-btn">
                                        <i class="fas fa-times"></i> Refuser
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($friends) && empty($pending_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h2>Aucun ami pour le moment</h2>
                <p>Commencez à explorer et à vous connecter avec d'autres joueurs!</p>
                <a href="search.php" class="cta-primary" style="display: inline-block; margin-top: 1rem;">
                    Rechercher des joueurs
                </a>
            </div>
        <?php elseif (!empty($friends)): ?>
            <div class="friends-grid">
                <?php foreach ($friends as $friend): ?>
                    <div class="friend-card">
                        <div class="friend-header">
                            <img src="<?php echo $friend['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="friend-avatar">
                            <div class="friend-info">
                                <h3><?php echo htmlspecialchars($friend['username']); ?></h3>
                                <div class="friend-stats">
                                    <?php if ($friend['platform']): ?>
                                        <span class="friend-stat">
                                            <i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($friend['platform']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($friend['discord_username']): ?>
                                        <span class="friend-stat">
                                            <i class="fab fa-discord"></i> <?php echo htmlspecialchars($friend['discord_username']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="friend-actions">
                            <a href="view_profile.php?id=<?php echo $friend['id']; ?>" class="friend-btn view-profile" style="text-decoration: none;">
                                <i class="fas fa-user"></i> Profil
                            </a>
                            <a href="messages.php?user=<?php echo $friend['id']; ?>" class="friend-btn message" style="text-decoration: none;">
                                <i class="fas fa-comments"></i> Message
                            </a>
                        </div>

                        <div class="friend-options">
                            <i class="fas fa-ellipsis-v"></i>
                            <div class="options-menu">
                                <div class="option-item danger" onclick="removeFriend(<?php echo $friend['id']; ?>)">
                                    <i class="fas fa-user-minus"></i> Retirer
                                </div>
                                <div class="option-item danger" onclick="blockUser(<?php echo $friend['id']; ?>)">
                                    <i class="fas fa-ban"></i> Bloquer
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="friends-section">
            <h2><i class="fas fa-ban"></i> Utilisateurs bloqués</h2>
            <?php if (empty($blocked_users)): ?>
                <div class="no-blocked-users">
                    <i class="fas fa-check-circle"></i>
                    <h3>Aucun utilisateur bloqué</h3>
                    <p>Vous n'avez bloqué aucun utilisateur pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="blocked-users-grid">
                    <?php foreach ($blocked_users as $blocked): ?>
                        <div class="blocked-user-card">
                            <div class="blocked-user-header">
                                <img src="<?php echo $blocked['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="blocked-user-avatar">
                                <div class="blocked-user-info">
                                    <h3><?php echo htmlspecialchars($blocked['username']); ?></h3>
                                    <div class="blocked-since">
                                        <i class="fas fa-clock"></i>
                                        Bloqué depuis le <?php echo date('d/m/Y', strtotime($blocked['blocked_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <button onclick="unblockUser(<?php echo $blocked['id']; ?>)" class="unblock-btn">
                                <i class="fas fa-unlock"></i>
                                Débloquer l'utilisateur
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        // Gestion du menu d'options
        document.querySelectorAll('.friend-options').forEach(options => {
            options.addEventListener('click', (e) => {
                e.stopPropagation();
                const menu = options.querySelector('.options-menu');
                menu.classList.toggle('show');
            });
        });

        // Fermer les menus lors d'un clic ailleurs
        document.addEventListener('click', () => {
            document.querySelectorAll('.options-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        });

        // Fonction pour afficher les notifications
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Fonction pour retirer un ami
        async function removeFriend(friendId) {
            if (!confirm('Êtes-vous sûr de vouloir retirer cet ami ?')) return;

            try {
                const response = await fetch('friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&friend_id=${friendId}&csrf_token=${document.getElementById('csrf_token').value}`
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Ami retiré avec succès');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue', 'error');
            }
        }

        // Fonction pour bloquer un utilisateur
        async function blockUser(userId) {
            if (!confirm('Êtes-vous sûr de vouloir bloquer cet utilisateur ? Cette action supprimera également votre amitié avec cet utilisateur.')) return;
            
            try {
                const response = await fetch('block_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${userId}&action=block&csrf_token=${document.getElementById('csrf_token').value}`
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue lors du blocage de l\'utilisateur', 'error');
            }
        }

        // Fonction pour accepter une demande d'ami
        async function acceptFriendRequest(userId) {
            try {
                const response = await fetch('friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=accept&friend_id=${userId}&csrf_token=${document.getElementById('csrf_token').value}`
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Demande d\'ami acceptée');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue', 'error');
            }
        }

        // Fonction pour refuser une demande d'ami
        async function rejectFriendRequest(userId) {
            try {
                const response = await fetch('friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reject&friend_id=${userId}&csrf_token=${document.getElementById('csrf_token').value}`
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Demande d\'ami refusée');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue', 'error');
            }
        }

        // Fonction pour débloquer un utilisateur
        async function unblockUser(userId) {
            if (!confirm('Êtes-vous sûr de vouloir débloquer cet utilisateur ?')) return;
            
            try {
                const response = await fetch('block_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${userId}&action=unblock&csrf_token=${document.getElementById('csrf_token').value}`
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue lors du déblocage de l\'utilisateur', 'error');
            }
        }
    </script>
</body>
</html> 