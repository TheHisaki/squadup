<?php
require_once 'config/database.php';
require_once 'config/security.php';

session_start();

$error = '';
$success = '';

// Vérifier si un token est fourni
if (!isset($_GET['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'];

// Vérifier si le token existe et n'est pas expiré
$stmt = $conn->prepare("
    SELECT pr.*, u.username 
    FROM password_resets pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.token = ? AND pr.expiry > NOW() AND pr.used = 0
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = "Ce lien de réinitialisation est invalide ou a expiré.";
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Valider le mot de passe
    if (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else if ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            // Mettre à jour le mot de passe
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            // Marquer le token comme utilisé
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->execute([$reset['id']]);
            
            $success = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
            
            // Rediriger vers la page de connexion après 3 secondes
            header("refresh:3;url=login.php");
        } catch (Exception $e) {
            $error = "Une erreur est survenue lors de la réinitialisation du mot de passe.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        .reset-password-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .reset-password-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reset-password-header h1 {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .reset-password-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
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

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            padding: 5px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
            animation: slideDown 0.5s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.2);
            color: #ff4444;
        }

        .password-requirements {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-to-login a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = event.currentTarget.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="reset-password-container">
        <div class="reset-password-header">
            <h1>Réinitialisation du mot de passe</h1>
            <?php if (!$error && !$success): ?>
                <p>Bonjour <?php echo htmlspecialchars($reset['username']); ?>, veuillez choisir votre nouveau mot de passe</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
            <?php if ($error === "Ce lien de réinitialisation est invalide ou a expiré."): ?>
                <div class="back-to-login">
                    <a href="forgot_password.php">Demander un nouveau lien</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!$error && !$success): ?>
            <form method="POST" class="reset-password-form">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        Le mot de passe doit contenir au moins 8 caractères
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-key"></i> Réinitialiser le mot de passe
                </button>
            </form>
        <?php endif; ?>

        <?php if (!$error || ($error && $error !== "Ce lien de réinitialisation est invalide ou a expiré.")): ?>
            <div class="back-to-login">
                <a href="login.php">Retour à la connexion</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 