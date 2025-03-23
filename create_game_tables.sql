USE squadup;

-- Création de la table des jeux si elle n'existe pas
CREATE TABLE IF NOT EXISTS games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL
);

-- Création de la table des préférences de jeux si elle n'existe pas
CREATE TABLE IF NOT EXISTS user_game_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    skill_level VARCHAR(50) NOT NULL,
    preferred_playtime VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_game (user_id, game_id)
);

-- Insertion des jeux prédéfinis s'ils n'existent pas déjà
INSERT IGNORE INTO games (id, name) VALUES
(1, 'League of Legends'),
(2, 'Valorant'),
(3, 'Counter-Strike 2'),
(4, 'Fortnite'),
(5, 'Call of Duty: Warzone'),
(6, 'Apex Legends'),
(7, 'Minecraft'),
(8, 'World of Warcraft'),
(9, 'Overwatch 2'),
(10, 'Rocket League'),
(11, 'FIFA 24'),
(12, 'GTA Online'),
(13, 'Rainbow Six Siege'),
(14, 'Dota 2'),
(15, 'Among Us'); 