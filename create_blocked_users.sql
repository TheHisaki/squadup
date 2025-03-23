-- Ajouter la colonne 'used' à la table password_resets si elle n'existe pas
ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS used BOOLEAN DEFAULT 0; 