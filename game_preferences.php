<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Définir le chemin de base
define('BASE_PATH', __DIR__);

// Vérifier si l'utilisateur est connecté
check_auth();

// Générer le token CSRF
$csrf_token = generate_csrf_token();

// Récupérer la liste des jeux
$stmt = $conn->prepare("SELECT * FROM games ORDER BY name");
$stmt->execute();
$games = $stmt->fetchAll();

// Récupérer les préférences actuelles de l'utilisateur
$stmt = $conn->prepare("
    SELECT ugp.*, g.name as game_name
    FROM user_game_preferences ugp
    JOIN games g ON ugp.game_id = g.id
    WHERE ugp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$preferences = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token']);

    if (isset($_POST['add_preference'])) {
        $game_id = filter_var($_POST['game_id'], FILTER_SANITIZE_NUMBER_INT);
        $skill_level = clean_input($_POST['skill_level']);
        $preferred_playtime = clean_input($_POST['preferred_playtime']);

        // Vérifier si la préférence existe déjà
        $stmt = $conn->prepare("SELECT id FROM user_game_preferences WHERE user_id = ? AND game_id = ?");
        $stmt->execute([$_SESSION['user_id'], $game_id]);
        
        if ($stmt->rowCount() === 0) {
            // Ajouter la nouvelle préférence
            $stmt = $conn->prepare("
                INSERT INTO user_game_preferences (user_id, game_id, skill_level, preferred_playtime)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $game_id, $skill_level, $preferred_playtime]);
            
            // Recharger la page
            header('Location: game_preferences.php');
            exit;
        }
    } elseif (isset($_POST['remove_preference'])) {
        $preference_id = filter_var($_POST['preference_id'], FILTER_SANITIZE_NUMBER_INT);
        
        // Supprimer la préférence
        $stmt = $conn->prepare("
            DELETE FROM user_game_preferences 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$preference_id, $_SESSION['user_id']]);
        
        // Recharger la page
        header('Location: game_preferences.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préférences de jeu - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preferences-container {
            max-width: 800px;
            margin: 100px auto;
            padding: 2rem;
        }

        .preferences-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .preferences-header h1 {
            font-size: 2rem;
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .preferences-header p {
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .add-preference-form {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
        }

        .submit-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: transform 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .preferences-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .preference-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .preference-card h3 {
            color: var(--text-color);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .preference-info {
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .remove-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: rgba(255, 68, 68, 0.1);
            transform: scale(1.1);
        }

        .no-preferences {
            text-align: center;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 15px;
            color: var(--text-color);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/navbar.php'; ?>

    <div class="preferences-container">
        <div class="preferences-header">
            <h1>Mes préférences de jeu</h1>
            <p>Gérez vos jeux préférés et vos disponibilités</p>
        </div>

        <form method="POST" class="add-preference-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="game_id">Jeu</label>
                    <select name="game_id" id="game_id" required>
                        <option value="">Sélectionnez un jeu</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="skill_level">Niveau</label>
                    <select name="skill_level" id="skill_level" required>
                        <option value="">Sélectionnez votre niveau</option>
                        <option value="débutant">Débutant</option>
                        <option value="intermédiaire">Intermédiaire</option>
                        <option value="expert">Expert</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="preferred_playtime">Horaires préférés</label>
                    <select name="preferred_playtime" id="preferred_playtime" required>
                        <option value="">Sélectionnez vos horaires</option>
                        <option value="matin">Matin (6h-12h)</option>
                        <option value="après-midi">Après-midi (12h-18h)</option>
                        <option value="soir">Soir (18h-00h)</option>
                        <option value="nuit">Nuit (00h-6h)</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="add_preference" class="submit-btn">
                <i class="fas fa-plus"></i> Ajouter un jeu
            </button>
        </form>

        <?php if (empty($preferences)): ?>
            <div class="no-preferences">
                <i class="fas fa-gamepad" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h2>Aucune préférence de jeu</h2>
                <p>Ajoutez vos jeux préférés pour trouver des joueurs compatibles</p>
            </div>
        <?php else: ?>
            <div class="preferences-list">
                <?php foreach ($preferences as $pref): ?>
                    <div class="preference-card">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="preference_id" value="<?php echo $pref['id']; ?>">
                            <button type="submit" name="remove_preference" class="remove-btn" title="Supprimer">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                        
                        <h3><?php echo htmlspecialchars($pref['game_name']); ?></h3>
                        <div class="preference-info">
                            <i class="fas fa-star"></i> Niveau: <?php echo htmlspecialchars($pref['skill_level']); ?>
                        </div>
                        <div class="preference-info">
                            <i class="fas fa-clock"></i> Horaires: <?php echo htmlspecialchars($pref['preferred_playtime']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 