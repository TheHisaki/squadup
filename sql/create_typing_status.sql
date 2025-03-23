CREATE TABLE IF NOT EXISTS typing_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    typing_to_user_id INT NOT NULL,
    last_typed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (typing_to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_typing (user_id, typing_to_user_id)
); 