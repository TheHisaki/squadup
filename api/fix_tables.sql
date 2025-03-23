USE squadup;

-- Supprimer les tables existantes
DROP TABLE IF EXISTS user_game_preferences;
DROP TABLE IF EXISTS games;

-- Recréer la table des jeux
CREATE TABLE games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL
);

-- Recréer la table des préférences de jeux
CREATE TABLE user_game_preferences (
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

-- Insérer les jeux prédéfinis
INSERT INTO games (name) VALUES
('League of Legends'),
('Valorant'),
('Counter-Strike 2'),
('Fortnite'),
('Call of Duty: Warzone'),
('Apex Legends'),
('Minecraft'),
('World of Warcraft'),
('Overwatch 2'),
('Rocket League'),
('FIFA 24'),
('GTA Online'),
('Rainbow Six Siege'),
('Dota 2'),
('Among Us'); 