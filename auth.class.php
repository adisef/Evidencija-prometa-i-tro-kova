<?php
class Auth {
    private $conn;
    private $session_name = 'evidencija_user';
    private $max_login_attempts = 5;
    private $lockout_time = 900; // 15 minuta u sekundama
    private $session_lifetime = 1800; // 30 minuta u sekundama

    public function __construct($db_connection) {
        $this->conn = $db_connection;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($username, $password) {
        if ($this->isLockedOut($username)) {
            return [
                'success' => false, 
                'message' => 'Račun je privremeno zaključan zbog previše pokušaja prijave.'
            ];
        }

        $stmt = $this->conn->prepare("
            SELECT id, username, password, ime, prezime, email, uloga, status, is_protected 
            FROM korisnici 
            WHERE username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] === 'neaktivan') {
                $this->logLoginAttempt($username, false);
                return ['success' => false, 'message' => 'Korisnički račun nije aktivan.'];
            }

            if (password_verify($password, $user['password'])) {
                // Uspješna prijava
                $this->logLoginAttempt($username, true);
                $this->createSession($user);
                
                // Ažuriraj vrijeme zadnje prijave
                $stmt = $this->conn->prepare("UPDATE korisnici SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();

                return ['success' => true, 'message' => 'Uspješna prijava.'];
            }
        }

        $this->logLoginAttempt($username, false);
        return ['success' => false, 'message' => 'Pogrešno korisničko ime ili lozinka.'];
    }

    private function isLockedOut($username) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_pokusaji 
            WHERE username = ? 
            AND uspjesno = 0 
            AND vrijeme > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->bind_param("si", $username, $this->lockout_time);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['attempts'] >= $this->max_login_attempts;
    }

    private function logLoginAttempt($username, $success) {
        $stmt = $this->conn->prepare("
            INSERT INTO login_pokusaji (username, ip_adresa, uspjesno) 
            VALUES (?, ?, ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ssi", $username, $ip, $success);
        $stmt->execute();
    }

    private function createSession($user) {
        $_SESSION[$this->session_name] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'ime' => $user['ime'],
            'prezime' => $user['prezime'],
            'email' => $user['email'],
            'uloga' => $user['uloga'],
            'is_protected' => $user['is_protected'],
            'last_activity' => time()
        ];
    }

    public function checkAuth() {
        if (!isset($_SESSION[$this->session_name])) {
            return false;
        }

        // Provjeri vrijeme neaktivnosti
        if (time() - $_SESSION[$this->session_name]['last_activity'] > $this->session_lifetime) {
            $this->logout();
            return false;
        }

        $_SESSION[$this->session_name]['last_activity'] = time();
        return true;
    }

    public function hasPermission($required_role) {
        if (!$this->checkAuth()) {
            return false;
        }

        $user_role = $_SESSION[$this->session_name]['uloga'];
        
        // Definirati hijerarhiju uloga
        $role_hierarchy = [
            'super_admin' => ['super_admin', 'admin', 'viewer'],
            'admin' => ['admin', 'viewer'],
            'viewer' => ['viewer']
        ];

        return in_array($required_role, $role_hierarchy[$user_role]);
    }

    public function isSuperAdmin() {
        return $this->checkAuth() && $_SESSION[$this->session_name]['uloga'] === 'super_admin';
    }

    public function canManageUser($user_id) {
        if (!$this->checkAuth()) {
            return false;
        }

        // Dohvati podatke o korisniku kojeg pokušavamo upravljati
        $stmt = $this->conn->prepare("SELECT uloga, is_protected FROM korisnici WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $target_user = $result->fetch_assoc();

        if (!$target_user) {
            return false;
        }

        // Super admin može upravljati svima osim zaštićenim korisnicima
        if ($this->isSuperAdmin()) {
            return !$target_user['is_protected'] || $user_id == $_SESSION[$this->session_name]['id'];
        }

        // Admin može upravljati samo viewer-ima
        if ($_SESSION[$this->session_name]['uloga'] === 'admin') {
            return $target_user['uloga'] === 'viewer';
        }

        return false;
    }

    public function getCurrentUser() {
        return isset($_SESSION[$this->session_name]) ? $_SESSION[$this->session_name] : null;
    }

    public function logout() {
        if (isset($_SESSION[$this->session_name])) {
            unset($_SESSION[$this->session_name]);
        }
        session_destroy();
    }

    public function resetPassword($user_id, $new_password) {
        if (!$this->canManageUser($user_id)) {
            return false;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("UPDATE korisnici SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        return $stmt->execute();
    }

    public function updateUser($user_id, $data) {
        if (!$this->canManageUser($user_id)) {
            return false;
        }

        $allowed_fields = ['ime', 'prezime', 'email', 'status'];
        
        // Super admin može mijenjati i ulogu
        if ($this->isSuperAdmin()) {
            $allowed_fields[] = 'uloga';
        }

        $updates = [];
        $types = "";
        $values = [];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $types .= "s";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $user_id;
        $types .= "i";

        $sql = "UPDATE korisnici SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);

        return $stmt->execute();
    }

    public function createUser($data) {
        // Samo admin i super_admin mogu kreirati korisnike
        if (!$this->hasPermission('admin')) {
            return false;
        }

        // Provjeri obavezna polja
        $required_fields = ['username', 'password', 'ime', 'prezime', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        // Super admin može postaviti bilo koju ulogu, admin samo viewer
        if (!isset($data['uloga'])) {
            $data['uloga'] = 'viewer';
        } elseif (!$this->isSuperAdmin() && $data['uloga'] !== 'viewer') {
            return false;
        }

        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare("
            INSERT INTO korisnici (username, password, ime, prezime, email, uloga, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'aktivan')
        ");

        $stmt->bind_param("ssssss", 
            $data['username'],
            $hashed_password,
            $data['ime'],
            $data['prezime'],
            $data['email'],
            $data['uloga']
        );

        return $stmt->execute();
    }
}
?>