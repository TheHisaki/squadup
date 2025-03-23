<?php
// Assurez-vous que la session est démarrée et que la base de données est connectée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    require_once 'config/database.php';
}

// Récupérer les informations de l'utilisateur si connecté
$current_user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}

// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);

// Compter les demandes d'amis en attente
$friend_requests = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM friendships 
        WHERE receiver_id = ? 
        AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $friend_requests = $stmt->fetchColumn();
}
?>
<nav class="navbar">
    <div class="nav-brand">
        <a href="index.php" style="display: flex; align-items: center; text-decoration: none; color: inherit;">
            <img src="assets/images/logo.png" alt="SquadUp Logo" class="logo" style="width: 50px; height: 50px; margin-right: 10px;">
            <h1>SquadUp</h1>
        </a>
    </div>
    <div class="nav-links">
        <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Accueil
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="search.php" class="<?php echo $current_page === 'search.php' ? 'active' : ''; ?>" style="<?php echo $current_page === 'search.php' ? 'background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white;' : ''; ?>">
                <i class="fas fa-search"></i> Recherche
            </a>
            <a href="amis.php" class="<?php echo $current_page === 'amis.php' ? 'active' : ''; ?>" style="<?php echo $current_page === 'amis.php' ? 'background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white;' : ''; ?>">
                <i class="fas fa-user-friends"></i> Amis
                <?php if ($friend_requests > 0): ?>
                    <span class="badge"><?php echo $friend_requests; ?></span>
                <?php endif; ?>
            </a>
            <a href="messages.php" class="<?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Messages
                <?php
                // Compter les messages non lus
                $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                $unread = $stmt->fetchColumn();
                if ($unread > 0): ?>
                    <span class="badge"><?php echo $unread; ?></span>
                <?php endif; ?>
            </a>
            <div class="notification-system">
                <button class="notifications-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notification-count" style="display: none;">0</span>
                </button>
                <div class="notification-box" id="notification-box">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button onclick="markAllAsRead()" class="mark-read-btn">
                            <i class="fas fa-check-double"></i> Tout marquer comme lu
                        </button>
                    </div>
                    <div class="notification-list" id="notification-list">
                        <!-- Les notifications seront ajoutées ici dynamiquement -->
                    </div>
                </div>
            </div>
            <div class="user-menu">
                <a href="profile.php" class="profile-btn <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Mon Profil
                </a>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        <?php else: ?>
            <div class="auth-links">
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Connexion
                </a>
                <a href="register.php" class="register-btn">
                    <i class="fas fa-user-plus"></i> Inscription
                </a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<style>
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background: var(--card-bg);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.notification-system {
    position: relative;
    margin-right: 1rem;
}

.notifications-btn {
    background: none;
    border: none;
    color: var(--text-color);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    position: relative;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-box {
    position: absolute;
    top: 100%;
    right: 0;
    width: 300px;
    max-height: 400px;
    background: var(--card-bg);
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: none;
    z-index: 1000;
    margin-top: 0.5rem;
    overflow: hidden;
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

.notification-header h3 {
    margin: 0;
    font-size: 1rem;
}

.mark-read-btn {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    font-size: 0.9rem;
    padding: 0.5rem;
}

.notification-list {
    max-height: 350px;
    overflow-y: auto;
    padding: 0.5rem;
}

.notification-item {
    padding: 1rem;
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
</style>

<script src="js/notifications.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les notifications
    initNotifications();
    
    // Fermer la boîte de notifications en cliquant en dehors
    document.addEventListener('click', function(event) {
        const notificationBox = document.getElementById('notification-box');
        const notificationsBtn = document.querySelector('.notifications-btn');
        
        if (!notificationBox.contains(event.target) && !notificationsBtn.contains(event.target)) {
            notificationBox.classList.remove('show');
        }
    });
});
</script> 