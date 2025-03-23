USE squadup;

-- Ajout de nouveaux champs à la table users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS age INT,
ADD COLUMN IF NOT EXISTS location VARCHAR(255),
ADD COLUMN IF NOT EXISTS availability JSON,
ADD COLUMN IF NOT EXISTS languages VARCHAR(255),
ADD COLUMN IF NOT EXISTS discord_id VARCHAR(255),
ADD COLUMN IF NOT EXISTS steam_id VARCHAR(255),
ADD COLUMN IF NOT EXISTS twitch_username VARCHAR(255),
ADD COLUMN IF NOT EXISTS youtube_channel VARCHAR(255),
ADD COLUMN IF NOT EXISTS profile_background VARCHAR(255),
ADD COLUMN IF NOT EXISTS achievements JSON;

-- Mise à jour de la table user_game_preferences
ALTER TABLE user_game_preferences
ADD COLUMN IF NOT EXISTS rank VARCHAR(50),
ADD COLUMN IF NOT EXISTS hours_played INT,
ADD COLUMN IF NOT EXISTS preferred_role VARCHAR(100),
ADD COLUMN IF NOT EXISTS preferred_server VARCHAR(50),
ADD COLUMN IF NOT EXISTS team_status VARCHAR(50),
ADD COLUMN IF NOT EXISTS achievements JSON;

-- Création d'une table pour les disponibilités hebdomadaires
CREATE TABLE IF NOT EXISTS weekly_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day_of_week TINYINT NOT NULL, -- 0 = Dimanche, 1 = Lundi, etc.
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_availability (user_id, day_of_week)
);

-- Création d'une table pour les badges/réalisations
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table de liaison entre utilisateurs et badges
CREATE TABLE IF NOT EXISTS user_badges (
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
); 