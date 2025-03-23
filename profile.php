<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Définir le chemin de base
define('BASE_PATH', __DIR__);

// Initialiser les variables de message
$success_message = '';
$error_message = '';

// Vérifier si l'utilisateur est connecté
check_auth();

// Générer le token CSRF avant son utilisation
$csrf_token = generate_csrf_token();

// Liste des jeux prédéfinis
$games = [
    ['id' => 1, 'name' => 'League of Legends'],
    ['id' => 2, 'name' => 'Valorant'],
    ['id' => 3, 'name' => 'Counter-Strike 2'],
    ['id' => 4, 'name' => 'Fortnite'],
    ['id' => 5, 'name' => 'Call of Duty: Warzone'],
    ['id' => 6, 'name' => 'Apex Legends'],
    ['id' => 7, 'name' => 'Minecraft'],
    ['id' => 8, 'name' => 'World of Warcraft'],
    ['id' => 9, 'name' => 'Overwatch 2'],
    ['id' => 10, 'name' => 'Rocket League'],
    ['id' => 11, 'name' => 'FIFA 24'],
    ['id' => 12, 'name' => 'GTA Online'],
    ['id' => 13, 'name' => 'Rainbow Six Siege'],
    ['id' => 14, 'name' => 'Dota 2'],
    ['id' => 15, 'name' => 'Among Us']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    verify_csrf_token($_POST['csrf_token']);

    // Gestion de la mise à jour de l'avatar
    if (isset($_POST['update_avatar']) && isset($_FILES['avatar'])) {
        $response = ['success' => false];
        
        if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($filetype, $allowed)) {
                $response['error'] = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
            } else {
                $newname = uniqid() . '.' . $filetype;
                $upload_path = 'uploads/avatars/' . $newname;
                
                if (!getimagesize($_FILES['avatar']['tmp_name'])) {
                    $response['error'] = "Le fichier n'est pas une image valide.";
                } else {
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        if ($stmt->execute([$upload_path, $_SESSION['user_id']])) {
                            $response['success'] = true;
                        }
                    }
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if (isset($_POST['update_profile'])) {
        $bio = clean_input($_POST['bio']);
        $discord = clean_input($_POST['discord_username']);
        $age_range = clean_input($_POST['age']);
        // Convertir la tranche d'âge en valeur numérique pour la base de données
        switch ($age_range) {
            case '13-17':
                $age = 13;
                break;
            case '18-24':
                $age = 18;
                break;
            case '25-34':
                $age = 25;
                break;
            case '35+':
                $age = 35;
                break;
            default:
                $age = null;
        }
        $language = clean_input($_POST['language']);
        $platform = clean_input($_POST['platform']);
        $availability_morning = isset($_POST['availability_morning']) ? 1 : 0;
        $availability_afternoon = isset($_POST['availability_afternoon']) ? 1 : 0;
        $availability_evening = isset($_POST['availability_evening']) ? 1 : 0;
        $availability_night = isset($_POST['availability_night']) ? 1 : 0;

        // Mise à jour des informations du profil
        $stmt = $conn->prepare("
            UPDATE users 
            SET bio = ?, 
                discord_username = ?, 
                age = ?,
                language = ?,
                platform = ?,
                availability_morning = ?,
                availability_afternoon = ?,
                availability_evening = ?,
                availability_night = ?
            WHERE id = ?
        ");
        if ($stmt->execute([
            $bio, 
            $discord,
            $age,
            $language,
            $platform,
            $availability_morning,
            $availability_afternoon,
            $availability_evening,
            $availability_night,
            $_SESSION['user_id']
        ])) {
            $success_message = "Profil mis à jour avec succès!";
            // Recharger les informations de l'utilisateur
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } else {
            $error_message = "Erreur lors de la mise à jour du profil.";
        }
    }

    // Gestion de l'ajout d'une préférence de jeu
    if (isset($_POST['add_preference'])) {
        $game_id = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT);
        $skill_level = filter_input(INPUT_POST, 'skill_level', FILTER_SANITIZE_STRING);
        $preferred_playtime = filter_input(INPUT_POST, 'preferred_playtime', FILTER_SANITIZE_STRING);

        if ($game_id && $skill_level && $preferred_playtime) {
            // Vérifier si la préférence existe déjà
            $stmt = $conn->prepare('SELECT id FROM user_game_preferences WHERE user_id = ? AND game_id = ?');
            $stmt->execute([$_SESSION['user_id'], $game_id]);
            $result = $stmt->fetch();

            if (!$result) {
                $stmt = $conn->prepare('INSERT INTO user_game_preferences (user_id, game_id, skill_level, preferred_playtime) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $game_id, $skill_level, $preferred_playtime]);
                $success_message = "Jeu ajouté avec succès!";
            } else {
                $error_message = "Ce jeu est déjà dans vos préférences.";
            }
        }
    }

    // Gestion de la suppression d'une préférence de jeu
    if (isset($_POST['remove_preference'])) {
        $preference_id = filter_input(INPUT_POST, 'preference_id', FILTER_VALIDATE_INT);
        if ($preference_id) {
            $stmt = $conn->prepare('DELETE FROM user_game_preferences WHERE id = ? AND user_id = ?');
            $stmt->execute([$preference_id, $_SESSION['user_id']]);
            $success_message = "Jeu supprimé avec succès!";
        }
    }
}

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Compter le nombre d'amis
$stmt = $conn->prepare("
    SELECT COUNT(*) as friend_count 
    FROM friendships 
    WHERE (sender_id = ? OR receiver_id = ?) 
    AND status = 'accepted'
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$friend_count = $stmt->fetch()['friend_count'];

// Récupérer les préférences de jeu de l'utilisateur
$stmt = $conn->prepare("
    SELECT ugp.id, ugp.skill_level, ugp.preferred_playtime, g.name 
    FROM user_game_preferences ugp 
    JOIN games g ON ugp.game_id = g.id 
    WHERE ugp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$game_preferences = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - SquadUp</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .select-wrapper {
            position: relative;
            width: 100%;
        }

        .select-wrapper::after {
            content: '▼';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            pointer-events: none;
            font-size: 0.8rem;
        }

        select {
            width: 100%;
            padding: 12px 35px 12px 15px;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            background: rgba(138, 43, 226, 0.1);
            color: var(--text-color);
            appearance: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        select:hover, select:focus {
            background: rgba(138, 43, 226, 0.2);
            border-color: var(--secondary-color);
        }

        select option {
            background: var(--card-bg);
            color: var(--text-color);
            padding: 10px;
        }

        .game-preferences {
            margin-top: 0;
        }

        .game-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .game-item {
            background: rgba(138, 43, 226, 0.1);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--primary-color);
            position: relative;
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

        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: rgba(255, 68, 68, 0.1);
            transform: scale(1.1);
        }

        .submit-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }

        .no-preferences {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .no-preferences i {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .profile-header {
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            margin-bottom: 2rem;
            position: relative;
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

        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .avatar-upload:hover {
            transform: scale(1.1);
        }

        .avatar-upload input[type="file"] {
            display: none;
        }

        .profile-info {
            flex-grow: 1;
        }

        .profile-info h1 {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .profile-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group textarea,
        .form-group input[type="text"] {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.8rem;
            color: var(--text-color);
            resize: vertical;
        }

        .form-group textarea:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .availability-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: rgba(138, 43, 226, 0.1);
            border-radius: 8px;
        }

        .availability-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary-color);
        }

        .game-list-container {
            margin-top: 2rem;
        }

        .settings-btn {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(138, 43, 226, 0.1);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .settings-btn:hover {
            background: rgba(138, 43, 226, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.1);
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/navbar.php'; ?>

    <div class="profile-container">
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="profile-section">
            <div class="profile-header">
                <div class="avatar-container">
                    <img src="<?php echo $user['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="avatar">
                    <label class="avatar-upload">
                        <input type="file" name="avatar" accept="image/*">
                        <i class="fas fa-camera" style="color: white;"></i>
                    </label>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                    <p>Membre depuis le <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                    <p><i class="fas fa-user-friends"></i> <?php echo $friend_count; ?> ami<?php echo $friend_count > 1 ? 's' : ''; ?></p>
                </div>
                <a href="parametres.php" class="settings-btn">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
            </div>
        </div>

        <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-user-edit"></i> Informations personnelles</h2>
            <form method="POST" enctype="multipart/form-data" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="age">Âge</label>
                        <div class="select-wrapper">
                            <select name="age" id="age" required>
                                <option value="">Sélectionnez votre tranche d'âge</option>
                                <option value="13-17" <?php echo ($user['age'] >= 13 && $user['age'] <= 17) ? 'selected' : ''; ?>>13-17 ans</option>
                                <option value="18-24" <?php echo ($user['age'] >= 18 && $user['age'] <= 24) ? 'selected' : ''; ?>>18-24 ans</option>
                                <option value="25-34" <?php echo ($user['age'] >= 25 && $user['age'] <= 34) ? 'selected' : ''; ?>>25-34 ans</option>
                                <option value="35+" <?php echo ($user['age'] >= 35) ? 'selected' : ''; ?>>35 ans et plus</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="language">Langue principale</label>
                        <div class="select-wrapper">
                            <select name="language" id="language" required>
                                <option value="">Sélectionnez une langue</option>
                                <option value="fr" <?php echo ($user['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                <option value="en" <?php echo ($user['language'] ?? '') === 'en' ? 'selected' : ''; ?>>Anglais</option>
                                <option value="es" <?php echo ($user['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Espagnol</option>
                                <option value="de" <?php echo ($user['language'] ?? '') === 'de' ? 'selected' : ''; ?>>Allemand</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="platform">Plateforme principale</label>
                        <div class="select-wrapper">
                            <select name="platform" id="platform" required>
                                <option value="">Sélectionnez une plateforme</option>
                                <option value="PC" <?php echo ($user['platform'] ?? '') === 'PC' ? 'selected' : ''; ?>>PC</option>
                                <option value="PlayStation" <?php echo ($user['platform'] ?? '') === 'PlayStation' ? 'selected' : ''; ?>>PlayStation</option>
                                <option value="Xbox" <?php echo ($user['platform'] ?? '') === 'Xbox' ? 'selected' : ''; ?>>Xbox</option>
                                <option value="Nintendo Switch" <?php echo ($user['platform'] ?? '') === 'Nintendo Switch' ? 'selected' : ''; ?>>Nintendo Switch</option>
                                <option value="Mobile" <?php echo ($user['platform'] ?? '') === 'Mobile' ? 'selected' : ''; ?>>Mobile</option>
                                <option value="Multi-Plateformes" <?php echo ($user['platform'] ?? '') === 'Multi-Plateformes' ? 'selected' : ''; ?>>Multi-Plateformes</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="discord_username">Discord Username</label>
                        <input type="text" name="discord_username" id="discord_username" value="<?php echo htmlspecialchars($user['discord_username'] ?? ''); ?>" placeholder="Exemple: User#1234">
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea name="bio" id="bio" rows="4" placeholder="Parlez-nous un peu de vous..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Disponibilités</label>
                    <div class="availability-grid">
                        <div class="availability-item">
                            <input type="checkbox" name="availability_morning" id="morning" <?php echo ($user['availability_morning'] ?? false) ? 'checked' : ''; ?>>
                            <label for="morning">Matin (6h-12h)</label>
                        </div>
                        <div class="availability-item">
                            <input type="checkbox" name="availability_afternoon" id="afternoon" <?php echo ($user['availability_afternoon'] ?? false) ? 'checked' : ''; ?>>
                            <label for="afternoon">Après-midi (12h-18h)</label>
                        </div>
                        <div class="availability-item">
                            <input type="checkbox" name="availability_evening" id="evening" <?php echo ($user['availability_evening'] ?? false) ? 'checked' : ''; ?>>
                            <label for="evening">Soir (18h-00h)</label>
                        </div>
                        <div class="availability-item">
                            <input type="checkbox" name="availability_night" id="night" <?php echo ($user['availability_night'] ?? false) ? 'checked' : ''; ?>>
                            <label for="night">Nuit (00h-6h)</label>
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_profile" class="submit-btn">
                    <i class="fas fa-save"></i> Mettre à jour le profil
                </button>
            </form>
        </div>

        <div class="profile-section">
            <h2 class="section-title"><i class="fas fa-gamepad"></i> Mes jeux préférés</h2>
            <form method="POST" class="add-game-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="game_id">Jeu</label>
                        <div class="select-wrapper">
                            <select name="game_id" id="game_id" required>
                                <option value="">Sélectionnez un jeu</option>
                                <?php foreach ($games as $game): ?>
                                    <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="skill_level">Niveau</label>
                        <div class="select-wrapper">
                            <select name="skill_level" id="skill_level" required>
                                <option value="">Sélectionnez votre niveau</option>
                                <option value="débutant">Débutant</option>
                                <option value="intermédiaire">Intermédiaire</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="preferred_playtime">Horaires préférés</label>
                        <div class="select-wrapper">
                            <select name="preferred_playtime" id="preferred_playtime" required>
                                <option value="">Sélectionnez vos horaires</option>
                                <option value="matin">Matin (6h-12h)</option>
                                <option value="après-midi">Après-midi (12h-18h)</option>
                                <option value="soir">Soir (18h-00h)</option>
                                <option value="nuit">Nuit (00h-6h)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_preference" class="submit-btn">
                    <i class="fas fa-plus"></i> Ajouter un jeu
                </button>
            </form>

            <div class="game-list-container">
                <?php if (empty($game_preferences)): ?>
                    <div class="no-preferences">
                        <i class="fas fa-gamepad" style="font-size: 3rem;"></i>
                        <h2>Aucun jeu ajouté</h2>
                        <p>Ajoutez vos jeux préférés pour trouver des joueurs compatibles</p>
                    </div>
                <?php else: ?>
                    <div class="game-list">
                        <?php foreach ($game_preferences as $pref): ?>
                            <div class="game-item">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="preference_id" value="<?php echo $pref['id']; ?>">
                                    <button type="submit" name="remove_preference" class="remove-btn" title="Supprimer">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                <h3><?php echo htmlspecialchars($pref['name']); ?></h3>
                                <p><i class="fas fa-star"></i> Niveau: <?php echo htmlspecialchars($pref['skill_level']); ?></p>
                                <p><i class="fas fa-clock"></i> Horaires: <?php echo htmlspecialchars($pref['preferred_playtime']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.avatar').src = e.target.result;
                }
                reader.readAsDataURL(e.target.files[0]);

                // Créer et envoyer automatiquement le formulaire pour l'avatar uniquement
                const formData = new FormData();
                formData.append('avatar', e.target.files[0]);
                formData.append('csrf_token', '<?php echo $csrf_token; ?>');
                formData.append('update_avatar', '1'); // Nouveau champ pour identifier la mise à jour de l'avatar

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const successDiv = document.createElement('div');
                        successDiv.className = 'success-message';
                        successDiv.textContent = 'Photo de profil modifiée avec succès!';
                        document.querySelector('.profile-container').insertBefore(successDiv, document.querySelector('.profile-container').firstChild);
                        
                        // Faire disparaître le message après 3 secondes
                        setTimeout(() => {
                            successDiv.style.opacity = '0';
                            setTimeout(() => successDiv.remove(), 300);
                        }, 3000);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
            }
        });
    </script>
</body>
</html> 