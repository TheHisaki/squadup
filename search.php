<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Définir le chemin de base
define('BASE_PATH', __DIR__);

// Vérifier si l'utilisateur est connecté
check_auth();

// Récupérer les paramètres de recherche
$search_query = isset($_GET['q']) ? clean_input($_GET['q']) : '';
$game_filter = isset($_GET['game']) ? clean_input($_GET['game']) : '';
$playtime_filter = isset($_GET['playtime']) ? clean_input($_GET['playtime']) : '';
$platform_filter = isset($_GET['platform']) ? clean_input($_GET['platform']) : '';
$age_filter = isset($_GET['age_range']) ? clean_input($_GET['age_range']) : '';
$language_filter = isset($_GET['language']) ? clean_input($_GET['language']) : '';

// Récupérer la liste des jeux
$stmt = $conn->prepare("SELECT id, name FROM games ORDER BY name");
$stmt->execute();
$games = $stmt->fetchAll();

// Construire la requête de recherche
$query = "
    SELECT DISTINCT 
        u.id, 
        u.username, 
        u.bio, 
        u.avatar,
        u.age,
        u.discord_username, 
        u.favorite_games,
        u.created_at,
        u.language,
        u.platform,
        u.availability_morning,
        u.availability_afternoon,
        u.availability_evening,
        u.availability_night,
        GROUP_CONCAT(DISTINCT g.name) as game_names,
        GROUP_CONCAT(DISTINCT ugp.skill_level) as skill_levels,
        GROUP_CONCAT(DISTINCT ugp.preferred_playtime) as playtimes
    FROM users u
    LEFT JOIN user_game_preferences ugp ON u.id = ugp.user_id
    LEFT JOIN games g ON ugp.game_id = g.id
    WHERE u.id != ?
";
$params = [$_SESSION['user_id']];

// Ajouter les conditions de recherche
if ($search_query) {
    $query .= " AND (u.username LIKE ? OR u.bio LIKE ? OR u.discord_username LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($game_filter) {
    $query .= " AND EXISTS (
        SELECT 1 FROM user_game_preferences ugp2 
        WHERE ugp2.user_id = u.id 
        AND ugp2.game_id = ?
    )";
    $params[] = $game_filter;
}

if ($playtime_filter) {
    switch ($playtime_filter) {
        case 'Matin':
            $query .= " AND u.availability_morning = 1";
            break;
        case 'Après-midi':
            $query .= " AND u.availability_afternoon = 1";
            break;
        case 'Soir':
            $query .= " AND u.availability_evening = 1";
            break;
        case 'Nuit':
            $query .= " AND u.availability_night = 1";
            break;
    }
}

if ($platform_filter) {
    $query .= " AND u.platform = ?";
    $params[] = $platform_filter;
}

if ($language_filter) {
    $query .= " AND u.language = ?";
    $params[] = $language_filter;
}

if ($age_filter) {
    switch ($age_filter) {
        case '13-17':
            $query .= " AND u.age BETWEEN 13 AND 17";
            break;
        case '18-24':
            $query .= " AND u.age BETWEEN 18 AND 24";
            break;
        case '25-34':
            $query .= " AND u.age BETWEEN 25 AND 34";
            break;
        case '35+':
            $query .= " AND u.age >= 35";
            break;
    }
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Exécuter la requête
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="js/notifications.js"></script>
    <style>
        .search-container {
            max-width: 1200px;
            margin: 100px auto;
            padding: 2rem;
        }

        .search-input {
            width: 100%;
            padding: 1rem;
            font-size: 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .search-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: transform 0.3s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        /* Styles pour les cartes utilisateur */
        .user-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
            position: relative;
            width: 100%;
            height: 300px;
        }

        .card-content {
            height: calc(100% - 70px);
            overflow: hidden;
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-info h3 {
            margin: 0;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .user-stats {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .stat-item {
            background: rgba(138, 43, 226, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .user-bio {
            margin: 1rem 0;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.8;
        }

        .user-games {
            margin-top: 1rem;
            overflow: hidden;
        }

        .action-buttons {
            position: absolute;
            bottom: 1.5rem;
            left: 1.5rem;
            right: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            height: 40px;
        }

        .action-btn {
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .profile-btn {
            background: rgba(138, 43, 226, 0.1) !important;
            color: var(--text-color) !important;
            border: none !important;
        }

        .friend-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .friend-btn.pending {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .friend-btn.friend {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }

        .message-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.2);
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            color: var(--text-color);
            background: var(--card-bg);
            border-radius: 15px;
            margin-top: 2rem;
        }

        /* Styles pour les notifications */
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
            animation: slideIn 0.3s ease-out;
        }

        .notification.success {
            border-left: 4px solid #4CAF50;
        }

        .notification.error {
            border-left: 4px solid #ff4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Styles pour les boutons d'ami */
        .friend-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
        }

        .friend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.2);
        }

        .notification-box {
            position: fixed;
            top: 80px;
            right: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
        }

        .notification-box.show {
            display: block;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            padding: 0.5rem;
        }

        .notification-item {
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .notification-item.unread {
            border-left: 3px solid var(--primary-color);
        }

        .notification-badge {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        .notifications-btn {
            position: relative;
            padding: 0.5rem;
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
        }

        .friend-request-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .friend-btn.reject-btn {
            background-color: #dc3545;
            color: white;
        }

        .friend-btn.reject-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="search-container">
        <form method="GET" action="search.php" class="search-form">
            <input type="text" name="q" placeholder="Rechercher des joueurs..." class="search-input" value="<?php echo htmlspecialchars($search_query); ?>">
            
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="game">Jeu</label>
                    <select name="game" id="game">
                        <option value="">Tous les jeux</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?php echo $game['id']; ?>" <?php echo ($game_filter == $game['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($game['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="playtime">Disponibilité</label>
                    <select name="playtime" id="playtime">
                        <option value="">Toutes les disponibilités</option>
                        <option value="Matin" <?php echo ($playtime_filter == 'Matin') ? 'selected' : ''; ?>>Matin (6h-12h)</option>
                        <option value="Après-midi" <?php echo ($playtime_filter == 'Après-midi') ? 'selected' : ''; ?>>Après-midi (12h-18h)</option>
                        <option value="Soir" <?php echo ($playtime_filter == 'Soir') ? 'selected' : ''; ?>>Soir (18h-00h)</option>
                        <option value="Nuit" <?php echo ($playtime_filter == 'Nuit') ? 'selected' : ''; ?>>Nuit (00h-6h)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="platform">Plateforme</label>
                    <select name="platform" id="platform">
                        <option value="">Toutes les plateformes</option>
                        <option value="PC" <?php echo ($platform_filter == 'PC') ? 'selected' : ''; ?>>PC</option>
                        <option value="PlayStation" <?php echo ($platform_filter == 'PlayStation') ? 'selected' : ''; ?>>PlayStation</option>
                        <option value="Xbox" <?php echo ($platform_filter == 'Xbox') ? 'selected' : ''; ?>>Xbox</option>
                        <option value="Nintendo Switch" <?php echo ($platform_filter == 'Nintendo Switch') ? 'selected' : ''; ?>>Nintendo Switch</option>
                        <option value="Mobile" <?php echo ($platform_filter == 'Mobile') ? 'selected' : ''; ?>>Mobile</option>
                        <option value="Multi-Plateformes" <?php echo ($platform_filter == 'Multi-Plateformes') ? 'selected' : ''; ?>>Multi-Plateformes</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="age_range">Âge</label>
                    <select name="age_range" id="age_range">
                        <option value="">Tous les âges</option>
                        <option value="13-17" <?php echo ($age_filter == '13-17') ? 'selected' : ''; ?>>13-17 ans</option>
                        <option value="18-24" <?php echo ($age_filter == '18-24') ? 'selected' : ''; ?>>18-24 ans</option>
                        <option value="25-34" <?php echo ($age_filter == '25-34') ? 'selected' : ''; ?>>25-34 ans</option>
                        <option value="35+" <?php echo ($age_filter == '35+') ? 'selected' : ''; ?>>35 ans et plus</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="language">Langue</label>
                    <select name="language" id="language">
                        <option value="">Toutes les langues</option>
                        <option value="fr" <?php echo ($language_filter == 'fr') ? 'selected' : ''; ?>>Français</option>
                        <option value="en" <?php echo ($language_filter == 'en') ? 'selected' : ''; ?>>Anglais</option>
                        <option value="es" <?php echo ($language_filter == 'es') ? 'selected' : ''; ?>>Espagnol</option>
                        <option value="de" <?php echo ($language_filter == 'de') ? 'selected' : ''; ?>>Allemand</option>
                        <option value="it" <?php echo ($language_filter == 'it') ? 'selected' : ''; ?>>Italien</option>
                        <option value="pt" <?php echo ($language_filter == 'pt') ? 'selected' : ''; ?>>Portugais</option>
                        <option value="ru" <?php echo ($language_filter == 'ru') ? 'selected' : ''; ?>>Russe</option>
                        <option value="zh" <?php echo ($language_filter == 'zh') ? 'selected' : ''; ?>>Chinois</option>
                        <option value="ja" <?php echo ($language_filter == 'ja') ? 'selected' : ''; ?>>Japonais</option>
                        <option value="ko" <?php echo ($language_filter == 'ko') ? 'selected' : ''; ?>>Coréen</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Rechercher
            </button>
        </form>

        <div class="results-grid">
            <?php if (empty($users)): ?>
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h2>Aucun résultat trouvé</h2>
                    <p>Essayez de modifier vos critères de recherche</p>
                </div>
            <?php else: ?>
                <!-- Ajouter le token CSRF dans un champ caché -->
                <input type="hidden" id="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="card-content">
                            <div class="user-header">
                                <img src="<?php echo $user['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="user-avatar">
                                <div class="user-info">
                                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                                    <div class="user-stats">
                                        <?php if ($user['platform']): ?>
                                            <span class="stat-item"><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($user['platform']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($user['language']): ?>
                                            <span class="stat-item"><i class="fas fa-language"></i> <?php echo htmlspecialchars($user['language']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($user['bio']): ?>
                                <p class="user-bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                            <?php endif; ?>

                            <?php if ($user['game_names']): ?>
                                <div class="user-games">
                                    <strong>Jeux :</strong>
                                    <?php foreach (explode(',', $user['game_names']) as $game): ?>
                                        <span class="stat-item"><?php echo htmlspecialchars(trim($game)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="action-buttons">
                            <a href="view_profile.php?id=<?php echo $user['id']; ?>" class="action-btn profile-btn">
                                <i class="fas fa-user"></i> Profil
                            </a>
                            <?php
                            // Vérifier le statut de l'amitié
                            $stmt = $conn->prepare("SELECT * FROM friendships WHERE 
                                (sender_id = ? AND receiver_id = ?) OR 
                                (sender_id = ? AND receiver_id = ?)");
                            $stmt->execute([$_SESSION['user_id'], $user['id'], $user['id'], $_SESSION['user_id']]);
                            $friendship = $stmt->fetch();
                            
                            if ($friendship) {
                                if ($friendship['status'] === 'pending') {
                                    if ($friendship['receiver_id'] == $_SESSION['user_id']) {
                                        // L'utilisateur courant a reçu une demande d'ami
                                        echo '<div class="friend-request-buttons">
                                            <button onclick="toggleFriendship(' . $user['id'] . ', \'accept\')" class="action-btn friend-btn" id="friend-btn-' . $user['id'] . '">
                                                <i class="fas fa-check"></i> Accepter
                                            </button>
                                            <button onclick="toggleFriendship(' . $user['id'] . ', \'reject\')" class="action-btn friend-btn reject-btn">
                                                <i class="fas fa-times"></i> Refuser
                                            </button>
                                        </div>';
                                    } else {
                                        // L'utilisateur courant a envoyé une demande d'ami
                                        echo '<button onclick="toggleFriendship(' . $user['id'] . ')" class="action-btn friend-btn pending" id="friend-btn-' . $user['id'] . '">
                                            <i class="fas fa-clock"></i> En attente
                                        </button>';
                                    }
                                } elseif ($friendship['status'] === 'accepted') {
                                    echo '<button onclick="toggleFriendship(' . $user['id'] . ')" class="action-btn friend-btn friend" id="friend-btn-' . $user['id'] . '">
                                        <i class="fas fa-user-minus"></i> Retirer
                                    </button>';
                                }
                            } else {
                                echo '<button onclick="toggleFriendship(' . $user['id'] . ')" class="action-btn friend-btn" id="friend-btn-' . $user['id'] . '">
                                    <i class="fas fa-user-plus"></i> Ajouter
                                </button>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        // Animation des cartes au scroll
        const cards = document.querySelectorAll('.user-card');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(50px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(card);
        });

        // Système d'amis simplifié
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
                
                // Déterminer l'action en fonction du bouton actuel si aucune action n'est spécifiée
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
                const buttons = document.querySelectorAll(`.friend-btn[id$="${friendId}"], .friend-request-buttons button`);
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
                    // Mettre à jour l'interface en fonction de l'action
                    switch (action) {
                        case 'add':
                            if (data.action === 'cancelled') {
                                btn.innerHTML = '<i class="fas fa-user-plus"></i> Ajouter';
                                btn.classList.remove('pending', 'friend');
                                showNotification('Demande d\'ami annulée');
                            } else {
                                btn.innerHTML = '<i class="fas fa-clock"></i> En attente';
                                btn.classList.add('pending');
                                btn.classList.remove('friend');
                                showNotification('Demande d\'ami envoyée !');
                            }
                            break;
                            
                        case 'accept':
                            // Remplacer les deux boutons par un seul bouton "Retirer"
                            const parentDiv = btn.closest('.friend-request-buttons');
                            if (parentDiv) {
                                parentDiv.innerHTML = `
                                    <button onclick="toggleFriendship(${friendId}, 'remove')" class="action-btn friend-btn friend" id="friend-btn-${friendId}">
                                        <i class="fas fa-user-minus"></i> Retirer
                                    </button>
                                `;
                            }
                            showNotification('Demande d\'ami acceptée !');
                            break;
                            
                        case 'reject':
                            // Remplacer les deux boutons par le bouton "Ajouter"
                            const parentDiv2 = btn.closest('.friend-request-buttons');
                            if (parentDiv2) {
                                parentDiv2.innerHTML = `
                                    <button onclick="toggleFriendship(${friendId})" class="action-btn friend-btn" id="friend-btn-${friendId}">
                                        <i class="fas fa-user-plus"></i> Ajouter
                                    </button>
                                `;
                            }
                            showNotification('Demande d\'ami refusée');
                            break;
                            
                        case 'remove':
                            btn.innerHTML = '<i class="fas fa-user-plus"></i> Ajouter';
                            btn.classList.remove('friend', 'pending');
                            showNotification('Ami retiré de votre liste');
                            break;
                    }
                    
                    // Mettre à jour le compteur de notifications
                    if (typeof updateNotifications === 'function') {
                        updateNotifications();
                    }
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
                const buttons = document.querySelectorAll(`.friend-btn[id$="${friendId}"], .friend-request-buttons button`);
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

        // Initialiser les notifications au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            initNotifications();
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

        // Fonction pour ajouter un ami
        async function addFriend(userId) {
            try {
                const response = await fetch('friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add&friend_id=${userId}&csrf_token=${document.getElementById('csrf_token').value}`
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Mettre à jour le bouton
                    const addButton = document.querySelector(`button[onclick="addFriend(${userId})"]`);
                    if (addButton) {
                        addButton.disabled = true;
                        addButton.innerHTML = '<i class="fas fa-clock"></i> En attente';
                    }
                } else {
                    showNotification(data.message || 'Une erreur est survenue', 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue lors de l\'envoi de la demande', 'error');
            }
        }
    </script>
</body>
</html> 