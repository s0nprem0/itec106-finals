-- ======================================================
-- DATABASE: itec106 (Tech Builder Quiz Game Database)
-- ======================================================

CREATE DATABASE IF NOT EXISTS itec106
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE itec106;

-- ======================================================
-- 1. Account System (Stores Player and Admin Information)
-- ======================================================
CREATE TABLE accounts (
    acct_id INT AUTO_INCREMENT PRIMARY KEY, -- unique account identifier (e.g., '1')
    first_name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    email_addr VARCHAR(255) NOT NULL,
    username VARCHAR(30) NOT NULL, -- unique username for login
    birthdate DATE NOT NULL, -- store as DATE for easier age calculations
    password VARCHAR(255) NOT NULL,  -- store hashed password
    role ENUM('player', 'moderator', 'admin') NOT NULL DEFAULT 'player' -- role to differentiate between players, moderators, and admins
) ENGINE=InnoDB;

-- ======================================================
-- 2. Assets Table (Hardware Inventory with Category and Pricing)
-- ======================================================
CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,  -- supports cents (e.g., 1599.99)
    category VARCHAR(50) DEFAULT 'Other',
    image_url VARCHAR(255), -- optional URL to an image of the item
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ======================================================
-- 3. Auth Tokens Table (Persistent "Remember Me" Login)
-- ======================================================
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acct_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (acct_id) REFERENCES accounts(acct_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ======================================================
-- 4. Scores Table (Tracks Player's Performance)
-- ======================================================
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acct_id INT NOT NULL,   -- references your account ID
    streak INT NOT NULL,              -- number of correct guesses in a row
    difficulty VARCHAR(10) NOT NULL DEFAULT 'medium', -- difficulty level: easy, medium, hard
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (acct_id)
        REFERENCES accounts(acct_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- ======================================================
-- 5. Permissions Table (Granular Role-Based Access Control)
-- ======================================================
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('player', 'moderator', 'admin') NOT NULL,
    permission VARCHAR(50) NOT NULL,
    UNIQUE KEY (role, permission)
) ENGINE=InnoDB;

-- Seed permissions for each role
INSERT INTO permissions (role, permission) VALUES
-- Moderator permissions (view + edit, no delete)
('moderator', 'admin.access'),
('moderator', 'assets.view'),
('moderator', 'assets.create'),
('moderator', 'assets.edit'),
('moderator', 'assets.edit_price'),
('moderator', 'players.view'),
('moderator', 'scores.view'),
-- Admin permissions (full access including delete)
('admin', 'admin.access'),
('admin', 'assets.view'),
('admin', 'assets.create'),
('admin', 'assets.edit'),
('admin', 'assets.edit_price'),
('admin', 'assets.delete'),
('admin', 'players.view'),
('admin', 'scores.view'),
('admin', 'scores.delete');

-- ======================================================
-- Seed Data (Optional: Insert Sample Data for Testing)
-- ======================================================
INSERT INTO accounts (first_name, surname, email_addr, username, birthdate, password, role) VALUES
('Admin', 'User', 'admin@example.com', 'admin', '1990-01-01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Jayson', 'Player', 'player1@example.com', 'player1', '2000-05-15', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player'),
('Moderator', 'User', 'moderator@example.com', 'moderator', '1995-03-20', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderator');

-- Seed 15 diverse hardware assets to test the game loop immediately
INSERT INTO assets (item_name, price, category, image_url) VALUES
('NVIDIA RTX 4090', 1599.99, 'GPU', NULL),
('AMD Ryzen 9 7950X3D', 699.00, 'CPU', NULL),
('Corsair Vengeance 32GB DDR5', 149.50, 'RAM', NULL),
('Samsung 990 Pro 2TB', 189.99, 'Storage', NULL),
('ASUS ROG Strix Z790-E', 399.99, 'Motherboard', NULL),
('Noctua NH-D15', 99.95, 'Cooler', NULL),
('Apple Vision Pro', 3499.00, 'VR/AR', NULL),
('Steam Deck OLED 1TB', 649.00, 'Console', NULL),
('Meta Quest 3 512GB', 649.00, 'VR/AR', NULL),
('LG C3 42-inch OLED TV', 1399.00, 'Monitor', NULL),
('Logitech MX Master 3S', 99.00, 'Peripherals', NULL),
('Wooting 60HE Keyboard', 175.00, 'Peripherals', NULL),
('Herman Miller Aeron Chair', 1320.00, 'Furniture', NULL),
('Sony PlayStation 5', 499.00, 'Console', NULL),
('Ubiquiti Dream Machine Pro', 379.00, 'Networking', NULL);