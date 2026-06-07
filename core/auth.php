<?php 

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['acct_id'])) {
        header("Location: /itec106/index.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die("Access denied.");
    }
}

class Auth {
    public static function loginUser($pdo, $username, $password) {
        $stmt = $pdo->prepare("SELECT acct_id, password, role FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['acct_id'] = $user['acct_id'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return "Invalid username or password.";
    }

    public static function registerUser($pdo, $firstName, $surname, $email, $username, $birthdate, $password) {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT acct_id FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => "Username already taken."];
        }
        
        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user into the accounts table
        $stmt = $pdo->prepare("INSERT INTO accounts (first_name, surname, email_addr, username, birthdate, password, role) VALUES (?, ?, ?, ?, ?, ?, 'player')");
        
        if ($stmt->execute([$firstName, $surname, $email, $username, $birthdate, $passwordHash])) {
            return ['success' => true, 'message' => "Registration successful! You may now login."];
        }
        return ['success' => false, 'message' => "Registration failed. Please try again."];
    }
}