<?php
// src/Auth.php
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    public function login($username, $password, $tenantId = null) {
        $driver = Database::getInstance()->getDriver();
        $tenantCol = ($driver === 'sqlite') ? "" : "tenant_id, ";
        
        $sql = "SELECT id, {$tenantCol}username, password_hash, role, branch_id, active FROM users WHERE username = :username";
        $params = [':username' => $username];

        if (!empty($tenantId) && $driver !== 'sqlite') {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        } elseif ($driver !== 'sqlite') {
            // If no tenantId provided on cloud, could be global admin or we might need to handle this
            // Generally, on cloud we expect a tenant if it's a subdomain login
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check Active Status
            if (isset($user['active']) && $user['active'] == 0) {
                 return 'user_inactive';
            }

            // Regenerar ID de sesión para evitar fixation
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tenant_id'] = isset($user['tenant_id']) ? $user['tenant_id'] : null;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['branch_id'] = $user['branch_id'];

            // Log de Actividad
            $this->logActivity($user['id'], 'login', 'Inicio de sesión exitoso');

            // --- LICENCIA BASE CHECK (Solo si tiene sucursal) ---
            if ($user['branch_id']) {
                $stmt = $this->db->prepare("SELECT license_expiry FROM branches WHERE id = ?");
                $stmt->execute([$user['branch_id']]);
                $branch = $stmt->fetch();
                if ($branch && $branch['license_expiry']) {
                    $today = date('Y-m-d');
                    if ($branch['license_expiry'] < $today) {
                        // Licencia Vencida -> Bloquear y Salir
                        $this->logActivity($user['id'], 'login_failed', 'Bloqueo por Licencia Base Vencida');
                        $this->logout(); // Limpiar sesion parcial
                        return 'license_expired'; // Special return code
                    }
                }
            }
            // ----------------------------------------------------
            
            return true;
        }
        return false;
    }

    public function logout() {
        if ($this->isAuthenticated()) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'Cierre de sesión');
        }
        $_SESSION = [];
        session_destroy();
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function isBranchManager() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'branch_manager';
    }

    public function getCurrentUser() {
        if (!$this->isAuthenticated()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'tenant_id' => $_SESSION['tenant_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'branch_id' => $_SESSION['branch_id']
        ];
    }

    /**
     * @param string|array $roles Rol simple o array de roles permitidos
     */
    public function requireRole($roles) {
        if (!$this->isAuthenticated()) {
            $this->redirect('../login.php');
            exit;
        }
        
        $currentRole = $_SESSION['role'] ?? '';
        $allowed = is_array($roles) ? $roles : [$roles];

        if (!in_array($currentRole, $allowed)) {
             $this->redirectUnauthorized();
        }
    }

    private function redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
        } else {
            echo "<script>window.location.href='$url';</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
        }
        exit;
    }

    private function redirectUnauthorized() {
        if ($this->isAdmin() || $this->isBranchManager()) {
            $this->redirect('../admin/dashboard.php');
        } else {
            $this->redirect('../pos/index.php');
        }
    }

    public function logActivity($userId, $action, $details = null) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $this->db->prepare("INSERT INTO user_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $details, $ip]);

            // Tambien actualizar last_activity
            $this->updateLastActivity($userId);
        } catch (Exception $e) {
            // No fallar si el log falla
        }
    }

    public function updateLastActivity($userId) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_activity = " . \Database::nowSql() . " WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {}
    }
}
