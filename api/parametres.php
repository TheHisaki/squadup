<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Vérifier si l'utilisateur est connecté
check_auth();

// Initialiser les variables de message
$success_message = '';
$error_message = '';

// Générer le token CSRF
$csrf_token = generate_csrf_token();

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    verify_csrf_token($_POST['csrf_token']);

    // Modification du pseudo
    if (isset($_POST['update_username'])) {
        $new_username = clean_input($_POST['username']);
        
        // Vérifier si le pseudo est déjà utilisé
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$new_username, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $error_message = "Ce pseudo est déjà utilisé.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            if ($stmt->execute([$new_username, $_SESSION['user_id']])) {
                $success_message = "Pseudo mis à jour avec succès!";
                $user['username'] = $new_username;
            }
        }
    }

    // Modification du mot de passe
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Vérifier le mot de passe actuel
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $success_message = "Mot de passe mis à jour avec succès!";
                    }
                } else {
                    $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
                }
            } else {
                $error_message = "Les nouveaux mots de passe ne correspondent pas.";
            }
        } else {
            $error_message = "Mot de passe actuel incorrect.";
        }
    }

    // Suppression du compte
    if (isset($_POST['delete_account'])) {
        if (password_verify($_POST['confirm_delete_password'], $user['password'])) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$_SESSION['user_id']])) {
                session_destroy();
                header('Location: login.php?message=account_deleted');
                exit;
            }
        } else {
            $error_message = "Mot de passe incorrect pour la suppression du compte.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 120px auto;
            padding: 2rem;
        }

        .settings-section {
            background: var(--card-bg);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-color);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .submit-btn {
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

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }

        .danger-zone {
            border-color: #ff4444;
        }

        .danger-zone .section-title {
            color: #ff4444;
        }

        .delete-btn {
            background: #ff4444;
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

        .delete-btn:hover {
            background: #ff0000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 68, 68, 0.3);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
            text-decoration: none;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        .success-message, .error-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .success-message {
            background: rgba(46, 213, 115, 0.1);
            border: 1px solid #2ed573;
            color: #2ed573;
        }

        .error-message {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid #ff4444;
            color: #ff4444;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="settings-container">
        <a href="profile.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Retour au profil
        </a>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="settings-section">
            <h2 class="section-title"><i class="fas fa-user"></i> Modifier le pseudo</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label for="username">Nouveau pseudo</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <button type="submit" name="update_username" class="submit-btn">
                    <i class="fas fa-save"></i> Mettre à jour le pseudo
                </button>
            </form>
        </div>

        <div class="settings-section">
            <h2 class="section-title"><i class="fas fa-lock"></i> Modifier le mot de passe</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit" name="update_password" class="submit-btn">
                    <i class="fas fa-key"></i> Mettre à jour le mot de passe
                </button>
            </form>
        </div>

        <div class="settings-section danger-zone">
            <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Zone de danger</h2>
            <p style="color: #ff4444; margin-bottom: 1rem;">La suppression de votre compte est irréversible. Toutes vos données seront définitivement effacées.</p>
            <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.');">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label for="confirm_delete_password">Entrez votre mot de passe pour confirmer</label>
                    <input type="password" name="confirm_delete_password" id="confirm_delete_password" required>
                </div>
                <button type="submit" name="delete_account" class="delete-btn">
                    <i class="fas fa-trash-alt"></i> Supprimer mon compte
                </button>
            </form>
        </div>
    </div>
</body>
</html> 