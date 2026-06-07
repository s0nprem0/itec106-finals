<?php 

define('REMEMBER_COOKIE', 'remember_token');
define('REMEMBER_DAYS', 30);

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['acct_id'])) {
        global $pdo;
        if ($pdo && Auth::tryRememberLogin($pdo)) {
            return;
        }
        session_write_close();
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}

function hasPermission($pdo, $permission) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $role = $_SESSION['role'] ?? 'player';
    $stmt = $pdo->prepare("SELECT 1 FROM permissions WHERE role = ? AND permission = ?");
    $stmt->execute([$role, $permission]);
    return (bool)$stmt->fetchColumn();
}

function requirePermission($pdo, $permission) {
    requireLogin();
    if (!hasPermission($pdo, $permission)) {
        http_response_code(403);
        die("Access denied.");
    }
}

class Auth {
    public static function loginUser($pdo, $username, $password, $remember = false) {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $last_attempt = $_SESSION['login_last_attempt'] ?? 0;
        $wait = min(pow(2, $attempts), 60);
        if ($attempts > 0 && time() - $last_attempt < $wait) {
            $remaining = $wait - (time() - $last_attempt);
            return "Too many failed attempts. Please wait $remaining seconds.";
        }

        $stmt = $pdo->prepare("SELECT acct_id, password, role FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['acct_id'] = $user['acct_id'];
            $_SESSION['role'] = $user['role'];

            if ($remember) {
                self::issueRememberToken($pdo, $user['acct_id']);
            }
            return true;
        }

        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['login_last_attempt'] = time();
        return "Invalid username or password.";
    }

    public static function tryRememberLogin($pdo) {
        if (isset($_SESSION['acct_id'])) {
            return true;
        }

        $rawToken = $_COOKIE[REMEMBER_COOKIE] ?? '';
        if (!$rawToken) {
            return false;
        }

        $hash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare("SELECT acct_id, expires_at FROM auth_tokens WHERE token_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row || strtotime($row['expires_at']) < time()) {
            self::clearRememberToken($pdo);
            return false;
        }

        $stmt2 = $pdo->prepare("SELECT acct_id, role FROM accounts WHERE acct_id = ?");
        $stmt2->execute([$row['acct_id']]);
        $user = $stmt2->fetch();

        if (!$user) {
            self::clearRememberToken($pdo);
            return false;
        }

        $_SESSION['acct_id'] = $user['acct_id'];
        $_SESSION['role'] = $user['role'];

        self::rotateRememberToken($pdo, $user['acct_id']);
        return true;
    }

    private static function issueRememberToken($pdo, $acctId) {
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $expires = date('Y-m-d H:i:s', time() + 86400 * REMEMBER_DAYS);

        $stmt = $pdo->prepare("INSERT INTO auth_tokens (acct_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$acctId, $hash, $expires]);

        setcookie(REMEMBER_COOKIE, $rawToken, [
            'expires'  => time() + 86400 * REMEMBER_DAYS,
            'path'     => BASE_URL . '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function rotateRememberToken($pdo, $acctId) {
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE acct_id = ?");
        $stmt->execute([$acctId]);

        self::issueRememberToken($pdo, $acctId);
    }

    public static function clearRememberToken($pdo) {
        $rawToken = $_COOKIE[REMEMBER_COOKIE] ?? '';
        if ($rawToken) {
            $hash = hash('sha256', $rawToken);
            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token_hash = ?");
            $stmt->execute([$hash]);
        }

        setcookie(REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => BASE_URL . '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function registerUser($pdo, $firstName, $surname, $email, $username, $birthdate, $password) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => "Invalid email format."];
        }

        $stmt = $pdo->prepare("SELECT acct_id FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => "Username already taken."];
        }

        $stmt = $pdo->prepare("SELECT acct_id FROM accounts WHERE email_addr = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => "Email address already in use."];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO accounts (first_name, surname, email_addr, username, birthdate, password, role) VALUES (?, ?, ?, ?, ?, ?, 'player')");

        if ($stmt->execute([$firstName, $surname, $email, $username, $birthdate, $passwordHash])) {
            return ['success' => true, 'message' => "Registration successful! You may now login."];
        }
        return ['success' => false, 'message' => "Registration failed. Please try again."];
    }
}