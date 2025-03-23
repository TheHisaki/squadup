<?php
require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/email.php';

function sendEmailSMTP($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName) {
    $smtpServer = SMTP_HOST;
    $smtpPort = SMTP_PORT;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;

    // Créer une connexion socket sécurisée
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    // Se connecter au serveur SMTP
    $socket = stream_socket_client(
        "ssl://{$smtpServer}:{$smtpPort}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        error_log("Erreur de connexion SMTP: $errstr ($errno)");
        return false;
    }

    // Lire la réponse du serveur
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '220') {
        error_log("Erreur SMTP - Réponse initiale: $response");
        fclose($socket);
        return false;
    }

    // Envoyer EHLO
    fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '250') {
        error_log("Erreur SMTP - EHLO: $response");
        fclose($socket);
        return false;
    }
    // Vider le buffer des réponses EHLO supplémentaires
    while (substr($response, 3, 1) === '-') {
        $response = fgets($socket);
    }

    // Authentification
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '334') {
        error_log("Erreur SMTP - AUTH: $response");
        fclose($socket);
        return false;
    }

    fputs($socket, base64_encode($username) . "\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '334') {
        error_log("Erreur SMTP - USERNAME: $response");
        fclose($socket);
        return false;
    }

    fputs($socket, base64_encode($password) . "\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '235') {
        error_log("Erreur SMTP - PASSWORD: $response");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fputs($socket, "MAIL FROM:<{$fromEmail}>\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '250') {
        error_log("Erreur SMTP - MAIL FROM: $response");
        fclose($socket);
        return false;
    }

    // RCPT TO
    fputs($socket, "RCPT TO:<{$to}>\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '250') {
        error_log("Erreur SMTP - RCPT TO: $response");
        fclose($socket);
        return false;
    }

    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '354') {
        error_log("Erreur SMTP - DATA: $response");
        fclose($socket);
        return false;
    }

    // En-têtes et contenu
    $message = "From: {$fromName} <{$fromEmail}>\r\n";
    $message .= "To: <{$to}>\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n\r\n";
    
    $message .= "--boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textBody . "\r\n\r\n";
    
    $message .= "--boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    
    $message .= "--boundary--\r\n";
    $message .= "\r\n.\r\n";

    // Envoyer le message
    fputs($socket, $message);
    $response = fgets($socket);
    if (substr($response, 0, 3) !== '250') {
        error_log("Erreur SMTP - Envoi message: $response");
        fclose($socket);
        return false;
    }

    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide";
    } else {
        // Vérifier si l'email existe dans la base de données
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            try {
                // Supprimer les anciens tokens non utilisés pour cet utilisateur
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0");
                $stmt->execute([$user['id']]);

                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Sauvegarder le token dans la base de données
                $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expiry]);

                // Construire le lien de réinitialisation avec le chemin correct
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/squadup/reset_password.php?token=" . $token;

                // Préparer le contenu de l'email
                $subject = "Réinitialisation de votre mot de passe SquadUp";
                
                $htmlBody = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #8a2be2;'>Bonjour " . htmlspecialchars($user['username']) . ",</h2>
                            <p>Vous avez demandé la réinitialisation de votre mot de passe sur SquadUp.</p>
                            <p>Cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe :</p>
                            <p style='text-align: center;'>
                                <a href='" . $resetLink . "' 
                                   style='display: inline-block; 
                                          padding: 12px 24px; 
                                          background: linear-gradient(45deg, #8a2be2, #9400d3); 
                                          color: white; 
                                          text-decoration: none; 
                                          border-radius: 5px;
                                          font-weight: bold;'>
                                    Réinitialiser mon mot de passe
                                </a>
                            </p>
                            <p><strong>Ce lien expirera dans 1 heure.</strong></p>
                            <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.</p>
                            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='color: #666; font-size: 12px;'>Ceci est un email automatique, merci de ne pas y répondre.</p>
                        </div>
                    </body>
                    </html>";

                $textBody = "Bonjour " . $user['username'] . ",\n\n" .
                           "Vous avez demandé la réinitialisation de votre mot de passe.\n\n" .
                           "Cliquez sur le lien suivant pour réinitialiser votre mot de passe :\n" .
                           $resetLink . "\n\n" .
                           "Ce lien expirera dans 1 heure.\n\n" .
                           "Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.\n\n" .
                           "L'équipe SquadUp";

                // Envoyer l'email
                if (sendEmailSMTP($email, $subject, $htmlBody, $textBody, SMTP_FROM_EMAIL, SMTP_FROM_NAME)) {
                    $success = "Un email de réinitialisation a été envoyé à votre adresse email.";
                } else {
                    error_log("Échec de l'envoi de l'email à $email");
                    throw new Exception("Erreur lors de l'envoi de l'email");
                }
            } catch (Exception $e) {
                error_log("Erreur d'envoi d'email: " . $e->getMessage());
                $error = "Une erreur est survenue lors de l'envoi de l'email. Veuillez réessayer plus tard.";
            }
        } else {
            // Pour des raisons de sécurité, on affiche le même message que si l'email existait
            $success = "Si cette adresse email est associée à un compte, vous recevrez un email de réinitialisation.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .forgot-password-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .forgot-password-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .forgot-password-header h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .forgot-password-form {
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

    <div class="forgot-password-container">
        <div class="forgot-password-header">
            <h1>Mot de passe oublié</h1>
            <p>Entrez votre adresse email pour réinitialiser votre mot de passe</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="forgot-password-form">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <div class="password-container">
                    <input type="email" id="email" name="email" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Envoyer le lien de réinitialisation
            </button>
        </form>

        <div class="back-to-login">
            <a href="login.php">Retour à la connexion</a>
        </div>
    </div>
</body>
</html> 