<?php
session_start();
require_once 'config/database.php';

// Récupérer les informations de l'utilisateur si connecté
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Récupérer les statistiques
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
$stmt->execute();
$total_users = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) as total_friendships FROM friendships WHERE status = 'accepted'");
$stmt->execute();
$total_friendships = $stmt->fetchColumn();

// Récupérer les jeux les plus populaires
$stmt = $conn->prepare("
    SELECT g.name, COUNT(ugp.id) as player_count
    FROM games g
    LEFT JOIN user_game_preferences ugp ON g.id = ugp.game_id
    GROUP BY g.id
    ORDER BY player_count DESC
    LIMIT 6
");
$stmt->execute();
$popular_games = $stmt->fetchAll();

// Récupérer les derniers membres inscrits
$stmt = $conn->prepare("
    SELECT id, username, avatar, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 6
");
$stmt->execute();
$newest_members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SquadUp - Trouve ton duo gaming!</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        .stats-section {
            padding: 4rem 2rem;
            background: rgba(138, 43, 226, 0.1);
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .games-section {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
            font-size: 2rem;
        }

        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .game-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .game-card:hover {
            transform: translateY(-5px);
        }

        .members-section {
            padding: 4rem 2rem;
            background: rgba(138, 43, 226, 0.1);
        }

        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .member-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .member-card:hover {
            transform: translateY(-5px);
        }

        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary-color);
            object-fit: cover;
        }

        .testimonials-section {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .testimonial-text {
            font-style: italic;
            margin-bottom: 1rem;
        }

        .testimonial-author {
            font-weight: bold;
            color: var(--primary-color);
        }

        footer {
            background: var(--card-bg);
            padding: 4rem 2rem;
            margin-top: 4rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: var(--text-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: var(--primary-color);
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-links a {
            color: var(--text-color);
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main>
        <div class="hero-section">
            <div class="hero-content">
                <h1 class="glitch" data-text="Trouve ton duo gaming!">Trouve ton duo gaming!</h1>
                <p class="hero-subtitle">Connecte-toi avec des joueurs qui partagent ta passion</p>
                <div class="cta-buttons">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="cta-primary">Rejoins l'aventure</a>
                    <?php else: ?>
                        <a href="search.php" class="cta-primary">Rechercher des joueurs</a>
                    <?php endif; ?>
                    <a href="search.php" class="cta-secondary">Explorer</a>
                </div>
            </div>
            <div class="hero-features">
                <div class="feature-card">
                    <i class="fas fa-gamepad"></i>
                    <h3>Match par jeu</h3>
                    <p>Trouve des joueurs qui aiment les mêmes jeux que toi</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h3>Communauté active</h3>
                    <p>Rejoins une communauté passionnée de gaming</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-trophy"></i>
                    <h3>Profils vérifiés</h3>
                    <p>Des vrais gamers, pas de fake</p>
                </div>
            </div>
        </div>

        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Joueurs inscrits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_friendships); ?></div>
                    <div class="stat-label">Duos formés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($popular_games); ?></div>
                    <div class="stat-label">Jeux disponibles</div>
                </div>
            </div>
        </section>

        <section class="games-section">
            <h2 class="section-title">Jeux populaires</h2>
            <div class="games-grid">
                <?php foreach ($popular_games as $game): ?>
                    <div class="game-card">
                        <i class="fas fa-gamepad" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                        <p><?php echo $game['player_count']; ?> joueurs</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="members-section">
            <h2 class="section-title">Nouveaux membres</h2>
            <div class="members-grid">
                <?php foreach ($newest_members as $member): ?>
                    <div class="member-card">
                        <img src="<?php echo $member['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="member-avatar">
                        <h3><?php echo htmlspecialchars($member['username']); ?></h3>
                        <p>Membre depuis <?php echo date('d/m/Y', strtotime($member['created_at'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="testimonials-section">
            <h2 class="section-title">Ce que disent nos membres</h2>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <p class="testimonial-text">"Grâce à SquadUp, j'ai trouvé une équipe incroyable pour jouer à Valorant. On s'entraîne régulièrement et on progresse ensemble!"</p>
                    <p class="testimonial-author">- Alex, 23 ans</p>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"Je cherchais des joueurs francophones pour World of Warcraft, et j'ai trouvé une super guilde grâce à la plateforme."</p>
                    <p class="testimonial-author">- Marie, 28 ans</p>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"L'interface est super intuitive et la communauté est vraiment bienveillante. Je recommande à 100% !"</p>
                    <p class="testimonial-author">- Thomas, 19 ans</p>
                </div>
            </div>
        </section>

        <footer>
            <div class="footer-content">
                <div class="footer-section">
                    <h3>À propos</h3>
                    <ul>
                        <li><a href="#">Notre histoire</a></li>
                        <li><a href="#">L'équipe</a></li>
                        <li><a href="#">Carrières</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="#">Centre d'aide</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Contactez-nous</a></li>
                        <li><a href="#">Signaler un bug</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Légal</h3>
                    <ul>
                        <li><a href="#">Conditions d'utilisation</a></li>
                        <li><a href="#">Politique de confidentialité</a></li>
                        <li><a href="#">Cookies</a></li>
                        <li><a href="#">Mentions légales</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Suivez-nous</h3>
                    <p>Restez connecté avec la communauté SquadUp</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-discord"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <script src="js/main.js"></script>
</body>
</html> 