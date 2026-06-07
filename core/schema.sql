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
    streak INT NOT NULL,              -- number of correct guesses in a row (unused in money mode)
    profit DECIMAL(10,2) DEFAULT NULL, -- final profit for money-based game mode
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
('admin', 'players.edit'),
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
('Ubiquiti Dream Machine Pro', 379.00, 'Networking', NULL),

-- New additions for more volatility and variety
('NVIDIA RTX 5090', 2499.99, 'GPU', NULL),
('AMD Radeon RX 8900 XTX', 1199.99, 'GPU', NULL),
('Intel Core i9-15900K', 589.00, 'CPU', NULL),
('G.Skill Trident Z5 64GB DDR5', 289.99, 'RAM', NULL),
('Crucial T705 4TB SSD', 449.99, 'Storage', NULL),
('Samsung Odyssey OLED G9', 1299.99, 'Monitor', NULL),
('Razer BlackWidow V4', 189.99, 'Peripherals', NULL),
('DJI Avata 2 FPV Drone', 999.00, 'Drone', NULL),
('Framework Laptop 16', 1899.00, 'Laptop', NULL);
('Alienware AW3423DW QD-OLED', 1099.99, 'Monitor', NULL),
('Corsair RM850x 850W PSU', 129.99, 'Power Supply', NULL),
('Lian Li O11 Dynamic EVO', 149.99, 'Case', NULL),
('Fractal Design North', 139.99, 'Case', NULL),
('WD Black SN850X 1TB NVMe', 89.99, 'Storage', NULL),
('Seagate IronWolf Pro 18TB HDD', 349.00, 'Storage', NULL),
('AMD Ryzen 7 7800X3D', 399.00, 'CPU', NULL),
('Intel Core i5-13600K', 299.99, 'CPU', NULL),
('NVIDIA RTX 4070 Ti SUPER', 799.99, 'GPU', NULL),
('AMD Radeon RX 7800 XT', 499.00, 'GPU', NULL),
('MSI MAG B650 Tomahawk WiFi', 219.00, 'Motherboard', NULL),
('Gigabyte X670E AORUS Master', 489.99, 'Motherboard', NULL),
('Corsair iCUE H150i Elite LCD', 289.99, 'Cooler', NULL),
('Arctic Liquid Freezer III 360', 119.99, 'Cooler', NULL),
('Razer DeathAdder V3 Pro', 149.99, 'Peripherals', NULL),
('SteelSeries Apex Pro TKL', 189.99, 'Peripherals', NULL),
('Elgato Stream Deck MK.2', 149.99, 'Peripherals', NULL),
('Shure SM7B Dynamic Microphone', 399.00, 'Audio', NULL),
('Sony WH-1000XM5 Headphones', 398.00, 'Audio', NULL),
('Focusrite Scarlett 2i2 Audio Interface', 199.99, 'Audio', NULL),
('Apple MacBook Pro 16 M3 Max', 3499.00, 'Laptop', NULL),
('ASUS ROG Zephyrus G14', 1599.99, 'Laptop', NULL),
('Xbox Series X 1TB', 499.99, 'Console', NULL),
('Nintendo Switch OLED', 349.99, 'Console', NULL),
('Synology DS923+ NAS', 599.99, 'Networking', NULL),
('Raspberry Pi 5 8GB', 80.00, 'Microcontroller', NULL);