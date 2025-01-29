<?php
class SessionMiddleware {
    private $auth;
    private $excluded_paths = ['/login.php', '/forgot-password.php', '/reset-password.php'];

    public function __construct($auth) {
        $this->auth = $auth;
    }

    public function handle() {
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Dozvoli pristup isključenim putanjama
        if (in_array($current_path, $this->excluded_paths)) {
            return true;
        }

        // Provjeri autentifikaciju
        if (!$this->auth->checkAuth()) {
            header('Location: /login.php');
            exit;
        }

        // Provjeri CSRF token za POST zahtjeve
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCSRFToken()) {
                header('HTTP/1.1 403 Forbidden');
                exit('Invalid CSRF token');
            }
        }

        return true;
    }

    private function validateCSRFToken() {
        return isset($_POST['csrf_token']) && 
               isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
}
?>