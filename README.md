# SquadUp

Une plateforme de mise en relation pour les joueurs.

## Structure des dossiers

- `config/` : Fichiers de configuration
- `uploads/` : Dossier pour les fichiers uploadés
  - `avatars/` : **IMPORTANT** - Ce dossier doit être préservé lors des réupload pour conserver les avatars des utilisateurs
- `assets/` : Ressources statiques (images, CSS, JS)
- `logs/` : Fichiers de logs

## Installation

1. Cloner le repository
2. Créer la base de données en utilisant le fichier `sql/database.sql`
3. Configurer la base de données dans `config/database.php`
4. S'assurer que le dossier `uploads/avatars` existe et a les permissions d'écriture (chmod 755)
5. Démarrer le serveur PHP

## Maintenance

Lors de la mise à jour du site :
- **NE PAS SUPPRIMER** le dossier `uploads/avatars` car il contient les avatars des utilisateurs
- Si nécessaire, sauvegarder le contenu du dossier `uploads/avatars` avant la mise à jour
- Après la mise à jour, restaurer le contenu du dossier `uploads/avatars` si nécessaire 