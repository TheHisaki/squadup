-- Création de la base de données
CREATE DATABASE IF NOT EXISTS squadup;
USE squadup;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT,
    avatar VARCHAR(255),
    favorite_games TEXT,
    discord_username VARCHAR(100),
    age INT,
    language VARCHAR(50),
    platform VARCHAR(50),
    availability_morning BOOLEAN DEFAULT FALSE,
    availability_afternoon BOOLEAN DEFAULT FALSE,
    availability_evening BOOLEAN DEFAULT FALSE,
    availability_night BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des jeux
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_game_name (name)
);

-- Table des matchs entre utilisateurs
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des préférences de jeu des utilisateurs
CREATE TABLE IF NOT EXISTS user_game_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    skill_level ENUM('débutant', 'intermédiaire', 'expert') NOT NULL,
    preferred_playtime VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY unique_game_preference (user_id, game_id)
);

-- Table des messages entre utilisateurs
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insérer quelques jeux par défaut
INSERT IGNORE INTO games (name) VALUES 
('League of Legends'),
('Valorant'),
('Counter-Strike 2'),
('Fortnite'),
('Minecraft'),
('Call of Duty: Warzone'),
('Apex Legends'),
('Overwatch 2'),
('Rocket League'),
('FIFA 24'),
('GTA V'),
('Rainbow Six Siege'),
('Dota 2'),
('World of Warcraft'),
('Final Fantasy XIV'),
('Lost Ark'),
('Diablo IV'),
('Path of Exile'); 