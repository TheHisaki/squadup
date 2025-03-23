<?php
// Configuration de sécurité générale
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Configuration des en-têtes de sécurité
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval'");

// Fonction pour générer un token CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier le token CSRF
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('CSRF token validation failed');
    }
    return true;
}

// Fonction pour nettoyer les entrées utilisateur
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour vérifier si l'utilisateur est connecté
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Fonction pour vérifier les tentatives de connexion
function check_login_attempts($ip) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = time();
        return true;
    }

    if ($_SESSION['login_attempts'] >= 5) {
        $time_passed = time() - $_SESSION['last_attempt'];
        if ($time_passed < 900) { // 15 minutes
            return false;
        }
        $_SESSION['login_attempts'] = 0;
    }
    return true;
}

// Fonction pour logger les activités suspectes
function log_suspicious_activity($type, $details) {
    $log_file = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_entry = "[$timestamp] [$ip] [$type] $details\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Fonction pour valider le mot de passe
function validate_password($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match("#[0-9]+#", $password)) return false;
    if (!preg_match("#[a-zA-Z]+#", $password)) return false;
    if (!preg_match("#[A-Z]+#", $password)) return false;
    if (!preg_match("#\W+#", $password)) return false;
    return true;
}

// Fonction pour générer un sel unique
function generate_salt() {
    return bin2hex(random_bytes(16));
}

// Classe pour gérer les sessions de manière sécurisée
class SecureSessionHandler {
    private $key;

    public function __construct() {
        $this->key = bin2hex(random_bytes(32));
    }

    public function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(serialize($data), 'AES-256-CBC', $this->key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return unserialize(openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, 0, $iv));
    }
}
?> 