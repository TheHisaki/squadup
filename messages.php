<?php
require_once 'config/database.php';
require_once 'config/security.php';
session_start();

// Définir le chemin de base
define('BASE_PATH', __DIR__);

// Vérifier si l'utilisateur est connecté
check_auth();

// Récupérer l'ID de l'utilisateur avec qui on veut discuter
$chat_user_id = isset($_GET['user']) ? filter_var($_GET['user'], FILTER_SANITIZE_NUMBER_INT) : null;

// Récupérer la liste des conversations
$stmt = $conn->prepare("
    SELECT DISTINCT 
        u.id,
        u.username,
        u.avatar,
        m.content as last_message,
        m.created_at as last_message_time,
        m.is_read,
        (SELECT COUNT(*) FROM messages 
         WHERE ((from_user_id = ? AND to_user_id = u.id) 
         OR (from_user_id = u.id AND to_user_id = ?))
         AND is_read = 0 AND to_user_id = ?) as unread_count
    FROM users u
    LEFT JOIN messages m ON (
        (m.from_user_id = u.id AND m.to_user_id = ?) 
        OR (m.from_user_id = ? AND m.to_user_id = u.id)
    )
    WHERE u.id IN (
        SELECT DISTINCT 
            CASE 
                WHEN from_user_id = ? THEN to_user_id 
                ELSE from_user_id 
            END
        FROM messages
        WHERE from_user_id = ? OR to_user_id = ?
    )
    GROUP BY u.id
    ORDER BY m.created_at DESC
");
$stmt->execute([
    $_SESSION['user_id'], 
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id']
]);
$conversations = $stmt->fetchAll();

// Si un utilisateur spécifique est sélectionné, récupérer ses informations
$chat_user = null;
if ($chat_user_id) {
    // Vérifier si l'utilisateur est bloqué
    $stmt = $conn->prepare("
        SELECT id FROM blocked_users 
        WHERE (user_id = ? AND blocked_user_id = ?)
        OR (user_id = ? AND blocked_user_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $chat_user_id, $chat_user_id, $_SESSION['user_id']]);
    $is_blocked = $stmt->fetch() !== false;

    if ($is_blocked) {
        header("Location: messages.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->execute([$chat_user_id]);
    $chat_user = $stmt->fetch();

    // Marquer les messages comme lus
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0
    ");
    $stmt->execute([$chat_user_id, $_SESSION['user_id']]);

    // Récupérer l'historique des messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.avatar
        FROM messages m
        JOIN users u ON m.from_user_id = u.id
        WHERE (m.from_user_id = ? AND m.to_user_id = ?)
        OR (m.from_user_id = ? AND m.to_user_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $chat_user_id, $chat_user_id, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll();
}

// Générer le token CSRF avant son utilisation
$csrf_token = generate_csrf_token();

// Traitement de l'envoi d'un nouveau message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    verify_csrf_token($_POST['csrf_token']);
    
    $message = clean_input($_POST['message']);
    $to_user_id = filter_var($_POST['to_user_id'], FILTER_SANITIZE_NUMBER_INT);
    
    if (!empty($message) && $to_user_id) {
        $stmt = $conn->prepare("
            INSERT INTO messages (from_user_id, to_user_id, content, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $to_user_id, $message]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message_id' => $conn->lastInsertId(),
            'message' => $message,
            'time' => date('H:i')
        ]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Message vide ou destinataire invalide']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - SquadUp</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://emoji-css.afeld.me/emoji.css" rel="stylesheet">
    <style>
        .messages-container {
            max-width: 1400px;
            margin: 120px auto;
            padding: 1rem;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1rem;
            height: calc(100vh - 140px);
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .conversations-list {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .conversations-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .conversations-header h2 {
            font-family: 'Press Start 2P', cursive;
            font-size: 1rem;
            margin: 0;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .conversations-search {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(138, 43, 226, 0.2);
        }

        .conversations-list-content {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 0.5rem;
            position: relative;
            text-decoration: none;
            color: inherit;
        }

        .conversation-item:hover {
            background: rgba(138, 43, 226, 0.1);
            transform: translateY(-2px);
        }

        .conversation-item.active {
            background: linear-gradient(45deg, rgba(138, 43, 226, 0.2), rgba(255, 105, 180, 0.2));
            border: 1px solid rgba(138, 43, 226, 0.3);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .conversation-item:hover .conversation-avatar {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .last-message {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        .conversation-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .message-time {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .unread-badge {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(138, 43, 226, 0.3);
        }

        .chat-container {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.02);
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .chat-actions {
            display: flex;
            gap: 1rem;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--text-color);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            max-width: 80%;
            opacity: 0;
            transform: translateY(20px);
            animation: slideIn 0.3s ease forwards;
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .message-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .message-bubble {
            padding: 1rem;
            border-radius: 18px;
            position: relative;
            max-width: 100%;
            word-wrap: break-word;
        }

        .message.sent .message-bubble {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.received .message-bubble {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            border-bottom-left-radius: 5px;
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .message-reactions {
            display: flex;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .reaction {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reaction:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .chat-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.02);
        }

        .message-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            height: 42px;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            height: 42px;
            padding: 0 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .message-input-container {
            flex: 1;
            position: relative;
            height: 42px;
            display: flex;
            align-items: center;
        }

        .message-input {
            width: 100%;
            height: 42px;
            padding: 0.8rem 3rem 0.8rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            resize: none;
            line-height: 1.5;
        }

        .action-button {
            background: none;
            border: none;
            color: var(--text-color);
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .emoji-button {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .send-button {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            height: 42px;
            padding: 0 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .emoji-picker {
            position: absolute;
            bottom: 100%;
            right: 0;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1rem;
            display: none;
            z-index: 1000;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .emoji-picker.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .typing-indicator {
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .typing-dots {
            display: flex;
            gap: 0.2rem;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: typingDot 1.4s infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typingDot {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-4px); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
                margin: 0;
                height: 100vh;
                border-radius: 0;
            }

            .conversations-list {
                display: none;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1000;
            }

            .conversations-list.active {
                display: block;
            }

            .chat-container {
                border-radius: 0;
            }

            .back-to-conversations {
                display: block;
            }
        }

        .user-profile-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.5rem;
            min-width: 200px;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .dropdown-menu.active {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1rem;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            padding: 2rem;
        }

        .no-chat-selected i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .no-chat-selected h2 {
            font-family: 'Press Start 2P', cursive;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .no-chat-selected p {
            font-size: 1rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/navbar.php'; ?>

    <div class="messages-container">
        <div class="conversations-list">
            <div class="conversations-header">
                <h2>Messages</h2>
            </div>
            <div class="conversations-search">
                <input type="text" class="search-input" placeholder="Rechercher une conversation..." id="conversation-search">
            </div>
            <div class="conversations-list-content">
                <?php foreach ($conversations as $conv): ?>
                    <a href="?user=<?php echo $conv['id']; ?>" class="conversation-item <?php echo $chat_user_id == $conv['id'] ? 'active' : ''; ?>">
                        <img src="<?php echo $conv['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="conversation-avatar">
                        <div class="conversation-info">
                            <div class="conversation-name"><?php echo htmlspecialchars($conv['username']); ?></div>
                            <?php if ($conv['last_message']): ?>
                                <div class="last-message"><?php echo htmlspecialchars($conv['last_message']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="conversation-meta">
                            <?php if ($conv['last_message_time']): ?>
                                <div class="message-time"><?php echo date('H:i', strtotime($conv['last_message_time'])); ?></div>
                            <?php endif; ?>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="chat-container">
            <?php if ($chat_user): ?>
                <div class="chat-header">
                    <div class="chat-user-info">
                        <a href="view_profile.php?id=<?php echo $chat_user['id']; ?>" class="user-profile-link">
                            <img src="<?php echo $chat_user['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="conversation-avatar">
                            <div class="conversation-info">
                                <div class="conversation-name"><?php echo htmlspecialchars($chat_user['username']); ?></div>
                            </div>
                        </a>
                    </div>
                    <div class="chat-actions">
                        <button class="action-btn" title="Appel vocal">
                            <i class="fas fa-phone"></i>
                        </button>
                        <button class="action-btn" title="Appel vidéo">
                            <i class="fas fa-video"></i>
                        </button>
                        <div class="dropdown">
                            <button class="action-btn" title="Plus d'options" id="more-options">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu" id="options-menu">
                                <a href="#" class="dropdown-item" onclick="blockUser()">
                                    <i class="fas fa-ban"></i> Bloquer l'utilisateur
                                </a>
                                <a href="#" class="dropdown-item" onclick="reportUser()">
                                    <i class="fas fa-flag"></i> Signaler l'utilisateur
                                </a>
                                <a href="#" class="dropdown-item" onclick="clearChat()">
                                    <i class="fas fa-trash"></i> Effacer la conversation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['from_user_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>" data-message-id="<?php echo $message['id']; ?>">
                            <img src="<?php echo $message['avatar'] ?? 'assets/images/default-avatar.png'; ?>" alt="Avatar" class="message-avatar">
                            <div class="message-content">
                                <div class="message-bubble">
                                    <?php echo htmlspecialchars($message['content']); ?>
                                </div>
                                <div class="message-meta">
                                    <span class="message-time"><?php echo date('H:i', strtotime($message['created_at'])); ?></span>
                                    <?php if ($message['from_user_id'] == $_SESSION['user_id'] && $message['is_read']): ?>
                                        <span class="message-status"><i class="fas fa-check-double"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-reactions" id="reactions-<?php echo $message['id']; ?>">
                                    <!-- Les réactions seront ajoutées dynamiquement -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="typing-indicator" id="typing-indicator" style="display: none;">
                    <span class="typing-text"></span>
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>

                <?php if (!$is_blocked): ?>
                    <div class="chat-footer">
                        <form class="message-form" id="chat-form" method="POST" onsubmit="return false;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="to_user_id" value="<?php echo $chat_user['id']; ?>">
                            
                            <div class="message-actions">
                                <button type="button" class="action-button" id="attach-file" title="Joindre un fichier">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button type="button" class="action-button" id="record-voice" title="Message vocal">
                                    <i class="fas fa-microphone"></i>
                                </button>
                            </div>

                            <div class="message-input-container">
                                <textarea 
                                    name="message" 
                                    class="message-input" 
                                    placeholder="Écrivez votre message..." 
                                    id="message-input"
                                    rows="1"
                                ></textarea>
                                <button type="button" class="action-button emoji-button" id="emoji-button" title="Emoji">
                                    <i class="far fa-smile"></i>
                                </button>
                                <div class="emoji-picker" id="emoji-picker">
                                    <!-- Les emojis seront ajoutés dynamiquement -->
                                </div>
                            </div>

                            <button type="submit" class="send-button">
                                <i class="fas fa-paper-plane"></i>
                                <span>Envoyer</span>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="blocked-message">
                        <p>Vous ne pouvez pas envoyer de messages à cet utilisateur car il est bloqué.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-chat-selected">
                    <i class="fas fa-comments"></i>
                    <h2>Sélectionnez une conversation</h2>
                    <p>Choisissez un utilisateur dans la liste pour commencer à discuter</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initializeChat();
        });

        function initializeChat() {
            // Initialiser les composants
            initializeEmojiPicker();
            initializeVoiceRecording();
            initializeFileUpload();
            initializeMessageInput();
            initializeSearch();
            
            // Démarrer les polling si une conversation est sélectionnée
            if (document.getElementById('chat-messages')) {
                startMessagePolling();
                startTypingPolling();
            }

            // Faire défiler jusqu'au dernier message
            scrollToBottom();
        }

        function initializeEmojiPicker() {
            const emojiButton = document.getElementById('emoji-button');
            const emojiPicker = document.getElementById('emoji-picker');
            const messageInput = document.getElementById('message-input');

            // Créer la grille d'emojis
            const emojis = ['😀', '😂', '😍', '🥰', '😊', '😎', '🤔', '😅', '😭', '😤', '👍', '👎', '❤️', '🎮', '🎲', '🎯', '🎪', '🎨'];
            
            const emojiGrid = document.createElement('div');
            emojiGrid.style.display = 'grid';
            emojiGrid.style.gridTemplateColumns = 'repeat(6, 1fr)';
            emojiGrid.style.gap = '0.5rem';

            emojis.forEach(emoji => {
                const emojiSpan = document.createElement('span');
                emojiSpan.textContent = emoji;
                emojiSpan.style.cursor = 'pointer';
                emojiSpan.style.fontSize = '1.5rem';
                emojiSpan.onclick = () => {
                    messageInput.value += emoji;
                    messageInput.focus();
                };
                emojiGrid.appendChild(emojiSpan);
            });

            emojiPicker.appendChild(emojiGrid);

            // Gérer l'affichage du picker
            emojiButton.onclick = (e) => {
                e.stopPropagation();
                emojiPicker.classList.toggle('active');
            };

            document.addEventListener('click', (e) => {
                if (!emojiPicker.contains(e.target) && !emojiButton.contains(e.target)) {
                    emojiPicker.classList.remove('active');
                }
            });
        }

        function initializeVoiceRecording() {
            const recordButton = document.getElementById('record-voice');
            let mediaRecorder;
            let audioChunks = [];
            let isRecording = false;

            recordButton.addEventListener('click', async () => {
                try {
                    if (!isRecording) {
                        // Démarrer l'enregistrement
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        mediaRecorder = new MediaRecorder(stream);
                        
                        mediaRecorder.ondataavailable = (e) => {
                            audioChunks.push(e.data);
                        };

                        mediaRecorder.onstop = async () => {
                            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                            await sendVoiceMessage(audioBlob);
                            audioChunks = [];
                            stream.getTracks().forEach(track => track.stop());
                        };

                        mediaRecorder.start();
                        isRecording = true;
                        recordButton.innerHTML = '<i class="fas fa-stop"></i>';
                        recordButton.style.color = 'red';
                    } else {
                        // Arrêter l'enregistrement
                        mediaRecorder.stop();
                        isRecording = false;
                        recordButton.innerHTML = '<i class="fas fa-microphone"></i>';
                        recordButton.style.color = '';
                    }
                } catch (error) {
                    console.error('Erreur lors de l\'enregistrement audio:', error);
                    alert('Impossible d\'accéder au microphone');
                }
            });
        }

        function initializeFileUpload() {
            const fileButton = document.getElementById('attach-file');
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.multiple = true;
            fileInput.accept = 'image/*,.pdf,.doc,.docx';
            fileInput.style.display = 'none';
            document.body.appendChild(fileInput);

            fileButton.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', async () => {
                const files = Array.from(fileInput.files);
                for (const file of files) {
                    await handleFileUpload(file);
                }
                fileInput.value = '';
            });
        }

        function initializeMessageInput() {
            const messageInput = document.getElementById('message-input');
            const form = document.getElementById('chat-form');
            
            if (!messageInput || !form) return;

            // Gérer le statut de frappe
            let typingTimeout;
            messageInput.addEventListener('input', () => {
                clearTimeout(typingTimeout);
                updateTypingStatus(true);
                typingTimeout = setTimeout(() => updateTypingStatus(false), 3000);
            });
        }

        function initializeSearch() {
            const searchInput = document.getElementById('conversation-search');
            const conversations = document.querySelectorAll('.conversation-item');

            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                conversations.forEach(conv => {
                    const name = conv.querySelector('.conversation-name').textContent.toLowerCase();
                    const lastMessage = conv.querySelector('.last-message')?.textContent.toLowerCase() || '';
                    const isVisible = name.includes(searchTerm) || lastMessage.includes(searchTerm);
                    conv.style.display = isVisible ? '' : 'none';
                });
            });
        }

        async function sendMessage(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = e.target;
            const messageInput = form.querySelector('#message-input');
            const message = messageInput.value.trim();

            if (!message) return;

            try {
                const formData = new FormData();
                formData.append('message', message);
                formData.append('to_user_id', form.querySelector('[name="to_user_id"]').value);
                formData.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();

                if (data.success) {
                    // Ajouter le message à la conversation
                    appendMessage({
                        id: data.message_id,
                        content: message,
                        from_user_id: <?php echo $_SESSION['user_id']; ?>,
                        username: '<?php echo $_SESSION['username']; ?>',
                        avatar: '<?php echo $_SESSION['avatar'] ?? 'assets/images/default-avatar.png'; ?>',
                        time: data.time,
                        is_read: false
                    });

                    // Réinitialiser le formulaire
                    messageInput.value = '';
                    messageInput.style.height = '42px';
                    scrollToBottom();
                } else {
                    console.error('Erreur lors de l\'envoi du message:', data.error);
                }
            } catch (error) {
                console.error('Erreur lors de l\'envoi du message:', error);
            }
        }

        async function handleFileUpload(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('to_user_id', document.querySelector('[name="to_user_id"]').value);
            formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

            try {
                const response = await fetch('upload_attachment.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    appendMessage({
                        id: data.message_id,
                        content: '',
                        from_user_id: <?php echo $_SESSION['user_id']; ?>,
                        username: '<?php echo $_SESSION['username']; ?>',
                        avatar: '<?php echo $_SESSION['avatar'] ?? 'assets/images/default-avatar.png'; ?>',
                        time: data.time,
                        attachment: data.attachment
                    });
                    scrollToBottom();
                } else {
                    alert('Erreur lors de l\'envoi du fichier: ' + data.error);
                }
            } catch (error) {
                console.error('Erreur lors de l\'upload du fichier:', error);
                alert('Erreur lors de l\'envoi du fichier');
            }
        }

        async function sendVoiceMessage(audioBlob) {
            const formData = new FormData();
            formData.append('audio', audioBlob, 'message.wav');
            formData.append('to_user_id', document.querySelector('[name="to_user_id"]').value);
            formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

            try {
                const response = await fetch('send_voice_message.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    appendMessage({
                        id: data.message_id,
                        content: '',
                        from_user_id: <?php echo $_SESSION['user_id']; ?>,
                        username: '<?php echo $_SESSION['username']; ?>',
                        avatar: '<?php echo $_SESSION['avatar'] ?? 'assets/images/default-avatar.png'; ?>',
                        time: data.time,
                        audio: data.audio_url
                    });
                    scrollToBottom();
                } else {
                    alert('Erreur lors de l\'envoi du message vocal: ' + data.error);
                }
            } catch (error) {
                console.error('Erreur lors de l\'envoi du message vocal:', error);
                alert('Erreur lors de l\'envoi du message vocal');
            }
        }

        function appendMessage(message) {
            const chatMessages = document.getElementById('chat-messages');
            const isCurrentUser = message.from_user_id == <?php echo $_SESSION['user_id']; ?>;
            const messageHTML = `
                <div class="message ${isCurrentUser ? 'sent' : 'received'}" data-message-id="${message.id}">
                    <img src="${message.avatar}" alt="Avatar" class="message-avatar">
                    <div class="message-content">
                        <div class="message-bubble">
                            ${message.content}
                            ${message.attachment ? `
                                <div class="attachment">
                                    ${message.attachment.type.startsWith('image/') 
                                        ? `<img src="${message.attachment.url}" alt="Image jointe">`
                                        : `<a href="${message.attachment.url}" target="_blank">
                                            <i class="fas fa-file"></i> ${message.attachment.name}
                                          </a>`
                                    }
                                </div>
                            ` : ''}
                            ${message.audio ? `
                                <div class="voice-message">
                                    <audio controls src="${message.audio}"></audio>
                                </div>
                            ` : ''}
                        </div>
                        <div class="message-meta">
                            <span class="message-time">${message.time}</span>
                            ${isCurrentUser && message.is_read ? '<span class="message-status"><i class="fas fa-check-double"></i></span>' : ''}
                        </div>
                        <div class="message-reactions" id="reactions-${message.id}"></div>
                    </div>
                </div>
            `;
            chatMessages.insertAdjacentHTML('beforeend', messageHTML);
            
            // Si c'est un message reçu, le marquer comme lu
            if (!isCurrentUser) {
                markMessagesAsRead();
            }
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        async function updateTypingStatus(isTyping) {
            try {
                await fetch('update_typing_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `to_user_id=${document.querySelector('[name="to_user_id"]').value}&is_typing=${isTyping ? 1 : 0}`
                });
            } catch (error) {
                console.error('Erreur lors de la mise à jour du statut de frappe:', error);
            }
        }

        function startMessagePolling() {
            setInterval(async () => {
                try {
                    const lastMessageId = document.querySelector('.message:last-child')?.dataset.messageId || 0;
                    const response = await fetch(`get_new_messages.php?last_id=${lastMessageId}&user_id=${document.querySelector('[name="to_user_id"]').value}`);
                    const data = await response.json();

                    if (data.success) {
                        if (data.messages.length > 0) {
                            data.messages.forEach(message => appendMessage(message));
                            scrollToBottom();
                        }
                        // Marquer les messages comme lus
                        markMessagesAsRead();
                    }
                } catch (error) {
                    console.error('Erreur lors de la récupération des nouveaux messages:', error);
                }
            }, 3000);
        }

        async function markMessagesAsRead() {
            try {
                const userId = document.querySelector('[name="to_user_id"]').value;
                const response = await fetch('mark_messages_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `from_user_id=${userId}&csrf_token=${document.querySelector('[name="csrf_token"]').value}`
                });
                
                const data = await response.json();
                if (data.success) {
                    // Mettre à jour les indicateurs de lecture dans l'interface
                    document.querySelectorAll('.message.received').forEach(message => {
                        const statusSpan = message.querySelector('.message-status');
                        if (!statusSpan) {
                            const metaDiv = message.querySelector('.message-meta');
                            const newStatus = document.createElement('span');
                            newStatus.className = 'message-status';
                            newStatus.innerHTML = '<i class="fas fa-check-double"></i>';
                            metaDiv.appendChild(newStatus);
                        }
                    });
                }
            } catch (error) {
                console.error('Erreur lors du marquage des messages comme lus:', error);
            }
        }

        function startTypingPolling() {
            setInterval(async () => {
                try {
                    const response = await fetch(`check_typing_status.php?user_id=${document.querySelector('[name="to_user_id"]').value}`);
                    const data = await response.json();

                    const typingIndicator = document.getElementById('typing-indicator');
                    if (data.success && data.is_typing) {
                        typingIndicator.querySelector('.typing-text').textContent = `${data.username} est en train d'écrire`;
                        typingIndicator.style.display = 'flex';
                    } else {
                        typingIndicator.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Erreur lors de la vérification du statut de frappe:', error);
                }
            }, 3000);
        }

        // Nettoyer les ressources lors de la fermeture de la page
        window.addEventListener('beforeunload', () => {
            updateTypingStatus(false);
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Initialiser le menu déroulant
            const moreOptions = document.getElementById('more-options');
            const optionsMenu = document.getElementById('options-menu');
            
            if (moreOptions && optionsMenu) {
                moreOptions.addEventListener('click', (e) => {
                    e.stopPropagation();
                    optionsMenu.classList.toggle('active');
                });

                document.addEventListener('click', (e) => {
                    if (!optionsMenu.contains(e.target)) {
                        optionsMenu.classList.remove('active');
                    }
                });
            }

            // Fonctions pour le menu déroulant
            window.blockUser = () => {
                if (confirm('Êtes-vous sûr de vouloir bloquer cet utilisateur ?')) {
                    // Implémenter la logique de blocage
                    console.log('Utilisateur bloqué');
                }
            };

            window.reportUser = () => {
                if (confirm('Voulez-vous signaler cet utilisateur ?')) {
                    // Implémenter la logique de signalement
                    console.log('Utilisateur signalé');
                }
            };

            window.clearChat = () => {
                if (confirm('Êtes-vous sûr de vouloir effacer cette conversation ?')) {
                    // Implémenter la logique de suppression
                    console.log('Conversation effacée');
                }
            };

            // Gérer l'envoi des messages
            const form = document.getElementById('chat-form');
            const messageInput = document.getElementById('message-input');

            if (form && messageInput) {
                // Gérer la soumission du formulaire et la touche Entrée dans une seule fonction
                const handleSubmit = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    sendMessage(e);
                };

                // Attacher les gestionnaires d'événements
                form.addEventListener('submit', handleSubmit);
                messageInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        e.stopPropagation();
                        handleSubmit(new Event('submit'));
                    }
                });
            }
        });
    </script>
</body>
</html> 