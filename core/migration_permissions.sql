-- Migration: Add moderator role + permissions table
-- Run against the existing database: mysql -h db -u root -p itec106 < migration_permissions.sql

ALTER TABLE accounts MODIFY role ENUM('player', 'moderator', 'admin') NOT NULL DEFAULT 'player';

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('player', 'moderator', 'admin') NOT NULL,
    permission VARCHAR(50) NOT NULL,
    UNIQUE KEY (role, permission)
) ENGINE=InnoDB;

INSERT INTO permissions (role, permission) VALUES
('moderator', 'admin.access'),
('moderator', 'assets.view'),
('moderator', 'assets.create'),
('moderator', 'assets.edit'),
('moderator', 'assets.edit_price'),
('moderator', 'players.view'),
('moderator', 'scores.view'),
('admin', 'admin.access'),
('admin', 'assets.view'),
('admin', 'assets.create'),
('admin', 'assets.edit'),
('admin', 'assets.edit_price'),
('admin', 'assets.delete'),
('admin', 'players.view'),
('admin', 'players.edit'),
('admin', 'scores.view'),
('admin', 'scores.delete')
ON DUPLICATE KEY UPDATE permission = VALUES(permission);

INSERT IGNORE INTO accounts (first_name, surname, email_addr, username, birthdate, password, role) VALUES
('Moderator', 'User', 'moderator@example.com', 'moderator', '1995-03-20', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator');
